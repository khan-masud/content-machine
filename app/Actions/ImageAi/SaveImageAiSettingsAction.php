<?php

namespace App\Actions\ImageAi;

use App\Data\ImageAi\ImageAiSettingsData;
use App\Models\Workspace;
use App\Support\ImageAi\ImageAiConfig;

class SaveImageAiSettingsAction
{
    public function handle(Workspace $workspace, ImageAiSettingsData $data): void
    {
        ImageAiConfig::write(
            $workspace,
            $data->cloudflareAccountId,
            $data->cloudflareApiToken,
            $data->pollinationsEnabled,
        );
    }
}
