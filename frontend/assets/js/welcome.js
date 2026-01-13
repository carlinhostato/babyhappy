// Ajuste o caminho base da sua API
const API_BASE_URL = 'http://localhost/babyhappy_v1/api/auth'; 
const REDIRECT_URL = 'pages/choose_role.html';

async function initializeWelcome() {
    const statusMessage = document.getElementById('status-message');
    
    try {
        // Chamada à API para inicializar a sessão no servidor PHP
        const response = await fetch(`${API_BASE_URL}/welcome_init.php`);
        const result = await response.json();

        if (result.success) {
            statusMessage.textContent = "Pronto! A redirecionar em 4 segundos...";
        } else {
            // Se a API falhar, logamos o erro, mas o timer continua para não bloquear
            statusMessage.textContent = "Aviso: Falha na inicialização da sessão.";
            console.error("Erro na inicialização da API:", result.message);
        }

    } catch (error) {
        // Erro de rede total
        console.error("Falha na conexão com a API:", error);
    } finally {
        // Timer de 4 segundos para redirecionamento, independentemente do sucesso da API
        setTimeout(function() {
            window.location.href = REDIRECT_URL;
        }, 4000); 
    }
}

// Inicia o processo quando a página carrega
initializeWelcome();