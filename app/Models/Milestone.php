<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['repository_id', 'github_milestone_id', 'github_iteration_id', 'title', 'due_on', 'state', 'synced_at'])]
class Milestone extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'synced_at' => 'datetime',
        ];
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    public function sprint(): HasOne
    {
        return $this->hasOne(Sprint::class);
    }
}
