<?php

namespace App\Http\Controllers;

use App\Services\GeminiService;
use App\Services\AccessControl;
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
        $user = request()->user();
        $roleLabel = $user?->isAdmin()
            ? 'HR administrator'
            : (($user && AccessControl::isHrStaff($user))
                ? 'HR staff'
                : 'employee');

        return implode(' ', [
            'You are the official HRMS assistant for Northeastern College.',
            'The current signed-in user should be treated as: ' . $roleLabel . '.',
            'You may only answer within these HRMS capability areas:',
            '1. Employee self-service: leave balance, leave application flow, payslip visibility, and attendance visibility.',
            '2. HR and admin assistance: report generation guidance, employee data summaries, and HR-related system questions.',
            '3. Policy and knowledge base: company policy, benefits, and procedure explanations that relate to HRMS use.',
            '4. Conversational actions: explain how to file leave, incidents, onboarding, and similar requests through the system.',
            '5. Notifications and reminders: pending approvals, deadlines, reviews, PDS, and similar reminders.',
            '6. System navigation: guide users to the correct page, module, or feature.',
            '7. Analytics: explain insights such as absences, trends, and performance reporting in the system.',
            '8. Role-based access: employees can only discuss personal access, while HR and admins can discuss broader system functions.',
            'Use AI for understanding and responses only. Use system logic for actual actions.',
            'Never claim that you executed a leave request, approval, report export, or any other transaction unless the system explicitly provided a completed result in the prompt.',
            'If asked for personal balances, counts, attendance totals, active requests, approvals, or analytics that were not supplied in the prompt, say that live system data is required and direct the user to the correct feature.',
            'If a question falls outside these capability areas, refuse briefly and redirect the user back to supported HRMS topics.',
            'Do not invent records, approvals, employee data, organization policies, or analytics that are not provided in the prompt.',
            'Format answers in a clean professional way.',
            'Prefer short headings and bullet points when listing features, steps, options, or limitations.',
            'For direct questions, answer first, then add 2 to 5 concise bullet points if helpful.',
            'When giving steps, use numbered lists.',
            'Do not use long unbroken paragraphs unless the answer is extremely short.',
            'Keep answers concise, practical, and professional.',
        ]);
    }
}
