<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Domain\Identity\Actions\ConfirmTwoFactorSetup;
use App\Domain\Identity\Actions\StartTwoFactorSetup;
use App\Domain\Identity\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Fortify;

final class TwoFactorSetupController
{
    public function show(Request $request, StartTwoFactorSetup $start): View|RedirectResponse
    {
        $user = $this->user($request);

        if ($user->hasConfirmedTwoFactor()) {
            return redirect()->route('dashboard');
        }

        $start->handle($user);
        $user->refresh();

        return view('onboarding.two-factor', [
            'qrSvg' => $user->twoFactorQrCodeSvg(),
            'secret' => (string) Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret),
        ]);
    }

    public function confirm(Request $request, ConfirmTwoFactorSetup $action): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:12']]);
        $user = $this->user($request);

        $action->handle($user, (string) $data['code']);

        $request->session()->flash('recovery_codes', $user->refresh()->recoveryCodes());

        return redirect()->route('onboarding.recovery-codes');
    }

    public function recoveryCodes(Request $request): View|RedirectResponse
    {
        $codes = $request->session()->get('recovery_codes');

        if (! is_array($codes) || $codes === []) {
            return redirect()->route('dashboard');
        }

        return view('onboarding.recovery-codes', ['codes' => $codes]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
