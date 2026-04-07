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

        // Epic の github_priority 集計に使う優先度順リスト（先頭ほど優先度が高い）
        // GitHub 同期時に未知の値は末尾に自動追加される
        // デフォルトは p0 > p1 > p2（GitHub Projects 標準のシングルセレクト値）
        Setting::firstOrCreate(
            ['key' => 'epic_github_priority_order'],
            ['value' => json_encode(['p0', 'p1', 'p2'])]
        );

        // リリースバッファ日数: 開発完了後リリースまでの営業日数（デフォルト0）
        // due_date のこの日数分前を「開発完了目標日」とし、着手目安日を算出する
        Setting::firstOrCreate(
            ['key' => 'release_buffer_days'],
            ['value' => '0']
        );
    }
}
