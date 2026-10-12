<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Domain\Identity\Actions\FreezeAccount;
use App\Domain\Identity\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The "freeze my account" link in security emails. It is signed and works without signing
 * in (the person may have lost access). A confirm step stops link scanners freezing accounts.
 */
final class FreezeLinkController
{
    public function show(Request $request, User $user): View
    {
        return view('account.freeze-confirm', [
            'alreadyFrozen' => $user->isFrozen(),
            'action' => $request->fullUrl(),
        ]);
    }

    public function store(User $user, FreezeAccount $action): View
    {
        $action->handle($user, 'email_link');

        return view('account.freeze-done');
    }
}
