<?php
// perplexity_api.php - Versione migliorata
require_once 'config.php';
require_once 'functions.php';

class PerplexityAPI {
    private $api_key;
    private $api_url;

    public function __construct() {
        $this->api_key = defined('PERPLEXITY_API_KEY') ? PERPLEXITY_API_KEY : '';
        $this->api_url = defined('PERPLEXITY_API_URL') ? PERPLEXITY_API_URL : '';
    }

    public function sendMessage($message, $context = '') {
        // Analizza sempre la domanda
        $product_info = $this->analyzeQuestion($message);

        // Se è una domanda sui prodotti, cerca nel database
        if ($product_info['is_product_question']) {
            $db_response = $this->getProductInfoFromDB($product_info);
            if ($db_response) {
                return [
                    'success' => true,
                    'response' => $db_response,
                    'from_db' => true
                ];
            }
        }

        // Se è una domanda generica sul negozio
        if ($this->isStoreQuestion($message)) {
            return $this->getStoreInfoResponse($message);
        }

        // Se Perplexity è configurato, usalo
        if (!empty($this->api_key)) {
            $result = $this->callPerplexityAPI($message, $context, $product_info);
            if ($result['success']) {
                return $result;
            }
        }

        // Fallback: risposta generica
        return $this->getFallbackResponse($message, $product_info);
    }

    private function isStoreQuestion($message) {
        $message_lower = strtolower($message);
        $store_keywords = [
            'spedizione', 'consegna', 'tempo', 'arriva', 'costano', 'prezzi',
            'orari', 'aperto', 'chiuso', 'pagamento', 'carta', 'paypal',
            'reso', 'rimborso', 'garanzia', 'assistenza', 'clienti',
            'negozio', 'dove', 'indirizzo', 'contatti', 'telefono', 'email'
        ];

        foreach ($store_keywords as $keyword) {
            if (strpos($message_lower, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }

    private function getStoreInfoResponse($message) {
        $message_lower = strtolower($message);

        if (strpos($message_lower, 'spedizione') !== false ||
            strpos($message_lower, 'consegna') !== false ||
            strpos($message_lower, 'tempo') !== false) {
            return [
                'success' => true,
                'response' => "🚚 **Informazioni Spedizione:**\n\n• Tempi medi: 2-5 giorni lavorativi\n• Spedizione express: 24-48 ore (+€9.90)\n• Ritiro in negozio: disponibile\n• Spedizione gratuita sopra €50\n\nPer informazioni specifiche su un prodotto, chiedimi direttamente!"
            ];
        }

        if (strpos($message_lower, 'pagamento') !== false) {
            return [
                'success' => true,
                'response' => "💳 **Metodi di Pagamento:**\n\n• Carta di Credito (Visa, MasterCard)\n• PayPal\n• Bonifico Bancario\n• Contrassegno (+€3.90)\n• Criptovalute (Bitcoin, Ethereum)"
            ];
        }

        if (strpos($message_lower, 'orari') !== false ||
            strpos($message_lower, 'aperto') !== false) {
            return [
                'success' => true,
                'response' => "🕒 **Orari Negozio:**\n\n• Lun-Ven: 9:00-18:00\n• Sabato: 9:00-13:00\n• Domenica: Chiuso\n• Assistenza telefono: Lun-Sab 8:00-20:00"
            ];
        }

        if (strpos($message_lower, 'contatti') !== false ||
            strpos($message_lower, 'telefono') !== false ||
            strpos($message_lower, 'email') !== false) {
            return [
                'success' => true,
                'response' => "📞 **Contatti:**\n\n• Telefono: +39 02 1234 5678\n• Email: info@shoponline.com\n• Indirizzo: Via Roma 123, Milano\n• Assistenza Clienti: Lun-Ven 9:00-18:00"
            ];
        }

        // Risposta generica per domande sul negozio
        return [
            'success' => true,
            'response' => "🏪 **Informazioni ShopOnline:**\n\nSiamo un e-commerce specializzato in elettronica e informatica. Offriamo i migliori prezzi confrontando multiple forniture.\n\nPosso aiutarti con:\n• Informazioni prodotti e prezzi\n• Tempi di spedizione\n• Metodi di pagamento\n• Contatti e assistenza\n\nChiedimi pure cosa ti serve! 😊"
        ];
    }

    private function getFallbackResponse($message, $product_info) {
        if ($product_info['is_product_question']) {
            return [
                'success' => true,
                'response' => "🔍 Per '" . $product_info['product_name'] . "' non ho trovato prodotti specifici nel catalogo attuale.\n\n💡 **Suggerimenti:**\n• Verifica l'ortografia\n• Cerca direttamente nella barra di ricerca\n• Contatta il servizio clienti per prodotti speciali\n\nPosso aiutarti con altri prodotti elettronici! 🛒"
            ];
        }

        return [
            'success' => true,
            'response' => "ℹ️ **Assistente ShopOnline**\n\nSono specializzato in informazioni sui prodotti elettronici e sul negozio.\n\nPosso aiutarti con:\n• Prezzi e disponibilità prodotti\n• Informazioni spedizione\n• Contatti negozio\n• Categorie prodotti\n\nProva a chiedermi informazioni su monitor, mouse, tastiere, stampanti o altri prodotti! 😊"
        ];
    }

    private function callPerplexityAPI($message, $context, $product_info) {
        $system_message = $this->buildSystemPrompt($product_info);

        $data = [
            'model' => 'sonar-medium-online',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $system_message
                ],
                [
                    'role' => 'user',
                    'content' => $message
                ]
            ],
            'max_tokens' => 500,
            'temperature' => 0.2,
            'top_p' => 0.9
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->api_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->api_key
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            $result = json_decode($response, true);
            $content = $result['choices'][0]['message']['content'] ?? 'Mi dispiace, non ho capito la domanda.';

            return [
                'success' => true,
                'response' => $content
            ];
        } else {
            error_log("Perplexity API Error: HTTP $http_code");
            return [
                'success' => false,
                'response' => ''
            ]; // Ritorna vuoto per usare il fallback
        }
    }

    private function analyzeQuestion($message) {
        $message_lower = strtolower($message);

        $result = [
            'is_product_question' => false,
            'product_name' => '',
            'question_type' => '',
            'quantity' => 1
        ];

        $products = [
            'mouse', 'monitor', 'tastiera', 'stampante', 'webcam',
            'ssd', 'hard disk', 'ram', 'cpu', 'scheda video',
            'cuffie', 'router', 'alimentatore', 'case'
        ];

        foreach ($products as $product) {
            if (strpos($message_lower, $product) !== false) {
                $result['is_product_question'] = true;
                $result['product_name'] = $product;
                break;
            }
        }

        if (strpos($message_lower, 'prezzo') !== false ||
            strpos($message_lower, 'costa') !== false ||
            strpos($message_lower, 'quanto') !== false) {
            $result['question_type'] = 'prezzo';
        }

        if (strpos($message_lower, 'sconto') !== false) {
            $result['question_type'] = 'sconto';
        }

        if (strpos($message_lower, 'spedizione') !== false ||
            strpos($message_lower, 'tempo') !== false ||
            strpos($message_lower, 'arriva') !== false) {
            $result['question_type'] = 'spedizione';
        }

        preg_match('/\b(\d+)\b/', $message, $matches);
        if ($matches) {
            $result['quantity'] = intval($matches[1]);
        }

        return $result;
    }

    private function getProductInfoFromDB($product_info) {
        $conn = getDBConnection();

        try {
            $stmt = $conn->prepare("
                SELECT a.id_articolo, a.nome, a.prezzo_vendita, a.descrizione,
                       af.prezzo_acquisto, af.quantita_disponibile,
                       f.nome as nome_fornitore, f.giorni_spedizione
                FROM Articoli a
                LEFT JOIN Articoli_Fornitori af ON a.id_articolo = af.id_articolo
                LEFT JOIN Fornitori f ON af.id_fornitore = f.id_fornitore
                WHERE LOWER(a.nome) LIKE ? 
                ORDER BY af.prezzo_acquisto ASC
                LIMIT 3
            ");

            $search_term = '%' . strtolower($product_info['product_name']) . '%';
            $stmt->execute([$search_term]);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($products)) {
                return null;
            }

            return $this->formatProductResponse($products, $product_info);

        } catch (Exception $e) {
            error_log("Errore ricerca prodotti: " . $e->getMessage());
            return null;
        }
    }

    private function formatProductResponse($products, $product_info) {
        $response = "";
        $quantity = $product_info['quantity'];

        switch ($product_info['question_type']) {
            case 'prezzo':
                $response = "🔍 **Informazioni prezzi per " . $product_info['product_name'] . ":**\n\n";
                foreach ($products as $product) {
                    $prezzo_totale = $product['prezzo_acquisto'] * $quantity;
                    $sconto = calcolaSconto(
                        $product['id_fornitore'] ?? 0,
                        $quantity,
                        $prezzo_totale
                    );
                    $prezzo_finale = $prezzo_totale * (1 - $sconto/100);

                    $response .= "🛒 **" . $product['nome'] . "**\n";
                    $response .= "• Fornitore: " . ($product['nome_fornitore'] ?? 'N/A') . "\n";
                    $response .= "• Prezzo unitario: €" . number_format($product['prezzo_acquisto'], 2) . "\n";
                    $response .= "• Prezzo per " . $quantity . " pz: €" . number_format($prezzo_finale, 2) . "\n";
                    if ($sconto > 0) {
                        $response .= "• Sconto applicato: " . $sconto . "%\n";
                    }
                    $response .= "• Disponibilità: " . ($product['quantita_disponibile'] ?? 0) . " pz\n\n";
                }
                $response .= "💡 *Per ordini specifici, vai alla pagina del prodotto*";
                break;

            case 'sconto':
                $response = "🎯 **Sconti disponibili per " . $product_info['product_name'] . ":**\n\n";
                foreach ($products as $product) {
                    $prezzo_totale = $product['prezzo_acquisto'] * $quantity;
                    $sconto = calcolaSconto(
                        $product['id_fornitore'] ?? 0,
                        $quantity,
                        $prezzo_totale
                    );

                    $response .= "📦 **" . $product['nome'] . "**\n";
                    $response .= "• Quantità: " . $quantity . " pz\n";
                    $response .= "• Sconto applicabile: " . $sconto . "%\n";
                    $response .= "• Prezzo originale: €" . number_format($prezzo_totale, 2) . "\n";
                    $response .= "• Prezzo scontato: €" . number_format($prezzo_totale * (1 - $sconto/100), 2) . "\n\n";
                }
                break;

            case 'spedizione':
                $response = "🚚 **Tempi di spedizione per " . $product_info['product_name'] . ":**\n\n";
                foreach ($products as $product) {
                    $response .= "📦 **" . $product['nome'] . "**\n";
                    $response .= "• Fornitore: " . ($product['nome_fornitore'] ?? 'N/A') . "\n";
                    $response .= "• Tempo spedizione: " . ($product['giorni_spedizione'] ?? 'N/A') . " giorni lavorativi\n";
                    $response .= "• Disponibilità: " . ($product['quantita_disponibile'] ?? 0) . " pz\n\n";
                }
                break;

            default:
                $response = "🔍 **Prodotti trovati per '" . $product_info['product_name'] . "':**\n\n";
                foreach ($products as $product) {
                    $response .= "🛒 **" . $product['nome'] . "**\n";
                    $response .= "• Prezzo: €" . number_format($product['prezzo_acquisto'], 2) . "\n";
                    $response .= "• Fornitore: " . ($product['nome_fornitore'] ?? 'N/A') . "\n";
                    $response .= "• Disponibile: " . ($product['quantita_disponibile'] ?? 0) . " pz\n\n";
                }
                $response .= "💡 *Chiedimi informazioni su prezzi, sconti o tempi di spedizione!*";
        }

        return $response;
    }

    private function buildSystemPrompt($product_info) {
        $prompt = "Sei un assistente virtuale per ShopOnline, un e-commerce di elettronica.

COMPORTAMENTO:
- Rispondi SEMPRE in italiano
- Risposte BREVI e UTILI (max 3-4 righe)
- Tono cordiale ma professionale
- Per domande sui prodotti ShopOnline: dai informazioni specifiche se disponibili
- Se non conosci informazioni specifiche: suggerisci di cercare sul sito o contattare il servizio clienti
- Non inventare prezzi o dettagli sui prodotti se non li conosci

PRODOTTI PRINCIPALI:
- Elettronica: monitor, mouse, tastiere, webcam
- Computer: SSD, RAM, CPU, schede video
- Stampanti, cuffie, router, alimentatori

ISTRUZIONE IMPORTANTE: Se l'utente chiede informazioni su prodotti specifici che potrebbero essere nel catalogo ShopOnline, concentrati sulle informazioni generali e suggerisci di verificare sul sito per dettagli precisi.";

        if ($product_info['is_product_question']) {
            $prompt .= "\n\nNOTA: L'utente sta chiedendo informazioni su: " . $product_info['product_name'];
        }

        return $prompt;
    }
}

// Funzioni per gestire le conversazioni
function salvaConversazione($id_utente, $sessione_id, $messaggio_utente, $risposta_bot) {
    $conn = getDBConnection();
    try {
        $stmt = $conn->prepare("
            INSERT INTO ChatConversations (id_utente, sessione_id, messaggio_utente, risposta_bot) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$id_utente, $sessione_id, $messaggio_utente, $risposta_bot]);
        return true;
    } catch (Exception $e) {
        error_log("Errore salvataggio conversazione: " . $e->getMessage());
        return false;
    }
}

function getCronologiaConversazione($sessione_id, $limit = 10) {
    $conn = getDBConnection();
    try {
        $stmt = $conn->prepare("
            SELECT messaggio_utente, risposta_bot, timestamp 
            FROM ChatConversations 
            WHERE sessione_id = ? 
            ORDER BY timestamp DESC 
            LIMIT ?
        ");
        $stmt->execute([$sessione_id, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Errore caricamento cronologia: " . $e->getMessage());
        return [];
    }
}
?>