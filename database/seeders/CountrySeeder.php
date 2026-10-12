<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Compliance\Enums\CountryStatus;
use App\Domain\Compliance\Models\Country;
use Illuminate\Database\Seeder;
use libphonenumber\PhoneNumberUtil;
use Locale;

/**
 * Every region that has a phone numbering plan (so the country list and phone validation
 * always agree), named in English via ICU. Comprehensively sanctioned jurisdictions start
 * blocked; everything else starts allowed until the operator sets the serve list (ADR-0001).
 * Re-running keeps statuses an admin has already changed.
 */
final class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $blocked = array_map('strtoupper', (array) config('platform.blocked_countries_default', []));

        foreach (PhoneNumberUtil::getInstance()->getSupportedRegions() as $code) {
            $name = Locale::getDisplayRegion('-'.$code, 'en');

            if (! is_string($name) || $name === '' || $name === $code) {
                continue;
            }

            $country = Country::query()->firstOrNew(['code' => $code]);
            $country->name = $name;

            if (! $country->exists) {
                $country->status = in_array($code, $blocked, true) ? CountryStatus::Blocked : CountryStatus::Allowed;
            }

            $country->save();
        }
    }
}
