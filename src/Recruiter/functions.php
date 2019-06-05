<?php
namespace Recruiter;

function array_some($array, callable $predicate)
{
    foreach ($array as $key => $value) {
        if (call_user_func($predicate, $value, $key, $array)) {
            return true;
        }
    }
    return false;
}

function array_group_by($array, callable $f = null)
{
    $f = $f ?: function ($value) {
        return $value;
    };
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
        []
    );
}
