// ../js/heartbeat.js
// Script para manter a sessão e o status 'online' do utilizador atualizado.

document.addEventListener('DOMContentLoaded', function() {
    // ⚠️ ATENÇÃO: Configure o caminho CORRETO da API
    const ACTIVITY_API_URL = 'http://localhost/babyhappy_v1/api/user/update_activity.php';
    
    // Frequência do heartbeat em milissegundos (ex: a cada 30 segundos)
    const HEARTBEAT_INTERVAL = 30000; 

    /**
     * Envia uma requisição POST para atualizar a atividade do utilizador na base de dados.
     * Esta função é essencialmente o "batimento cardíaco" (heartbeat) da sessão.
     */
    async function sendActivityUpdate() {
        try {
            const response = await fetch(ACTIVITY_API_URL, {
                method: 'POST',
                // O body pode ser vazio, mas o método POST é necessário 
                // para satisfazer a verificação $_SERVER["REQUEST_METHOD"] == "POST" no PHP
            });
            
            // Opcional: Verificar a resposta (pode ser útil para debug)
            const data = await response.json();

            if (data.success) {
                // console.log('Atividade atualizada com sucesso.');
            } else {
                console.warn('Falha ao atualizar a atividade:', data.error);
                // Se a sessão expirou, o backend deve ter retornado um erro de autenticação.
                // Aqui você pode adicionar lógica para logout ou notificação.
            }

        } catch (error) {
            // Este erro geralmente indica problemas de rede ou CORS
            console.error('Erro de rede durante o heartbeat:', error);
        }
    }

    /**
     * Inicia o serviço de heartbeat.
     */
    function startHeartbeat() {
        // Envia a primeira atualização imediatamente
        sendActivityUpdate();

        // Configura o intervalo para as atualizações subsequentes
        setInterval(sendActivityUpdate, HEARTBEAT_INTERVAL);
        
        // Console log para indicar que o serviço está ativo (apenas para debug)
        console.log(`Heartbeat iniciado: Atualização a cada ${HEARTBEAT_INTERVAL / 1000} segundos.`);
    }

    // --- Inicialização ---
    
    // Inicia o heartbeat assim que a página é carregada
    startHeartbeat();
});