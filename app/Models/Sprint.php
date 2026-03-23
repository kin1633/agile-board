<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * スプリントを表すモデル。
 *
 * start_date と working_days はユーザーが手動で設定する項目のため、
 * GitHub 同期（GitHubSyncService）で上書きしてはならない。
 */
#[Fillable(['milestone_id', 'title', 'start_date', 'end_date', 'working_days', 'state'])]
class Sprint extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function retrospectives(): HasMany
    {
        return $this->hasMany(Retrospective::class);
    }

    /**
     * ポイントベースのベロシティを計算する。
     * exclude_velocity=true のIssueとラベルは除外する。
     */
    public function pointVelocity(): int
    {
        return $this->issues()
            ->where('state', 'closed')
            ->where('exclude_velocity', false)
            ->whereDoesntHave('labels', fn ($q) => $q->where('exclude_velocity', true))
            ->sum('story_points');
    }

    /**
     * Issueベースのベロシティを計算する。
     * exclude_velocity=true のIssueとラベルは除外する。
     */
    public function issueVelocity(): int
    {
        return $this->issues()
            ->where('state', 'closed')
            ->where('exclude_velocity', false)
            ->whereDoesntHave('labels', fn ($q) => $q->where('exclude_velocity', true))
            ->count();
    }
}
