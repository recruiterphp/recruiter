Setup
============

============
Dependencies
============
| You need `Php <https://php.net/>`_ version >= 8.4
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
| You will need to create a bootstrap file for the worker processes, in order to include your classes so they can be used by the worker.

| If for example you use `composer` autoloading for your project, you can write a simple bootstrap file like this:

.. code-block:: php

   <?php
   # src/recruiter-autoload.php

   require_once __DIR__ . '/../vendor/autoload.php';

   // in the bootstrap file you have access to a Recruiter\Recruiter instance through global variable `$recruiter`.
   // $recruiter;

| After that you will need to launch the `recruiter`, `worker` and `cleaner` processes. You can specify the MongoDB URI via ``--target`` or by setting the MONGODB_URI environment variable (``--target`` takes precedence):

.. code-block:: bash

   $ php vendor/bin/recruiter start:recruiter --target 127.0.0.1:27017 --bootstrap src/recruiter-bootstrap.php
   $ php vendor/bin/recruiter start:worker --target 127.0.0.1:27017 --bootstrap src/recruiter-bootstrap.php
   $ php vendor/bin/recruiter start:cleaner --target 127.0.0.1:27017


============
Sample
============
| Here is an `empty sample project <https://github.com/recruiterphp/recruiter-example>`_ using Recruiter.
