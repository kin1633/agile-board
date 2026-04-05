<?php

namespace App\Models;

use Database\Factories\HolidayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    /** @use HasFactory<HolidayFactory> */
    use HasFactory;

    protected $fillable = ['date', 'name', 'type'];

    /**
     * SQLiteはネイティブのDATE型を持たないため、時刻部分なしで保存しないと
     * WHERE比較やupsertのON CONFLICT検出が失敗する
     */
    protected $dateFormat = 'Y-m-d';

    /** @var array<string, string> */
    protected $casts = [
        'date' => 'date',
    ];
}
