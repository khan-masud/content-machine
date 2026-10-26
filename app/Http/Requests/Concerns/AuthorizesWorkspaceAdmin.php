<?php

namespace App\Http\Requests\Concerns;

use App\Models\User;
use App\Models\Workspace;

/**
 * Form-request authorization for workspace settings: only an owner or admin
 * of the current workspace's team may submit.
 */
trait AuthorizesWorkspaceAdmin
{
    public function authorize(): bool
    {
        $user = $this->user();
        $workspace = Workspace::current();
        if (! $user instanceof User || $workspace === null) {
            return false;
        }

        $member = $workspace->team->members()->whereKey($user->id)->first();

        return in_array($member?->pivot->role, ['owner', 'admin'], true);
    }
}
