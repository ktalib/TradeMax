<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Identity\Models\LoginAttempt;
use App\Domain\Identity\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class DashboardController
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $previousLogin = LoginAttempt::query()
            ->where('user_id', $user->id)
            ->where('success', true)
            ->latest('id')
            ->skip(1)
            ->first();

        return view('dashboard', [
            'user' => $user,
            'previousLogin' => $previousLogin,
        ]);
    }
}
