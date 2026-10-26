<?php

namespace App\Http\Controllers\Settings;

use App\Actions\PhotoCards\DeleteCardPersonAction;
use App\Actions\PhotoCards\SaveCardPersonAction;
use App\Data\PhotoCards\CardPersonData;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\Concerns\AuthorizesWorkspaceSettings;
use App\Http\Requests\Settings\StoreCardPersonRequest;
use App\Models\CardPerson;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CardPeopleController extends Controller
{
    use AuthorizesWorkspaceSettings;

    public function index(Request $request): Response
    {
        $workspace = $this->currentWorkspace();
        $this->authorizeWorkspaceAdmin($request, $workspace);

        return Inertia::render('workspace-settings/card-people', [
            'people' => CardPerson::query()
                ->where('workspace_id', $workspace->id)
                ->orderBy('name')
                ->get()
                ->map(fn (CardPerson $person): array => [
                    'id' => $person->id,
                    'name' => $person->name,
                    'aliases' => $person->aliases,
                    'photo_url' => route('scratchpad.media', $person->media_asset_id),
                ]),
        ]);
    }

    public function store(StoreCardPersonRequest $request, SaveCardPersonAction $action): RedirectResponse
    {
        $action->handle($this->currentWorkspace(), $request->user(), CardPersonData::fromRequest($request));

        return to_route('settings.card-people.index')->with('success', 'Person saved.');
    }

    public function destroy(Request $request, int $person, DeleteCardPersonAction $action): RedirectResponse
    {
        $workspace = $this->currentWorkspace();
        $this->authorizeWorkspaceAdmin($request, $workspace);
        $action->handle($workspace, $person);

        return to_route('settings.card-people.index')->with('success', 'Person removed.');
    }
}
