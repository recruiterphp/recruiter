<?php

declare(strict_types=1);

namespace Recruiter;

interface Finalizable
{
    public function afterSuccess(): void;

    public function afterFailure(\Throwable $e): void;

    public function afterLastFailure(\Throwable $e): void;

    public function finalize(?\Throwable $e = null): void;
}
