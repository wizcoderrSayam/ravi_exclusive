<?php

namespace Database\Seeders\Concerns;

use Illuminate\Support\Facades\File;
use Peer\CMS\Facades\CMS;
use Peer\CMS\Models\Media;

trait StoresMediaLibraryFiles
{
    /**
     * Copies a file into a top-level folder of the CMS's media library, so it can be
     * picked in the admin, and returns its library path. A file already there is kept.
     */
    private function storeInMediaLibrary(string $folderName, string $source): string
    {
        $folder = Media::query()->firstOrCreate(['kind' => 'folder', 'parent_id' => null, 'name' => $folderName], ['path' => '']);
        $name = basename($source);
        $path = $folder->folderPath().'/'.$name;
        $disk = CMS::mediaDisk();

        if (! $disk->exists($path)) {
            $disk->put($path, File::get($source));
        }

        Media::query()->updateOrCreate(['kind' => 'file', 'parent_id' => $folder->id, 'name' => $name], [
            'path' => $path,
            'disk' => config('cms.media.disk'),
            'mime' => File::mimeType($source),
            'size' => File::size($source),
        ]);

        return $path;
    }
}
