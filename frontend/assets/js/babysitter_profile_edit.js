/**
 * assets/js/babysitter_profile_edit.js
 * Versão Corrigida: Resolução do problema de atualização de foto
 */

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('profile-edit-form');
    const submitBtn = document.getElementById('submit-btn');
    const previewImage = document.getElementById('preview-image');
    const welcomeDisplay = document.getElementById('welcome-message-display'); 
    const photoInput = document.getElementById('new_photo');
    
    // Configuração de URLs
    const API_URL_FETCH = '/babyhappy_v1/api/auth/fetch_babysitter_profile.php';
    const API_URL_UPDATE = '/babyhappy_v1/api/auth/update_profile.php'; 
    const DEFAULT_AVATAR_URL = '/babyhappy_v1/frontend/assets/images/default-avatar.png';

    // --- Função para Corrigir Path da Imagem (CRÍTICO) ---
    function getCorrectPhotoUrl(rawPath) {
        if (!rawPath) return DEFAULT_AVATAR_URL;
        
        // Se já é URL completa
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
            return '/babyhappy_v1/' + rawPath;
        }
        
        // Se começa com /frontend/
        if (rawPath.startsWith('/frontend/')) {
            return '/babyhappy_v1' + rawPath;
        }
        
        // Fallback: adiciona o prefixo completo
        const cleanPath = rawPath.startsWith('/') ? rawPath.substring(1) : rawPath;
        return '/babyhappy_v1/' + cleanPath;
    }

    // --- Funções de Feedback ---
    function showFeedback(message, type = 'success') {
        const container = document.getElementById('feedback-container');
        if (container) {
            const icon = type === 'success' ? '✅' : '❌';
            const bgColor = type === 'success' ? '#d4edda' : '#f8d7da';
            const textColor = type === 'success' ? '#155724' : '#721c24';
            
            container.innerHTML = `
                <div class="alert alert-${type}" style="padding:12px 15px; margin:15px 0; border-radius:8px; background:${bgColor}; color:${textColor}; border-left: 4px solid ${type==='success'?'#28a745':'#dc3545'}">
                    ${icon} ${message}
                </div>
            `;
            
            // Auto-hide após 5 segundos
            setTimeout(() => {
                container.innerHTML = '';
            }, 5000);
        } else {
            alert(message);
        }
    }
    
    // --- Função Global de Preview (MELHORADA) ---
    window.previewPhoto = function(event) {
        const file = event.target.files[0];
        
        if (!file) return;
        
        // Validação de tamanho (5MB máximo)
        const maxSize = 5 * 1024 * 1024; // 5MB em bytes
        if (file.size > maxSize) {
            showFeedback('⚠️ Ficheiro muito grande! Tamanho máximo: 5MB', 'error');
            event.target.value = ''; // Limpa o input
            return;
        }
        
        // Validação de tipo
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            showFeedback('⚠️ Formato inválido! Use: JPG, PNG, GIF ou WebP', 'error');
            event.target.value = '';
            return;
        }
        
        // Preview da imagem
        if (previewImage) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImage.src = e.target.result;
                console.log('✅ Preview carregado com sucesso');
            }
            reader.onerror = function() {
                showFeedback('Erro ao ler o ficheiro de imagem', 'error');
            }
            reader.readAsDataURL(file);
        }
    }

    // --- Função para Atualizar Imagem no DOM (NOVA) ---
    function updateProfileImage(photoUrl) {
        if (!previewImage) return;
        
        const correctUrl = getCorrectPhotoUrl(photoUrl);
        const timestamp = new Date().getTime();
        
        console.log('📸 Atualizando imagem:', correctUrl);
        
        // Atualiza com cache-busting
        previewImage.src = correctUrl + '?t=' + timestamp;
        
        // Fallback para imagem padrão em caso de erro
        previewImage.onerror = function() {
            console.warn('⚠️ Erro ao carregar imagem, usando padrão');
            this.src = DEFAULT_AVATAR_URL;
            this.onerror = null; // Evita loop infinito
        };
    }

    // --- Carregamento de Dados do Perfil ---
    async function loadProfileData() {
        try {
            console.log('🔄 Carregando dados do perfil...');
            
            const response = await fetch(API_URL_FETCH);
            if (!response.ok) {
                throw new Error(`Erro de rede: ${response.status} ${response.statusText}`);
            }

            const data = await response.json();
            console.log('📦 Dados recebidos:', data);

            if (data.success) {
                // 1. Atualiza o nome de boas-vindas
                if (welcomeDisplay) {
                    welcomeDisplay.textContent = `Bem-vindo(a), ${data.nome_completo || 'Utilizador(a)'}`;
                }

                // 2. Preenchimento Automático dos Campos
                const fields = {
                    'nome_completo': data.nome_completo,
                    'email': data.email,
                    'localizacao': data.localizacao,
                    'phone': data.phone,
                    'disponibilidade': data.disponibilidade,
                    'preco_hora': data.preco_hora,
                    'descricao': data.descricao
                };

                for (const [id, value] of Object.entries(fields)) {
                    const el = document.getElementById(id);
                    if (el) {
                        el.value = value || '';
                        console.log(`✓ Campo ${id} preenchido`);
                    }
                }

                // 3. Atualização da Foto de Perfil (CORRIGIDO)
                if (data.photo_url) {
                    updateProfileImage(data.photo_url);
                } else {
                    updateProfileImage(DEFAULT_AVATAR_URL);
                }

                // 4. Seleção do Rádio de Experiência
                if (data.experiencia) {
                    const expRadio = document.querySelector(`input[name="experiencia"][value="${data.experiencia}"]`);
                    if (expRadio) {
                        expRadio.checked = true;
                        console.log(`✓ Experiência selecionada: ${data.experiencia}`);
                    }
                }

                // 5. Habilita o botão de submit
                if (submitBtn) {
                    submitBtn.disabled = false;
                }

                console.log('✅ Perfil carregado com sucesso!');

            } else {
                showFeedback(`Erro ao carregar dados: ${data.message}`, 'error');
                console.error('❌ Erro na resposta:', data.message);
            }
        } catch (error) {
            console.error('❌ Erro crítico ao carregar perfil:', error);
            if (welcomeDisplay) {
                welcomeDisplay.textContent = 'Bem-vindo(a), Visitante';
            }
            showFeedback('Erro ao ligar ao servidor para carregar o seu perfil.', 'error');
        }
    }

    // --- Submissão do Formulário (MELHORADO) ---
    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            console.log('📤 Iniciando envio do formulário...');

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = '⏳ A Guardar...';
            }

            // Validação de Password
            const passEl = document.getElementById('password');
            const confEl = document.getElementById('confirm_password');
            const password = passEl ? passEl.value.trim() : '';
            const confirmPassword = confEl ? confEl.value.trim() : '';

            if (password) {
                if (password.length < 6) {
                    showFeedback('⚠️ A password deve ter pelo menos 6 caracteres.', 'error');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = '💾 Guardar Alterações';
                    }
                    return;
                }
                if (password !== confirmPassword) {
                    showFeedback('⚠️ As passwords não coincidem.', 'error');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = '💾 Guardar Alterações';
                    }
                    return;
                }
            }

            // Captura todos os campos
            const formData = new FormData(form);
            
            // Debug: Log dos dados sendo enviados
            console.log('📋 Dados do formulário:');
            for (let [key, value] of formData.entries()) {
                if (key === 'new_photo') {
                    console.log(`  ${key}:`, value.name || 'Sem ficheiro');
                } else {
                    console.log(`  ${key}: ${value}`);
                }
            }

            try {
                const response = await fetch(API_URL_UPDATE, {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error(`Erro HTTP: ${response.status}`);
                }
                
                const result = await response.json();
                console.log('📥 Resposta do servidor:', result);

                if (result.success) {
                    showFeedback(result.message || '✅ Perfil atualizado com sucesso!', 'success');
                    
                    // CRÍTICO: Atualiza a foto imediatamente se houver nova
                    if (result.new_photo_url) {
                        console.log('🖼️ Nova foto recebida:', result.new_photo_url);
                        updateProfileImage(result.new_photo_url);
                    }
                    
                    // Recarrega todos os dados para sincronizar
                    setTimeout(() => {
                        loadProfileData();
                    }, 500);
                    
                    // Limpa os campos de password
                    if (passEl) passEl.value = '';
                    if (confEl) confEl.value = '';
                    
                    // Limpa o input de ficheiro
                    if (photoInput) photoInput.value = '';
                    
                } else {
                    showFeedback(result.message || '❌ Erro ao guardar alterações.', 'error');
                }
            } catch (error) {
                console.error('❌ Erro na submissão:', error);
                showFeedback('Erro técnico ao tentar comunicar com o servidor.', 'error');
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = '💾 Guardar Alterações';
                }
            }
        });
    }

    // --- Inicialização ---
    console.log('🚀 Script inicializado');
    loadProfileData();
});