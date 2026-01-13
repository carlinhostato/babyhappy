// frontend/js/parent_dashboard.js

const API_BASE_URL = 'http://localhost/babyhappy_v1/api/v1'; 
const DEFAULT_PHOTO_URL = '../assets/images/default_profile.png'; // Caminho relativo ao HTML

document.addEventListener('DOMContentLoaded', function() {
    const mainContent = document.querySelector('.main-content');
    const bottomMenu = document.getElementById('bottom-menu');
    let currentSection = '';

    // Função 1: Verifica Autenticação e Carrega Dados Essenciais
    async function checkAuthAndLoadData() {
        try {
            // A API de perfil também serve como verificação de autenticação
            const response = await fetch(`${API_BASE_URL}/user/get_profile.php`); 
            
            if (response.status === 401) {
                // Redirecionar para o login se não autorizado
                window.location.href = 'login.html'; 
                return;
            }
            
            const result = await response.json();
            
            if (!result.success) {
                 // Erro no servidor, mas autorizado
                throw new Error(result.message || "Erro ao carregar dados do utilizador.");
            }

            // Armazenar dados do utilizador (opcionalmente no localStorage/SessionStorage)
            localStorage.setItem('userProfile', JSON.stringify(result.user));
            
            // Inicia a navegação
            handleNavigation();

        } catch (error) {
            console.error("Falha na inicialização:", error);
            mainContent.innerHTML = `<p class="message error">❌ Falha crítica: ${error.message}</p>`;
        }
    }

    // Função 2: Gerencia a navegação e o estado da URL
    function handleNavigation() {
        const hash = window.location.hash.replace('#', '');
        currentSection = hash || 'pesquisa'; 
        
        showSection(currentSection);
        
        // Se a secção for 'perfil', carregamos o formulário
        if (currentSection === 'perfil') {
            renderProfileForm();
        }
        // Se a secção for 'chat', inicializamos a verificação de mensagens
        if (currentSection === 'chat') {
            checkUnreadMessages(); 
        }
    }
    
    // Função 3: Mostra a Secção Correta
    function showSection(sectionId) {
        document.querySelectorAll('.dashboard-section').forEach(section => {
            section.style.display = 'none';
        });
        
        const targetSection = document.getElementById(`section-${sectionId}`);
        if (targetSection) {
            targetSection.style.display = 'block';
        } else {
             // Caso a secção não exista (erro), volta para pesquisa
             showSection('pesquisa');
        }

        // Atualiza a classe ativa no menu inferior
        document.querySelectorAll('.nav-link').forEach(link => {
            if (link.dataset.section === sectionId) {
                link.classList.add('active-section');
            } else {
                link.classList.remove('active-section');
            }
        });
        
        // Esconde o loading
        document.getElementById('section-loading').style.display = 'none';
        document.body.className = `section-${sectionId}`;
    }

    // Função 4: Renderiza os dados do perfil (substituindo o PHP switch)
    function renderProfileForm() {
        const user = JSON.parse(localStorage.getItem('userProfile'));
        if (!user) return; // Deve ser impedido pela checkAuth

        const profileDetailsDiv = document.getElementById('profile-details');
        
        // Monta o HTML do perfil
        profileDetailsDiv.innerHTML = `
            <div class="profile-photo-area">
                <img src="${user.photo_url || DEFAULT_PHOTO_URL}" alt="Foto de Perfil" id="profile-img">
                <div class="form-group" style="margin-top: 10px;">
                    <label for="new_photo">Alterar Foto:</label>
                    <input type="file" id="new_photo" name="new_photo" accept="image/*">
                </div>
            </div>

            <input type="hidden" name="user_id" value="${user.user_id || ''}">
            <div class="form-group">
                <label for="nome_completo">Nome Completo</label>
                <input type="text" id="nome_completo" name="nome_completo" value="${user.nome_completo}" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="${user.email}" required>
            </div>
        `;
        // O formulário de submissão (profile-form) precisará de um event listener
        // para enviar dados para a API /user/update_profile.php via FETCH.
    }
    
    // Função 5: Listener para navegação por cliques no menu
    bottomMenu.addEventListener('click', function(e) {
        const link = e.target.closest('.nav-link');
        if (link) {
            e.preventDefault();
            const sectionId = link.dataset.section;
            window.location.hash = sectionId;
        }
    });
    
    // Listener para o evento hashchange (navegação via URL)
    window.addEventListener('hashchange', handleNavigation);
    
    // Inicia o processo
    checkAuthAndLoadData();
    
    // O código de verificação de mensagens não lidas
    // deve ser reescrito para usar a API de chat
    window.checkUnreadMessages = function() {
        // Nova API para verificar mensagens (se houver)
        fetch('fetch_unread_count_api.php') 
            .then(response => response.json())
            .then(data => {
                const dot = document.getElementById('chat-notification-dot');
                const title = document.getElementById('page-title');
                
                if (data.unread_count > 0) {
                    dot.style.display = 'block';
                    title.textContent = `(${data.unread_count}) Nova(s) Mensagem(ns)`; 
                } else {
                    dot.style.display = 'none';
                    title.textContent = 'Dashboard | BabyHappy';
                }
            })
            .catch(error => {
                console.error('Erro ao verificar não lidas:', error);
            });
    }

    // Inicializa a verificação de mensagens e o intervalo (opcional)
    // checkUnreadMessages();
    // setInterval(checkUnreadMessages, 10000); 

});

const API_AUTH_URL = 'http://localhost/babyhappy_v1/api/auth/logout.php';
const logoutLink = document.getElementById('logout-link');

logoutLink.addEventListener('click', async (e) => {
    e.preventDefault(); // <-- ISTO É CRUCIAL! Impede a navegação do navegador.
    
    try {
        // Envia a requisição POST para a API de Logout
        await fetch(API_AUTH_URL, {
            method: 'POST' 
        });

        // Não importa o resultado da resposta, assumimos que a sessão foi destruída.
        
    } catch (error) {
        console.error("Erro de rede ou processamento durante o logout:", error);
    } finally {
        // Redireciona para a página de login
        localStorage.clear(); 
        window.location.href = 'login.html'; 
    }
});