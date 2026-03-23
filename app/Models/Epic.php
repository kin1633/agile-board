<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'description', 'status'])]
class Epic extends Model
{
    use HasFactory;

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }
}
