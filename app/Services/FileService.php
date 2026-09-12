<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Centralized instruction-file handling (public disk).
 */
class FileService
{
    public function __construct(private string $disk = 'public', private string $directory = 'exam-instructions') {}

    public function store(?UploadedFile $file): ?string
    {
        if (! $file) {
            return null;
        }

        return $file->store($this->directory, $this->disk);
    }

    /**
     * Store a replacement upload and delete the old file.
     * Returns the new path, or the old path when no file was given.
     */
    public function replace(?string $oldPath, ?UploadedFile $file): ?string
    {
        if (! $file) {
            return $oldPath;
        }

        $this->delete($oldPath);

        return $this->store($file);
    }

    public function delete(?string $path): void
    {
        if ($path && Storage::disk($this->disk)->exists($path)) {
            Storage::disk($this->disk)->delete($path);
        }
    }
}
