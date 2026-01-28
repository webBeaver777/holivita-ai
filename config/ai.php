<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | AnythingLLM Configuration
    |--------------------------------------------------------------------------
    |
    | Настройки для интеграции с AnythingLLM.
    |
    */

    'anythingllm' => [
        'api_url' => env('ANYTHINGLLM_API_URL', 'https://chatbot.meta-whale.com'),
        'api_key' => env('ANYTHINGLLM_API_KEY'),
        'workspace_slug' => env('ANYTHINGLLM_WORKSPACE', 'holionboarding'),
        'summary_workspace_slug' => env('ANYTHINGLLM_SUMMARY_WORKSPACE', 'holisummarization'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | Провайдер AI по умолчанию. Можно легко переключить на другой.
    |
    */

    'default_provider' => env('AI_PROVIDER', 'anythingllm'),

    /*
    |--------------------------------------------------------------------------
    | Onboarding Settings
    |--------------------------------------------------------------------------
    */

    'onboarding' => [
        'welcome_prompt' => env(
            'ONBOARDING_WELCOME_PROMPT',
            'Начни онбординг. Поприветствуй пользователя тепло и задай первый вопрос.'
        ),
        'queue' => env('ONBOARDING_QUEUE', 'onboarding'),
        'job_tries' => (int) env('ONBOARDING_JOB_TRIES', 3),
        'job_backoff' => (int) env('ONBOARDING_JOB_BACKOFF', 10),
    ],

];
