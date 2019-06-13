Setup
============

============
Dependencies
============
| You need `Php <https://php.net/>`_ version > 7.2
| You need a running `Mongodb <https://www.mongodb.com/>`_ instance


============
Install
============
You can install Recruiter through `Composer`_ by running the following command in your terminal:

.. code-block:: bash

    $ composer require recruiterphp/recruiter


.. _Composer: https://getcomposer.org

============
Setup
============
| Dovrai creare un file di bootstrap per i processi worker, in modo tale da includere le tue classi cossiché possano essere utilizzate dal worker.

| Se ad esempio utilizzi l'autoloading di `composer` per il tuo progetto, puoi scrivere un file di bootstrap semplice come questo:

.. code-block:: php

   <?php
   # src/recruiter-autoload.php

   require_once __DIR__ . '/../vendor/autoload.php';

   // in the bootstrap file you have access to a Recruiter\Recruiter instance through global variable `$recruiter`.
   // $recruiter;

| Dopodiché dovrai lanciare i processi `recruiter`, `worker` e `cleaner`

.. code-block:: bash

   $ php vendor/bin/recruiter start:recruiter --target 127.0.0.1:27017 --bootstrap src/recruiter-bootstrap.php
   $ php vendor/bin/recruiter start:worker --target 127.0.0.1:27017 --bootstrap src/recruiter-bootstrap.php
   $ php vendor/bin/recruiter start:cleaner --target 127.0.0.1:27017


============
Sample
============
| Here is an `empty sample project <https://github.com/recruiterphp/recruiter-example>`_ using Recruiter.
