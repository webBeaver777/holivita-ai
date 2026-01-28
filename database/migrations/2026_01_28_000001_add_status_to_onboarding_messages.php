<?php

declare(strict_types=1);

use App\Enums\MessageStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onboarding_messages', function (Blueprint $table) {
            $table->enum('status', MessageStatus::values())
                ->default(MessageStatus::COMPLETED->value)
                ->after('content');
            $table->text('error_message')->nullable()->after('status');

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('onboarding_messages', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'error_message']);
        });
    }
};
