<?php

namespace App\Services;

use App\Events\HrmsNotificationCreated;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class HrmsNotificationService
{
    /**
     * @param Collection<int, User>|array<int, User> $users
     * @param array<string, mixed> $payload
     */
    public function notifyUsers(Collection|array $users, array $payload): void
    {
        $collection = ($users instanceof Collection ? $users : collect($users))
            ->filter(fn ($user) => $user instanceof User)
            ->unique('id')
            ->values();
        if ($collection->isEmpty()) {
            return;
        }

        $type = $this->normalizeType((string) ($payload['type'] ?? 'info'));
        $title = trim((string) ($payload['title'] ?? 'Notification'));
        $message = trim((string) ($payload['message'] ?? ''));
        if ($message === '') {
            return;
        }

        $module = trim((string) ($payload['module'] ?? 'general'));
        $recordId = $payload['record_id'] ?? null;
        $routeName = $this->normalizeRouteName($payload['route_name'] ?? null);
        $routeParams = $this->normalizeRouteParams($payload['route_params'] ?? []);
        if (!$routeName) {
            [$legacyRouteName, $legacyRouteParams] = $this->resolveRouteFromActionUrl($payload['action_url'] ?? null);
            $routeName = $legacyRouteName;
            $routeParams = $legacyRouteParams;
        }
        $senderType = (string) ($payload['sender_type'] ?? 'system');
        $senderId = $payload['sender_id'] ?? null;
        $senderName = $payload['sender_name'] ?? null;
        $senderRole = $payload['sender_role'] ?? null;
        $eventKey = trim((string) ($payload['event_key'] ?? 'system.event'));
        $priority = $this->normalizePriority((string) ($payload['priority'] ?? 'normal'));

        $createdAt = now();
        $rows = [];

        foreach ($collection as $user) {
            if (
                !$user instanceof User
                || $this->hasRecentDuplicateNotification(
                    $user,
                    $eventKey,
                    $module,
                    $recordId,
                    $title,
                    $message
                )
            ) {
                continue;
            }

            $rows[] = [
                'id' => (string) Str::uuid(),
                'type' => 'App\\Notifications\\HrmsDatabaseNotification',
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => json_encode([
                    'title' => $title,
                    'message' => $message,
                    'type' => $type,
                    'module' => $module,
                    'record_id' => $recordId,
                    'route_name' => $routeName,
                    'route_params' => $routeParams,
                    'action_url' => null,
                    'sender_type' => $senderType,
                    'sender_id' => $senderId,
                    'sender_name' => $senderName,
                    'sender_role' => $senderRole,
                    'recipient_user_id' => $user->id,
                    'event_key' => $eventKey,
                    'priority' => $priority,
                ], JSON_UNESCAPED_UNICODE),
                'read_at' => null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];
        }

        if (empty($rows)) {
            return;
        }

        DatabaseNotification::query()->insert($rows);

        $freshNotifications = DatabaseNotification::query()
            ->whereIn('id', collect($rows)->pluck('id')->all())
            ->orderBy('created_at')
            ->get()
            ->keyBy(function (DatabaseNotification $notification) {
                return (string) $notification->id;
            });

        foreach ($rows as $row) {
            /** @var DatabaseNotification|null $notification */
            $notification = $freshNotifications->get($row['id']);
            if (!$notification) {
                continue;
            }

            HrmsNotificationCreated::dispatch(
                (int) $notification->notifiable_id,
                $this->toFrontendPayload($notification)
            );
        }
    }

    private function hasRecentDuplicateNotification(
        User $user,
        string $eventKey,
        string $module,
        mixed $recordId,
        string $title,
        string $message
    ): bool {
        $recentNotifications = DatabaseNotification::query()
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->where('type', 'App\\Notifications\\HrmsDatabaseNotification')
            ->where('created_at', '>=', now()->subMinutes(5))
            ->get();

        return $recentNotifications->contains(function (DatabaseNotification $notification) use ($eventKey, $module, $recordId, $title, $message) {
            $data = is_array($notification->data) ? $notification->data : [];

            return (string) ($data['event_key'] ?? '') === $eventKey
                && (string) ($data['module'] ?? '') === $module
                && (string) ($data['record_id'] ?? '') === (string) ($recordId ?? '')
                && (string) ($data['title'] ?? '') === $title
                && (string) ($data['message'] ?? '') === $message;
        });
    }

    /**
     * @return array<int, User>
     */
    public function resolveRecipientsForModule(string $module): array
    {
        return match (strtolower(trim($module))) {
            'recruitment' => AccessControl::adminUsers()
                ->merge(AccessControl::hrHeadUsers())
                ->unique('id')
                ->values()
                ->all(),
            'attendance' => AccessControl::adminUsers()
                ->merge(AccessControl::hrHeadUsers())
                ->unique('id')
                ->values()
                ->all(),
            default => AccessControl::adminUsers()->values()->all(),
        };
    }

    /**
     * @param Collection<int, User>|array<int, User> $users
     */
    public function notifyByRoles(Collection|array $users, array $payload): void
    {
        $this->notifyUsers($users, $payload);
    }

    public function formatSender(?User $actor): array
    {
        if (!$actor) {
            return [
                'sender_type' => 'system',
                'sender_id' => null,
                'sender_name' => 'System',
                'sender_role' => 'system',
            ];
        }

        return [
            'sender_type' => 'user',
            'sender_id' => $actor->id,
            'sender_name' => $actor->name,
            'sender_role' => $actor->role ?? $actor->positionName(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toFrontendPayload(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        return [
            'id' => (string) $notification->id,
            'title' => (string) ($data['title'] ?? 'Notification'),
            'message' => (string) ($data['message'] ?? ''),
            'type' => $this->normalizeType((string) ($data['type'] ?? 'info')),
            'module' => (string) ($data['module'] ?? 'general'),
            'record_id' => $data['record_id'] ?? null,
            'route_name' => $this->normalizeRouteName($data['route_name'] ?? null),
            'route_params' => $this->normalizeRouteParams($data['route_params'] ?? []),
            'action_url' => null,
            'sender_type' => (string) ($data['sender_type'] ?? 'system'),
            'sender_id' => $data['sender_id'] ?? null,
            'sender_name' => (string) ($data['sender_name'] ?? 'System'),
            'sender_role' => (string) ($data['sender_role'] ?? 'system'),
            'recipient_user_id' => (int) $notification->notifiable_id,
            'read' => $notification->read_at !== null,
            'read_at' => optional($notification->read_at)?->toIso8601String(),
            'created_at' => optional($notification->created_at)?->toIso8601String(),
            'event_key' => (string) ($data['event_key'] ?? 'system.event'),
            'priority' => $this->normalizePriority((string) ($data['priority'] ?? 'normal')),
            'redirect_url' => route('notifications.redirect', ['notification' => (string) $notification->id]),
        ];
    }

    private function normalizeType(string $type): string
    {
        $normalized = strtolower(trim($type));
        return in_array($normalized, ['success', 'info', 'warning', 'error'], true) ? $normalized : 'info';
    }

    private function normalizePriority(string $priority): string
    {
        $normalized = strtolower(trim($priority));
        return in_array($normalized, ['low', 'normal', 'high', 'critical'], true) ? $normalized : 'normal';
    }

    private function normalizeActionUrl(mixed $actionUrl): ?string
    {
        $url = is_string($actionUrl) ? trim($actionUrl) : '';
        return $url !== '' ? $url : null;
    }

    private function normalizeRouteName(mixed $routeName): ?string
    {
        $name = is_string($routeName) ? trim($routeName) : '';
        return $name !== '' ? $name : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeRouteParams(mixed $routeParams): array
    {
        if (!is_array($routeParams)) {
            return [];
        }

        return collect($routeParams)
            ->filter(fn ($value, $key) => is_string($key) && $key !== '')
            ->map(function ($value) {
                if (is_scalar($value) || is_null($value)) {
                    return $value;
                }
                return (string) $value;
            })
            ->all();
    }

    /**
     * @return array{0: string|null, 1: array<string, mixed>}
     */
    private function resolveRouteFromActionUrl(mixed $actionUrl): array
    {
        $url = $this->normalizeActionUrl($actionUrl);
        if (!$url) {
            return [null, []];
        }

        try {
            $parsed = parse_url($url);
            $path = (string) ($parsed['path'] ?? '');
            $query = (string) ($parsed['query'] ?? '');
            if ($path === '') {
                return [null, []];
            }

            $appBasePath = parse_url(url('/'), PHP_URL_PATH) ?: '';
            $candidates = [$path];
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
                    $matched = app('router')->getRoutes()->match($request);
                    $name = $matched->getName();
                    if (!$name || !Route::has($name)) {
                        continue;
                    }

                    return [$name, $matched->parameters()];
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
}
