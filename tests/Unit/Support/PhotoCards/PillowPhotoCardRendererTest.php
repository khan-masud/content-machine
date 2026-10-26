<?php

namespace Tests\Unit\Support\PhotoCards;

use App\Models\Workspace;
use App\Support\PhotoCards\PhotoCardComposer;
use App\Support\PhotoCards\PhotoCardRenderException;
use App\Support\PhotoCards\PillowPhotoCardRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class PillowPhotoCardRendererTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_a_real_png_with_pillow(): void
    {
        $check = Process::run([(string) config('photo_cards.python'), '-c', 'from PIL import features; assert features.check("raqm")']);
        if (! $check->successful()) {
            $this->markTestSkipped('python3 with Pillow and libraqm is not installed.');
        }

        $workspace = Workspace::factory()->create(['name' => 'Amar Page']);
        $png = (new PillowPhotoCardRenderer)->render((new PhotoCardComposer)->sample($workspace, 'sarcasm'));

        $this->assertStringStartsWith("\x89PNG", $png);
        $size = getimagesizefromstring($png);
        $this->assertNotFalse($size);
        $this->assertSame([1080, 1350], [$size[0], $size[1]]);
    }

    public function test_a_failing_renderer_process_raises_a_readable_error(): void
    {
        Process::fake(['*' => Process::result(errorOutput: 'boom', exitCode: 1)]);
        $workspace = Workspace::factory()->create();

        $this->expectException(PhotoCardRenderException::class);
        $this->expectExceptionMessage('python3 and Pillow');

        (new PillowPhotoCardRenderer)->render((new PhotoCardComposer)->sample($workspace));
    }

    public function test_missing_raqm_is_reported(): void
    {
        Process::fake(['*' => Process::result(exitCode: 3)]);
        $workspace = Workspace::factory()->create();

        $this->expectException(PhotoCardRenderException::class);
        $this->expectExceptionMessage('libraqm');

        (new PillowPhotoCardRenderer)->render((new PhotoCardComposer)->sample($workspace));
    }
}
