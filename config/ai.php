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

];
