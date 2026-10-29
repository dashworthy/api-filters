<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use LogicException;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * The relation paths a developer allows clients to filter through. A client's
 * path is only ever a key into this list: what reaches Eloquent is the
 * developer's spelling, and every listed segment was checked by reflection to
 * be a method that can only build a relation before it was ever called.
 */
final class RelationAllowList
{
    /**
     * Every allowed path and each of its prefixes, keyed by camel-cased segments.
     *
     * @var array<string, array{path: string, model: Model}>
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
     * The developer's spelling of a client's path and the model it reaches, or
     * null when it is not allowed.
     *
     * @return array{path: string, model: Model}|null
     */
    public function find(string $clientPath): ?array
    {
        return $this->paths[$this->key($clientPath)] ?? null;
    }

    private function allow(Model $model, string $path): void
    {
        $spelling = [];

        foreach (explode('.', $path) as $segment) {
            $model = $this->relation($model, $segment, $path)->getRelated();
            $spelling[] = $segment;

            $this->paths[$this->key(implode('.', $spelling))] = ['path' => implode('.', $spelling), 'model' => $model];
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

    private function key(string $path): string
    {
        return implode('.', array_map(Str::camel(...), explode('.', $path)));
    }
}
