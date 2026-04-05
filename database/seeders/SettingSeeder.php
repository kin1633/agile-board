<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        // 1人日の基準時間（デフォルト7時間）
        Setting::set('hours_per_person_day', '7');

        // GitHub Projects の Sprint Iteration フィールド名（デフォルト: "Sprint"）
        Setting::set('sprint_iteration_field', 'Sprint');
    }
}
