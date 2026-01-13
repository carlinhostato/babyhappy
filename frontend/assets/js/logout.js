// ../assets/js/logout.js
// Logout seguro: chama o endpoint no servidor com credentials, limpa apenas dados de sessão locais
// (não limpa todo o localStorage por defeito, para não apagar simulações ou outras prefs do utilizador)

const API_BASE_URL_LOGOUT = 'http://localhost/babyhappy_v1/api/auth';
const LOGOUT_API_URL = `${API_BASE_URL_LOGOUT}/logout.php`;

document.addEventListener('DOMContentLoaded', function() {
    const logoutLink = document.getElementById('logout-link');

    if (!logoutLink) return;

    logoutLink.addEventListener('click', async (e) => {
        e.preventDefault();

        try {
            // Chamada ao servidor para destruir sessão (envia cookies de sessão)
            await fetch(LOGOUT_API_URL, {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' }
            });
        } catch (error) {
            console.error('Erro de rede/processamento durante o logout:', error);
            // Continuamos com o redirect mesmo que o fetch falhe
        } finally {
            // Limpar apenas dados temporários relacionados à sessão/UX.
            // NÃO usamos localStorage.clear() para evitar apagar simulação (sim_balance / sim_history)
            // Nem outras preferências persistentes do utilizador.
            try {
                sessionStorage.removeItem('paymentFeedback');
                // Se tiver outras chaves de sessão temporárias, remova aqui:
                // sessionStorage.removeItem('some_other_key');
            } catch (err) {
                console.warn('Erro ao limpar sessionStorage:', err);
            }

            // Se preferir que a simulação local seja limpa ao fazer logout,
            // descomente as linhas abaixo:
            // localStorage.removeItem('sim_balance');
            // localStorage.removeItem('sim_history');

            // Redirecionar para a página de login
            window.location.href = 'login.html';
        }
    });
});