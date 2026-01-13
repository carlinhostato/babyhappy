document.addEventListener('DOMContentLoaded', () => {
    // ⚠️ ATENÇÃO: Defina o ID do babysitter que deseja carregar
    const SITTER_ID = 15; // Exemplo: ID do babysitter a ser visualizado
    
    // ⚠️ ATENÇÃO: Verifique o caminho CORRETO para o seu ficheiro PHP
    const API_URL = `/babyhappy_v1/api/ratings/ajax_get_ratings.php?sitter_id=${SITTER_ID}`;
    
    const ratingsContainer = document.getElementById('ratings-container');
    const errorDisplay = document.getElementById('error-message');
    const sitterIdDisplay = document.getElementById('sitter-id-display');

    if (sitterIdDisplay) {
        sitterIdDisplay.textContent = SITTER_ID;
    }

    // Função para gerar as estrelas com base no rating
    function generateStarRating(rating) {
        const fullStar = '★';
        const emptyStar = '☆';
        let stars = '';

        for (let i = 1; i <= 5; i++) {
            stars += (i <= rating) ? fullStar : emptyStar;
        }
        return stars;
    }
    
    // Função para formatar a data
    function formatRatingDate(dateString) {
        const options = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
        try {
            return new Date(dateString).toLocaleDateString('pt-PT', options);
        } catch (e) {
            return dateString; // Devolve a string original se falhar
        }
    }

    // Função principal para carregar e exibir as avaliações
    async function loadRatings() {
        ratingsContainer.innerHTML = '<p>A carregar avaliações...</p>';
        errorDisplay.style.display = 'none';

        try {
            const response = await fetch(API_URL);
            
            if (!response.ok) {
                throw new Error(`Erro HTTP: ${response.status}`);
            }

            const data = await response.json();

            if (data.success) {
                renderRatings(data.ratings);
            } else {
                // Erro devolvido pela API PHP (Ex: ID inválido)
                ratingsContainer.innerHTML = '';
                errorDisplay.textContent = '❌ Erro da API: ' + (data.error || 'Detalhes desconhecidos.');
                errorDisplay.style.display = 'block';
            }

        } catch (error) {
            // Erro de rede ou JSON inválido
            console.error('Falha ao obter avaliações:', error);
            ratingsContainer.innerHTML = '';
            errorDisplay.textContent = '❌ Falha de comunicação. Verifique a URL da API.';
            errorDisplay.style.display = 'block';
        }
    }

    // Função para renderizar as avaliações no container
    function renderRatings(ratings) {
        if (!ratings || ratings.length === 0) {
            ratingsContainer.innerHTML = '<p class="no-ratings">Este babysitter ainda não tem avaliações.</p>';
            return;
        }

        ratingsContainer.innerHTML = ''; // Limpa o "A carregar..."

        ratings.forEach(rating => {
            const ratingDiv = document.createElement('div');
            ratingDiv.classList.add('rating-item');

            const stars = generateStarRating(rating.rating);
            const formattedDate = formatRatingDate(rating.data_avaliacao);

            ratingDiv.innerHTML = `
                <div class="rating-header">
                    <span class="rating-author">${rating.remetente_nome}</span>
                    <span class="rating-stars">${stars}</span>
                </div>
                <p class="rating-comment">${rating.comentario}</p>
                <span class="rating-date">Avaliado em: ${formattedDate}</span>
            `;
            
            ratingsContainer.appendChild(ratingDiv);
        });
    }

    // Iniciar o carregamento
    loadRatings();
});