<?php
declare(strict_types=1);

/** Server-side input checks. Each method returns the cleaned value or throws a 400 ApiException. */
class Validator
{
    /** Required text, trimmed, at most $max characters. */
    public static function text(array $data, string $key, string $label, int $max): string
    {
        $value = $data[$key] ?? '';
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            throw new ApiException("$label is required.", 400);
        }
        if (mb_strlen($value) > $max) {
            throw new ApiException("$label must be at most $max characters.", 400);
        }
        return $value;
    }

    public static function email(array $data, string $key, string $label): string
    {
        $value = self::text($data, $key, $label, 255);
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new ApiException("$label is not a valid email address.", 400);
        }
        return $value;
    }

    /** Required date in YYYY-MM-DD format that really exists (so 2026-02-30 is rejected). */
    public static function date(array $data, string $key, string $label): string
    {
        $value = self::text($data, $key, $label, 10);
        $d = DateTime::createFromFormat('!Y-m-d', $value);
        if ($d === false || $d->format('Y-m-d') !== $value) {
            throw new ApiException("$label must be a valid date (YYYY-MM-DD).", 400);
        }
        return $value;
    }

    /** Required whole number between $min and $max. */
    public static function intRange(array $data, string $key, string $label, int $min, int $max): int
    {
        $value = filter_var($data[$key] ?? null, FILTER_VALIDATE_INT);
        if ($value === false || $value < $min || $value > $max) {
            throw new ApiException("$label must be a whole number between $min and $max.", 400);
        }
        return $value;
    }

    /** Required positive id (used for references to other records). */
    public static function id(mixed $raw, string $label): int
    {
        $value = filter_var($raw, FILTER_VALIDATE_INT);
        if ($value === false || $value < 1) {
            throw new ApiException("$label is required.", 400);
        }
        return $value;
    }

    /** Value must be one of $allowed. */
    public static function oneOf(array $data, string $key, string $label, array $allowed): string
    {
        $value = $data[$key] ?? '';
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            throw new ApiException("$label must be one of: " . implode(', ', $allowed) . '.', 400);
        }
        return $value;
    }
}
