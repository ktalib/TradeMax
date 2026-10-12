<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What custody actually holds, entered by finance or read from a provider. Append-only.
        Schema::create('custody_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->string('source', 16)->comment('manual | gateway');
            $table->decimal('balance', 36, 18);
            $table->timestamp('as_of');
            $table->foreignId('recorded_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['asset_id', 'as_of']);
        });

        Schema::create('reconciliation_reports', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->string('status', 16)->index()->comment('matched | mismatch | missing');
            $table->decimal('ledger_custody', 36, 18)->comment('-(system_hot + system_cold)');
            $table->decimal('reported_custody', 36, 18)->nullable();
            $table->decimal('difference', 36, 18)->nullable()->comment('reported - ledger');
            $table->decimal('tolerance', 36, 18);
            $table->foreignId('custody_balance_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('withdrawals_halted')->default(false);
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['asset_id', 'created_at']);
        });

        Schema::create('liability_proofs', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->date('snapshot_date');
            $table->char('root_hash', 64);
            $table->decimal('total', 36, 18);
            $table->unsignedInteger('leaf_count');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['asset_id', 'snapshot_date']);
        });

        Schema::create('liability_proof_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liability_proof_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('position');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('balance', 36, 18);
            $table->char('nonce', 32)->comment('random per leaf so balances cannot be guessed from hashes');
            $table->char('leaf_hash', 64);

            $table->unique(['liability_proof_id', 'position']);
            $table->unique(['liability_proof_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liability_proof_leaves');
        Schema::dropIfExists('liability_proofs');
        Schema::dropIfExists('reconciliation_reports');
        Schema::dropIfExists('custody_balances');
    }
};
