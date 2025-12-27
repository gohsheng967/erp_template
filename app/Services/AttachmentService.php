<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Models\Attachment;

class AttachmentService
{
    /**
     * Store an uploaded file and attach it to a model.
     */
    public static function store(
        UploadedFile $file,
        Model $attachable,
        string $disk = 'public'
    ): Attachment {
        $directory = self::buildDirectory($attachable);

        $filename = self::buildFilename($file);

        $path = $file->storeAs(
            $directory,
            $filename,
            $disk
        );

        return Attachment::create([
            'uuid'            => Str::uuid(),
            'attachable_type' => get_class($attachable),
            'attachable_id'   => $attachable->id,
            'file_path'       => $path,
            'original_name'   => $file->getClientOriginalName(),
            'mime_type'       => $file->getMimeType(),
            'file_size'       => $file->getSize(),
        ]);
    }

    /**
     * Build storage directory.
     * Example: claim_item/123
     */
    protected static function buildDirectory(Model $attachable): string
    {
        return Str::snake(class_basename($attachable))
            . '/' . $attachable->id;
    }

    /**
     * Build unique filename.
     */
    protected static function buildFilename(UploadedFile $file): string
    {
        return Str::uuid() . '.' . $file->getClientOriginalExtension();
    }
}
