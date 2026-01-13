// public/js/parent_bookings.js

document.addEventListener('DOMContentLoaded', function() {
    // --- Configuração das URLs ---
    const PROJECT_ROOT = '/babyhappy_v1/'; 
    const API_BASE_URL = 'http://localhost/babyhappy_v1/api/auth'; 
    const BOOKINGS_API_URL = `${API_BASE_URL}/get_parent_bookings.php`;
    const CANCEL_API_URL = `${API_BASE_URL}/cancel_booking.php`; 
    const DEFAULT_PHOTO_URL = `${PROJECT_ROOT}frontend/assets/images/default_profile.png`; // caminho absoluto relativo ao site root
    
    const bookingsContainer = document.getElementById('bookings-list-container');
    
    // --- Funções Auxiliares ---
    const formatPrice = (value) => {
        const numericValue = parseFloat(value);
        if (isNaN(numericValue)) return '€0,00';
        return new Intl.NumberFormat('pt-PT', { style: 'currency', currency: 'EUR' }).format(numericValue);
    };

    const formatStatusBadge = (status) => {
        let className = 'default';
        const lowerStatus = String(status || '').toLowerCase();
        
        switch (lowerStatus) {
            case 'aprovada':
            case 'confirmada': className = 'confirmed'; break;
            case 'solicitada': className = 'requested'; break;
            case 'rejeitada': className = 'rejected'; break;
            case 'cancelada': className = 'cancelled'; break;
            case 'paga': className = 'completed'; break;
            default: className = 'default';
        }
        const displayStatus = String(status || '').charAt(0).toUpperCase() + String(status || '').slice(1);
        return `<span class="status-badge ${className}">${displayStatus}</span>`;
    };

    // --- Normalizador de URL de foto (resolve caminhos relativos / legacy) ---
    function getCorrectPhotoUrl(rawPath) {
        try {
            if (!rawPath) return DEFAULT_PHOTO_URL;
            let p = String(rawPath).trim();
            if (!p) return DEFAULT_PHOTO_URL;

            // already absolute URL
            if (/^https?:\/\//i.test(p)) return p;

            // already starting from root
            if (p.startsWith('/')) {
                // if path already contains project root prefix, return as-is
                if (p.startsWith(PROJECT_ROOT)) return p;
                // otherwise prefix project root (to avoid missing /babyhappy_v1)
                return PROJECT_ROOT.replace(/\/$/, '') + p;
            }

            // if starts with project root without leading slash
            if (p.startsWith(PROJECT_ROOT.replace(/^\//, ''))) {
                return '/' + p;
            }

            // common patterns: frontend/, public/uploads, backend/uploads, assets/
            const candidates = [
                `${PROJECT_ROOT}${p}`,
                `${PROJECT_ROOT}frontend/${p}`,
                `${PROJECT_ROOT}frontend/uploads/${p}`,
                `${PROJECT_ROOT}public/${p}`,
                `${PROJECT_ROOT}public/uploads/${p}`,
                `${PROJECT_ROOT}assets/${p}`,
                `${PROJECT_ROOT}backend/${p}`
            ];

            // return first candidate (we can't check file existence here without server)
            const chosen = candidates[0];
            console.debug('[photo] normalizing path:', rawPath, '->', chosen);
            return chosen;
        } catch (err) {
            console.warn('getCorrectPhotoUrl error', err);
            return DEFAULT_PHOTO_URL;
        }
    }

    // --- Lógica de Cancelamento AJAX ---
    async function cancelBooking(bookingId) {
        if (!confirm('Tem a certeza que deseja cancelar esta reserva?')) return;

        try {
            const formData = new FormData();
            formData.append('booking_id', bookingId);

            const response = await fetch(CANCEL_API_URL, {
                method: 'POST',
                body: formData,
                credentials: 'include' // enviar cookie de sessão se necessário
            });
            
            const data = await response.json();
            if (data.success) {
                alert('Reserva cancelada com sucesso.');
                loadParentBookings();
            } else {
                alert(`Erro: ${data.message}`);
            }
        } catch (error) {
            console.error('Erro ao cancelar:', error);
            alert('Erro de rede ao cancelar.');
        }
    }

    // --- Renderização ---
    function renderBookingCard(booking) {
        const sitterName = booking.sitter_nome || 'Babysitter';
        const rawPhoto = booking.photo_url || booking.photo || booking.foto || '';
        const sitterPhotoUrl = getCorrectPhotoUrl(rawPhoto);
        // debug
        console.debug(`Booking ${booking.booking_id} sitter photo resolved to:`, sitterPhotoUrl);

        const status = String(booking.status_reserva || '').toLowerCase();
        
        const startDate = booking.data_inicio ? new Date(booking.data_inicio).toLocaleString('pt-PT', { dateStyle: 'short', timeStyle: 'short' }) : '-';
        const endDate = booking.data_fim ? new Date(booking.data_fim).toLocaleString('pt-PT', { dateStyle: 'short', timeStyle: 'short' }) : '-';
        
        const canCancel = ['solicitada', 'aprovada', 'confirmada'].includes(status);
        const canPay = (status === 'aprovada' || status === 'confirmada');

        let actionButtons = '';
        
        if (status === 'paga') {
            actionButtons += `<span class="paid-success">✅ Pagamento Concluído</span>`;
        } else if (canPay) {
            // ensure montante_total is passed as number/string
            const amountParam = encodeURIComponent(String(booking.montante_total || '0'));
            actionButtons += `
                <a href="payments_view.html?booking_id=${booking.booking_id}&amount=${amountParam}" 
                   class="btn-pagar">
                   💳 Efetuar Pagamento
                </a>`;
        }

        if (canCancel) {
            // use data attribute and delegated handler or global function
            actionButtons += `
                <button data-booking-id="${booking.booking_id}" class="btn-cancelar">
                    Cancelar
                </button>`;
        }

        // Build HTML with onerror fallback for img
        const imgTag = `<img src="${sitterPhotoUrl}" alt="Foto Sitter" class="sitter-thumb" onerror="this.onerror=null;this.src='${DEFAULT_PHOTO_URL}';">`;

        return `
            <div class="booking-card" data-booking-id="${booking.booking_id}">
                <div class="booking-header">
                    <h4>Reserva com ${sitterName}</h4>
                    ${formatStatusBadge(booking.status_reserva)}
                </div>
                <div class="booking-sitter-info">
                    ${imgTag}
                    <span>Babysitter: <strong>${sitterName}</strong></span>
                </div>
                <div class="booking-details">
                    <p>Início: <strong>${startDate}</strong></p>
                    <p>Fim: <strong>${endDate}</strong></p>
                    <p>Total: <strong class="price">${formatPrice(booking.montante_total)}</strong></p>
                </div>
                <div class="booking-actions">${actionButtons}</div>
            </div>
        `;
    }

    async function loadParentBookings() {
        if (!bookingsContainer) return;
        bookingsContainer.innerHTML = '<p>A carregar reservas...</p>';

        try {
            const response = await fetch(BOOKINGS_API_URL, { credentials: 'include' });
            const data = await response.json();

            if (data.success && Array.isArray(data.bookings) && data.bookings.length > 0) {
                bookingsContainer.innerHTML = data.bookings.map(renderBookingCard).join('');
                // attach cancel listeners (delegated)
                bookingsContainer.querySelectorAll('.btn-cancelar').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const id = this.dataset.bookingId;
                        cancelBooking(id);
                    });
                });
            } else {
                bookingsContainer.innerHTML = `<p>Nenhuma reserva encontrada.</p>`;
            }
        } catch (error) {
            console.error('Erro ao carregar bookings:', error);
            bookingsContainer.innerHTML = `<p class="error">Erro ao carregar dados.</p>`;
        }
    }

    // Expor função global se necessário (compatibilidade)
    window.cancelBooking = cancelBooking;
    loadParentBookings();
});