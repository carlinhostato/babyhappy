document.addEventListener('DOMContentLoaded', () => {
    // ⚠️ ATENÇÃO:
    // 1. Você precisará de uma API para listar reservas (fetchBookingsAPI).
    // 2. A API processBookingAPI é a que criamos na resposta anterior.
    
    // Caminho da API de Processamento (process_booking.php)
    const PROCESS_BOOKING_API_URL = '/api/babysitter/process_booking.php'; 
    
    // Simulação de uma API para listar as reservas (Necessita de ser implementada no Backend PHP)
    const LIST_BOOKINGS_API_URL = '/api/babysitter/list_reservations.php'; 

    const reservasList = document.getElementById('reservas-list');
    const feedbackMsg = document.getElementById('feedback-message');

    // --- FUNÇÕES DE UTENSÍLIO ---

    function showFeedback(message, type) {
        feedbackMsg.className = `feedback-message feedback-${type}`;
        feedbackMsg.textContent = message;
        feedbackMsg.style.display = 'block';
        setTimeout(() => {
            feedbackMsg.style.display = 'none';
        }, 5000);
    }
    
    function formatDateTime(dateString) {
        const options = { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' };
        try {
            return new Date(dateString).toLocaleDateString('pt-PT', options);
        } catch (e) {
            return dateString;
        }
    }

    // --- FUNÇÃO PRINCIPAL DE PROCESSAMENTO (CHAMA A API) ---

    window.processBookingAction = async function(bookingId, action) {
        const cardId = `booking-${bookingId}`;
        const actionContainer = document.querySelector(`#${cardId} .booking-actions`);
        
        // Feedback visual imediato
        const originalHtml = actionContainer.innerHTML;
        actionContainer.innerHTML = '<span style="color: blue;">A processar...</span>';
        
        showFeedback('A processar a ação...', 'loading');

        try {
            const response = await fetch(PROCESS_BOOKING_API_URL, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    booking_id: bookingId,
                    action: action
                })
            });

            const data = await response.json();

            if (data.success) {
                showFeedback(data.message, 'success');
                // Recarrega a lista após o sucesso
                loadReservations(); 
            } else {
                showFeedback('Erro: ' + data.message, 'error');
                actionContainer.innerHTML = originalHtml; // Restaura botões
            }

        } catch (error) {
            showFeedback('Erro de comunicação com o servidor. Verifique a API.', 'error');
            actionContainer.innerHTML = originalHtml; // Restaura botões
            console.error('AJAX Error:', error);
        }
    }

    // --- FUNÇÃO PARA RENDERIZAR A LISTA ---

    function renderReservations(reservas) {
        reservasList.innerHTML = ''; // Limpa a lista
        if (reservas.length === 0) {
            reservasList.innerHTML = '<p>Não tem reservas para gerir.</p>';
            return;
        }

        reservas.forEach(reserva => {
            const statusClass = reserva.status_reserva.toLowerCase().replace(/[^a-z]/g, ''); // Limpa status
            const card = document.createElement('div');
            card.className = 'booking-card';
            card.id = `booking-${reserva.booking_id}`;
            card.style.borderLeftColor = statusClass === 'pendente' ? '#ffc107' : statusClass === 'aceite' ? '#28a745' : statusClass === 'paga' ? '#17a2b8' : statusClass === 'rejeitada' ? '#dc3545' : '#6c757d';

            let actionButtons = '';
            
            if (reserva.status_reserva === 'pendente') {
                actionButtons = `
                    <button onclick="processBookingAction(${reserva.booking_id}, 'aceitar')" class="btn btn-success">Aceitar</button>
                    <button onclick="processBookingAction(${reserva.booking_id}, 'rejeitar')" class="btn btn-danger">Rejeitar</button>
                `;
            } else if (reserva.status_reserva === 'aceite' || reserva.status_reserva === 'paga') {
                actionButtons = `
                    <button onclick="processBookingAction(${reserva.booking_id}, 'concluir')" class="btn btn-primary">Marcar como Concluída</button>
                `;
            }

            card.innerHTML = `
                <p><strong>Cliente:</strong> ${reserva.parent_nome}</p>
                <p><strong>Início:</strong> ${formatDateTime(reserva.data_inicio)}</p>
                <p><strong>Fim:</strong> ${formatDateTime(reserva.data_fim)}</p>
                <p><strong>Montante:</strong> €${reserva.montante_total.toFixed(2).replace('.', ',')}</p>
                <p><strong>Status Atual:</strong> <span class="status-badge status-${statusClass}">${reserva.status_reserva.charAt(0).toUpperCase() + reserva.status_reserva.slice(1)}</span></p>

                <div class="booking-actions" style="margin-top:10px;">
                    ${actionButtons}
                </div>
            `;
            reservasList.appendChild(card);
        });
    }

    // --- FUNÇÃO PARA CARREGAR DADOS (CHAMA A API DE LISTAGEM) ---

    async function loadReservations() {
        reservasList.innerHTML = '<p class="feedback-loading">A carregar dados do servidor...</p>';
        
        try {
            // ⚠️ NOTA: Esta API deve ser implementada no seu backend PHP!
            const response = await fetch(LIST_BOOKINGS_API_URL);
            
            if (!response.ok) {
                throw new Error('Falha ao obter lista de reservas: HTTP ' + response.status);
            }
            
            const data = await response.json();

            if (data.success) {
                renderReservations(data.reservas);
            } else {
                reservasList.innerHTML = `<p style="color: red;">Erro ao carregar lista: ${data.message}</p>`;
            }

        } catch (error) {
            reservasList.innerHTML = `<p style="color: red;">❌ Erro de rede ou a API de listagem (${LIST_BOOKINGS_API_URL}) não está operacional.</p>`;
            console.error('Falha na API de Listagem:', error);
            // Simular dados se a API falhar (APENAS PARA DESENVOLVIMENTO)
            // renderReservations(getSimulatedReservations()); 
        }
    }

    // Inicia o carregamento quando a página carrega
    loadReservations();
});
// A função de simulação abaixo é apenas um exemplo do formato de dados esperado:
/*
function getSimulatedReservations() {
    return [
        { booking_id: 101, parent_nome: "Maria Santos", data_inicio: "2025-12-15 10:00:00", data_fim: "2025-12-15 14:00:00", montante_total: 40.00, status_reserva: "pendente" },
        { booking_id: 102, parent_nome: "João Silva", data_inicio: "2025-12-16 18:00:00", data_fim: "2025-12-16 22:00:00", montante_total: 50.00, status_reserva: "paga" },
        { booking_id: 103, parent_nome: "Ana Pereira", data_inicio: "2025-12-18 09:00:00", data_fim: "2025-12-18 11:00:00", montante_total: 20.00, status_reserva: "aceite" }
    ];
}
*/