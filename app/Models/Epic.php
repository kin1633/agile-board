<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'description', 'status', 'github_status', 'due_date', 'priority', 'started_at'])]
class Epic extends Model
{
    use HasFactory;

    /** @var array<string, string> */
    protected $casts = [
        'due_date' => 'date:Y-m-d',
        'started_at' => 'date:Y-m-d',
    ];

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }
}
