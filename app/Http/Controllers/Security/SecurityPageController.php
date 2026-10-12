<?php

declare(strict_types=1);

namespace App\Http\Controllers\Security;

use Illuminate\Contracts\View\View;

final class SecurityPageController
{
    public function __invoke(): View
    {
        return view('security.index');
    }
}
