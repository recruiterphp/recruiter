#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Recruiter\Factory;
use Recruiter\Infrastructure\Memory\MemoryLimit;
use Recruiter\Infrastructure\Persistence\Mongodb\URI as MongoURI;
use Recruiter\Recruiter;
use Recruiter\Workable\LazyBones;

$factory = new Factory();
$db = $factory->getMongoDb(
    MongoURI::fromEnvironment(),
    $options = [],
);
$db->drop();

$recruiter = new Recruiter($db);

LazyBones::waitForMs(200, 100)
    ->asJobOf($recruiter)
    ->inBackground()
    ->execute()
;

$memoryLimit = new MemoryLimit('64MB');
$worker = $recruiter->hire($memoryLimit);
$assignments = $recruiter->assignJobsToWorkers();
$worker->work();
