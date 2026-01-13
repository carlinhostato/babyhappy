// /public/assets/js/load_user_name.js

document.addEventListener('DOMContentLoaded', function() {
    // Endpoint da nova API de nome
    const API_URL_NAME = '/babyhappy_v1/api/auth/fetch_user_name.php';
    const welcomeDisplay = document.getElementById('welcome-message-display');

    if (!welcomeDisplay) {
        // Se o elemento não existir, não fazemos nada (pode ser uma página externa)
        return; 
    }

    async function loadUserName() {
        try {
            const response = await fetch(API_URL_NAME);
            
            if (!response.ok) {
                // Tenta ler erro JSON ou lança erro de rede
                const errorData = await response.json().catch(() => ({ message: 'Erro de rede ou API.' }));
                throw new Error(errorData.message);
            }

            const result = await response.json();

            if (result.success && result.nome) {
                // Preenche o elemento com o nome
                welcomeDisplay.textContent = `Bem vindo(a), ${result.nome}`;
            } else {
                // Em caso de sucesso=false ou nome vazio, mostra a saudação genérica
                welcomeDisplay.textContent = 'Bem vindo(a), Utilizador(a)';
            }

        } catch (error) {
            console.error('Erro ao carregar o nome do utilizador:', error.message);
            welcomeDisplay.textContent = 'Bem vindo(a), Erro de Carregamento';
        }
    }

    // Define um valor temporário enquanto carrega
    welcomeDisplay.textContent = 'A carregar...'; 
    loadUserName();
});