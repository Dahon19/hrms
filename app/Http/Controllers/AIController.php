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
        $result = $this->gemini->generateText(
            'Briefly describe the purpose of the Northeastern College HRMS.',
            $this->chatSystemInstruction(),
        );

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
                $this->chatSystemInstruction(),
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

    protected function chatSystemInstruction(): string
    {
        return implode(' ', [
            'You are the official HRMS assistant for Northeastern College.',
            'Only answer questions that are directly related to the HRMS system, its purpose, its modules,',
            'its workflows, or how the organization uses the system.',
            'The organization uses this system for recruitment, employee records, leave management, attendance,',
            'performance evaluation, offboarding, and related HR operations.',
            'If a question is outside the HRMS or organization-use context, refuse briefly and redirect the user back to system-related topics.',
            'Do not invent records, approvals, employee data, or organization policies that are not provided in the prompt.',
            'Keep answers concise, practical, and professional.',
        ]);
    }
}
