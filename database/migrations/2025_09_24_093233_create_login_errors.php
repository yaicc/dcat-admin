<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection()
    {
        return $this->config('database.connection') ?: config('database.default');
    }

    public function config($key)
    {
        return config('admin.'.$key);
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create($this->config('database.login_errors_table'), function (Blueprint $table) {
            $table->id();
            $table->string('username');
            $table->string('ip');
            $table->integer('errors');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists($this->config('database.login_errors_table'));
    }
};