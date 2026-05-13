<?php

namespace Database\Seeders;

use App\Models\User;

/**
 * Prefer legacy dev seeded accounts when present so local runs stay stable,
 * otherwise fall back to the first user row (covers production bootstrap after initial users exist).
 */
final class OrganizationSeedActorResolver
{
    public static function resolveUserId(): ?int
    {
        $preferredEmails = ['superadmin@hrnexus.com', 'hrhead@hrnexus.com', 'superadmin@example.com', 'hrhead1@example.com'];

        $id = User::query()
            ->whereIn('email', $preferredEmails)
            ->orderBy('id')
            ->value('id');

        if ($id !== null) {
            return (int) $id;
        }

        $fallbackId = User::query()->orderBy('id')->value('id');

        return $fallbackId !== null ? (int) $fallbackId : null;
    }
}
