<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class GeminiService
{
    protected string $apiKey;
    protected string $model;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = (string) (config('services.gemini.api_key') ?: env('GEMINI_API_KEY', ''));
        $this->model = (string) config('services.gemini.model', 'gemini-2.5-flash');
        $this->baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';
    }

    public function generateText(string $prompt, ?string $systemInstruction = null): array
    {
        return $this->chat([
            [
                'role' => 'user',
                'text' => $prompt,
            ],
        ], $systemInstruction);
    }

    public function chat(array $messages, ?string $systemInstruction = null): array
    {
        if ($this->apiKey === '') {
            throw new InvalidArgumentException('Gemini API key is not configured.');
        }

        $contents = collect($messages)
            ->filter(fn ($message) => filled($message['text'] ?? null))
            ->map(function ($message): array {
                $role = strtolower((string) ($message['role'] ?? 'user')) === 'model' ? 'model' : 'user';

                return [
                    'role' => $role,
                    'parts' => [
                        ['text' => (string) $message['text']],
                    ],
                ];
            })
            ->values()
            ->all();

        if ($contents === []) {
            throw new InvalidArgumentException('At least one chat message is required.');
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.7,
                'topP' => 0.9,
                'maxOutputTokens' => 1024,
            ],
        ];

        if (filled($systemInstruction)) {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => (string) $systemInstruction],
                ],
            ];
        }

        try {
            $response = Http::timeout(30)
                ->retry(2, 250)
                ->withHeaders([
                    'x-goog-api-key' => $this->apiKey,
                ])
                ->post("{$this->baseUrl}/{$this->model}:generateContent", $payload)
                ->throw();
        } catch (RequestException $exception) {
            $status = $exception->response?->status();
            $errorBody = $exception->response?->json() ?? $exception->response?->body();

            return [
                'error' => true,
                'message' => $this->normalizeErrorMessage($errorBody, $status),
                'status' => $status,
            ];
        }

        $data = $response->json();
        $parts = data_get($data, 'candidates.0.content.parts', []);
        $text = collect($parts)
            ->pluck('text')
            ->filter()
            ->implode("\n");

        return [
            'error' => false,
            'text' => $text,
            'raw' => $data,
        ];
    }

    protected function normalizeErrorMessage(array|string|null $errorBody, ?int $status): string
    {
        $providerMessage = is_array($errorBody)
            ? (string) data_get($errorBody, 'error.message', '')
            : trim((string) $errorBody);

        if ($status === 503) {
            return 'The assistant is temporarily unavailable because the AI service is under heavy load. Please try again in a moment.';
        }

        if ($status === 429) {
            return 'The assistant is receiving too many requests right now. Please wait a moment and try again.';
        }

        if ($status === 401 || $status === 403) {
            return 'The assistant could not authenticate with the AI service. Check the Gemini production credentials.';
        }

        if ($providerMessage !== '') {
            return $providerMessage;
        }

        return 'The assistant is unavailable right now. Please try again in a moment.';
    }
}
