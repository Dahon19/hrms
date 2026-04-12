<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

class EncryptedValueCast implements CastsAttributes
{
    public function __construct(
        private string $type = 'string',
        private int $precision = 2,
    ) {
    }

    public function get($model, string $key, $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        $decrypted = $this->decryptStoredValue((string) $value);

        return match ($this->type) {
            'date' => $decrypted === '' ? null : Carbon::parse($decrypted),
            'decimal' => $decrypted === '' ? null : number_format((float) $decrypted, $this->precision, '.', ''),
            'integer' => $decrypted === '' ? null : (int) $decrypted,
            default => $decrypted,
        };
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        $normalized = match ($this->type) {
            'date' => $this->normalizeDate($value),
            'decimal' => $this->normalizeDecimal($value),
            'integer' => $this->normalizeInteger($value),
            default => $this->normalizeString($value),
        };

        if ($normalized === null) {
            return null;
        }

        return Crypt::encryptString($normalized);
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return Carbon::parse((string) $value)->toDateString();
    }

    private function normalizeDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, $this->precision, '.', '');
    }

    private function normalizeInteger(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) (int) $value;
    }

    private function normalizeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return (string) $value;
    }

    private function decryptStoredValue(string $value): string
    {
        if (!$this->looksEncrypted($value)) {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException $exception) {
            throw $exception;
        }
    }

    private function looksEncrypted(string $value): bool
    {
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return false;
        }

        $payload = json_decode($decoded, true);

        return is_array($payload)
            && array_key_exists('iv', $payload)
            && array_key_exists('value', $payload)
            && array_key_exists('mac', $payload);
    }
}
