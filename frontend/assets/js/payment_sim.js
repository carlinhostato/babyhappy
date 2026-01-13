document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('payment-form');
    const submitBtn = document.getElementById('submit-btn');
    const feedbackDiv = document.getElementById('feedback');
    
    // ⚠️ ATENÇÃO: Substitua pelo caminho real para o seu processador PHP
    const API_URL = '/babyhappy_V1/api/auth/pagamento.php';

    if (!form) return;

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        submitBtn.disabled = true;
        submitBtn.textContent = 'Processando... Por favor, aguarde.';
        feedbackDiv.style.display = 'none';

        // 1. Coletar dados do formulário
        const formData = new FormData(form);
        // O Fetch API pode enviar FormData diretamente para PHP (Content-Type: multipart/form-data)

        try {
            // 2. Enviar dados para o processador PHP
            const response = await fetch(API_URL, {
                method: 'POST',
                body: formData 
            });

            const data = await response.json();

            // 3. Processar resposta
            if (data.success) {
                feedbackDiv.className = 'message-area success';
                feedbackDiv.innerHTML = data.message + `<br>ID Pagamento: <strong>${data.payment_id}</strong>`;
                form.reset(); // Limpa o formulário após sucesso
            } else {
                feedbackDiv.className = 'message-area error';
                feedbackDiv.textContent = data.message;
            }

        } catch (error) {
            feedbackDiv.className = 'message-area error';
            feedbackDiv.textContent = '❌ Erro de rede: Não foi possível comunicar com o servidor.';
            console.error('Erro de Fetch:', error);
        } finally {
            feedbackDiv.style.display = 'block';
            submitBtn.disabled = false;
            submitBtn.textContent = 'Processar Pagamento (Simular)';
        }
    });
});