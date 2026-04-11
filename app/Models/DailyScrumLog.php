<?php

namespace App\Models;

use Database\Factories\DailyScrumLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * デイリースクラムの進捗記録モデル。
 *
 * 記録単位: 日付 × タスク（子Issue） × メンバー（1日1レコード）
 */
#[Fillable(['date', 'issue_id', 'member_id', 'progress_percentage', 'memo'])]
class DailyScrumLog extends Model
{
    /** @use HasFactory<DailyScrumLogFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'progress_percentage' => 'integer',
        ];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
