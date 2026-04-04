<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['year', 'month', 'title', 'goal', 'status', 'started_at', 'due_date'])]
class Milestone extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'started_at' => 'date',
            'due_date' => 'date',
        ];
    }

    /**
     * 配下のスプリント一覧。
     * 1つのマイルストーンに複数スプリントを紐付けられる。
     */
    public function sprints(): HasMany
    {
        return $this->hasMany(Sprint::class);
    }
}
