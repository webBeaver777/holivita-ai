<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Contracts\AI\AIClientInterface;
use App\DTOs\AI\ChatRequestDTO;
use App\DTOs\AI\ChatResponseDTO;
use App\DTOs\AI\SummarizeRequestDTO;
use App\DTOs\AI\SummarizeResponseDTO;
use App\Exceptions\AIClientException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Клиент для AnythingLLM API.
 */
final class AnythingLLMClient implements AIClientInterface
{
    private const COMPLETION_MARKER = '[ONBOARDING_COMPLETE]';

    private const CHAT_TIMEOUT = 60;

    private const SUMMARY_TIMEOUT = 120;

    public function __construct(
        private readonly string $apiUrl,
        private readonly string $apiKey,
        private readonly string $workspaceSlug,
        private readonly string $summaryWorkspaceSlug,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function chat(ChatRequestDTO $request): ChatResponseDTO
    {
        $response = $this->sendRequest(
            workspace: $this->workspaceSlug,
            message: $request->message,
            sessionId: $request->sessionId,
            mode: 'chat',
            timeout: self::CHAT_TIMEOUT,
        );

        $message = $this->extractMessage($response);
        $isComplete = str_contains($message, self::COMPLETION_MARKER);
        $cleanMessage = trim(str_replace(self::COMPLETION_MARKER, '', $message));

        return new ChatResponseDTO(
            message: $cleanMessage,
            isComplete: $isComplete,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function summarize(SummarizeRequestDTO $request): SummarizeResponseDTO
    {
        $dialogue = $this->formatDialogue($request->messages);

        $response = $this->sendRequest(
            workspace: $this->summaryWorkspaceSlug,
            message: $dialogue,
            sessionId: $request->sessionId,
            mode: 'chat',
            timeout: self::SUMMARY_TIMEOUT,
        );

        $rawResponse = $this->extractMessage($response);

        return new SummarizeResponseDTO(
            summary: $this->parseJson($rawResponse),
        );
    }

    /**
     * Отправить запрос к API.
     *
     * @return array<string, mixed>
     *
     * @throws AIClientException
     */
    private function sendRequest(
        string $workspace,
        string $message,
        ?string $sessionId,
        string $mode,
        int $timeout,
    ): array {
        $endpoint = "{$this->apiUrl}/api/v1/workspace/{$workspace}/chat";

        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($endpoint, [
                    'message' => $message,
                    'mode' => $mode,
                    'sessionId' => $sessionId ?? 'default',
                ]);

            if (! $response->successful()) {
                Log::error('AnythingLLM API error', [
                    'workspace' => $workspace,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new AIClientException("AnythingLLM API error: {$response->status()}");
            }

            return $response->json();
        } catch (ConnectionException $e) {
            Log::error('AnythingLLM connection error', ['error' => $e->getMessage()]);
            throw new AIClientException('Could not connect to AnythingLLM', previous: $e);
        }
    }

    /**
     * Извлечь сообщение из ответа API.
     *
     * @param  array<string, mixed>  $response
     */
    private function extractMessage(array $response): string
    {
        return $response['textResponse'] ?? $response['response'] ?? '';
    }

    /**
     * Форматировать диалог для суммаризации.
     *
     * @param  array<array{role: string, content: string}>  $messages
     */
    private function formatDialogue(array $messages): string
    {
        $lines = array_map(
            fn (array $msg) => sprintf(
                '%s: %s',
                $msg['role'] === 'user' ? 'Пользователь' : 'HOLI',
                $msg['content']
            ),
            $messages
        );

        return implode("\n\n", $lines);
    }

    /**
     * Извлечь JSON из ответа.
     *
     * @return array<string, mixed>
     */
    private function parseJson(string $response): array
    {
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            try {
                $decoded = json_decode($matches[0], true, 512, JSON_THROW_ON_ERROR);

                return is_array($decoded) ? $decoded : [];
            } catch (\JsonException $e) {
                Log::warning('Failed to parse JSON from AI response', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'raw_response' => $response,
            'parse_error' => true,
        ];
    }
}
