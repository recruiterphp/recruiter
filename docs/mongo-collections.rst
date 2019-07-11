Mongo Collections
===============================

| Come abbiamo già visto, la libreria `recruiter` si appoggia a `Mongodb` per la storicizzazione dei dati.
| Vediamo a grandi linee la struttura utilizzata in modo da possedere una conoscenza di massima che vi renderà più facile le indagini in caso di comportamenti anomali.

.. _roster-collection:

============================
"roster" collection
============================
| La collezione **roster** contiene i dati relativi ai vari `worker` in esecuzione.

| É grazie a questa collezione che il processo `recruiter` conosce quali worker sono presenti e quali di questi sono disponibili a prendere in carico un nuovo `job`, ed é sempre in questa collezione che il processo `recruiter` utilizza per memorizzare quale `job` é stato assegnato a quale `worker`. In questo modo ogni processo `worker` legge ripetutamente (polling) il proprio documento così da individuare quale sarà il prossimo `job` da eseguire.

| Ogni processo `worker` registra all'avvio i propri dati in un documento di questa collezione, questo documento viene rimosso durante la fase di shutdown del worker.

| Ogni processo `worker` aggiorna questo documento periodicamente con la data corrente, in maniera tale da rendere esplicito il fatto di essere ancora "vivo".

| Grazie a questa data, il recruiter può capire che il `worker` non é più online, potendo rimuovere il documento relativo al `worker` morto ed evitando cosi di assegnargli dei lavori da eseguire.

.. _scheduled-collection:

============================
"scheduled" collection
============================
| La collezione **scheduled** contiene i vari `jobs` da eseguire.

| Il processo recruiter legge periodicamente (polling) questa collezione in modo da individuare quali `jobs` vanno eseguiti, in base alla loro data di schedulazione.

| Nel caso in cui un `job` venga eseguito senza successo, la data di schedulazione verrà aggiornata in relazione alla proprio politica di retry, in caso del raggiungimento del numero massimo di retry il documento verrà spostato nella collezione **archived**


.. _archived-collection:

============================
"archived" collection
============================
| La collezione **archived** contiene lo storico dei vari `jobs` eseguiti.

| Un `job` viene spostato dalla collezione **scheduled** alla collezione **archived** nel caso in cui venga eseguito e completato con successo, oppure nel caso in cui l'esecuzione fallisca ed é stato raggiunto il massimo numero di tentativi di esecuzione.
| Il processo `cleaner` si occupa di mantenere ridotte le dimensioni di questa collezione, cancellando i `jobs` più vecchi di 5 giorni (default).
| É possibile modificare questa finestra temporale tramite l'opzione **clean-after** del processo `cleaner`.

.. _schedulers-collection:

============================
"schedulers" collection
============================
| La collezione **schedulers** contiente un template dei `job` che devono essere eseguiti periodicamente.

| Il processo `recruiter` legge periodicamente (polling) questa collezione in modo da creare e schedulare dei nuovi `job` da aggiungere alla collezione `scheduled`.
