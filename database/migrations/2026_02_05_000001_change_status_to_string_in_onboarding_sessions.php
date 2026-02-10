<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onboarding_sessions', function (Blueprint $table) {
            $table->string('status', 20)->default('in_progress')->change();
        });
    }

    public function down(): void
    {
        Schema::table('onboarding_sessions', function (Blueprint $table) {
            $table->enum('status', ['in_progress', 'completed', 'cancelled', 'expired'])
                ->default('in_progress')
                ->change();
        });
    }
};
