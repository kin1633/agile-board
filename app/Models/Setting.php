<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    // settings テーブルは key が主キーのためタイムスタンプなし
    public $timestamps = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    /**
     * 設定値を取得する。キーが存在しない場合はデフォルト値を返す。
     */
    public static function get(string $key, string $default = ''): string
    {
        return static::find($key)?->value ?? $default;
    }

    /**
     * 設定値を保存する。キーが存在しない場合は新規作成、存在する場合は更新する。
     */
    public static function set(string $key, string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
