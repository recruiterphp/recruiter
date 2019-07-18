.. _retry-policies:

Retry Policies
==============

===================================
Implements a custom RetryPolicy
===================================

| All'interno della libreria sono presenti delle RetryPolicy che coprono i casi più comuni.
| In caso di necessità potremo comunque creare una nuova policy in modo da coprire la nostra necessità.
| Per creare una nuova policy dovremo creare una classe che implementi l'interfaccia |retryPolicy.class|_

===================================
DoNotDoItAgain
===================================

This is the default (implicit) `RetryPolicy`, use it only if you want to make explicit the fact that the job should not be repeated.

.. code-block:: php

   <?php

   use Recruiter\Recruiter;
   use Recruiter\Workable\ShellCommand;
   use Recruiter\RetryPolicy\DoNotDoItAgain;

   $mongodbInstance = new MongoDB\Client(...);
   $recruiter = new Recruiter($mongodbInstance);

   ShellCommand::fromCommandLine("false");
      ->asJobOf($recruiter)
      ->retryWithPolicy(new DoNotDoItAgain())
      ->inBackground()
      ->execute()
   ;



===================================
ExponentialBackoff
===================================

Questa `RetryPolicy` permette di ritentare l'esecuzione di un job ad intervalli esponenziali.
Ad esempio possiamo impostare di avere un massimo di 10 retry con un intervallo iniziale di 30 secondi.
Questo significa che dopo il primo fallimento verrà effettuato un retry dopo 30 secondi, in caso anche questo fallisca verrà effettuato un altro retry dopo 60 secondi, nel caso in cui anche questo fallisca verrà effettuato un nuovo retry dopo 120 secondi e cosi via, fino ad un massimo di 10 nuovi tentativi.

L' `ExponentialBackoff` policy accetta come parametri il numero massimo di tentativi da effettuare ed i secondi iniziali di intervallo prima di effettuare il primo tentativo.

Examples:

.. code-block:: php

   <?php

   use Recruiter\Recruiter;
   use Recruiter\Workable\ShellCommand;
   use Recruiter\RetryPolicy\ExponentialBackoff;

   $mongodbInstance = new MongoDB\Client(...);
   $recruiter = new Recruiter($mongodbInstance);

   $retryPolicy = ExponentialBackoff::forTimes(10, 30);
   //This is the same to the following:
   // $retryPolicy = new ExponentialBackoff(10, 30);

   ShellCommand::fromCommandLine("false");
      ->asJobOf($recruiter)
      ->retryWithPolicy($retryPolicy)
      ->inBackground()
      ->execute()
   ;



..TODO: verificare al parte seguente prima di pubblicarla
.. Questa policy comprende anche un factory method che accetta il numero di secondi massimo in cui riprovare ed il numero di secondi iniziali di intervallo prima di effettuare il primo tentativo:
..
.. Examples:
..
.. .. code-block:: php
..
..    <?php
..
..    use Recruiter\Recruiter;
..    use Recruiter\Workable\ShellCommand;
..    use Recruiter\RetryPolicy\ExponentialBackoff;
..
..    $mongodbInstance = new MongoDB\Client(...);
..    $recruiter = new Recruiter($mongodbInstance);
..
..    // In this case there will be a maximum of 4 attempts: after the first failure a retry will be made after 30 seconds, another one after 60 seconds, and the last after 120 seconds
..    $retryPolicy = ExponentialBackoff::forAnInterval(120, 30);
..
..    ShellCommand::fromCommandLine("false");
..       ->asJobOf($recruiter)
..       ->retryWithPolicy($retryPolicy)
..       ->scheduleAt(Moment::fromDateTime(new DateTime('2027-02-21T15:00:00.0000Z');))
..       ->inBackground()
..       ->execute()
..    ;



==================
RetryForevers
==================

Questa `RetryPolicy` permette di ritentare l'esecuzione di un job all'infinito specificando l'intervallo di tempo tra un tentativo e l'altro.
Se ad esempio volessimo eseguire un job all'infinito aspettando 30 secondi tra un tentativo e l'altro possiamo scrivere:

.. code-block:: php

   <?php

   use Recruiter\Recruiter;
   use Recruiter\Workable\ShellCommand;
   use Recruiter\RetryPolicy\RetryForevers;

   $mongodbInstance = new MongoDB\Client(...);
   $recruiter = new Recruiter($mongodbInstance);

   $retryPolicy = RetryForevers::afterSeconds(30);
   //This is the same to the following:
   // $retryPolicy = new RetryForevers(30);

   ShellCommand::fromCommandLine("false");
      ->asJobOf($recruiter)
      ->retryWithPolicy($retryPolicy)
      ->inBackground()
      ->execute()
   ;



==================
RetryManyTimes
==================

Questa `RetryPolicy` permette di ritentare l'esecuzione di un job un numero finito di volte specificando l'intervallo di tempo tra un tentativo e l'altro.
Se ad esempio vogliamo ritentare un job per 3 volte, aspettando 30 secondi tra un tentativoo e l'altro, possiamo scrivere:

.. code-block:: php

   <?php

   use Recruiter\Recruiter;
   use Recruiter\Workable\ShellCommand;
   use Recruiter\RetryPolicy\RetryManyTimes;

   $mongodbInstance = new MongoDB\Client(...);
   $recruiter = new Recruiter($mongodbInstance);

   $retryPolicy = RetryManyTimes::forTimes(3, 30);
   //This is the same to the following:
   // $retryPolicy = new RetryManyTimes(3, 30);

   ShellCommand::fromCommandLine("false");
      ->asJobOf($recruiter)
      ->retryWithPolicy($retryPolicy)
      ->inBackground()
      ->execute()
   ;



==================
TimeTable
==================

Questa `RetryPolicy` permette di ritentare l'esecuzione di un job ad intervalli regolari dipendenti da quanto tempo é passato rispetto alla creazione del job.

Ad esempio se volessimo ritentare il job
* ogni minuto per i primi 5 minuti di vita del job,
* ogni 5 minuti per la prima ora (cioé i successivi 55 minuti)
.. * ogni 5 minuti per i successivi 55 minuti
* ed ogni ora per le prime 24 ore (cioé le successive 23 ore)
.. * ogni ora per le successive 23 ore

Possiamo scrivere il seguente codice:

.. code-block:: php

   <?php

   use Recruiter\Recruiter;
   use Recruiter\Workable\ShellCommand;
   use Recruiter\RetryPolicy\TimeTable;

   $mongodbInstance = new MongoDB\Client(...);
   $recruiter = new Recruiter($mongodbInstance);

   $retryPolicy = new TimeTable([
      '5 minutes ago' => '1 minute',
      '1 hour ago' => '5 minutes',
      '24 hours ago' => '1 hour',
   ]);

   ShellCommand::fromCommandLine("false");
      ->asJobOf($recruiter)
      ->retryWithPolicy($retryPolicy)
      ->inBackground()
      ->execute()
   ;


.. warning::
   Questa policy accetta un array chiave-valore dove sia le chiavi che i valori devono essere stringhe parsabili dalla funzione php `strtotime`_

   .. _strtotime: https://www.php.net/manual/en/function.strtotime.php


.. |retryPolicy.class| replace:: ``Recruiter\RetryPolicy``
.. _retryPolicy.class: https://github.com/recruiterphp/recruiter/blob/master/src/Recruiter/RetryPolicy.php
