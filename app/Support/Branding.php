<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/** The uploaded gym logo, kept in storage/app/private/branding (included in backups). */
class Branding
{
    public static function directory(): string
    {
        return storage_path('app/private/branding');
    }

    public static function logoPath(): ?string
    {
        $file = setting('gym_logo');
        $path = $file ? self::directory().DIRECTORY_SEPARATOR.basename($file) : null;

        return $path && File::isFile($path) ? $path : null;
    }

    /** For PDFs (dompdf can't fetch URLs offline). */
    public static function logoDataUri(): ?string
    {
        $path = self::logoPath();

        return $path ? 'data:'.File::mimeType($path).';base64,'.base64_encode(File::get($path)) : null;
    }
}
