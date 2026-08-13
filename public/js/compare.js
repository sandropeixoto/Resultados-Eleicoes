/**
 * Data Warehouse Eleitoral - Comparador Direto de Candidatos (Head-to-Head)
 */

let tomSelectCandA = null;
let tomSelectCandB = null;
let chartCompareBar = null;

document.addEventListener('DOMContentLoaded', () => {
    initCompareSelectors();
});

function initCompareSelectors() {
    const selA = document.getElementById('cand_a_select');
    const selB = document.getElementById('cand_b_select');

    if (!selA || !selB) return;

    if (selA.tomselect) selA.tomselect.destroy();
    if (selB.tomselect) selB.tomselect.destroy();

    const tomConfig = {
        valueField: 'id',
        labelField: 'nm_urna_candidato',
        searchField: ['nm_urna_candidato', 'nm_candidato', 'sg_partido', 'nr_candidato'],
        placeholder: 'Digite para buscar candidato...',
        loadThrottle: 300,
        load: function(query, callback) {
            if (!query.length || query.length < 2) return callback();
            fetch('/api/candidates.php?q=' + encodeURIComponent(query))
                .then(response => response.json())
                .then(json => {
                    callback(json.candidatos || []);
                })
                .catch(() => callback());
        },
        render: {
            option: function(item, escape) {
                return `<div>
                    <strong>${escape(item.nm_urna_candidato)}</strong> (${escape(item.sg_partido)})
                    <span style="font-size: 0.8em; color: #94a3b8;"> - ${escape(item.nm_municipio)} (${escape(item.ds_cargo)})</span>
                </div>`;
            },
            item: function(item, escape) {
                return `<div>${escape(item.nm_urna_candidato)} (${escape(item.sg_partido)} - ${escape(item.nm_municipio)})</div>`;
            }
        }
    };

    new TomSelect('#cand_a_select', tomConfig);
    new TomSelect('#cand_b_select', tomConfig);
}

// Carrega os dados da comparação via API
async function loadCompareData() {
    const candAId = document.getElementById('compareCandA')?.value || '';
    const candBId = document.getElementById('compareCandB')?.value || '';

    if (!candAId || !candBId) return;

    try {
        const res = await fetch(`api/compare.php?cand_a=${encodeURIComponent(candAId)}&cand_b=${encodeURIComponent(candBId)}`);
        const data = await res.json();

        if (!data.success) {
            showToast(data.message || 'Falha ao comparar candidatos.', 'error');
            return;
        }

        renderCandidateCards(data.cand_a, data.cand_b, data.advantage);
        renderAdvantageBanner(data.advantage, data.cand_a, data.cand_b);
        renderCompareChart(data.cand_a, data.cand_b);
        renderTacticalSuggestions(data.tactical_suggestions);

    } catch (err) {
        console.error("Erro ao carregar comparação:", err);
    }
}

// Renderiza Cards dos Candidatos
function renderCandidateCards(candA, candB, advantage) {
    const cardAEl = document.getElementById('candACard');
    const cardBEl = document.getElementById('candBCard');

    const isAWinner = advantage.winner === 'A';
    const isBWinner = advantage.winner === 'B';

    if (cardAEl) {
        cardAEl.className = `candidate-card ${isAWinner ? 'winner-card' : ''}`;
        cardAEl.innerHTML = `
            ${isAWinner ? '<div class="winner-ribbon">LÍDER / ELEITO</div>' : ''}
            <div class="cand-header">
                <div class="cand-avatar">${candA.nm_urna_candidato.charAt(0)}</div>
                <div class="cand-name-title">
                    <h3>${candA.nm_urna_candidato}</h3>
                    <p>${candA.nm_candidato} (${candA.nr_candidato})</p>
                </div>
            </div>
            <div class="cand-stat-box">
                <span class="cand-stat-label">Votos Nominais Válidos</span>
                <span class="cand-stat-num">${formatNumber(candA.qt_votos_nom_validos)}</span>
            </div>
            <div class="cand-stat-box">
                <span class="cand-stat-label">% Votos Válidos</span>
                <span class="cand-stat-num">${candA.pc_votos_validos}%</span>
            </div>
            <div class="cand-stat-box">
                <span class="cand-stat-label">Partido / Coligação</span>
                <span style="font-weight: 700; color: var(--accent-primary);">${candA.sg_partido}</span>
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.5rem;">
                <strong>Coligação:</strong> ${candA.ds_composicao_coligacao || 'Chapa Pura'}
            </div>
        `;
    }

    if (cardBEl) {
        cardBEl.className = `candidate-card ${isBWinner ? 'winner-card' : ''}`;
        cardBEl.innerHTML = `
            ${isBWinner ? '<div class="winner-ribbon">LÍDER / ELEITO</div>' : ''}
            <div class="cand-header">
                <div class="cand-avatar">${candB.nm_urna_candidato.charAt(0)}</div>
                <div class="cand-name-title">
                    <h3>${candB.nm_urna_candidato}</h3>
                    <p>${candB.nm_candidato} (${candB.nr_candidato})</p>
                </div>
            </div>
            <div class="cand-stat-box">
                <span class="cand-stat-label">Votos Nominais Válidos</span>
                <span class="cand-stat-num">${formatNumber(candB.qt_votos_nom_validos)}</span>
            </div>
            <div class="cand-stat-box">
                <span class="cand-stat-label">% Votos Válidos</span>
                <span class="cand-stat-num">${candB.pc_votos_validos}%</span>
            </div>
            <div class="cand-stat-box">
                <span class="cand-stat-label">Partido / Coligação</span>
                <span style="font-weight: 700; color: var(--accent-primary);">${candB.sg_partido}</span>
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.5rem;">
                <strong>Coligação:</strong> ${candB.ds_composicao_coligacao || 'Chapa Pura'}
            </div>
        `;
    }
}

// Renderiza Banner de Vantagem Eleitoral
function renderAdvantageBanner(advantage, candA, candB) {
    const banner = document.getElementById('advantageBanner');
    if (!banner) return;

    if (advantage.winner === 'EMPATE') {
        banner.innerHTML = `
            <div class="advantage-icon"><i class="fas fa-balance-scale"></i></div>
            <div class="advantage-text">
                <h4>Empate Técnico Registrado</h4>
                <p>Ambos os candidatos possuem a mesma votação nominal de ${formatNumber(candA.qt_votos_nom_validos)} votos.</p>
            </div>
        `;
    } else {
        banner.innerHTML = `
            <div class="advantage-icon"><i class="fas fa-trophy"></i></div>
            <div class="advantage-text">
                <h4>Vantagem Apurada: ${advantage.winner_name}</h4>
                <p>Liderança de <strong>${formatNumber(advantage.vote_diff)} votos</strong> (${advantage.lead_pct}% a mais) sobre ${advantage.runner_up_name}.</p>
            </div>
        `;
    }
}

// Renderiza Gráfico Comparativo em Barras
function renderCompareChart(candA, candB) {
    const ctx = document.getElementById('chartCompareBarCanvas')?.getContext('2d');
    if (!ctx) return;

    if (chartCompareBar) chartCompareBar.destroy();

    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const textColor = isDark ? '#f8fafc' : '#0f172a';

    chartCompareBar = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: [candA.nm_urna_candidato, candB.nm_urna_candidato],
            datasets: [{
                label: 'Votos Nominais',
                data: [candA.qt_votos_nom_validos, candB.qt_votos_nom_validos],
                backgroundColor: ['#2563eb', '#10b981'],
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: { ticks: { color: textColor } },
                y: { ticks: { color: textColor } }
            }
        }
    });
}

// Renderiza Sugestões Táticas para Campanhas
function renderTacticalSuggestions(suggestions) {
    const container = document.getElementById('tacticalSuggestionsContainer');
    if (!container) return;

    if (!suggestions || suggestions.length === 0) {
        container.innerHTML = `<div style="color: var(--text-muted);">Nenhuma recomendação gerada.</div>`;
        return;
    }

    container.innerHTML = suggestions.map(s => `
        <div class="insight-card efficiency" style="margin-bottom: 0.85rem;">
            <div class="insight-title">${s.title}</div>
            <div class="insight-message">${s.message}</div>
            <div class="insight-action"><i class="fas fa-chess-knight"></i> ${s.strategy}</div>
        </div>
    `).join('');
}
