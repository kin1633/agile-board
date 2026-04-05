<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->enum('type', ['full_leave', 'half_am', 'half_pm', 'early_leave', 'late_arrival']);
            // 早退・遅刻の場合のみ時刻を記録する（全休・半休はNULL）
            $table->time('time')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            // 同一メンバー・日付・種別は1件のみ許容
            $table->unique(['member_id', 'date', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};
