<?php

namespace Mohab\LaravelBlueprint\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait UploadImages
{
    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Store a single uploaded file and return its storage path.
     *
     * @param  Request     $request
     * @param  string      $inputName   Form input name
     * @param  string      $folder      Destination folder (e.g. 'avatars', 'products/icons')
     * @param  string|null $disk        Storage disk — defaults to the trait's configured disk
     * @return string|null              Stored path, or null if no file was uploaded
     */
    public function uploadImage(
        Request $request,
        string  $inputName,
        string  $folder,
        ?string $disk = null,
    ): ?string {
        if (!$request->hasFile($inputName)) {
            return null;
        }

        return $this->storeFile($request->file($inputName), $folder, $disk);
    }

    /**
     * Replace a model's stored file with a newly uploaded one.
     *
     * The old file is deleted from storage before the new one is saved.
     * If no new file is uploaded the existing path is returned unchanged.
     *
     * @param  Model       $model
     * @param  Request     $request
     * @param  string      $inputName   Form input name
     * @param  string      $folder      Destination folder
     * @param  string      $column      Model attribute that holds the current path
     * @param  string|null $disk
     * @return string|null
     */
    public function updateImage(
        Model   $model,
        Request $request,
        string  $inputName,
        string  $folder,
        string  $column = 'image',
        ?string $disk = null,
    ): ?string {
        $currentPath = $model->getAttribute($column);

        if (!$request->hasFile($inputName)) {
            return $currentPath;
        }

        $this->deleteFile($currentPath, $disk);

        return $this->storeFile($request->file($inputName), $folder, $disk);
    }

    /**
     * Store multiple uploaded files and return all their storage paths.
     *
     * @param  UploadedFile[] $files
     * @param  string         $folder
     * @param  string|null    $disk
     * @return string[]
     */
    public function uploadMultipleImages(
        array   $files,
        string  $folder,
        ?string $disk = null,
    ): array {
        $paths = [];

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $paths[] = $this->storeFile($file, $folder, $disk);
            }
        }

        return $paths;
    }

    /**
     * Delete related model records (and their files) that are NOT in the keep list.
     *
     * Useful when syncing a model's image gallery: pass the IDs that should be
     * retained and everything else is removed from both storage and the database.
     *
     * @param  Model       $model       The related model class instance (used for querying)
     * @param  int         $foreignId   The foreign-key value to filter by
     * @param  string      $foreignKey  The foreign-key column name
     * @param  int[]       $keepIds     Record IDs to preserve
     * @param  string      $fileColumn  Column that holds the file path on each record
     * @param  string|null $disk
     */
    public function deleteOldImages(
        Model   $model,
        int     $foreignId,
        string  $foreignKey,
        array   $keepIds = [],
        string  $fileColumn = 'image',
        ?string $disk = null,
    ): void {
        $query = $model::where($foreignKey, $foreignId);

        $keepIds = array_map('intval', $keepIds);

        if (!empty($keepIds)) {
            $query->whereNotIn('id', $keepIds);
        }

        foreach ($query->get() as $record) {
            $this->deleteFile($record->getAttribute($fileColumn), $disk);
            $record->delete();
        }
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * The default storage disk.
     * Override this method in your class to change the disk project-wide.
     */
    protected function uploadDisk(): string
    {
        return 'public';
    }

    /**
     * Process and store an UploadedFile, returning its path.
     *
     * A short unique suffix is appended to the slug-ified filename to prevent
     * collisions when different users upload files with the same original name.
     */
    private function storeFile(UploadedFile $file, string $folder, ?string $disk): string
    {
        $extension = $file->getClientOriginalExtension();

        $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        // e.g. "my-photo-3f2a.jpg"
        $filename = Str::slug($baseName) . '-' . Str::random(4) . '.' . $extension;

        return $file->storeAs(
            trim($folder, '/'),
            $filename,
            $disk ?? $this->uploadDisk(),
        );
    }

    /**
     * Delete a file from storage if the path is non-empty.
     */
    private function deleteFile(?string $path, ?string $disk): void
    {
        if (!empty($path)) {
            Storage::disk($disk ?? $this->uploadDisk())->delete($path);
        }
    }
}