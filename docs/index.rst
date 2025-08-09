.. Recruiter documentation master file, created by
   sphinx-quickstart on Thu Jun 13 08:28:22 2019.
   You can adapt this file completely to your liking, but it should at least
   contain the root `toctree` directive.

Welcome to Recruiter's documentation!
=====================================

| *Recruiter* is a Job Queue Manager built with PHP meant to be used in PHP projects.
| It allows PHP developers to perform operations in the background.

Features and characteristics:

* :ref:`Jobs<jobs>` are made persistent on MongoDB
* :ref:`Jobs<job-retry>` with complex and customizable strategies
* :ref:`Jobs<jobs>` are stored by default in an history collection for after the fact inspection and analytics
* :ref:`Multiple queues are supported through grouping<priority>`
* Built to be robust, scalable and fault tolerant

At high level, it provides a few major components:

* A :ref:`recruiter<recruiter>`: a single instance long-running process that assigns enqueued jobs to workers
* A :ref:`worker<worker>`: multiple instance long-running processes that each execute a single job at a time
* A :ref:`cleaner<cleaner>`: a single instance long-running process that takes care of cleaning up dirty conditions that can happen (i.e. worker dead from a fatal error)

|
|
.. toctree::
   :maxdepth: 2

   overview
   processes
   setup
   workable
   jobs
   retry-policies
   repeatable-jobs

   recruiter-hooks
   priority
   analytics
   mongo-collections
   administration
   inside-tests

   todo
