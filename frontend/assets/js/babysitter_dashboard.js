// frontend/js/dashboard_sitter.js

// CORREÇÃO: Definir API_BASE_URL para apontar para a pasta 'api'
const API_BASE_URL = 'http://localhost/babyhappy_v1/api/auth'; 
const DEFAULT_PHOTO_PATH = '../assets/images/default_profile.png'; 

document.addEventListener('DOMContentLoaded', function() {
    const mainContent = document.querySelector('.main-content');
    const bottomMenu = document.getElementById('bottom-menu');
    const welcomeMessage = document.getElementById('welcome-message');

    // Função principal: Verifica Auth e Inicia o Dashboard
    async function initDashboard() {
        try {
            // 1. Carregar Dados Completos do Perfil
            // CORREÇÃO: Caminho correto para a API de perfil (/user/...)
            const profileResponse = await fetch(`${API_BASE_URL}/user/get_profile_sitter.php`);
            
            if (profileResponse.status === 401) {
                window.location.href = 'login.html'; // Redireciona se não autorizado
                return;
            }
            
            const profileResult = await profileResponse.json();
            
            if (!profileResult.success) {
                throw new Error(profileResult.message || "Erro ao carregar dados do utilizador.");
            }
            
            const user = profileResult.user;
            localStorage.setItem('sitterProfile', JSON.stringify(user));
            
            // Atualiza a mensagem de boas-vindas
            welcomeMessage.textContent = `Bem-vindo(a), ${user.nome_completo}`;
            
            // 2. Iniciar a Navegação
            handleNavigation();
            
            // 3. Iniciar verificação de mensagens
            checkUnreadMessages();
            setInterval(checkUnreadMessages, 10000);

        } catch (error) {
            console.error("Falha na inicialização:", error);
            mainContent.innerHTML = `<p class="message error">❌ Erro crítico: ${error.message}.</p>`;
        }
    }

    // --- Lógica de Navegação ---
    function handleNavigation() {
        const hash = window.location.hash.replace('#', '');
        const section = hash || 'reservas'; 
        
        showSection(section);
        
        if (section === 'perfil') {
            renderProfileForm();
        }
        // * Implemente aqui a lógica para carregar as reservas (loadBookings()), pagamentos, etc. *
    }
    
    function showSection(sectionId) {
        // Esconde todas as secções
        document.querySelectorAll('.dashboard-section').forEach(section => {
            section.style.display = 'none';
        });
        
        // Mostra a secção alvo
        const targetSection = document.getElementById(`section-${sectionId}`);
        if (targetSection) {
            targetSection.style.display = 'block';
        } 
        
        // Atualiza o menu
        document.querySelectorAll('.nav-link').forEach(link => {
            link.classList.remove('active-section');
            if (link.dataset.section === sectionId) {
                link.classList.add('active-section');
            }
        });
        
        document.getElementById('section-loading').style.display = 'none';
        document.body.className = `section-${sectionId}`;
    }

    // --- Lógica de Renderização do Perfil ---
    function renderProfileForm() {
        const user = JSON.parse(localStorage.getItem('sitterProfile'));
        if (!user) return; 

        const detailsDiv = document.getElementById('profile-details-sitter');
        
        detailsDiv.innerHTML = `
            <div class="profile-photo-area">
                <img src="${user.photo_url || DEFAULT_PHOTO_PATH}" alt="Foto de Perfil" id="profile-img">
                <div class="form-group" style="margin-top: 10px;">
                    <label for="new_photo">Alterar Foto:</label>
                    <input type="file" id="new_photo" name="new_photo" accept="image/*">
                </div>
            </div>
            
            <input type="hidden" name="user_id" value="${user.user_id}">
            <div class="form-group">
                <label for="nome_completo">Nome Completo</label>
                <input type="text" id="nome_completo" name="nome_completo" value="${user.nome_completo}" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="${user.email}" required>
            </div>
            <div class="form-group">
                <label for="bio">Biografia</label>
                <textarea id="bio" name="bio">${user.bio || ''}</textarea>
            </div>
            <div class="form-group">
                <label for="hourly_rate">Preço/Hora (€)</label>
                <input type="number" id="hourly_rate" name="hourly_rate" value="${user.hourly_rate || ''}" required>
            </div>
            <div class="form-group">
                <label for="experience_years">Anos de Experiência</label>
                <input type="number" id="experience_years" name="experience_years" value="${user.experience_years || ''}">
            </div>
        `;
    }
    
    // --- Lógica de Chat ---
    window.checkUnreadMessages = async function() {
        try {
            // Assume que a API de chat está em /api/chat/check_unread_api.php
            const response = await fetch(`${API_BASE_URL}/chat/check_unread_api.php`); 
            const data = await response.json();
            
            const dot = document.getElementById('chat-notification-dot');
            const title = document.getElementById('page-title');
            
            if (data.unread_count > 0) {
                dot.style.display = 'block';
                title.textContent = `(${data.unread_count}) Nova(s) Mensagem(ns)`; 
            } else {
                dot.style.display = 'none';
                title.textContent = 'Dashboard Babysitter | BabyHappy';
            }
        } catch (error) {
            console.error('Erro ao verificar não lidas:', error);
        }
    }

    window.addEventListener('hashchange', handleNavigation);
    
    // Inicia o processo
    initDashboard();
});

// --- LÓGICA DE SUBMISSÃO DO PERFIL (FORA DO DOMContentLoaded) ---
// O event listener para o formulário de perfil é definido aqui:

// Assumimos que o elemento 'profile-form' existe no HTML e que a API /user/update_profile.php existe
document.getElementById('profile-form').addEventListener('submit', async function(event) {
    event.preventDefault();
    
    const profileForm = event.target;
    const messageElement = document.getElementById('profile-message');
    
    const formData = new FormData(profileForm);
    
    // Limpeza dos campos de password se estiverem vazios
    if (formData.get('password') === '') {
        formData.delete('password');
        formData.delete('confirm_password');
    }

    messageElement.textContent = 'A guardar alterações...';
    messageElement.style.color = 'blue';

    try {
        // CORREÇÃO: Caminho correto para a API de atualização de perfil (/user/update_profile.php)
        const response = await fetch(`${API_BASE_URL}/user/update_profile.php`, {
            method: 'POST',
            body: formData 
        });

        const result = await response.json();

        if (response.ok && result.success) {
            messageElement.textContent = `✅ ${result.message}`;
            messageElement.style.color = 'green';
            
            // Atualiza a foto no frontend
            if (result.new_photo_url) {
                const fullUrl = `http://localhost/babyhappy_v1/${result.new_photo_url}?${Date.now()}`;
                document.getElementById('profile-img').src = fullUrl;
            }
            
            // Limpa passwords
            profileForm.querySelector('#password').value = '';
            profileForm.querySelector('#confirm_password').value = '';

        } else {
            throw new Error(result.message || 'Erro desconhecido ao atualizar perfil.');
        }

    } catch (error) {
        console.error('Erro de atualização:', error);
        messageElement.textContent = `❌ Erro ao guardar: ${error.message}`;
        messageElement.style.color = 'red';
    }
});