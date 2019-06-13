What is Recruiter
=================

| *Recruiter* is a Job Queue Manager built with PHP meant to be used in PHP projects.
| It allow php developers to perform some operations in background.

Features and characteristics:

* `Jobs <jobs.html>`_ are made persistent on MongoDB
* `Jobs are retriable <jobs.html#retry>`_ with complex and customizable strategies
* `Jobs <jobs.html>`_ are stored by default in an history collection for after the fact inspection and analytics
* `Multiple queues are supported through tagging <priority.html>`_
* Built to be robust, scalable and fault tolerant

At high level, it provides a few major components:

* A `recruiter`_ a single instance long-running process who assign enqueued jobs to a worker
* A `worker`_ a multiple instace long-running processes that each execute a single job at a time
* A `cleaner`_ a single instance long-running process who takes care of cleaning up the dirty conditions that can happen (i.e. worker dead for a fatal error)


=================================
Why
=================================

DA COMPLETARE
#################################

Onebip is a payment system (think PayPal with mobile devices in place of credit cards), things like: payment notifications, subscription renewals, remainder messages, … are **really** important. You cannot skip or lose a job (notification are idempotent but payments are not). You cannot forgot to have completed a job (customer/merchant support must have data to do their job). You need to know if and when you can retry a failed job (external services have rate limits and are based on agreements/contracts). We have developed internally our job/queue solution called **Recruiter**. After a year in production and many *billions* of jobs we have decided to put what we have learned into a stand alone project and to make it available to everyone.


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
