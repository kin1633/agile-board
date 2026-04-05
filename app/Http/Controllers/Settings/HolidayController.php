<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class HolidayController extends Controller
{
    /**
     * 年フィルタ付きで祝日一覧を表示する。
     */
    public function index(Request $request): Response
    {
        $year = (int) $request->query('year', now()->year);

        $holidays = Holiday::query()
            ->whereYear('date', $year)
            ->orderBy('date')
            ->get(['id', 'date', 'name', 'type'])
            ->map(fn (Holiday $h) => [
                'id' => $h->id,
                'date' => $h->date->toDateString(),
                'name' => $h->name,
                'type' => $h->type,
            ]);

        return Inertia::render('settings/holidays', [
            'holidays' => $holidays,
            'year' => $year,
        ]);
    }

    /**
     * 外部APIから指定年の国民の祝日を一括インポートする。
     *
     * holidays-jp.github.io の公開APIを利用。重複インポートは name のみ上書きし安全に再実行できる。
     */
    public function import(Request $request): RedirectResponse
    {
        $year = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2030'],
        ])['year'];

        $response = Http::get("https://holidays-jp.github.io/api/v1/{$year}/date.json");

        if ($response->failed()) {
            return back()->withErrors(['year' => '祝日APIの取得に失敗しました。時間をおいて再試行してください。']);
        }

        $rows = collect($response->json())
            ->map(fn (string $name, string $date) => [
                'date' => $date,
                'name' => $name,
                'type' => 'national',
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->values()
            ->all();

        Holiday::upsert($rows, ['date'], ['name', 'updated_at']);

        return back()->with('success', "{$year}年の祝日をインポートしました（".count($rows).'件）');
    }

    /**
     * 現場独自の休日を手動で追加する。
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date', 'unique:holidays,date'],
            'name' => ['required', 'string', 'max:100'],
        ]);

        Holiday::create([
            'date' => $validated['date'],
            'name' => $validated['name'],
            'type' => 'site_specific',
        ]);

        return back()->with('success', '現場休日を追加しました');
    }

    /**
     * 祝日を削除する。
     */
    public function destroy(Holiday $holiday): RedirectResponse
    {
        $holiday->delete();

        return back()->with('success', '休日を削除しました');
    }
}
