<?php
require_once 'config.php';
require_once 'functions.php';

class PerplexityAPI {

    // Queste proprietà sono accessibili solo all'interno della classe
    private $api_key;    // Memorizza la chiave API per Perplexity AI
    private $api_url;    // Memorizza l'URL dell'endpoint API

    // costruttore
    public function __construct() {
        // INIZIALIZZA LA CHIAVE API:
        // Verifica se la costante PERPLEXITY_API_KEY è definita nel file config.php
        // Se esiste, usa il suo valore, altrimenti usa stringa vuota (sistema disabilitato)
        $this->api_key = defined('PERPLEXITY_API_KEY') ? PERPLEXITY_API_KEY : '';

        // INIZIALIZZA L'URL API:
        $this->api_url = defined('PERPLEXITY_API_URL') ? PERPLEXITY_API_URL : '';
    }

    // metodo pubblico per gestire i messaggi dell'utente
    public function sendMessage($message) {
        // ANALIZZA IL MESSAGGIO PER CAPIRE IL TIPO DI DOMANDA
        // Restituisce array con informazioni sul prodotto richiesto, tipo domanda, ecc.
        $product_info = $this->analyzeQuestion($message);

        // PRIORITÀ 1: TENTA DI USARE L'API PERPLEXITY (AI AVANZATA)
        // Verifica se l'API è configurata (chiave e URL presenti)
        if ($this->api_key && $this->api_url) {
            // RECUPERA IL CONTESTO DAL DATABASE PER MIGLIORARE LA RISPOSTA AI
            // Se è una domanda su prodotti, cerca informazioni reali dal DB
            $db_context = $this->getDBContext($product_info);
            // CHIAMA L'API ESTERNA DI PERPLEXITY - Passa il messaggio, il contesto DB e le info prodotto
            $result = $this->callPerplexityAPI($message, $db_context, $product_info);
            // SE L'API HA RISPOSTO CON SUCCESSO, RESTITUISCI LA RISPOSTA
            if ($result['success']) return $result;
            // SE L'API HA FALLITO, REGISTRA L'ERRORE E PROCEDI CON FALLBACK
            error_log("Perplexity API fallita, uso fallback");
        }

        // PRIORITÀ 2: SE LA DOMANDA RIGUARDA UN PRODOTTO SPECIFICO
        // Verifica se l'analisi ha identificato una domanda su prodotti
        if ($product_info['is_product_question']) {
            // CERCA INFORMAZIONI SUL PRODOTTO NEL DATABASE
            // Se trova risultati, formatta una risposta
            if ($db_response = $this->getProductInfoFromDB($product_info)) {
                // RESTITUISCE RISPOSTA DAL DATABASE CON FLAG DI ORIGINE
                return [
                    'success' => true,
                    'response' => $db_response,
                    'from_db' => true  // Indica che la risposta viene dal DB
                ];
            }
        }

        // PRIORITÀ 3: SE LA DOMANDA RIGUARDA INFORMAZIONI GENERALI DEL NEGOZIO
        // Verifica se è una domanda su spedizioni, pagamenti, contatti, etc.
        if ($this->isStoreQuestion($message)) {
            //RESTITUISCE RISPOSTA PREDEFINITA PER DOMANDE SUL NEGOZIO
            return $this->getStoreInfoResponse($message);
        }

        // PRIORITÀ 4: FALLBACK GENERICO - SE NESSUN ALTRO METODO HA FUNZIONATO
        // Restituisce una risposta di default per domande non riconosciute
        return $this->getFallbackResponse($message, $product_info);
    }

    private function getDBContext($product_info) {

        // CONTROLLO INIZIALE: SE NON È UNA DOMANDA SU PRODOTTI, ESCI SUBITO
        // Ottimizzazione: evita query inutili al database
        if (!$product_info['is_product_question']) return '';

        $conn = getDBConnection();
        try {
            $stmt = $conn->prepare("
                SELECT a.nome, a.prezzo_vendita, af.prezzo_acquisto, 
                       af.quantita_disponibile, f.nome as nome_fornitore, f.giorni_spedizione
                FROM Articoli a
                LEFT JOIN Articoli_Fornitori af ON a.id_articolo = af.id_articolo
                LEFT JOIN Fornitori f ON af.id_fornitore = f.id_fornitore
                WHERE LOWER(a.nome) LIKE ? 
                ORDER BY af.prezzo_acquisto ASC LIMIT 3
            ");

            // % sono wildcard: cerca il testo in qualsiasi posizione nel nome
            $stmt->execute(['%' . strtolower($product_info['product_name']) . '%']);

            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // SE SONO STATI TROVATI PRODOTTI, COSTRUISCE IL CONTESTO
            if ($products) {
                //INIZIALIZZA LA STRINGA DI CONTESTO PER L'AI
                $context = "Prodotti disponibili:\n";

                // ITERA SU OGNI PRODOTTO TROVATO
                foreach ($products as $p) {
                    // AGGIUNGE INFORMAZIONI SUL PRODOTTO AL CONTESTO
                    // Formatta una riga per ogni prodotto con dati strutturati
                    $context .= "- {$p['nome']}: €{$p['prezzo_acquisto']}, ";
                    $context .= "Disponibili: {$p['quantita_disponibile']} pz, ";
                    $context .= "Fornitore: {$p['nome_fornitore']}, ";
                    $context .= "Spedizione: {$p['giorni_spedizione']} giorni\n";
                }

                return $context;
            }

        } catch (Exception $e) {
            // Non mostra l'errore all'utente per sicurezza
            error_log("Errore recupero contesto DB: " . $e->getMessage());
        }

        // SE ARRIVA QUI, QUALCOSA È ANDATO STORTO O NON CI SONO PRODOTTI
        return '';
    }
    private function callPerplexityAPI($message, $db_context, $product_info) {
        // Combina contesto DB e informazioni specifiche del prodotto
        $system_message = $this->buildSystemPrompt($product_info, $db_context);
        //PREPARA I DATI PER LA RICHIESTA A
        $data = [
            'model' => 'sonar',//modello AI
            'messages' => [
                // Definisce il ruolo e il comportamento dell'assistente
                ['role' => 'system', 'content' => $system_message],
                ['role' => 'user', 'content' => $message]// Contiene il testo inviato dall'utente
            ],
            'max_tokens' => 500,//massimi token
            'temperature' => 0.7,//creatività
            'top_p' => 0.9 // Permette una certa varietà nelle risposte
        ];

        $ch = curl_init();//permette di inviare richieste HTTP verso API o server esterni
        //CONFIGURA TUTTE LE OPZIONI CURL IN UN UNICO ARRAY
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->api_url,//URL ENDPOINT
            CURLOPT_POST => 1, //SPECIFICA CHE È UNA RICHIESTA POST
            CURLOPT_POSTFIELDS => json_encode($data), //CONVERTE I DATI IN JSON E LI IMPOSTA COME BODY
            CURLOPT_RETURNTRANSFER => true,//RESTITUISCE LA RISPOSTA COME STRINGA INVECE DI STAMPARLA
            CURLOPT_HTTPHEADER => [//IMPOSTA GLI HEADER HTTP DELLA RICHIESTA
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->api_key
            ],
            CURLOPT_TIMEOUT => 30,//TIMEOUT DI 30 SECONDI - DOPO SCADE LA RICHIESTA
            CURLOPT_SSL_VERIFYPEER => true//VERIFICA IL CERTIFICATO SSL PER SICUREZZA
        ]);

        $response = curl_exec($ch);// ESEGUE LA CHIAMATA HTTP E OTTIENE LA RISPOSTA
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);//RECUPERA IL CODICE DI STATO HTTP DELLA RISPOSTA
        curl_close($ch);
        // VERIFICA SE LA RICHIESTA È ANDATA A BUON FINE
        if ($http_code === 200 && $response) {
            $result = json_decode($response, true);
            if (isset($result['choices'][0]['message']['content'])) {
                //RESTITUISCE SUCCESSO CON LA RISPOSTA DELL'AI
                return [
                    'success' => true,
                    'response' => $result['choices'][0]['message']['content'],
                    'from_ai' => true
                ];
            }
        }
        return ['success' => false, 'response' => ''];
    }

    private function buildSystemPrompt($product_info, $db_context) {
        //INIZIALIZZA IL PROMPT BASE CON INFORMAZIONI FONDAMENTALI
        $prompt = "Sei un assistente per ShopOnline, e-commerce italiano di elettronica.
    
COMPORTAMENTO:
- Rispondi SEMPRE in italiano
- Tono cordiale e professionale
- Risposte concise (max 150 parole)
- Usa i dati dal database se disponibili

CATALOGO: Elettronica, computer, periferiche

INFORMAZIONI NEGOZIO:
- Spedizione: 2-5 giorni (gratis sopra €50)
- Pagamenti: carta, PayPal, bonifico
- Assistenza: Lun-Ven 9-18, Tel: +39 02 1234 5678";

        // SE È DISPONIBILE CONTESTO DATABASE, LO AGGIUNGE AL PROMPT
        // $db_context contiene informazioni reali su prodotti e disponibilità
        if ($db_context) {
            $prompt .= "\n\nDATI PRODOTTI ATTUALI:\n" . $db_context;
            $prompt .= "\nUsa questi dati reali per rispondere con precisione.";
        }
        // SE LA DOMANDA RIGUARDA UN PRODOTTO SPECIFICO, LO SEGNALA ALL'AI
        if ($product_info['is_product_question']) {
            $prompt .= "\n\nL'utente chiede informazioni su: " . $product_info['product_name'];
        }

        return $prompt;
    }

    private function analyzeQuestion($message) {
        $message_lower = strtolower($message);
        $result = [
            'is_product_question' => false,  // Flag: la domanda riguarda prodotti?
            'product_name' => '',            // Nome del prodotto identificato
            'question_type' => '',           // Tipo di domanda (prezzo, spedizione, etc.)
            'quantity' => 1                  // Quantità richiesta (default 1)
        ];
        // DEFINISCE LA LISTA DEI PRODOTTI NEL CATALOGO DA RICONOSCERE
        $products = ['mouse', 'monitor', 'tastiera', 'stampante', 'webcam', 'ssd', 'hard disk', 'ram', 'cpu', 'scheda video'];

        //CERCA SE IL MESSAGGIO CONTIENE UNO DEI PRODOTTI DEL CATALOGO
        foreach ($products as $product) {
            // VERIFICA SE IL NOME PRODOTTO È PRESENTE NEL MESSAGGIO
            if (strpos($message_lower, $product) !== false) {// strpos() restituisce la posizione
                //SE TROVATO, IMPOSTA I FLAG CORRISPONDENTI
                $result['is_product_question'] = true;  // Domanda su prodotto
                $result['product_name'] = $product;     // Salva il prodotto trovato
                break;
            }
        }

        // ANALIZZA IL TIPO DI DOMANDA IN BASE A PAROLE CHIAVE
        // Verifica se la domanda riguarda i PREZZI
        if (strpos($message_lower, 'prezzo') !== false || strpos($message_lower, 'costa') !== false) {
            $result['question_type'] = 'prezzo';
        }
        // 10. Verifica se la domanda riguarda le SPEDIZIONI
        elseif (strpos($message_lower, 'spedizione') !== false || strpos($message_lower, 'tempo') !== false) {
            $result['question_type'] = 'spedizione';
        }
        // 11. Verifica se la domanda riguarda SCONTI
        elseif (strpos($message_lower, 'sconto') !== false) {
            $result['question_type'] = 'sconto';
        }
        //SE TROVATO UN NUMERO, LO CONVERTE IN INTERO E LO SALVA
        preg_match('/\b(\d+)\b/', $message, $matches);
        if ($matches) $result['quantity'] = intval($matches[1]);

        return $result;
    }

    private function isStoreQuestion($message) {
        $message_lower = strtolower($message);
        // DEFINISCE L'ARRAY DI PAROLE CHIAVE CHE IDENTIFICANO DOMANDE SUL NEGOZIO
        $store_keywords = ['spedizione', 'consegna', 'pagamento', 'orari', 'contatti', 'telefono', 'email', 'garanzia'];

        foreach ($store_keywords as $keyword) {
            //SE TROVA UNA PAROLA CHIAVE, RESTITUISCE IMMEDIATAMENTE TRUE
            if (strpos($message_lower, $keyword) !== false) return true;
        }
        return false;
    }

    private function getStoreInfoResponse($message) {
        $message_lower = strtolower($message);
        // SE LA DOMANDA RIGUARDA LE SPEDIZIONI
        if (strpos($message_lower, 'spedizione') !== false) {
            // RESTITUISCE RISPOSTA STRUTTURATA SULLE SPEDIZIONI
            return [
                'success' => true,  // Flag di successo
                'response' => "🚚 **Spedizione:**\n• Standard: 2-5 giorni\n• Express: 24-48 ore (+€9.90)\n• Gratis sopra €50"
            ];
        }

        // SE LA DOMANDA RIGUARDA I PAGAMENTI
        if (strpos($message_lower, 'pagamento') !== false) {
            return [
                'success' => true,
                'response' => "💳 **Pagamenti:**\n• Carte: Visa, MasterCard\n• PayPal\n• Bonifico\n• Contrassegno (+€3.90)"
            ];
        }

        // SE LA DOMANDA RIGUARDA I CONTATTI
        if (strpos($message_lower, 'contatti') !== false) {
            return [
                'success' => true,
                'response' => "📞 **Contatti:**\n• Tel: +39 02 1234 5678\n• Email: info@shoponline.com\n• Orari: Lun-Ven 9:00-18:00"
            ];
        }

        // SE NESSUNA PAROLA CHIAVE SPECIFICA È STATA TROVATA
        // Restituisce una risposta generica di benvenuto
        return [
            'success' => true,
            'response' => "🏪 **ShopOnline** - E-commerce di elettronica\n\nPosso aiutarti con:\n• Prodotti e prezzi\n• Spedizioni\n• Pagamenti\n• Assistenza"
        ];
    }

    private function getProductInfoFromDB($product_info) {
        $conn = getDBConnection();
        try {//query per prendere info del prodotto
            $stmt = $conn->prepare("
                SELECT a.nome, a.prezzo_vendita, af.prezzo_acquisto, 
                       af.quantita_disponibile, f.nome as nome_fornitore, f.giorni_spedizione
                FROM Articoli a
                LEFT JOIN Articoli_Fornitori af ON a.id_articolo = af.id_articolo
                LEFT JOIN Fornitori f ON af.id_fornitore = f.id_fornitore
                WHERE LOWER(a.nome) LIKE ? 
                ORDER BY af.prezzo_acquisto ASC LIMIT 3
            ");
            //esegue query, % sono wildcard: cerca il testo in qualsiasi posizione nel nome
            $stmt->execute(['%' . strtolower($product_info['product_name']) . '%']);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            //se $products non è vuoto, formatta, altrimenti null
            return $products ? $this->formatProductResponse($products, $product_info) : null;

        } catch (Exception $e) {
            error_log("Errore ricerca prodotti: " . $e->getMessage());
            return null;
        }
    }

    // METODO PRIVATO PER FORMATTARE LA RISPOSTA DEI PRODOTTI IN MODO LEGGIBILE
    // Trasforma dati database grezzi in risposte strutturate per l'utente
    private function formatProductResponse($products, $product_info) {
        $response = "";
        // RECUPERA LA QUANTITÀ RICHIESTA DALL'UTENTE (default 1)
        $quantity = $product_info['quantity'];

        // SWITCH SUL TIPO DI DOMANDA PER PERSONALIZZARE LA RISPOSTA
        switch ($product_info['question_type']) {

            // DOMANDA SUI PREZZI
            case 'prezzo':
                // 6. INTESTAZIONE PER DOMANDE DI PREZZO
                $response = "🔍 **Prezzi " . $product_info['product_name'] . ":**\n\n";
                //ITERA SU OGNI PRODOTTO TROVATO
                foreach ($products as $product) {
                    // CALCOLA IL PREZZO TOTALE (prezzo unitario × quantità)
                    $prezzo_totale = $product['prezzo_acquisto'] * $quantity;

                    //CALCOLA LO SCONTO APPLICABILE IN BASE A QUANTITÀ E VALORE
                    $sconto = calcolaSconto($product['id_fornitore'] ?? 0, $quantity, $prezzo_totale);
                    //CALCOLA IL PREZZO FINALE APPLICANDO LO SCONTO
                    $prezzo_finale = $prezzo_totale * (1 - $sconto/100);
                    // 11. AGGIUNGE IL NOME DEL PRODOTTO IN GRASSETTO
                    $response .= "🛒 **" . $product['nome'] . "**\n";

                    //AGGIUNGE IL PREZZO UNITARIO FORMATTATO
                    $response .= "• €" . number_format($product['prezzo_acquisto'], 2) . "/pz";
                    // SE LA QUANTITÀ > 1, AGGIUNGE IL TOTALE E LO SCONTO
                    if ($quantity > 1) $response .= " × {$quantity} = €" . number_format($prezzo_finale, 2);
                    // SE C'È SCONTO, LO MOSTRA IN PERCENTUALE
                    if ($sconto > 0) $response .= " (sconto {$sconto}%)";

                    // AGGIUNGE IL NOME DEL FORNITORE (con fallback 'N/A')
                    $response .= "\n• Fornitore: " . ($product['nome_fornitore'] ?? 'N/A') . "\n\n";
                }
                break;

            //  DOMANDA SULLA SPEDIZIONE
            case 'spedizione':
                // INTESTAZIONE PER DOMANDE DI SPEDIZIONE
                $response = "🚚 **Spedizione " . $product_info['product_name'] . ":**\n\n";

                // ITERA SU OGNI PRODOTTO
                foreach ($products as $product) {
                    // AGGIUNGE IL NOME DEL PRODOTTO
                    $response .= "📦 **" . $product['nome'] . "**\n";

                    // AGGIUNGE I TEMPI DI SPEDIZIONE (con fallback 'N/A')
                    $response .= "• Tempo: " . ($product['giorni_spedizione'] ?? 'N/A') . " giorni\n\n";
                }
                break;

            // DOMANDA GENERICA SUI PRODOTTI
            default:
                // INTESTAZIONE GENERICA
                $response = "🔍 **Prodotti trovati:**\n\n";
                //ITERA SU OGNI PRODOTTO
                foreach ($products as $product) {
                    // AGGIUNGE NOME E PREZZO BASE
                    $response .= "🛒 **" . $product['nome'] . "**\n";
                    $response .= "• Prezzo: €" . number_format($product['prezzo_acquisto'], 2) . "\n\n";
                }
        }

        return $response;
    }

    //METODO PRIVATO PER LA RISPOSTA DI FALLBACK (ULTIMA PRIORITÀ)
    private function getFallbackResponse($message, $product_info) {
        //SE LA DOMANDA RIGUARDA UN PRODOTTO SPECIFICO MA NON È STATO TROVATO
        if ($product_info['is_product_question']) {
            return [
                'success' => true,
                'response' => "🔍 Non ho trovato '" . $product_info['product_name'] . "' nel catalogo.\n\n💡 Prova a cercare nella barra di ricerca"
            ];
        }
        //SE LA DOMANDA È GENERICA O NON RICONOSCIUTA
        return [
            'success' => true,
            'response' => "ℹ️ **Assistente ShopOnline**\n\nPosso aiutarti con:\n• Prezzi e prodotti\n• Spedizioni\n• Assistenza"
        ];
    }
}
//FUNIONE SALVATAGGIO CHAT
/*function salvaConversazione($id_utente, $sessione_id, $messaggio_utente, $risposta_bot) {
    $conn = getDBConnection();
    try {
        $stmt = $conn->prepare("INSERT INTO ChatConversations (id_utente, sessione_id, messaggio_utente, risposta_bot) VALUES (?, ?, ?, ?)");
        $stmt->execute([$id_utente, $sessione_id, $messaggio_utente, $risposta_bot]);
        return true;
    } catch (Exception $e) {
        error_log("Errore salvataggio conversazione: " . $e->getMessage());
        return false;
    }
}*/

/*function getCronologiaConversazione($sessione_id, $limit = 10) {
    $conn = getDBConnection();
    try {
        $stmt = $conn->prepare("SELECT messaggio_utente, risposta_bot, timestamp FROM ChatConversations WHERE sessione_id = ? ORDER BY timestamp DESC LIMIT ?");
        $stmt->execute([$sessione_id, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Errore caricamento cronologia: " . $e->getMessage());
        return [];
    }
}*/
?>