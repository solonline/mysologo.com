<?php

namespace App\Domains\Kyc\Support;

use Illuminate\Support\Facades\Storage;

class CustomerKycMedia
{
    public const DISK = 'local';

    /** @var list<string> */
    public const FIELDS = [
        'front_image',
        'back_image',
        'full_front_image',
        'full_back_image',
        'portrait_image',
        'front_video',
        'back_video',
        'front_image_camera_front',
        'back_image_camera_front',
    ];

    public static function isKycPath(?string $path): bool
    {
        return is_string($path)
            && preg_match('#^customer/\d+/didit/\d+/[a-z0-9_]+\.[a-z0-9]+$#', $path) === 1;
    }

    public static function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (! self::isKycPath($path)) {
            return Storage::disk('public')->url($path);
        }

        if (! preg_match('#^customer/(\d+)/didit/(\d+)/([a-z0-9_]+)\.[a-z0-9]+$#', $path, $matches)) {
            return null;
        }

        if (! in_array($matches[3], self::FIELDS, true)) {
            return null;
        }

        return route('dashboard.customers.documents.media', [
            'customer' => $matches[1],
            'document' => $matches[2],
            'field' => $matches[3],
        ], false);
    }
}
