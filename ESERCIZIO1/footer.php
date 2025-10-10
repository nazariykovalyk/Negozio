<footer class="footer">
    <div class="container">
        <div class="footer-content">
            <!-- Colonna 1: Informazioni -->
            <div class="footer-column">
                <h3 class="footer-title">🛍️ ShopOnline</h3>
                <p class="footer-description">
                    Il tuo negozio di fiducia per acquisti intelligenti. Confrontiamo i prezzi di diversi fornitori per offrirti sempre il miglior rapporto qualità-prezzo.
                </p>
                <div class="footer-features">
                    <div class="feature">⭐ Migliori Prezzi Garantiti</div>
                    <div class="feature">🚚 Spedizione Veloce</div>
                    <div class="feature">🔒 Acquisto Sicuro</div>
                </div>
            </div>

            <!-- Colonna 2: Link Utili -->
            <div class="footer-column">
                <h4 class="footer-subtitle">Link Utili</h4>
                <ul class="footer-links">
                    <li><a href="index.php" class="footer-link">🏠 Homepage</a></li>
                    <li><a href="carrello.php" class="footer-link">🛒 Il mio Carrello</a></li>
                    <li><a href="profilo.php" class="footer-link">👤 Il mio Profilo</a></li>
                </ul>
            </div>

            <!-- Colonna 3: Categorie -->
            <div class="footer-column">
                <h4 class="footer-subtitle">Categorie</h4>
                <ul class="footer-links">
                    <li><a href="index.php?cerca=monitor" class="footer-link">🖥️ Monitor & Display</a></li>
                    <li><a href="index.php?cerca=mouse" class="footer-link">🖱️ Mouse</a></li>
                    <li><a href="index.php?cerca=stampante" class="footer-link">🖨️ Stampanti</a></li>
                    <li><a href="index.php?cerca=ssd" class="footer-link">💾 Storage</a></li>
                </ul>
            </div>

            <!-- Colonna 4: Contatti -->
            <div class="footer-column">
                <h4 class="footer-subtitle">Contatti</h4>
                <div class="footer-contacts">
                    <div class="contact-item">📧 info@shoponline.com</div>
                    <div class="contact-item">📞 +39 02 1234 5678</div>
                    <div class="contact-item">🏢 Via Roma 123, Milano</div>
                    <div class="contact-item">🕒 Lun-Ven 9:00-18:00</div>
                </div>
            </div>
        </div>

        <!-- Separatore -->
        <div class="footer-divider"></div>

        <!-- Copyright e link legali -->
        <div class="footer-bottom">
            <div class="footer-copyright">
                © 2025 ShopOnline - Sistema di Gestione Acquisti. Tutti i diritti riservati.
            </div>
            <div class="footer-legal">
                <a href="#" class="legal-link">Privacy Policy</a>
                <a href="#" class="legal-link">Termini di Servizio</a>
                <a href="#" class="legal-link">Cookie Policy</a>
            </div>
        </div>

        <!-- Messaggio aggiuntivo -->
        <div class="footer-message">
            <p>🛡️ Acquisti 100% sicuri | 📦 Spedizioni in 24/48 ore | 🔄 Resi gratuiti entro 30 giorni</p>
        </div>
    </div>
</footer>

<!-- Widget Chat Bot -->
<div id="chat-widget">
    <div id="chat-toggle">🤖 Ada AI</div>
    <div id="chat-container">
        <div id="chat-header">
            <h4>🛍️ Assistente ShopOnline</h4>
            <button id="chat-close">×</button>
        </div>
        <div id="chat-messages"></div>
        <div id="chat-input-container">
            <input type="text" id="chat-input" placeholder="Scrivi la tua domanda...">
            <button id="chat-send">Invia</button>
        </div>
    </div>
</div>

<style>
    /* Stili per il widget chat */
    #chat-widget {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 1000;
    }

    #chat-toggle {
        background: #ff9900;
        color: white;
        padding: 15px 20px;
        border-radius: 25px;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        font-weight: bold;
        transition: all 0.3s;
    }

    #chat-toggle:hover {
        background: #e68900;
        transform: scale(1.05);
    }

    #chat-container {
        position: absolute;
        bottom: 60px;
        right: 0;
        width: 350px;
        height: 500px;
        background: white;
        border-radius: 10px;
        box-shadow: 0 5px 25px rgba(0,0,0,0.2);
        display: none;
        flex-direction: column;
        border: 1px solid #ddd;
    }

    #chat-container.show {
        display: flex;
    }

    #chat-header {
        background: #232f3e;
        color: white;
        padding: 15px;
        border-radius: 10px 10px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    #chat-header h4 {
        margin: 0;
        font-size: 16px;
    }

    #chat-close {
        background: none;
        border: none;
        color: white;
        font-size: 20px;
        cursor: pointer;
        padding: 0;
        width: 25px;
        height: 25px;
    }

    #chat-messages {
        flex: 1;
        padding: 15px;
        overflow-y: auto;
        background: #f8f9fa;
    }

    .message-time {
        font-size: 11px;
        opacity: 0.7;
        margin-top: 5px;
    }

    #chat-input-container {
        padding: 15px;
        border-top: 1px solid #ddd;
        display: flex;
        gap: 10px;
    }

    #chat-input {
        flex: 1;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        outline: none;
    }

    #chat-input:focus {
        border-color: #007bff;
    }

    #chat-send {
        background: #ff9900;
        color: white;
        border: none;
        padding: 10px 15px;
        border-radius: 5px;
        cursor: pointer;
        transition: background 0.3s;
    }

    #chat-send:hover {
        background: #e68900;
    }

    .typing-indicator {
        padding: 10px 15px;
        background: white;
        border: 1px solid #ddd;
        border-radius: 15px;
        margin-bottom: 15px;
        max-width: 80%;
        border-bottom-left-radius: 5px;
        display: none;
    }

    .typing-dots {
        display: flex;
        gap: 3px;
    }

    .typing-dots span {
        width: 6px;
        height: 6px;
        background: #666;
        border-radius: 50%;
        animation: typing 1.4s infinite;
    }

    .typing-dots span:nth-child(2) { animation-delay: 0.2s; }
    .typing-dots span:nth-child(3) { animation-delay: 0.4s; }

    @keyframes typing {
        0%, 60%, 100% { transform: translateY(0); }
        30% { transform: translateY(-5px); }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const chat = {
            toggle: document.getElementById('chat-toggle'),
            container: document.getElementById('chat-container'),
            close: document.getElementById('chat-close'),
            input: document.getElementById('chat-input'),
            send: document.getElementById('chat-send'),
            messages: document.getElementById('chat-messages'),
            sessionId: 'chat_' + Date.now()
        };

        // Messaggio di benvenuto
        addBotMessage('Ciao! Sono l\'assistente virtuale di ShopOnline. Come posso aiutarti con i nostri prodotti di elettronica?');

        // Event listeners
        chat.toggle.addEventListener('click', toggleChat);
        chat.close.addEventListener('click', () => chat.container.classList.remove('show'));
        chat.input.addEventListener('keypress', e => e.key === 'Enter' && sendMessage());
        chat.send.addEventListener('click', sendMessage);

        function toggleChat() {
            chat.container.classList.toggle('show');
            if (chat.container.classList.contains('show')) {
                chat.input.focus();
            }
        }

        function sendMessage() {
            const message = chat.input.value.trim();
            if (!message) return;

            addUserMessage(message);
            chat.input.value = '';
            showTypingIndicator();

            fetch('chat_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ messaggio: message, sessione_id: chat.sessionId })
            })
                .then(response => response.json())
                .then(data => {
                    hideTypingIndicator();
                    addBotMessage(data.success ? data.response : 'Mi dispiace, si è verificato un errore. Riprova.');
                })
                .catch(() => {
                    hideTypingIndicator();
                    addBotMessage('Errore di connessione. Riprova più tardi.');
                });
        }

        function addUserMessage(message) {
            addMessage(message, 'user');
        }

        function addBotMessage(message) {
            addMessage(message, 'bot');
        }

        function addMessage(message, type) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${type}`;
            messageDiv.innerHTML = `${message}<div class="message-time">${getCurrentTime()}</div>`;
            chat.messages.appendChild(messageDiv);
            scrollToBottom();
        }

        function showTypingIndicator() {
            const typingDiv = document.createElement('div');
            typingDiv.id = 'typing-indicator';
            typingDiv.className = 'typing-indicator';
            typingDiv.style.display = 'block';
            typingDiv.innerHTML = '<div class="typing-dots"><span></span><span></span><span></span></div>';
            chat.messages.appendChild(typingDiv);
            scrollToBottom();
        }

        function hideTypingIndicator() {
            const indicator = document.getElementById('typing-indicator');
            if (indicator) indicator.remove();
        }

        function scrollToBottom() {
            chat.messages.scrollTop = chat.messages.scrollHeight;
        }

        function getCurrentTime() {
            return new Date().toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' });
        }
    });
</script>
</body>
</html>