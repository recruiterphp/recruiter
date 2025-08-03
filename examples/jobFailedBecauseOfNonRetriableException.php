#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Recruiter\Factory;
use Recruiter\Infrastructure\Memory\MemoryLimit;
use Recruiter\Infrastructure\Persistence\Mongodb\URI as MongoURI;
use Recruiter\Recruiter;
use Recruiter\Workable\AlwaysFail;
use Timeless as T;

$factory = new Factory();
$db = $factory->getMongoDb(
    MongoURI::fromEnvironment(),
    $options = [],
);
$db->drop();

$recruiter = new Recruiter($db);

new AlwaysFail()
    ->asJobOf($recruiter)
    ->retryManyTimes(5, T\seconds(1), DomainException::class)
    ->inBackground()
    ->execute()
;

$memoryLimit = new MemoryLimit('64MB');
$worker = $recruiter->hire($memoryLimit);
while (true) {
    printf("Try to do my work\n");
    $assignments = $recruiter->assignJobsToWorkers();
    if (0 === $assignments) {
        break;
    }
    $worker->work();
    usleep(1200 * 1000);
}
