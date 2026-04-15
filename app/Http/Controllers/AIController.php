<?php

namespace App\Http\Controllers;

use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AIController extends Controller
{
    public function __construct(
        protected GeminiService $gemini,
    ) {
    }

    public function test(): JsonResponse
    {
        $result = $this->gemini->generateText('Reply with a short greeting for the HRMS chatbot.');

        if ($result['error'] ?? false) {
            return response()->json([
                'ok' => false,
                'message' => $result['message'] ?? 'Gemini request failed.',
                'status' => $result['status'] ?? 500,
            ], 502);
        }

        return response()->json([
            'ok' => true,
            'model' => config('services.gemini.model'),
            'reply' => $result['text'] ?? '',
        ]);
    }

    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['nullable', 'string'],
            'history' => ['nullable', 'array'],
            'history.*.role' => ['required_with:history', 'string'],
            'history.*.text' => ['required_with:history', 'string'],
            'system_instruction' => ['nullable', 'string'],
        ]);

        $messages = collect($validated['history'] ?? [])
            ->map(fn (array $message): array => [
                'role' => $message['role'],
                'text' => $message['text'],
            ])
            ->values()
            ->all();

        if (filled($validated['message'] ?? null)) {
            $messages[] = [
                'role' => 'user',
                'text' => $validated['message'],
            ];
        }

        try {
            $result = $this->gemini->chat(
                $messages,
                $validated['system_instruction'] ?? 'You are a helpful HRMS assistant.',
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        if ($result['error'] ?? false) {
            return response()->json([
                'ok' => false,
                'message' => $result['message'] ?? 'Gemini request failed.',
                'status' => $result['status'] ?? 500,
            ], 502);
        }

        return response()->json([
            'ok' => true,
            'reply' => $result['text'] ?? '',
            'raw' => $result['raw'] ?? [],
        ]);
    }
}
