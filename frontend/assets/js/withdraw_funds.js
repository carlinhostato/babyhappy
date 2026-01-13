// ../js/withdraw_funds.js

document.addEventListener('DOMContentLoaded', function() {
    // ⚠️ ATENÇÃO: Ajuste esta URL para o seu endpoint PHP
    const WITHDRAW_API_URL = 'http://localhost/babyhappy_v1/api/auth/withdraw_funds_simulate.php';
    // ⚠️ Supondo que você tem um endpoint para buscar o saldo atual
    const BALANCE_API_URL = 'http://localhost/babyhappy_v1/api/auth/get_current_balance.php'; 
    
    // Supondo que o ID do utilizador é armazenado globalmente ou em outro lugar seguro (AJUSTE CONFORME NECESSÁRIO)
    // Para fins de simulação, vamos usar um ID mock, mas na prática, deve vir da sessão/autenticação.
    const USER_ID = 1; // 🚨 MUDAR PARA VARIÁVEL REAL (e.g., vinda de um PHP echo)
    
    // Elementos DOM
    const form = document.getElementById('withdraw-form');
    const amountInput = document.getElementById('amount');
    const methodSelect = document.getElementById('method');
    const detailsLabel = document.getElementById('details-label');
    const detailsValueInput = document.getElementById('details_value');
    const feedbackMessage = document.getElementById('feedback-message');
    const submitBtn = document.getElementById('submit-btn');
    const balanceDisplay = document.getElementById('current-balance');

    // Função para formatar o preço
    const formatCurrency = (value) => {
        return new Intl.NumberFormat('pt-PT', { style: 'currency', currency: 'EUR' }).format(value);
    };
    
    // --- Lógica de Interface ---

    // Atualiza o rótulo do campo de detalhes com base no método selecionado
    function updateDetailsField() {
        const method = methodSelect.value;
        detailsValueInput.value = ''; // Limpa o campo ao mudar o método
        
        switch (method) {
            case 'MBWAY':
                detailsLabel.textContent = 'Número de Telemóvel MB Way (9 dígitos):';
                detailsValueInput.placeholder = 'Ex: 912345678';
                detailsValueInput.type = 'tel';
                detailsValueInput.maxLength = 9;
                break;
            case 'DEBIT_CARD':
                detailsLabel.textContent = 'IBAN ou N.º de Cartão (Máx 50 caracteres):';
                detailsValueInput.placeholder = 'Ex: PT50 xxxx xxxx xxxx xxxx xx';
                detailsValueInput.type = 'text';
                detailsValueInput.maxLength = 50;
                break;
            default:
                detailsLabel.textContent = 'Detalhes do Método:';
                detailsValueInput.placeholder = 'Selecione um método primeiro';
                detailsValueInput.type = 'text';
                detailsValueInput.maxLength = 50;
        }
    }

    // --- Lógica da API (Backend) ---

    async function fetchCurrentBalance() {
         balanceDisplay.textContent = 'A carregar saldo...';
         try {
            // 🚨 Esta chamada requer um endpoint PHP de busca de saldo real para funcionar
            // Esta é uma simulação, ajuste conforme a sua API de saldo.
            const response = await fetch(`${BALANCE_API_URL}?user_id=${USER_ID}`);
            if (!response.ok) throw new Error('Falha ao obter saldo.');

            const data = await response.json();
            
            if (data.success && data.balance !== undefined) {
                balanceDisplay.textContent = `Saldo Disponível: ${formatCurrency(data.balance)}`;
                amountInput.max = parseFloat(data.balance).toFixed(2); // Define o máximo no input
            } else {
                balanceDisplay.textContent = 'Erro ao carregar saldo.';
                console.error('API Saldo:', data.message || 'Dados inválidos.');
            }

        } catch (error) {
            balanceDisplay.textContent = 'Erro de rede ao carregar saldo.';
            console.error(error);
        }
    }

    async function handleWithdrawal(event) {
        event.preventDefault();
        feedbackMessage.textContent = '';
        submitBtn.disabled = true;
        submitBtn.textContent = 'A processar...';

        const amount = parseFloat(amountInput.value);
        const method = methodSelect.value;
        let detailsValue = detailsValueInput.value.trim();

        // Validação Front-End (Repete a do PHP para melhor UX)
        if (amount <= 0 || amount < 1.00) {
            displayFeedback('Montante inválido (Mínimo 1.00 €).', 'red');
            return;
        }
        if (!method) {
            displayFeedback('Selecione um método de levantamento.', 'red');
            return;
        }
        if (!detailsValue) {
            displayFeedback('Os detalhes do método são obrigatórios.', 'red');
            return;
        }
        if (method === 'MBWAY') {
             detailsValue = detailsValue.replace(/\D/g, ''); // Limpa não-dígitos
             if (detailsValue.length !== 9) {
                 displayFeedback('O número de telemóvel MB Way deve ter 9 dígitos.', 'red');
                 return;
             }
        }
        
        try {
            const response = await fetch(WITHDRAW_API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    user_id: USER_ID, // Use o ID real
                    amount: amount,
                    method: method,
                    details_value: detailsValue
                })
            });

            const data = await response.json();

            if (data.success) {
                displayFeedback(data.message, 'green');
                form.reset(); 
                fetchCurrentBalance(); // Atualiza o saldo
            } else {
                displayFeedback(data.message, 'red');
            }

        } catch (error) {
            console.error('Erro de rede ou JSON inválido:', error);
            displayFeedback('Erro de comunicação com o servidor.', 'red');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Solicitar Levantamento';
        }
    }

    function displayFeedback(message, color) {
        feedbackMessage.textContent = message;
        feedbackMessage.style.color = color;
    }

    // --- Inicialização ---

    methodSelect.addEventListener('change', updateDetailsField);
    form.addEventListener('submit', handleWithdrawal);
    
    // Inicia o formulário carregando o saldo e configurando o campo de detalhes
    updateDetailsField(); 
    fetchCurrentBalance(); 
});