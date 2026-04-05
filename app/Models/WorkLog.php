<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 日次作業実績を記録するモデル。
 *
 * category が null の場合は開発作業（タスクへの直接作業時間）。
 * PM管理工数・保守運用工数は category で区別し、エピックのみ任意紐付け。
 */
#[Fillable(['date', 'start_time', 'end_time', 'member_id', 'epic_id', 'issue_id', 'category', 'hours', 'note'])]
class WorkLog extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'hours' => 'decimal:2',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function epic(): BelongsTo
    {
        return $this->belongsTo(Epic::class);
    }

    /** 紐付くイシュー（開発作業の場合のタスクまたはストーリー） */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }
}
