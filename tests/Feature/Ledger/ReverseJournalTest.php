<?php

declare(strict_types=1);

use App\Domain\Admin\Models\Admin;
use App\Domain\Identity\Models\User;
use App\Domain\Ledger\Actions\ReverseJournal;
use App\Domain\Ledger\DTOs\EntryDTO;
use App\Domain\Ledger\DTOs\PostedBy;
use App\Domain\Ledger\Enums\PostedByType;
use App\Domain\Ledger\Exceptions\CannotReverseJournal;
use App\Domain\Ledger\Exceptions\InsufficientBalance;
use App\Domain\Ledger\Models\Journal;
use App\Support\Audit\AuditLog;
use Illuminate\Support\Facades\Hash;
use Tests\Support\LedgerAssertions;

beforeEach(function (): void {
    $this->btc = createAsset('BTC', 8);
    $this->user = User::factory()->create();
    $this->admin = Admin::query()->create(['name' => 'Finance', 'email' => 'finance@example.com', 'password' => Hash::make('velvet-harbor-canyon-quill')]);
    $this->reverse = app(ReverseJournal::class);
});

afterEach(fn () => LedgerAssertions::assertBalanced());

it('undoes a journal with a mirror-image journal and audits it', function (): void {
    $original = deposit($this->user->id, $this->btc, '0.4');

    $reversal = $this->reverse->handle($original->publicId, 'Credited to the wrong user', PostedBy::admin($this->admin->id), $this->admin);

    $journal = Journal::query()->findOrFail($reversal->id);
    expect($journal->reverses_journal_id)->toBe($original->id)
        ->and($journal->posted_by_type)->toBe(PostedByType::Admin)
        ->and($journal->posted_by_id)->toBe($this->admin->id)
        ->and($journal->description)->toBe('Reversal: Credited to the wrong user')
        ->and(Journal::query()->findOrFail($original->id)->reversal?->id)->toBe($reversal->id)
        ->and(balanceOf(userWallet($this->user->id, $this->btc))->available->isZero())->toBeTrue()
        ->and(balanceOf(systemWallet($this->btc))->available->isZero())->toBeTrue();

    $log = AuditLog::query()->where('action', 'ledger.journal_reversed')->sole();
    expect($log->actor_type)->toBe('admin')
        ->and($log->after)->toEqual(['reversal_journal' => $reversal->publicId, 'reason' => 'Credited to the wrong user']);
});

it('reverses a journal only once', function (): void {
    $original = deposit($this->user->id, $this->btc, '0.4');
    $this->reverse->handle($original->publicId, 'mistake', PostedBy::admin($this->admin->id));

    $this->reverse->handle($original->publicId, 'again', PostedBy::admin($this->admin->id));
})->throws(CannotReverseJournal::class, 'already been reversed');

it('does not reverse a reversal', function (): void {
    $original = deposit($this->user->id, $this->btc, '0.4');
    $reversal = $this->reverse->handle($original->publicId, 'mistake', PostedBy::admin($this->admin->id));

    $this->reverse->handle($reversal->publicId, 'undo the undo', PostedBy::admin($this->admin->id));
})->throws(CannotReverseJournal::class, 'itself a reversal');

it('fails when the credited funds have been spent', function (): void {
    $original = deposit($this->user->id, $this->btc, '0.4');
    postJournal([EntryDTO::debit(userWallet($this->user->id, $this->btc), '0.3'), EntryDTO::credit(systemWallet($this->btc), '0.3')]);

    expect(fn () => $this->reverse->handle($original->publicId, 'mistake', PostedBy::admin($this->admin->id)))
        ->toThrow(InsufficientBalance::class)
        ->and(Journal::query()->whereNotNull('reverses_journal_id')->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'ledger.journal_reversed')->count())->toBe(0);
});
