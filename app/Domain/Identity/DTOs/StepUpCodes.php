<?php

declare(strict_types=1);

namespace App\Domain\Identity\DTOs;

/**
 * The two codes every sensitive action requires: authenticator (TOTP) and emailed code.
 */
final readonly class StepUpCodes
{
    public function __construct(
        public ?string $totp,
        public ?string $email,
    ) {}
}
