<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkLogCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'value',
        'label',
        'work_log_category_group_id',
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
     * この種別が属するグループを返す。
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(WorkLogCategoryGroup::class, 'work_log_category_group_id');
    }

    /**
     * アクティブなカテゴリを並び順で取得するスコープ。
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
