Repeatable Jobs
========================
| There are cases where a procedure needs to be repeated over time, regardless of whether it is completed successfully or not.
| This can be accomplished through special jobs called `RepeatableJob`.
| Let's see how to use them through an example.
|
| Suppose we want to send a report to a specific email address every day at 06:00 UTC.
| We will need to :ref:`create our Workable<workable>` that contains the procedure to generate/send the report. We proceed, as seen before, to implement the |recruiter.workable.class|_ class using the |recruiter.workable-behaviour.class|_ trait to avoid writing redundant code.

.. code-block:: php

   <?php

   use Recruiter\Recruiter;
   use Recruiter\Workable;
   use Recruiter\WorkableBehaviour;

   class DailyReportCommand implements Workable
   {
      use WorkableBehaviour;

      public function execute()
      {
         // ...
         // here we generate the report and send it to the desired recipient
         // ...
      }
   }

| Now we need to make our class also implement the |recruiter.repeatableJob.class|_ interface so that it can be automatically scheduled according to a specific pattern.

.. code-block:: php

   <?php

   use Recruiter\Recruiter;
   use Recruiter\Repeatable;
   use Recruiter\RepeatableBehaviour;
   use Recruiter\Workable;
   use Recruiter\WorkableBehaviour;

   class DailyReportCommand implements Workable, Repeatable
   {
      use WorkableBehaviour, RepeatableBehaviour;

      public function execute()
      {
         // ...
         // here we generate the report and send it to the desired recipient
         // ...
      }

      public function urn(): string
      {
         return 'report:daily';
      }

      public function unique(): bool
      {
         return false;
      }
   }

| We have therefore assigned a unique name to our `Repeatable` (through the ``urn()`` method), and indicated to the `Recruiter` whether or not it is possible for 2 or more instances of this `job` to overlap (through the ``unique()`` method)

| Now that we have a `Repeatable`, let's see how to schedule it at regular intervals.
| To specify the job execution policy, we need to use a |recruiter.schedule-policy.class|_
| Within the recruiter library, we find an existing `SchedulePolicy` called |recruiter.cron.class|_ that allows you to specify execution intervals with the same syntax used by the unix `cron <https://en.wikipedia.org/wiki/Cron>`_ daemon.
| Therefore, to send our report every day at `06:00 UTC`, we need to do this:

.. code-block:: php

   <?php

   use Recruiter\Recruiter;
   use Recruiter\SchedulePolicy\Cron;

   $mongodbInstance = new MongoDB\Client(...);
   $recruiter = new Recruiter($mongodbInstance);

   $schedulePolicy = new Cron('0 6 * * *');

   $scheduler = (new DailyReportCommand())
      ->asRepeatableJobOf($this->recruiter)
      ->repeatWithPolicy($schedulePolicy)
      ->retryWithPolicy(new DoNotDoItAgain()) // this is the default behaviour
      ->create()
   ;


| To remove an active scheduler, you can use the console command `scheduler:remove` and follow its instructions.

.. code-block:: bash

   $ php vendor/bin/recruiter scheduler:remove --target 127.0.0.1:27017


.. |recruiter.workable.class| replace:: ``Recruiter\Workable``
.. _recruiter.workable.class: https://github.com/recruiterphp/recruiter/blob/master/src/Recruiter/Workable.php

.. |recruiter.repeatableJob.class| replace:: ``Recruiter\Repeatable``
.. _recruiter.repeatableJob.class: https://github.com/recruiterphp/recruiter/blob/master/src/Recruiter/Repeatable.php

.. |recruiter.workable-behaviour.class| replace:: ``Recruiter\WorkableBehaviour``
.. _recruiter.workable-behaviour.class: https://github.com/recruiterphp/recruiter/blob/master/src/Recruiter/WorkableBehaviour.php

.. |recruiter.schedule-policy.class| replace:: ``Recruiter\SchedulePolicy``
.. _recruiter.schedule-policy.class: https://github.com/recruiterphp/recruiter/blob/master/src/Recruiter/SchedulePolicy.php

.. |recruiter.cron.class| replace:: ``Recruiter\SchedulePolicy\Cron``
.. _recruiter.cron.class: https://github.com/recruiterphp/recruiter/blob/master/src/Recruiter/SchedulePolicy/Cron.php
