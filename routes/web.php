<?php

use App\Http\Controllers\Auth\GitHubController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EpicController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\MilestoneController;
use App\Http\Controllers\RetrospectiveController;
use App\Http\Controllers\SprintController;
use App\Http\Controllers\SyncController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// ルートアクセス: 未認証なら /login、認証済みなら /dashboard へリダイレクト
Route::get('/', function () {
    return Auth::check()
        ? redirect('/dashboard')
        : redirect('/login');
});

// GitHub OAuth 認証ルート（認証不要）
Route::get('/auth/github', [GitHubController::class, 'redirect'])->name('auth.github');
Route::get('/auth/github/callback', [GitHubController::class, 'callback'])->name('auth.github.callback');

// ログアウト
Route::post('/logout', LogoutController::class)->name('logout')->middleware('auth');

// 認証が必要なルート
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // スプリント
    Route::get('/sprints', [SprintController::class, 'index'])->name('sprints.index');
    Route::get('/sprints/{sprint}', [SprintController::class, 'show'])->name('sprints.show');

    // マイルストーン（自動生成のため create/store/destroy ルートは存在しない）
    Route::get('/milestones', [MilestoneController::class, 'index'])->name('milestones.index');
    Route::get('/milestones/{milestone}', [MilestoneController::class, 'show'])->name('milestones.show');
    Route::get('/milestones/{milestone}/edit', [MilestoneController::class, 'edit'])->name('milestones.edit');
    Route::put('/milestones/{milestone}', [MilestoneController::class, 'update'])->name('milestones.update');

    // スプリントへのマイルストーン紐付け
    Route::patch('/sprints/{sprint}/milestone', [SprintController::class, 'assignMilestone'])->name('sprints.milestone');

    // エピック
    // /epics/{epic} より前に定義しないとルートが衝突するため先頭に配置
    Route::get('/epics/export', [EpicController::class, 'export'])->name('epics.export');
    Route::get('/epics', [EpicController::class, 'index'])->name('epics.index');
    Route::post('/epics', [EpicController::class, 'store'])->name('epics.store');
    Route::put('/epics/{epic}', [EpicController::class, 'update'])->name('epics.update');
    Route::delete('/epics/{epic}', [EpicController::class, 'destroy'])->name('epics.destroy');

    // レトロスペクティブ
    Route::get('/retrospectives', [RetrospectiveController::class, 'index'])->name('retrospectives.index');
    Route::post('/retrospectives', [RetrospectiveController::class, 'store'])->name('retrospectives.store');
    Route::put('/retrospectives/{retrospective}', [RetrospectiveController::class, 'update'])->name('retrospectives.update');
    Route::delete('/retrospectives/{retrospective}', [RetrospectiveController::class, 'destroy'])->name('retrospectives.destroy');

    // Issue
    Route::get('/stories', [IssueController::class, 'index'])->name('issues.index');
    Route::patch('/issues/{issue}', [IssueController::class, 'update'])->name('issues.update');

    // GitHub 同期
    Route::post('/sync', SyncController::class)->name('sync');
});

require __DIR__.'/settings.php';
