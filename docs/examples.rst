Examples
============

| To execute background jobs you need to instantiate a class that implements the |recruiter.workable.class|_ interface.
| Later we will `learn how to create a Workable class`__.

Inside the ``Recruiter`` library `there are ready-to-use Workable classes`__, for these examples we will use the |recruiter.workable.shellCommand.class|_ class which allows executing shell commands in the background.

To queue jobs for execution we need to have an instance of the |recruiter.recruiter.class|_ class.

============
Hello World
============
Let's say we wanted to execute a shell command (e.g. ``echo "Hello World"``) in background, we could do it in the following way:

.. code-block:: php

   <?php

   use Recruiter\Recruiter;
   use Recruiter\Workable\ShellCommand;

   $mongodbInstance = new MongoDB\Client(...);
   $recruiter = new Recruiter($mongodbInstance);

   ShellCommand::fromCommandLine('echo "Hello World" > /tmp/job.output');
      ->asJobOf($recruiter)
      ->inBackground()
      ->execute()
   ;

this way a ``job`` will be scheduled that will execute the command ``bash ./my_custom_script.sh`` and this job will be executed as soon as a ``worker`` is available.

==============================
Schedule a Job in the future
==============================
In case we want the job execution not to be (almost) "instantaneous" but instead to be scheduled in the future, we can do it through the ``scheduleAt()`` method to which we need to pass an instance of |timeless.moment.class|_

.. code-block:: php

   <?php

   use Recruiter\Recruiter;
   use Recruiter\Workable\ShellCommand;
   use Timeless\Moment;

   $mongodbInstance = new MongoDB\Client(...);
   $recruiter = new Recruiter($mongodbInstance);

   ShellCommand::fromCommandLine("bash ./my_custom_script.sh");
      ->asJobOf($recruiter)
      ->scheduleAt(Moment::fromDateTime(new DateTime('2151-02-21T15:03:01.012345Z');))
      ->inBackground()
      ->execute()
   ;

This way the job will be queued and executed as soon as there is a free worker available after the date '2151-02-21T15:03:01.012345Z'

============
Retry
============

| In the previous examples, jobs will be executed only once, regardless of whether they succeed or not.

| In case of job failure, recruiter gives us the possibility to specify that its execution can be retried.
| To do this we need to assign a |retryPolicy.class|_ to the job through the ``retryWithPolicy(RetryPolicy $retryPolicy)`` method.

| We will see later `how to create your own RetryPolicy`__, in the meantime we can use the `retry policies already included in the recruiter library`__.

| If for example we wanted to retry the execution of a job 3 times, waiting 1 minute between attempts, we could do it this way:

.. code-block:: php

   <?php

   use Recruiter\Recruiter;
   use Recruiter\Workable\ShellCommand;
   use Recruiter\RetryPolicy\RetryManyTimes;

   $mongodbInstance = new MongoDB\Client(...);
   $recruiter = new Recruiter($mongodbInstance);

   $retryPolicy = new RetryManyTimes(3, 60);

   ShellCommand::fromCommandLine("false");
      ->asJobOf($recruiter)
      ->retryWithPolicy($retryPolicy)
      ->scheduleAt(Moment::fromDateTime(new DateTime('2027-02-21T15:00:00.0000Z');))
      ->inBackground()
      ->execute()
   ;

| Based on this example the job will be executed 4 times (since the ``false`` command will always fail),
| the first time it will be executed on: `2027-02-21T15:00:00.0000Z` as scheduled, then 3 new attempts will be made spaced 60 seconds apart, which will therefore take place on the dates:
| `2027-02-21T15:00:01.0000Z`
| `2027-02-21T15:00:02.0000Z`
| `2027-02-21T15:00:03.0000Z`

This is a simple example of how to repeat a job in case of failure, :ref:`Retry Policies` can also have much more complex logic, take a look at the dedicated page to understand their potential.


| It is also possible to specify in which cases to execute a new attempt and in which not.
| The ``retryWithPolicy`` method is composed like this: ``retryWithPolicy(RetryPolicy $retryPolicy, $retriableExceptionTypes = [])``
| In the default case where ``$retriableExceptionTypes`` is an empty array, the job will be attempted again whatever exception is thrown.
| If instead we specify one or more exceptions then the job will be attempted again only in case an exception is thrown that is an instance of at least one of the passed classes.
| E.g.:

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
| In case the endpoint designed to receive the merchant's payment notifications is not reachable, we would want the notification sending to be attempted again, maybe after a few minutes, hoping that in the meantime the endpoint has become reachable again, but we don't want our process to be blocked for a few minutes when it could go on doing other things in the meantime.
| Recruiter helps us in this case too, it is in fact possible to make a job executed `in process` at the moment it is scheduled, and, only in case of failure, queued for background execution so as to be able to execute subsequent retries.

| E.g.:

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

| As you can notice the only thing we did was remove the call to the ``inBackground()`` method, this way the command will be executed immediately, and, only in case of failure, will be inserted in the queue of jobs to be executed in background.
| In case a RetryPolicy is not set, the process will be executed immediately and, both in case of success and in case of failure, will be archived without any subsequent attempt.

.. warning::
   The `inBackground()` method is implicitly invoked when the job is scheduled for future execution via the `scheduleAt()` method
   Therefore these 2 calls are identical and in both cases the job execution will be exclusively in background.

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


===============
Grouping Jobs
===============
| As `we have seen previously <CHANGEME.html>`_ a worker can be assigned to only one specific group of jobs.
| To assign a job to a group you use the ``inGroup($group)`` method

.. code-block:: php

   <?php

   HttpCommand::fromRequest($request);
      ->asJobOf($recruiter)
      ->inGroup('http')
      ->inBackground()
      ->execute()
   ;

| This way only the workers related to the `http` group will be able to execute this job


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

__ how-to-create-a-workable.html

__ predefined-workables.html

__ how-to-create-a-retry-policy.html

__ predefined-retry-policies.html


.. TODO
.. find a better example command to execute than my_custom_script
