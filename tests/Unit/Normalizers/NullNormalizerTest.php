<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\Normalizers\NullNormalizer;

mutates(NullNormalizer::class);

$normalize = fn (mixed $value): mixed => (new NullNormalizer)->handle($value, fn (mixed $value): mixed => $value);

it('turns the string null into null', function () use ($normalize) {
    expect($normalize('null'))->toBeNull();
});

it('leaves other values alone', function (mixed $value) use ($normalize) {
    expect($normalize($value))->toBe($value);
})->with([
    'another case' => 'NULL',
    'an empty string' => '',
    'a zero' => '0',
    'false' => false,
]);

it('hands its result to the next normalizer', function () {
    expect((new NullNormalizer)->handle('null', fn (mixed $value): array => ['next' => $value]))->toBe(['next' => null]);
});
