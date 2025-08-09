Mongo Collections
===============================

| As we have already seen, the `recruiter` library relies on `MongoDB` for data persistence.
| Let's look at the structure used at a high level so that we have a general understanding that will make investigations easier in case of anomalous behavior.

.. _roster-collection:

============================
"roster" collection
============================
| The **roster** collection contains data related to various running `workers`.

| Thanks to this collection, the `recruiter` process knows which workers are present and which of them are available to take on a new `job`. It is also in this collection that the `recruiter` process stores which `job` has been assigned to which `worker`. In this way, each `worker` process repeatedly reads (polling) its own document to identify which will be the next `job` to execute.

| Each `worker` process registers its data in a document of this collection at startup. This document is removed during the worker's shutdown phase.

| Each `worker` process periodically updates this document with the current date, making it explicit that it is still "alive".

| Thanks to this date, the recruiter can understand that the `worker` is no longer online, being able to remove the document related to the dead `worker` and thus avoiding assigning jobs to it.

.. _scheduled-collection:

============================
"scheduled" collection
============================
| The **scheduled** collection contains the various `jobs` to be executed.

| The recruiter process periodically reads (polling) this collection to identify which `jobs` should be executed, based on their scheduling date.

| In case a `job` is executed unsuccessfully, the scheduling date will be updated according to its retry policy. If the maximum number of retries is reached, the document will be moved to the **archived** collection.


.. _archived-collection:

============================
"archived" collection
============================
| The **archived** collection contains the history of various executed `jobs`.

| A `job` is moved from the **scheduled** collection to the **archived** collection when it is executed and completed successfully, or when execution fails and the maximum number of execution attempts has been reached.
| The `cleaner` process is responsible for keeping the size of this collection small by deleting `jobs` older than 5 days (default).
| It is possible to modify this time window through the **clean-after** option of the `cleaner` process.

| This collection is very useful for 2 reasons:

   * investigating the reasons for job failure (the document includes the job status (completed or not) and the reason for the last failure, plus other useful data)
   * :ref:`rescheduling a job<recovering>`

.. _schedulers-collection:

============================
"schedulers" collection
============================
| The **schedulers** collection contains templates of `jobs` that must be executed periodically.

| The `recruiter` process periodically reads (polling) this collection to create and schedule new `jobs` to add to the `scheduled` collection.
