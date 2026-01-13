// ../assets/js/payments_parents.js
// Frontend payments logic — grava saldo no servidor (use SIMULATE = false para produção).
// Lógica de levantamento removida.

document.addEventListener("DOMContentLoaded", function () {

    // ===============================
    // CONFIGURAÇÃO
    // ===============================
    const API_BASE_URL = '/babyhappy_v1/api/auth';
    const BALANCE_API_URL = `${API_BASE_URL}/get_balance.php`;
    const PROCESS_API_URL = `${API_BASE_URL}/process_payment.php`;
    const FEEDBACK_STORAGE_KEY = 'paymentFeedback';

    // Se quiser testar localmente sem backend, marque true. Para persistência no servidor use false.
    const SIMULATE = false;

    // ===============================
    // ELEMENTOS DOM
    // ===============================
    const balanceDisplay = document.getElementById('current-balance');
    const paymentForm = document.getElementById('paymentForm');
    const methodSelect = document.getElementById("method");
    const mbwayFields = document.getElementById("mbway-fields");
    const cardFields = document.getElementById("card-fields");
    const loadSubmitBtn = document.getElementById("load-submit-btn");
    const historyContainer = document.getElementById('history-container');
    const feedbackContainer = document.getElementById('feedback-message-container');
    const amountInput = document.getElementById('amount');

    const openDepositBtn = document.getElementById('open-deposit-btn');
    const depositSection = document.getElementById('deposit-section');
    const reservaInfo = document.getElementById('reserva-info');
    const reservaIdEl = document.getElementById('reserva-id');
    const hiddenBookingInput = document.getElementById('booking_id');

    // URL params (booking flow)
    const urlParams = new URLSearchParams(window.location.search);
    const bookingId = urlParams.get('booking_id');
    const amountParam = urlParams.get('amount');

    // ===============================
    // LOCK CONTRA DUPLO SUBMIT
    // ===============================
    let isSubmitting = false;

    // ===============================
    // UTILS
    // ===============================
    const formatPrice = (value) => {
        const num = parseFloat(value);
        if (Number.isNaN(num)) return '€0,00';
        return new Intl.NumberFormat('pt-PT', {
            style: 'currency',
            currency: 'EUR'
        }).format(num);
    };

    const showFeedback = (message, type = 'success') => {
        if (!feedbackContainer) return;
        feedbackContainer.innerHTML = `<p class="message ${type}">${message}</p>`;
        feedbackContainer.style.display = 'block';
        setTimeout(() => { feedbackContainer.style.display = 'none'; }, 5000);
    };

    const handleInitialFeedback = () => {
        const stored = sessionStorage.getItem(FEEDBACK_STORAGE_KEY);
        if (stored) {
            try {
                const data = JSON.parse(stored);
                showFeedback(data.message, data.type);
            } catch (err) {
                console.warn('Invalid feedback stored', err);
            }
            sessionStorage.removeItem(FEEDBACK_STORAGE_KEY);
        }
    };

    const isElementVisible = (el) => !!el && window.getComputedStyle(el).display !== 'none';

    // ===============================
    // UI: métodos de pagamento (depósito)
    // ===============================
    function toggleFields() {
        const selected = methodSelect ? methodSelect.value : '';
        if (mbwayFields) mbwayFields.style.display = selected === "MB Way" ? "block" : "none";
        if (cardFields) cardFields.style.display = selected === "Cartão de Crédito" ? "block" : "none";

        const inputs = [
            ...(mbwayFields ? mbwayFields.querySelectorAll('input') : []),
            ...(cardFields ? cardFields.querySelectorAll('input') : [])
        ];
        inputs.forEach(input => {
            const paymentFieldsParent = input.closest('.payment-fields');
            input.required = paymentFieldsParent ? isElementVisible(paymentFieldsParent) : false;
        });
    }
    if (methodSelect) {
        methodSelect.addEventListener('change', toggleFields);
        toggleFields();
    }

    // ===============================
    // HISTÓRICO (render)
    // ===============================
    function renderHistoryTable(history) {
        if (!history || history.length === 0) {
            historyContainer.innerHTML = `<div class="empty-state"><p>Nenhum movimento registado ainda.</p></div>`;
            return;
        }

        historyContainer.innerHTML = `
            <div class="payments-table-wrapper">
                <table class="payments-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Tipo</th>
                            <th>Ref.</th>
                            <th>Valor</th>
                            <th>Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${history.map(item => {
                            const sign = (item.signed_montante ?? (item.type === 'LOAD_BALANCE' ? 1 : -1)) > 0 ? '+' : '-';
                            const amountClass = sign === '+' ? 'amount-positive' : 'amount-negative';
                            const typeLabel = item.type === 'LOAD_BALANCE' ? 'Carregamento' : (item.type === 'WITHDRAWAL' ? 'Levantamento' : 'Pagamento');
                            const dateStr = item.data_pagamento ? new Date(item.data_pagamento).toLocaleString('pt-PT') : '-';
                            const ref = item.referencia_gateway ?? '-';
                            const montante = item.montante ?? 0;
                            const saldoApos = item.saldo_apos ?? item.balance_after ?? 0;
                            return `
                                <tr>
                                    <td>${dateStr}</td>
                                    <td>${typeLabel}</td>
                                    <td>${ref}</td>
                                    <td class="${amountClass}">${sign}${formatPrice(montante)}</td>
                                    <td>${formatPrice(saldoApos)}</td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    // ===============================
    // LOAD DATA (balance + history)
    // ===============================
    async function loadData() {
        if (!historyContainer) return;
        historyContainer.innerHTML = '<p>A carregar dados...</p>';

        if (SIMULATE) {
            const balance = parseFloat(localStorage.getItem('sim_balance') || '0');
            const history = JSON.parse(localStorage.getItem('sim_history') || '[]');
            if (balanceDisplay) balanceDisplay.textContent = formatPrice(balance);
            renderHistoryTable(history);
            return;
        }

        try {
            const response = await fetch(BALANCE_API_URL, { method: 'GET', credentials: 'include', cache: 'no-store' });
            const data = await response.json();
            if (!data.success) {
                showFeedback(data.message || 'Erro ao carregar dados.', 'error');
                if (balanceDisplay) balanceDisplay.textContent = '€N/A';
                return;
            }
            if (balanceDisplay) balanceDisplay.textContent = formatPrice(data.balance);
            renderHistoryTable(data.history || []);
        } catch (err) {
            console.error(err);
            showFeedback('Erro de rede ao carregar dados.', 'error');
            if (balanceDisplay) balanceDisplay.textContent = '€N/A';
        }
    }

    // ===============================
    // Mostrar painel de depósito (e pré-preencher se booking)
    // ===============================
    function openDepositSection() {
        if (depositSection) depositSection.style.display = 'block';
        if (bookingId && hiddenBookingInput && reservaInfo && reservaIdEl) {
            hiddenBookingInput.value = bookingId;
            reservaInfo.style.display = 'block';
            reservaIdEl.textContent = `#${bookingId}`;
            if (amountParam && amountInput) {
                amountInput.value = amountParam;
                amountInput.readOnly = true;
                if (loadSubmitBtn) loadSubmitBtn.textContent = '✅ Confirmar Pagamento de Reserva';
            }
        }
        if (amountInput) amountInput.focus();
    }

    if (bookingId && amountParam) openDepositSection();
    if (openDepositBtn) openDepositBtn.addEventListener('click', (e) => { e.preventDefault(); openDepositSection(); depositSection.scrollIntoView({ behavior: 'smooth' }); });

    // ===============================
    // SUBMISSÃO DO PAGAMENTO (carregamento OR booking payment using wallet)
    // ===============================
    if (paymentForm) {
        paymentForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (isSubmitting) return;
            isSubmitting = true;

            const rawAmount = amountInput ? amountInput.value : '0';
            const amount = parseFloat(rawAmount);
            const method = methodSelect ? methodSelect.value : '';

            const min = parseFloat(amountInput?.getAttribute('min') || '0');
            const max = parseFloat(amountInput?.getAttribute('max') || '1000000');

            if (!amount || amount <= 0 || Number.isNaN(amount) || amount < min || amount > max || !method) {
                showFeedback('Preencha correctamente os campos e verifique o valor.', 'error');
                isSubmitting = false;
                return;
            }

            // validações por método
            if (method === 'MB Way') {
                const phone = document.getElementById('mbway_number')?.value?.trim() || '';
                if (!/^\d{9}$/.test(phone)) { showFeedback('Número MB Way inválido (9 dígitos).', 'error'); isSubmitting = false; return; }
            } else if (method === 'Cartão de Crédito') {
                const cardNumber = document.getElementById('card_number')?.value.replace(/\s+/g, '') || '';
                const expiry = document.getElementById('card_expiry')?.value.trim() || '';
                const cvv = document.getElementById('card_cvv')?.value?.trim() || '';
                if (!/^\d{13,19}$/.test(cardNumber) || !/^\d{2}\/\d{2}$/.test(expiry) || !/^\d{3,4}$/.test(cvv)) {
                    showFeedback('Dados do cartão inválidos.', 'error'); isSubmitting = false; return;
                }
            }

            loadSubmitBtn.disabled = true;
            const originalBtnText = loadSubmitBtn.textContent;
            loadSubmitBtn.textContent = 'A processar...';

            try {
                const payload = { amount, method };
                if (bookingId || (hiddenBookingInput && hiddenBookingInput.value)) payload.booking_id = bookingId || hiddenBookingInput.value;
                const mbwayNumber = document.getElementById('mbway_number')?.value?.trim();
                if (mbwayNumber) payload.mbway_number = mbwayNumber;
                const cardNumber = document.getElementById('card_number')?.value?.replace(/\s+/g, '');
                if (cardNumber) payload.card_number = cardNumber;

                if (SIMULATE) {
                    // Simulação local (não recomendado para produção)
                    const balance = parseFloat(localStorage.getItem('sim_balance') || '0');
                    const newBalance = parseFloat((balance + amount).toFixed(2));
                    localStorage.setItem('sim_balance', newBalance.toFixed(2));
                    const hist = JSON.parse(localStorage.getItem('sim_history') || '[]');
                    hist.unshift({ data_pagamento: new Date().toISOString(), type: 'LOAD_BALANCE', referencia_gateway: `SIM-${Date.now()}`, montante: amount, saldo_apos: newBalance });
                    localStorage.setItem('sim_history', JSON.stringify(hist));
                    await loadData();
                    showFeedback(`Simulação: carregamento de ${formatPrice(amount)} efetuado.`, 'success');
                    if (payload.booking_id) { window.location.href = 'parent_bookings.html'; return; }
                } else {
                    const response = await fetch(PROCESS_API_URL, {
                        method: 'POST',
                        credentials: 'include',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });

                    // handle non-json or network error
                    let result;
                    try {
                        result = await response.json();
                    } catch (err) {
                        console.error('Resposta inválida do servidor', err);
                        showFeedback('Resposta inválida do servidor.', 'error');
                        return;
                    }

                    if (result.success) {
                        sessionStorage.setItem(FEEDBACK_STORAGE_KEY, JSON.stringify({ message: result.message || 'Pagamento efectuado.', type: 'success' }));
                        if (payload.booking_id) {
                            // pagamento de reserva efetuado — redirecionar para reservas
                            window.location.href = 'parent_bookings.html';
                            return;
                        } else {
                            await loadData();
                            showFeedback(result.message || 'Pagamento efectuado.', 'success');
                        }
                    } else {
                        const msg = (result.message || '').toLowerCase();
                        if (msg.includes('saldo insuficiente')) {
                            showFeedback(result.message || 'Saldo insuficiente. Por favor carregue a sua wallet.', 'error');

                            // abrir secção de depósito com valores preenchidos para completar pagamento da reserva
                            if (payload.booking_id) {
                                openDepositSection();
                                if (hiddenBookingInput) hiddenBookingInput.value = payload.booking_id;
                                if (amountInput) {
                                    amountInput.value = amount;
                                    amountInput.readOnly = true;
                                }
                                if (loadSubmitBtn) loadSubmitBtn.textContent = '✅ Confirmar Pagamento de Reserva';
                            }
                        } else {
                            showFeedback(result.message || 'Erro no pagamento.', 'error');
                        }
                    }
                }

            } catch (err) {
                console.error(err);
                showFeedback('Erro de rede ou do servidor.', 'error');
            } finally {
                loadSubmitBtn.disabled = false;
                loadSubmitBtn.textContent = originalBtnText || '✅ Efetuar Pagamento';
                isSubmitting = false;
            }
        });
    }

    // ===============================
    // INIT
    // ===============================
    handleInitialFeedback();
    loadData();

});