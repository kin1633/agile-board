<?php

namespace App\Models;

use Database\Factories\AttendanceLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLog extends Model
{
    /** @use HasFactory<AttendanceLogFactory> */
    use HasFactory;

    protected $fillable = ['member_id', 'date', 'type', 'time', 'note'];

    /** SQLiteのTEXT型との互換性のため日付部分のみ保存する */
    protected $dateFormat = 'Y-m-d';

    /** @var array<string, string> */
    protected $casts = [
        'date' => 'date',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
