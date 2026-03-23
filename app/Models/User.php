<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * 認証ユーザーを表すモデル。
 *
 * 本アプリは GitHub OAuth 専用のため、メールアドレスやパスワードは持たない。
 */
#[Fillable(['github_id', 'name', 'avatar', 'github_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * キャスト定義を返す。
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'github_token' => 'encrypted', // GitHubトークンを暗号化保存
        ];
    }

    public function member(): HasOne
    {
        return $this->hasOne(Member::class);
    }
}
