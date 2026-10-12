<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\OtpChannel;
use App\Domain\Identity\Enums\OtpPurpose;
use App\Domain\Identity\Models\OneTimeCode;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\OtpService;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->otp = app(OtpService::class);
    $this->user = User::factory()->create();
});

it('issues a 6-digit code and stores only its hash', function (): void {
    $code = $this->otp->issue($this->user, OtpPurpose::StepUp, OtpChannel::Email, $this->user->email);

    expect($code)->toMatch('/^\d{6}$/')
        ->and(OneTimeCode::query()->sole()->code_hash)->not->toContain($code);
});

it('verifies a code once', function (): void {
    $code = $this->otp->issue($this->user, OtpPurpose::StepUp, OtpChannel::Email, $this->user->email);

    expect($this->otp->verify($this->user, OtpPurpose::StepUp, $code))->toBeTrue()
        ->and($this->otp->verify($this->user, OtpPurpose::StepUp, $code))->toBeFalse();
});

it('accepts codes typed with spaces', function (): void {
    $code = $this->otp->issue($this->user, OtpPurpose::StepUp, OtpChannel::Email, $this->user->email);

    expect($this->otp->verify($this->user, OtpPurpose::StepUp, substr($code, 0, 3).' '.substr($code, 3)))->toBeTrue();
});

it('only works for the purpose and destination it was issued for', function (): void {
    $code = $this->otp->issue($this->user, OtpPurpose::PhoneChange, OtpChannel::Sms, '+447400123456');

    expect($this->otp->verify($this->user, OtpPurpose::StepUp, $code))->toBeFalse()
        ->and($this->otp->verify($this->user, OtpPurpose::PhoneChange, $code, '+447400999999'))->toBeFalse()
        ->and($this->otp->verify($this->user, OtpPurpose::PhoneChange, $code, '+447400123456'))->toBeTrue();
});

it('expires after 10 minutes', function (): void {
    $code = $this->otp->issue($this->user, OtpPurpose::StepUp, OtpChannel::Email, $this->user->email);

    $this->travel(11)->minutes();

    expect($this->otp->verify($this->user, OtpPurpose::StepUp, $code))->toBeFalse();
});

it('locks a code after 5 wrong guesses', function (): void {
    $code = $this->otp->issue($this->user, OtpPurpose::StepUp, OtpChannel::Email, $this->user->email);
    $wrong = $code === '000000' ? '111111' : '000000';

    for ($i = 0; $i < 5; $i++) {
        $this->otp->verify($this->user, OtpPurpose::StepUp, $wrong);
    }

    expect($this->otp->verify($this->user, OtpPurpose::StepUp, $code))->toBeFalse();
});

it('retires the previous code when a new one is issued', function (): void {
    $first = $this->otp->issue($this->user, OtpPurpose::StepUp, OtpChannel::Email, $this->user->email);
    $second = $this->otp->issue($this->user, OtpPurpose::StepUp, OtpChannel::Email, $this->user->email);

    if ($first !== $second) {
        expect($this->otp->verify($this->user, OtpPurpose::StepUp, $first))->toBeFalse();
    }
    expect($this->otp->verify($this->user, OtpPurpose::StepUp, $second))->toBeTrue();
});

it('limits how many codes can be requested', function (): void {
    for ($i = 0; $i < 3; $i++) {
        $this->otp->issue($this->user, OtpPurpose::StepUp, OtpChannel::Email, $this->user->email);
    }

    expect(fn () => $this->otp->issue($this->user, OtpPurpose::StepUp, OtpChannel::Email, $this->user->email))
        ->toThrow(ValidationException::class);
});
