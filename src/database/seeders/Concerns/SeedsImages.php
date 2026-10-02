<?php

namespace Database\Seeders\Concerns;

use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;

trait SeedsImages
{
    /**
     * Copy a seed image from database/seeders/images to the image disk
     * and return its path on the disk.
     *
     * The copy is skipped when the image is already there, because the
     * seeders run again on every deploy.
     */
    protected function seedImage(string $path): string
    {
        $disk = Storage::disk(config('filesystems.images'));

        if (! $disk->exists($path)) {
            $disk->putFileAs(
                dirname($path),
                new File(database_path('seeders/images/' . $path)),
                basename($path)
            );
        }

        return $path;
    }
}
