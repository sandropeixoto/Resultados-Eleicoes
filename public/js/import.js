/**
 * Data Warehouse Eleitoral - Importador CSV com Terminal Streaming em Iframe
 */

document.addEventListener('DOMContentLoaded', () => {
    initCSVImporter();
});

// Funções globais de callback chamadas pelas tags <script> emitidas via streaming pelo Iframe
window.updateImportProgress = function(pct, msg) {
    const progressBarFill = document.getElementById('progressBarFill');
    const progressStatus = document.getElementById('progressStatusText');

    if (progressBarFill) progressBarFill.style.width = Math.min(100, Math.max(0, pct)) + '%';
    if (progressStatus && msg) progressStatus.innerText = msg;
};

window.onImportComplete = function(inserted, skipped, totalInDb) {
    const progressBarFill = document.getElementById('progressBarFill');
    const progressStatus = document.getElementById('progressStatusText');
    const badge = document.getElementById('importLiveBadge');

    if (inserted > 0 || (inserted === 0 && skipped === 0 && totalInDb > 0)) {
        if (progressBarFill) progressBarFill.style.width = '100%';
        if (progressStatus) {
            progressStatus.innerHTML = `<span style="color: var(--emerald); font-weight: 700;"><i class="fas fa-check-circle"></i> Importação Concluída com Sucesso!</span> (${formatNumber(inserted)} processados | Total no Banco: ${formatNumber(totalInDb)})`;
        }
        if (badge) {
            badge.style.background = 'var(--emerald)';
            badge.innerHTML = `<i class="fas fa-check-circle"></i> CONCLUÍDO (${formatNumber(inserted)} REGISTROS)`;
        }

        if (typeof showToast === 'function') {
            showToast(`Sucesso! ${formatNumber(inserted)} registros processados. Total no banco: ${formatNumber(totalInDb)}`, 'success');
        }

        // Recarrega todos os módulos do painel
        if (typeof loadFilterOptions === 'function') loadFilterOptions();
        if (typeof loadDashboardData === 'function') loadDashboardData();
        if (typeof initCompareSelectors === 'function') initCompareSelectors();

    } else {
        if (progressBarFill) progressBarFill.style.width = '0%';
        if (progressStatus) {
            progressStatus.innerHTML = `<span style="color: var(--rose); font-weight: 700;"><i class="fas fa-exclamation-triangle"></i> Falha na Importação.</span> Verifique as mensagens de erro no terminal abaixo.`;
        }
        if (badge) {
            badge.style.background = 'var(--rose)';
            badge.innerHTML = `<i class="fas fa-times-circle"></i> ERRO NA IMPORTAÇÃO`;
        }
        if (typeof showToast === 'function') {
            showToast('Erro ao importar arquivo CSV. Consulte o terminal de logs.', 'error');
        }
    }
};

function formatNumber(num) {
    return new Intl.NumberFormat('pt-BR').format(num || 0);
}

function initCSVImporter() {
    const dropzone = document.getElementById('dropzoneArea');
    const fileInput = document.getElementById('csvFileInput');
    const form = document.getElementById('importForm');
    const progressContainer = document.getElementById('importProgressContainer');
    const streamContainer = document.getElementById('importStreamContainer');
    const progressBarFill = document.getElementById('progressBarFill');
    const progressStatus = document.getElementById('progressStatusText');
    const badge = document.getElementById('importLiveBadge');

    if (!dropzone || !fileInput || !form) return;

    // Clique na dropzone dispara file picker
    dropzone.addEventListener('click', () => fileInput.click());
    dropzone.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            fileInput.click();
        }
    });

    // Drag & Drop handlers
    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.add('dragover');
        });
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('dragover');
        });
    });

    dropzone.addEventListener('drop', (e) => {
        const files = e.dataTransfer.files;
        if (files && files.length > 0) {
            fileInput.files = files;
            startUpload();
        }
    });

    fileInput.addEventListener('change', () => {
        if (fileInput.files.length > 0) {
            startUpload();
        }
    });

    function startUpload() {
        const file = fileInput.files[0];
        if (!file) return;

        if (!file.name.toLowerCase().endsWith('.csv')) {
            if (typeof showToast === 'function') {
                showToast('Por favor, selecione um arquivo válido no formato .CSV', 'error');
            } else {
                alert('Por favor, selecione um arquivo válido no formato .CSV');
            }
            return;
        }

        if (file.size === 0) {
            if (typeof showToast === 'function') {
                showToast('O arquivo CSV selecionado está vazio (0 bytes).', 'error');
            } else {
                alert('O arquivo CSV selecionado está vazio (0 bytes).');
            }
            return;
        }

        // Exibe contêineres de progresso e terminal iframe
        if (progressContainer) progressContainer.style.display = 'block';
        if (streamContainer) streamContainer.style.display = 'block';
        if (progressBarFill) progressBarFill.style.width = '5%';
        if (progressStatus) progressStatus.innerText = `Enviando arquivo ${file.name} (${formatNumber(Math.round(file.size / 1024))} KB) ao servidor...`;
        
        if (badge) {
            badge.style.background = 'var(--accent-primary)';
            badge.innerHTML = `<i class="fas fa-sync fa-spin"></i> PROCESSANDO`;
        }

        // Submete o formulário direto para o Iframe de streaming
        form.submit();
    }
}
