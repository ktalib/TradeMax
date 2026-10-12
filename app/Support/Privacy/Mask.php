<?php

declare(strict_types=1);

namespace App\Support\Privacy;

/**
 * Masks personal data for logs and for showing a contact back to its owner.
 */
final class Mask
{
    public static function email(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1).'•••@'.$domain;
    }

    public static function phone(string $phone): string
    {
        $length = mb_strlen($phone);

        return $length <= 6 ? str_repeat('•', $length) : mb_substr($phone, 0, 4).str_repeat('•', $length - 6).mb_substr($phone, -2);
    }

    public static function ip(?string $ip): string
    {
        if ($ip === null || $ip === '') {
            return '';
        }

        if (str_contains($ip, ':')) {
            return implode(':', array_slice(explode(':', $ip), 0, 2)).':••••';
        }

        $parts = explode('.', $ip);

        return count($parts) === 4 ? $parts[0].'.•••.•••.'.$parts[3] : $ip;
    }
}
