/**
 * Data Warehouse Eleitoral - Core App & Theme Controller
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
            
            // Dispara evento para atualizar gráficos Chart.js com as novas cores do tema
            window.dispatchEvent(new CustomEvent('themeChanged', { detail: { theme: newTheme } }));
        });
    }
}

function updateThemeIcon(theme) {
    const themeBtn = document.getElementById('themeToggleBtn');
    if (!themeBtn) return;
    if (theme === 'dark') {
        themeBtn.innerHTML = '<i class="fas fa-sun" style="color: #fbbf24;"></i>';
        themeBtn.setAttribute('title', 'Alternar para Tema Clean');
    } else {
        themeBtn.innerHTML = '<i class="fas fa-moon" style="color: #475569;"></i>';
        themeBtn.setAttribute('title', 'Alternar para Tema Dark');
    }
}

// Navegação por Abas
function initTabs() {
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-tab');

            tabBtns.forEach(b => b.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));

            btn.classList.add('active');
            const targetContent = document.getElementById(targetId);
            if (targetContent) {
                targetContent.classList.add('active');
            }

            // Recarrega gráficos se for para a aba de comparador ou dashboard
            if (targetId === 'comparador' && typeof loadCompareData === 'function') {
                loadCompareData();
            } else if (targetId === 'visao-geral' && typeof loadDashboardData === 'function') {
                loadDashboardData();
            }
        });
    });
}

// Formatador Numérico BRL
function formatNumber(num) {
    return new Intl.NumberFormat('pt-BR').format(num || 0);
}

// Toast Notificações Amigáveis
function showToast(message, type = 'info') {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.style.cssText = 'position: fixed; bottom: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px;';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    const bg = type === 'success' ? 'var(--emerald)' : (type === 'error' ? 'var(--rose)' : 'var(--accent-primary)');
    toast.style.cssText = `background: ${bg}; color: #ffffff; padding: 12px 20px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; box-shadow: 0 4px 12px rgba(0,0,0,0.3); animation: fadeIn 0.3s ease;`;
    toast.innerHTML = message;

    container.appendChild(toast);
    setTimeout(() => {
        toast.remove();
    }, 4000);
}
