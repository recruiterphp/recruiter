Documentazione da scrivere:
============================

.. role:: strike
    :class: strike

- :strike:`dipendenze`
- perché esiste
- :strike:`che problemi risolve` é sufficiente dire che permette l'esecuzione di routine in background?
- struttura
   - :strike:`cos'é recruiter`
   - :strike:`cos'é un worker`
   - :strike:`cos'é il cleaner`
- setup
   - :strike:`installazione`
   - :strike:`lanciare recruiter`
   - :strike:`lanciare workers`
   - :strike:`lanciare cleaners`

- esempi
   - :strike:`hello world: task singolo che deve essere completato`
   - :strike:`senza retry policy (hello world)`
   - :strike:`schedule subito`
   - :strike:`schedule nel futuro`
   - :strike:`con retry policy semplice`
   - :strike:`retryable exceptions`
   - :strike:`retry policy complesse (exponential backoff)`
   - :strike:`task ottimistico`

- approfondimenti
   - :strike:`come si implementa una workable`
   - :strike:`come si implementa una retry policy` (non é dettagliato, ma uno si può guardare l'implementazione di una delle tante esistenti per capire come fare...)
   - :strike:`tags`
   - :strike:`recruiter statistiche`
   - come rimettere un job in coda
   - collezioni ( a che serve archived, etc.)
   - how to debug
   - :strike:`hooks`
   - gracefull shutdown
   - esecuzione nei test
   - :strike:`repeatable jobs`
   - iniettare il logger
   - :strike:`working-on (priority queues)`
   - geezer
