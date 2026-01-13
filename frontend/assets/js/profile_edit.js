// ../js/profile_edit.js
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('profile-edit-form');
    const feedbackMessage = document.getElementById('feedback-message');
    const photoDisplay = document.getElementById('profile-photo-display');
    const babysitterFields = document.getElementById('babysitter-fields');
    
    const API_URLS = {
        FETCH: '/babyhappy_v1/api/auth/fetch_profile.php', 
        UPDATE: '/babyhappy_v1/api/auth/update_profile.php' 
    };

    let currentUserRole = '';

    // --- Função Auxiliar para evitar o erro de "null" ---
    function setFieldValue(id, value) {
        const element = document.getElementById(id);
        if (element) {
            element.value = value || '';
        }
    }

    // --- Função para Corrigir o Caminho da Imagem ---
    function getCorrectPhotoUrl(rawPath) {
        if (!rawPath) return '/babyhappy_v1/frontend/assets/images/default_profile.png';
        if (rawPath.startsWith('http')) return rawPath;
        
        // Garante que o caminho começa com /babyhappy_v1/
        const cleanPath = rawPath.startsWith('/') ? rawPath.substring(1) : rawPath;
        if (cleanPath.startsWith('babyhappy_v1/')) {
            return '/' + cleanPath;
        }
        return '/babyhappy_v1/' + cleanPath;
    }
    
    // --- Funções de Feedback ---
    function showFeedback(message, type = 'success') {
        if (!feedbackMessage) return;
        feedbackMessage.textContent = message;
        feedbackMessage.className = `message ${type}`;
        feedbackMessage.style.display = 'block';
        
        setTimeout(() => {
            feedbackMessage.style.display = 'none';
        }, 5000);
    }

    // --- 1. Carregar Dados Atuais ---
    function loadProfileData() {
        fetch(API_URLS.FETCH)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Falha na resposta da rede. Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const user = data.data;
                    currentUserRole = user.role; 
                    
                    setFieldValue('user-id-field', user.user_id);
                    setFieldValue('nome_completo', user.nome_completo);
                    setFieldValue('email', user.email);
                    setFieldValue('phone', user.phone);
                    setFieldValue('localizacao', user.localizacao);

                    // CORREÇÃO DA IMAGEM AQUI
                    if (photoDisplay) {
                        const correctUrl = getCorrectPhotoUrl(user.photo_url);
                        photoDisplay.src = correctUrl + '?' + new Date().getTime(); 
                    }
                    
                    if (user.role === 'babysitter' && babysitterFields) {
                        babysitterFields.style.display = 'block';
                        setFieldValue('bio', user.bio);
                        setFieldValue('experiencia', user.experiencia);
                        setFieldValue('disponibilidade', user.disponibilidade);
                        setFieldValue('proximidade', user.proximidade);
                    } else if (babysitterFields) {
                        babysitterFields.style.display = 'none';
                    }

                } else {
                    showFeedback('Erro ao carregar dados do perfil: ' + data.message, 'error');
                }
            })
            .catch((error) => {
                console.error("Erro de comunicação:", error);
                showFeedback(`Erro ao carregar dados. Detalhe: ${error.message}`, 'error');
            });
    }

    // --- 2. Processar Envio do Formulário ---
    if (form) {
        form.addEventListener('submit', function(event) {
            event.preventDefault();
            
            const formData = new FormData(form);
            const password = formData.get('password');
            const confirmPassword = formData.get('confirm_password');
            
            if (password && password !== confirmPassword) {
                showFeedback('A confirmação da password não corresponde.', 'error');
                return;
            }

            const submitBtn = form.querySelector('.btn-submit');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'A Guardar...';
            }

            fetch(API_URLS.UPDATE, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                 if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Guardar Alterações';
                 }
                 if (!response.ok) throw new Error(`Status: ${response.status}`);
                 return response.json();
            })
            .then(data => {
                if (data.success) {
                    showFeedback(data.message, 'success');
                    
                    if (data.new_photo_url && photoDisplay) {
                        const correctUrl = getCorrectPhotoUrl(data.new_photo_url);
                        photoDisplay.src = correctUrl + '?' + new Date().getTime(); 
                    }
                    
                    setFieldValue('password', '');
                    setFieldValue('confirm_password', '');
                    setFieldValue('new_photo', ''); 
                    
                } else {
                    showFeedback('Falha na atualização: ' + data.message, 'error');
                }
            })
            .catch((error) => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Guardar Alterações';
                }
                console.error("Erro de comunicação:", error);
                showFeedback(`Erro ao tentar atualizar. Detalhe: ${error.message}`, 'error');
            });
        });
    }

    loadProfileData();
});