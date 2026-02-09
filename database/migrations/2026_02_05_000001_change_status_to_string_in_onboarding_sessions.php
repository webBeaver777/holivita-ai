<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE onboarding_sessions MODIFY COLUMN status VARCHAR(20) DEFAULT 'in_progress'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE onboarding_sessions MODIFY COLUMN status ENUM('in_progress', 'completed', 'cancelled', 'expired') DEFAULT 'in_progress'");
    }
};
