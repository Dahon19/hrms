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
            $errorBody = $exception->response?->json() ?? $exception->response?->body();

            return [
                'error' => true,
                'message' => is_array($errorBody) ? json_encode($errorBody) : (string) $errorBody,
                'status' => $exception->response?->status(),
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
}
