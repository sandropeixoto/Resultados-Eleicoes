<?php
/**
 * API - Métricas do Dashboard & Banco de Insights Estratégicos de Campanha (Optimized & Cached)
 * Data Warehouse Eleitoral
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/cache.php';

try {
    // Filtros Recebidos
    $ano       = isset($_GET['ano']) ? trim($_GET['ano']) : '';
    $municipio = isset($_GET['municipio']) ? trim($_GET['municipio']) : '';
    $cargo     = isset($_GET['cargo']) ? trim($_GET['cargo']) : '';
    $partido   = isset($_GET['partido']) ? trim($_GET['partido']) : '';
    $situacao  = isset($_GET['situacao']) ? trim($_GET['situacao']) : '';
    $search    = isset($_GET['q']) ? trim($_GET['q']) : '';

    $isDefaultRequest = ($ano === '' && $municipio === '' && $cargo === '' && $partido === '' && $situacao === '' && $search === '');
    $cacheKey = 'dashboard_default_v2';

    if ($isDefaultRequest) {
        $cachedData = Cache::get($cacheKey, 3600);
        if ($cachedData !== null) {
            header('X-Cache: HIT');
            echo json_encode($cachedData, JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $pdo = Database::getConnection();

    $where = ["1=1"];
    $params = [];

    if ($ano !== '') {
        $where[] = "`Ano` = :ano";
        $params[':ano'] = (int)$ano;
    }
    if ($municipio !== '') {
        $where[] = "`nm_municipio` = :municipio";
        $params[':municipio'] = $municipio;
    }
    if ($cargo !== '') {
        $where[] = "`ds_cargo` = :cargo";
        $params[':cargo'] = $cargo;
    }
    if ($partido !== '') {
        $where[] = "`sg_partido` = :partido";
        $params[':partido'] = $partido;
    }
    if ($situacao !== '') {
        $where[] = "`ds_sit_totalizacao` = :situacao";
        $params[':situacao'] = $situacao;
    }
    if ($search !== '') {
        $where[] = "(`nm_candidato` LIKE :q OR `nm_urna_candidato` LIKE :q OR `sg_partido` LIKE :q OR `nm_municipio` LIKE :q)";
        $params[':q'] = "%{$search}%";
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    // Executor seguro de consultas parametrizadas
    $executeQuery = function(PDO $pdo, string $sql, array $baseParams) {
        $finalParams = [];
        foreach ($baseParams as $key => $val) {
            $count = substr_count($sql, $key);
            if ($count > 1) {
                for ($i = 1; $i <= $count; $i++) {
                    $newKey = $key . '_' . $i;
                    $sql = preg_replace('/' . preg_quote($key, '/') . '\b/', $newKey, $sql, 1);
                    $finalParams[$newKey] = $val;
                }
            } elseif ($count === 1) {
                $finalParams[$key] = $val;
            }
        }
        $stmt = $pdo->prepare($sql);
        foreach ($finalParams as $k => $v) {
            $type = is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($k, $v, $type);
        }
        $stmt->execute();
        return $stmt;
    };

    // 1. KPIs Gerais + Métrica de Coligação (Consolidados em 1 única query)
    $sqlKpi = "SELECT 
                COALESCE(SUM(`qt_votos_nom_validos`), 0) AS total_votos,
                COUNT(*) AS total_candidatos,
                COUNT(DISTINCT `nm_municipio`) AS total_municipios,
                COUNT(DISTINCT `sg_partido`) AS total_partidos,
                SUM(CASE WHEN `ds_composicao_coligacao` LIKE '%/%' THEN 1 ELSE 0 END) AS em_coligacao
               FROM resultados_votacao {$whereSql}";
    $stmtKpi = $executeQuery($pdo, $sqlKpi, $params);
    $kpiData = $stmtKpi->fetch(PDO::FETCH_ASSOC);

    // 2. Ranking de Candidatos (Top 50)
    $sqlRanking = "SELECT 
                    `id`, `nm_candidato`, `nm_urna_candidato`, `nr_candidato`,
                    `sg_partido`, `nm_municipio`, `ds_cargo`, `ds_sit_totalizacao`,
                    `qt_votos_nom_validos`, `pc_votos_validos`, `Ano`
                   FROM resultados_votacao {$whereSql}
                   ORDER BY `qt_votos_nom_validos` DESC, `nm_urna_candidato` ASC
                   LIMIT 50";
    $stmtRanking = $executeQuery($pdo, $sqlRanking, $params);
    $ranking = $stmtRanking->fetchAll(PDO::FETCH_ASSOC);

    // 3. Votação por Partido
    $whereParty = $where;
    $whereParty[] = "`sg_partido` IS NOT NULL AND `sg_partido` != ''";
    $whereSqlParty = 'WHERE ' . implode(' AND ', $whereParty);

    $sqlParty = "SELECT 
                    `sg_partido` AS party,
                    SUM(`qt_votos_nom_validos`) AS votes,
                    COUNT(*) AS candidates
                 FROM resultados_votacao {$whereSqlParty}
                 GROUP BY `sg_partido`
                 ORDER BY votes DESC";
    $stmtParty = $executeQuery($pdo, $sqlParty, $params);
    $partyVotes = $stmtParty->fetchAll(PDO::FETCH_ASSOC);

    $totalVotesAllParties = array_sum(array_column($partyVotes, 'votes')) ?: 1;
    foreach ($partyVotes as &$p) {
        $p['votes'] = (int)$p['votes'];
        $p['candidates'] = (int)$p['candidates'];
        $p['percentage'] = round(($p['votes'] / $totalVotesAllParties) * 100, 2);
    }
    unset($p);

    // 4. Tendência Histórica por Ano
    $sqlTrend = "SELECT 
                    `Ano`,
                    SUM(`qt_votos_nom_validos`) AS total_votos,
                    COUNT(*) AS total_candidatos
                 FROM resultados_votacao {$whereSql}
                 GROUP BY `Ano`
                 ORDER BY `Ano` ASC";
    $stmtTrend = $executeQuery($pdo, $sqlTrend, $params);
    $trend = $stmtTrend->fetchAll(PDO::FETCH_ASSOC);

    // 5. Situação & Totalização
    $whereSit = $where;
    $whereSit[] = "`ds_sit_totalizacao` IS NOT NULL AND `ds_sit_totalizacao` != ''";
    $whereSqlSit = 'WHERE ' . implode(' AND ', $whereSit);

    $sqlSit = "SELECT 
                `ds_sit_totalizacao`,
                COUNT(*) AS total,
                SUM(`qt_votos_nom_validos`) AS total_votos
               FROM resultados_votacao {$whereSqlSit}
               GROUP BY `ds_sit_totalizacao`
               ORDER BY total DESC";
    $stmtSit = $executeQuery($pdo, $sqlSit, $params);
    $situationBreakdown = $stmtSit->fetchAll(PDO::FETCH_ASSOC);

    // =========================================================================
    // 6. BANCO DE INSIGHTS ESTRATÉGICOS E INTELIGÊNCIA ELEITORAL
    // =========================================================================
    $insights = [];

    // Insight 1: Hegemonia & Dominância Partidária
    if (count($partyVotes) > 0) {
        $topParty = $partyVotes[0];
        $secondParty = $partyVotes[1] ?? null;
        if ($secondParty && $secondParty['votes'] > 0) {
            $diff = $topParty['votes'] - $secondParty['votes'];
            $leadPct = round(($diff / $secondParty['votes']) * 100, 1);
            $insights[] = [
                'type' => 'dominance',
                'badge' => 'Dominância Partidária',
                'title' => "Liderança de Votação: {$topParty['party']}",
                'message' => "O partido **{$topParty['party']}** concentra **{$topParty['percentage']}%** da votação apurada (" . number_format($topParty['votes'], 0, ',', '.') . " votos), mantendo uma vantagem de **{$leadPct}%** (" . number_format($diff, 0, ',', '.') . " votos) à frente de **{$secondParty['party']}**.",
                'action' => "Recomendação Estratégica: Opor alianças com partidos de centro-esquerda/centro-direita para desafiar zonas de influência direta do {$topParty['party']}."
            ];
        }
    }

    // Insight 2: Taxa de Conversão Eleitoral
    $sqlEfic = "SELECT `sg_partido`, 
                       SUM(CASE WHEN `ds_sit_totalizacao` LIKE '%ELEITO%' THEN 1 ELSE 0 END) AS eleitos,
                       COUNT(*) AS total
                FROM resultados_votacao {$whereSql}
                GROUP BY `sg_partido`
                HAVING COUNT(*) >= 1
                ORDER BY (SUM(CASE WHEN `ds_sit_totalizacao` LIKE '%ELEITO%' THEN 1 ELSE 0 END) * 1.0 / COUNT(*)) DESC
                LIMIT 1";
    $stmtEfic = $executeQuery($pdo, $sqlEfic, $params);
    $efic = $stmtEfic->fetch(PDO::FETCH_ASSOC);

    if ($efic && $efic['total'] > 0) {
        $rate = round(($efic['eleitos'] / $efic['total']) * 100, 1);
        $insights[] = [
            'type' => 'efficiency',
            'badge' => 'Eficiência de Chapa',
            'title' => "Conversão de Candidaturas: {$efic['sg_partido']}",
            'message' => "O **{$efic['sg_partido']}** registrou a maior taxa de conversão eleitoral da base com **{$rate}%** dos seus candidatos eleitos ({$efic['eleitos']} de {$efic['total']}).",
            'action' => "Estratégia: Modelo eficiente na distribuição de fundo partidário e aproveitamento de lideranças com apelo eleitoral."
        ];
    }

    // Insight 3: Disputa Acirrada & Zonas de Virada
    if (count($ranking) >= 2) {
        $cand1 = $ranking[0];
        $cand2 = $ranking[1];
        $voteGap = $cand1['qt_votos_nom_validos'] - $cand2['qt_votos_nom_validos'];
        if ($cand1['nm_municipio'] === $cand2['nm_municipio'] && $cand1['ds_cargo'] === $cand2['ds_cargo']) {
            $leadPct = round(($voteGap / max(1, $cand2['qt_votos_nom_validos'])) * 100, 1);
            $insights[] = [
                'type' => 'competition',
                'badge' => 'Zona de Virada',
                'title' => "Disputa Acirrada em {$cand1['nm_municipio']}",
                'message' => "A liderança entre 1º colocado (**{$cand1['nm_urna_candidato']}**) e 2º (**{$cand2['nm_urna_candidato']}**) no cargo de {$cand1['ds_cargo']} é de apenas **" . number_format($voteGap, 0, ',', '.') . " votos** ({$leadPct}% de margem).",
                'action' => "Oportunidade: Região de alta volatilidade eleitoral. Recomendado intensificar campanhas de rua e corpo a corpo."
            ];
        }
    }

    // Insight 4: Potencial de Suplentes de Alta Votação
    $sqlSuplente = "SELECT `nm_urna_candidato`, `sg_partido`, `nm_municipio`, `qt_votos_nom_validos`
                    FROM resultados_votacao {$whereSql}
                    AND `ds_sit_totalizacao` LIKE '%SUPLENTE%'
                    ORDER BY `qt_votos_nom_validos` DESC
                    LIMIT 1";
    $stmtSuplente = $executeQuery($pdo, $sqlSuplente, $params);
    $topSuplente = $stmtSuplente->fetch(PDO::FETCH_ASSOC);

    if ($topSuplente) {
        $insights[] = [
            'type' => 'suplencia',
            'badge' => 'Reserva de Votos',
            'title' => "Liderança de Suplência: {$topSuplente['nm_urna_candidato']}",
            'message' => "O candidato **{$topSuplente['nm_urna_candidato']}** ({$topSuplente['sg_partido']}) obteve **" . number_format($topSuplente['qt_votos_nom_validos'], 0, ',', '.') . " votos** em {$topSuplente['nm_municipio']}, figurando como o principal suplente de seu grupo.",
            'action' => "Estratégia: Monitoramento ativo para eventual composição de secretarias ou assunção de mandato na vacância."
        ];
    }

    // Insight 5: Análise Territorial & Escala Regional
    if ((int)$kpiData['total_municipios'] >= 1) {
        $insights[] = [
            'type' => 'territory',
            'badge' => 'Estratégia Territorial',
            'title' => "Alcance em " . number_format($kpiData['total_municipios']) . " Municípios",
            'message' => "O Data Warehouse contabiliza **" . number_format($kpiData['total_votos'], 0, ',', '.') . " votos válidos** distribuídos entre **" . number_format($kpiData['total_candidatos']) . " candidatos** mapeados.",
            'action' => "Recomendação de Mídia: Alocação do orçamento publicitário e impulsionamento proporcional ao colégio eleitoral de cada cidade."
        ];
    }

    // Insight 6: Força de Coligações vs Chapa Pura (usando kpiData já calculado)
    $totalRegs = (int)$kpiData['total_candidatos'];
    $emColig = (int)($kpiData['em_coligacao'] ?? 0);
    if ($totalRegs > 0) {
        $pctColig = round(($emColig / $totalRegs) * 100, 1);
        $insights[] = [
            'type' => 'coalition',
            'badge' => 'Engenharia de Chapas',
            'title' => "Composição de Alianças: {$pctColig}% em Coligação",
            'message' => "Cerca de **{$pctColig}%** das candidaturas analisadas integram coligações multipartidárias, unificando tempo de rádio e TV.",
            'action' => "Recomendação: Avaliar o impacto da divisão de quociente partidário entre siglas parceiras."
        ];
    }

    $response = [
        'success' => true,
        'kpis' => [
            'total_votos' => (int)$kpiData['total_votos'],
            'total_candidatos' => (int)$kpiData['total_candidatos'],
            'total_municipios' => (int)$kpiData['total_municipios'],
            'total_partidos' => (int)$kpiData['total_partidos']
        ],
        'ranking' => $ranking,
        'party_votes' => $partyVotes,
        'historic_trend' => $trend,
        'situation_breakdown' => $situationBreakdown,
        'insights' => $insights
    ];

    if ($isDefaultRequest) {
        Cache::set($cacheKey, $response);
        header('X-Cache: MISS');
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
