<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkLogCategoryGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'sort_order',
        'is_billable',
    ];

    protected $casts = [
        'is_billable' => 'boolean',
    ];

    /**
     * このグループに属する実績種別を返す。
     */
    public function categories(): HasMany
    {
        return $this->hasMany(WorkLogCategory::class);
    }
}
