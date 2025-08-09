<?php

namespace Recruiter;

class RetryPolicyInJob
{
    /**
     * @param array{retry_policy?: array{class?: class-string, parameters: array<mixed>}} $document
     *
     * @throws \Exception
     */
    public static function import(array $document): RetryPolicy
    {
        if (!array_key_exists('retry_policy', $document)) {
            throw new \Exception('Unable to import Job without data about RetryPolicy object');
        }
        $dataAboutRetryPolicyObject = $document['retry_policy'];
        if (!array_key_exists('class', $dataAboutRetryPolicyObject)) {
            throw new \Exception('Unable to import Job without a class');
        }
        if (!class_exists($dataAboutRetryPolicyObject['class'])) {
            throw new \Exception('Unable to import Job with unknown RetryPolicy class');
        }
        if (!method_exists($dataAboutRetryPolicyObject['class'], 'import')) {
            throw new \Exception('Unable to import RetryPolicy without method import');
        }

        return $dataAboutRetryPolicyObject['class']::import($dataAboutRetryPolicyObject['parameters']);
    }

    /**
     * @return array{retry_policy: array{class: class-string<RetryPolicy>, parameters: array<mixed>}}
     */
    public static function export(RetryPolicy $retryPolicy): array
    {
        return [
            'retry_policy' => [
                'class' => $retryPolicy::class,
                'parameters' => $retryPolicy->export(),
            ],
        ];
    }

    /**
     * @return array{}
     */
    public static function initialize(): array
    {
        return [];
    }
}
