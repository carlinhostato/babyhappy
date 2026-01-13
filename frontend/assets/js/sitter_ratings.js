// sitter_ratings.js - VERSÃO FINAL COMPLETA

document.addEventListener('DOMContentLoaded', function() {
    
    // --- CONFIGURAÇÃO DA API ---
    const PROJECT_ROOT = '/babyhappy_v1/'; 
    const GET_SITTER_API = `${PROJECT_ROOT}api/auth/get_sitter_details.php`;
    const RATINGS_API_URL = `${PROJECT_ROOT}api/auth/sitter_ratings_api.php`; 

    // --- ELEMENTOS HTML ---
    const sitterPhoto = document.getElementById('sitter-photo');
    const sitterName = document.getElementById('sitter-name');
    const sitterPrice = document.getElementById('sitter-price');
    const ratingsCountHeader = document.getElementById('ratings-count-header');
    const ratingsCountDisplay = document.getElementById('ratings-count-display');
    const ratingsList = document.getElementById('ratings-list');
    const feedbackContainer = document.getElementById('feedback-message-container');
    
    // --- FUNÇÕES AUXILIARES ---

    function formatPrice(price) {
        return new Intl.NumberFormat('pt-PT', {
            style: 'currency',
            currency: 'EUR'
        }).format(price);
    }

    function renderStars(ratingValue) {
        const value = parseFloat(ratingValue) || 0;
        const roundedRating = Math.round(value);
        let starsHtml = '';
        for (let i = 1; i <= 5; i++) {
            const color = i <= roundedRating ? 'gold' : '#ddd';
            starsHtml += `<span class="text-2xl" style="color: ${color};">★</span>`;
        }
        return starsHtml;
    }

    function showFeedback(message, type = 'success') {
        if (!feedbackContainer) return;

        const bgColor = type === 'success' 
            ? 'bg-green-50 border-green-400 text-green-800' 
            : 'bg-red-50 border-red-400 text-red-800';
        const icon = type === 'success' ? '✅' : '❌';

        feedbackContainer.innerHTML = `
            <div class="card p-4 mb-6 border-l-4 ${bgColor}">
                <p class="font-medium">${icon} ${message}</p>
            </div>
        `;

        setTimeout(() => {
            feedbackContainer.innerHTML = '';
        }, 5000);
    }

    // --- 1. EXTRAIR O ID DA URL ---
    const urlParams = new URLSearchParams(window.location.search);
    const sitterId = urlParams.get('id');

    if (!sitterId) {
        if (sitterName) sitterName.textContent = '❌ Erro: ID não fornecido';
        showFeedback('❌ ID do Babysitter não especificado na URL', 'error');
        return;
    }

    // --- 2. CARREGAR DADOS COMPLETOS (PERFIL + AVALIAÇÕES) ---
    async function loadSitterData(id) {
        try {
            const response = await fetch(`${GET_SITTER_API}?id=${id}`);
            
            if (!response.ok) {
                throw new Error(`Erro HTTP ${response.status}`);
            }

            const data = await response.json();
            
            if (data.success && data.sitter) {
                const sitter = data.sitter;
                
                // Atualizar nome
                if (sitterName) {
                    sitterName.textContent = sitter.nome_completo || 'Babysitter';
                }

                // Atualizar preço
                if (sitterPrice) {
                    const price = parseFloat(sitter.preco_hora) || 0;
                    sitterPrice.textContent = formatPrice(price) + '/h';
                }

                // Atualizar foto
                if (sitterPhoto) {
                    let imageUrl = sitter.photo_url || `${PROJECT_ROOT}frontend/assets/images/default_profile.png`;
                    sitterPhoto.src = imageUrl;
                    sitterPhoto.alt = `Foto de ${sitter.nome_completo}`;
                }

                // Atualizar título da página
                document.title = `Avaliações - ${sitter.nome_completo} | BabyHappy`;

                // Carregar avaliações
                if (data.reviews) {
                    renderRatings(data.reviews);
                } else {
                    if (ratingsList) {
                        ratingsList.innerHTML = '<p class="text-gray-500 text-center py-8">📝 Ainda não há avaliações.</p>';
                    }
                }

                return sitter;

            } else {
                throw new Error(data.message || 'Babysitter não encontrado');
            }

        } catch (error) {
            console.error('❌ Erro ao carregar dados:', error);
            
            if (sitterName) {
                sitterName.textContent = '❌ Erro ao carregar';
            }
            if (sitterPrice) {
                sitterPrice.textContent = '0,00 €/h';
            }
            
            showFeedback(`❌ Erro ao carregar dados: ${error.message}`, 'error');
            return null;
        }
    }

    // --- 3. RENDERIZAR LISTA DE AVALIAÇÕES ---
    function renderRatings(ratings) {
        if (!ratingsList) return;

        // Atualizar contadores
        const count = ratings.length;
        if (ratingsCountHeader) {
            ratingsCountHeader.textContent = `${count} ${count === 1 ? 'avaliação' : 'avaliações'}`;
        }
        if (ratingsCountDisplay) {
            ratingsCountDisplay.textContent = `${count} total`;
        }

        if (ratings.length === 0) {
            ratingsList.innerHTML = '<p class="text-gray-500 text-center py-8">📝 Ainda não há avaliações.</p>';
            return;
        }

        ratingsList.innerHTML = ratings.map(rating => {
            const parentName = rating.avaliador_nome || `Utilizador #${rating.avaliador_id}`;
            const ratingValue = parseFloat(rating.rating_value || rating.rating) || 0;
            const comment = rating.comment || rating.comentario || '';
            const date = new Date(rating.data_avaliacao || rating.created_at).toLocaleDateString('pt-PT');
            const photoUrl = rating.parent_photo || `${PROJECT_ROOT}frontend/assets/images/default_profile.png`;
            
            const starsHtml = renderStars(ratingValue);
            const isOwner = rating.is_owner || false;

            return `
                <div class="border border-gray-200 rounded-lg p-4 bg-gray-50 hover:bg-gray-100 transition-colors">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center space-x-3">
                            <img src="${photoUrl}" 
                                 alt="${parentName}" 
                                 class="w-10 h-10 rounded-full object-cover border-2 border-blue-200">
                            <div>
                                <p class="font-semibold text-gray-800">${parentName}</p>
                                <p class="text-xs text-gray-500">${date}</p>
                            </div>
                        </div>
                        ${isOwner ? `
                            <div class="flex gap-2">
                                <button onclick="editRating(${rating.id}, ${ratingValue}, '${comment.replace(/'/g, "\\'")}');" 
                                        class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                    ✏️ Editar
                                </button>
                                <button onclick="deleteRating(${rating.id});" 
                                        class="text-red-600 hover:text-red-800 text-sm font-medium">
                                    🗑️ Apagar
                                </button>
                            </div>
                        ` : ''}
                    </div>
                    <div class="mb-2">${starsHtml}</div>
                    ${comment ? `<p class="text-gray-700 text-sm mt-2">${comment}</p>` : ''}
                </div>
            `;
        }).join('');
    }

    // --- 4. SISTEMA DE ESTRELAS INTERATIVAS ---
    function setupStarPicker(containerId, inputId, displayId) {
        const container = document.getElementById(containerId);
        const input = document.getElementById(inputId);
        const display = document.getElementById(displayId);
        
        if (!container || !input) return;

        let selectedRating = 0;

        container.innerHTML = '';
        for (let i = 1; i <= 5; i++) {
            const star = document.createElement('span');
            star.className = 'text-3xl cursor-pointer hover:scale-110 transition-transform';
            star.style.color = '#ddd';
            star.textContent = '★';
            star.dataset.rating = i;
            
            star.addEventListener('click', function() {
                selectedRating = i;
                input.value = i;
                
                const allStars = container.querySelectorAll('span');
                allStars.forEach((s, idx) => {
                    s.style.color = (idx < i) ? 'gold' : '#ddd';
                });
                
                if (display) {
                    display.textContent = i;
                    const displayBox = document.getElementById(displayId + '-box');
                    if (displayBox) {
                        displayBox.classList.remove('hidden');
                    }
                }
                
                const errorElement = document.getElementById(inputId.replace('input', 'error'));
                if (errorElement) {
                    errorElement.classList.add('hidden');
                }
            });
            
            container.appendChild(star);
        }
    }

    // --- 5. ADICIONAR NOVA AVALIAÇÃO ---
    const addRatingForm = document.getElementById('add-rating-form');
    if (addRatingForm) {
        addRatingForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const ratingValue = document.getElementById('add-rating-input').value;
            if (!ratingValue) {
                const errorElement = document.getElementById('add-rating-error');
                if (errorElement) {
                    errorElement.classList.remove('hidden');
                }
                return;
            }

            const formData = new FormData(this);
            formData.append('sitter_id', sitterId);

            try {
                const response = await fetch(RATINGS_API_URL, {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    showFeedback('✅ Avaliação adicionada com sucesso!', 'success');
                    
                    this.reset();
                    document.getElementById('add-rating-input').value = '';
                    document.getElementById('add-rating-display-box').classList.add('hidden');
                    setupStarPicker('simple-star-picker', 'add-rating-input', 'add-rating-display');
                    
                    // Recarregar dados
                    loadSitterData(sitterId);
                } else {
                    showFeedback(`❌ ${result.message || 'Erro ao adicionar avaliação'}`, 'error');
                }
            } catch (error) {
                console.error('❌ Erro:', error);
                showFeedback('❌ Erro ao comunicar com o servidor', 'error');
            }
        });
    }

    // --- 6. EDITAR AVALIAÇÃO ---
    window.editRating = function(ratingId, currentRating, currentComment) {
        const editContainer = document.getElementById('edit-rating-form-container');
        const editIdInput = document.getElementById('edit-rating-id');
        const editRatingInput = document.getElementById('edit-rating-input');
        const editCommentInput = document.getElementById('edit-comentario');
        const editDisplay = document.getElementById('edit-rating-display');

        if (editContainer && editIdInput && editRatingInput && editCommentInput) {
            editContainer.classList.remove('hidden');
            editIdInput.value = ratingId;
            editRatingInput.value = currentRating;
            editCommentInput.value = currentComment;
            editDisplay.textContent = currentRating;

            const starPicker = document.getElementById('edit-star-picker');
            if (starPicker) {
                const allStars = starPicker.querySelectorAll('span');
                allStars.forEach((star, idx) => {
                    star.style.color = (idx < currentRating) ? 'gold' : '#ddd';
                });
            }

            editContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    };

    // --- 7. FORM DE EDIÇÃO ---
    const editRatingForm = document.getElementById('edit-rating-form');
    if (editRatingForm) {
        editRatingForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            try {
                const response = await fetch(RATINGS_API_URL, {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    showFeedback('✅ Avaliação atualizada com sucesso!', 'success');
                    document.getElementById('edit-rating-form-container').classList.add('hidden');
                    loadSitterData(sitterId);
                } else {
                    showFeedback(`❌ ${result.message || 'Erro ao atualizar'}`, 'error');
                }
            } catch (error) {
                console.error('❌ Erro:', error);
                showFeedback('❌ Erro ao comunicar com o servidor', 'error');
            }
        });
    }

    // --- 8. CANCELAR EDIÇÃO ---
    const cancelEditBtn = document.getElementById('cancel-edit-btn');
    if (cancelEditBtn) {
        cancelEditBtn.addEventListener('click', function() {
            document.getElementById('edit-rating-form-container').classList.add('hidden');
        });
    }

    // --- 9. APAGAR AVALIAÇÃO ---
    window.deleteRating = async function(ratingId) {
        if (!confirm('Tem a certeza que deseja apagar esta avaliação?')) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'delete_rating');
        formData.append('rating_id', ratingId);

        try {
            const response = await fetch(RATINGS_API_URL, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                showFeedback('✅ Avaliação apagada com sucesso!', 'success');
                loadSitterData(sitterId);
            } else {
                showFeedback(`❌ ${result.message || 'Erro ao apagar'}`, 'error');
            }
        } catch (error) {
            console.error('❌ Erro:', error);
            showFeedback('❌ Erro ao comunicar com o servidor', 'error');
        }
    };

    // --- INICIALIZAÇÃO ---
    loadSitterData(sitterId);
    setupStarPicker('simple-star-picker', 'add-rating-input', 'add-rating-display');
    setupStarPicker('edit-star-picker', 'edit-rating-input', 'edit-rating-display');
});