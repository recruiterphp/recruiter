.. _workable:

Cos'é un oggetto Workable?
===================================

| Il workable é la classe che contiene la procedura che dovrà essere svolta in maniera asincrona.
| Questa verrà poi encapsulata in un `Job` del recruiter per poter essere messo in una coda ed essere successivamente eseguito da un :ref:`Worker<worker>`.

===================================
Implementare un proprio `Workable`
===================================

| Vediamo con un esempio come poter implementare un proprio `Workable`.
| Supponiamo di avere una procedura dove ad un certo punto avremo bisogno di inviare una richiesta http.
| Vogliamo che questa richiesta venga effettuata in maniera asincrona tramite `Recruiter`.

| Avremo quindi bisogno di creare una classe che si occupi dell'invio della richiesta http e che possa poi essere encapsulata in un `Job` del recruiter.
| Supponiamo di avere già nel nostro dominio una classe `Request` che si occupa di rappresentare la request da inviare, ed una classe `HttpClient` che si occupa dell'effetivo invio delle richieste.
| Iniziamo:

.. code-block:: php

   <?php

   use Http\Request;

   class HttpRequestCommand
   {
      /**
       * @var Request
       */
      private $request;

      public function __construct(Request $request)
      {
         $this->request = $request;
      }

      public static function fromRequest(Request $request)
      {
         return new self ($request);
      }

      public function execute()
      {
         $httpClient = Container::instance()->get('Http\Client');
         $httpClient->send($this->request);
      }
   }

.. note::
   | Possiamo nominare a nostro piacimento il metodo che verrà poi richiamato dai `Worker`, in questo caso é stato scelto `execute()` ma nulla vieta di utilizzare un nome diverso.
   | Vedremo più avanti come istruire i `Worker` a richiamare il metodo che vogliamo.


| Bene, ora che abbiamo la nostra classe che é in grado di inviare una `Request` vediamo come fare per poterla utilizzare tramite `Recruiter` e poter quindi eseguirla in maniera `asincrona`.
| Per prima cosa dovremo fra si che la nostra `HttpRequestCommand` implementi l'interfaccia |recruiter.workable.class|_.
| Questa interfaccia si compone di 3 metodi, utili a trasformare il nostro `Workable` in un `Job` del recruiter e a poterlo importare ed esportare per il salvataggio a database e successivo ripristino.

.. code-block:: php

   <?php

   use Recruiter\Recruiter;
   use Recruiter\Workable;
   use Http\Request;

   class HttpRequestCommand implements Workable
   {
      /**
       * @var Request
       */
      private $request;

      public function __construct(Request $request)
      {
         $this->request = $request;
      }

      public static function fromRequest(Request $request)
      {
         return new self ($request);
      }

      public function execute()
      {
         $httpClient = Container::instance()->get('Http\Client');
         $httpClient->send($this->request);
      }

      public function asJobOf(Recruiter $recruiter)
      {
         return $recruiter->jobOf($this);
      }

      public function export()
      {
         return ['request' => $this->request];
      }

      public static function import($parameters)
      {
         return new self(Request::box($parameters['request']));
      }
   }


| Ora il recruiter potrà creare un `Job` dedicato all'esecuzione di questa procedura, esportare i dati necessari all'esecuzione della procedura per poterli salvare su database e successivamente ricreare l'istanza del nostro `Workable` quando dovrà essere eseguito.

.. warning::
   | Ricorda che l'istanza della tua classe `Workable` verrà storicizzata su Mongo, assicurati quindi che il metodo **export()** della tua classe ritorni un contenuto serializzabile.
   | In questo esempio diamo per scontato che la classe ``Http\Client`` non sia serializzabile, per questo motivo non é inclusa nell'export e viene ricavata tramite l'utilizzo di un "ServiceLocator".

| Vediamo ora come utilizzarlo.

.. code-block:: php

   <?php

   use Recruiter\Recruiter;

   $mongodbInstance = new MongoDB\Client(...);
   $recruiter = new Recruiter($mongodbInstance);

   $request = Request::post($url, $body);
   HttpRequestCommand::fromRequest($request)
      ->asJobOf($recruiter)
      ->inBackground()
      ->execute() // this is the method defined in the Workable class
   ;

| Ora la nostra `Request` é in coda, pronta per essere inviata non appena un `Worker` sarà disponibile.
| Analizzando il codice possiamo notare che:
| - abbiamo instanziato il nostro `Workable` **HttpRequestCommand** passandoglia una `Request`.
| - abbiamo incapsulato il nostro `Workable` in un `Job`.
| - abbiamo settato il `Job` per l'esecuzione in background.
| - abbiamo istruito il `Worker` a chiamare il metodo **`execute()`** sull'istanza `Workable` contenuta nel `Job`.

| Nel :ref:`prossimo capitolo<jobs>` scopriremo tutte le opzioni disponibili per i vari `Job`.


.. |recruiter.workable.class| replace:: ``Recruiter\Workable``
.. _recruiter.workable.class: https://github.com/recruiterphp/recruiter/blob/master/src/Recruiter/Workable.php


.. |recruiter.workable-behaviour.class| replace:: ``Recruiter\WorkableBehaviour``
.. _recruiter.workable-behaviour.class: https://github.com/recruiterphp/recruiter/blob/master/src/Recruiter/WorkableBehaviour.php
