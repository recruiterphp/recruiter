Jobs
============

| FIXME:! Un `job` é l'unita di lavoro......
| Come abbiamo già visto in precedenza esso incapsula la procedura da eseguire (l'esportazione del `Workable`), inoltre contiene tutte le informazioni necessarie alla corretta esecuzione, come ad esempio:

* la data di schedulazione,
* la policy di retry in caso di fallimento,
* lo stato attuale (da eseguire, in esecuzione, eseguito),
* il gruppo a cui appartiene
* altri (i.e. data di creazione, numero di tentativi effettuati, tags, ecc.)

| Un `job` può

* essere eseguito in process instantaneamente,
* essere schedulato per l'esecuzione in background il prima possibile,
* essere schedulato per l'esecuzione in background ad una determinata data/ora
* essere eseguito in process instantaneamente, ed in caso di fallimento essere schedulato per l'esecuzione in background in accordo con le proprie policy di retry.
* essere ritentato in caso di fallimento una o più volte, in accordo con delle specifiche politiche di retry.

.. All'interno della libreria ``Recruiter`` `esistono già delle classi Workable utilizzabili`__, per questi esempi utilizzeremo la classe |recruiter.workable.shellCommand.class|_ che permette di eseguire dei comandi di shell in background.

Per poter accodare dei job da eseguire dovremo avere in mano un istanza della classe |recruiter.recruiter.class|_.

.. warning::
   | Il recruiter é studiato per garantire l'esecuzione di ogni job **almeno** una volta.
   | Questo significa che, indipendentemente dalle policy di retry, in alcuni casi (anomali) un job potrebbe essere eseguito più di una volta.
   | Ciò potrebbe accadere se, ad esempio, un `worker` dovesse morire fatalmente dopo aver eseguito il job ma prima di riuscire a modificare lo stato del job in ``eseguito``; in quel caso il job verrà, dopo un certo periodo di tempo, assegnato ad un altro worker e quindi eseguito una seconda volta.

============
Hello World
============
| Abbiamo già visto questo esempio nel `capitolo relativo alla creazione di un oggetto Workable <workable.html>`_
| Tenendo conto di aver sviluppato una classe **HttpRequestCommand** il codice più semplice che potremmo scrivere per accodare una richiesta Http é il seguente:

.. code-block:: php

   <?php

   use MyDomain\HttpRequestCommand;
   use MyDomain\Request;
   use Recruiter\Recruiter;

   $mongodbInstance = new MongoDB\Client(...);
   $recruiter = new Recruiter($mongodbInstance);

   $request = Request::post($url, $body);
   HttpRequestCommand::fromRequest($request)
      ->asJobOf($recruiter)
      ->inBackground()
      ->execute() // this is the method defined in the Workable class
   ;

| in questo modo verrà schedulato un ``job`` che chiamerà il metodo ``execute()`` della classe ``HttpRequestCommand`` e verrà eseguito non appena un ``worker`` sarà disponibile.

==============================
Schedule a Job in the future
==============================
| Nel caso in cui volessimo che l'esecuzione del job sia programmata per il futuro (invece che quasi "istantanea"), possiamo farlo tramite il metodo ``scheduleAt()`` a cui dovremo pasasre un instanza di |timeless.moment.class|_
| Se ad esempio volessimo programmare l'esecuzione di una chiamata http per il giorno ``19 gennaio 2038`` potremmo fare in questo modo:

.. code-block:: php

   <?php

   use MyDomain\HttpRequestCommand;
   use MyDomain\Request;
   use Recruiter\Recruiter;
   use Timeless\Moment;

   $mongodbInstance = new MongoDB\Client(...);
   $recruiter = new Recruiter($mongodbInstance);

   $request = Request::post($url, $body);
   HttpRequestCommand::fromRequest($request)
      ->asJobOf($recruiter)
      ->scheduleAt(Moment::fromDateTime(new DateTime('2038-01-19T00:00:00.000000Z');))
      ->inBackground()
      ->execute()
   ;

In questo modo il job verrà messo in coda e verrà eseguto non appena ci sarà un worker libero disponibile successivamente alla data '2038-01-19T00:00:00.000000Z'

============
Retry
============

| Negli esempi visti in precdenza i job verrano eseguiti una sola volta, indipendentemente dal fatto che abbiano successo o meno.

| In caso di fallimento di un job il recruiter ci dà la possibilità di specificare il fatto la sua esecuzione possa essere ritentata.
| Per fare ciò dovremo assegnare una |retryPolicy.class|_ al job tramite il metodo ``retryWithPolicy(RetryPolicy $retryPolicy)``.

| Vedremo più avanti `come poter creare una propria RetryPolicy <retry-policies.html>`_, nel frattempo possiamo utilizzare le retry policies già incluse nella libreria recruiter.

| Supponiamo ad esempio di voler ritentare la nostra chiamata http nel caso in cui fallisca, di volere eseguire fino ad un massimo di tre retry e di voler attendere 60 secondi tra un tentativo e l'altro:

.. code-block:: php

   <?php

   use MyDomain\HttpRequestCommand;
   use MyDomain\Request;
   use Recruiter\Recruiter;
   use Recruiter\RetryPolicy\RetryManyTimes;
   use Timeless\Moment;


   $mongodbInstance = new MongoDB\Client(...);
   $recruiter = new Recruiter($mongodbInstance);

   $retryPolicy = new RetryManyTimes(3, 60);

   $request = Request::post($url, $body);
   HttpRequestCommand::fromRequest($request)
      ->asJobOf($recruiter)
      ->scheduleAt(Moment::fromDateTime(new DateTime('2038-01-19T00:00:00.000Z')))
      ->retryWithPolicy($retryPolicy)
      ->inBackground()
      ->execute()
   ;

| In base a questo esempio il nostro job verrà eseguito fino ad un massimo di 4 volte,
| la prima volta verrà eseguito in data: `2038-01-19T00:00:00.000Z` come da schedulazione, in seguito, in caso di fallimento, verranno fatti 3 nuovi tentativi distanziati 60 secondi l'uno dell'altro, che avranno quindi luogo nelle date:
| `2038-01-19T00:01:00.000Z`
| `2038-01-19T00:02:00.000Z`
| `2038-01-19T00:03:00.000Z`

| Questo é un semplice esempio di come poter ripetere un job in caso di fallimento, le :ref:`Retry Policies` possono avere anche logiche molto più complesse, date uno sguardo alla `pagina dedicata <retry-policies.html>`_ per capirne le potenzialità.

=============================
Retriable Exceptions
=============================

| Indipendentemente dalla `RetryPolicy` utilizzata, possiamo sempre specificare in quali casi eseguire un nuovo tentativo e in quali no.
| Il metodo ``retryWithPolicy`` permette infatti di specificare, come secondo argomento, un array di eccezioni per le quali é consentito eseguire un nuovo tentativo.
| Nel caso in cui questo array sia vuoto (come nel caso di default), il job verrà tentato di nuovo qualsiasi eccezione venga sollevata.
| Nel caso invece in cui questo array contiene una o più eccezioni, allora verrà effettuato un nuovo tentativo solo nel caso in cui venga intercettata un eccezione che sia un istanza di una delle classi contenute in questo array.
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

.. note::
   | Il metodo `inBackground()` viene implicitamente invocato nel caso in cui il job venga schedulato per l'esecuzione futura tramite il metodo `scheduleAt()`
   | Perciò queste 2 chiamate sono identiche ed in entrambi i casi l'esecuzione del job sarà esclusivamente in background.

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


==================
Raggrupare i Job
==================
| Come `abbiamo visto in precedenza <FIXME:!.html>`_ un worker può essere assegnato ad un solo specifico gruppo di jobs.
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

==================
Tags
==================
| É anche possibile taggare i jobs in modo tale da agevolare la ricerca di jobs o altre attività di query (es. statistiche).

.. code-block:: php

   <?php

   HttpCommand::fromRequest($request);
      ->asJobOf($recruiter)
      ->taggedAs(['userId:42', 'color:red'])
      ->inBackground()
      ->execute()
   ;
