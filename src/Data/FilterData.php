<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\Data;

use LogicException;

/**
 * One filter: the column it compares, the operator the client named, its
 * normalized value, and the allow-listed relation path it filters through, if
 * any. The operation that handles the operator decides, through its rules(),
 * which values reach it, and reads the value back through the getter for the
 * shape those rules allow.
 */
final readonly class FilterData
{
    public function __construct(
        public string $column,
        public string $operator,
        public mixed $value = null,
        public ?string $relation = null,
    ) {}

    /**
     * The same filter on another spelling of its column, such as the column
     * qualified inside a relation's subquery.
     */
    public function withColumn(string $column): self
    {
        return new self($column, $this->operator, $this->value, $this->relation);
    }

    /**
     * @throws LogicException when the value is a list or an object
     */
    public function scalarValue(): string|int|float|bool|null
    {
        return is_scalar($this->value) || $this->value === null ? $this->value : throw $this->notA('single value');
    }

    /**
     * @throws LogicException when the value is not a string
     */
    public function stringValue(): string
    {
        return is_string($this->value) ? $this->value : throw $this->notA('string');
    }

    /**
     * @return list<scalar|null>
     *
     * @throws LogicException when the value is not a list of single values and nulls
     */
    public function listValue(): array
    {
        if (! is_array($this->value) || ! array_is_list($this->value)) {
            throw $this->notA('list');
        }

        $values = [];

        foreach ($this->value as $value) {
            $values[] = is_scalar($value) || $value === null ? $value : throw $this->notA('list of single values');
        }

        return $values;
    }

    private function notA(string $shape): LogicException
    {
        return new LogicException("The [{$this->operator}] filter's value is not a {$shape}.");
    }
}
