<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('fingerprint_hash', 64)->comment('sha256 of the device cookie token');
            $table->string('user_agent', 512)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('verified_at')->nullable()->comment('email OTP passed on this device');
            $table->timestamp('trusted_at')->nullable();
            $table->timestamp('trusted_until')->nullable()->comment('skip OTP until then (30 days)');
            $table->timestamps();

            $table->unique(['user_id', 'fingerprint_hash']);
        });

        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->string('ip', 45);
            $table->string('user_agent', 512)->nullable();
            $table->boolean('success');
            $table->string('reason', 32)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['email', 'created_at']);
            $table->index(['ip', 'created_at']);
        });

        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 64);
            $table->string('key_prefix', 16)->comment('first characters of the key, shown in the UI');
            $table->char('key_hash', 64)->unique()->comment('sha256 of the full API key, used for lookup');
            $table->text('secret')->comment('encrypted; HMAC signing needs the server to know it (ADR-0007)');
            $table->json('permissions');
            $table->json('ip_whitelist')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
        });

        Schema::create('one_time_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 32);
            $table->string('channel', 8)->comment('email | sms');
            $table->char('destination_hash', 64)->comment('sha256 of where the code was sent');
            $table->char('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'purpose', 'created_at']);
        });

        Schema::create('contact_changes', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 8)->comment('email | phone');
            $table->text('new_value')->comment('encrypted');
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type', 'confirmed_at']);
        });

        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('document', 32)->comment('terms | privacy | risk_disclosure | marketing | cookies');
            $table->string('version', 32);
            $table->char('jurisdiction', 2)->nullable();
            $table->timestamp('accepted_at');
            $table->string('ip', 45)->nullable();

            $table->unique(['user_id', 'document', 'version']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type', 32)->nullable()->comment('user | admin | system');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 64);
            $table->string('subject_type', 64)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['actor_type', 'actor_id', 'created_at']);
            $table->index(['subject_type', 'subject_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });

        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->string('status', 16)->default('active')->index();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('consents');
        Schema::dropIfExists('contact_changes');
        Schema::dropIfExists('one_time_codes');
        Schema::dropIfExists('api_keys');
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('user_devices');
    }
};
