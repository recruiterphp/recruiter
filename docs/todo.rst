Documentation to write:
========================

.. role:: strike
    :class: strike

- :strike:`dependencies`
- why it exists
- :strike:`what problems it solves` is it sufficient to say that it allows background routine execution?
- structure
   - :strike:`what is recruiter`
   - :strike:`what is a worker`
   - :strike:`what is the cleaner`
- setup
   - :strike:`installation`
   - :strike:`launching recruiter`
   - :strike:`launching workers`
   - :strike:`launching cleaners`

- examples
   - :strike:`hello world: single task that must be completed`
   - :strike:`without retry policy (hello world)`
   - :strike:`schedule immediately`
   - :strike:`schedule in the future`
   - :strike:`with simple retry policy`
   - :strike:`retryable exceptions`
   - :strike:`complex retry policies (exponential backoff)`
   - :strike:`optimistic task`

- deep dives
   - :strike:`how to implement a workable`
   - :strike:`how to implement a retry policy` (not detailed, but one can look at the implementation of one of the many existing ones to understand how to do it...)
   - :strike:`tags`
   - :strike:`recruiter statistics`
   - :strike:`hooks`
   - :strike:`repeatable jobs`
   - :strike:`working-on (priority queues)`
   - :strike:`collections (what archived is for, etc.)`
   - :strike:`how to put a job back in queue`
   - :strike:`execution in tests`
   - :strike:`inject the logger`
   - geezer
