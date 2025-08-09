.. _retry-policies:

Retry Policies
==============

===================================
Implements a custom RetryPolicy
===================================

| The library contains RetryPolicies that cover the most common cases.
| If needed, we can create a new policy to cover our specific needs.
| To create a new policy, we need to create a class that implements the |retryPolicy.class|_ interface

===================================
DoNotDoItAgain
===================================

This is the default (implicit) `RetryPolicy`, use it only if you want to make explicit the fact that the job should not be repeated.

.. code-block:: php

   <?php

   use Recruiter\Recruiter;
   use Recruiter\Workable\ShellCommand;
   use Recruiter\RetryPolicy\DoNotDoItAgain;

   $mongodbInstance = new MongoDB\Client(...);
   $recruiter = new Recruiter($mongodbInstance);

   ShellCommand::fromCommandLine("false");
      ->asJobOf($recruiter)
      ->retryWithPolicy(new DoNotDoItAgain())
      ->inBackground()
      ->execute()
   ;



===================================
ExponentialBackoff
===================================

This `RetryPolicy` allows you to retry job execution at exponential intervals.
For example, we can set a maximum of 10 retries with an initial interval of 30 seconds.
This means that after the first failure, a retry will be attempted after 30 seconds, if this also fails another retry will be attempted after 60 seconds, if this also fails a new retry will be attempted after 120 seconds and so on, up to a maximum of 10 new attempts.

The `ExponentialBackoff` policy accepts as parameters the maximum number of attempts to make and the initial seconds of interval before making the first attempt.

Examples:

.. code-block:: php

   <?php

   use Recruiter\Recruiter;
   use Recruiter\Workable\ShellCommand;
   use Recruiter\RetryPolicy\ExponentialBackoff;

   $mongodbInstance = new MongoDB\Client(...);
   $recruiter = new Recruiter($mongodbInstance);

   $retryPolicy = ExponentialBackoff::forTimes(10, 30);
   //This is the same to the following:
   // $retryPolicy = new ExponentialBackoff(10, 30);

   ShellCommand::fromCommandLine("false");
      ->asJobOf($recruiter)
      ->retryWithPolicy($retryPolicy)
      ->inBackground()
      ->execute()
   ;



..TODO: verify the following part before publishing it
.. This policy also includes a factory method that accepts the maximum number of seconds to retry and the initial number of seconds of interval before making the first attempt:
..
.. Examples:
..
.. .. code-block:: php
..
..    <?php
..
..    use Recruiter\Recruiter;
..    use Recruiter\Workable\ShellCommand;
..    use Recruiter\RetryPolicy\ExponentialBackoff;
..
..    $mongodbInstance = new MongoDB\Client(...);
..    $recruiter = new Recruiter($mongodbInstance);
..
..    // In this case there will be a maximum of 4 attempts: after the first failure a retry will be made after 30 seconds, another one after 60 seconds, and the last after 120 seconds
..    $retryPolicy = ExponentialBackoff::forAnInterval(120, 30);
..
..    ShellCommand::fromCommandLine("false");
..       ->asJobOf($recruiter)
..       ->retryWithPolicy($retryPolicy)
..       ->scheduleAt(Moment::fromDateTime(new DateTime('2027-02-21T15:00:00.0000Z');))
..       ->inBackground()
..       ->execute()
..    ;



==================
RetryForevers
==================

This `RetryPolicy` allows you to retry job execution infinitely by specifying the time interval between one attempt and another.
If for example we wanted to run a job infinitely waiting 30 seconds between one attempt and another, we can write:

.. code-block:: php

   <?php

   use Recruiter\Recruiter;
   use Recruiter\Workable\ShellCommand;
   use Recruiter\RetryPolicy\RetryForevers;

   $mongodbInstance = new MongoDB\Client(...);
   $recruiter = new Recruiter($mongodbInstance);

   $retryPolicy = RetryForevers::afterSeconds(30);
   //This is the same to the following:
   // $retryPolicy = new RetryForevers(30);

   ShellCommand::fromCommandLine("false");
      ->asJobOf($recruiter)
      ->retryWithPolicy($retryPolicy)
      ->inBackground()
      ->execute()
   ;



==================
RetryManyTimes
==================

This `RetryPolicy` allows you to retry job execution a finite number of times by specifying the time interval between one attempt and another.
If for example we want to retry a job 3 times, waiting 30 seconds between one attempt and another, we can write:

.. code-block:: php

   <?php

   use Recruiter\Recruiter;
   use Recruiter\Workable\ShellCommand;
   use Recruiter\RetryPolicy\RetryManyTimes;

   $mongodbInstance = new MongoDB\Client(...);
   $recruiter = new Recruiter($mongodbInstance);

   $retryPolicy = RetryManyTimes::forTimes(3, 30);
   //This is the same to the following:
   // $retryPolicy = new RetryManyTimes(3, 30);

   ShellCommand::fromCommandLine("false");
      ->asJobOf($recruiter)
      ->retryWithPolicy($retryPolicy)
      ->inBackground()
      ->execute()
   ;



==================
TimeTable
==================

This `RetryPolicy` allows you to retry job execution at regular intervals depending on how much time has passed since the job was created.

For example, if we wanted to retry the job
* every minute for the first 5 minutes of the job's life,
* every 5 minutes for the first hour (i.e., the next 55 minutes)
.. * every 5 minutes for the next 55 minutes
* and every hour for the first 24 hours (i.e., the next 23 hours)
.. * every hour for the next 23 hours

We can write the following code:

.. code-block:: php

   <?php

   use Recruiter\Recruiter;
   use Recruiter\Workable\ShellCommand;
   use Recruiter\RetryPolicy\TimeTable;

   $mongodbInstance = new MongoDB\Client(...);
   $recruiter = new Recruiter($mongodbInstance);

   $retryPolicy = new TimeTable([
      '5 minutes ago' => '1 minute',
      '1 hour ago' => '5 minutes',
      '24 hours ago' => '1 hour',
   ]);

   ShellCommand::fromCommandLine("false");
      ->asJobOf($recruiter)
      ->retryWithPolicy($retryPolicy)
      ->inBackground()
      ->execute()
   ;


.. warning::
   This policy accepts a key-value array where both keys and values must be strings parsable by the php `strtotime`_ function

   .. _strtotime: https://www.php.net/manual/en/function.strtotime.php


.. |retryPolicy.class| replace:: ``Recruiter\RetryPolicy``
.. _retryPolicy.class: https://github.com/recruiterphp/recruiter/blob/master/src/Recruiter/RetryPolicy.php
