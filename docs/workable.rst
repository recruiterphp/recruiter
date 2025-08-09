.. _workable:

What is a Workable object?
===================================

| A workable is the class that contains the procedure that must be executed asynchronously.
| This will then be encapsulated in a recruiter `Job` to be placed in a queue and subsequently executed by a :ref:`Worker<worker>`.

===================================
Implementing your own `Workable`
===================================

| Let's see with an example how to implement your own `Workable`.
| Suppose we have a procedure where at some point we will need to send an HTTP request.
| We want this request to be executed asynchronously via `Recruiter`.

| We will therefore need to create a class that handles sending the HTTP request and can then be encapsulated in a recruiter `Job`.
| Suppose we already have in our domain a `Request` class that represents the request to be sent, and an `HttpClient` class that handles the actual sending of requests.
| Let's start:

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
   | We can name the method that will be called by the `Worker` as we like, in this case `execute()` was chosen but nothing prevents us from using a different name.
   | We will see later how to instruct the `Worker` to call the method we want.


| Good, now that we have our class that is able to send a `Request` let's see how to use it via `Recruiter` and be able to execute it `asynchronously`.
| First we need to make our `HttpRequestCommand` implement the |recruiter.workable.class|_ interface.
| This interface consists of 3 methods, useful for transforming our `Workable` into a recruiter `Job` and being able to import and export it for database storage and subsequent restoration.

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


| Now the recruiter will be able to create a `Job` dedicated to executing this procedure, export the data necessary for executing the procedure to save them to the database and subsequently recreate the instance of our `Workable` when it needs to be executed.

.. warning::
   | Remember that the instance of your `Workable` class will be stored in Mongo, so make sure that the **export()** method of your class returns serializable content.
   | In this example we assume that the ``Http\Client`` class is not serializable, which is why it is not included in the export and is obtained through the use of a "ServiceLocator".

| Let's now see how to use it.

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

| Now our `Request` is in queue, ready to be sent as soon as a `Worker` is available.
| Analyzing the code we can notice that:
| - we have instantiated our `Workable` **HttpRequestCommand** passing it a `Request`.
| - we have encapsulated our `Workable` in a `Job`.
| - we have set the `Job` for background execution.
| - we have instructed the `Worker` to call the **`execute()`** method on the `Workable` instance contained in the `Job`.

| In the :ref:`next chapter<jobs>` we will discover all the options available for the various `Job`.


.. |recruiter.workable.class| replace:: ``Recruiter\Workable``
.. _recruiter.workable.class: https://github.com/recruiterphp/recruiter/blob/master/src/Recruiter/Workable.php


.. |recruiter.workable-behaviour.class| replace:: ``Recruiter\WorkableBehaviour``
.. _recruiter.workable-behaviour.class: https://github.com/recruiterphp/recruiter/blob/master/src/Recruiter/WorkableBehaviour.php
