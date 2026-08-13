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
    const searchInput = document.getElementById('searchInput');
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
            document.getElementById('filterAno').value = '';
            if (tomSelectMunicipio) tomSelectMunicipio.setValue('');
            document.getElementById('filterCargo').value = '';
            document.getElementById('filterPartido').value = '';
            document.getElementById('filterSituacao').value = '';
            document.getElementById('filterSearch').value = '';
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
            showToast('Erro ao atualizar dashboard: ' + (data.error || 'Desconhecido'), 'error');
            return;
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

// Renderiza KPIs
function renderKPIs(kpis) {
    document.getElementById('kpiTotalVotos').innerText = formatNumber(kpis.total_votos);
    document.getElementById('kpiTotalCandidatos').innerText = formatNumber(kpis.total_candidatos);
    document.getElementById('kpiTotalMunicipios').innerText = formatNumber(kpis.total_municipios);
    document.getElementById('kpiTotalPartidos').innerText = formatNumber(kpis.total_partidos);
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
        if (c.ds_sit_totalizacao.includes('ELEITO')) badgeClass = 'badge-eleito';
        else if (c.ds_sit_totalizacao.includes('NÃO ELEITO')) badgeClass = 'badge-nao-eleito';

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

// Renderiza Gráfico de Rosca por Partido
function renderPartyChart(partyVotes) {
    const ctx = document.getElementById('chartPartyCanvas')?.getContext('2d');
    if (!ctx) return;

    if (chartParty) chartParty.destroy();

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
                borderColor: isDark ? '#131b2e' : '#ffffff'
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

// Renderiza Gráfico de Tendência Histórica
function renderTrendChart(historicTrend) {
    const ctx = document.getElementById('chartTrendCanvas')?.getContext('2d');
    if (!ctx) return;

    if (chartTrend) chartTrend.destroy();

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

// Renderiza Gráfico de Situação em Coluna Única Full Width
function renderSituationChart(situationBreakdown) {
    const ctx = document.getElementById('chartSituationCanvas')?.getContext('2d');
    if (!ctx) return;

    if (chartSituation) chartSituation.destroy();

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

// Renderiza Insights Estratégicos de Campanha
function renderStrategicInsights(insights) {
    const container = document.getElementById('strategicInsightsContainer');
    if (!container) return;

    if (!insights || insights.length === 0) {
        container.innerHTML = `<div style="color: var(--text-muted);">Sem insights suficientes para os filtros selecionados.</div>`;
        return;
    }

    container.innerHTML = insights.map(i => `
        <div class="insight-card ${i.type}">
            <div class="insight-header">
                <span class="insight-badge">${i.badge}</span>
            </div>
            <div class="insight-title">${i.title}</div>
            <div class="insight-message">${i.message}</div>
            <div class="insight-action"><i class="fas fa-lightbulb"></i> ${i.action}</div>
        </div>
    `).join('');
}
