document.addEventListener('DOMContentLoaded', () => {
    const listContainer = document.getElementById('reservas-list');
    const loadingMessage = document.getElementById('loading-message');
    const noBookingsMessage = document.getElementById('no-bookings');
    const feedbackMessage = document.getElementById('feedback-message');
    const loggedInUserNameDisplay = document.getElementById('welcome-message-display'); 
    
    const API_ROOT = window.location.origin + '/babyhappy_v1/';
    
    const API_URLS = {
        LIST: API_ROOT + 'api/auth/babysitter_list.php',
        ACTION: API_ROOT + 'api/auth/babysitter_action.php',
        FETCH_NAME: API_ROOT + 'api/auth/fetch_user_name.php'
    };

    function showFeedback(message, type = 'success') {
        if (!feedbackMessage) return;
        feedbackMessage.textContent = message;
        feedbackMessage.style.display = 'block';
        feedbackMessage.style.color = type === 'success' ? '#155724' : '#721c24';
        feedbackMessage.style.backgroundColor = type === 'success' ? '#d4edda' : '#f8d7da';
        setTimeout(() => { feedbackMessage.style.display = 'none'; }, 5000);
    }

    function formatCurrency(amount) {
        return `€${parseFloat(amount).toFixed(2).replace('.', ',')}`;
    }

    function formatDate(dateString) {
        const options = { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' };
        return new Date(dateString).toLocaleDateString('pt-PT', options);
    }

    async function fetchLoggedInUserName() {
        if (!loggedInUserNameDisplay) return;
        try {
            const response = await fetch(API_URLS.FETCH_NAME);
            const d = await response.json(); 
            if (d.success) loggedInUserNameDisplay.textContent = `Bem-vindo(a), ${d.nome}`;
        } catch (e) { console.error("Erro ao buscar nome"); }
    }

    function renderBookingCard(booking) {
        const card = document.createElement('div');
        card.className = 'reserva-card';
        card.dataset.id = booking.booking_id;

        let actionHtml = '';
        let statusDisplay = `<span class="status ${booking.status_reserva}">${booking.status_reserva}</span>`;

        if (booking.status_reserva === 'pendente') {
            actionHtml = `
                <div class="action-buttons" style="margin-top:10px; display:flex; gap:10px;">
                    <button type="button" onclick="processAction(${booking.booking_id}, 'aprovar')" class="btn-aprovar">✅ Aprovar</button>
                    <button type="button" onclick="processAction(${booking.booking_id}, 'recusar')" class="btn-recusar">❌ Recusar</button>
                </div>
            `;
        }

        card.innerHTML = `
            <p><strong>ID:</strong> ${booking.booking_id}</p>
            <p><strong>Pai:</strong> ${booking.nome_pai || '—'}</p>
            <p><strong>Início:</strong> ${formatDate(booking.data_inicio)}</p>
            <p><strong>Fim:</strong> ${formatDate(booking.data_fim)}</p>
            <p><strong>Status:</strong> ${statusDisplay}</p>
            <p><strong>Total:</strong> ${formatCurrency(booking.montante_total)}</p>
            ${actionHtml}
        `;
        return card;
    }

    // Função global para ser chamada pelos botões
    window.processAction = async function(bookingId, actionType) {
        try {
            const formData = new FormData();
            formData.append('booking_id', bookingId);
            formData.append('action', actionType);

            const response = await fetch(API_URLS.ACTION, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                showFeedback(data.message, 'success');
                loadBookings(); // Recarrega a lista para atualizar o status
            } else {
                showFeedback(data.message, 'error');
            }
        } catch (error) {
            showFeedback('Erro ao processar ação.', 'error');
        }
    };

    async function loadBookings() {
        try {
            const response = await fetch(API_URLS.LIST);
            const data = await response.json();
            listContainer.innerHTML = '';
            if (data.success && data.data.length > 0) {
                data.data.forEach(b => listContainer.appendChild(renderBookingCard(b)));
            } else {
                noBookingsMessage.style.display = 'block';
            }
        } catch (e) { showFeedback('Erro ao carregar lista.', 'error'); }
    }

    fetchLoggedInUserName();
    loadBookings();
});