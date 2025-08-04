<?php

use Recruiter\Recruiter;

echo 'BOOTSTRAP!!!' . PHP_EOL;

assert(isset($recruiter));
assert($recruiter instanceof Recruiter);

$recruiter->getEventDispatcher()->addListener('job.failure.last', function ($event): void {
    error_log('Job definitively failed: ' . var_export($event->export(), true));
});
