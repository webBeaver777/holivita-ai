<?php

declare(strict_types=1);

use App\Enums\OnboardingStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('onboarding_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->enum('status', OnboardingStatus::values())
                ->default(OnboardingStatus::IN_PROGRESS->value);
            $table->json('summary_json')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Индексы для быстрого поиска
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('onboarding_sessions');
    }
};
