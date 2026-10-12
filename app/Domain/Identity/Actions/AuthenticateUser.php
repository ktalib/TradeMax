<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\LoginAttempt;
use App\Domain\Identity\Models\User;
use App\Support\Captcha\CaptchaVerifier;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Fortify's credential check (Fortify::authenticateUsing). Records every failure with a
 * reason, asks for a CAPTCHA after repeated failures from one IP, and refuses suspended
 * and closed accounts. Successful sign-ins are recorded by RecordSuccessfulLogin.
 */
final class AuthenticateUser
{
    // Checked against for unknown emails, so they cost the same time as wrong passwords.
    private static ?string $dummyHash = null;

    public function __construct(private readonly CaptchaVerifier $captcha) {}

    public function __invoke(Request $request): ?User
    {
        $email = Str::lower(trim((string) $request->input('email')));
        $password = (string) $request->input('password');

        if (self::captchaRequired($request) && ! $this->captcha->verify($this->token($request), $request->ip())) {
            throw ValidationException::withMessages(['captcha' => __('Complete the security check to continue.')]);
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            Hash::check($password, self::$dummyHash ??= Hash::make(Str::random(32)));
            $this->record($request, $email, null, 'invalid_credentials');

            return null;
        }

        if (! Hash::check($password, $user->password)) {
            $this->record($request, $email, $user, 'invalid_credentials');

            return null;
        }

        if (! $user->status->canSignIn()) {
            $this->record($request, $email, $user, 'account_'.$user->status->value);

            throw ValidationException::withMessages(['email' => __('This account can\'t sign in. Contact support for help.')]);
        }

        return $user;
    }

    public static function captchaRequired(Request $request): bool
    {
        $threshold = (int) config('platform.security.captcha_after_failed_logins', 3);

        return LoginAttempt::query()
            ->where('ip', (string) $request->ip())
            ->where('success', false)
            ->where('created_at', '>=', CarbonImmutable::now()->subMinutes(15))
            ->count() >= $threshold;
    }

    private function record(Request $request, string $email, ?User $user, string $reason): void
    {
        LoginAttempt::query()->create([
            'user_id' => $user?->id,
            'email' => mb_substr($email, 0, 254),
            'ip' => (string) $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 512),
            'success' => false,
            'reason' => $reason,
        ]);
    }

    private function token(Request $request): ?string
    {
        $token = $request->input('cf-turnstile-response');

        return is_string($token) ? $token : null;
    }
}
