<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sprint_id', 'issue_id', 'type', 'content', 'outcome'])]
class SprintReview extends Model
{
    use HasFactory;

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    /** 記録に紐付けられたIssue（任意） */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }
}
