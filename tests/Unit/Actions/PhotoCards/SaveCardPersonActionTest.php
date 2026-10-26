<?php

namespace Tests\Unit\Actions\PhotoCards;

use App\Actions\PhotoCards\DeleteCardPersonAction;
use App\Actions\PhotoCards\SaveCardPersonAction;
use App\Data\PhotoCards\CardPersonData;
use App\Models\CardPerson;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PhotoCards\FakePhotoCardRenderer;
use Tests\TestCase;

class SaveCardPersonActionTest extends TestCase
{
    use RefreshDatabase;

    private function photo(string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'person');
        file_put_contents((string) $path, FakePhotoCardRenderer::png().$name);

        return new UploadedFile((string) $path, $name, 'image/png', null, true);
    }

    public function test_it_stores_the_photo_and_replaces_a_person_with_the_same_name(): void
    {
        Storage::fake('scratchpad');
        $workspace = Workspace::factory()->create();
        $action = app(SaveCardPersonAction::class);

        $first = $action->handle($workspace, null, new CardPersonData('পরীমনি', ['Porimoni'], $this->photo('a.png')));
        $second = $action->handle($workspace, null, new CardPersonData('পরীমনি', ['পরিমনি'], $this->photo('b.png')));

        $this->assertTrue($first->is($second));
        $this->assertSame(1, CardPerson::count());
        $this->assertSame(['পরিমনি'], $second->fresh()?->aliases);
        $this->assertNotSame($first->media_asset_id, $second->media_asset_id);
        Storage::disk('scratchpad')->assertExists((string) $second->mediaAsset?->path);
    }

    public function test_delete_only_removes_a_person_in_the_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        $mine = CardPerson::factory()->for($workspace)->create();
        $theirs = CardPerson::factory()->create();

        (new DeleteCardPersonAction)->handle($workspace, $theirs->id);
        $this->assertNotNull($theirs->fresh());

        (new DeleteCardPersonAction)->handle($workspace, $mine->id);
        $this->assertNull($mine->fresh());
    }
}
