#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Timeless as T;

use Recruiter\Recruiter;
use Recruiter\Factory;
use Recruiter\Workable\AlwaysFail;
use Recruiter\RetryPolicy;
use Recruiter\Worker;
use Recruiter\Option\MemoryLimit;

$factory = new Factory();
$db = $factory->getMongoDb(
    $hosts = 'localhost:27017',
    $options = [],
    $dbName = 'recruiter'
);
$db->drop();

$recruiter = new Recruiter($db);

(new AlwaysFail())
    ->asJobOf($recruiter)
    ->retryManyTimes(5, T\second(1))
    ->inBackground()
    ->execute();

$memoryLimit = new MemoryLimit('memory-limit', '64MB');
$worker = $recruiter->hire($memoryLimit);
while (true) {
    printf("Try to do my work\n");
    $assignments = $recruiter->assignJobsToWorkers();
    if ($assignments === 0) break;
    $worker->work();
    usleep(1200 * 1000);
}
