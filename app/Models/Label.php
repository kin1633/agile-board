<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'exclude_velocity'])]
class Label extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'exclude_velocity' => 'boolean',
        ];
    }

    public function issues(): BelongsToMany
    {
        return $this->belongsToMany(Issue::class, 'issue_labels');
    }
}
