<?php

namespace App\Rules;

/**
 * Validates safe same-origin relative paths for post-action redirects.
 */
final class InternalAppPath
{
    /**
     * Whether the value is a safe same-origin relative path for redirects.
     */
    public static function isValid(string $value): bool
    {
        $value = trim($value);

        if ($value === '') {
            return false;
        }

        if (strlen($value) > 2048) {
            return false;
        }

        if (! str_starts_with($value, '/')) {
            return false;
        }

        if (str_starts_with($value, '//')) {
            return false;
        }

        if (str_contains($value, '://')) {
            return false;
        }

        if (preg_match('/[\x00-\x1f\x7f]/', $value) === 1) {
            return false;
        }

        return true;
    }
}
