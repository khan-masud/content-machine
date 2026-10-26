<?php

namespace App\Http\Controllers\Settings;

use App\Actions\ImageAi\SaveImageAiSettingsAction;
use App\Data\ImageAi\ImageAiSettingsData;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\Concerns\AuthorizesWorkspaceSettings;
use App\Http\Requests\Settings\UpdateImageAiSettingsRequest;
use App\Support\ImageAi\ImageAiConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ImageAiSettingsController extends Controller
{
    use AuthorizesWorkspaceSettings;

    public function edit(Request $request): Response
    {
        $workspace = $this->currentWorkspace();
        $this->authorizeWorkspaceAdmin($request, $workspace);
        $config = ImageAiConfig::fromWorkspace($workspace);

        return Inertia::render('workspace-settings/image-ai', [
            'cloudflareAccountId' => $config->cloudflareAccountId() ?? '',
            'cloudflareConfigured' => $config->cloudflareConfigured(),
            'pollinationsEnabled' => $config->pollinationsEnabled(),
        ]);
    }

    public function update(UpdateImageAiSettingsRequest $request, SaveImageAiSettingsAction $action): RedirectResponse
    {
        $action->handle($this->currentWorkspace(), ImageAiSettingsData::fromRequest($request));

        return to_route('settings.image-ai.edit')->with('success', 'Image AI settings saved.');
    }
}
