<style>
    .login-box {
        margin-top: -10rem;
        padding: 5px;
    }
    .login-card-body {
        padding: 1.5rem 1.8rem 1.6rem;
    }
    .card, .card-body {
        border-radius: .25rem
    }
    .login-btn {
        padding-left: 2rem!important;;
        padding-right: 1.5rem!important;
    }
    .content {
        overflow-x: hidden;
    }
    .form-group .control-label {
        text-align: left;
    }
    /* captcha inline layout */
    .captcha-flex {
        display: flex;
        align-items: center;
        gap: .75rem;
    }
    .captcha-flex .captcha-input {
        flex: 1 1 auto;
        position: relative;
    }
    img.captcha {
        height: auto; /* 由脚本在运行时匹配输入框高度 */
        border-radius: .25rem;
    }
</style>

<div class="login-page bg-40">
    <div class="login-box">
        <div class="login-logo mb-2">
            {{ config('admin.name') }}
        </div>
        <div class="card">
            <div class="card-body login-card-body shadow-100">
                <p class="login-box-msg mt-1 mb-1">{{ __('admin.welcome_back') }}</p>
                @if($ban)
                    <div class="alert alert-danger">
                        <p>{{ __('admin.ip_ban') }}</p>
                    </div>
                @endif

                <form id="login-form" method="POST" action="{{ admin_url('auth/login') }}">

                    <input type="hidden" name="_token" value="{{ csrf_token() }}"/>

                    <fieldset class="form-label-group form-group position-relative has-icon-left">
                        <input
                                type="text"
                                class="form-control {{ $errors->has('username') ? 'is-invalid' : '' }}"
                                name="username"
                                placeholder="{{ trans('admin.username') }}"
                                value="{{ old('username') }}"
                                required
                                autofocus
                        >

                        <div class="form-control-position">
                            <i class="feather icon-user"></i>
                        </div>

                        <label for="email">{{ trans('admin.username') }}</label>

                        <div class="help-block with-errors"></div>
                        @if($errors->has('username'))
                            <span class="invalid-feedback text-danger" role="alert">
                                            @foreach($errors->get('username') as $message)
                                    <span class="control-label" for="inputError"><i class="feather icon-x-circle"></i> {{$message}}</span><br>
                                @endforeach
                                        </span>
                        @endif
                    </fieldset>

                    <fieldset class="form-label-group form-group position-relative has-icon-left">
                        <input
                                minlength="5"
                                maxlength="20"
                                id="password"
                                type="password"
                                class="form-control {{ $errors->has('password') ? 'is-invalid' : '' }}"
                                name="password"
                                placeholder="{{ trans('admin.password') }}"
                                required
                                autocomplete="current-password"
                        >

                        <div class="form-control-position">
                            <i class="feather icon-lock"></i>
                        </div>
                        <label for="password">{{ trans('admin.password') }}</label>

                        <div class="help-block with-errors"></div>
                        @if($errors->has('password'))
                            <span class="invalid-feedback text-danger" role="alert">
                                            @foreach($errors->get('password') as $message)
                                    <span class="control-label" for="inputError"><i class="feather icon-x-circle"></i> {{$message}}</span><br>
                                @endforeach
                                            </span>
                        @endif

                    </fieldset>

                    @if(!empty($captchaEnabled))
                    <fieldset class="form-label-group form-group">
                        <div class="captcha-flex">
                            <div class="captcha-input position-relative has-icon-left">
                                <input
                                        id="captcha"
                                        type="text"
                                        class="form-control {{ $errors->has('captcha') ? 'is-invalid' : '' }}"
                                        name="captcha"
                                        placeholder="{{ trans('admin.captcha') }}"
                                        required
                                        autocomplete="captcha"
                                >
                                <div class="form-control-position">
                                    <i class="feather icon-image"></i>
                                </div>
                                <label for="captcha" class="d-none">{{ trans('admin.captcha') }}</label>
                            </div>
                            @php($__captcha_src = function_exists('captcha_src') ? captcha_src() : '')
                            @if($__captcha_src)
                            <img class="captcha" src="{{ $__captcha_src }}" title="{{ trans('admin.reload') }}" style="cursor:pointer" onclick="this.src='{{ $__captcha_src }}&r='+Math.random()">
                            @endif
                        </div>

                        <div class="help-block with-errors"></div>
                        @if($errors->has('captcha'))
                            <span class="invalid-feedback text-danger" role="alert">
                                @foreach($errors->get('captcha') as $message)
                                    <span class="control-label" for="inputError"><i class="feather icon-x-circle"></i> {{$message}}</span><br>
                                @endforeach
                            </span>
                        @endif
                    </fieldset>
                    @endif

                    <div class="form-group d-flex justify-content-between align-items-center">
                        <div class="text-left">
                            @if(config('admin.auth.remember'))
                            <fieldset class="checkbox">
                                <div class="vs-checkbox-con vs-checkbox-primary">
                                    <input id="remember" name="remember"  value="1" type="checkbox" {{ old('remember') ? 'checked' : '' }}>
                                    <span class="vs-checkbox">
                                                        <span class="vs-checkbox--check">
                                                          <i class="vs-icon feather icon-check"></i>
                                                        </span>
                                                    </span>
                                    <span> {{ trans('admin.remember_me') }}</span>
                                </div>
                            </fieldset>
                            @endif
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary float-right login-btn">

                        {{ __('admin.login') }}
                        &nbsp;
                        <i class="feather icon-arrow-right"></i>
                    </button>
                </form>

            </div>
        </div>
    </div>
</div>

<script>
Dcat.ready(function () {
    // ajax表单提交
    $('#login-form').form({
        validate: true,
    });
    // 点击验证码图片刷新，防止缓存
    $(document).on('click', 'img.captcha', function() {
        var src = $(this).attr('src').split('&r=')[0];
        $(this).attr('src', src + '&r=' + Math.random());
    });
    // 统一验证码图片与输入框高度
    var adjustCaptchaHeight = function() {
        var $input = $('#captcha');
        var $img = $('img.captcha');
        if ($input.length && $img.length) {
            var h = $input.outerHeight();
            if (h) {
                $img.css('height', h + 'px');
            }
        }
    };
    adjustCaptchaHeight();
    $(window).on('resize', adjustCaptchaHeight);
});
</script>
