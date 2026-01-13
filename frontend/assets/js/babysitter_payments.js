// assets/js/babysitter_payments.js

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('withdrawal-form');
    const methodSelect = document.getElementById('withdrawal_method');
    const amountInput = document.getElementById('withdrawal_amount');
    const message = document.getElementById('withdrawal-message');
    const submitBtn = document.getElementById('submit-withdrawal');
    const currentBalanceDisplay = document.getElementById('current-balance');
    const maxBalanceHint = document.getElementById('max-balance-hint');
    const historicoContainer = document.getElementById('historico-tabela');
    const historicoLoading = document.getElementById('historico-loading');
    
    // Elemento para o nome no header
    const welcomeDisplay = document.getElementById('welcome-message-display'); 

    let currentBalance = 0.00;

    // Campos de Detalhes
    const detailsContainer = document.getElementById('details-input-container');
    const detailsInput = document.getElementById('details_value');
    const detailsLabel = document.getElementById('details-label');
    const detailsHint = document.getElementById('details-hint');

    // ATENÇÃO: Verifique se os caminhos estão corretos no seu servidor
    const API_URLS = {
        FETCH_ALL_DATA: '/babyhappy_V1/api/auth/babysitter_payments_fetch.php',
        WITHDRAW: '/babyhappy_V1/api/auth/withdraw_funds_simulate.php',
        FETCH_NAME: '/babyhappy_v1/api/auth/fetch_user_name.php'
    };
    
    // --- Funções de Formatação e UI ---
    function formatCurrency(amount) {
        return parseFloat(amount).toFixed(2).replace('.', ',');
    }
    
    function formatDate(dateString) {
        const d = new Date(dateString);
        return d.toLocaleDateString('pt-PT', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    function showFormMessage(msg, color) {
        if (message) {
            message.style.color = color;
            message.textContent = msg;
        }
    }
    
    // Função para ler o corpo APENAS UMA VEZ e tratar erros JSON/HTML
    async function processResponseText(response) {
        const responseText = await response.text(); 

        try {
            // Tenta analisar o texto como JSON
            const data = JSON.parse(responseText); 
            return data;
        } catch (e) {
            // Se falhar (SyntaxError), significa que o servidor enviou HTML/texto de erro PHP
            console.error("Erro no Parse JSON. Resposta do Servidor:", responseText);
            
            return {
                success: false, 
                message: `Erro de Servidor: A resposta não é JSON. (${response.status} ${response.statusText})`
            };
        }
    }

    // --- 0. Carregar Nome no Header ---
    async function loadUserName() {
        if (!welcomeDisplay) return;

        try {
            const response = await fetch(API_URLS.FETCH_NAME);
            const data = await response.json();

            if (data.success && data.nome) {
                welcomeDisplay.textContent = `Bem-vindo(a), ${data.nome}`;
            } else {
                welcomeDisplay.textContent = 'Bem-vindo(a), Utilizador(a)';
            }
        } catch (error) {
            console.error('Erro ao carregar o nome:', error);
            welcomeDisplay.textContent = 'Bem-vindo(a), Erro de Carregamento';
        }
    }


    // --- 1. Carregar Dados Iniciais (Saldo e Histórico) ---
    async function loadInitialData() {
        if (historicoLoading) historicoLoading.style.display = 'block';

        try {
            const response = await fetch(API_URLS.FETCH_ALL_DATA); 
            
            const data = await processResponseText(response);

            if (data.success) {
                currentBalance = parseFloat(data.saldo || 0); 
                
                // Atualizar Display de Saldo
                if (currentBalanceDisplay) currentBalanceDisplay.textContent = formatCurrency(currentBalance) + ' €';
                if (maxBalanceHint) maxBalanceHint.textContent = formatCurrency(currentBalance) + ' €';
                
                // Configurar Input de Montante
                if (amountInput) {
                    amountInput.max = currentBalance.toFixed(2);
                    amountInput.value = currentBalance > 1 ? currentBalance.toFixed(2) : '0.00';
                }
                
                // Habilitar Botão
                if (submitBtn) submitBtn.disabled = currentBalance < 1;

                // Renderizar Histórico
                renderHistoryTable(data.historico || []); 
            } else {
                if (currentBalanceDisplay) currentBalanceDisplay.textContent = 'Erro';
                showFormMessage(data.message, 'red');
            }
        } catch (error) {
            console.error('Erro ao carregar dados de pagamentos:', error);
            if (currentBalanceDisplay) currentBalanceDisplay.textContent = 'Erro';
            showFormMessage('Erro de comunicação com o servidor. Verifique o caminho da API.', 'red');
        } finally {
            if (historicoLoading) historicoLoading.style.display = 'none';
        }
    }
    
    // --- 2. Renderizar Histórico ---
    function renderHistoryTable(historico) {
        if (!historicoContainer) return; 
        
        if (historico.length === 0) {
            historicoContainer.innerHTML = '<p style="text-align:center; color:#555; padding:15px; background:#fff; border-radius:10px;">Nenhum pagamento de reserva recebido ainda.</p>';
            return;
        }

        let html = `
            <table style="width:100%; border-collapse:collapse; background:#fff; border-radius:10px; overflow:hidden;">
                <thead>
                    <tr style="background:#4a8cc7; color:#fff;">
                        <th style="padding:10px; border:1px solid #ccc;">Data</th>
                        <th style="padding:10px; border:1px solid #ccc;">Cliente</th>
                        <th style="padding:10px; border:1px solid #ccc;">Valor Recebido</th>
                        <th style="padding:10px; border:1px solid #ccc;">Reserva</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        historico.forEach(row => {
            html += `
                <tr>
                    <td style="padding:8px; border:1px solid #ccc; font-size:0.9em;">${formatDate(row.data_pagamento)}</td>
                    <td style="padding:8px; border:1px solid #ccc;">${row.parent_nome}</td>
                    <td style="padding:8px; border:1px solid #ccc; text-align:right; color:green; font-weight:bold;">+€${formatCurrency(row.montante)}</td>
                    <td style="padding:8px; border:1px solid #ccc; text-align:center;">${row.booking_id ? '#' + row.booking_id : '-'}</td>
                </tr>
            `;
        });

        html += '</tbody></table>';
        historicoContainer.innerHTML = html;
    }

    // --- 3. Gerir o Formulário de Levantamento ---

    function handleMethodChange() {
        if (!methodSelect) return;

        const method = methodSelect.value;
        if (detailsInput) detailsInput.value = '';
        if (detailsInput) detailsInput.required = false;
        if (detailsContainer) detailsContainer.style.display = 'none';

        if (method === 'MBWAY') {
            if (detailsContainer) detailsContainer.style.display = 'block';
            if (detailsLabel) detailsLabel.textContent = 'Número de Telemóvel (MB Way - 9 dígitos):';
            if (detailsInput) {
                detailsInput.placeholder = 'Ex: 91xxxxxxx';
                detailsInput.type = 'number';
                detailsInput.removeAttribute('maxlength');
                detailsInput.required = true;
            }
            if (detailsHint) detailsHint.textContent = 'O levantamento será enviado para este número MB Way.';
        } else if (method === 'DEBIT_CARD') {
            if (detailsContainer) detailsContainer.style.display = 'block';
            if (detailsLabel) detailsLabel.textContent = 'IBAN ou N.º de Cartão (Máx. 50 caracteres):';
            if (detailsInput) {
                detailsInput.placeholder = 'Ex: PT50 xxxx xxxx xxxx xxxx xxxx x (IBAN)';
                detailsInput.type = 'text';
                detailsInput.maxLength = 50; 
                detailsInput.required = true;
            }
            if (detailsHint) detailsHint.textContent = 'Introduza o IBAN ou número do cartão/conta para transferência.';
        }
    }
    
    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            showFormMessage('', 'red');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'A processar...';
            }

            // ✅ CORRIGIDO: Declarar 'data' no escopo da função para evitar ReferenceError
            let data = null; 
            
            const formData = new FormData(form);
            const amount = parseFloat(formData.get('amount'));
            let detailsValue = formData.get('details_value') ? formData.get('details_value').trim() : '';
            const method = formData.get('method');
            
            // Validações Front-end
            if (amount > currentBalance || amount < 1 || isNaN(amount)) {
                showFormMessage('Montante inválido ou superior ao saldo disponível (mínimo de €1).', 'red');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Solicitar Levantamento';
                }
                return;
            }
            if (detailsInput && detailsInput.required && !detailsValue) {
                showFormMessage('Por favor, preencha os detalhes do método de levantamento.', 'red');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Solicitar Levantamento';
                }
                return;
            }
            if (method === 'MBWAY') {
                detailsValue = detailsValue.replace(/\s/g, ''); 
                if (detailsValue.length !== 9 || isNaN(detailsValue)) {
                    showFormMessage('O número de telemóvel MB Way deve ter exatamente 9 dígitos.', 'red');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Solicitar Levantamento';
                    }
                    return;
                }
            }
            
            // Envio para a API
            try {
                const response = await fetch(API_URLS.WITHDRAW, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        amount: amount,
                        method: method,
                        details_value: detailsValue
                    })
                });
                
                data = await processResponseText(response); 

                if (data.success) {
                    showFormMessage('✅ ' + data.message, 'green');
                    
                    // ATUALIZAÇÃO DO SALDO EM TEMPO REAL
                    const newBalance = parseFloat(data.new_balance_simulated || 0);
                    currentBalance = newBalance; // Atualiza a variável global
                    
                    if (currentBalanceDisplay) {
                        currentBalanceDisplay.textContent = formatCurrency(currentBalance) + ' €';
                    }
                    if (maxBalanceHint) {
                        maxBalanceHint.textContent = formatCurrency(currentBalance) + ' €';
                    }
                    if (amountInput) {
                        amountInput.max = currentBalance.toFixed(2);
                        amountInput.value = '0.00'; // Limpa o campo
                    }
                    
                    // Reativa e ajusta o botão (Sucesso)
                    if (submitBtn) {
                        submitBtn.disabled = currentBalance < 1; 
                        submitBtn.textContent = 'Solicitar Levantamento';
                    }

                } else {
                    showFormMessage('❌ Falha: ' + data.message, 'red');
                }
            } catch(error) {
                console.error('Erro de rede ou servidor:', error);
                showFormMessage('Ocorreu um erro de comunicação fatal.', 'red');
            } finally {
                // Reativa o botão APENAS se não houve sucesso (falha na API ou erro fatal)
                if (submitBtn && (!data || !data.success)) { 
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Solicitar Levantamento';
                }
            }
        });
    }
    
    // Inicialização
    if (methodSelect) methodSelect.addEventListener('change', handleMethodChange);
    handleMethodChange(); 
    
    // Chamadas de carregamento
    loadUserName();      
    loadInitialData();   
});