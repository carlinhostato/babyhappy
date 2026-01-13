// Garantir que o código só é executado após o HTML estar totalmente carregado
document.addEventListener('DOMContentLoaded', function() {
    
    const registerForm = document.getElementById('registerForm');
    const messageElement = document.getElementById('message');
    
    // Se o formulário ou a mensagem não forem encontrados, é melhor parar
    if (!registerForm || !messageElement) {
        console.error("Erro fatal: Elementos HTML 'registerForm' ou 'message' não encontrados.");
        return;
    }

    // Anexar o listener de submissão do formulário
    registerForm.addEventListener('submit', function(event) {
        event.preventDefault(); // Impede o envio tradicional do formulário

        // 1. Coletar dados do formulário
        const userData = {
            role: registerForm.querySelector('input[name="role"]').value,
            nome_completo: registerForm.querySelector('input[name="nome_completo"]').value,
            email: registerForm.querySelector('input[name="email"]').value,
            password: registerForm.querySelector('input[name="password"]').value,
            localizacao: registerForm.querySelector('input[name="localizacao"]').value
        };

        console.log('=== ENVIANDO DADOS ===');
        console.log('Dados:', userData);
        console.log('=====================');

        // 2. URL da sua API PHP
        const apiUrl = 'http://localhost/babyhappy_v1/api/auth/register_api.php'; 

        messageElement.textContent = 'A processar...';
        messageElement.style.color = 'blue';

        // 3. Fazer a requisição POST para a API
        fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(userData)
        })
        .then(response => {
            // PRIMEIRO: Ler a resposta como texto (sempre funciona)
            return response.text().then(text => {
                // DEBUG: Mostrar TUDO que veio do servidor
                console.log('=== DEBUG RESPOSTA ===');
                console.log('Status:', response.status);
                console.log('Content-Type:', response.headers.get('content-type'));
                console.log('Resposta completa:', text);
                console.log('Tamanho:', text.length);
                console.log('Primeiros 100 caracteres:', text.substring(0, 100));
                console.log('=====================');

                // Verificar se há conteúdo
                if (!text || text.trim() === '') {
                    throw new Error('Resposta vazia do servidor.');
                }

                // Verificar se é JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    console.error('Resposta não é JSON:', text);
                    throw new Error('O servidor não retornou JSON. Resposta: ' + text.substring(0, 200));
                }

                // Tentar fazer parse do JSON
                let data;
                try {
                    data = JSON.parse(text);
                } catch (parseError) {
                    console.error('Erro ao fazer parse do JSON:', parseError);
                    console.error('Texto que causou erro:', text);
                    throw new Error('Resposta inválida do servidor. Verifique o console para detalhes.');
                }

                // Se a resposta não for OK (2xx), lançar erro com a mensagem da API
                if (!response.ok) {
                    throw new Error(data.message || `Erro HTTP ${response.status}`);
                }

                return data;
            });
        })
        .then(data => {
            // 5. Sucesso no Registo
            console.log('=== SUCESSO ===');
            console.log('Dados retornados:', data);
            
            messageElement.textContent = '✅ Registo concluído! Redirecionando...';
            messageElement.style.color = 'green';
            
            // Pequeno delay antes de redirecionar para o utilizador ver a mensagem
            setTimeout(() => {
                window.location.href = 'login.html';
            }, 1500);
        })
        .catch(error => {
            // 6. Erro no Registo ou na Rede
            console.error('=== ERRO ===');
            console.error('Mensagem:', error.message);
            console.error('Stack:', error.stack);
            
            messageElement.textContent = `❌ Erro: ${error.message}`;
            messageElement.style.color = 'red';
        });
    });
});