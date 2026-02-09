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
        Schema::create('voice_transcriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id')->index();
            $table->uuid('session_id')->nullable()->index();
            $table->string('provider')->nullable();
            $table->string('language', 5)->default('ru');
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('mime_type');
            $table->unsignedInteger('file_size');
            $table->enum('status', MessageStatus::values())->default(MessageStatus::PENDING->value);
            $table->text('transcribed_text')->nullable();
            $table->float('confidence')->nullable();
            $table->float('duration')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_transcriptions');
    }
};
