<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 20)->unique()->comment('E.164');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('password');
            $table->text('two_factor_secret')->nullable()->comment('encrypted by Fortify');
            $table->text('two_factor_recovery_codes')->nullable()->comment('encrypted by Fortify');
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->unsignedTinyInteger('kyc_tier')->default(0);
            $table->string('status', 16)->default('active')->index();
            $table->timestamp('frozen_at')->nullable();
            $table->text('date_of_birth')->nullable()->comment('encrypted');
            $table->char('nationality', 2)->nullable();
            $table->char('residence_country', 2)->index();
            $table->string('timezone', 64)->default('UTC');
            $table->string('locale', 12)->default('en');
            $table->char('preferred_fiat', 3)->default('USD');
            $table->string('theme', 8)->default('dark');
            $table->text('anti_phishing_code')->nullable()->comment('encrypted');
            $table->string('referral_code', 16)->unique();
            $table->foreignId('referred_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('withdrawal_locked_until')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
