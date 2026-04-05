<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkLogCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'value',
        'label',
        'group_name',
        'color',
        'is_billable',
        'is_default',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_billable' => 'boolean',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * アクティブなカテゴリを並び順で取得するスコープ。
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
