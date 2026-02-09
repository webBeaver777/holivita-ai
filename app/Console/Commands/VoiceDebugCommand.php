<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Команда для отладки голосового сервиса.
 */
class VoiceDebugCommand extends Command
{
    protected $signature = 'voice:debug';

    protected $description = 'Проверка подключения к сервисам транскрипции';

    public function handle(): int
    {
        $this->info('=== Отладка голосовых сервисов ===');
        $this->newLine();

        $this->checkAnythingLLM();
        $this->newLine();
        $this->checkOpenAI();
        $this->newLine();
        $this->showConfig();

        return self::SUCCESS;
    }

    private function checkAnythingLLM(): void
    {
        $this->info('📡 AnythingLLM:');

        $apiUrl = config('voice.providers.anythingllm.api_url') ?: config('ai.anythingllm.api_url');
        $apiKey = config('voice.providers.anythingllm.api_key') ?: config('ai.anythingllm.api_key');
        $enabled = config('voice.providers.anythingllm.enabled', true);

        $this->line("   URL: {$apiUrl}");
        $this->line('   API Key: '.($apiKey ? substr($apiKey, 0, 10).'...' : 'НЕ ЗАДАН'));
        $this->line('   Enabled: '.($enabled ? 'Да' : 'Нет'));

        if (! $apiUrl || ! $apiKey) {
            $this->error('   ❌ Конфигурация не полная');

            return;
        }

        // Проверка авторизации
        $this->line('   Проверка авторизации...');
        try {
            $response = Http::timeout(10)
                ->withHeaders(['Authorization' => "Bearer {$apiKey}"])
                ->get("{$apiUrl}/api/v1/auth");

            if ($response->successful()) {
                $this->info('   ✅ Авторизация успешна');
                $this->line('   Ответ: '.json_encode($response->json()));
            } else {
                $this->error("   ❌ Авторизация не удалась: {$response->status()}");
                $this->line('   Ответ: '.$response->body());
            }
        } catch (\Exception $e) {
            $this->error("   ❌ Ошибка подключения: {$e->getMessage()}");
        }

        // Проверка доступных эндпоинтов
        $this->newLine();
        $this->line('   Проверка эндпоинтов...');

        $endpoints = [
            '/api/v1/audio/transcribe' => 'Транскрипция (POST)',
            '/api/v1/system/env' => 'Системная информация',
            '/api/v1/workspaces' => 'Воркспейсы',
            '/api/v1/system/preferences' => 'Настройки системы',
        ];

        foreach ($endpoints as $endpoint => $name) {
            try {
                $response = Http::timeout(5)
                    ->withHeaders(['Authorization' => "Bearer {$apiKey}"])
                    ->get("{$apiUrl}{$endpoint}");

                $status = $response->status();
                $icon = in_array($status, [200, 201, 405]) ? '✅' : '❌';
                $this->line("   {$icon} {$name}: HTTP {$status}");

                // Для системной информации показываем детали
                if ($endpoint === '/api/v1/system/env' && $response->successful()) {
                    $data = $response->json();
                    if (isset($data['settings'])) {
                        $stt = $data['settings']['SpeechToTextProvider'] ?? 'не задан';
                        $this->line("      └─ STT Provider: {$stt}");
                    }
                }
            } catch (\Exception $e) {
                $this->line("   ❌ {$name}: {$e->getMessage()}");
            }
        }
    }

    private function checkOpenAI(): void
    {
        $this->info('🌐 OpenAI Whisper:');

        $apiKey = config('voice.providers.openai.api_key');
        $enabled = config('voice.providers.openai.enabled', false);
        $model = config('voice.providers.openai.model', 'whisper-1');

        $this->line('   API Key: '.($apiKey ? substr($apiKey, 0, 10).'...' : 'НЕ ЗАДАН'));
        $this->line('   Enabled: '.($enabled ? 'Да' : 'Нет'));
        $this->line("   Model: {$model}");

        if (! $apiKey) {
            $this->warn('   ⚠️ API ключ не задан - OpenAI недоступен');

            return;
        }

        // Проверка API
        $this->line('   Проверка API...');
        try {
            $response = Http::timeout(10)
                ->withHeaders(['Authorization' => "Bearer {$apiKey}"])
                ->get('https://api.openai.com/v1/models');

            if ($response->successful()) {
                $this->info('   ✅ API доступен');
            } else {
                $this->error("   ❌ API недоступен: {$response->status()}");
            }
        } catch (\Exception $e) {
            $this->error("   ❌ Ошибка: {$e->getMessage()}");
        }
    }

    private function showConfig(): void
    {
        $this->info('⚙️ Конфигурация:');
        $this->line('   Default Provider: '.config('voice.default_provider', 'anythingllm'));
        $this->line('   Max File Size: '.round(config('voice.max_file_size', 25 * 1024 * 1024) / 1024 / 1024, 1).' MB');
        $this->line('   Timeout: '.config('voice.timeout', 60).' сек');
        $this->line('   Default Language: '.config('voice.default_language', 'ru'));
    }
}
