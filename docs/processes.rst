Processes
=================

.. _recruiter:

=================================
Recruiter process
=================================

| Il processo `recruiter` é colui che si occupa di assegnare, al momento giusto, i job presenti in coda ai vari `worker` che sono in esecuzione.

| E' importante che una sola instanza del processo `recruiter` sia live in un determinato momento, ma lo sviluppatore non deve preoccuparsene in quanto il `recruiter` include al suo interno un meccanismo di muta esclusione, é quindi possibile eseguire più processi in contemporanea (ad esempio se si hanno più server identici, ognuno dei quali lancia la propria istanza di `recruiter`) senza che ci siano problemi di concorrenza.
| Per maggiori informazioni riguardo a questa funzionalità guardare il capitolo relativo a `Geezer <geezer.html>`_

| Il processo `recruiter` per funzionare ha bisogno di collegarsi ad un istanza di mongodb, é possibile specificare l'URI tramite l'opzione **--target ${MONGOURI}** (se non specificata il processo `recruiter` proverà a collegarsi a `localhost:27017`)

| Se si vuole approfittare degli `hook`_ messi a disposizione dal processo `recruiter` é indispensabile passare al comando uno script php da includere, in modo tale che le funzioni definite dall'utente siano visibili. Questo può essere fatto tramite l'opzione **--bootstrap**

.. _hook: recruiter-hooks.html

| Per lanciare il processo `recruiter` utilizzare il seguente comando:

.. code-block:: bash

   $ php vendor/bin/recruiter start:recruiter --target 127.0.0.1:27017

Per una lista completa delle opzioni lanciare il comando:

.. code-block:: bash

   $ php vendor/bin/recruiter help start:recruiter


.. |recruiter.binary| replace:: $ php vendor/bin/recruiter


.. _worker:

=================================
Worker process
=================================

| Il processo `worker` é colui che si occupa effettivamente di eseguire un determinato job.
| Al proprio avvio il processo `worker` comunica al processo `recruiter` il fatto di essere disponibile ad accettare lavori.
| E' possibile eseguire più processi `worker` in contemporanea, ognuno di questi eseguirà un singolo job alla volta.
| E' possibile limitare un `worker` all'esecuzione di un solo specifico gruppo di lavori, questo é un metodo per poter gesitre in maniera blanda le `priorità`_.

.. _priorità: priority.html

| Il processo `worker` per funzionare ha bisogno di collegarsi ad un istanza di mongodb, é possibile specificare l'URI tramite l'opzione **--target ${MONGOURI}** (se non specificata il processo `worker` proverà a collegarsi a `localhost:27017`)

| Ad eccezione di alcuni rari casi, il processo `worker` dovrà eseguire codice facente parte del progetto in cui viene incluso, é quindi indispensabile passare al comando uno script php da includere, in modo tale che le classi definite dall'utente siano visibili. Questo può essere fatto tramite l'opzione **--bootrap**

| Per lanciare il processo `worker` utilizzare il seguente comando:

.. code-block:: bash

   $ php vendor/bin/recruiter start:worker --target 127.0.0.1:27017 --bootstrap $APP_BASE_PATH/worker-boostrap.php

Per una lista completa delle opzioni lanciare il comando:

.. code-block:: bash

   $ php vendor/bin/recruiter help start:worker


.. _cleaner:

=================================
Cleaner process
=================================

| Il processo `cleaner` si occupa di mantenere coerente lo stato della libreria.
| Ad esempio un determinato `worker` potrebbe morire in maniera fatale durante l'esecuzione di un job, lasciando il job lockato e quindi non più eseguibile da altri.
| Grazie al processo `cleaner` i job possono essere rimessi nella coda di esecuzione dopo un determinato periodo di stallo.

| Il processo `cleaner` per funzionare ha bisogno di collegarsi ad un istanza di mongodb, é possibile specificare l'URI tramite l'opzione **--target ${MONGOURI}** (se non specificata il processo `cleaner` proverà a collegarsi a `localhost:27017`)

| Per lanciare il processo `cleaner` utilizzare il seguente comando:

.. code-block:: bash

   $ php vendor/bin/recruiter start:cleaner --target 127.0.0.1:27017

Per una lista completa delle opzioni lanciare il comando:

.. code-block:: bash

   $ php vendor/bin/recruiter help start:cleaner


=================================
Logging
=================================
| Come abbiamo visto nei paragrafi precedenti, é possibile lanciare i vari processi (`recruiter`, `worker` e `cleaner`) grazie allo script php ``vendor/bin/recruiter``.
| Lo script php ``vendor/bin/recruiter`` non fa altro che creare una istanza di |symfony.console.application.doc|_, registrare i vari |symfony.console.command.doc|_ (Recruiter, Worker e Clenaer Commands) ed eseguire l'applicazione symfony.
| Lo script crea i comandi Recruiter, Worker e Cleaner iniettandogli un istanza di |psr.loginterface.doc|_ che logga su standard output. Nel caso in cui si desiderasse una diversa tipologia di |psr.loginterface.doc|_ bisogna includere questi comandi nella propria ``Symfony\Component\Console\Application`` in modo tale da poterli inizializzare iniettandogli il logger che si vuole.


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
