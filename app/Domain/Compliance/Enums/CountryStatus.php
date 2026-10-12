<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Enums;

enum CountryStatus: string
{
    case Allowed = 'allowed';

    /** Can register; some features are disabled (countries.features_disabled). */
    case Restricted = 'restricted';

    /** Cannot register or sign in. */
    case Blocked = 'blocked';
}
