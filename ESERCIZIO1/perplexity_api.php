<?php
require_once 'config.php';
require_once 'functions.php';

class PerplexityAPI {
    private $api_key;
    private $api_url;

    public function __construct() {
        $this->api_key = defined('PERPLEXITY_API_KEY') ? PERPLEXITY_API_KEY : '';
        $this->api_url = defined('PERPLEXITY_API_URL') ? PERPLEXITY_API_URL : '';
    }

    public function sendMessage($message) {
        $product_info = $this->analyzeQuestion($message);

        // PRIORITÀ 1: Perplexity API
        if ($this->api_key && $this->api_url) {
            $db_context = $this->getDBContext($product_info);
            $result = $this->callPerplexityAPI($message, $db_context, $product_info);
            if ($result['success']) return $result;
            error_log("Perplexity API fallita, uso fallback");
        }

        // PRIORITÀ 2: DB per prodotti
        if ($product_info['is_product_question']) {
            if ($db_response = $this->getProductInfoFromDB($product_info)) {
                return ['success' => true, 'response' => $db_response, 'from_db' => true];
            }
        }

        // PRIORITÀ 3: Domande negozio
        if ($this->isStoreQuestion($message)) {
            return $this->getStoreInfoResponse($message);
        }

        // PRIORITÀ 4: Fallback
        return $this->getFallbackResponse($message, $product_info);
    }

    private function getDBContext($product_info) {
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

            $stmt->execute(['%' . strtolower($product_info['product_name']) . '%']);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($products) {
                $context = "Prodotti disponibili:\n";
                foreach ($products as $p) {
                    $context .= "- {$p['nome']}: €{$p['prezzo_acquisto']}, ";
                    $context .= "Disponibili: {$p['quantita_disponibile']} pz, ";
                    $context .= "Fornitore: {$p['nome_fornitore']}, ";
                    $context .= "Spedizione: {$p['giorni_spedizione']} giorni\n";
                }
                return $context;
            }
        } catch (Exception $e) {
            error_log("Errore recupero contesto DB: " . $e->getMessage());
        }
        return '';
    }

    private function callPerplexityAPI($message, $db_context, $product_info) {
        $system_message = $this->buildSystemPrompt($product_info, $db_context);

        $data = [
            'model' => 'sonar',
            'messages' => [
                ['role' => 'system', 'content' => $system_message],
                ['role' => 'user', 'content' => $message]
            ],
            'max_tokens' => 500,
            'temperature' => 0.7,
            'top_p' => 0.9
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->api_url,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->api_key
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200 && $response) {
            $result = json_decode($response, true);
            if (isset($result['choices'][0]['message']['content'])) {
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

        if ($db_context) {
            $prompt .= "\n\nDATI PRODOTTI ATTUALI:\n" . $db_context;
            $prompt .= "\nUsa questi dati reali per rispondere con precisione.";
        }

        if ($product_info['is_product_question']) {
            $prompt .= "\n\nL'utente chiede informazioni su: " . $product_info['product_name'];
        }

        return $prompt;
    }

    private function analyzeQuestion($message) {
        $message_lower = strtolower($message);
        $result = [
            'is_product_question' => false,
            'product_name' => '',
            'question_type' => '',
            'quantity' => 1
        ];

        $products = ['mouse', 'monitor', 'tastiera', 'stampante', 'webcam', 'ssd', 'hard disk', 'ram', 'cpu', 'scheda video'];

        foreach ($products as $product) {
            if (strpos($message_lower, $product) !== false) {
                $result['is_product_question'] = true;
                $result['product_name'] = $product;
                break;
            }
        }

        if (strpos($message_lower, 'prezzo') !== false || strpos($message_lower, 'costa') !== false) {
            $result['question_type'] = 'prezzo';
        } elseif (strpos($message_lower, 'spedizione') !== false || strpos($message_lower, 'tempo') !== false) {
            $result['question_type'] = 'spedizione';
        } elseif (strpos($message_lower, 'sconto') !== false) {
            $result['question_type'] = 'sconto';
        }

        preg_match('/\b(\d+)\b/', $message, $matches);
        if ($matches) $result['quantity'] = intval($matches[1]);

        return $result;
    }

    private function isStoreQuestion($message) {
        $message_lower = strtolower($message);
        $store_keywords = ['spedizione', 'consegna', 'pagamento', 'orari', 'contatti', 'telefono', 'email', 'garanzia'];

        foreach ($store_keywords as $keyword) {
            if (strpos($message_lower, $keyword) !== false) return true;
        }
        return false;
    }

    private function getStoreInfoResponse($message) {
        $message_lower = strtolower($message);

        if (strpos($message_lower, 'spedizione') !== false) {
            return [
                'success' => true,
                'response' => "🚚 **Spedizione:**\n• Standard: 2-5 giorni\n• Express: 24-48 ore (+€9.90)\n• Gratis sopra €50"
            ];
        }

        if (strpos($message_lower, 'pagamento') !== false) {
            return [
                'success' => true,
                'response' => "💳 **Pagamenti:**\n• Carte: Visa, MasterCard\n• PayPal\n• Bonifico\n• Contrassegno (+€3.90)"
            ];
        }

        if (strpos($message_lower, 'contatti') !== false) {
            return [
                'success' => true,
                'response' => "📞 **Contatti:**\n• Tel: +39 02 1234 5678\n• Email: info@shoponline.com\n• Orari: Lun-Ven 9:00-18:00"
            ];
        }

        return [
            'success' => true,
            'response' => "🏪 **ShopOnline** - E-commerce di elettronica\n\nPosso aiutarti con:\n• Prodotti e prezzi\n• Spedizioni\n• Pagamenti\n• Assistenza"
        ];
    }

    private function getProductInfoFromDB($product_info) {
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

            $stmt->execute(['%' . strtolower($product_info['product_name']) . '%']);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $products ? $this->formatProductResponse($products, $product_info) : null;

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
                $response = "🔍 **Prezzi " . $product_info['product_name'] . ":**\n\n";
                foreach ($products as $product) {
                    $prezzo_totale = $product['prezzo_acquisto'] * $quantity;
                    $sconto = calcolaSconto($product['id_fornitore'] ?? 0, $quantity, $prezzo_totale);
                    $prezzo_finale = $prezzo_totale * (1 - $sconto/100);

                    $response .= "🛒 **" . $product['nome'] . "**\n";
                    $response .= "• €" . number_format($product['prezzo_acquisto'], 2) . "/pz";
                    if ($quantity > 1) $response .= " × {$quantity} = €" . number_format($prezzo_finale, 2);
                    if ($sconto > 0) $response .= " (sconto {$sconto}%)";
                    $response .= "\n• Fornitore: " . ($product['nome_fornitore'] ?? 'N/A') . "\n\n";
                }
                break;

            case 'spedizione':
                $response = "🚚 **Spedizione " . $product_info['product_name'] . ":**\n\n";
                foreach ($products as $product) {
                    $response .= "📦 **" . $product['nome'] . "**\n";
                    $response .= "• Tempo: " . ($product['giorni_spedizione'] ?? 'N/A') . " giorni\n\n";
                }
                break;

            default:
                $response = "🔍 **Prodotti trovati:**\n\n";
                foreach ($products as $product) {
                    $response .= "🛒 **" . $product['nome'] . "**\n";
                    $response .= "• Prezzo: €" . number_format($product['prezzo_acquisto'], 2) . "\n\n";
                }
        }

        return $response;
    }

    private function getFallbackResponse($message, $product_info) {
        if ($product_info['is_product_question']) {
            return [
                'success' => true,
                'response' => "🔍 Non ho trovato '" . $product_info['product_name'] . "' nel catalogo.\n\n💡 Prova a cercare nella barra di ricerca"
            ];
        }

        return [
            'success' => true,
            'response' => "ℹ️ **Assistente ShopOnline**\n\nPosso aiutarti con:\n• Prezzi e prodotti\n• Spedizioni\n• Assistenza"
        ];
    }
}

function salvaConversazione($id_utente, $sessione_id, $messaggio_utente, $risposta_bot) {
    $conn = getDBConnection();
    try {
        $stmt = $conn->prepare("INSERT INTO ChatConversations (id_utente, sessione_id, messaggio_utente, risposta_bot) VALUES (?, ?, ?, ?)");
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
        $stmt = $conn->prepare("SELECT messaggio_utente, risposta_bot, timestamp FROM ChatConversations WHERE sessione_id = ? ORDER BY timestamp DESC LIMIT ?");
        $stmt->execute([$sessione_id, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Errore caricamento cronologia: " . $e->getMessage());
        return [];
    }
}
?>