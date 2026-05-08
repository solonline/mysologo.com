<?php

namespace App\Http\Controllers\Dashboard;

use App\DTOs\SettingDTO;
use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settingsService) {}

    public function index()
    {
        $settings = $this->settingsService->all()->keyBy('key');

        return view('dashboard.settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'settings'   => ['required', 'array'],
            'settings.*' => ['nullable', 'string', 'max:2000'],
        ]);

        foreach ($data['settings'] as $key => $value) {
            $this->settingsService->set(new SettingDTO(
                key: $key,
                value: $value ?? ''
            ));
        }

        return redirect()->route('dashboard.settings')
            ->with('success', 'Configurações guardadas com sucesso.');
    }
}
