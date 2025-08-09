.. _priority:

How to handle priority
=================================

| Let's suppose we have an e-commerce application that has these functionalities within it:

* sending a double-optin email message (for email address verification before purchase).
* sending a purchase confirmation email.
* sending a follow-up email to users who have not completed their purchase after 7 days.

| In all three cases it involves sending an email (we can therefore have just one type of ``Workable``), but all three have a different priority:

| In the first case we want the email to arrive as soon as possible, the user is in front of the screen waiting for that email and we want to give them the best possible service, also the later it arrives the more likely the purchase will be abandoned.

| In the second case we want the email to arrive not too long after the purchase has been completed but without any particular urgency, seeking a compromise between giving the best possible service to the user and not overloading our system too much.

| In the last case instead we have no hurry, in fact whether the email is sent after exactly 7 days or after 7 days and 2 minutes changes very little, the user will not notice and the business (in our hypothesis) will not suffer either.

| To implement this strategy we can first :ref:`divide jobs into different groups<jobs-grouping>`, in this specific case we will therefore create 3 groups:

* `double-optin-email` (or `high-priority`)
* `confirmation-email` (or `generics`)
* `follow-up-email` (or `low-priority`)

| Once this is done, we will instruct the ``workers`` to take charge of only a specific type of group. This way, thanks to the number of ``workers`` present on each queue (group) we will have different processing speeds for ``jobs``.
| For example, suppose we want to enable seven workers, we can divide them in this way:

* 1 worker that works on the ``follow-up-email`` (or ``low-priority``) queue
* 2 workers that work on the ``confirmation-email`` (or ``generics``) queue
* 4 workers that work on the ``double-optin-email`` (or ``high-priority``) queue

| By doing this, the following will be executed:

- only one job belonging to the ``follow-up-email`` group at a time (So in case there are 2 jobs in the ``low-priority`` group both scheduled for the same time, the second will be executed only after the first one is completed).
- two jobs belonging to the ``confirmation-email`` group in parallel
- four jobs belonging to the ``double-optin-email`` group in parallel

| Generally speaking, we can say that the more workers there are for a specific queue (group), the faster that queue will be processed.

| To limit a worker's work to a specific group of jobs we will need to use the **work-on** option when launching the `worker` process.
| For example:

.. code-block:: bash

   $ php vendor/bin/recruiter start:worker --work-on='double-optin-email' --target 127.0.0.1:27017 --bootstrap $APP_BASE_PATH/worker-boostrap.php
   $ php vendor/bin/recruiter start:worker --work-on='double-optin-email' --target 127.0.0.1:27017 --bootstrap $APP_BASE_PATH/worker-boostrap.php
   $ php vendor/bin/recruiter start:worker --work-on='double-optin-email' --target 127.0.0.1:27017 --bootstrap $APP_BASE_PATH/worker-boostrap.php
   $ php vendor/bin/recruiter start:worker --work-on='double-optin-email' --target 127.0.0.1:27017 --bootstrap $APP_BASE_PATH/worker-boostrap.php

   $ php vendor/bin/recruiter start:worker --work-on='confirmation-email' --target 127.0.0.1:27017 --bootstrap $APP_BASE_PATH/worker-boostrap.php
   $ php vendor/bin/recruiter start:worker --work-on='confirmation-email' --target 127.0.0.1:27017 --bootstrap $APP_BASE_PATH/worker-boostrap.php

   $ php vendor/bin/recruiter start:worker --work-on='follow-up-email' --target 127.0.0.1:27017 --bootstrap $APP_BASE_PATH/worker-boostrap.php
