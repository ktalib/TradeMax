<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Domain\Identity\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class FrozenAccountController
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        if (! $user->isFrozen()) {
            return redirect()->route('dashboard');
        }

        return view('account.frozen', ['frozenAt' => $user->frozen_at]);
    }
}
