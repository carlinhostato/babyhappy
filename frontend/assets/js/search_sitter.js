// search_sitter.js - Versão Corrigida com URLs de Imagem Consistentes

// --- CONFIGURAÇÃO DE URLS ---
const PROJECT_ROOT = '/babyhappy_v1/'; 
const API_FOLDER = 'api/auth/'; 
const SEARCH_API_URL = `${PROJECT_ROOT}${API_FOLDER}get_sitters.php`;
const BOOKING_API_URL = `${PROJECT_ROOT}${API_FOLDER}make_booking.php`;
const DEFAULT_AVATAR_URL = `${PROJECT_ROOT}frontend/assets/images/default-avatar.png`;

// --- REFERÊNCIAS DE ELEMENTOS GLOBAIS ---
const bookingModal = document.getElementById('booking-modal');
const bookingFeedback = document.getElementById('booking-feedback-message');
const modalSitterIdHidden = document.getElementById('modal-sitter-id');
const modalHourlyRateHidden = document.getElementById('modal-hourly-rate-hidden');
const dateInicioInput = document.getElementById('data_inicio');
const dateFimInput = document.getElementById('data_fim');
const modalHourlyRateDisplay = document.getElementById('modal-hourly-rate');
const modalDurationDisplay = document.getElementById('modal-duration');
const modalTotalCostDisplay = document.getElementById('modal-total-cost');
const submitBtn = document.getElementById('modal-submit-btn'); 

// --- FUNÇÃO PARA NORMALIZAR URL DE IMAGEM (NOVA) ---
function getCorrectPhotoUrl(rawPath) {
    if (!rawPath) return DEFAULT_AVATAR_URL;
    
    // Se já é URL completa (http/https)
    if (rawPath.startsWith('http://') || rawPath.startsWith('https://')) {
        return rawPath;
    }
    
    // Se já começa com /babyhappy_v1/
    if (rawPath.startsWith('/babyhappy_v1/')) {
        return rawPath;
    }
    
    // Se começa com babyhappy_v1/ (sem barra inicial)
    if (rawPath.startsWith('babyhappy_v1/')) {
        return '/' + rawPath;
    }
    
    // Se começa com frontend/
    if (rawPath.startsWith('frontend/')) {
        return PROJECT_ROOT + rawPath;
    }
    
    // Se começa com /frontend/
    if (rawPath.startsWith('/frontend/')) {
        return PROJECT_ROOT.replace(/\/$/, '') + rawPath;
    }
    
    // Se começa com public/uploads (legado)
    if (rawPath.startsWith('public/uploads')) {
        return PROJECT_ROOT + rawPath;
    }
    
    // Se começa com /public/uploads
    if (rawPath.startsWith('/public/uploads')) {
        return PROJECT_ROOT.replace(/\/$/, '') + rawPath;
    }
    
    // Fallback: adiciona o prefixo completo
    const cleanPath = rawPath.startsWith('/') ? rawPath.substring(1) : rawPath;
    return PROJECT_ROOT + cleanPath;
}

// --- FUNÇÕES AUXILIARES GLOBAIS ---

function formatPrice(price) {
    return new Intl.NumberFormat('pt-PT', { 
        style: 'currency', 
        currency: 'EUR' 
    }).format(price);
}

function renderStars(rating) {
    const roundedRating = Math.round(rating);
    let starsHtml = '';
    for (let i = 1; i <= 5; i++) {
        const color = i <= roundedRating ? 'gold' : '#ccc';
        starsHtml += `<span style="color: ${color};">★</span>`;
    }
    return starsHtml;
}

function showFeedback(message, type = 'success') {
    if (bookingFeedback) {
        bookingFeedback.innerHTML = `<p class="message-box ${type}">${message}</p>`;
        bookingFeedback.style.display = 'block';
        setTimeout(() => {
            bookingFeedback.style.display = 'none';
        }, 5000);
    }
}

function calculateCost() {
    if (!dateInicioInput || !dateFimInput || !modalHourlyRateHidden) return;
    
    const start = new Date(dateInicioInput.value);
    const end = new Date(dateFimInput.value);
    const hourlyRate = parseFloat(modalHourlyRateHidden.value);

    if (isNaN(hourlyRate) || start >= end) {
        if (modalDurationDisplay) modalDurationDisplay.textContent = '0 horas';
        if (modalTotalCostDisplay) modalTotalCostDisplay.textContent = formatPrice(0); 
        return;
    }

    const durationMs = end - start;
    const durationHours = durationMs / (1000 * 60 * 60);
    const totalCost = durationHours * hourlyRate;

    if (modalDurationDisplay) modalDurationDisplay.textContent = `${durationHours.toFixed(1)} horas`;
    if (modalTotalCostDisplay) modalTotalCostDisplay.textContent = formatPrice(totalCost);
}

function closeBookingModal() {
    if (bookingModal) {
        bookingModal.classList.remove('open');
        bookingModal.style.display = 'none';
    }
    const bookingForm = document.getElementById('booking-form'); 
    if (bookingForm) {
        bookingForm.reset(); 
    }
}

function openBookingModal(sitterId, sitterName, price) {
    if (!bookingModal || !modalHourlyRateDisplay) return;

    document.getElementById('sitter-name-display').textContent = sitterName;
    modalHourlyRateDisplay.textContent = formatPrice(price) + ' / Hora';
    
    modalSitterIdHidden.value = sitterId;
    modalHourlyRateHidden.value = price; 
    
    const now = new Date();
    const localDateTime = new Date(now.getTime() - (now.getTimezoneOffset() * 60000)).toISOString().slice(0, 16);
    dateInicioInput.setAttribute('min', localDateTime);
    dateFimInput.setAttribute('min', localDateTime);

    const bookingForm = document.getElementById('booking-form');
    if(bookingForm) bookingForm.reset(); 

    if (modalDurationDisplay) modalDurationDisplay.textContent = '0 horas';
    if (modalTotalCostDisplay) modalTotalCostDisplay.textContent = formatPrice(0);

    bookingModal.classList.add('open'); 
    bookingModal.style.display = 'flex';
}

function hideSuccessOverlay() {
    const successOverlayElement = document.getElementById('success-overlay');
    if (successOverlayElement) {
        successOverlayElement.style.display = 'none'; 
        successOverlayElement.classList.remove('is-active');
    }
}

// FUNÇÃO DA ANIMAÇÃO DO ZEZINHO
function showSuccessOverlay(message) {
    const successOverlayElement = document.getElementById('success-overlay');
    const overlayMessageElement = document.getElementById('overlay-message');

    if (!successOverlayElement || !overlayMessageElement) {
        showFeedback('✅ ' + message, 'success');
        return;
    }

    closeBookingModal();
    overlayMessageElement.textContent = "RESERVA SOLICITADA COM SUCESSO";

    successOverlayElement.style.display = 'flex'; 
    successOverlayElement.classList.add('is-active'); 
    
    setTimeout(hideSuccessOverlay, 8000); 
}

// --- FUNÇÃO PRINCIPAL: RENDERIZAR CARD DO BABYSITTER (CORRIGIDA) ---
function renderSitterCard(sitterData) {
    const rating = parseFloat(sitterData.media_avaliacao) || 0;
    const profileLink = `${PROJECT_ROOT}frontend/pages/sitter_profile_view.html?id=${sitterData.user_id}`;
    
    // CORREÇÃO CRÍTICA: Usa a função getCorrectPhotoUrl
    const imageUrl = getCorrectPhotoUrl(sitterData.photo_url);

    const cardDiv = document.createElement('div');
    cardDiv.className = 'sitter-card-new';
    cardDiv.dataset.sitterId = sitterData.user_id;
    cardDiv.dataset.sitterPrice = sitterData.preco_hora || 0;
    cardDiv.dataset.sitterName = sitterData.nome_completo;

    const infoSection = document.createElement('div');
    infoSection.className = 'sitter-info-section';
    
    // Imagem com fallback automático
    const img = document.createElement('img');
    img.src = imageUrl;
    img.alt = `Foto de ${sitterData.nome_completo}`;
    img.className = 'sitter-photo-new';
    
    // Fallback em caso de erro ao carregar
    img.onerror = function() {
        console.warn(`⚠️ Erro ao carregar foto do sitter ${sitterData.user_id}, usando padrão`);
        this.src = DEFAULT_AVATAR_URL;
        this.onerror = null; // Evita loop infinito
    };
    
    infoSection.appendChild(img);
    
    const nameLink = document.createElement('a');
    nameLink.href = profileLink;
    nameLink.className = 'sitter-name';
    nameLink.textContent = sitterData.nome_completo;
    infoSection.appendChild(nameLink);
    
    const locationDiv = document.createElement('div');
    locationDiv.className = 'sitter-location';
    locationDiv.textContent = sitterData.localizacao || 'N/D';
    infoSection.appendChild(locationDiv);
    
    const ratingDiv = document.createElement('div');
    ratingDiv.className = 'sitter-rating';
    ratingDiv.innerHTML = `${renderStars(rating)} (${rating.toFixed(1)})`; 
    infoSection.appendChild(ratingDiv);

    const descriptionP = document.createElement('p');
    descriptionP.className = 'sitter-description-short';
    const descText = sitterData.descricao || 'Sem descrição disponível.';
    descriptionP.textContent = descText.length > 95 ? descText.substring(0, 95) + '...' : descText;
    descriptionP.style.cssText = 'font-size: 0.82em; color: #666; margin: 10px 0; line-height: 1.4; height: 38px; overflow: hidden;';
    infoSection.appendChild(descriptionP);
    
    const priceSpan = document.createElement('span');
    priceSpan.className = 'price-highlight';
    priceSpan.textContent = formatPrice(sitterData.preco_hora || 0) + ' / Hora';
    infoSection.appendChild(priceSpan);
    
    cardDiv.appendChild(infoSection);

    const divider = document.createElement('div');
    divider.className = 'sitter-divider';
    cardDiv.appendChild(divider);

    const detailsDiv = document.createElement('div');
    detailsDiv.className = 'sitter-details';
    
    const detailsMap = [
        { label: 'Experiência:', value: sitterData.experiencia || 'Básica' },
        { label: 'Proximidade:', value: sitterData.proximidade_calculada || 'N/D' },
        { label: 'Disponibilidade:', value: sitterData.disponibilidade || 'N/D' }
    ];

    detailsMap.forEach(item => {
        const detailItem = document.createElement('div');
        detailItem.className = 'sitter-detail-item';
        detailItem.innerHTML = `<span class="sitter-detail-label">${item.label}</span> <span class="sitter-detail-value">${item.value}</span>`;
        detailsDiv.appendChild(detailItem);
    });

    cardDiv.appendChild(detailsDiv);
    
    const buttonsDiv = document.createElement('div');
    buttonsDiv.className = 'sitter-buttons';
    
    const reserveBtn = document.createElement('button');
    reserveBtn.className = 'button-primary open-booking-modal';
    reserveBtn.type = 'button';
    reserveBtn.textContent = '✓ Reservar';
    buttonsDiv.appendChild(reserveBtn);
    
    const inlineBtnsDiv = document.createElement('div');
    inlineBtnsDiv.style.cssText = 'display:flex; gap:8px;';
    inlineBtnsDiv.innerHTML = `
        <a href="${PROJECT_ROOT}frontend/pages/chat_dashboard.html?chat_with=${sitterData.user_id}" class="button-secondary flex-1">💬 Chat</a>
        <a href="${PROJECT_ROOT}frontend/pages/sitter_ratings.html?id=${sitterData.user_id}" class="button-secondary flex-1">⭐ Avaliar</a>
    `;
    
    buttonsDiv.appendChild(inlineBtnsDiv);
    cardDiv.appendChild(buttonsDiv);

    return cardDiv; 
}

// --- EVENT LISTENERS E CARREGAMENTO INICIAL ---
document.addEventListener('DOMContentLoaded', function() {
    
    const filterForm = document.getElementById('filter-form'); 
    const queryInput = document.getElementById('query'); 
    const headerSearchInput = document.getElementById('header-search-input'); 
    const clearFiltersBtn = document.getElementById('clear-filters-btn');
    const sittersGrid = document.getElementById('sitters-grid');
    const sitterCountMessage = document.getElementById('sitter-count-message');
    const closeBtn = document.getElementById('close-modal-btn'); 
    const closeOverlayBtn = document.getElementById('overlay-close-btn'); 

    const proximidadeFilter = document.getElementById('proximidade');
    const disponibilidadeFilter = document.getElementById('disponibilidade');
    const experienciaFilter = document.getElementById('experiencia');
    
    async function loadSitters(filters) {
        const urlParams = new URLSearchParams(filters);
        const url = `${SEARCH_API_URL}?${urlParams.toString()}`;

        if(sittersGrid) sittersGrid.innerHTML = '';
        if(sitterCountMessage) sitterCountMessage.textContent = 'A pesquisar...';

        try {
            const response = await fetch(url);
            const result = await response.json();

            console.log('📦 Sitters recebidos:', result.sitters?.length || 0);

            if (result.success && result.sitters) {
                sittersGrid.innerHTML = '';
                if (result.sitters.length === 0) {
                    sitterCountMessage.textContent = `Nenhum babysitter encontrado.`;
                } else {
                    sitterCountMessage.textContent = `${result.sitters.length} babysitters encontrados.`;
                    result.sitters.forEach(sitter => {
                        sittersGrid.appendChild(renderSitterCard(sitter));
                    });
                }
            } else {
                console.error('❌ Erro na resposta:', result.message);
                sitterCountMessage.textContent = 'Erro ao carregar babysitters.';
            }
        } catch (error) {
            console.error('❌ Erro na requisição:', error);
            sitterCountMessage.textContent = 'Erro de conexão.';
        }
    }

    function getCurrentFilters(queryValue = '') {
        return {
            query: queryValue.trim(),
            proximidade: proximidadeFilter?.value || '',
            disponibilidade: disponibilidadeFilter?.value || '',
            experiencia: experienciaFilter?.value || ''
        };
    }

    filterForm?.addEventListener('submit', function(e) {
        e.preventDefault();
        loadSitters(getCurrentFilters(queryInput.value));
    });

    clearFiltersBtn?.addEventListener('click', function() {
        filterForm.reset();
        loadSitters({});
    });

    sittersGrid?.addEventListener('click', function(e) {
        const btn = e.target.closest('.open-booking-modal');
        if (btn) {
            const card = btn.closest('.sitter-card-new');
            openBookingModal(card.dataset.sitterId, card.dataset.sitterName, parseFloat(card.dataset.sitterPrice));
        }
    });

    closeBtn?.addEventListener('click', closeBookingModal);
    closeOverlayBtn?.addEventListener('click', hideSuccessOverlay);
    dateInicioInput?.addEventListener('change', calculateCost);
    dateFimInput?.addEventListener('change', calculateCost);

    const bookingFormLocal = document.getElementById('booking-form'); 
    bookingFormLocal?.addEventListener('submit', async function(e) { 
        e.preventDefault();
        
        if (!submitBtn) return;
        
        submitBtn.textContent = 'A Enviar...';
        submitBtn.disabled = true;

        try {
            const response = await fetch(BOOKING_API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    sitter_id: modalSitterIdHidden.value,
                    data_inicio: dateInicioInput.value,
                    data_fim: dateFimInput.value,
                    preco_hora: modalHourlyRateHidden.value
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                showSuccessOverlay('RESERVA SOLICITADA COM SUCESSO');
            } else {
                alert(result.message || 'Erro ao criar reserva.');
            }
        } catch (error) {
            console.error('❌ Erro na reserva:', error);
            alert('Erro de conexão ao solicitar reserva.');
        } finally {
            submitBtn.textContent = 'Solicitar Reserva';
            submitBtn.disabled = false;
        }
    });

    // Carrega os babysitters ao inicializar
    console.log('🚀 Iniciando carregamento de babysitters...');
    loadSitters({});
});