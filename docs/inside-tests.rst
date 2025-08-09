Executing jobs within tests:
=======================================================

| During the execution of tests that exercise code using the `recruiter` library to queue `jobs`, we face the problem of having to ensure that those `jobs` are executed to avoid possible test failures.

| To make the queued `jobs` execute we could simply replicate what happens in the production environment and therefore activate the `recruiter`, `worker` and `cleaner` processes.

| Depending on the test environment we are in, this solution can present disadvantages, such as:

  * **Greater difficulty in test execution**: since our environment must provide for the execution of long running processes and ensure they are active during the entire test execution.
  * **Decreased test execution speed**: In case tests depend on the result of job execution we would have to wait for their execution by workers, which no matter how reactive they may be cannot be instantaneous; we must also consider that jobs could be scheduled in the future and therefore workers will not be able to execute them until the scheduling date has passed.
  * **Impossibility of depending on jobs scheduled far in the future**: If jobs scheduled a few seconds away from the current moment only lead to slower test execution, jobs scheduled far in the future (e.g. the next day or next month) make test execution impossible (we certainly can't wait so long to finish executing a test).

| All these points can be resolved using a method, provided by the |recruiter.recruiter.class|_ class, which allows the execution, in the current process, of all previously queued jobs.

| The method is ``flushJobsSynchronously()`` and can be called on any instance of the |recruiter.recruiter.class|_ class (so not necessarily the same instance used to queue the `jobs`).
| Thanks to this method we can ensure that all queued jobs are executed without having to have an environment with active `recruiter`, `worker` and `cleaner` processes and without having to wait for the scheduling date of each of them.


.. code-block:: php

   <?php

   namespace Tests;

   use Core\DomainService;

   $mongodbInstance = new MongoDB\Client(...);
   $recruiter = new Recruiter($mongodbInstance);

   $domainService = new DomainService($recruiter);
   $domainService->methodThatQueuesJobs();

   $recruiter->flushJobsSynchronously(); // Here all previously queued jobs are executed


.. |recruiter.recruiter.class| replace:: ``Recruiter\Recruiter``
.. _recruiter.recruiter.class: https://github.com/recruiterphp/recruiter/blob/master/src/Recruiter/Recruiter.php
