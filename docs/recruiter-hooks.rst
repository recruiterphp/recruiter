Recruiter Hooks
=============================

| E' possibile definire, nel proprio progetto, delle ``hook functions`` che verranno richiamate dal `Recruiter` in determinati momenti/stati.

| Queste funzioni riceveranno come argomento un instanza del `Recruiter` e sono:

* recruiter_became_master
* recruiter_stept_back

=============================
recruiter_became_master
=============================
| Questa funzione verrà invocata dal processo recruiter nel momento in cui ottiene la leadership.

| Per maggiori informazioni su cosa significhi ottenere la leadership vedere il capitolo relativo a `Geezer <geezer.html>`_


.. code-block:: php

   <?php

   use Recruiter\Recruiter;

   function recruiter_became_master(Recruiter $recruiter): void
   {
      // Schedule a Recruiter\Repeatable job
   }

=============================
recruiter_stept_back
=============================
| Questa funzione verrà invocata dal processo recruiter che perde la leadership.

| Per maggiori informazioni su cosa significhi perdere la leadership vedere il capitolo relativo a `Geezer <geezer.html>`_

.. code-block:: php

   <?php

   use Recruiter\Recruiter;

   function recruiter_stept_back(Recruiter $recruiter): void
   {

   }
