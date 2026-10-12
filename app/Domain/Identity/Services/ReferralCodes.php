<?php

declare(strict_types=1);

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\User;
use RuntimeException;

final class ReferralCodes
{
    // No 0/O or 1/I/L: codes get read aloud and typed from screenshots.
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const LENGTH = 8;

    public function generate(): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $code = '';
            for ($i = 0; $i < self::LENGTH; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }

            if (! User::query()->where('referral_code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Could not generate a unique referral code.');
    }

    public function referrerId(?string $code): ?int
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        $id = User::query()->where('referral_code', strtoupper(trim($code)))->value('id');

        return is_int($id) ? $id : null;
    }
}
