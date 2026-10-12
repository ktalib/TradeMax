<?php

declare(strict_types=1);

namespace App\Domain\Identity\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use ZxcvbnPhp\Zxcvbn;

/**
 * Rejects guessable passwords using zxcvbn (dictionary words, keyboard patterns, dates,
 * and the user's own name or email), on top of the minimum length.
 */
final class StrongPassword implements ValidationRule
{
    /**
     * @param  list<string>  $userInputs  personal data the password must not be built from
     */
    public function __construct(private readonly array $userInputs = []) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $result = (new Zxcvbn)->passwordStrength($value, $this->expandedInputs());
        $minimum = (int) config('platform.security.password_min_zxcvbn_score', 3);

        if ((int) $result['score'] < $minimum) {
            $fail(__('This password is too easy to guess. Use a longer phrase of unrelated words, and avoid your name or email.'));
        }
    }

    /**
     * "Ada Okafor" / "ada.okafor@example.com" also yield "ada", "okafor", "adaokafor" and
     * "ada.okafor", so a password like "adaokafor2024" is recognised as personal.
     *
     * @return list<string>
     */
    private function expandedInputs(): array
    {
        $inputs = [];

        foreach ($this->userInputs as $input) {
            $input = mb_strtolower(trim($input));
            if ($input === '') {
                continue;
            }

            $base = str_contains($input, '@') ? strstr($input, '@', true) : $input;
            $parts = preg_split('/[^\pL\pN]+/u', (string) $base, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            $inputs[] = $input;
            $inputs[] = (string) $base;
            $inputs[] = implode('', $parts);
            array_push($inputs, ...$parts);
        }

        return array_values(array_unique(array_filter($inputs, static fn (string $s): bool => mb_strlen($s) >= 3)));
    }
}
