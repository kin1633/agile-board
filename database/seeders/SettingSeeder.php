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

        // GitHub Projects の Iteration フィールド名マッピング
        // Sprint フィールド名 → アプリ内のスプリント
        Setting::set('sprint_iteration_field', 'Sprint');
        // Monthly フィールド名 → アプリ内のマイルストーン（月次目標）
        Setting::set('monthly_iteration_field', 'Monthly');
    }
}
