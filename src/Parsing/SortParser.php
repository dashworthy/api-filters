<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\Parsing;

use Dashworthy\ApiFilters\Columns\ColumnResolver;
use Dashworthy\ApiFilters\Data\SortData;
use Dashworthy\ApiFilters\Enums\SortOperator;
use Dashworthy\ApiFilters\Relations\RelationAllowList;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validates a request's sorts and turns them into typed sorts. A field must be
 * a visible column of the query's model, or a visible column reached through
 * an allow-listed path whose every segment is a to-one relation. Every
 * rejected field gets the same message. It applies nothing, so a rejected
 * request leaves the query untouched.
 */
final class SortParser
{
    public function __construct(
        private readonly ColumnResolver $columns,
        private readonly RelationAllowList $relations,
    ) {}

    /**
     * @return list<SortData>
     *
     * @throws ValidationException
     */
    public function parse(mixed $sorts, Model $model): array
    {
        $rules = self::shapeRules();
        $rules['sorts'] = ['present', ...$rules['sorts']];

        /** @var list<array{field: string, sort_operator: string}> $sorts */
        $sorts = Validator::make(['sorts' => $sorts], $rules, self::shapeMessages())->validate()['sorts'];
        $errors = [];
        $parsed = [];

        foreach ($sorts as $index => $sort) {
            $target = $this->resolveField($model, $sort['field']);

            if ($target === null) {
                $errors["sorts.{$index}.field"] = [self::message('not_sortable')];

                continue;
            }

            $parsed[] = new SortData($target['column'], SortOperator::from($sort['sort_operator']), $target['relation']);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $parsed;
    }

    /**
     * The rules for the shape of sorts, shared with FilterableRequest. The
     * caller decides whether sorts must be present.
     *
     * @return array<string, list<mixed>>
     */
    public static function shapeRules(): array
    {
        return [
            'sorts' => ['list', 'max:'.self::limit()],
            'sorts.*.field' => ['required', 'string'],
            'sorts.*.sort_operator' => ['required', Rule::enum(SortOperator::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function shapeMessages(): array
    {
        return ['sorts.max' => self::message('too_many_sorts', self::limit())];
    }

    /**
     * The column and to-one relation path a field names, or null when it names
     * nothing sortable. A client's path is only looked up, never handed to the
     * model: what comes back is the developer's spelling from the allow-list.
     *
     * @return array{column: string, relation: string|null}|null
     */
    private function resolveField(Model $model, string $field): ?array
    {
        if (! str_contains($field, '.')) {
            $column = $this->columns->resolve($model, $field);

            return $column === null ? null : ['column' => $column, 'relation' => null];
        }

        $relation = $this->relations->find(Str::beforeLast($field, '.'));

        if ($relation === null || ! $relation['toOne']) {
            return null;
        }

        $column = $this->columns->column($relation['model'], Str::afterLast($field, '.'));

        return $column === null ? null : ['column' => $column, 'relation' => $relation['path']];
    }

    private static function limit(): int
    {
        return Config::integer('api-filters.limits.sorts');
    }

    private static function message(string $key, ?int $max = null): string
    {
        $message = Lang::get("api-filters::validation.{$key}", $max === null ? [] : ['max' => (string) $max]);

        return is_string($message) ? $message : $key;
    }
}
