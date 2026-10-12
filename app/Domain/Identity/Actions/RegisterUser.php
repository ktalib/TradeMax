<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Compliance\Enums\CountryStatus;
use App\Domain\Compliance\Repositories\Contracts\CountryDirectory;
use App\Domain\Identity\Models\Consent;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Rules\PasswordRules;
use App\Domain\Identity\Services\DeviceRecognizer;
use App\Domain\Identity\Services\PhoneNumbers;
use App\Domain\Identity\Services\ReferralCodes;
use App\Support\Audit\AuditLogger;
use App\Support\Captcha\CaptchaVerifier;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Registration, country of residence first: blocked countries stop here. Phone numbers are
 * stored in E.164. The registering browser becomes the account's first verified device.
 */
final class RegisterUser implements CreatesNewUsers
{
    public function __construct(
        private readonly CountryDirectory $countries,
        private readonly PhoneNumbers $phones,
        private readonly ReferralCodes $referrals,
        private readonly DeviceRecognizer $devices,
        private readonly CaptchaVerifier $captcha,
        private readonly AuditLogger $audit,
        private readonly Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        $validated = Validator::make($input, [
            'residence_country' => ['required', 'string', 'size:2', function (string $attribute, mixed $value, \Closure $fail): void {
                $status = $this->countries->statusOf((string) $value);
                if ($status === null) {
                    $fail(__('Choose your country of residence from the list.'));
                } elseif ($status === CountryStatus::Blocked) {
                    $fail(__('We can\'t open accounts for residents of this country.'));
                }
            }],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:254', Rule::unique(User::class, 'email')],
            'phone' => ['required', 'string', 'max:32'],
            'password' => PasswordRules::forNewPassword([(string) ($input['email'] ?? ''), (string) ($input['name'] ?? '')]),
            'timezone' => ['nullable', 'string', 'timezone:all'],
            'referral_code' => ['nullable', 'string', 'max:16'],
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => __('Please accept the Terms, Privacy Policy and Risk Disclosure to continue.'),
        ])->validate();

        if (! $this->captcha->verify($this->stringOrNull($input['cf-turnstile-response'] ?? null), $this->request->ip())) {
            throw ValidationException::withMessages(['captcha' => __('Complete the security check to continue.')]);
        }

        $country = strtoupper((string) $validated['residence_country']);
        $phone = $this->phones->toE164((string) $validated['phone'], $country);

        if ($phone === null) {
            throw ValidationException::withMessages(['phone' => __('Enter a valid mobile number. Landlines can\'t receive our security codes.')]);
        }

        if (User::query()->where('phone', $phone)->exists()) {
            throw ValidationException::withMessages(['phone' => __('This phone number is already linked to an account.')]);
        }

        $referredBy = $this->referrals->referrerId($this->stringOrNull($validated['referral_code'] ?? null));

        if (filled($validated['referral_code'] ?? null) && $referredBy === null) {
            throw ValidationException::withMessages(['referral_code' => __('We couldn\'t find that referral code. Check it, or leave the field empty.')]);
        }

        return DB::transaction(function () use ($validated, $country, $phone, $referredBy): User {
            $user = User::query()->create([
                'name' => trim((string) $validated['name']),
                'email' => Str::lower((string) $validated['email']),
                'phone' => $phone,
                'password' => (string) $validated['password'],
                'residence_country' => $country,
                'timezone' => $this->stringOrNull($validated['timezone'] ?? null) ?? 'UTC',
                'locale' => $this->request->getPreferredLanguage((array) config('platform.locales')) ?? (string) config('app.locale'),
                'preferred_fiat' => (string) config('platform.default_display_fiat', 'USD'),
                'referral_code' => $this->referrals->generate(),
                'referred_by_id' => $referredBy,
            ]);

            $now = CarbonImmutable::now();
            foreach ((array) config('platform.legal') as $document => $version) {
                Consent::query()->create([
                    'user_id' => $user->id,
                    'document' => $document,
                    'version' => (string) $version,
                    'jurisdiction' => $country,
                    'accepted_at' => $now,
                    'ip' => $this->request->ip(),
                ]);
            }

            $this->devices->markVerified($this->devices->sight($user, $this->request)->device, trust: false);

            $this->audit->record('user.registered', $user, after: ['residence_country' => $country, 'referred' => $referredBy !== null], actor: $user);

            return $user;
        });
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
