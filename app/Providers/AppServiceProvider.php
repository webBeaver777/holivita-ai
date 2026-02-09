<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\AI\AIClientInterface;
use App\Contracts\Onboarding\OnboardingServiceInterface;
use App\Services\AI\AnythingLLMClient;
use App\Services\Onboarding\OnboardingService;
use App\Services\Voice\VoiceClientFactory;
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

        $this->app->singleton(VoiceTranscriptionService::class, function () {
            return new VoiceTranscriptionService(new VoiceClientFactory);
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
