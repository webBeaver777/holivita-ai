<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\AI\AIClientInterface;
use App\Contracts\Onboarding\OnboardingServiceInterface;
use App\Services\AI\AnythingLLMClient;
use App\Services\Onboarding\OnboardingService;
use App\Services\Voice\OpenAIVoiceClient;
use App\Services\Voice\VoiceTranscriptionService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AIClientInterface::class, function () {
            $config = config('ai.anythingllm');

            return new AnythingLLMClient(
                apiUrl: $config['api_url'],
                apiKey: $config['api_key'] ?? '',
                workspaceSlug: $config['workspace_slug'],
                summaryWorkspaceSlug: $config['summary_workspace_slug'],
            );
        });

        $this->app->singleton(OnboardingServiceInterface::class, OnboardingService::class);

        $this->app->singleton(OpenAIVoiceClient::class, function () {
            return new OpenAIVoiceClient(
                apiKey: (string) config('voice.openai.api_key', ''),
                model: (string) config('voice.openai.model', 'whisper-1'),
                timeout: (int) config('voice.openai.timeout', 60),
            );
        });

        $this->app->singleton(VoiceTranscriptionService::class, function ($app) {
            return new VoiceTranscriptionService(
                client: $app->make(OpenAIVoiceClient::class),
                defaultLanguage: (string) config('voice.default_language', 'ru'),
                storagePath: (string) config('voice.storage_path', 'voice-uploads'),
                storageDisk: (string) config('voice.storage_disk', 'local'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
