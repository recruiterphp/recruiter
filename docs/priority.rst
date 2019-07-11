How to handle priority
=================================

| Supponiamo di avere un applicazione di tipo e-commerce che abbia al suo interno queste funzionalità.

* invio di un messaggio email di double-option (per la verifica dell'indirizzo email prima dell'acquisto).
* invio di una email di conferma acquisto.
* invio di una email di follow-up agli utenti che non hanno completato l'acquisto dopo 7 giorni.

| In tutti e tre i casi si tratta dell'invio di un email (possiamo avere quindi un solo tipo di ``Workable``), ma tutte e tre hanno una diversa priorità:

| Nel primo caso vogliamo che l'email arrivi il prima possibile, l'utente é davanti allo schermo che attende quell'email e vogliamo dargli il miglior servizio possibile, inoltre più tardi arriva e più é facile che l'acquisto venga abbandonato.

| Nel secondo caso vogliamo che l'email arrivi non troppo dopo che l'acquisto sia stato completato ma senza neanche una particolare fretta, cercando un compromesso tra il dare il miglior servizio possibile all'utente e a non sovraccaricare troppo il nostro sistema.

| Nell'ultimo caso invece non abbiamo nessuna fretta, infatti che l'email venga inviata dopo 7 giorni esatti o dopo 7 giorni e 2 minuti cambia molto poco, l'utente non se ne accorgerà ed anche il business (in questa nostra ipotesi) non ne risentirà.

| Per attuare questa strategia possiamo innanzitutto `suddividere i jobs in diversi gruppi <jobs.html#raggrupare-i-job>`_, in questo specifico caso andremo quindi a creare 3 gruppi:

* `double-optin-email` (or `high-priority`)
* `confirmation-email` (or `generics`)
* `follow-up-email` (or `low-priority`)

| Una volta fatto ciò, andremo ad istruire i ``workers`` per prendersi carico solo di una determinata tipologia di gruppo. In questo modo, grazie al numero di ``workers`` presenti su ogni coda (gruppo) avremo velocità di evasione dei ``jobs`` differenti.
| Ad esempio supponiamo di voler abilitare sette workers, possiamo suddividerli in questa modalità:

* 1 worker che lavora sulla coda ``follow-up-email`` (or ``low-priority``)
* 2 worker che lavorano sulla coda ``confirmation-email`` (or ``generics``)
* 4 worker che lavorano sulla coda ``double-optin-email`` (or ``high-priority``)

| Cosi facendo verrà eseguito:

- un solo job facente parte del gruppo ``follow-up-email`` alla volta (Quindi nel caso in cui ci siano 2 jobs nel gruppo ``low-priority`` schedulati entrambi per lo stesso orario, il secondo verrà eseguito solo al termine del primo).
- due job facenti parti del gruppo ``confirmation-email`` in parallelo
- quattro job facenti parti del gruppo ``double-optin-email`` in parallelo

| In linea di massima possiamo affermare che più worker ci sono per una determinata coda (gruppo) e più quella coda verrà smaltita velocemente.

| Per limitare il lavoro di un worker ad uno specifico gruppo di jobs dovremo utilizzare l'ozione `work-on` al lancio del processo `worker`.
| Ad esempio:

.. code-block:: bash

   $ php vendor/bin/recruiter start:worker --work-on='double-optin-email' --target 127.0.0.1:27017 --bootstrap $APP_BASE_PATH/worker-boostrap.php
   $ php vendor/bin/recruiter start:worker --work-on='double-optin-email' --target 127.0.0.1:27017 --bootstrap $APP_BASE_PATH/worker-boostrap.php
   $ php vendor/bin/recruiter start:worker --work-on='double-optin-email' --target 127.0.0.1:27017 --bootstrap $APP_BASE_PATH/worker-boostrap.php
   $ php vendor/bin/recruiter start:worker --work-on='double-optin-email' --target 127.0.0.1:27017 --bootstrap $APP_BASE_PATH/worker-boostrap.php

   $ php vendor/bin/recruiter start:worker --work-on='confirmation-email' --target 127.0.0.1:27017 --bootstrap $APP_BASE_PATH/worker-boostrap.php
   $ php vendor/bin/recruiter start:worker --work-on='confirmation-email' --target 127.0.0.1:27017 --bootstrap $APP_BASE_PATH/worker-boostrap.php

   $ php vendor/bin/recruiter start:worker --work-on='follow-up-email' --target 127.0.0.1:27017 --bootstrap $APP_BASE_PATH/worker-boostrap.php
