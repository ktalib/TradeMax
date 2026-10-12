<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Registration needs the country gate in Phase 1, so the table starts here with the
| geo-restriction columns. Phase 3 (KYC) adds the tier and document columns.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->char('code', 2)->primary()->comment('ISO 3166-1 alpha-2');
            $table->string('name');
            $table->string('status', 16)->default('allowed')->index()->comment('allowed | restricted | blocked');
            $table->json('features_disabled')->nullable()->comment('e.g. ["staking","copy_trading","signals"]');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
