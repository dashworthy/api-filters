<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use LogicException;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * The relation paths a developer allows clients to filter and sort through. A
 * client's path is only ever a key into this list: what reaches Eloquent is
 * the developer's spelling, and every listed segment was checked by reflection
 * to be a method that can only build a relation before it was ever called.
 */
final class RelationAllowList
{
    /**
     * Every allowed path and each of its prefixes, keyed by camel-cased segments.
     *
     * @var array<string, array{path: string, model: Model|null, toOne: bool}>
     */
    private array $paths = [];

    /**
     * @param  list<string>  $paths  relation method names joined by dots, such as lineItems.product
     *
     * @throws LogicException when a segment is not a relation method
     */
    public function __construct(Model $model, array $paths)
    {
        foreach ($paths as $path) {
            $this->allow($model, $path);
        }
    }

    /**
     * The developer's spelling of a client's path, the model it reaches, and
     * whether every segment reaches at most one row, or null when the path is
     * not allowed. The model is null once a morph-to segment makes it differ
     * from row to row, so no column can be checked against its list.
     *
     * @return array{path: string, model: Model|null, toOne: bool}|null
     */
    public function find(string $clientPath): ?array
    {
        return $this->paths[$this->key($clientPath)] ?? null;
    }

    private function allow(Model $model, string $path): void
    {
        $spelling = [];
        $toOne = true;
        $isMorphed = false;

        foreach (explode('.', $path) as $segment) {
            $relation = $this->relation($model, $segment, $path);
            $model = $relation->getRelated();
            $spelling[] = $segment;
            $toOne = $toOne && $this->isToOne($relation);
            $isMorphed = $isMorphed || $relation instanceof MorphTo;

            $this->paths[$this->key(implode('.', $spelling))] = ['path' => implode('.', $spelling), 'model' => $isMorphed ? null : $model, 'toOne' => $toOne];
        }
    }

    /**
     * Build the relation a segment names, once reflection shows the method
     * can only return one: it is public, takes no arguments, is not one of
     * Eloquent's own model methods, and declares a Relation return type.
     * Names that only work through __call, such as truncate, have no method
     * to reflect, so they are refused without being called.
     *
     * @return Relation<Model, Model, mixed>
     */
    private function relation(Model $model, string $segment, string $path): Relation
    {
        $method = method_exists($model, $segment) ? new ReflectionMethod($model, $segment) : null;
        $type = $method?->getReturnType();

        $isRelationMethod = $method !== null
            && $method->isPublic()
            && ! $method->isStatic()
            && $method->getNumberOfRequiredParameters() === 0
            && $method->getDeclaringClass()->getName() !== Model::class
            && $type instanceof ReflectionNamedType
            && is_a($type->getName(), Relation::class, true);

        $relation = $isRelationMethod ? $method->invoke($model) : null;

        if (! $relation instanceof Relation) {
            throw new LogicException("api-filters cannot allow the relation path [{$path}]: [{$segment}] is not a public method of ".$model::class.' that declares an Eloquent relation as its return type.');
        }

        return $relation;
    }

    /**
     * Whether the relation reaches at most one row with a related model known
     * up front. A morph-to's related model differs from row to row.
     *
     * @param  Relation<Model, Model, mixed>  $relation
     */
    private function isToOne(Relation $relation): bool
    {
        return ! $relation instanceof MorphTo
            && ($relation instanceof BelongsTo
                || $relation instanceof HasOne
                || $relation instanceof MorphOne
                || $relation instanceof HasOneThrough);
    }

    private function key(string $path): string
    {
        return implode('.', array_map(Str::camel(...), explode('.', $path)));
    }
}
