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

        // Epic の github_status 集計に使う優先度順リスト（先頭ほど優先度が高い）
        // GitHub 同期時に未知の値は末尾に自動追加される
        Setting::firstOrCreate(
            ['key' => 'epic_github_status_order'],
            ['value' => json_encode(['In Progress', 'On Hold', 'Todo', 'Cancelled', 'Done'])]
        );
    }
}
