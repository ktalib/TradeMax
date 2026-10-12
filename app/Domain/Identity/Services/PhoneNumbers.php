<?php

declare(strict_types=1);

namespace App\Domain\Identity\Services;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;

/**
 * Parses phone numbers typed with or without an international prefix, using the
 * selected country as the default region, and stores them in E.164.
 */
final class PhoneNumbers
{
    public function __construct(private readonly PhoneNumberUtil $util) {}

    public function toE164(string $input, string $region): ?string
    {
        try {
            $number = $this->util->parse($input, strtoupper($region));
        } catch (NumberParseException) {
            return null;
        }

        if (! $this->util->isValidNumber($number)) {
            return null;
        }

        // SMS codes must be deliverable: refuse landlines (mobile/fixed-or-mobile/VoIP are fine).
        if ($this->util->getNumberType($number) === PhoneNumberType::FIXED_LINE) {
            return null;
        }

        return $this->util->format($number, PhoneNumberFormat::E164);
    }

    public function callingCode(string $region): ?int
    {
        $code = $this->util->getCountryCodeForRegion(strtoupper($region));

        return $code > 0 ? $code : null;
    }
}
