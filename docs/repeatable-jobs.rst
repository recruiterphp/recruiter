Repeatable Jobs
========================
| Ci sono dei casi in cui la procedura da eseguire deve essere ripetuta nel tempo, indipendentemente dal fatto che venga completata con sucesso o meno.
| Questo può essere svolto grazie a dei particolari job che prendono il nome di `RepeatableJob`.
| Vediamo come utilizzarli tramite un esempio.
|
| Supponiamo di voler inviare, ogni giorno alle 06:00 UTC, un report ad un determinato indirizzo email.
| Avremo bisogno quindi di :ref:`creare il nostro Workable<workable>` che contenga la procedura per generare/inviare il report, procediamo, come visto in precedenza, ad implementare la classe |recruiter.workable.class|_ utilizzando il trait |recruiter.workable-behaviour.class|_ per evitare di scrivere codice ridondante.

.. code-block:: php

   <?php

   use Recruiter\Recruiter;
   use Recruiter\Workable;
   use Recruiter\WorkableBehaviour;

   class DailyReportCommand implements Workable
   {
      use WorkableBehaviour;

      public function execute()
      {
         // ...
         // here we generate the report and send it to the desired recipient
         // ...
      }
   }

| Ora dobbiamo far si che la nostra classe implementi anche l'interfaccia |recruiter.repeatableJob.class|_ in modo che possa essere schedulato automaticamente secondo un determinato schema.

.. code-block:: php

   <?php

   use Recruiter\Recruiter;
   use Recruiter\Repeatable;
   use Recruiter\RepeatableBehaviour;
   use Recruiter\Workable;
   use Recruiter\WorkableBehaviour;

   class DailyReportCommand implements Workable, Repeatable
   {
      use WorkableBehaviour, RepeatableBehaviour;

      public function execute()
      {
         // ...
         // here we generate the report and send it to the desired recipient
         // ...
      }

      public function urn(): string
      {
         return 'report:daily';
      }

      public function unique(): bool
      {
         return false;
      }
   }

| Abbiamo quindi assegnato un nome univoco al nostro `Repeatable` (tramite il metodo ``urn()``), ed indicato al `Recruiter` se é possibile o meno che 2 o più istanze di questo `job` si sovrappongano (tramite il metodo ``unique()``)

| Ora che abbiamo un `Repeatable` vediamo come poterlo schedulare ad intervalli regolari.
| Per indicare la politica di esecuzione del job dovremo utilizzare una |recruiter.schedule-policy.class|_
| All'interno della libreria recruiter troviamo una `SchedulePolicy` già esistente che prende il nome di |recruiter.cron.class|_ e permette di specificare gli intervalli di esecuzione con la stessa sintassi utilizzata dal demone unix `cron <https://en.wikipedia.org/wiki/Cron>`_.
| Quindi, per inviare il nostro report ogni giorno alle `06:00 UTC` dovremo fare in questo modo:

.. code-block:: php

   <?php

   use Recruiter\Recruiter;
   use Recruiter\SchedulePolicy\Cron;

   $mongodbInstance = new MongoDB\Client(...);
   $recruiter = new Recruiter($mongodbInstance);

   $schedulePolicy = new Cron('0 6 * * *');

   $scheduler = (new DailyReportCommand())
      ->asRepeatableJobOf($this->recruiter)
      ->repeatWithPolicy($schedulePolicy)
      ->retryWithPolicy(new DoNotDoItAgain()) // this is the default behaviour
      ->create()
   ;


| Per eliminare uno scheduler attivo é possibile utilizzare il comando console `scheduler:remove` e seguirne le istruzioni.

.. code-block:: bash

   $ php vendor/bin/recruiter scheduler:remove --target 127.0.0.1:27017


.. |recruiter.workable.class| replace:: ``Recruiter\Workable``
.. _recruiter.workable.class: https://github.com/recruiterphp/recruiter/blob/master/src/Recruiter/Workable.php

.. |recruiter.repeatableJob.class| replace:: ``Recruiter\Repeatable``
.. _recruiter.repeatableJob.class: https://github.com/recruiterphp/recruiter/blob/master/src/Recruiter/Repeatable.php

.. |recruiter.workable-behaviour.class| replace:: ``Recruiter\WorkableBehaviour``
.. _recruiter.workable-behaviour.class: https://github.com/recruiterphp/recruiter/blob/master/src/Recruiter/WorkableBehaviour.php

.. |recruiter.schedule-policy.class| replace:: ``Recruiter\SchedulePolicy``
.. _recruiter.schedule-policy.class: https://github.com/recruiterphp/recruiter/blob/master/src/Recruiter/SchedulePolicy.php

.. |recruiter.cron.class| replace:: ``Recruiter\SchedulePolicy\Cron``
.. _recruiter.cron.class: https://github.com/recruiterphp/recruiter/blob/master/src/Recruiter/SchedulePolicy/Cron.php
