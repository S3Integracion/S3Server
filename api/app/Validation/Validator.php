<?php
declare(strict_types=1);

namespace App\Validation;

use App\Core\HttpException;

final class Validator
{
    public static function requiredString(array $data, string $field, int $min = 1, int $max = 190): string
    {
        $value = $data[$field] ?? null;
        if (!is_string($value)) {
            throw new HttpException(422, 'validation_error', sprintf('Field "%s" must be a string.', $field));
        }

        $trimmed = trim($value);
        $length = strlen($trimmed);
        if ($length < $min || $length > $max) {
            throw new HttpException(
                422,
                'validation_error',
                sprintf('Field "%s" length must be between %d and %d characters.', $field, $min, $max)
            );
        }

        return $trimmed;
    }

    public static function optionalString(array $data, string $field, int $min = 0, int $max = 255): ?string
    {
        if (!array_key_exists($field, $data) || $data[$field] === null) {
            return null;
        }

        if (!is_string($data[$field])) {
            throw new HttpException(422, 'validation_error', sprintf('Field "%s" must be a string.', $field));
        }

        $trimmed = trim($data[$field]);
        $length = strlen($trimmed);
        if ($length < $min || $length > $max) {
            throw new HttpException(
                422,
                'validation_error',
                sprintf('Field "%s" length must be between %d and %d characters.', $field, $min, $max)
            );
        }

        return $trimmed;
    }

    public static function requiredEmail(array $data, string $field): string
    {
        $value = self::requiredString($data, $field, 5, 190);
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new HttpException(422, 'validation_error', sprintf('Field "%s" must be a valid email.', $field));
        }

        return strtolower($value);
    }

    public static function optionalEmail(array $data, string $field): ?string
    {
        if (!array_key_exists($field, $data) || $data[$field] === null) {
            return null;
        }

        if (!is_string($data[$field])) {
            throw new HttpException(422, 'validation_error', sprintf('Field "%s" must be a valid email.', $field));
        }

        $value = strtolower(trim($data[$field]));
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new HttpException(422, 'validation_error', sprintf('Field "%s" must be a valid email.', $field));
        }

        return $value;
    }

    public static function optionalBool(array $data, string $field): ?bool
    {
        if (!array_key_exists($field, $data) || $data[$field] === null) {
            return null;
        }

        if (!is_bool($data[$field])) {
            throw new HttpException(422, 'validation_error', sprintf('Field "%s" must be a boolean.', $field));
        }

        return $data[$field];
    }

    /**
     * @return int[]
     */
    public static function requiredIntegerArray(array $data, string $field, bool $allowEmpty = true): array
    {
        if (!array_key_exists($field, $data) || !is_array($data[$field])) {
            throw new HttpException(422, 'validation_error', sprintf('Field "%s" must be an array.', $field));
        }

        $values = [];
        foreach ($data[$field] as $value) {
            if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
                throw new HttpException(422, 'validation_error', sprintf('Field "%s" must contain only integers.', $field));
            }
            $intValue = (int) $value;
            if ($intValue <= 0) {
                throw new HttpException(422, 'validation_error', sprintf('Field "%s" must contain positive integers.', $field));
            }
            $values[] = $intValue;
        }

        $values = array_values(array_unique($values));
        if (!$allowEmpty && empty($values)) {
            throw new HttpException(422, 'validation_error', sprintf('Field "%s" cannot be empty.', $field));
        }

        return $values;
    }

    public static function routeInt(?string $value, string $fieldName = 'id'): int
    {
        if ($value === null || !ctype_digit($value)) {
            throw new HttpException(422, 'validation_error', sprintf('Route parameter "%s" must be a positive integer.', $fieldName));
        }

        $resolved = (int) $value;
        if ($resolved <= 0) {
            throw new HttpException(422, 'validation_error', sprintf('Route parameter "%s" must be a positive integer.', $fieldName));
        }

        return $resolved;
    }

    /**
     * @return array{page: int, per_page: int, search: string}
     */
    public static function pagination(array $query): array
    {
        $page = 1;
        $perPage = 20;

        if (isset($query['page']) && is_scalar($query['page']) && ctype_digit((string) $query['page'])) {
            $page = max(1, (int) $query['page']);
        }

        if (isset($query['per_page']) && is_scalar($query['per_page']) && ctype_digit((string) $query['per_page'])) {
            $perPage = (int) $query['per_page'];
        }

        $perPage = max(1, min(100, $perPage));

        $search = '';
        if (isset($query['search']) && is_scalar($query['search'])) {
            $search = trim((string) $query['search']);
            if (strlen($search) > 100) {
                throw new HttpException(422, 'validation_error', 'Search query cannot exceed 100 characters.');
            }
        }

        return [
            'page' => $page,
            'per_page' => $perPage,
            'search' => $search,
        ];
    }
}
