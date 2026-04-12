<?php

namespace App\Support;

use Illuminate\Support\ViewErrorBag;

class FormValidation
{
    public static function matchesContext(?string $context = null): bool
    {
        return blank($context) || old('form_context') === $context;
    }

    public static function hasError(string|array $fields, ?string $context = null, string $bag = 'default'): bool
    {
        if (!self::matchesContext($context)) {
            return false;
        }

        $errorBag = self::errorBag($bag);
        if ($errorBag === null) {
            return false;
        }

        foreach ((array) $fields as $field) {
            if ($errorBag->has($field)) {
                return true;
            }
        }

        return false;
    }

    public static function invalidClass(string|array $fields, ?string $context = null, string $bag = 'default'): string
    {
        return self::hasError($fields, $context, $bag) ? 'is-invalid' : '';
    }

    public static function firstError(string|array $fields, ?string $context = null, string $bag = 'default'): ?string
    {
        if (!self::matchesContext($context)) {
            return null;
        }

        $errorBag = self::errorBag($bag);
        if ($errorBag === null) {
            return null;
        }

        foreach ((array) $fields as $field) {
            $message = $errorBag->first($field);
            if ($message !== '') {
                return $message;
            }
        }

        return null;
    }

    private static function errorBag(string $bag = 'default'): ?\Illuminate\Support\MessageBag
    {
        $errors = session('errors');
        if (!$errors instanceof ViewErrorBag) {
            return null;
        }

        return $errors->getBag($bag);
    }
}
