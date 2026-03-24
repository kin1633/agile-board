<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['owner', 'name', 'full_name', 'active', 'github_project_number', 'synced_at'])]
class Repository extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }
}
