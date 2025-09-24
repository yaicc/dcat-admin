<?php

namespace Dcat\Admin\Http\Controllers;

use Dcat\Admin\Admin;
use Dcat\Admin\Form;
use Dcat\Admin\Http\Repositories\Administrator;
use Dcat\Admin\Layout\Content;
use Dcat\Admin\Traits\HasFormResponse;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Dcat\Admin\Models\LoginError;

class AuthController extends Controller
{
    use HasFormResponse;

    /**
     * @var string
     */
    protected $view = 'admin::pages.login';

    /**
     * @var string
     */
    protected $redirectTo;

    private function realIp()
    {
      return isset($_SERVER["HTTP_CF_CONNECTING_IP"]) ? $_SERVER["HTTP_CF_CONNECTING_IP"] : request()->ip();
    }

    private function ipBan(Request $request)
    {
      $ip = $this->realIp();
      $window = (int) config('admin.login_errors.window_seconds', 7200);
      $maxErrors = (int) config('admin.login_errors.max_errors', 5);

      $r = LoginError::query()->where('ip', $ip)->first();
      if ($r) {
        // 使用 updated_at 作为最近一次尝试时间
        $expiredAt = now()->subSeconds($window);
        if (! empty($r->updated_at) && $r->updated_at < $expiredAt) {
          // 超过时间窗口则重置计数
          LoginError::query()->where('ip', $ip)->update([
            'errors' => 0,
            'updated_at' => now(),
          ]);
          return false;
        }
        // 在窗口内达到阈值则封禁
        return $r->errors >= $maxErrors;
      }

      return false;
    }

    private function incrementError(Request $request)
    {
      $ip = $this->realIp();
      $window = (int) config('admin.login_errors.window_seconds', 7200);

      $r = LoginError::query()->where('ip', $ip)->first();
      if (!$r) {
        LoginError::query()->create([
          'username' => $request[$this->username()],
          'ip' => $ip,
          'errors' => 1,
          'created_at' => now(),
          'updated_at' => now(),
        ]);
      } else {
        $expiredAt = now()->subSeconds($window);
        if (! empty($r->updated_at) && $r->updated_at < $expiredAt) {
          LoginError::query()->where('ip', $ip)->update([
            'errors' => 1,
            'updated_at' => now(),
          ]);
        } else {
          LoginError::query()->where('ip', $ip)->update([
            'errors' => $r->errors + 1,
            'updated_at' => now(),
          ]);
        }
      }
    }

    private function clearErrorOnSuccess(Request $request)
    {
      $ip = $this->realIp();
      LoginError::query()->where('ip', $ip)->delete();
    }

    /**
     * Show the login page.
     *
     * @return Content|\Illuminate\Http\RedirectResponse
     */
    public function getLogin(Content $content)
    {
        if ($this->guard()->check()) {
            return redirect($this->getRedirectPath());
        }

        $ban = $this->ipBan(request());
        $captchaEnabled = (bool) config('admin.login_captcha', true) && function_exists('captcha_src');

        return $content->full()->body(view($this->view, [
          'ban' => $ban,
          'captchaEnabled' => $captchaEnabled,
        ]));
    }

    /**
     * Handle a login request.
     *
     * @param  Request  $request
     * @return mixed
     */
    public function postLogin(Request $request)
    {
        $ban = $this->ipBan(request());
        if ($ban) {
            return $this->validationErrorsResponse([
                $this->username() => __('admin.ip_ban'),
            ]);
        }

        $credentials = $request->only([$this->username(), 'password', 'captcha']);
        $remember = (bool) $request->input('remember', false);

        /** @var \Illuminate\Validation\Validator $validator */
        $rules = [
            $this->username()   => 'required',
            'password'          => 'required',
        ];
        if ((bool) config('admin.login_captcha', true) && function_exists('captcha')) {
            $rules['captcha'] = 'required|captcha';
        }
        $validator = Validator::make($credentials, $rules);

        if ($validator->fails()) {
            $this->incrementError($request);
            return $this->validationErrorsResponse($validator);
        }

        unset($credentials['captcha']);

        if ($this->guard()->attempt($credentials, $remember)) {
            $this->clearErrorOnSuccess($request);
            return $this->sendLoginResponse($request);
        }

        $this->incrementError($request);

        return $this->validationErrorsResponse([
            $this->username() => $this->getFailedLoginMessage(),
        ]);
    }

    /**
     * User logout.
     *
     * @return Redirect|string
     */
    public function getLogout(Request $request)
    {
        $this->guard()->logout();

        $request->session()->invalidate();

        $path = admin_url('auth/login');
        if ($request->pjax()) {
            return "<script>location.href = '$path';</script>";
        }

        return redirect($path);
    }

    /**
     * User setting page.
     *
     * @param  Content  $content
     * @return Content
     */
    public function getSetting(Content $content)
    {
        $form = $this->settingForm();
        $form->tools(
            function (Form\Tools $tools) {
                $tools->disableList();
            }
        );

        return $content
            ->title(trans('admin.user_setting'))
            ->body($form->edit(Admin::user()->getKey()));
    }

    /**
     * Update user setting.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function putSetting()
    {
        $form = $this->settingForm();

        if (! $this->validateCredentialsWhenUpdatingPassword()) {
            $form->responseValidationMessages('old_password', trans('admin.old_password_error'));
        }

        return $form->update(Admin::user()->getKey());
    }

    protected function validateCredentialsWhenUpdatingPassword()
    {
        $user = Admin::user();

        $oldPassword = \request('old_password');
        $newPassword = \request('password');

        if (
            (! $newPassword)
            || ($newPassword === $user->getAuthPassword())
        ) {
            return true;
        }

        if (! $oldPassword) {
            return false;
        }

        return $this->guard()
            ->getProvider()
            ->validateCredentials($user, ['password' => $oldPassword]);
    }

    /**
     * Model-form for user setting.
     *
     * @return Form
     */
    protected function settingForm()
    {
        return new Form(new Administrator(), function (Form $form) {
            $form->action(admin_url('auth/setting'));

            $form->disableCreatingCheck();
            $form->disableEditingCheck();
            $form->disableViewCheck();

            $form->tools(function (Form\Tools $tools) {
                $tools->disableView();
                $tools->disableDelete();
            });

            $form->display('username', trans('admin.username'));
            $form->text('name', trans('admin.name'))->required();
            $form->image('avatar', trans('admin.avatar'))->autoUpload();

            $form->password('old_password', trans('admin.old_password'));

            $form->password('password', trans('admin.password'))
                ->minLength(5)
                ->maxLength(20)
                ->customFormat(function ($v) {
                    if ($v == $this->password) {
                        return;
                    }

                    return $v;
                });
            $form->password('password_confirmation', trans('admin.password_confirmation'))->same('password');

            $form->ignore(['password_confirmation', 'old_password']);

            $form->saving(function (Form $form) {
                if ($form->password && $form->model()->password != $form->password) {
                    $form->password = bcrypt($form->password);
                }

                if (! $form->password) {
                    $form->deleteInput('password');
                }
            });

            $form->saved(function (Form $form) {
                return $form
                    ->response()
                    ->success(trans('admin.update_succeeded'))
                    ->redirect('auth/setting');
            });
        });
    }

    /**
     * @return string|\Symfony\Component\Translation\TranslatorInterface
     */
    protected function getFailedLoginMessage()
    {
        return Lang::has('admin.auth_failed')
            ? trans('admin.auth_failed')
            : 'These credentials do not match our records.';
    }

    /**
     * Get the post login redirect path.
     *
     * @return string
     */
    protected function getRedirectPath()
    {
        return $this->redirectTo ?: admin_url('/');
    }

    /**
     * Send the response after the user was authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function sendLoginResponse(Request $request)
    {
        $request->session()->regenerate();

        $path = $this->getRedirectPath();

        return $this->response()
            ->success(trans('admin.login_successful'))
            ->locationToIntended($path)
            ->locationIf(Admin::app()->getEnabledApps(), $path)
            ->send();
    }

    /**
     * Get the login username to be used by the controller.
     *
     * @return string
     */
    protected function username()
    {
        return 'username';
    }

    /**
     * Get the guard to be used during authentication.
     *
     * @return \Illuminate\Contracts\Auth\StatefulGuard|GuardHelpers
     */
    protected function guard()
    {
        return Admin::guard();
    }
}
