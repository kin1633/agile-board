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
        // 複数リポジトリをまたいで同名のラベルは統合する設計
        Schema::create('labels', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            // exclude_velocity から意味を反転して include_velocity に変更（true=ベロシティ計算に含める）
            $table->boolean('include_velocity')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('labels');
    }
};
