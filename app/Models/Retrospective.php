<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sprint_id', 'type', 'content'])]
class Retrospective extends Model
{
    use HasFactory;

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }
}
