.. _recruiter-hooks:

Recruiter Hooks
=============================

| It is possible to define, in your own project, ``hook functions`` that will be called by the `Recruiter` at specific moments/states.

| These functions will receive as argument an instance of the `Recruiter` and are:

* recruiter_became_master
* recruiter_stept_back

=============================
recruiter_became_master
=============================
| This function will be invoked by the recruiter process when it obtains leadership.

| For more information on what obtaining leadership means see the chapter on :ref:`Geezer<geezer>`


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
| This function will be invoked by the recruiter process that loses leadership.

| For more information on what losing leadership means see the chapter on :ref:`Geezer<geezer>`

.. code-block:: php

   <?php

   use Recruiter\Recruiter;

   function recruiter_stept_back(Recruiter $recruiter): void
   {

   }
