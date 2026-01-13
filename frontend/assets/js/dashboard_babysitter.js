  // --- Carregamento de Dados (loadProfileData permanece igual) ---

    async function loadProfileData() {
        try {
            const response = await fetch(API_URL_FETCH);
            
            if (!response.ok) {
                throw new Error(`Erro de rede: ${response.status}`);
            }

            const data = await response.json();

            if (data.success) {
                const nome = data.nome_completo || 'Utilizador(a)';
                
                if (welcomeDisplay) {
                    welcomeDisplay.textContent = `Bem-vindo(a), ${nome}`;
                }

                document.getElementById('nome_completo').value = data.nome_completo || '';
                document.getElementById('email').value = data.email || '';
                document.getElementById('localizacao').value = data.localizacao || '';

                const photoUrl = data.photo_url || DEFAULT_AVATAR_URL;
                previewImage.src = photoUrl.startsWith('http') || photoUrl.startsWith('/') ? photoUrl : DEFAULT_AVATAR_URL;

                if (data.experiencia) {
                    const expRadio = document.querySelector(`input[name="experiencia"][value="${data.experiencia}"]`);
                    if (expRadio) {
                        expRadio.checked = true;
                    }
                }

                if (data.disponibilidade) {
                    document.getElementById('disponibilidade').value = data.disponibilidade;
                }
                
                // Habilitar o botão de submissão se o carregamento for OK
                submitBtn.disabled = false;
            } else {
                showFeedback(`Erro ao carregar dados do perfil: ${data.message}`, 'error');
            }
        } catch (error) {
            console.error('Erro de rede ou API na busca:', error);
            if (welcomeDisplay) {
                 welcomeDisplay.textContent = 'Bem-vindo(a), Erro de Carregamento';
            }
            showFeedback('Erro de comunicação com o servidor ao carregar dados.', 'error');
            // Mesmo com erro de carregamento, podemos permitir a submissão
            if (submitBtn) {
                submitBtn.disabled = false;
            }
        }
    }