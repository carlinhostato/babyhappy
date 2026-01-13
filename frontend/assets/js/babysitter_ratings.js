// assets/js/babysitter_ratings.js

document.addEventListener('DOMContentLoaded', () => {
    const averageRatingDisplay = document.getElementById('average-rating');
    const totalRatingsDisplay = document.getElementById('total-ratings');
    const ratingsListContainer = document.getElementById('ratings-list');
    const loadingMessage = document.getElementById('loading-message');
    const noRatingsMessage = document.getElementById('no-ratings-message');
    const feedbackMessage = document.getElementById('feedback-message');
    const welcomeMessageDisplay = document.getElementById('welcome-message-display');

    // Caminhos da API (Ajuste a raiz conforme necessário!)
    const API_ROOT = window.location.origin + '/babyhappy_v1/'; 
    const API_URLS = {
        FETCH_RATINGS: API_ROOT + 'api/auth/fetch_babysitter_ratings.php',
        FETCH_NAME: API_ROOT + 'api/auth/fetch_user_name.php' // Assumindo que este ficheiro existe
    };

    function showFeedback(message, type = 'error') {
        feedbackMessage.textContent = message;
        feedbackMessage.className = `message ${type}`;
        feedbackMessage.style.display = 'block';
        setTimeout(() => feedbackMessage.style.display = 'none', 5000);
    }

    function renderStars(rating) {
        let stars = '';
        for (let i = 0; i < rating; i++) {
            stars += '<span class="rating-star">★</span>'; // Estrela cheia
        }
        for (let i = rating; i < 5; i++) {
            stars += '<span style="color:#e9ecef;">★</span>'; // Estrela vazia
        }
        return stars;
    }

    function renderRatingCard(rating) {
        const date = new Date(rating.data_avaliacao);
        const formattedDate = date.toLocaleDateString('pt-PT') + ' ' + date.toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' });

        return `
            <div class="rating-card">
                <div class="rating-score">
                    ${renderStars(parseInt(rating.rating))}
                </div>
                <p class="text-base mt-2">${rating.comentario || 'Sem comentário.'}</p>
                <p class="rating-info">
                    De: <strong>${rating.remetente_nome}</strong>
                    | Em: ${formattedDate}
                </p>
            </div>
        `;
    }
    
    // --- Lógica de Carregamento de Avaliações ---
    async function loadRatings() {
        loadingMessage.style.display = 'block';
        ratingsListContainer.innerHTML = '';
        noRatingsMessage.style.display = 'none';

        try {
            // Se o seu sistema precisar de passar o user_id:
            // const urlParams = new URLSearchParams(window.location.search);
            // const userId = urlParams.get('user_id');
            // const response = await fetch(`${API_URLS.FETCH_RATINGS}?user_id=${userId}`);
            
            // Usando apenas a Sessão (como o PHP da API está configurado)
            const response = await fetch(API_URLS.FETCH_RATINGS);
            
            const data = await response.json();

            if (data.success && data.data) {
                const { total_ratings, average_rating, ratings_list } = data.data;

                averageRatingDisplay.textContent = average_rating.toFixed(1);
                totalRatingsDisplay.textContent = total_ratings;
                
                if (total_ratings > 0) {
                    ratings_list.forEach(rating => {
                        ratingsListContainer.innerHTML += renderRatingCard(rating);
                    });
                } else {
                    noRatingsMessage.style.display = 'block';
                }
            } else {
                showFeedback('Erro ao carregar avaliações: ' + (data.message || 'Resposta inválida da API'), 'error');
            }
        } catch (error) {
            console.error('Erro de rede ao carregar avaliações:', error);
            showFeedback('Erro de comunicação com o servidor. Verifique o caminho da API.', 'error');
        } finally {
            loadingMessage.style.display = 'none';
        }
    }
    
    // --- Lógica de Carregamento do Nome (já usada noutros ficheiros) ---
    async function fetchLoggedInUserName() {
        if (!welcomeMessageDisplay) return;
        try {
            const response = await fetch(API_URLS.FETCH_NAME); 
            const d = await response.json(); 
            if (d.success && d.nome) {
                welcomeMessageDisplay.textContent = `Bem-vindo(a), ${d.nome}`; 
            } else {
                welcomeMessageDisplay.textContent = 'Bem-vindo(a), Utilizador(a)';
            }
        } catch (error) {
            welcomeMessageDisplay.textContent = 'Bem-vindo(a), Erro';
        }
    }


    // --- Inicialização ---
    fetchLoggedInUserName();
    loadRatings();
});