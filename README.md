# ShopOnline - Sistema di Gestione Acquisti Intelligente

## Panoramica del Progetto

ShopOnline è una piattaforma e-commerce avanzata che rivoluziona l'esperienza d'acquisto online attraverso un sistema intelligente di confronto prezzi tra multipli fornitori. La piattaforma combina tecnologie moderne con un'interfaccia utente intuitiva per offrire un servizio completo di acquisto business to business e business to consumer.

## Architettura del Sistema

### Stack Tecnologico
Il sistema è costruito utilizzando PHP in stile procedurale per garantire prestazioni ottimali e facilità di manutenzione. Il database MySQL gestisce tutte le operazioni dati, mentre il frontend utilizza tecnologie web standard come HTML5, CSS3 e JavaScript vanilla per una compatibilità universale.

### Modello Dati
L'architettura del database è progettata per gestire relazioni complesse tra utenti, prodotti, fornitori e ordini. Il sistema utilizza un modello normalizzato che garantisce integrità dei dati e prestazioni scalabili, con tabelle separate per la gestione degli sconti, metodi di pagamento e cronologia delle conversazioni.

## Caratteristiche Principali

### Sistema di Ricerca Intelligente
Il cuore della piattaforma è il motore di ricerca che analizza in tempo reale la disponibilità e i prezzi tra tutti i fornitori collegati. Per ogni prodotto ricercato, il sistema identifica automaticamente il fornitore più conveniente.

### Gestione Sconti Multi-Livello
ShopOnline implementa un sofisticato sistema di calcolo sconti che considera tre dimensioni diverse:
- Sconti per quantità (acquisti all'ingrosso)
- Sconti per valore totale dell'ordine
- Sconti stagionali e promozionali periodici

Il sistema seleziona automaticamente la combinazione di sconti più vantaggiosa per l'utente finale.

### Esperienza Utente Personalizzata
La piattaforma adatta dinamicamente l'interfaccia in base allo stato di autenticazione dell'utente. Gli utenti non registrati possono esplorare prodotti e prezzi, mentre gli utenti registrati accedono a funzionalità avanzate come il salvataggio permanente del carrello, lo storico ordini e la gestione dei metodi di pagamento.

### Sistema Carrello Avanzato
Il carrello della spesa offre un'esperienza seamless con salvataggio automatico sia in sessione che nel database. Gli utenti possono modificare quantità, visualizzare il totale aggiornato in tempo reale e procedere all'acquisto con pochi click.

## Sicurezza e Affidabilità

### Protezione Dati
Il sistema implementa multiple layer di sicurezza incluse la cifratura delle password con algoritmo bcrypt, protezione contro attacchi SQL injection attraverso prepared statements, e sanitizzazione di tutti gli input utente per prevenire cross-site scripting.

### Gestione Transazioni
Tutte le operazioni finanziarie e di modifica inventario sono gestite attraverso transazioni atomiche che garantiscono la consistenza dei dati anche in caso di errori di sistema.

## Integrazione Intelligenza Artificiale

### Assistente Virtuale
ShopOnline integra un assistente AI basato su Perplexity API che fornisce supporto clienti 24/7. L'assistente è in grado di:
- Rispondere a domande su prodotti e prezzi
- Fornire informazioni su spedizioni e politiche del negozio
- Guidare gli utenti attraverso il processo d'acquisto
- Offrire alternative di prodotto basate sul catalogo disponibile

### Pipeline di Risposta Intelligente
Il sistema utilizza una strategia a livelli per le risposte dell'assistente, partendo dall'API esterna e degradando elegantemente verso risposte predefinite basate sul database interno quando necessario.

## Performance e Scalabilità

### Ottimizzazioni Implementate
La piattaforma è ottimizzata per gestire carichi elevati attraverso:
- Query database ottimizzate con indici strategici
- Gestione efficiente delle sessioni utente
- Caricamento lazy delle immagini e contenuti
- Architettura modulare che facilita l'aggiunta di nuove funzionalità

### Metriche di Performance
Il sistema è progettato per mantenere tempi di risposta inferiori ai 200 millisecondi per le ricerche e caricamento pagine completo entro 2 secondi, supportando contemporaneamente centinaia di utenti concorrenti.

## Processo d'Acquisto

### Flusso Completo
L'utente segue un percorso guidato che parte dalla ricerca prodotti, passa attraverso la selezione delle quantità/scelta prodotto e il confronto fornitori, per arrivare al checkout sicuro con multiple opzioni di pagamento. Il sistema gestisce automaticamente la verifica disponibilità e l'aggiornamento dell'inventario.

### Multi-Modalità di Pagamento
La piattaforma supporta carte di credito, PayPal e bonifici bancari, con gestione sicura dei dati sensibili attraverso tokenizzazione e cifratura.

### Sistema di Logging
Tutte le attività significative sono tracciate in log strutturati che facilitano il debugging, l'analisi delle performance e la sicurezza del sistema.

## Compatibilità e Accessibilità

### Design Responsive
L'interfaccia è progettata con approccio mobile first, garantendo un'esperienza ottimale su dispositivi mobili, tablet e desktop. Il design è accessibile e conforme agli standard web moderni.

### Supporto Browser
La piattaforma è compatibile con tutte le versioni recenti dei principali browser web, inclusi Chrome, Firefox, Safari e Edge.

## Estensibilità e Sviluppo Futuro

### Architettura Modulare
La struttura del codice facilita l'aggiunta di nuove funzionalità senza impattare il sistema esistente. 

## Installazione e Deployment

### Requisiti di Sistema
Per il deployment sono richiesti server web Apache o Nginx con supporto PHP 8.0+, database MySQL 5.7+, e configurazioni di sicurezza standard per ambienti production.

## Supporto e Manutenzione

La piattaforma include strumenti completi per il monitoring adella salute del sistema e documentazione tecnica esaustiva per sviluppatori e amministratori di sistema.

# Link utili
Analisi TDD/BDD: https://1drv.ms/w/c/6388146e8998ea37/EeTdq6jOC01JmaTNrGXKwFUBtOvAWouezlDUN0C0bPkpdw?e=H1H8fs

