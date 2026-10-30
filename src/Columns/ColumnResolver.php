<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\Columns;

use Dashworthy\ApiFilters\Attributes\Filterable;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;

/**
 * Resolves a client's field to a column of a model's table, without reading
 * the table. A model exposes only the columns its #[Filterable] attribute
 * lists, matched in any letter case and spelled as listed, and a model
 * without one exposes none. A listed column the model hides from
 * serialization stays unexposed.
 */
final class ColumnResolver
{
    /**
     * The qualified column the field names, or null when it names no exposed column.
     */
    public function resolve(Model $model, string $field): ?string
    {
        $column = $this->column($model, $field);

        return $column === null ? null : $model->qualifyColumn($column);
    }

    /**
     * The column the field names, unqualified, or null when it names no
     * exposed column. The applier qualifies a related column inside the
     * relation's subquery, against the table name Laravel uses there, which
     * is an alias for a relation back to the same model.
     */
    public function column(Model $model, string $field): ?string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*\z/', $field) !== 1) {
            return null;
        }

        $column = $this->listed($model)[strtolower($field)] ?? null;

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
     * The columns the model's #[Filterable] attribute lists, keyed by
     * lower-cased name, or none when it has no such attribute. Like Laravel's
     * lookup for #[Hidden], it checks the class, then its traits, then each
     * parent class and its traits.
     *
     * @return array<string, string>
     */
    private function listed(Model $model): array
    {
        for ($class = new ReflectionClass($model); $class !== false; $class = $class->getParentClass()) {
            foreach ([$class, ...$class->getTraits()] as $declaring) {
                $attributes = $declaring->getAttributes(Filterable::class);

                if ($attributes !== []) {
                    $columns = $attributes[0]->newInstance()->columns;

                    return array_combine(array_map(strtolower(...), $columns), $columns);
                }
            }
        }

        return [];
    }
}
