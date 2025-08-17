<?php

declare(strict_types=1);

namespace Recruiter\Infrastructure\Memory;

use function ByteUnits\box;

use ByteUnits\ParseException;
use ByteUnits\System;

readonly class MemoryLimit
{
    private System $limit;

    public function __construct(int|string|System $limit)
    {
        try {
            $this->limit = box($limit);
        } catch (ParseException $e) {
            throw new \UnexpectedValueException(sprintf("Memory limit '%s' is an invalid value: %s", (string) $limit, $e->getMessage()));
        }
    }

    public function ensure(int|string|System $used): void
    {
        $used = box($used);
        if ($used->isGreaterThan($this->limit)) {
            throw new MemoryLimitExceededException(sprintf('Memory limit reached, %s is more than the force limit of %s', $used->format(), $this->limit->format()));
        }
    }
}
