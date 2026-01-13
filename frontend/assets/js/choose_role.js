/**
 * choose_role.js
 * Gere a seleção de papel (Babysitter ou Pai) e comunica com o backend.
 */

// 1. Configuração de URLs
// Certifica-te de que esta base não termina em barra para evitar caminhos como //set_role.php
const API_BASE_URL = 'http://localhost/babyhappy_v1/api/auth'; 
const SET_ROLE_API = `${API_BASE_URL}/set_role.php`;

document.addEventListener('DOMContentLoaded', function() {
    const roleButtons = document.querySelectorAll('.btn-role');
    const statusMessage = document.getElementById('role-status');

    if (!statusMessage) {
        console.warn("Elemento 'role-status' não encontrado no HTML.");
    }

    roleButtons.forEach(button => {
        button.addEventListener('click', async function(e) {
            e.preventDefault(); // Previne comportamentos inesperados do link/botão

            const role = this.dataset.role;           // 'babysitter' ou 'parent'
            const redirectUrl = this.dataset.redirect; // Ex: 'registo_babysitter.html'

            if (statusMessage) {
                statusMessage.textContent = `A configurar o seu perfil como ${role}...`;
                statusMessage.style.color = "blue";
            }
            
            try {
                // 2. Chamada à API para definir o papel na sessão PHP
                const response = await fetch(SET_ROLE_API, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ role: role })
                });

                // Lemos primeiro como texto para evitar erro de parse se o PHP enviar lixo
                const responseText = await response.text();
                
                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (parseError) {
                    throw new Error("O servidor não devolveu um JSON válido. Resposta: " + responseText);
                }

                if (response.ok && result.success) {
                    if (statusMessage) statusMessage.textContent = "Sucesso! A redirecionar...";
                    
                    // Pequeno delay para o utilizador ver a mensagem de sucesso
                    setTimeout(() => {
                        window.location.href = redirectUrl;
                    }, 800);
                } else {
                    throw new Error(result.message || 'Erro ao definir o papel no servidor.');
                }

            } catch (error) {
                console.error('Erro na comunicação com a API de set_role:', error);
                
                if (statusMessage) {
                    statusMessage.textContent = "Aviso: Erro de sessão, mas a avançar...";
                    statusMessage.style.color = "orange";
                }

                // Força o avanço após 1.5s mesmo com erro, para não bloquear o utilizador
                setTimeout(() => { 
                    window.location.href = redirectUrl; 
                }, 1500); 
            }
        });
    });
});