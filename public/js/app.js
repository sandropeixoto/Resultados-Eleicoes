/**
 * Data Warehouse Eleitoral - Core App, Theme Controller & Accessibility Engine
 */

document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initTabs();
});

// Switcher de Temas (Dark Obsidian & Clean Minimalist)
function initTheme() {
    const themeBtn = document.getElementById('themeToggleBtn');
    const savedTheme = localStorage.getItem('theme') || 'dark';

    document.documentElement.setAttribute('data-theme', savedTheme);
    updateThemeIcon(savedTheme);

    if (themeBtn) {
        themeBtn.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
            
            // Dispara evento global para atualizar gráficos Chart.js com as cores do novo tema
            window.dispatchEvent(new CustomEvent('themeChanged', { detail: { theme: newTheme } }));
        });
    }
}

function updateThemeIcon(theme) {
    const themeBtn = document.getElementById('themeToggleBtn');
    if (!themeBtn) return;
    if (theme === 'dark') {
        themeBtn.innerHTML = '<i class="fas fa-sun" style="color: #fbbf24;" aria-hidden="true"></i>';
        themeBtn.setAttribute('title', 'Alternar para Tema Clean');
        themeBtn.setAttribute('aria-label', 'Alternar para Tema Clean');
    } else {
        themeBtn.innerHTML = '<i class="fas fa-moon" style="color: #475569;" aria-hidden="true"></i>';
        themeBtn.setAttribute('title', 'Alternar para Tema Dark Obsidian');
        themeBtn.setAttribute('aria-label', 'Alternar para Tema Dark Obsidian');
    }
}

// Navegação por Abas com Acessibilidade ARIA Completa
function initTabs() {
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-tab');

            tabBtns.forEach(b => {
                b.classList.remove('active');
                b.setAttribute('aria-selected', 'false');
            });

            tabContents.forEach(c => c.classList.remove('active'));

            btn.classList.add('active');
            btn.setAttribute('aria-selected', 'true');

            const targetContent = document.getElementById(targetId);
            if (targetContent) {
                targetContent.classList.add('active');
                targetContent.focus();
            }

            // Recarrega gráficos/dados ao navegar para abas específicas
            if (targetId === 'comparador' && typeof loadCompareData === 'function') {
                loadCompareData();
            } else if (targetId === 'visao-geral' && typeof loadDashboardData === 'function') {
                loadDashboardData();
            }
        });
    });
}

// Formatador Numérico no Padrão Brasileiro (BRL)
function formatNumber(num) {
    return new Intl.NumberFormat('pt-BR').format(num || 0);
}

// Sistema de Notificação Toast Estilizado e Acessível
function showToast(message, type = 'info') {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.setAttribute('aria-live', 'polite');
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast-item ${type}`;

    let iconClass = 'fa-info-circle';
    if (type === 'success') iconClass = 'fa-check-circle';
    else if (type === 'error') iconClass = 'fa-exclamation-triangle';
    else if (type === 'warning') iconClass = 'fa-exclamation-circle';

    toast.innerHTML = `
        <i class="fas ${iconClass} toast-icon" aria-hidden="true"></i>
        <div class="toast-body">${message}</div>
        <button class="toast-close" aria-label="Fechar notificação">&times;</button>
        <div class="toast-progress"></div>
    `;

    const closeBtn = toast.querySelector('.toast-close');
    closeBtn.addEventListener('click', () => {
        toast.remove();
    });

    container.appendChild(toast);

    setTimeout(() => {
        if (toast.parentNode) {
            toast.style.animation = 'toastSlideIn 0.3s reverse forwards';
            setTimeout(() => toast.remove(), 300);
        }
    }, 4000);
}
