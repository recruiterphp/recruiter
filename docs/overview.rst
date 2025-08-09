What is Recruiter
=================

| *Recruiter* is a Job Queue Manager built with PHP meant to be used in PHP projects.
| It allow php developers to perform some operations in background.

Features and characteristics:

* :ref:`Jobs<jobs>` are made persistent on MongoDB
* :ref:`Jobs<job-retry>` with complex and customizable strategies
* :ref:`Jobs<jobs>` are stored by default in an history collection for after the fact inspection and analytics
* :ref:`Multiple queues are supported through grouping<priority>`
* Built to be robust, scalable and fault tolerant

At high level, it provides a few major components:

* A :ref:`recruiter<recruiter>`: a single instance long-running process who assign enqueued jobs to a worker
* A :ref:`worker<worker>`: a multiple instace long-running processes that each execute a single job at a time
* A :ref:`cleaner<cleaner>`: a single instance long-running process who takes care of cleaning up the dirty conditions that can happen (i.e. worker dead for a fatal error)


=================================
Why
=================================

TO BE COMPLETED
#################################

Onebip is a payment system (think PayPal with mobile devices in place of credit cards), things like: payment notifications, subscription renewals, remainder messages, … are **really** important. You cannot skip or lose a job (notification are idempotent but payments are not). You cannot forgot to have completed a job (customer/merchant support must have data to do their job). You need to know if and when you can retry a failed job (external services have rate limits and are based on agreements/contracts). We have developed internally our job/queue solution called **Recruiter**. After a year in production and many *billions* of jobs we have decided to put what we have learned into a stand alone project and to make it available to everyone.
