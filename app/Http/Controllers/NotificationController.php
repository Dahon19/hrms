<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\JobPosting;
use App\Models\LeaveRequest;
use App\Models\PdsProfile;
use App\Models\TravelOrder;
use App\Services\HrmsNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class NotificationController extends Controller
{
    public function index(Request $request, HrmsNotificationService $notificationService): JsonResponse
    {
        $limit = max(1, min((int) $request->query('limit', 10), 500));
        $user = $request->user();
        $totalCount = (int) $user->notifications()->count();

        $notifications = $user->notifications()
            ->latest('created_at')
            ->limit($limit)
            ->get();

        return response()->json([
            'status' => 'success',
            'notifications' => $notifications->map(
                fn (DatabaseNotification $notification) => $notificationService->toFrontendPayload($notification)
            )->values(),
            'unread_count' => (int) $user->unreadNotifications()->count(),
            'limit' => $limit,
            'total_count' => $totalCount,
            'has_more' => $totalCount > $notifications->count(),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'unread_count' => (int) $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $dbNotification = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        if ($dbNotification->read_at === null) {
            $dbNotification->markAsRead();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Notification marked as read.',
            'unread_count' => (int) $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'status' => 'success',
            'message' => 'All notifications marked as read.',
            'unread_count' => 0,
        ]);
    }

    public function redirect(Request $request, string $notification)
    {
        $dbNotification = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $data = is_array($dbNotification->data) ? $dbNotification->data : [];

        [$routeName, $routeParams] = $this->resolveTargetRoute($data);

        if (!$routeName || !Route::has($routeName)) {
            Log::warning('Notification redirect failed: route not found.', [
                'notification_id' => (string) $dbNotification->id,
                'user_id' => (int) $request->user()->id,
                'route_name' => $routeName,
                'route_params' => $routeParams,
            ]);

            if ($dbNotification->read_at === null) {
                $dbNotification->markAsRead();
            }

            return redirect()
                ->route('dashboard')
                ->with('error', 'Notification target is no longer available.');
        }

        if (!$this->recordsExistForRouteParams($routeParams)) {
            Log::warning('Notification redirect failed: target record missing.', [
                'notification_id' => (string) $dbNotification->id,
                'user_id' => (int) $request->user()->id,
                'route_name' => $routeName,
                'route_params' => $routeParams,
            ]);

            if ($dbNotification->read_at === null) {
                $dbNotification->markAsRead();
            }

            return redirect()
                ->route('dashboard')
                ->with('error', 'Notification target record is unavailable.');
        }

        if ($dbNotification->read_at === null) {
            $dbNotification->markAsRead();
        }

        return redirect()->route($routeName, $routeParams);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{0: string|null, 1: array<string, mixed>}
     */
    private function resolveTargetRoute(array $data): array
    {
        $routeName = trim((string) ($data['route_name'] ?? ''));
        $routeParams = Arr::wrap($data['route_params'] ?? []);
        if (!is_array($routeParams)) {
            $routeParams = [];
        }

        $routeParams = collect($routeParams)
            ->filter(fn ($value, $key) => is_string($key) && $key !== '')
            ->map(function ($value) {
                if (is_scalar($value) || is_null($value)) {
                    return $value;
                }
                return (string) $value;
            })
            ->all();

        if ($routeName !== '') {
            return [$routeName, $routeParams];
        }

        $legacyActionUrl = trim((string) ($data['action_url'] ?? ''));
        if ($legacyActionUrl === '') {
            return [null, []];
        }

        return $this->resolveLegacyRouteFromUrl($legacyActionUrl);
    }

    /**
     * @return array{0: string|null, 1: array<string, mixed>}
     */
    private function resolveLegacyRouteFromUrl(string $legacyActionUrl): array
    {
        try {
            $parsed = parse_url($legacyActionUrl);
            $path = (string) ($parsed['path'] ?? '');
            $query = (string) ($parsed['query'] ?? '');
            if ($path === '') {
                return [null, []];
            }

            $candidates = [];
            $appBasePath = parse_url(url('/'), PHP_URL_PATH) ?: '';
            $candidates[] = $path;
            if ($appBasePath !== '' && $appBasePath !== '/' && str_starts_with($path, $appBasePath)) {
                $trimmed = substr($path, strlen($appBasePath));
                $candidates[] = $trimmed === '' ? '/' : $trimmed;
            }
            // Legacy absolute URLs may include an extra leading app folder (e.g. /hrms/...).
            $segments = array_values(array_filter(explode('/', trim($path, '/'))));
            if (count($segments) > 1) {
                $candidates[] = '/' . implode('/', array_slice($segments, 1));
            }

            $candidates = array_values(array_unique(array_filter($candidates, fn ($candidate) => is_string($candidate) && $candidate !== '')));
            foreach ($candidates as $candidatePath) {
                try {
                    $request = Request::create($candidatePath . ($query ? ('?' . $query) : ''), 'GET');
                    $matchedRoute = app('router')->getRoutes()->match($request);
                    $routeName = $matchedRoute->getName();
                    if (!$routeName) {
                        continue;
                    }

                    return [$routeName, $matchedRoute->parameters()];
                } catch (NotFoundHttpException $e) {
                    continue;
                } catch (\Throwable $e) {
                    continue;
                }
            }

            return [null, []];
        } catch (\Throwable $e) {
            return [null, []];
        }
    }

    /**
     * @param array<string, mixed> $routeParams
     */
    private function recordsExistForRouteParams(array $routeParams): bool
    {
        if (empty($routeParams)) {
            return true;
        }

        $bindings = [
            'leave' => LeaveRequest::class,
            'leave_request' => LeaveRequest::class,
            'employee' => Employee::class,
            'employee_document' => EmployeeDocument::class,
            'employeeDocument' => EmployeeDocument::class,
            'jobPosting' => JobPosting::class,
            'job_posting' => JobPosting::class,
            'applicant' => Applicant::class,
            'pds_profile' => PdsProfile::class,
            'pdsProfile' => PdsProfile::class,
            'travel_order' => TravelOrder::class,
            'travelOrder' => TravelOrder::class,
        ];

        foreach ($routeParams as $key => $value) {
            if (!isset($bindings[$key])) {
                continue;
            }

            $modelClass = $bindings[$key];
            $id = is_object($value) ? null : $value;
            if (!is_numeric($id)) {
                continue;
            }

            $usesSoftDeletes = in_array(SoftDeletes::class, class_uses_recursive($modelClass), true);
            $query = $modelClass::query();
            if ($usesSoftDeletes) {
                $query = $query->withTrashed();
            }

            if (!$query->whereKey((int) $id)->exists()) {
                return false;
            }
        }

        return true;
    }
}
