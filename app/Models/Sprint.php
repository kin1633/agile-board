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
#[Fillable(['milestone_id', 'github_iteration_id', 'title', 'start_date', 'end_date', 'working_days', 'iteration_duration_days', 'state', 'goal'])]
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

    public function sprintReviews(): HasMany
    {
        return $this->hasMany(SprintReview::class);
    }

    /**
     * ポイントベースのベロシティを計算する。
     * Issue の exclude_velocity=true、またはラベルの include_velocity=false の Issue は除外する。
     */
    public function pointVelocity(): int
    {
        return $this->issues()
            ->where('state', 'closed')
            ->where('exclude_velocity', false)
            ->whereDoesntHave('labels', fn ($q) => $q->where('include_velocity', false))
            ->sum('story_points');
    }

    /**
     * Issueベースのベロシティを計算する。
     * Issue の exclude_velocity=true、またはラベルの include_velocity=false の Issue は除外する。
     */
    public function issueVelocity(): int
    {
        return $this->issues()
            ->where('state', 'closed')
            ->where('exclude_velocity', false)
            ->whereDoesntHave('labels', fn ($q) => $q->where('include_velocity', false))
            ->count();
    }
}
