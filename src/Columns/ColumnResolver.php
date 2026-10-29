<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\Columns;

use Illuminate\Database\Eloquent\Model;

/**
 * Resolves a client's field to a column of a model's table. Only a plain
 * identifier naming an existing column the model does not hide from
 * serialization resolves, matched in any letter case, and the schema's own
 * spelling, qualified with the table, is what reaches SQL.
 */
final class ColumnResolver
{
    /**
     * Each table's columns, keyed by connection and table, then by lower-cased name.
     *
     * @var array<string, array<string, string>>
     */
    private array $listings = [];

    /**
     * The qualified column the field names, or null when it names no visible column.
     */
    public function resolve(Model $model, string $field): ?string
    {
        $column = $this->column($model, $field);

        return $column === null ? null : $model->qualifyColumn($column);
    }

    /**
     * The schema's spelling of the column the field names, unqualified, or
     * null when it names no visible column. The applier qualifies a related
     * column inside the relation's subquery, against the table name Laravel
     * uses there, which is an alias for a relation back to the same model.
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
     * @return array<string, string>
     */
    private function columns(Model $model): array
    {
        $key = $model->getConnectionName().'|'.$model->getTable();

        if (! isset($this->listings[$key])) {
            $this->listings[$key] = [];

            foreach ($model->getConnection()->getSchemaBuilder()->getColumnListing($model->getTable()) as $column) {
                $this->listings[$key][strtolower($column)] = $column;
            }
        }

        return $this->listings[$key];
    }
}
