/**
 * ../js/chat_client.js
 * Responsável pela lógica de frontend, mensagens em tempo real e status online.
 */

document.addEventListener('DOMContentLoaded', () => {

    // --- 1. ESTADO GLOBAL ---
    let currentUserId = 0;
    let currentActiveChatId = null; 
    
    const urlParams = new URLSearchParams(window.location.search);
    currentActiveChatId = urlParams.get('chat_with') || null; 
    
    // --- 2. REFERÊNCIAS DOM ---
    const conversationListContainer = document.getElementById('conversation-list-container');
    const chatWindow = document.getElementById('chat-window');
    const messagesArea = document.getElementById('messages-list');
    const chatForm = document.getElementById('chat-form');
    const chatActiveContent = document.querySelector('.chat-active-content');
    const welcomeMessage = document.getElementById('welcome-message');
    const backButton = document.getElementById('back-to-list-btn');
    const loggedInUserNameDisplay = document.getElementById('welcome-message-display'); 
    const partnerNameDisplay = document.getElementById('partner-name-display');
    const partnerStatus = document.getElementById('partner-status');
    const deleteConversationBtn = document.getElementById('delete-conversation-btn');
    const inputSenderId = document.getElementById('input-sender-id');
    const inputReceiverId = document.getElementById('input-receiver-id');

    // Configuração de Caminhos
    const API_ROOT = '/babyhappy_v1/'; 
    const defaultPhoto = `${API_ROOT}frontend/assets/images/default_profile.png`; 

    const API_PATHS = {
        FETCH_ID:    `${API_ROOT}api/auth/fetch_user_id.php`, 
        CONVERSATIONS: `${API_ROOT}api/auth/fetch_conversations.php?user_id=`, 
        MESSAGES:    `${API_ROOT}api/auth/fetch_messages.php?user_id=`,
        SEND:        `${API_ROOT}api/auth/send_message.php`, 
        DELETE_MSG:  `${API_ROOT}api/auth/delete_message.php`,
        DELETE_CONV: `${API_ROOT}api/auth/delete_conversation.php`,
        STATUS:      `${API_ROOT}api/auth/fetch_status.php?partner_id=`,
        ACTIVITY:    `${API_ROOT}api/auth/update_activity.php`,
        FETCH_NAME:  `${API_ROOT}api/auth/fetch_user_name.php` 
    };

    if (inputReceiverId && currentActiveChatId) {
        inputReceiverId.value = currentActiveChatId;
    }

    // --- 3. FUNÇÕES DE UTILIDADE ---

    function formatPhotoUrl(path) {
        if (!path) return defaultPhoto;
        if (path.startsWith('http')) return path;
        let cleanPath = path.startsWith('/') ? path : '/' + path;
        if (!cleanPath.startsWith(API_ROOT)) {
            cleanPath = API_ROOT + path.replace(/^\//, '');
        }
        return cleanPath;
    }

    function scrollToBottom() {
        if (messagesArea) {
            messagesArea.scrollTop = messagesArea.scrollHeight;
        }
    }

    function applyMobileDisplay() {
        const isMobile = window.innerWidth < 768;
        if (!conversationListContainer || !chatWindow) return;
        
        if (isMobile) {
            if (currentActiveChatId) {
                conversationListContainer.style.display = 'none';
                chatWindow.style.display = 'flex';
            } else {
                conversationListContainer.style.display = 'block';
                chatWindow.style.display = 'none';
            }
        } else {
            conversationListContainer.style.display = 'block';
            chatWindow.style.display = 'flex';
        }
    }

    // --- 4. RENDERIZAÇÃO ---

    function renderConversationList(conversations) {
        if (!conversationListContainer) return;
        let html = '<h3 style="text-align:center; padding:15px; margin:0; border-bottom:1px solid #eee;">Conversas</h3>';
        
        if (!conversations || conversations.length === 0) {
            html += '<p style="text-align:center;color:#999;margin-top:20px;">Nenhuma conversa.</p>';
        } else {
            conversations.forEach(conv => {
                const isActive = conv.partner_id == currentActiveChatId;
                const hasUnread = parseInt(conv.unread_count) > 0;
                const photo = formatPhotoUrl(conv.partner_photo);
                
                html += `
                    <a href="?chat_with=${conv.partner_id}" class="conversation-item ${isActive ? 'active-conv' : ''}">
                        <div class="conv-photo"><img src="${photo}" alt="User"></div>
                        <div class="conv-info">
                            <b style="${hasUnread ? 'color:#2a5bd7;' : ''}">${conv.partner_name}</b><br>
                            <small>${(conv.last_message || 'Inicie o chat...').substring(0, 25)}</small>
                        </div>
                        ${hasUnread ? `<span class="unread-count">${conv.unread_count}</span>` : ''}
                    </a>`;
            });
        }
        conversationListContainer.innerHTML = html;
        applyMobileDisplay();
    }

    function renderMessages(messages) {
        if (!messagesArea) return;
        messagesArea.innerHTML = '';
        if (messages.length === 0) {
            messagesArea.innerHTML = '<div style="text-align:center;color:#aaa;margin-top:50px;">Diga "Olá"! 👋</div>';
        } else {
            messages.forEach(msg => {
                const classType = msg.is_my ? 'my-message' : 'partner-message';
                const deleteBtn = msg.is_my ? `<button class="delete-message-btn" data-id="${msg.message_id}">🗑️</button>` : ''; 
                
                messagesArea.innerHTML += `
                    <div class="message ${classType}" data-message-id="${msg.message_id}">
                        <div class="message-text">${msg.text}</div>
                        ${deleteBtn}
                        <div class="message-time">${msg.time} ${msg.visto || ''}</div>
                    </div>`;
            });
        }
        scrollToBottom();
    }

    // --- 5. FUNÇÕES FETCH ---

    async function fetchConversations() {
        if (currentUserId <= 0) return;
        try {
            const r = await fetch(API_PATHS.CONVERSATIONS + currentUserId);
            const d = await r.json();
            if (d.success) renderConversationList(d.conversations);
        } catch (e) { console.error("Erro conversas:", e); }
    }

    async function refreshStatus() {
        if (!currentActiveChatId) return;
        try {
            const r = await fetch(API_PATHS.STATUS + currentActiveChatId);
            const d = await r.json();
            if (d.status_html && partnerStatus) {
                partnerStatus.innerHTML = d.status_html;
            }
        } catch (e) { console.log("Status offline."); }
    }

    async function fetchActiveChat(isRefresh = false) {
        if (!currentActiveChatId || currentUserId <= 0) return;

        if (!isRefresh && chatActiveContent) {
            chatActiveContent.style.display = 'flex';
            if (welcomeMessage) welcomeMessage.style.display = 'none';
        }

        try {
            const r = await fetch(`${API_PATHS.MESSAGES}${currentUserId}&chat_with=${currentActiveChatId}`);
            const d = await r.json();
            if (d.success) {
                if (!isRefresh && partnerNameDisplay) {
                    partnerNameDisplay.textContent = d.partner_name || 'Conversa';
                }
                
                // Só renderiza se o número de mensagens mudou para poupar processamento
                const currentCount = messagesArea.querySelectorAll('.message').length;
                if (d.messages.length !== currentCount) {
                    renderMessages(d.messages);
                    fetchConversations();
                }
            }
        } catch (e) { console.error("Erro mensagens:", e); }
    }

    // --- 6. HANDLERS ---

    if (chatForm) {
        chatForm.addEventListener('submit', async e => {
            e.preventDefault();
            const inputField = chatForm.querySelector('input[name="message"]');
            const msg = inputField ? inputField.value.trim() : '';
            if (!msg || currentUserId <= 0) return;

            const formData = new FormData(chatForm);
            inputField.value = '';
            
            try {
                const response = await fetch(API_PATHS.SEND, { method: 'POST', body: formData });
                const d = await response.json();
                if (d.success) {
                    fetchActiveChat(true);
                    fetchConversations();
                }
            } catch (e) { console.error("Falha no envio."); }
        });
    }

    // Clique para apagar mensagem
    document.addEventListener('click', e => {
        if (e.target.classList.contains('delete-message-btn')) {
            const id = e.target.dataset.id;
            if (id && confirm('Eliminar mensagem?')) {
                fetch(API_PATHS.DELETE_MSG, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'message_id=' + id
                }).then(() => fetchActiveChat(true));
            }
        }
    });

    if (backButton) {
        backButton.addEventListener('click', () => {
            window.location.href = window.location.pathname;
        });
    }

    // --- 7. INICIALIZAÇÃO E PULSO (HEARTBEAT) ---
    
    async function fetchUserIdAndInitialize() {
        try {
            // Verifica se há sessão ativa
            const response = await fetch(API_PATHS.FETCH_ID);
            const data = await response.json();
            
            if (data.success && data.user_id) {
                currentUserId = data.user_id;
                
                // Atualiza UI de boas-vindas
                if (loggedInUserNameDisplay && data.user_name) {
                    loggedInUserNameDisplay.textContent = `Bem-vindo(a), ${data.user_name}`;
                }

                if (inputSenderId) inputSenderId.value = currentUserId;

                // Carrega dados iniciais
                fetchConversations(); 
                fetchActiveChat(false); 
                refreshStatus();

                // --- CONFIGURAÇÃO DE TIMERS (SÓ DEPOIS DO LOGIN) ---
                
                // 1. Pulso de Atividade (Heartbeat) - a cada 25s
                setInterval(() => {
                    fetch(API_PATHS.ACTIVITY, { method: 'POST' })
                    .then(r => {
                        if (r.status === 403) window.location.reload(); // Se perdeu sessão, recarrega
                    });
                }, 25000);

                // 2. Atualização Automática de Mensagens e Status
                if (currentActiveChatId) {
                    setInterval(() => {
                        fetchActiveChat(true);
                        refreshStatus();
                    }, 5000); // 5 segundos para fluidez
                }

                window.addEventListener('resize', applyMobileDisplay);

            } else {
                console.log("Chat suspenso: Aguardando login.");
            }
        } catch (error) {
            console.error("Erro na inicialização do cliente.");
        }
    }
    
    fetchUserIdAndInitialize();
});