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
        Schema::create('repositories', function (Blueprint $table) {
            $table->id();
            $table->string('owner'); // org名 or ユーザー名
            $table->string('name'); // リポジトリ名
            $table->string('full_name')->unique(); // owner/name
            $table->boolean('active')->default(true);
            $table->unsignedInteger('github_project_number')->nullable(); // GitHub ProjectsV2 のプロジェクト番号
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repositories');
    }
};
