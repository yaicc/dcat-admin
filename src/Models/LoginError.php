<?php

namespace Dcat\Admin\Models;

use Illuminate\Database\Eloquent\Model;

class LoginError extends Model
{
    /**
     * 动态从配置中获取表名，保持与 config('admin.database.login_errors_table') 一致
     */
    public function getTable()
    {
        return config('admin.database.login_errors_table');
    }

    protected $guarded = [];

    public $timestamps = true;
}


