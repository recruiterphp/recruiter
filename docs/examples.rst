Examples
============

| Per poter eseguire dei lavori in background devi instanziare una classe che implementi l'interfaccia |recruiter.workable.class|_.
| Più avanti `scopriremo come creare una classe Workable`__.

All'interno della libreria ``Recruiter`` `esistono già delle classi Workable utilizzabili`__, per questi esempi utilizzeremo la classe |recruiter.workable.shellCommand.class|_ che permette di eseguire dei comandi di shell in background.

Per poter accodare dei job da eseguire dovremo avere in mano un istanza della classe |recruiter.recruiter.class|_.

============
Hello World
============
Mettiamo caso volessimo eseguire un comando shell (es. ``echo "Hello World"``) in background, potremmo farlo nel seguente modo:

.. code-block:: php

   <?php

   use Recruiter\Recruiter;
   use Recruiter\Workable\ShellCommand;

   $mongodbInstance = new MongoDB\Client(...);
   $recruiter = new Recruiter($mongodbInstance);

   ShellCommand::fromCommandLine('echo "Hello World" > /tmp/job.output');
      ->asJobOf($recruiter)
      ->inBackground()
      ->execute()
   ;

in questo modo verrà schedulato un ``job`` che eseguirà il comando ``bash ./my_custom_script.sh`` e questo job verrà eseguito non appena un ``worker`` sarà disponibile.

==============================
Schedule a Job in the future
==============================
Nel caso in cui volessimo far si che l'esecuzione del job non sia (quasi) "istantanea" ma che invece sia schedulata nel futuro possiamo farlo tramite il metodo ``scheduleAt()`` a cui dovremo pasasre un instanza di |timeless.moment.class|_

.. code-block:: php

   <?php

   use Recruiter\Recruiter;
   use Recruiter\Workable\ShellCommand;
   use Timeless\Moment;

   $mongodbInstance = new MongoDB\Client(...);
   $recruiter = new Recruiter($mongodbInstance);

   ShellCommand::fromCommandLine("bash ./my_custom_script.sh");
      ->asJobOf($recruiter)
      ->scheduleAt(Moment::fromDateTime(new DateTime('2151-02-21T15:03:01.012345Z');))
      ->inBackground()
      ->execute()
   ;

In questo modo il job verrà messo in coda e verrà eseguto non appena ci sarà un worker libero disponibile successivamente alla data '2151-02-21T15:03:01.012345Z'

============
Retry
============

| Negli esempi visti in precdenza i job verrano eseguiti una sola volta, indipendentemente dal fatto che abbiano successo o meno.

| In caso di fallimento di un job il recruiter ci dà la possibilità di specificare il fatto la sua esecuzione possa essere ritentata.
| Per fare ciò dovremo assegnare una |retryPolicy.class|_ al job tramite il metodo ``retryWithPolicy(RetryPolicy $retryPolicy)``.

| Vedremo più avanti `come poter creare una propria RetryPolicy`__, nel frattempo possiamo utilizzare le `retry policies già incluse nella libreria recruiter`__.

| Se ad esempio volessimo ritentare l'esecuzione di un job per 3 volte, aspettando 1 minuto fra un tentativo e l'altro, potremmo fare in questo modo:

.. code-block:: php

   <?php

   use Recruiter\Recruiter;
   use Recruiter\Workable\ShellCommand;
   use Recruiter\RetryPolicy\RetryManyTimes;

   $mongodbInstance = new MongoDB\Client(...);
   $recruiter = new Recruiter($mongodbInstance);

   $retryPolicy = new RetryManyTimes(3, 60);

   ShellCommand::fromCommandLine("false");
      ->asJobOf($recruiter)
      ->retryWithPolicy($retryPolicy)
      ->scheduleAt(Moment::fromDateTime(new DateTime('2027-02-21T15:00:00.0000Z');))
      ->inBackground()
      ->execute()
   ;

| In base a questo esempio il job verrà eseguito 4 volte (in quanto il comando ``false`` fallirà sempre),
| la prima volta verrà eseguito in data: `2027-02-21T15:00:00.0000Z` come da schedulazione, in seguito verranno fatti 3 nuovi tentativi distanziati 60 secondi l'uno dell'altro, che avranno quindi luogo nelle date:
| `2027-02-21T15:00:01.0000Z`
| `2027-02-21T15:00:02.0000Z`
| `2027-02-21T15:00:03.0000Z`

Questo é un semplice esempio di come poter ripetere un job in caso di fallimento, le :ref:`Retry Policies` possono avere anche logiche molto più complesse, date uno sguardo alla pagina dedicata per capirne le potenzialità.


| E' inoltre possibile specificare in quali casi eseguire un nuovo tentativo e in quali no.
| Il metodo ``retryWithPolicy`` é infatti cosi composto: ``retryWithPolicy(RetryPolicy $retryPolicy, $retriableExceptionTypes = [])``
| Nel caso di default in cui ``$retriableExceptionTypes`` é un array vuoto, il job verrà tentato di nuovo qualsiasi eccezione venga sollevata.
| Se invece specifichiamo una o più eccezioni allora il job verrà tentato di nuovo solo nel caso in cui venga sollevata un eccezione che sia un istanza di almeno una delle classi passateo.
| Es.:

.. code-block:: php

   <?php

   $retryPolicy = new RetryManyTimes(3, 60);
   $retriableExceptionTypes = [
      \Psr\Http\Client\NetworkExceptionInterface::class
   ];

   HttpCommand::fromRequest($request);
      ->asJobOf($recruiter)
      ->retryWithPolicy($retryPolicy, $retriableExceptionTypes)
      ->inBackground()
      ->execute()
   ;

In questo caso il job verrà ripetuto solo in caso avvenga un eccezione di tipo ``Psr\Http\Client\NetworkExceptionInterface``, in tutti gli altri casi il job verrà archiviato.

===============
Optimistic Jobs
===============

| Ci potrebbero essere dei casi in cui abbiamo bisogno che una procedura sia eseguita nella maniera più reattiva possibile
| Facciamo finta di essere un sistema di pagamento, e vogliamo avvisare un ipotetico merchant di un ipotetico acquisto andato a buon fine da parte di un ipotetico cliente.
| Per assicurare la migliore user experience possibile ci interessa ovviamente notificare l'avvenuto pagamento al Merchant il prima possibile, in modo tale che il cliente riceva subito il suo prodotto.
| Nel caso in cui l'endpoint atto a ricevere le notifiche di pagamento del Merchant non sia raggiungibile vorremmo che l'invio della notifica sia tentato nuovamente, magari dopo qualche minuto, sperando che nel frattempo l'endpoint sia tornato raggiungibile, non vogliamo però che il nostro processo si blocchi per qualche minuto quando potrebbe andare avanti a fare altre cose nel frattempo.
| Il recruiter ci viene incontro anche in questo caso, é possibile infatti fare in modo che un job sia eseguito `in process` nel momento in cui viene schedulato, e, solo in caso di fallimento, venga accodato per l'esecuzione in background in modo da poter eseguire i successivi retry.

| Es.:

.. code-block:: php

   <?php

   $retryPolicy = new RetryManyTimes(3, 60);
   $retriableExceptionTypes = [
      \Psr\Http\Client\NetworkExceptionInterface::class
   ];

   HttpCommand::fromRequest($request);
      ->asJobOf($recruiter)
      ->retryWithPolicy($retryPolicy, $retriableExceptionTypes)
      ->execute()
   ;

| Come potete notare l'unica cosa che abbiamo fatto é stata togliere la chiamata al metodo ``inBackground()``, in questo modo il comando verrà eseguito subito, e, solo in caso di fallimento, verrà inserito nella coda dei job da eseguire in background.
| Nel caso in cui non venga settata una RetryPolicy, il processo verrà eseguito subito e, sia in caso di successo sia in caso di fallimento, verrà archiviato senza nessun successivo tentativo.

.. warning::
   Il metodo `inBackground()` viene implicitamente invocato nel caso in cui il job venga schedulato per l'esecuzione futura tramite il metodo `scheduleAt()`
   Perciò queste 2 chiamate sono identiche ed in entrambi i casi l'esecuzione del job sarà esclusivamente in background.

   .. code-block:: php

      <?php

      HttpCommand::fromRequest($request);
         ->asJobOf($recruiter)
         ->retryWithPolicy($retryPolicy, $retriableExceptionTypes)
         ->inBackground()
         ->execute()
      ;

      HttpCommand::fromRequest($request);
         ->asJobOf($recruiter)
         ->retryWithPolicy($retryPolicy, $retriableExceptionTypes)
         ->scheduleAt(Moment::fromDateTime(new DateTime('2151-02-21T15:03:01.012345Z');))
         ->execute()
      ;


===============
Grouping Jobs
===============
| Come `abbiamo visto in precedenza <CHANGEME.html>`_ un worker può essere assegnato ad un solo specifico gruppo di jobs.
| Per assegnare un job ad un gruppo si utilizza il metodo ``inGroup($group)``

.. code-block:: php

   <?php

   HttpCommand::fromRequest($request);
      ->asJobOf($recruiter)
      ->inGroup('http')
      ->inBackground()
      ->execute()
   ;

| In questo modo solo i worker relativi al gruppo `http` potranno eseguire questo job


.. |recruiter.workable.class| replace:: ``Recruiter\Workable``
.. _recruiter.workable.class: https://github.com/recruiterphp/recruiter/blob/master/src/Recruiter/Workable.php

.. |recruiter.workable.shellCommand.class| replace:: ``Recruiter\Workable\ShellCommand``
.. _recruiter.workable.shellCommand.class: https://github.com/recruiterphp/recruiter/blob/master/src/Recruiter/Workable/ShellCommand.php

.. |recruiter.recruiter.class| replace:: ``Recruiter\Recruiter``
.. _recruiter.recruiter.class: https://github.com/recruiterphp/recruiter/blob/master/src/Recruiter/Recruiter.php

.. |timeless.moment.class| replace:: ``Timeless\Moment``
.. _timeless.moment.class: https://github.com/recruiterphp/recruiter/blob/master/src/Timeless/Moment.php

.. |retryPolicy.class| replace:: ``Recruiter\RetryPolicy``
.. _retryPolicy.class: https://github.com/recruiterphp/recruiter/blob/master/src/Recruiter/RetryPolicy.php

__ how-to-create-a-workable.html

__ predefined-workables.html

__ how-to-create-a-retry-policy.html

__ predefined-retry-policies.html


.. TODO
.. trovare un esempio migliore di comando da eseguire rispetto a my_custom_script
