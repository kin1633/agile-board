<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * GitHub Issue を表すモデル。
 *
 * story_points と exclude_velocity はユーザーが手動で設定する項目のため、
 * GitHub 同期（GitHubSyncService）で上書きしてはならない。
 */
#[Fillable([
    'repository_id', 'sprint_id', 'epic_id', 'github_issue_number',
    'title', 'state', 'closed_at', 'assignee_login', 'story_points', 'exclude_velocity', 'synced_at',
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
        ];
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
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
}
