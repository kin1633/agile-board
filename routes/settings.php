<?php

use App\Http\Controllers\Settings\GeneralController;
use App\Http\Controllers\Settings\LabelController;
use App\Http\Controllers\Settings\MemberController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\RepositoryController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/repositories');

    // 一般設定（1人日の基準時間など）
    Route::get('settings/general', [GeneralController::class, 'index'])->name('settings.general');
    Route::patch('settings/general', [GeneralController::class, 'update'])->name('settings.general.update');

    // リポジトリ管理
    Route::get('settings/repositories', [RepositoryController::class, 'index'])->name('settings.repositories');
    // GitHub候補取得は {repository} より先に定義しないとルートが衝突する
    Route::get('settings/repositories/github', [RepositoryController::class, 'githubRepositories'])->name('settings.repositories.github');
    Route::post('settings/repositories', [RepositoryController::class, 'store'])->name('settings.repositories.store');
    Route::patch('settings/repositories/{repository}', [RepositoryController::class, 'update'])->name('settings.repositories.update');

    // メンバー管理（メンバーはGitHub OAuth認証時に自動作成されるため、編集のみ）
    Route::get('settings/members', [MemberController::class, 'index'])->name('settings.members');
    Route::patch('settings/members/{member}', [MemberController::class, 'update'])->name('settings.members.update');

    // ラベル管理
    Route::get('settings/labels', [LabelController::class, 'index'])->name('settings.labels');
    Route::patch('settings/labels/{label}', [LabelController::class, 'update'])->name('settings.labels.update');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

});
