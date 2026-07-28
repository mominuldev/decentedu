<?php

declare(strict_types=1);

namespace App\Actions\Cms\Media;

use App\Models\Cms\Asset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class UploadAsset
{
    public function handle(UploadedFile $file, ?int $folderId, User $user, string $category = 'cms'): Asset
    {
        return DB::transaction(function () use ($file, $folderId, $user, $category): Asset {
            $asset = Asset::query()->create([
                'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'media_folder_id' => $folderId,
                'uploaded_by' => $user->id,
                'category' => $category,
            ]);

            $disk = in_array($category, Asset::PRIVATE_CATEGORIES, true) ? 'local' : '';
            $media = $asset->addMedia($file)->toMediaCollection(Asset::COLLECTION, $disk);

            // Ensure conversions are generated immediately for the upload response.
            if (method_exists($media, 'generateConversions')) {
                $media->generateConversions();
            }

            return $asset;
        });
    }
}
