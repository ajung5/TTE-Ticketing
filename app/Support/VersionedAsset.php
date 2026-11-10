<?php

namespace App\Support;

use InvalidArgumentException;
use RuntimeException;

/**
 * Content-derived public asset URLs. No build step or manual ?v= numbers.
 * Hash only the assets actually included in the rendered page.
 */
final class VersionedAsset {
    public static function version(string $path): string {
        self::validatePath($path);
        $file = public_path($path);
        if (!is_file($file)) {
            throw new RuntimeException('Asset tidak tersedia: ' . $path);
        }
        $digest = hash_file('sha256', $file);
        if ($digest === false) {
            throw new RuntimeException('Hash aset gagal: ' . $path);
        }

        return substr($digest, 0, 12);
    }

    public static function url(string $path): string {
        return asset($path) . '?v=' . self::version($path);
    }

    private static function validatePath(string $path): void {
        if (
            $path === '' ||
            str_starts_with($path, '/') ||
            str_contains($path, '..') ||
            !preg_match('/\A[a-zA-Z0-9_\/.\-]+\z/D', $path)
        ) {
            throw new InvalidArgumentException('Path aset tidak valid.');
        }
    }
}
