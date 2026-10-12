<?php

declare(strict_types=1);

namespace App\Livewire\Security;

use App\Domain\Identity\Actions\CreateApiKey;
use App\Domain\Identity\Actions\RevokeApiKey;
use App\Domain\Identity\Enums\ApiKeyPermission;
use App\Domain\Identity\Models\ApiKey;
use App\Livewire\Security\Concerns\WithStepUp;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class ApiKeys extends Component
{
    use WithStepUp;

    public bool $creating = false;

    public string $name = '';

    /** @var list<string> */
    public array $permissions = ['read'];

    public string $ip_whitelist = '';

    /**
     * Shown in the response that created the key only. Protected properties are not part of
     * Livewire's page payload, so the secret is gone on the next interaction.
     *
     * @var array{key: string, secret: string}|null
     */
    protected ?array $issued = null;

    public function open(): void
    {
        $this->creating = true;
        $this->issued = null;
        $this->resetErrorBag();
    }

    public function cancel(): void
    {
        $this->reset('creating', 'name', 'permissions', 'ip_whitelist');
        $this->resetStepUp();
    }

    public function create(CreateApiKey $action): void
    {
        $this->throttle('api-key');

        $ips = array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $this->ip_whitelist) ?: [])));

        $issued = $action->handle($this->currentUser(), $this->stepUpCodes(), $this->name, $this->permissions, $ips);

        $this->cancel();
        $this->issued = ['key' => $issued->plainKey, 'secret' => $issued->plainSecret];
    }

    public function revoke(string $publicId, RevokeApiKey $action): void
    {
        $apiKey = ApiKey::query()->where('public_id', $publicId)->firstOrFail();
        $action->handle($this->currentUser(), $apiKey);

        $this->dispatch('toast', message: __('API key revoked.'));
    }

    public function render(): View
    {
        return view('livewire.security.api-keys', [
            'keys' => ApiKey::query()->where('user_id', $this->currentUser()->id)->latest('id')->get(),
            'permissionOptions' => ApiKeyPermission::cases(),
            'issued' => $this->issued,
        ]);
    }
}
