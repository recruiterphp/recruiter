Processes
=================

.. _recruiter:

=================================
Recruiter process
=================================

| The `recruiter` process is responsible for assigning, at the right time, the jobs in queue to the various `workers` that are running.

| It is important that only one instance of the `recruiter` process is live at any given time, but the developer should not worry about this since the `recruiter` includes an internal mutual exclusion mechanism, so it is possible to run multiple processes simultaneously (for example if you have multiple identical servers, each of which launches its own instance of `recruiter`) without concurrency problems.
| For more information about this functionality see the chapter on :ref:`Geezer<geezer>`

| The `recruiter` process needs to connect to a mongodb instance to work. You can specify the URI via the **--target** option, or by setting the MONGODB_URI environment variable (``--target`` takes precedence if both are provided, otherwise defaults to `localhost:27017`)

| If you want to take advantage of the :ref:`hooks<recruiter-hooks>` provided by the `recruiter` process, it is essential to pass a php script to include to the command, so that user-defined functions are visible. This can be done via the **--bootstrap** option

| To launch the `recruiter` process use the following command:

.. code-block:: bash

   $ php vendor/bin/recruiter start:recruiter --target 127.0.0.1:27017

For a complete list of options run the command:

.. code-block:: bash

   $ php vendor/bin/recruiter help start:recruiter


.. |recruiter.binary| replace:: $ php vendor/bin/recruiter


.. _worker:

=================================
Worker process
=================================

| The `worker` process is responsible for actually executing a specific job.
| When starting, the `worker` process communicates to the `recruiter` process that it is available to accept work.
| It is possible to run multiple `worker` processes simultaneously, each of these will execute a single job at a time.
| It is possible to limit a `worker` to executing only a specific group of jobs, this is a method to manage :ref:`priorities<priority>` in a soft way.

| The `worker` process needs to connect to a mongodb instance to work. You can specify the URI via the **--target** option, or by setting the MONGODB_URI environment variable (``--target`` takes precedence if both are provided, otherwise defaults to `localhost:27017`)

| Except for some rare cases, the `worker` process will have to execute code that is part of the project in which it is included, so it is essential to pass a php script to include to the command, so that user-defined classes are visible. This can be done via the **--bootstrap** option

| To launch the `worker` process use the following command:

.. code-block:: bash

   $ php vendor/bin/recruiter start:worker --target 127.0.0.1:27017 --bootstrap $APP_BASE_PATH/worker-boostrap.php

For a complete list of options run the command:

.. code-block:: bash

   $ php vendor/bin/recruiter help start:worker


.. _cleaner:

=================================
Cleaner process
=================================

| The `cleaner` process is responsible for maintaining the consistent state of the library.
| For example, a specific `worker` could die fatally during the execution of a job, leaving the job locked and therefore no longer executable by others.
| Thanks to the `cleaner` process, jobs can be put back into the execution queue after a certain period of stall.

| The `cleaner` process needs to connect to a mongodb instance to work. You can specify the URI via the **--target** option, or by setting the MONGODB_URI environment variable (``--target`` takes precedence if both are provided, otherwise defaults to `localhost:27017`)

| To launch the `cleaner` process use the following command:

.. code-block:: bash

   $ php vendor/bin/recruiter start:cleaner --target 127.0.0.1:27017

For a complete list of options run the command:

.. code-block:: bash

   $ php vendor/bin/recruiter help start:cleaner


=================================
Logging
=================================
| As we have seen in the previous paragraphs, it is possible to launch the various processes (`recruiter`, `worker` and `cleaner`) thanks to the php script ``vendor/bin/recruiter``.
| The php script ``vendor/bin/recruiter`` does nothing more than create an instance of |symfony.console.application.doc|_, register the various |symfony.console.command.doc|_ (Recruiter, Worker and Cleaner Commands) and execute the symfony application.
| The script creates the Recruiter, Worker and Cleaner commands by injecting them with an instance of |psr.loginterface.doc|_ that logs to standard output. In case you wanted a different type of |psr.loginterface.doc|_ you need to include these commands in your own ``Symfony\Component\Console\Application`` so that you can initialize them by injecting the logger you want.


.. code-block:: php

   <?php
   // bin/my-command

   use Recruiter\Geezer\Command\RobustCommandRunner;
   use Recruiter\Factory;
   use Recruiter\Infrastructure\Command\CleanerCommand;
   use Recruiter\Infrastructure\Command\RecruiterCommand;
   use Recruiter\Infrastructure\Command\WorkerCommand;
   use Symfony\Component\Console\Application;
   use Domain\MyLogger;

   $logger = new MyLogger();

   $application = new Application();

   $application->add(RecruiterCommand::toRobustCommand(new Factory(), $logger));
   $application->add(WorkerCommand::toRobustCommand(new Factory(), $logger));
   $application->add(CleanerCommand::toRobustCommand(new Factory(), $logger));

   $application->run();



.. |symfony.console.command.doc| replace:: ``Symfony\Component\Console\Command\Command``
.. _symfony.console.command.doc: https://symfony.com/doc/current/console.html#creating-a-command

.. |psr.loginterface.doc| replace:: ``Psr\Log\LoggerInterface``
.. _psr.loginterface.doc: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md

.. |symfony.console.application.doc| replace:: ``Symfony\Component\Console\Application``
.. _symfony.console.application.doc: https://symfony.com/doc/current/components/console.html#creating-a-console-application
