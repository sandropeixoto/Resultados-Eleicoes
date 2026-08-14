/**
 * Data Warehouse Eleitoral - Comparador Direto de Candidatos (Head-to-Head)
 * FASE 2 & 3: TomSelect Autocomplete, Métricas Diretas, Sinergia & Sugestões Táticas
 */

let tomSelectCandA = null;
let tomSelectCandB = null;
let chartCompareBar = null;
let lastCandA = null;
let lastCandB = null;

document.addEventListener('DOMContentLoaded', () => {
    initCompareSelectors();
    loadCompareData();
});

window.addEventListener('themeChanged', () => {
    if (lastCandA && lastCandB) {
        renderCompareChart(lastCandA, lastCandB);
    }
});

function initCompareSelectors() {
    const selA = document.getElementById('compareCandA');
    const selB = document.getElementById('compareCandB');

    if (!selA || !selB) return;

    if (selA.tomselect) selA.tomselect.destroy();
    if (selB.tomselect) selB.tomselect.destroy();

    const tomConfig = {
        valueField: 'id',
        labelField: 'nm_urna_candidato',
        searchField: ['nm_urna_candidato', 'nm_candidato', 'sg_partido', 'nr_candidato', 'nm_municipio'],
        placeholder: 'Busque por candidato, partido, número ou município...',
        loadThrottle: 250,
        shouldLoad: function() { return true; },
        load: function(query, callback) {
            fetch('api/candidates.php?q=' + encodeURIComponent(query))
                .then(response => response.json())
                .then(json => {
                    callback(json.candidatos || []);
                })
                .catch(() => callback());
        },
        onChange: function() {
            loadCompareData();
        },
        render: {
            option: function(item, escape) {
                const initial = item.nm_urna_candidato ? escape(item.nm_urna_candidato.charAt(0).toUpperCase()) : '?';
                const votesFormatted = item.qt_votos_nom_validos ? formatNumber(item.qt_votos_nom_validos) + ' votos' : '';
                return `
                    <div class="ts-cand-option" style="display: flex; align-items: center; gap: 10px; padding: 6px 4px;">
                        <div class="ts-cand-avatar" style="width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, var(--accent-primary, #2563eb), #1e40af); color: #ffffff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.95rem; flex-shrink: 0; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
                            ${initial}
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <div style="display: flex; align-items: center; gap: 8px; justify-content: space-between;">
                                <span style="font-weight: 700; font-size: 0.92rem; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    ${escape(item.nm_urna_candidato)}
                                </span>
                                <span class="ts-party-badge" style="background: rgba(37, 99, 235, 0.15); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); font-size: 0.72rem; font-weight: 700; padding: 1px 6px; border-radius: 4px; flex-shrink: 0;">
                                    ${escape(item.sg_partido || '')}
                                </span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">
                                <span><i class="fas fa-map-marker-alt" style="font-size: 0.7rem;"></i> ${escape(item.nm_municipio || '')} ${item.ds_cargo ? '(' + escape(item.ds_cargo) + ')' : ''}</span>
                                ${votesFormatted ? `<span style="font-weight: 700; color: var(--emerald, #10b981);"><i class="fas fa-vote-yea" style="font-size: 0.7rem;"></i> ${votesFormatted}</span>` : ''}
                            </div>
                        </div>
                    </div>
                `;
            },
            item: function(item, escape) {
                return `<div><strong>${escape(item.nm_urna_candidato)}</strong> <span style="opacity: 0.85;">(${escape(item.sg_partido || '')} - ${escape(item.nm_municipio || '')})</span></div>`;
            }
        }
    };

    tomSelectCandA = new TomSelect('#compareCandA', tomConfig);
    tomSelectCandB = new TomSelect('#compareCandB', tomConfig);
}

// Carrega os dados da comparação via API
async function loadCompareData() {
    const candAId = tomSelectCandA ? tomSelectCandA.getValue() : (document.getElementById('compareCandA')?.value || '');
    const candBId = tomSelectCandB ? tomSelectCandB.getValue() : (document.getElementById('compareCandB')?.value || '');

    try {
        let url = 'api/compare.php';
        if (candAId && candBId) {
            url += `?cand_a=${encodeURIComponent(candAId)}&cand_b=${encodeURIComponent(candBId)}`;
        }

        const res = await fetch(url);
        const data = await res.json();

        if (!data.success) {
            showToast(data.message || 'Falha ao comparar candidatos.', 'error');
            return;
        }

        lastCandA = data.cand_a;
        lastCandB = data.cand_b;

        // Popula TomSelect caso esteja vazio no carregamento inicial
        if (tomSelectCandA && data.cand_a && !tomSelectCandA.getValue()) {
            tomSelectCandA.addOption(data.cand_a);
            tomSelectCandA.setValue(data.cand_a.id, true);
        }
        if (tomSelectCandB && data.cand_b && !tomSelectCandB.getValue()) {
            tomSelectCandB.addOption(data.cand_b);
            tomSelectCandB.setValue(data.cand_b.id, true);
        }

        const metrics = data.metrics || {
            cand_a: { rejection: { score: 20, level: 'Baixa', color: '#10b981' }, coalition: { count: 1, score: 50 } },
            cand_b: { rejection: { score: 20, level: 'Baixa', color: '#10b981' }, coalition: { count: 1, score: 50 } }
        };

        renderCandidateCards(data.cand_a, data.cand_b, data.advantage, metrics);
        renderAdvantageBanner(data.advantage, data.cand_a, data.cand_b);
        renderCompareChart(data.cand_a, data.cand_b);
        renderTacticalSuggestions(data.tactical_suggestions);

    } catch (err) {
        console.error("Erro ao carregar comparação:", err);
    }
}

// Renderiza Cards dos Candidatos com Métricas Diretas
function renderCandidateCards(candA, candB, advantage, metrics) {
    const cardAEl = document.getElementById('candACard');
    const cardBEl = document.getElementById('candBCard');

    const isAWinner = advantage.winner === 'A';
    const isBWinner = advantage.winner === 'B';

    const mA = metrics.cand_a || {};
    const mB = metrics.cand_b || {};

    if (cardAEl) {
        cardAEl.className = `candidate-card ${isAWinner ? 'winner-card' : ''}`;
        cardAEl.innerHTML = `
            ${isAWinner ? '<div class="winner-ribbon">LÍDER / ELEITO</div>' : ''}
            <div class="cand-header">
                <div class="cand-avatar">${candA.nm_urna_candidato.charAt(0).toUpperCase()}</div>
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
                <span class="cand-stat-label">Share Válidos / Confronto</span>
                <span class="cand-stat-num">${candA.pc_votos_validos}% <small style="font-size: 0.75rem; color: var(--text-muted);">(${advantage.share_a}% H2H)</small></span>
            </div>
            <div class="cand-stat-box">
                <span class="cand-stat-label">Vulnerabilidade / Rejeição</span>
                <span style="font-weight: 800; color: ${mA.rejection?.color || 'var(--emerald)'};">${mA.rejection?.score || 20}% (${mA.rejection?.level || 'Baixa'})</span>
            </div>
            <div class="cand-stat-box">
                <span class="cand-stat-label">Sinergia de Coligação</span>
                <span style="font-weight: 800; color: var(--accent-primary);">${mA.coalition?.score || 50}/100 (${mA.coalition?.count || 1} partido${(mA.coalition?.count || 1) > 1 ? 's' : ''})</span>
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.6rem; padding-top: 0.4rem; border-top: 1px dashed var(--border-color);">
                <strong>Partido / Coligação:</strong> <span style="color: var(--accent-primary); font-weight: 700;">${candA.sg_partido}</span> — ${candA.ds_composicao_coligacao || 'Chapa Pura'}
            </div>
        `;
    }

    if (cardBEl) {
        cardBEl.className = `candidate-card ${isBWinner ? 'winner-card' : ''}`;
        cardBEl.innerHTML = `
            ${isBWinner ? '<div class="winner-ribbon">LÍDER / ELEITO</div>' : ''}
            <div class="cand-header">
                <div class="cand-avatar">${candB.nm_urna_candidato.charAt(0).toUpperCase()}</div>
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
                <span class="cand-stat-label">Share Válidos / Confronto</span>
                <span class="cand-stat-num">${candB.pc_votos_validos}% <small style="font-size: 0.75rem; color: var(--text-muted);">(${advantage.share_b}% H2H)</small></span>
            </div>
            <div class="cand-stat-box">
                <span class="cand-stat-label">Vulnerabilidade / Rejeição</span>
                <span style="font-weight: 800; color: ${mB.rejection?.color || 'var(--emerald)'};">${mB.rejection?.score || 20}% (${mB.rejection?.level || 'Baixa'})</span>
            </div>
            <div class="cand-stat-box">
                <span class="cand-stat-label">Sinergia de Coligação</span>
                <span style="font-weight: 800; color: var(--accent-primary);">${mB.coalition?.score || 50}/100 (${mB.coalition?.count || 1} partido${(mB.coalition?.count || 1) > 1 ? 's' : ''})</span>
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.6rem; padding-top: 0.4rem; border-top: 1px dashed var(--border-color);">
                <strong>Partido / Coligação:</strong> <span style="color: var(--accent-primary); font-weight: 700;">${candB.sg_partido}</span> — ${candB.ds_composicao_coligacao || 'Chapa Pura'}
            </div>
        `;
    }
}

// Renderiza Banner de Vantagem Eleitoral Apurada com Limiar de Virada
function renderAdvantageBanner(advantage, candA, candB) {
    const banner = document.getElementById('advantageBanner');
    if (!banner) return;

    if (advantage.winner === 'EMPATE') {
        banner.innerHTML = `
            <div class="advantage-icon"><i class="fas fa-balance-scale"></i></div>
            <div class="advantage-text">
                <h4>Empate Técnico Registrado</h4>
                <p>Ambos os candidatos possuem a mesma votação nominal de <strong>${formatNumber(candA.qt_votos_nom_validos)} votos</strong> no município de ${candA.nm_municipio}. Qualquer mobilização tática local definirá o vencedor.</p>
            </div>
        `;
    } else {
        banner.innerHTML = `
            <div class="advantage-icon"><i class="fas fa-trophy"></i></div>
            <div class="advantage-text" style="flex: 1;">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                    <h4>Vantagem Apurada: ${advantage.winner_name}</h4>
                    <span style="background: rgba(16, 185, 129, 0.18); color: var(--emerald); border: 1px solid var(--emerald); padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 0.85rem;">
                        <i class="fas fa-exchange-alt"></i> Virada Direta: +${formatNumber(advantage.swing_votes_required)} votos
                    </span>
                </div>
                <p style="margin-top: 4px;">
                    Liderança nominal de <strong>${formatNumber(advantage.vote_diff)} votos</strong> (${advantage.lead_pct}% superior) sobre ${advantage.runner_up_name}. Para alterar o resultado, são necessários <strong>${formatNumber(advantage.swing_votes_required)} votos convertidos diretamente</strong> ou <strong>${formatNumber(advantage.fresh_votes_required)} novos votos</strong> entre abstenções.
                </p>
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
    const gridColor = isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.08)';

    chartCompareBar = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: [candA.nm_urna_candidato, candB.nm_urna_candidato],
            datasets: [{
                label: 'Votos Nominais',
                data: [candA.qt_votos_nom_validos, candB.qt_votos_nom_validos],
                backgroundColor: ['#2563eb', '#10b981'],
                borderRadius: 8,
                barThickness: 45
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return ' Votos Nominais: ' + formatNumber(context.raw);
                        }
                    }
                }
            },
            scales: {
                x: { ticks: { color: textColor, font: { weight: 'bold' } }, grid: { display: false } },
                y: { ticks: { color: textColor }, grid: { color: gridColor } }
            }
        }
    });
}

// Renderiza Sugestões Táticas para Campanhas com Parse de Formatação
function renderTacticalSuggestions(suggestions) {
    const container = document.getElementById('tacticalSuggestionsContainer');
    if (!container) return;

    if (!suggestions || suggestions.length === 0) {
        container.innerHTML = `<div style="color: var(--text-muted);">Nenhuma recomendação gerada.</div>`;
        return;
    }

    const parseMarkdown = (txt) => {
        if (!txt) return '';
        return txt.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    };

    const getIcon = (type) => {
        switch(type) {
            case 'advantage': return 'fa-trophy';
            case 'coalition': return 'fa-users-cog';
            case 'rejection': return 'fa-shield-alt';
            case 'party_match': return 'fa-chess';
            default: return 'fa-lightbulb';
        }
    };

    container.innerHTML = suggestions.map(s => `
        <div class="insight-card efficiency" style="margin-bottom: 0.9rem; border-left: 4px solid var(--accent-primary); background: var(--bg-card); padding: 1rem 1.15rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color); border-left-width: 4px;">
            <div class="insight-title" style="font-weight: 800; font-size: 0.95rem; color: var(--text-primary); margin-bottom: 0.35rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas ${getIcon(s.type)}" style="color: var(--accent-primary);"></i> ${s.title}
            </div>
            <div class="insight-message" style="font-size: 0.88rem; color: var(--text-secondary); line-height: 1.45; margin-bottom: 0.6rem;">
                ${parseMarkdown(s.message)}
            </div>
            <div class="insight-action" style="font-size: 0.83rem; font-weight: 600; color: var(--emerald); background: rgba(16, 185, 129, 0.08); padding: 0.5rem 0.75rem; border-radius: 6px; border: 1px dashed rgba(16, 185, 129, 0.3);">
                <i class="fas fa-chess-knight"></i> ${parseMarkdown(s.strategy)}
            </div>
        </div>
    `).join('');
}
