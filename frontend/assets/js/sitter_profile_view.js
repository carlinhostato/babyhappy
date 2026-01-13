// Ficheiro: ../assets/js/sitter_profile_view.js

document.addEventListener('DOMContentLoaded', function() {
    
    // ATENÇÃO CRÍTICA: Ajuste este caminho para o local exato do seu ficheiro PHP!
    // Assumimos que o API está na raiz do seu servidor /api/auth/
    const API_URL_BASE = '/api/auth/sitter_profile_fetch.php'; 
    const feedbackArea = document.getElementById('feedback-area');
    const profileContent = document.getElementById('profile-content');
    
    // Função para obter o ID da babysitter da URL
    function getSitterIdFromUrl() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('id');
    }

    // Função para renderizar estrelas
    function renderRatingStars(containerId, rating) {
        const container = document.getElementById(containerId);
        if (!container) return;

        let starsHtml = '';
        const roundedRating = Math.round(rating); // Arredonda para a estrela cheia mais próxima

        // Geração das estrelas (cheias/vazias)
        for (let i = 1; i <= 5; i++) {
            const color = i <= roundedRating ? '#ffc107' : '#ccc';
            starsHtml += `<span style="color: ${color};">★</span>`;
        }
        
        // Injeta as estrelas e o valor formatado
        container.innerHTML = starsHtml + `<span style="font-size: 0.8em; margin-left: 5px; font-weight: bold;">${(rating).toFixed(1)}</span>`;
    }

    // Função para mostrar feedback (erros/mensagens de carregamento)
    function showFeedback(message, type = 'error') {
        if (profileContent) profileContent.style.display = 'none';
        
        const typeClass = type === 'error' ? 'alert-error' : 'alert-success';
        const icon = type === 'error' ? '❌ ' : '✅ ';

        feedbackArea.innerHTML = `
            <p class="${typeClass}" style="padding: 10px; border-radius: 5px; margin: 10px 0;">
                ${icon} ${message}
            </p>
        `;
    }

    // Função principal para carregar dados
    async function loadProfile() {
        const sitterId = getSitterIdFromUrl();

        if (!sitterId) {
            showFeedback('ID da Babysitter em falta no endereço (URL).', 'error');
            return;
        }

        feedbackArea.innerHTML = '<p style="color: #3b76b2;">A carregar perfil...</p>';

        try {
            const response = await fetch(`${API_URL_BASE}?id=${sitterId}`);
            
            if (!response.ok) {
                // Tenta ler o erro do corpo da resposta, se não, usa o status
                const errorText = await response.text();
                // Assumindo que a API devolve JSON com 'message' em caso de erro HTTP
                try {
                    const errorData = JSON.parse(errorText);
                    throw new Error(errorData.message || `Erro HTTP ${response.status}`);
                } catch (e) {
                    throw new Error(`Erro de rede: ${response.status}. Detalhes: ${errorText.substring(0, 50)}...`);
                }
            }

            const result = await response.json();

            if (result.success && result.data) {
                const data = result.data;

                // 1. Preencher Cabeçalho e Foto
                document.getElementById('sitter-name').textContent = data.nome_completo;
                document.getElementById('sitter-location').textContent = data.localizacao || 'Localização Não Especifícada';
                document.getElementById('sitter-photo').src = data.photo_url;
                document.title = `${data.nome_completo} | BabyHappy`;

                // 2. Preencher Detalhes
                // Substitui quebras de linha (\n) por <br> para formatação no HTML
                document.getElementById('sitter-description').innerHTML = (data.descricao || 'Sem descrição.').replace(/\n/g, '<br>'); 
                
                // Formatação do preço (usando formatPrice se necessário, ou Intl.NumberFormat como no search_sitter.js)
                const precoFormatado = `${Number(data.preco_hora).toFixed(2).replace('.', ',')} €`;
                document.getElementById('sitter-price').textContent = precoFormatado;
                
                // Garantindo que a experiência é um número
                const experienciaAnos = Number(data.experiencia_anos) || 0;
                document.getElementById('sitter-experience').textContent = experienciaAnos;

                // 3. Renderizar Rating
                const ratingValue = parseFloat(data.media_rating) || 0;
                renderRatingStars('rating-stars', ratingValue);

                // 4. Botão de Ação: Ajuste o href para o seu processo de agendamento
                // É CRÍTICO que o ID do sitter seja passado para a página de agendamento
                const bookButton = document.getElementById('book-button');
                if (bookButton) {
                    bookButton.href = `booking_process.php?sitter_id=${data.sitter_id}&rate=${data.preco_hora}`;
                }


                // Mostrar o conteúdo e limpar feedback
                if (profileContent) profileContent.style.display = 'block';
                if (feedbackArea) feedbackArea.innerHTML = ''; 

            } else {
                showFeedback(result.message || 'Erro ao carregar os dados do perfil.', 'error');
            }

        } catch (error) {
            console.error('Erro na API:', error);
            showFeedback(`Erro ao carregar o perfil. Por favor, verifique a URL da API e o Console (F12). Detalhe: ${error.message}`, 'error');
        }
    }

    // Iniciar o carregamento
    loadProfile();
});