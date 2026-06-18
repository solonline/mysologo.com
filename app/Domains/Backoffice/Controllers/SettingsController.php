<?php

namespace App\Domains\Backoffice\Controllers;

use App\Domains\Backoffice\Requests\UpdateSettingsRequest;
use App\Domains\Backoffice\Services\SettingsManagementService;
use App\Http\Controllers\Controller;

class SettingsController extends Controller
{
    public function __construct(
        protected SettingsManagementService $settings,
    ) {}

    public function index()
    {
        return view('dashboard.settings.index', [
            'settings' => $this->settings->allKeyedByKey(),
        ]);
    }

    public function update(UpdateSettingsRequest $request)
    {
        $this->settings->updateMany($request->validated('settings'));

        return redirect()->route('dashboard.settings')
            ->with('success', 'Configurações guardadas com sucesso.');
    }
}
