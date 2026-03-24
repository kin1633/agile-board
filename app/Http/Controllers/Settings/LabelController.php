<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Label;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LabelController extends Controller
{
    public function index(): Response
    {
        $labels = Label::orderBy('name')
            ->get()
            ->map(fn (Label $l) => [
                'id' => $l->id,
                'name' => $l->name,
                'include_velocity' => $l->include_velocity,
            ]);

        return Inertia::render('settings/labels', compact('labels'));
    }

    public function update(Request $request, Label $label): RedirectResponse
    {
        $validated = $request->validate([
            'include_velocity' => ['required', 'boolean'],
        ]);

        $label->update($validated);

        return redirect()->route('settings.labels');
    }
}
