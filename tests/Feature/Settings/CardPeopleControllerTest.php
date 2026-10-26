<?php

namespace Tests\Feature\Settings;

use App\Models\CardPerson;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PhotoCards\FakePhotoCardRenderer;
use Tests\TestCase;

class CardPeopleControllerTest extends TestCase
{
    use RefreshDatabase;

    private function member(Workspace $workspace, string $role = 'owner'): void
    {
        $user = User::factory()->create(['current_team_id' => $workspace->team->id]);
        $workspace->team->members()->attach($user->id, ['role' => $role]);
        $this->actingAs($user);
    }

    private function photo(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'person');
        file_put_contents((string) $path, FakePhotoCardRenderer::png());

        return new UploadedFile((string) $path, 'pori.png', 'image/png', null, true);
    }

    public function test_an_admin_adds_lists_and_removes_a_person(): void
    {
        Storage::fake('scratchpad');
        $workspace = Workspace::factory()->create();
        $this->member($workspace);

        $this->post('/settings/card-people', [
            'name' => 'পরীমনি',
            'aliases' => 'পরিমনি, Porimoni',
            'photo' => $this->photo(),
        ])->assertRedirect(route('settings.card-people.index'));

        $person = CardPerson::sole();
        $this->assertSame(['পরিমনি', 'Porimoni'], $person->aliases);

        $this->get(route('settings.card-people.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('workspace-settings/card-people')
                ->where('people.0.name', 'পরীমনি')
                ->where('people.0.photo_url', route('scratchpad.media', $person->media_asset_id)));

        $this->delete(route('settings.card-people.destroy', $person->id))->assertRedirect(route('settings.card-people.index'));
        $this->assertSame(0, CardPerson::count());
    }

    public function test_a_photo_and_name_are_required(): void
    {
        $workspace = Workspace::factory()->create();
        $this->member($workspace);

        $this->post('/settings/card-people', ['aliases' => 'x'])->assertSessionHasErrors(['name', 'photo']);
    }

    public function test_a_member_cannot_manage_people(): void
    {
        $workspace = Workspace::factory()->create();
        $person = CardPerson::factory()->for($workspace)->create();
        $this->member($workspace, 'member');

        $this->get(route('settings.card-people.index'))->assertForbidden();
        $this->post('/settings/card-people', ['name' => 'x', 'photo' => $this->photo()])->assertForbidden();
        $this->delete(route('settings.card-people.destroy', $person->id))->assertForbidden();
        $this->assertNotNull($person->fresh());
    }
}
