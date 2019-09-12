Esecuzione dei jobs all'interno dei tests:
=======================================================

| Durante l'esecuzione di test che esercitano del codice che utilizza la libreria `recruiter` per accodare dei `jobs`, ci troviamo davanti al problema di dover far si che quei `jobs` vengano eseguiti pena il possibile fallimento dei test.

| Per fare in modo che i `jobs` in coda vengano eseguiti potremmo banalmente replicare quello che avviene nell'ambiente di produzione e quindi attivare i processi di `recruiter`, `jobs` e `cleaner`.

| A seconda dell'ambiente di test in cui ci troviamo, questa soluzione può presentare degli svantaggi, come ad esempio:

  * **Maggiore difficoltà di esecuzione dei test**: in quanto il nostro ambiente deve prevedere l'esecuzione di long running process ed assicurarsi che siano attivi durante l'intera esecuzione del test.
  * **Diminuzione della velocità di esecuzione dei test**: Nel caso in cui i test dipendano dal risultato dell'esecuzione dei job dovremmo attendere la loro esecuzione da parte dei worker, che per quanto reattivi possano essere non possono essere istantanei; dobbiamo inoltre considerare il fatto che i job potrebbero essere schedulati nel futuro e che quindi i worker non potranno eseguirli finché la data di schedulazione non sia passata.
  * **Impossibilità di dipendere da job schedulati molto avanti nel futuro**: Se nel caso in cui i job siano schedulati a qualche secondo di distanza dal momento corrente porta al solo rallentamento di esecuzione dei test, il caso in cui i job siano schedulati molto avanti nel futuro (es. il giorno seguente o il mese seguente) porta all'impossibilità di esecuzione dei test (non possiamo certo attendere cosi tanto tempo per terminarne l'esecuzione di un test).

| Tutti questi punti possono essere risolti utilizzando un metodo, che ci mette a disposizione la classe |recruiter.recruiter.class|_, che permette l'esecuzione, nel processo corrente, di tutti i job precedentemente accodati.

| Il metodo é ``flushJobsSynchronously()`` e può essere chiamato su qualsiasi istanza della classe |recruiter.recruiter.class|_ (quindi non per forza la stessa istanza utilizzata per accodare i `job`).
| Grazie a questo metodo possiamo assicurarci che tutti i job in coda vengano eseguiti senza dover avere un ambiente con i processi `recruiter`, `worker` e `cleaner` attivi e senza dover attendere la data di schedulazione di ognuno di essi.


.. code-block:: php

   <?php

   namespace Tests;

   use Core\DomainService;

   $mongodbInstance = new MongoDB\Client(...);
   $recruiter = new Recruiter($mongodbInstance);

   $domainService = new DomainService($recruiter);
   $domainService->methodThatQueuesJobs();

   $recruiter->flushJobsSynchronously(); // Here all previously queued jobs are executed


.. |recruiter.recruiter.class| replace:: ``Recruiter\Recruiter``
.. _recruiter.recruiter.class: https://github.com/recruiterphp/recruiter/blob/master/src/Recruiter/Recruiter.php
