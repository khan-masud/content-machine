<?php

namespace Tests\Unit\Actions\Brand;

use App\Actions\Brand\SaveBrandSettingsAction;
use App\Data\Brand\BrandSettingsData;
use App\Models\MediaAsset;
use App\Models\Workspace;
use App\Support\Brand\BrandConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PhotoCards\FakePhotoCardRenderer;
use Tests\TestCase;

class SaveBrandSettingsActionTest extends TestCase
{
    use RefreshDatabase;

    private function logo(string $name = 'logo.png'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'logo');
        file_put_contents((string) $path, FakePhotoCardRenderer::png().$name);

        return new UploadedFile((string) $path, $name, 'image/png', null, true);
    }

    private function data(array $overrides = []): BrandSettingsData
    {
        return new BrandSettingsData(...array_merge([
            'name' => 'Amar Page',
            'website' => 'amarpage.com',
            'primaryColor' => '#112233',
            'font' => 'anek-bangla',
            'imageLabel' => 'Concept Image',
            'categoryStyles' => ['funny' => ['theme' => 'dark', 'accent' => '#00FF00']],
        ], $overrides));
    }

    public function test_it_saves_the_brand_and_stores_logos_as_media_assets(): void
    {
        Storage::fake('scratchpad');
        $workspace = Workspace::factory()->create();

        (new SaveBrandSettingsAction)->handle($workspace, null, $this->data([
            'logo' => $this->logo(),
            'darkLogo' => $this->logo('dark.png'),
        ]));

        $brand = BrandConfig::fromWorkspace($workspace->fresh());
        $this->assertSame('Amar Page', $brand->name());
        $this->assertSame('amarpage.com', $brand->website());
        $this->assertSame('#112233', $brand->primaryColor());
        $this->assertSame('anek-bangla', $brand->font());
        $this->assertSame('Concept Image', $brand->imageLabel());
        $this->assertSame(['theme' => 'dark', 'accent' => '#00FF00'], $brand->categoryStyle('funny'));
        $logo = MediaAsset::query()->findOrFail($brand->logoMediaAssetId());
        $this->assertSame($workspace->id, $logo->workspace_id);
        Storage::disk('scratchpad')->assertExists($logo->path);
        $this->assertNotNull($brand->darkLogoMediaAssetId());
        $this->assertNotSame($brand->logoMediaAssetId(), $brand->darkLogoMediaAssetId());
    }

    public function test_an_omitted_logo_is_kept_and_remove_clears_it(): void
    {
        Storage::fake('scratchpad');
        $workspace = Workspace::factory()->create();
        $action = new SaveBrandSettingsAction;
        $action->handle($workspace, null, $this->data(['logo' => $this->logo()]));
        $logoId = BrandConfig::fromWorkspace($workspace->fresh())->logoMediaAssetId();

        $action->handle($workspace, null, $this->data(['name' => 'Renamed']));
        $this->assertSame($logoId, BrandConfig::fromWorkspace($workspace->fresh())->logoMediaAssetId());

        $action->handle($workspace, null, $this->data(['removeLogo' => true]));
        $this->assertNull(BrandConfig::fromWorkspace($workspace->fresh())->logoMediaAssetId());
    }

    public function test_other_workspace_settings_are_left_alone(): void
    {
        $workspace = Workspace::factory()->create(['settings' => ['pexels' => ['api_key' => 'x']]]);

        (new SaveBrandSettingsAction)->handle($workspace, null, $this->data());

        $this->assertSame(['api_key' => 'x'], $workspace->fresh()?->settings['pexels'] ?? null);
    }
}
