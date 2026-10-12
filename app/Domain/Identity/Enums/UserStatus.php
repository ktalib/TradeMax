<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

use App\Support\StateMachine\GuardsTransitions;

enum UserStatus: string
{
    use GuardsTransitions;

    /** Normal use. */
    case Active = 'active';

    /** Self-service or staff freeze: can sign in, but withdrawals and API keys are stopped. */
    case Frozen = 'frozen';

    /** Staff action: cannot sign in. */
    case Suspended = 'suspended';

    /** Account closed; records kept for legal retention. Terminal. */
    case Closed = 'closed';

    public function transitions(): array
    {
        return match ($this) {
            self::Active => [self::Frozen, self::Suspended, self::Closed],
            self::Frozen => [self::Active, self::Suspended, self::Closed],
            self::Suspended => [self::Active, self::Closed],
            self::Closed => [],
        };
    }

    public function canSignIn(): bool
    {
        return $this === self::Active || $this === self::Frozen;
    }
}
