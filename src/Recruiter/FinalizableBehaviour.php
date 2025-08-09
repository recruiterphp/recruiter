<?php

declare(strict_types=1);

namespace Recruiter;

trait FinalizableBehaviour
{
    public function afterSuccess(): void
    {
    }

    public function afterFailure(\Exception $e): void
    {
    }

    public function afterLastFailure(\Exception $e): void
    {
    }

    public function finalize(?\Throwable $e = null): void
    {
    }
}
