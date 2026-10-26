<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Brand\SaveBrandSettingsAction;
use App\Contracts\PhotoCardRenderer;
use App\Data\Brand\BrandSettingsData;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\Concerns\AuthorizesWorkspaceSettings;
use App\Http\Requests\Settings\UpdateBrandSettingsRequest;
use App\Support\Brand\BrandConfig;
use App\Support\PhotoCards\PhotoCardComposer;
use App\Support\PhotoCards\PhotoCardRenderException;
use App\Support\Posts\PostCategories;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class BrandSettingsController extends Controller
{
    use AuthorizesWorkspaceSettings;

    public function edit(Request $request): Response
    {
        $workspace = $this->currentWorkspace();
        $this->authorizeWorkspaceAdmin($request, $workspace);
        $brand = BrandConfig::fromWorkspace($workspace);
        $stored = $brand->toArray();

        return Inertia::render('workspace-settings/brand', [
            'brand' => [
                'name' => $stored['name'] ?? '',
                'website' => $brand->website() ?? '',
                'primary_color' => $brand->primaryColor(),
                'font' => $brand->font(),
                'image_label' => $stored['image_label'] ?? '',
                'has_logo' => $brand->logoMediaAssetId() !== null,
                'has_dark_logo' => $brand->darkLogoMediaAssetId() !== null,
            ],
            'placeholders' => [
                'name' => $workspace->name,
                'image_label' => (string) config('photo_cards.brand_defaults.image_label'),
            ],
            'fonts' => collect((array) config('photo_cards.fonts'))
                ->map(fn (array $font, string $key): array => ['key' => $key, 'label' => $font['label']])
                ->values(),
            'categories' => collect(PostCategories::options())
                ->map(fn (array $option): array => $option + $brand->categoryStyle($option['key']))
                ->values(),
            'previewVersion' => md5((string) json_encode($stored)),
        ]);
    }

    public function update(UpdateBrandSettingsRequest $request, SaveBrandSettingsAction $action): RedirectResponse
    {
        $action->handle($this->currentWorkspace(), $request->user(), BrandSettingsData::fromRequest($request));

        return to_route('settings.brand.edit')->with('success', 'Brand saved.');
    }

    /**
     * A sample card in the saved brand, optionally in a category's style.
     */
    public function preview(Request $request, PhotoCardComposer $composer, PhotoCardRenderer $renderer): HttpResponse
    {
        $workspace = $this->currentWorkspace();
        $this->authorizeWorkspaceAdmin($request, $workspace);
        $category = PostCategories::normalize($request->query('category'));

        try {
            $png = $renderer->render($composer->sample($workspace, $category));
        } catch (PhotoCardRenderException $exception) {
            return response($exception->getMessage(), 422, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
