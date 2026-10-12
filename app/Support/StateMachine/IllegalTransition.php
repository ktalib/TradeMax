<?php

declare(strict_types=1);

namespace App\Support\StateMachine;

use BackedEnum;
use LogicException;

final class IllegalTransition extends LogicException
{
    public static function between(BackedEnum $from, BackedEnum $to): self
    {
        return new self(sprintf(
            'Illegal %s transition from "%s" to "%s".',
            class_basename($from),
            (string) $from->value,
            (string) $to->value,
        ));
    }
}
