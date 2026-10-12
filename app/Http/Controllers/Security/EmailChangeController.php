<?php

declare(strict_types=1);

namespace App\Http\Controllers\Security;

use App\Domain\Identity\Actions\ConfirmEmailChange;
use App\Domain\Identity\Models\ContactChange;
use App\Domain\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class EmailChangeController
{
    public function __invoke(Request $request, ContactChange $change, ConfirmEmailChange $action): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        try {
            $action->handle($user, $change);
        } catch (ValidationException $e) {
            return redirect()->route('security.index')->withErrors($e->errors());
        }

        return redirect()->route('security.index')->with('status', __('Your email address is updated. Withdrawals are paused for 24 hours for your protection.'));
    }
}
