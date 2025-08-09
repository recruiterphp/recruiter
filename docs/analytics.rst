.. _analytics-page:

Analytics
===========================

| Recruiter provides statistics on the current state of queues.
| This can be used, for example, to monitor that all queues are processed correctly.

| To obtain statistical data on the state of queues you need to call the ``analytics`` method of the ``Recruiter\Recruiter`` object.
| The returned value will be an array containing:

* **jobs**:
   - **queued**: the number of jobs in queue with a past scheduling date (and therefore to be executed), this number should remain stable.
   - **postponed**: the number of jobs in queue with a future scheduling date (to be executed only when the scheduling date has passed).

* **throughput**:
   - **value**: number of jobs executed per minute
   - **value_per_second**: number of jobs executed per second

* **latency**:
   - **average**: The average number of seconds that passes from the scheduling date to the job execution date. A high value means there are too few workers running for that specific queue.

* **execution_time**:
   - **average**: the average execution time of a job.

.. code-block:: php

   <?php

   use Recruiter\Recruiter;

   $mongodbInstance = new MongoDB\Client(...);
   $recruiter = new Recruiter($mongodbInstance);
   $analytics = $recruiter->analytics();

   var_export($analytics);
   // array (
   //    'jobs' => array (
   //       'queued' => 10,
   //       'postponed' => 30,
   //       'zombies' => 0,
   //    ),
   //    'throughput' => array (
   //       'value' => 3.0,
   //       'value_per_second' => 0.05,
   //    ),
   //    'latency' => array (
   //       'average' => 0.0,
   //    ),
   //    'execution_time' => array (
   //       'average' => 0,
   //    ),
   // )

| To view statistics related to a specific group of jobs it is possible to pass the group as the first argument to the analytics function.
| For additional usage modes refer directly to `the source code of the "analytics" method <https://github.com/recruiterphp/recruiter/blob/master/src/Recruiter/Recruiter.php>`_.

.. code-block:: php

   <?php

   $analytics = $recruiter->analytics('custom-group');
