<?php

namespace App\Actions\Brand;

use App\Actions\Scratchpad\Concerns\ResolvesMediaAsset;
use App\Data\Brand\BrandSettingsData;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Brand\BrandConfig;
use Illuminate\Support\Facades\DB;

/**
 * Saves a workspace's photo-card brand. Logos are stored as workspace media
 * assets (deduped by checksum) and referenced by id; an omitted upload keeps
 * the current logo.
 */
class SaveBrandSettingsAction
{
    use ResolvesMediaAsset;

    public function handle(Workspace $workspace, ?User $savedBy, BrandSettingsData $data): void
    {
        DB::transaction(function () use ($workspace, $savedBy, $data): void {
            $locked = Workspace::query()->whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            $current = BrandConfig::fromWorkspace($locked)->toArray();

            $logoId = $data->removeLogo ? null : ($current['logo_media_asset_id'] ?? null);
            if ($data->logo !== null) {
                $logoId = $this->resolveMediaAsset($locked, $savedBy, $data->logo, 'image')->id;
            }

            $darkLogoId = $data->removeDarkLogo ? null : ($current['logo_dark_media_asset_id'] ?? null);
            if ($data->darkLogo !== null) {
                $darkLogoId = $this->resolveMediaAsset($locked, $savedBy, $data->darkLogo, 'image')->id;
            }

            BrandConfig::write($locked, [
                'name' => $data->name,
                'website' => $data->website,
                'primary_color' => $data->primaryColor,
                'font' => $data->font,
                'image_label' => $data->imageLabel,
                'logo_media_asset_id' => $logoId,
                'logo_dark_media_asset_id' => $darkLogoId,
                'categories' => $data->categoryStyles,
            ]);
        });
    }
}
