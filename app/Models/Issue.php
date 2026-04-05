<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * GitHub Issue を表すモデル。
 *
 * 以下の項目はユーザーが手動で設定するため、GitHub 同期（GitHubSyncService）で上書きしてはならない:
 * - story_points: ストーリーポイント
 * - exclude_velocity: ベロシティ除外フラグ
 * - estimated_hours: 予定工数（タスクレベルの工数管理）
 * - actual_hours: 実績工数（タスクレベルの工数管理）
 */
#[Fillable([
    'repository_id', 'sprint_id', 'epic_id', 'parent_issue_id', 'github_issue_number',
    'title', 'state', 'project_status', 'closed_at', 'assignee_login', 'story_points', 'exclude_velocity',
    'estimated_hours', 'actual_hours', 'synced_at',
])]
class Issue extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'exclude_velocity' => 'boolean',
            'closed_at' => 'datetime',
            'synced_at' => 'datetime',
            'estimated_hours' => 'decimal:2',
            'actual_hours' => 'decimal:2',
        ];
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    /** 親イシュー（このイシューがサブイシューの場合） */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Issue::class, 'parent_issue_id');
    }

    /** サブイシュー一覧（このイシューが親の場合） */
    public function subIssues(): HasMany
    {
        return $this->hasMany(Issue::class, 'parent_issue_id');
    }

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    public function epic(): BelongsTo
    {
        return $this->belongsTo(Epic::class);
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'issue_labels');
    }

    /** 日次実績ログ（ワークログ） */
    public function workLogs(): HasMany
    {
        return $this->hasMany(WorkLog::class);
    }
}
