<?php

declare(strict_types=1);

namespace App\Domain\Admin\Models;

use App\Support\Concerns\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Back-office staff. A separate table and `admin` guard keep staff accounts out of the
 * customer user base; the admin panel itself arrives with the first Filament screens.
 *
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string $status
 * @property CarbonImmutable|null $last_login_at
 */
final class Admin extends Authenticatable
{
    use HasPublicUlid;
    use HasRoles;
    use TwoFactorAuthenticatable;

    protected string $guard_name = 'admin';

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'immutable_datetime',
            'last_login_at' => 'immutable_datetime',
        ];
    }
}
