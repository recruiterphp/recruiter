Documentazione da scrivere:
============================

.. role:: strike
    :class: strike

- perché esiste
- che problemi risolve
- struttura
   - cos'é recruiter
   - cos'é un worker
      * group
   - cos'é il cleaner
- setup
   - :strike:`installazione`
   - lanciare recruiter
   - lanciare workers
   - lanciare cleaners

- esempi
   - :strike:`hello world: task singolo che deve essere completato`
   - :strike:`senza retry policy`
   - :strike:`schedule subito`
   - :strike:`schedule nel futuro`
   - :strike:`con retry policy semplice`
   - :strike:`retryable exceptions`
   - retry policy complesse (exponential backoff)
   - :strike:`task ottimistico`

   - esecuzione nei test
   - repeatable jobs
   - iniettare il logger
   - :strike:`working-on`


- approfondimenti
   - come si implementa una workable
   - workable esistenti
   - come si implementa una retry policy
   - tags
   - recruiter statistiche
   - come rimettere un job in coda
   - collezioni ( a che serve archived, etc.)
   - how to debug
