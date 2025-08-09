<?php

namespace Recruiter;

/**
 * @param array<mixed> $array
 *
 * @return array<mixed>
 */
function array_group_by(array $array, ?callable $f = null): array
{
    $f = $f ?: (fn ($value) => $value);

    return array_reduce(
        $array,
        function ($buckets, $x) use ($f) {
            $key = call_user_func($f, $x);
            if (!array_key_exists($key, $buckets)) {
                $buckets[$key] = [];
            }
            $buckets[$key][] = $x;

            return $buckets;
        },
        [],
    );
}
