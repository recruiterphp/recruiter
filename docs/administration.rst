Administration console
===========================

| The library provides some console commands for recruiter management:

.. _recovering:

===========================
Recovering a job
===========================

| In case we want to re-execute a `job` that is in the :ref:`archive<archived-collection>` we can do it via the **job:recover** console command

| To execute this command it is necessary to know the ObjectId of the `job` you want to restore, to pass as argument to the command.

| It is possible to specify the new scheduling date via the **scheduleAt** option, otherwise the job will be rescheduled for execution at the current date.

.. code-block:: bash

   $ php vendor/bin/recruiter job:recover --target mongodb://localhost:27017/recruiter --scheduleAt "2019-12-01T22:18:00Z" 5d27436e2bacd566a67e85e4

===========================
Analytics
===========================

| It is possible to view the :ref:`statistics<analytics-page>` also in console via the command: **bko:analytics**

| You can specify the mongo server URI to connect to via the ``--target`` option, or by setting the MONGODB_URI environment variable (``--target`` takes precedence if both are provided).

| It is possible to limit statistics to a single group of jobs via the ``group`` option.

.. code-block:: bash

   $ php vendor/bin/recruiter bko:analytics --target mongodb://localhost:27017/recruiter --group html
