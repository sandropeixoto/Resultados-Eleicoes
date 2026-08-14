<?php
/**
 * API - Comparador Direto de Candidatos (Head-to-Head)
 * Análise comparativa, cálculo da vantagem eleitoral, índice de rejeição, sinergia de coligação e sugestões estratégicas.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = Database::getConnection();

    $candAId = isset($_GET['cand_a']) ? trim($_GET['cand_a']) : null;
    $candBId = isset($_GET['cand_b']) ? trim($_GET['cand_b']) : null;

    if (!$candAId || !$candBId) {
        $stmtDefault = $pdo->query("SELECT `id` FROM resultados_votacao ORDER BY `qt_votos_nom_validos` DESC LIMIT 2");
        $defaultIds = $stmtDefault->fetchAll(PDO::FETCH_COLUMN);
        $candAId = $candAId ?: ($defaultIds[0] ?? null);
        $candBId = $candBId ?: ($defaultIds[1] ?? null);
    }

    if (!$candAId || !$candBId) {
        echo json_encode([
            'success' => false,
            'message' => 'Selecione dois candidatos para realizar a comparação.'
        ]);
        exit;
    }

    $sql = "SELECT * FROM resultados_votacao WHERE `id` = :id LIMIT 1";
    
    $stmtA = $pdo->prepare($sql);
    $stmtA->execute([':id' => $candAId]);
    $candA = $stmtA->fetch(PDO::FETCH_ASSOC);

    $stmtB = $pdo->prepare($sql);
    $stmtB->execute([':id' => $candBId]);
    $candB = $stmtB->fetch(PDO::FETCH_ASSOC);

    if (!$candA || !$candB) {
        echo json_encode([
            'success' => false,
            'message' => 'Um ou ambos os candidatos não foram encontrados no banco de dados.'
        ]);
        exit;
    }

    // Calculation of Electoral Advantage & Required Swing Votes
    $votosA = (int)($candA['qt_votos_nom_validos'] ?? 0);
    $votosB = (int)($candB['qt_votos_nom_validos'] ?? 0);
    $diffVotos = abs($votosA - $votosB);
    $totalPairVotes = $votosA + $votosB;

    $shareA = $totalPairVotes > 0 ? round(($votosA / $totalPairVotes) * 100, 2) : 0;
    $shareB = $totalPairVotes > 0 ? round(($votosB / $totalPairVotes) * 100, 2) : 0;

    if ($votosA > $votosB) {
        $winner = 'A';
        $winnerName = $candA['nm_urna_candidato'];
        $runnerUpName = $candB['nm_urna_candidato'];
        $leadPct = $votosB > 0 ? round((($votosA - $votosB) / $votosB) * 100, 2) : 100;
        $swingVotesRequired = (int)floor($diffVotos / 2) + 1;
        $freshVotesRequired = $diffVotos + 1;
    } elseif ($votosB > $votosA) {
        $winner = 'B';
        $winnerName = $candB['nm_urna_candidato'];
        $runnerUpName = $candA['nm_urna_candidato'];
        $leadPct = $votosA > 0 ? round((($votosB - $votosA) / $votosA) * 100, 2) : 100;
        $swingVotesRequired = (int)floor($diffVotos / 2) + 1;
        $freshVotesRequired = $diffVotos + 1;
    } else {
        $winner = 'EMPATE';
        $winnerName = 'Empate Técnico';
        $runnerUpName = '';
        $leadPct = 0;
        $swingVotesRequired = 1;
        $freshVotesRequired = 1;
    }

    $diffPercentValidos = round(abs((float)($candA['pc_votos_validos'] ?? 0) - (float)($candB['pc_votos_validos'] ?? 0)), 2);

    // Calculate Party Rejection / Vulnerability Index and Coalition Metrics
    $metricsA = [
        'rejection' => calculateRejectionMetrics($candA),
        'coalition' => calculateCoalitionMetrics($candA)
    ];

    $metricsB = [
        'rejection' => calculateRejectionMetrics($candB),
        'coalition' => calculateCoalitionMetrics($candB)
    ];

    // Generate Actionable Tactical Campaign Advice
    $tacticalSuggestions = generateTacticalSuggestions($candA, $candB, [
        'winner' => $winner,
        'winner_name' => $winnerName,
        'runner_up_name' => $runnerUpName,
        'vote_diff' => $diffVotos,
        'lead_pct' => $leadPct,
        'swing_votes_required' => $swingVotesRequired,
        'fresh_votes_required' => $freshVotesRequired,
        'share_a' => $shareA,
        'share_b' => $shareB
    ], $metricsA, $metricsB);

    echo json_encode([
        'success' => true,
        'cand_a' => $candA,
        'cand_b' => $candB,
        'advantage' => [
            'winner' => $winner,
            'winner_name' => $winnerName,
            'runner_up_name' => $runnerUpName,
            'vote_diff' => $diffVotos,
            'lead_pct' => $leadPct,
            'share_a' => $shareA,
            'share_b' => $shareB,
            'percent_validos_diff' => $diffPercentValidos,
            'swing_votes_required' => $swingVotesRequired,
            'fresh_votes_required' => $freshVotesRequired
        ],
        'metrics' => [
            'cand_a' => $metricsA,
            'cand_b' => $metricsB
        ],
        'tactical_suggestions' => $tacticalSuggestions
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Helper: Party Rejection & Electoral Vulnerability Assessment
 */
function calculateRejectionMetrics($cand) {
    $score = 20; // baseline
    $sit = strtoupper($cand['ds_sit_totalizacao'] ?? '');
    $pcVal = (float)($cand['pc_votos_validos'] ?? 0);
    $colig = $cand['ds_composicao_coligacao'] ?? '';

    // Situation penalties
    if (strpos($sit, 'NÃO ELEITO') !== false || strpos($sit, 'NAO ELEITO') !== false) {
        $score += 35;
    } elseif (strpos($sit, 'INDEFERIDO') !== false || strpos($sit, 'CANCELADO') !== false) {
        $score += 55;
    } elseif (strpos($sit, 'SUPLENTE') !== false) {
        $score += 20;
    } elseif (strpos($sit, 'ELEITO') !== false || strpos($sit, 'SEGUNDO TURNO') !== false) {
        $score -= 10;
    }

    // Performance penalties/bonuses
    if ($pcVal < 5.0) {
        $score += 30;
    } elseif ($pcVal < 15.0) {
        $score += 15;
    } elseif ($pcVal > 40.0) {
        $score -= 20;
    }

    // Chapa Pura penalty (isolated party structure)
    if (empty($colig) || strpos($colig, '/') === false) {
        $score += 10;
    }

    $score = max(5, min(95, $score));

    if ($score < 25) {
        $level = 'Baixa';
        $color = '#10b981';
    } elseif ($score < 50) {
        $level = 'Moderada';
        $color = '#2563eb';
    } elseif ($score < 75) {
        $level = 'Elevada';
        $color = '#f59e0b';
    } else {
        $level = 'Crítica';
        $color = '#f43f5e';
    }

    return [
        'score' => $score,
        'level' => $level,
        'color' => $color
    ];
}

/**
 * Helper: Coalition Weighting & Synergy Assessment
 */
function calculateCoalitionMetrics($cand) {
    $coligRaw = trim($cand['ds_composicao_coligacao'] ?? '');
    if (empty($coligRaw)) {
        $parties = [$cand['sg_partido'] ?? 'OUTRO'];
    } else {
        $parties = array_map('trim', explode('/', $coligRaw));
    }

    $count = count($parties);
    $score = 45; // baseline

    if ($count > 1) {
        $score += min(45, ($count - 1) * 10);
    } else {
        $score -= 10;
    }

    $pcVal = (float)($cand['pc_votos_validos'] ?? 0);
    if ($pcVal > 30.0) {
        $score += 10;
    }

    $score = max(10, min(100, $score));

    return [
        'count' => $count,
        'score' => $score,
        'is_coalition' => $count > 1,
        'parties' => $parties
    ];
}

/**
 * Helper: Actionable Tactical Campaign Advice
 */
function generateTacticalSuggestions($candA, $candB, $adv, $metricsA, $metricsB) {
    $suggestions = [];

    // 1. Swing & Advantage Insight
    if ($adv['winner'] !== 'EMPATE') {
        $winnerName = $adv['winner_name'];
        $runnerUpName = $adv['runner_up_name'];
        $suggestions[] = [
            'type' => 'advantage',
            'title' => "Vantagem Apurada & Reversão Necessária",
            'message' => "**{$winnerName}** lidera por **" . number_format($adv['vote_diff'], 0, ',', '.') . " votos** ({$adv['lead_pct']}% de margem nominal).",
            'strategy' => "Metas de Virada: **{$runnerUpName}** necessita de **" . number_format($adv['swing_votes_required'], 0, ',', '.') . " votos diretos da base adversária** (virada) ou capturar **" . number_format($adv['fresh_votes_required'], 0, ',', '.') . " novos votos** entre abstenções e indecisos para assumir a liderança."
        ];
    } else {
        $suggestions[] = [
            'type' => 'tie',
            'title' => "Empate Técnico Nominal",
            'message' => "Ambos os candidatos acumulam exatamente **" . number_format($candA['qt_votos_nom_validos'], 0, ',', '.') . " votos válidos**.",
            'strategy' => "Estratégia de Desempate: Mobilizar micro-regiões e eleitores indecisos com foco em zonas eleitorais de maior abstenção."
        ];
    }

    // 2. Coalition Strength & Leverage
    $cA = $metricsA['coalition'];
    $cB = $metricsB['coalition'];
    if ($cA['count'] !== $cB['count']) {
        $leader = $cA['count'] > $cB['count'] ? $candA : $candB;
        $trailing = $cA['count'] > $cB['count'] ? $candB : $candA;
        $leaderMetrics = $cA['count'] > $cB['count'] ? $cA : $cB;
        $lCount = max($cA['count'], $cB['count']);
        $tCount = min($cA['count'], $cB['count']);
        
        $suggestions[] = [
            'type' => 'coalition',
            'title' => "Sinergia de Coligação e Tempo de TV/Rádio",
            'message' => "**{$leader['nm_urna_candidato']}** conta com aliança ampla de **{$lCount} partidos** (Sinergia: {$leaderMetrics['score']}/100), enquanto **{$trailing['nm_urna_candidato']}** possui **{$tCount} partido(s)**.",
            'strategy' => "Ação Recomendada: A chapa adversária deve compensar o menor tempo de rádio/TV intensificando atuação digital nas redes sociais e mobilização porta a porta."
        ];
    }

    // 3. Rejection & Vulnerability Analysis
    $rejA = $metricsA['rejection'];
    $rejB = $metricsB['rejection'];
    if ($rejA['score'] > 45 || $rejB['score'] > 45) {
        $vulnCand = $rejA['score'] >= $rejB['score'] ? $candA : $candB;
        $vulnRej = $rejA['score'] >= $rejB['score'] ? $rejA : $rejB;
        $suggestions[] = [
            'type' => 'rejection',
            'title' => "Indicador de Vulnerabilidade / Rejeição Partidária",
            'message' => "**{$vulnCand['nm_urna_candidato']}** apresenta índice de vulnerabilidade estimado de **{$vulnRej['score']}% ({$vulnRej['level']})** com base em seu histórico eleitoral e coligação.",
            'strategy' => "Estratégia de Blindagem: Reduzir a rejeição reforçando propostas concretas para o município de **{$vulnCand['nm_municipio']}** e desacoplando a imagem de desgastes partidários locais."
        ];
    }

    // 4. Party Direct Match Insight
    $suggestions[] = [
        'type' => 'party_match',
        'title' => "Confronto Partidário: {$candA['sg_partido']} vs {$candB['sg_partido']}",
        'message' => "Disputa territorial direta no município de **{$candA['nm_municipio']}** ({$candA['ds_cargo']}). Share relativo no confronto: **{$adv['share_a']}% ({$candA['sg_partido']})** vs **{$adv['share_b']}% ({$candB['sg_partido']})**.",
        'strategy' => "Ação Tática: Concentrar recursos de campanha nos bairros periféricos de **{$candA['nm_municipio']}** com maior densidade de votação nominal."
    ];

    return $suggestions;
}
