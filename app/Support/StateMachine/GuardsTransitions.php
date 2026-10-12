<?php

declare(strict_types=1);

namespace App\Support\StateMachine;

/**
 * For backed enums that model a status column. The enum lists its legal moves in
 * transitions(); assertCanTransitionTo() throws on anything else.
 */
trait GuardsTransitions
{
    /**
     * @return list<self>
     */
    abstract public function transitions(): array;

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->transitions(), true);
    }

    public function assertCanTransitionTo(self $to): void
    {
        if (! $this->canTransitionTo($to)) {
            throw IllegalTransition::between($this, $to);
        }
    }
}
