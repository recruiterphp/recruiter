<?php

namespace Recruiter;

use MongoDB\Client;
use MongoDB\Database;
use MongoDB\Driver\Exception\RuntimeException as DriverRuntimeException;
use Recruiter\Infrastructure\Persistence\Mongodb\URI;

class Factory
{
    public function getMongoDb(URI $uri, array $options = []): Database
    {
        try {
            $optionsWithMajorityConcern = ['w' => 'majority'];
            $client = new Client(
                $uri->__toString(),
                $optionsWithMajorityConcern,
                array_merge([
                    'typeMap' => [
                        'array' => 'array',
                        'document' => 'array',
                        'root' => 'array',
                    ],
                ], $options),
            );
            $client->listDatabases(); // in order to avoid lazy connections and catch eventually connection exceptions here

            return $client->selectDatabase($uri->database());
        } catch (DriverRuntimeException $e) {
            throw new \UnexpectedValueException(sprintf("'No MongoDB running at '%s'", $uri->__toString()), $e->getCode(), $e);
        }
    }
}
