Administration console
===========================

| La libreria fornisce alcuni comandi da console per la gestione del recruiter:

===========================
Recovering a job
===========================

| Nel caso in cui un volessimo rieseguire un `job` che si trova in :ref:`archivio<archived-collection>` possiamo farlo tramite il comando console **job:recover**

| Per poter eseguire questo comando é necessario conoscere l'id (MongoId del documento) del `job` che si vuole ripristinare, da passare come argomento del comando.

| É possibile specificare la nuova data di schedulazione tramite l'opzione **scheduleAt**, altrimenti il job verrà rischedulato per l'esecuzione nella data corrente.

.. code-block:: bash

   $ php vendor/bin/recruiter job:recover --target mongodb://localhost:27017/recruiter --scheduleAt "2019-12-01T22:18:00Z" 5d27436e2bacd566a67e85e4

===========================
Analytics
===========================

| É possibile visulizzare le :ref:`statistiche<analytics-page>` anche in console tramite il comando: **bko:analytics**

| É possibile specificare l'uri del server mongo al quale connettersi tramite l'opzione ``target``.

| É possibile limitare le statistiche ad un solo gruppo di job tramite l'opzione ``group``.

.. code-block:: bash

   $ php vendor/bin/recruiter bko:analytics --target mongodb://localhost:27017/recruiter --group html
