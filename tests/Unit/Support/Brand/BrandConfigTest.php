<?php

namespace Tests\Unit\Support\Brand;

use App\Models\Workspace;
use App\Support\Brand\BrandConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unconfigured_workspace_falls_back_to_defaults(): void
    {
        $workspace = Workspace::factory()->create(['name' => 'Amar Page', 'settings' => []]);
        $brand = BrandConfig::fromWorkspace($workspace);

        $this->assertSame('Amar Page', $brand->name());
        $this->assertNull($brand->website());
        $this->assertSame('#0E7490', $brand->primaryColor());
        $this->assertSame('hind-siliguri', $brand->font());
        $this->assertSame('প্রতীকী চিত্র', $brand->imageLabel());
        $this->assertSame(['theme' => 'dark', 'accent' => '#FACC15'], $brand->categoryStyle('sarcasm'));
        $this->assertSame(['theme' => 'light', 'accent' => '#0E7490'], $brand->categoryStyle(null));
    }

    public function test_stored_values_and_category_overrides_win(): void
    {
        $workspace = Workspace::factory()->create();
        BrandConfig::write($workspace, [
            'name' => 'Brand',
            'website' => 'brand.com',
            'primary_color' => '#112233',
            'font' => 'anek-bangla',
            'categories' => ['sarcasm' => ['theme' => 'light', 'accent' => '#abcdef']],
        ]);
        $brand = BrandConfig::fromWorkspace($workspace->fresh());

        $this->assertSame('Brand', $brand->name());
        $this->assertSame('brand.com', $brand->website());
        $this->assertSame('#112233', $brand->primaryColor());
        $this->assertSame('anek-bangla', $brand->font());
        $this->assertSame(['theme' => 'light', 'accent' => '#ABCDEF'], $brand->categoryStyle('sarcasm'));
        $this->assertSame(['theme' => 'light', 'accent' => '#112233'], $brand->categoryStyle(null));
    }

    public function test_invalid_stored_values_are_ignored(): void
    {
        $workspace = Workspace::factory()->create();
        BrandConfig::write($workspace, [
            'primary_color' => 'red',
            'font' => 'comic-sans',
            'categories' => ['techy' => ['theme' => 'neon', 'accent' => 'blue']],
        ]);
        $brand = BrandConfig::fromWorkspace($workspace->fresh());

        $this->assertSame('#0E7490', $brand->primaryColor());
        $this->assertSame('hind-siliguri', $brand->font());
        $this->assertSame(['theme' => 'dark', 'accent' => '#22D3EE'], $brand->categoryStyle('techy'));
    }
}
