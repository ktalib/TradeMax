<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Domain\Identity\Actions\SendDeviceVerificationCode;
use App\Domain\Identity\Actions\VerifyDevice;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\DeviceRecognizer;
use App\Support\Privacy\Mask;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class DeviceVerificationController
{
    public function show(Request $request): View|RedirectResponse
    {
        if ($request->session()->has(DeviceRecognizer::SESSION_KEY)) {
            return redirect()->intended(route('dashboard'));
        }

        $user = $this->user($request);

        return view('onboarding.verify-device', ['maskedEmail' => Mask::email($user->email)]);
    }

    public function send(Request $request, SendDeviceVerificationCode $action): RedirectResponse
    {
        $action->handle($this->user($request));

        return back()->with('status', __('We sent a 6-digit code to your email.'));
    }

    public function confirm(Request $request, VerifyDevice $action): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:12'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $action->handle($this->user($request), $request, (string) $data['code'], (bool) ($data['remember'] ?? false));

        return redirect()->intended(route('dashboard'));
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
