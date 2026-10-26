<?php

namespace App\Support\Pexels;

use App\Contracts\PexelsProvider;
use App\Contracts\PexelsProviderFactory;
use App\Models\Workspace;

class HttpPexelsProviderFactory implements PexelsProviderFactory
{
    public function forWorkspace(Workspace $workspace): ?PexelsProvider
    {
        $config = PexelsConfig::fromWorkspace($workspace);

        return $config->isConfigured() ? new HttpPexelsProvider($config) : null;
    }
}
