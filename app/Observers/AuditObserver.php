<?php

namespace App\Observers;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

class AuditObserver
{
    private const REDACTED_VALUE = '[protected]';

    public function created(Model $model): void
    {
        AuditLogger::log('created', $model, [
            'attributes' => $this->sanitizeMetadata($model->getAttributes(), $model),
        ]);
    }

    public function updated(Model $model): void
    {
        AuditLogger::log('updated', $model, [
            'changes' => $this->sanitizeMetadata($model->getChanges(), $model),
        ]);
    }

    public function deleted(Model $model): void
    {
        AuditLogger::log('deleted', $model, [
            'attributes' => $this->sanitizeMetadata($model->getAttributes(), $model),
        ]);
    }

    private function sanitizeMetadata(array $metadata, Model $model): array
    {
        if (!str_starts_with($model::class, 'App\\Models\\Pds')) {
            return $metadata;
        }

        return collect($metadata)
            ->map(fn () => self::REDACTED_VALUE)
            ->all();
    }
}
