<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Slack / Microsoft Teams 通知設定コントローラー。
 *
 * Incoming Webhook URL の設定と通知テストを管理する。
 */
class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('settings/notifications', [
            'teamsWebhookUrl' => Setting::get('teams_webhook_url'),
            'slackWebhookUrl' => Setting::get('slack_webhook_url'),
            'events' => [
                'sprint_started' => Setting::get('notify_sprint_start', '1') === '1',
                'sprint_completed' => Setting::get('notify_sprint_complete', '1') === '1',
                'blocker_created' => Setting::get('notify_blocker', '1') === '1',
                'daily_reminder' => Setting::get('notify_daily_reminder', '0') === '1',
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'teams_webhook_url' => ['nullable', 'url', 'max:500'],
            'slack_webhook_url' => ['nullable', 'url', 'max:500'],
            'events' => ['nullable', 'array'],
            'events.sprint_started' => ['boolean'],
            'events.sprint_completed' => ['boolean'],
            'events.blocker_created' => ['boolean'],
            'events.daily_reminder' => ['boolean'],
        ]);

        Setting::set('teams_webhook_url', $validated['teams_webhook_url'] ?? '');
        Setting::set('slack_webhook_url', $validated['slack_webhook_url'] ?? '');

        $events = $validated['events'] ?? [];
        Setting::set('notify_sprint_start', ($events['sprint_started'] ?? false) ? '1' : '0');
        Setting::set('notify_sprint_complete', ($events['sprint_completed'] ?? false) ? '1' : '0');
        Setting::set('notify_blocker', ($events['blocker_created'] ?? false) ? '1' : '0');
        Setting::set('notify_daily_reminder', ($events['daily_reminder'] ?? false) ? '1' : '0');

        return redirect()->route('settings.notifications');
    }

    /**
     * テスト通知を送信する。
     */
    public function test(Request $request): RedirectResponse
    {
        $this->notificationService->notifySprintStarted(
            'テストスプリント',
            '通知設定のテスト',
            now()->addWeeks(2)->toDateString()
        );

        return back()->with('status', 'テスト通知を送信しました');
    }
}
