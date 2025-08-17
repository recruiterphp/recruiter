<?php

declare(strict_types=1);

namespace Recruiter;

/**
 * @template T
 *
 * @param array<T>                $array
 * @param ?callable(T): array-key $f
 *
 * @return array<T>
 */
function array_group_by(array $array, ?callable $f = null): array
{
    $f = $f ?: (fn ($value) => $value);

    return array_reduce(
        $array,
        function (array $buckets, mixed $x) use ($f) {
            /** @var array-key $key */
            $key = call_user_func($f, $x);
            if (!is_array($buckets[$key] ?? null)) {
                $buckets[$key] = [];
            }
            $buckets[$key][] = $x;

            return $buckets;
        },
        [],
    );
}
