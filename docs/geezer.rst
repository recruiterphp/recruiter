Geezer
==========================

What is Geezer
===============

| *Geezer* is a PHP library that provides tools for building robust long-running processes.
| It is designed to handle the common challenges that arise when building services that need to run continuously, such as:

* Graceful shutdown handling with signal management
* Leadership election for distributed coordination
* Memory management and garbage collection
* Exponential backoff and wait strategies
* Process state management and fault tolerance

Features and characteristics:

* Built specifically for long-running PHP processes
* Integrated with the :ref:`recruiterphp/concurrency<concurrency>` library for distributed locking
* Signal handling for graceful shutdowns (SIGINT, SIGTERM, SIGHUP, SIGQUIT)
* Leadership strategies for single-instance processes in distributed environments
* Configurable wait strategies with exponential backoff
* Memory leak prevention with periodic garbage collection
* Structured logging with process information

At high level, it provides these major components:

* A :ref:`RobustCommand<robust-command>`: interface for implementing resilient long-running commands
* A :ref:`RobustCommandRunner<command-runner>`: Symfony Console command wrapper that adds robustness features
* :ref:`Leadership strategies<leadership>`: for coordinating multiple instances of the same process
* :ref:`Wait strategies<wait-strategies>`: for implementing backoff and pause behavior

============
Hello World
============

| The simplest way to use Geezer is to implement the ``RobustCommand`` interface and run it with ``RobustCommandRunner``.
| Here's a basic example:

.. code-block:: php

   <?php

   use Recruiter\Geezer\Command\RobustCommand;
   use Recruiter\Geezer\Command\RobustCommandRunner;
   use Recruiter\Geezer\Leadership\Anarchy;
   use Recruiter\Geezer\Timing\ConstantPause;
   use Symfony\Component\Console\Input\InputDefinition;
   use Symfony\Component\Console\Input\InputInterface;
   use Psr\Log\NullLogger;

   class MyLongRunningProcess implements RobustCommand
   {
       private bool $shouldTerminate = false;

       public function leadershipStrategy(): LeadershipStrategy
       {
           return new Anarchy(); // No leadership coordination
       }

       public function waitStrategy(): WaitStrategy
       {
           return new ConstantPause(5000); // 5 second pause between iterations
       }

       public function name(): string
       {
           return 'my:long-running-process';
       }

       public function description(): string
       {
           return 'A robust long-running process example';
       }

       public function definition(): InputDefinition
       {
           return new InputDefinition();
       }

       public function init(InputInterface $input): void
       {
           // Initialize based on input parameters
       }

       public function hasTerminated(): bool
       {
           return $this->shouldTerminate;
       }

       public function execute(): bool
       {
           // Your business logic here
           echo "Processing...\n";

           // Return true if work was done, false if nothing to do
           return true;
       }

       public function shutdown(?\Throwable $e = null): bool
       {
           echo "Shutting down gracefully...\n";
           return true;
       }
   }

   // Create and run the robust command
   $command = new MyLongRunningProcess();
   $runner = new RobustCommandRunner($command, new NullLogger());

This creates a long-running process that:

* Runs in a loop, executing the ``execute()`` method repeatedly
* Waits 5 seconds between iterations
* Handles shutdown signals gracefully
* Logs process information
* Manages memory with periodic garbage collection

.. _leadership:

==================
Leadership Strategies
==================

| Leadership strategies determine how multiple instances of the same process coordinate to ensure only one is active at a time.
| This is essential for processes that should have only one active instance across a distributed system.

Anarchy
-------

| The ``Anarchy`` strategy provides no coordination - every process instance will run independently.
| Use this when you don't need single-instance coordination:

.. code-block:: php

   <?php

   use Recruiter\Geezer\Leadership\Anarchy;

   public function leadershipStrategy(): LeadershipStrategy
   {
       return new Anarchy();
   }

Dictatorship
------------

| The ``Dictatorship`` strategy uses distributed locking to ensure only one process instance is active.
| It requires a :ref:`recruiterphp/concurrency Lock<concurrency>` for coordination:

.. code-block:: php

   <?php

   use Recruiter\Geezer\Leadership\Dictatorship;
   use Recruiter\Concurrency\MongoLock;
   use Recruiter\Concurrency\MongoLockRepository;

   $mongoCollection = $mongodb->selectCollection('locks', 'geezer_locks');
   $lockRepository = new MongoLockRepository($mongoCollection);
   $lock = new MongoLock($lockRepository, 'my-process-lock');

   public function leadershipStrategy(): LeadershipStrategy
   {
       return new Dictatorship($lock, 60); // 60 second term of office
   }

| The ``Dictatorship`` strategy:

* Attempts to acquire the lock before allowing process execution
* Refreshes the lock periodically during execution
* Releases the lock on shutdown
* If lock acquisition fails, the process waits and retries

.. _wait-strategies:

================
Wait Strategies
================

| Wait strategies control how long a process waits between execution cycles.
| They implement the ``Iterator`` interface to provide flexible timing behavior.

ConstantPause
-------------

| Waits a fixed amount of time between iterations:

.. code-block:: php

   <?php

   use Recruiter\Geezer\Timing\ConstantPause;

   public function waitStrategy(): WaitStrategy
   {
       return new ConstantPause(1000); // 1 second pause
   }

ExponentialBackoffStrategy
--------------------------

| Implements exponential backoff, useful when the process might be waiting for external resources:

.. code-block:: php

   <?php

   use Recruiter\Geezer\Timing\ExponentialBackoffStrategy;

   public function waitStrategy(): WaitStrategy
   {
       return new ExponentialBackoffStrategy(100, 5000); // From 100ms to 5s max
   }

| The exponential backoff:

* Starts with no wait on the first iteration
* Doubles the wait time on each subsequent iteration
* Caps at the maximum specified value
* Resets to zero when ``execute()`` returns ``true`` (indicating successful work)

This pattern is ideal for processes that poll external resources - when work is available, the process responds quickly, but when idle, it gradually backs off to reduce resource usage.

.. _robust-command:

================
RobustCommand Interface
================

| The ``RobustCommand`` interface defines the contract for long-running processes.
| Implementing this interface provides a structured approach to building resilient services.

Key Methods
-----------

``execute(): bool``
    The main business logic. Return ``true`` if work was performed, ``false`` if nothing was done.
    When returning ``true``, wait strategies will reset their backoff.

``hasTerminated(): bool``
    Return ``true`` when the process should stop running. This allows for graceful termination
    based on business logic (e.g., processing a finite queue).

``shutdown(?\Throwable $e = null): bool``
    Called when the process is shutting down, either gracefully or due to an exception.
    Perform cleanup operations here.

``leadershipStrategy(): LeadershipStrategy``
    Return the leadership coordination strategy for this process.

``waitStrategy(): WaitStrategy``
    Return the timing strategy for pauses between execution cycles.

Leadership Events
-----------------

| Optionally implement ``LeadershipEventsHandler`` to receive notifications about leadership changes:

.. code-block:: php

   <?php

   use Recruiter\Geezer\Command\LeadershipEventsHandler;

   class MyProcess implements RobustCommand, LeadershipEventsHandler
   {
       public function leadershipAcquired(): void
       {
           echo "I am now the leader!\n";
       }

       public function leadershipLost(): void
       {
           echo "Leadership lost, stepping down...\n";
       }
   }

.. _command-runner:

========================
RobustCommandRunner
========================

| The ``RobustCommandRunner`` is a Symfony Console command that wraps your ``RobustCommand``
| and adds robustness features:

Features:

* **Signal Handling**: Gracefully handles SIGINT, SIGTERM, SIGHUP, and SIGQUIT
* **Leadership Election**: Manages leadership acquisition and release
* **Memory Management**: Performs garbage collection every 100 cycles
* **Structured Logging**: Includes hostname, PID, and process name in log messages
* **Exception Handling**: Catches and logs exceptions, ensuring graceful shutdown

Usage with Symfony Console Application:

.. code-block:: php

   <?php

   use Symfony\Component\Console\Application;
   use Psr\Log\LoggerInterface;

   $application = new Application();
   $logger = // ... your PSR-3 logger

   $command = new MyLongRunningProcess();
   $runner = new RobustCommandRunner($command, $logger);

   $application->add($runner);
   $application->run();

====================
Production Considerations
====================

Memory Management
-----------------

| Geezer automatically performs garbage collection every 100 execution cycles to prevent memory leaks
| in long-running processes. For memory-intensive operations, consider:

* Explicitly unsetting large variables after use
* Using generators for processing large datasets
* Monitoring memory usage and implementing additional cleanup in your ``execute()`` method

Signal Handling
---------------

| The runner registers handlers for common termination signals. In containerized environments,
| ensure your container runtime sends appropriate signals (SIGTERM) for graceful shutdown.

Leadership Coordination
-----------------------

| When using ``Dictatorship`` with distributed locking:

* Choose appropriate lock timeout values based on your execution cycle duration
* Monitor lock acquisition failures in your logs
* Consider lock refresh failures as indication of network or database issues

Logging
-------

| All log messages include contextual information:

* Hostname and process ID for debugging in distributed environments
* Timestamp and program name for correlation
* Leadership status changes for coordination debugging

Example log output:

.. code-block:: text

   [hostname:12345] Leadership election
   [hostname:12345] Leadership status changed in: `acquired`
   [hostname:12345] Processing work...


.. |recruiter.geezer.command.robustCommand| replace:: ``Recruiter\Geezer\Command\RobustCommand``
.. _recruiter.geezer.command.robustCommand: https://github.com/recruiterphp/geezer/blob/master/src/Command/RobustCommand.php

.. |recruiter.geezer.command.robustCommandRunner| replace:: ``Recruiter\Geezer\Command\RobustCommandRunner``
.. _recruiter.geezer.command.robustCommandRunner: https://github.com/recruiterphp/geezer/blob/master/src/Command/RobustCommandRunner.php

.. |recruiter.geezer.leadership.leadershipStrategy| replace:: ``Recruiter\Geezer\Leadership\LeadershipStrategy``
.. _recruiter.geezer.leadership.leadershipStrategy: https://github.com/recruiterphp/geezer/blob/master/src/Leadership/LeadershipStrategy.php

.. |recruiter.geezer.timing.waitStrategy| replace:: ``Recruiter\Geezer\Timing\WaitStrategy``
.. _recruiter.geezer.timing.waitStrategy: https://github.com/recruiterphp/geezer/blob/master/src/Timing/WaitStrategy.php
