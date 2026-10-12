<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Domain\Compliance\Repositories\Contracts\CountryDirectory;
use App\Domain\Identity\Actions\SendPhoneVerificationCode;
use App\Domain\Identity\Actions\UpdateUnverifiedPhone;
use App\Domain\Identity\Actions\VerifyPhone;
use App\Domain\Identity\Models\User;
use App\Support\Privacy\Mask;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PhoneVerificationController
{
    public function show(Request $request, CountryDirectory $countries): View|RedirectResponse
    {
        $user = $this->user($request);

        if ($user->hasVerifiedPhone()) {
            return redirect()->route('onboarding.two-factor');
        }

        return view('onboarding.verify-phone', [
            'maskedPhone' => Mask::phone($user->phone),
            'countries' => $countries->registrable(),
            'residence' => $user->residence_country,
        ]);
    }

    public function send(Request $request, SendPhoneVerificationCode $action): RedirectResponse
    {
        $action->handle($this->user($request));

        return back()->with('status', __('We sent a 6-digit code by SMS.'));
    }

    public function verify(Request $request, VerifyPhone $action): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:12']]);

        $action->handle($this->user($request), (string) $data['code']);

        return redirect()->route('onboarding.two-factor')->with('status', __('Phone number confirmed.'));
    }

    public function update(Request $request, UpdateUnverifiedPhone $action): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'phone_country' => ['required', 'string', 'size:2'],
        ]);

        $action->handle($this->user($request), (string) $data['phone'], (string) $data['phone_country']);

        return back()->with('status', __('Phone number updated. Send a code to confirm it.'));
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
