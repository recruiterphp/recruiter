Jobs
================

================
What is a Job
================

| We can define ``Jobs`` as the work units executed by recruiter.
| They encapsulate the ``Workable`` object that we have seen previously (and therefore the procedure to execute) in addition to all other information necessary for the correct execution of this procedure, such as:

* the scheduling date,
* the retry policy in case of failure,
* the current state (to be executed, executing, executed),
* the group it belongs to
* others (i.e. creation date, number of attempts made, tags, etc.)

| A `job` can:

* be executed in process instantaneously,
* be scheduled for background execution as soon as possible,
* be scheduled for background execution at a specific date/time
* be executed in process instantaneously, and in case of failure be scheduled for background execution according to its retry policies.
* be retried in case of failure one or more times, according to specific retry policies.


To queue jobs for execution we need to have an instance of the |recruiter.recruiter.class|_ class.

.. warning::
   | Recruiter is designed to guarantee the execution of each job **at least** once.
   | This means that, regardless of retry policies, in some (abnormal) cases a job could be executed more than once.
   | This could happen if, for example, a `worker` should die fatally after executing the job but before being able to change the job state to ``executed``; in that case the job will, after a certain period of time, be assigned to another worker and therefore executed a second time.

============
Hello World
============
| We have already seen this example in the :ref:`chapter on creating a Workable object<workable>`.
| Assuming we have developed an **HttpRequestCommand** class, the simplest code we could write to queue an Http request is the following:

.. code-block:: php

   <?php

   use MyDomain\HttpRequestCommand;
   use MyDomain\Request;
   use Recruiter\Recruiter;

   $mongodbInstance = new MongoDB\Client(...);
   $recruiter = new Recruiter($mongodbInstance);

   $request = Request::post($url, $body);
   HttpRequestCommand::fromRequest($request)
      ->asJobOf($recruiter)
      ->inBackground()
      ->execute() // this is the method defined in the Workable class
   ;

| this way a ``job`` will be scheduled that will call the ``execute()`` method of the ``HttpRequestCommand`` class and will be executed as soon as a ``worker`` is available.

==============================
Schedule a Job in the future
==============================
| In case we want the job execution to be scheduled for the future (instead of almost "instantaneous"), we can do it through the ``scheduleAt()`` method to which we need to pass an instance of |timeless.moment.class|_
| If for example we wanted to schedule the execution of an http call for ``January 19, 2038`` we could do it this way:

.. code-block:: php

   <?php

   use MyDomain\HttpRequestCommand;
   use MyDomain\Request;
   use Recruiter\Recruiter;
   use Timeless\Moment;

   $mongodbInstance = new MongoDB\Client(...);
   $recruiter = new Recruiter($mongodbInstance);

   $request = Request::post($url, $body);
   HttpRequestCommand::fromRequest($request)
      ->asJobOf($recruiter)
      ->scheduleAt(Moment::fromDateTime(new DateTime('2038-01-19T00:00:00.000000Z');))
      ->inBackground()
      ->execute()
   ;

This way the job will be queued and executed as soon as there is a free worker available after the date '2038-01-19T00:00:00.000000Z'



.. _job-retry:

============
Retry
============

| In the examples seen previously, jobs will be executed only once, regardless of whether they succeed or fail.

| In case of job failure, recruiter gives us the possibility to specify that its execution can be retried.
| To do this we need to assign a |retryPolicy.class|_ to the job through the ``retryWithPolicy(RetryPolicy $retryPolicy)`` method.

| We'll see later :ref:`how to create your own RetryPolicy<retry-policies>`, in the meantime we can use the retry policies already included in the recruiter library.

| Let's assume for example that we want to retry our http call in case it fails, we want to execute up to a maximum of three retries and we want to wait 60 seconds between each attempt:

.. code-block:: php

   <?php

   use MyDomain\HttpRequestCommand;
   use MyDomain\Request;
   use Recruiter\Recruiter;
   use Recruiter\RetryPolicy\RetryManyTimes;
   use Timeless\Moment;


   $mongodbInstance = new MongoDB\Client(...);
   $recruiter = new Recruiter($mongodbInstance);

   $retryPolicy = new RetryManyTimes(3, 60);

   $request = Request::post($url, $body);
   HttpRequestCommand::fromRequest($request)
      ->asJobOf($recruiter)
      ->scheduleAt(Moment::fromDateTime(new DateTime('2038-01-19T00:00:00.000Z')))
      ->retryWithPolicy($retryPolicy)
      ->inBackground()
      ->execute()
   ;

| Based on this example our job will be executed up to a maximum of 4 times,
| the first time it will be executed on date: `2038-01-19T00:00:00.000Z` as scheduled, then, in case of failure, 3 new attempts will be made spaced 60 seconds apart from each other, which will therefore take place on the dates:
| `2038-01-19T00:01:00.000Z`
| `2038-01-19T00:02:00.000Z`
| `2038-01-19T00:03:00.000Z`

| This is a simple example of how to repeat a job in case of failure, the `Retry Policies` can have much more complex logic too, take a look at the :ref:`dedicated page<retry-policies>` to understand their potential.

=============================
Retriable Exceptions
=============================

| Regardless of the `RetryPolicy` used, we can always specify in which cases to execute a new attempt and in which not.
| The ``retryWithPolicy`` method in fact allows you to specify, as a second argument, an array of exceptions for which it is allowed to execute a new attempt.
| In case this array is empty (as in the default case), the job will be attempted again whatever exception is raised.
| In case this array contains one or more exceptions, then a new attempt will be made only if an exception is intercepted that is an instance of one of the classes contained in this array.
| For example:

.. code-block:: php

   <?php

   $retryPolicy = new RetryManyTimes(3, 60);
   $retriableExceptionTypes = [
      \Psr\Http\Client\NetworkExceptionInterface::class
   ];

   HttpCommand::fromRequest($request);
      ->asJobOf($recruiter)
      ->retryWithPolicy($retryPolicy, $retriableExceptionTypes)
      ->inBackground()
      ->execute()
   ;

In this case the job will be repeated only if an exception of type ``Psr\Http\Client\NetworkExceptionInterface`` occurs, in all other cases the job will be archived.

===============
Optimistic Jobs
===============

| There could be cases where we need a procedure to be executed in the most reactive way possible
| Let's pretend to be a payment system, and we want to notify a hypothetical merchant of a hypothetical purchase that went well by a hypothetical customer.
| To ensure the best possible user experience we obviously want to notify the merchant of the successful payment as soon as possible, so that the customer receives their product immediately.
| In case the endpoint designed to receive the merchant's payment notifications is not reachable we would like the notification sending to be attempted again, maybe after a few minutes, hoping that in the meantime the endpoint has become reachable again, but we don't want our process to be blocked for a few minutes when it could continue doing other things in the meantime.
| Recruiter helps us in this case too, it is in fact possible to make a job be executed `in process` at the moment it is scheduled, and, only in case of failure, be queued for background execution so as to be able to execute subsequent retries.

| For example:

.. code-block:: php

   <?php

   $retryPolicy = new RetryManyTimes(3, 60);
   $retriableExceptionTypes = [
      \Psr\Http\Client\NetworkExceptionInterface::class
   ];

   HttpCommand::fromRequest($request);
      ->asJobOf($recruiter)
      ->retryWithPolicy($retryPolicy, $retriableExceptionTypes)
      ->execute()
   ;

| As you can notice the only thing we did was remove the call to the ``inBackground()`` method, this way the command will be executed immediately, and, only in case of failure, will be inserted into the queue of jobs to be executed in background.
| In case a RetryPolicy is not set, the process will be executed immediately and, both in case of success and in case of failure, will be archived without any subsequent attempt.

.. note::
   | The `inBackground()` method is implicitly invoked in case the job is scheduled for future execution through the `scheduleAt()` method
   | Therefore these 2 calls are identical and in both cases the job execution will be exclusively in background.

   .. code-block:: php

      <?php

      HttpCommand::fromRequest($request);
         ->asJobOf($recruiter)
         ->retryWithPolicy($retryPolicy, $retriableExceptionTypes)
         ->inBackground()
         ->execute()
      ;

      HttpCommand::fromRequest($request);
         ->asJobOf($recruiter)
         ->retryWithPolicy($retryPolicy, $retriableExceptionTypes)
         ->scheduleAt(Moment::fromDateTime(new DateTime('2151-02-21T15:03:01.012345Z');))
         ->execute()
      ;




.. _jobs-grouping:

==================
Grouping Jobs
==================
| The `workers` (the processes that execute the work described by the jobs) can be launched with the intent of executing any available job or can be limited to executing only one group of jobs.
| This mode can be useful to us, for example, to :ref:`manage different execution priorities depending on the jobs<priority>`.

| Each `job` can be assigned, at most, to a single group and to do this we use the ``inGroup($group)`` method

.. code-block:: php

   <?php

   HttpCommand::fromRequest($request);
      ->asJobOf($recruiter)
      ->inGroup('http')
      ->inBackground()
      ->execute()
   ;

==================
Tags
==================
| It is also possible to tag jobs in such a way as to facilitate searching for jobs or other query activities (e.g. statistics).

.. code-block:: php

   <?php

   HttpCommand::fromRequest($request);
      ->asJobOf($recruiter)
      ->taggedAs(['userId:42', 'color:red'])
      ->inBackground()
      ->execute()
   ;



.. |recruiter.workable.class| replace:: ``Recruiter\Workable``
.. _recruiter.workable.class: https://github.com/recruiterphp/recruiter/blob/master/src/Recruiter/Workable.php

.. |recruiter.workable.shellCommand.class| replace:: ``Recruiter\Workable\ShellCommand``
.. _recruiter.workable.shellCommand.class: https://github.com/recruiterphp/recruiter/blob/master/src/Recruiter/Workable/ShellCommand.php

.. |recruiter.recruiter.class| replace:: ``Recruiter\Recruiter``
.. _recruiter.recruiter.class: https://github.com/recruiterphp/recruiter/blob/master/src/Recruiter/Recruiter.php

.. |timeless.moment.class| replace:: ``Timeless\Moment``
.. _timeless.moment.class: https://github.com/recruiterphp/recruiter/blob/master/src/Timeless/Moment.php

.. |retryPolicy.class| replace:: ``Recruiter\RetryPolicy``
.. _retryPolicy.class: https://github.com/recruiterphp/recruiter/blob/master/src/Recruiter/RetryPolicy.php
