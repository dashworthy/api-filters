<?php

declare(strict_types=1);

use Dashworthy\ApiFilters\Normalizers\BooleanNormalizer;

mutates(BooleanNormalizer::class);

$normalize = fn (mixed $value): mixed => (new BooleanNormalizer)->handle($value, fn (mixed $value): mixed => $value);

it('turns true and on into true', function (string $token) use ($normalize) {
    expect($normalize($token))->toBeTrue();
})->with(['true', 'on']);

it('turns false and off into false', function (string $token) use ($normalize) {
    expect($normalize($token))->toBeFalse();
})->with(['false', 'off']);

it('leaves other values alone', function (mixed $value) use ($normalize) {
    expect($normalize($value))->toBe($value);
})->with([
    'another case' => 'TRUE',
    'yes' => 'yes',
    'a numeric string' => '1',
    'an integer' => 1,
    'null' => [null],
]);

it('hands its result to the next normalizer', function () {
    expect((new BooleanNormalizer)->handle('on', fn (mixed $value): array => ['next' => $value]))->toBe(['next' => true]);
});
