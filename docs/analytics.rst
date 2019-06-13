Analytics
===========================

| Il recruiter mette a disposizione delle statistiche sullo stato attuale delle code.
| Questo può essere utilizzato, ad esempio, per monitorare che tutte le code vengano smaltite correttamente.

| Per poter ricavare i dati statistici sullo stato delle code bisogna chiamare il metodo ``analytics`` dell'oggetto ``Recruiter\Recruiter``.
| Il valore restituito sarà un array contenente:

* **jobs**:
   - **queued**: il numero di jobs in coda con una data di schedulazione passata (e quindi da eseguire), questo numero dovrebbe rimanere stabile.
   - **postponed**: il numero di jobs in coda con una data di schedulazione futura (da eseguire solo quando la data di schedulazione sarà passata).

* **throughput**:
   - **value**: numero di job eseguiti al minuto
   - **value_per_second**: numero di job eseguiti al secondo

* **latency**:
   - **average**: Il numero medio di secondi che passa dalla data di schedulazione alla data di esecuzione del job. Un valore alto significa che ci sono troppi pochi worker in esecuzione per quella specifica coda.

* **execution_time**:
   - **average**: il tempo di esecuzione medio di un job.

.. code-block:: php

   <?php

   use Recruiter\Recruiter;

   $mongodbInstance = new MongoDB\Client(...);
   $recruiter = new Recruiter($mongodbInstance);
   $analytics = $recruiter->analytics();

   var_export($analytics);
   // array (
   //    'jobs' => array (
   //       'queued' => 10,
   //       'postponed' => 30,
   //       'zombies' => 0,
   //    ),
   //    'throughput' => array (
   //       'value' => 3.0,
   //       'value_per_second' => 0.05,
   //    ),
   //    'latency' => array (
   //       'average' => 0.0,
   //    ),
   //    'execution_time' => array (
   //       'average' => 0,
   //    ),
   // )

| Per visualizzare le statistiche relative ad uno specifico gruppo di job é possibile passare il gruppo come primo argomento alla funzione analytics.
| Per ulteriori modalità di utilizzo fare riferimento direttamente `al codice sorgente del metodo "analytics" <https://github.com/recruiterphp/recruiter/blob/master/src/Recruiter/Recruiter.php>`_.

.. code-block:: php

   <?php

   $analytics = $recruiter->analytics('custom-group');
