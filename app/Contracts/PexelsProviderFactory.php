<?php

namespace App\Contracts;

use App\Models\Workspace;

interface PexelsProviderFactory
{
    /**
     * A provider using the workspace's own Pexels key, or null when the
     * workspace has none. Works outside a request (queue jobs).
     */
    public function forWorkspace(Workspace $workspace): ?PexelsProvider;
}
