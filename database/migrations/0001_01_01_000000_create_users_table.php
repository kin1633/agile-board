<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // GitHub OAuth専用のusersテーブル（パスワード認証なし）
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('github_id')->unique();
            $table->string('name');
            $table->string('avatar')->nullable();
            $table->text('github_token'); // encrypted キャストで暗号化保存
            $table->rememberToken(); // Auth::login(remember: true) で利用
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('sessions');
    }
};
