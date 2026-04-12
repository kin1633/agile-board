<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'repository_id', 'issue_id', 'github_pr_number', 'title', 'state',
    'author_login', 'review_state', 'merged_at', 'head_branch', 'base_branch',
    'github_url', 'synced_at', 'ci_status',
])]
class PullRequest extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'merged_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    /** 関連するIssue（ストーリー） */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }
}
