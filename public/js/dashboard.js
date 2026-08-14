/**
 * Data Warehouse Eleitoral - Dashboard & Analytics Engine
 */

let chartParty = null;
let chartTrend = null;
let chartSituation = null;
let tomSelectMunicipio = null;

document.addEventListener('DOMContentLoaded', () => {
    loadFilterOptions();
    loadDashboardData();
    bindFilterEvents();

    window.addEventListener('themeChanged', () => {
        if (chartParty) chartParty.update();
        if (chartTrend) chartTrend.update();
        if (chartSituation) chartSituation.update();
    });
});

// Carrega as opções de filtro
async function loadFilterOptions() {
    try {
        const res = await fetch('api/options.php');
        const data = await res.json();

        if (!data.success) return;

        if (data.db_type && typeof updateDbStatusBadge === 'function') {
            updateDbStatusBadge(data.db_type, data.db_driver, data.total_db_records);
        }

        // Popula Ano
        const selAno = document.getElementById('filterAno');
        if (selAno) {
            selAno.innerHTML = '<option value="">Todos os Anos</option>' + 
                data.anos.map(ano => `<option value="${ano}">${ano}</option>`).join('');
        }

        // Popula Município
        const selMuni = document.getElementById('filterMunicipio');

        // Destrói instância prévia do TomSelect no município se existir
        if (tomSelectMunicipio) {
            tomSelectMunicipio.destroy();
            tomSelectMunicipio = null;
        }
        if (selMuni && selMuni.tomselect) {
            selMuni.tomselect.destroy();
        }

        if (selMuni) {
            selMuni.innerHTML = '<option value="">Todos os Municípios</option>' + 
                data.municipios.map(m => `<option value="${m}">${m}</option>`).join('');
        }

        // Inicializa TomSelect no Município para permitir busca por texto
        if (window.TomSelect && selMuni) {
            tomSelectMunicipio = new TomSelect('#filterMunicipio', {
                create: false,
                placeholder: 'Buscar Município...',
                allowEmptyOption: true
            });
        }

        // Popula Cargo
        const selCargo = document.getElementById('filterCargo');
        if (selCargo) {
            selCargo.innerHTML = '<option value="">Todos os Cargos</option>' + 
                data.cargos.map(c => `<option value="${c}">${c}</option>`).join('');
        }

        // Popula Partido
        const selPartido = document.getElementById('filterPartido');
        if (selPartido) {
            selPartido.innerHTML = '<option value="">Todos os Partidos</option>' + 
                data.partidos.map(p => `<option value="${p}">${p}</option>`).join('');
        }

        // Popula Situação
        const selSit = document.getElementById('filterSituacao');
        if (selSit) {
            selSit.innerHTML = '<option value="">Todas as Situações</option>' + 
                data.situacoes.map(s => `<option value="${s}">${s}</option>`).join('');
        }

    } catch (err) {
        console.error("Erro ao carregar opções:", err);
    }
}

// Vincula ouvintes de alteração nos filtros
function bindFilterEvents() {
    const filters = ['filterAno', 'filterCargo', 'filterPartido', 'filterSituacao'];
    filters.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', () => loadDashboardData());
        }
    });

    const elMuni = document.getElementById('filterMunicipio');
    if (elMuni) {
        elMuni.addEventListener('change', () => loadDashboardData());
    }

    let searchDebounceTimeout = null;
    // CORREÇÃO DE BUG: Alterado de 'searchInput' para 'filterSearch' para corresponder ao ID no public/index.php
    const searchInput = document.getElementById('filterSearch');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(searchDebounceTimeout);
            searchDebounceTimeout = setTimeout(() => {
                loadDashboardData();
            }, 300);
        });
    }

    const btnReset = document.getElementById('btnResetFilters');
    if (btnReset) {
        btnReset.addEventListener('click', () => {
            const elAno = document.getElementById('filterAno');
            if (elAno) elAno.value = '';
            
            if (tomSelectMunicipio) tomSelectMunicipio.setValue('');
            
            const elCargo = document.getElementById('filterCargo');
            if (elCargo) elCargo.value = '';

            const elPartido = document.getElementById('filterPartido');
            if (elPartido) elPartido.value = '';

            const elSit = document.getElementById('filterSituacao');
            if (elSit) elSit.value = '';

            const elSearch = document.getElementById('filterSearch');
            if (elSearch) elSearch.value = '';

            loadDashboardData();
        });
    }
}

// Carrega os dados atualizados do Dashboard via API
async function loadDashboardData() {
    const ano = document.getElementById('filterAno')?.value || '';
    const municipio = document.getElementById('filterMunicipio')?.value || '';
    const cargo = document.getElementById('filterCargo')?.value || '';
    const partido = document.getElementById('filterPartido')?.value || '';
    const situacao = document.getElementById('filterSituacao')?.value || '';
    const q = document.getElementById('filterSearch')?.value || '';

    const queryParams = new URLSearchParams({ ano, municipio, cargo, partido, situacao, q });

    try {
        const res = await fetch(`api/dashboard.php?${queryParams.toString()}`);
        const data = await res.json();

        if (!data.success) {
            if (typeof showToast === 'function') {
                showToast('Erro ao atualizar dashboard: ' + (data.error || 'Desconhecido'), 'error');
            } else {
                console.error("Erro ao carregar dados:", data.error);
            }
            return;
        }

        if (data.db_type && data.kpis && typeof updateDbStatusBadge === 'function') {
            updateDbStatusBadge(data.db_type, data.db_driver, data.kpis.total_candidatos);
        }

        renderKPIs(data.kpis);
        renderPartyChart(data.party_votes);
        renderTrendChart(data.historic_trend);
        renderSituationChart(data.situation_breakdown);
        renderRankingTable(data.ranking);
        renderStrategicInsights(data.insights);

    } catch (err) {
        console.error("Erro ao carregar dados do dashboard:", err);
    }
}

// Renderiza KPIs com métricas aprimoradas HHI & QE
function renderKPIs(kpis) {
    if (!kpis) return;
    const elVotos = document.getElementById('kpiTotalVotos');
    if (elVotos) elVotos.innerText = formatNumber(kpis.total_votos);

    const elCands = document.getElementById('kpiTotalCandidatos');
    if (elCands) elCands.innerText = formatNumber(kpis.total_candidatos);

    const elMunis = document.getElementById('kpiTotalMunicipios');
    if (elMunis) elMunis.innerText = formatNumber(kpis.total_municipios);

    const elParts = document.getElementById('kpiTotalPartidos');
    if (elParts) elParts.innerText = formatNumber(kpis.total_partidos);

    const elHHI = document.getElementById('kpiHHI');
    if (elHHI && kpis.hhi !== undefined) {
        elHHI.innerText = `${formatNumber(kpis.hhi)} pts (ENP: ${kpis.enp})`;
    }

    const elQE = document.getElementById('kpiQE');
    if (elQE && kpis.qe !== undefined) {
        elQE.innerText = `${formatNumber(kpis.qe)} / vaga`;
    }
}

// Renderiza Tabela de Ranking
function renderRankingTable(ranking) {
    const tbody = document.getElementById('tableRankingBody');
    if (!tbody) return;

    if (!ranking || ranking.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2rem;">Nenhum candidato encontrado com os filtros aplicados.</td></tr>`;
        return;
    }

    tbody.innerHTML = ranking.map((c, index) => {
        let badgeClass = 'badge-suplente';
        if (c.ds_sit_totalizacao && c.ds_sit_totalizacao.includes('ELEITO')) badgeClass = 'badge-eleito';
        else if (c.ds_sit_totalizacao && c.ds_sit_totalizacao.includes('NÃO ELEITO')) badgeClass = 'badge-nao-eleito';

        return `
            <tr>
                <td><strong>#${index + 1}</strong></td>
                <td>
                    <div style="font-weight: 700; color: var(--text-primary);">${c.nm_urna_candidato}</div>
                    <div style="font-size: 0.78rem; color: var(--text-muted);">${c.nm_candidato} (${c.nr_candidato})</div>
                </td>
                <td><span style="font-weight: 800; color: var(--accent-primary);">${c.sg_partido}</span></td>
                <td>${c.nm_municipio}</td>
                <td>${c.ds_cargo}</td>
                <td><span class="badge ${badgeClass}">${c.ds_sit_totalizacao}</span></td>
                <td><strong style="font-family: 'JetBrains Mono', monospace; font-size: 0.95rem;">${formatNumber(c.qt_votos_nom_validos)}</strong> (${c.pc_votos_validos}%)</td>
            </tr>
        `;
    }).join('');
}

// Renderiza Gráfico de Rosca por Partido (Garante destruição total de instâncias anteriores)
function renderPartyChart(partyVotes) {
    const canvasEl = document.getElementById('chartPartyCanvas');
    if (!canvasEl) return;

    // Destrói qualquer instância do Chart.js associada ao elemento canvas
    if (window.Chart && typeof Chart.getChart === 'function') {
        const existingChart = Chart.getChart(canvasEl);
        if (existingChart) existingChart.destroy();
    }
    if (chartParty) {
        chartParty.destroy();
        chartParty = null;
    }

    if (!partyVotes || partyVotes.length === 0) return;

    const ctx = canvasEl.getContext('2d');
    const labels = partyVotes.slice(0, 8).map(p => p.party);
    const data = partyVotes.slice(0, 8).map(p => p.votes);

    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const textColor = isDark ? '#f8fafc' : '#0f172a';

    chartParty = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: [
                    '#2563eb', '#10b981', '#f59e0b', '#ef4444', 
                    '#8b5cf6', '#06b6d4', '#ec4899', '#64748b'
                ],
                borderWidth: 2,
                borderColor: isDark ? '#111827' : '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: { color: textColor, font: { family: 'Plus Jakarta Sans', weight: '600' } }
                }
            }
        }
    });
}

// Renderiza Gráfico de Tendência Histórica (Garante destruição total de instâncias anteriores)
function renderTrendChart(historicTrend) {
    const canvasEl = document.getElementById('chartTrendCanvas');
    if (!canvasEl) return;

    if (window.Chart && typeof Chart.getChart === 'function') {
        const existingChart = Chart.getChart(canvasEl);
        if (existingChart) existingChart.destroy();
    }
    if (chartTrend) {
        chartTrend.destroy();
        chartTrend = null;
    }

    if (!historicTrend || historicTrend.length === 0) return;

    const ctx = canvasEl.getContext('2d');
    const labels = historicTrend.map(t => `Ano ${t.Ano}`);
    const data = historicTrend.map(t => t.total_votos);

    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const textColor = isDark ? '#f8fafc' : '#0f172a';
    const gridColor = isDark ? '#22304d' : '#e2e8f0';

    chartTrend = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Total de Votos Válidos',
                data: data,
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.15)',
                fill: true,
                tension: 0.3,
                borderWidth: 3,
                pointRadius: 6,
                pointBackgroundColor: '#10b981'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { ticks: { color: textColor }, grid: { color: gridColor } },
                y: { ticks: { color: textColor }, grid: { color: gridColor } }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
}

// Renderiza Gráfico de Situação (Garante destruição total de instâncias anteriores)
function renderSituationChart(situationBreakdown) {
    const canvasEl = document.getElementById('chartSituationCanvas');
    if (!canvasEl) return;

    if (window.Chart && typeof Chart.getChart === 'function') {
        const existingChart = Chart.getChart(canvasEl);
        if (existingChart) existingChart.destroy();
    }
    if (chartSituation) {
        chartSituation.destroy();
        chartSituation = null;
    }

    if (!situationBreakdown || situationBreakdown.length === 0) return;

    const ctx = canvasEl.getContext('2d');
    const labels = situationBreakdown.map(s => s.ds_sit_totalizacao);
    const data = situationBreakdown.map(s => s.total);

    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const textColor = isDark ? '#f8fafc' : '#0f172a';
    const gridColor = isDark ? '#22304d' : '#e2e8f0';

    chartSituation = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Total de Candidatos',
                data: data,
                backgroundColor: [
                    '#10b981', '#ef4444', '#f59e0b', '#3b82f6', '#8b5cf6', '#ec4899'
                ],
                borderRadius: 8,
                maxBarThickness: 60
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { ticks: { color: textColor, font: { weight: '600' } }, grid: { display: false } },
                y: { ticks: { color: textColor }, grid: { color: gridColor } }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
}

// Formatador auxiliar de marcação markdown simples (**texto**)
function formatMarkdown(text) {
    if (!text) return '';
    return text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
}

// Renderiza Insights Estratégicos de Campanha
function renderStrategicInsights(insights) {
    const container = document.getElementById('strategicInsightsContainer');
    if (!container) return;

    if (!insights || insights.length === 0) {
        container.innerHTML = `<div style="color: var(--text-muted); padding: 1.5rem; text-align: center;">Sem insights suficientes para os filtros selecionados.</div>`;
        return;
    }

    container.innerHTML = insights.map(i => `
        <div class="insight-card ${i.type || 'default'}">
            <div class="insight-header">
                <span class="insight-badge">${i.badge || 'Insight Estratégico'}</span>
            </div>
            <div class="insight-title">${i.title}</div>
            <div class="insight-message">${formatMarkdown(i.message)}</div>
            <div class="insight-action"><i class="fas fa-lightbulb"></i> ${formatMarkdown(i.action)}</div>
        </div>
    `).join('');
}
