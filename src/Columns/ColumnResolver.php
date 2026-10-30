<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\Columns;

use Dashworthy\ApiFilters\Attributes\Filterable;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;

/**
 * Resolves a client's field to a column of a model's table. Only a plain
 * identifier naming a column the model exposes resolves, matched in any
 * letter case: a column its #[Filterable] attribute lists, or without one,
 * any column of its table. A column the model hides from serialization never
 * resolves. The listed or schema spelling, qualified with the table, is what
 * reaches SQL.
 */
final class ColumnResolver
{
    /**
     * Each model's exposed columns, keyed by model class, connection and
     * table, then by lower-cased name.
     *
     * @var array<string, array<string, string>>
     */
    private array $listings = [];

    /**
     * The qualified column the field names, or null when it names no exposed column.
     */
    public function resolve(Model $model, string $field): ?string
    {
        $column = $this->column($model, $field);

        return $column === null ? null : $model->qualifyColumn($column);
    }

    /**
     * The listed or schema spelling of the column the field names,
     * unqualified, or null when it names no exposed column. The applier
     * qualifies a related column inside the relation's subquery, against the
     * table name Laravel uses there, which is an alias for a relation back to
     * the same model.
     */
    public function column(Model $model, string $field): ?string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*\z/', $field) !== 1) {
            return null;
        }

        $column = $this->columns($model)[strtolower($field)] ?? null;

        return $column === null || $this->isHidden($model, $column) ? null : $column;
    }

    /**
     * Whether serialization drops the column, as Model::toArray() decides it.
     */
    private function isHidden(Model $model, string $column): bool
    {
        $name = strtolower($column);
        $visible = array_map(strtolower(...), $model->getVisible());

        return in_array($name, array_map(strtolower(...), $model->getHidden()), true)
            || ($visible !== [] && ! in_array($name, $visible, true));
    }

    /**
     * The columns the model exposes: the ones its #[Filterable] attribute
     * lists, or every column of its table when it has none.
     *
     * @return array<string, string>
     */
    private function columns(Model $model): array
    {
        $key = $model::class.'|'.$model->getConnectionName().'|'.$model->getTable();

        if (! isset($this->listings[$key])) {
            $this->listings[$key] = [];

            foreach ($this->listed($model) ?? $model->getConnection()->getSchemaBuilder()->getColumnListing($model->getTable()) as $column) {
                $this->listings[$key][strtolower($column)] = $column;
            }
        }

        return $this->listings[$key];
    }

    /**
     * The columns the model's #[Filterable] attribute lists, or null when it
     * has none. Like Laravel's lookup for #[Hidden], it checks the class,
     * then its traits, then each parent class and its traits.
     *
     * @return list<string>|null
     */
    private function listed(Model $model): ?array
    {
        for ($class = new ReflectionClass($model); $class !== false; $class = $class->getParentClass()) {
            foreach ([$class, ...$class->getTraits()] as $declaring) {
                $attributes = $declaring->getAttributes(Filterable::class);

                if ($attributes !== []) {
                    return $attributes[0]->newInstance()->columns;
                }
            }
        }

        return null;
    }
}
