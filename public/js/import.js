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

    if (progressBarFill) progressBarFill.style.width = pct + '%';
    if (progressStatus && msg) progressStatus.innerText = msg;
};

window.onImportComplete = function(inserted, skipped, totalInDb) {
    const progressBarFill = document.getElementById('progressBarFill');
    const progressStatus = document.getElementById('progressStatusText');
    const badge = document.getElementById('importLiveBadge');

    if (inserted > 0) {
        if (progressBarFill) progressBarFill.style.width = '100%';
        if (progressStatus) {
            progressStatus.innerHTML = `<span style="color: var(--emerald); font-weight: 700;"><i class="fas fa-check-circle"></i> Importação Concluída com Sucesso!</span> (${formatNumber(inserted)} inseridos | Total no Banco: ${formatNumber(totalInDb)})`;
        }
        if (badge) {
            badge.style.background = 'var(--emerald)';
            badge.innerHTML = `<i class="fas fa-check-circle"></i> CONCLUÍDO (${formatNumber(inserted)} NOVOS REGISTROS)`;
        }

        showToast(`Sucesso! ${inserted} registros inseridos. Total no Banco: ${totalInDb}`, 'success');

        // Recarrega todos os módulos do painel
        if (typeof loadFilterOptions === 'function') loadFilterOptions();
        if (typeof loadDashboardData === 'function') loadDashboardData();
        if (typeof initCompareSelectors === 'function') initCompareSelectors();

    } else {
        if (progressBarFill) progressBarFill.style.width = '0%';
        if (progressStatus) {
            progressStatus.innerHTML = `<span style="color: var(--rose); font-weight: 700;"><i class="fas fa-exclamation-triangle"></i> Falha na Importação.</span> Verifique as mensagens de erro no console abaixo.`;
        }
        if (badge) {
            badge.style.background = 'var(--rose)';
            badge.innerHTML = `<i class="fas fa-times-circle"></i> ERRO NA IMPORTAÇÃO`;
        }
        showToast('Erro ao importar arquivo CSV. Consulte os logs no console.', 'error');
    }
};

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
        if (files.length > 0) {
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
        if (!file || !file.name.toLowerCase().endsWith('.csv')) {
            showToast('Por favor, selecione um arquivo válido no formato .CSV', 'error');
            return;
        }

        // Exibe contêineres de progresso e terminal iframe
        if (progressContainer) progressContainer.style.display = 'block';
        if (streamContainer) streamContainer.style.display = 'block';
        if (progressBarFill) progressBarFill.style.width = '5%';
        if (progressStatus) progressStatus.innerText = `Enviando arquivo ${file.name} ao servidor...`;
        
        if (badge) {
            badge.style.background = 'var(--accent-primary)';
            badge.innerHTML = `<i class="fas fa-sync fa-spin"></i> PROCESSANDO`;
        }

        // Submete o formulário direto para o Iframe de streaming
        form.submit();
    }
}
