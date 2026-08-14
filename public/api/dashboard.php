<?php
/**
 * API - Métricas do Dashboard & Banco de Insights Estratégicos de Campanha (Optimized & Cached)
 * Data Warehouse Eleitoral
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/cache.php';

try {
    // Filtros Recebidos
    $ano       = isset($_GET['ano']) ? trim($_GET['ano']) : '';
    $municipio = isset($_GET['municipio']) ? trim($_GET['municipio']) : '';
    $cargo     = isset($_GET['cargo']) ? trim($_GET['cargo']) : '';
    $partido   = isset($_GET['partido']) ? trim($_GET['partido']) : '';
    $situacao  = isset($_GET['situacao']) ? trim($_GET['situacao']) : '';
    $search    = isset($_GET['q']) ? trim($_GET['q']) : '';

    $isDefaultRequest = ($ano === '' && $municipio === '' && $cargo === '' && $partido === '' && $situacao === '' && $search === '');
    $cacheKey = 'dashboard_default_v3';

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

    // 1. KPIs Gerais + Métrica de Coligação (Consolidados em 1 única query - strict ONLY_FULL_GROUP_BY)
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

    // 3. Votação por Partido + Herfindahl-Hirschman Index (HHI)
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
    $hhi = 0.0;
    foreach ($partyVotes as &$p) {
        $p['votes'] = (int)$p['votes'];
        $p['candidates'] = (int)$p['candidates'];
        $p['percentage'] = round(($p['votes'] / $totalVotesAllParties) * 100, 2);
        $pct = (float)$p['percentage'];
        $hhi += ($pct * $pct);
    }
    unset($p);

    $hhi = round($hhi, 2);
    $enp = $hhi > 0 ? round(10000 / $hhi, 2) : 0.0; // Effective Number of Parties (Laakso-Taagepera)
    $hhiClassification = 'Alta Fragmentação (Pulverizado)';
    if ($hhi >= 2500) {
        $hhiClassification = 'Alta Concentração (Partido Dominante)';
    } elseif ($hhi >= 1500) {
        $hhiClassification = 'Concentração Moderada (Oligopólio Partidário)';
    }

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

    // 6. Projeção de Quociente Eleitoral (QE) & Barreira de 10%
    $sqlEleitos = "SELECT COUNT(*) AS total_eleitos FROM resultados_votacao {$whereSql} AND `ds_sit_totalizacao` LIKE '%ELEITO%'";
    $stmtEleitos = $executeQuery($pdo, $sqlEleitos, $params);
    $totalEleitos = (int)$stmtEleitos->fetchColumn();

    $totalCandidatos = (int)$kpiData['total_candidatos'];
    if ($totalEleitos > 0) {
        $vagas = $totalEleitos;
    } elseif ($cargo === 'Deputado Federal') {
        $vagas = 17;
    } elseif ($cargo === 'Deputado Estadual') {
        $vagas = 41;
    } else {
        $vagas = max(1, min(35, (int)round($totalCandidatos / 8)));
    }

    $totalVotosValidos = (int)$kpiData['total_votos'];
    $qe = max(1, (int)floor($totalVotosValidos / max(1, $vagas)));
    $threshold10pct = round($qe * 0.10);

    // Projeções por partido para QE
    $partyProjections = [];
    foreach (array_slice($partyVotes, 0, 6) as $pv) {
        $qp = (int)floor($pv['votes'] / $qe);
        $sobra = $pv['votes'] % $qe;
        $partyProjections[] = [
            'party' => $pv['party'],
            'votes' => $pv['votes'],
            'qp_direct_seats' => $qp,
            'sobra_votos' => $sobra,
            'pct_to_next_seat' => round(($sobra / $qe) * 100, 1)
        ];
    }

    // 7. Volatilidade & Índice de Competitividade Municipal (MVI & CI)
    $topRaceComp = null;
    $sqlRaceComp = "SELECT 
                        `nm_municipio`, 
                        `ds_cargo`,
                        COALESCE(SUM(`qt_votos_nom_validos`), 0) AS total_votos_race,
                        COUNT(*) AS total_cands
                    FROM resultados_votacao {$whereSql}
                    GROUP BY `nm_municipio`, `ds_cargo`
                    HAVING COUNT(*) >= 2 AND SUM(`qt_votos_nom_validos`) > 0
                    ORDER BY total_votos_race DESC
                    LIMIT 1";
    $stmtRaceComp = $executeQuery($pdo, $sqlRaceComp, $params);
    $topRace = $stmtRaceComp->fetch(PDO::FETCH_ASSOC);

    if ($topRace) {
        $sqlTop2 = "SELECT `nm_urna_candidato`, `sg_partido`, `qt_votos_nom_validos`
                    FROM resultados_votacao 
                    {$whereSql} AND `nm_municipio` = :raceMuni AND `ds_cargo` = :raceCargo
                    ORDER BY `qt_votos_nom_validos` DESC
                    LIMIT 2";
        $raceParams = array_merge($params, [
            ':raceMuni' => $topRace['nm_municipio'],
            ':raceCargo' => $topRace['ds_cargo']
        ]);
        $stmtTop2 = $executeQuery($pdo, $sqlTop2, $raceParams);
        $top2Cands = $stmtTop2->fetchAll(PDO::FETCH_ASSOC);

        if (count($top2Cands) >= 2 && (int)$topRace['total_votos_race'] > 0) {
            $c1Votes = (int)$top2Cands[0]['qt_votos_nom_validos'];
            $c2Votes = (int)$top2Cands[1]['qt_votos_nom_validos'];
            $gap = $c1Votes - $c2Votes;
            $totalRaceVotes = (int)$topRace['total_votos_race'];
            
            $mov = round(($gap / max(1, $totalRaceVotes)) * 100, 2);
            $ci = round(100 - $mov, 2);
            $mvi = $c1Votes > 0 ? round(($c2Votes / $c1Votes) * 100, 1) : 0.0;

            $topRaceComp = [
                'municipio' => $topRace['nm_municipio'],
                'cargo' => $topRace['ds_cargo'],
                'cand1' => $top2Cands[0]['nm_urna_candidato'] . ' (' . $top2Cands[0]['sg_partido'] . ')',
                'cand2' => $top2Cands[1]['nm_urna_candidato'] . ' (' . $top2Cands[1]['sg_partido'] . ')',
                'cand1_votes' => $c1Votes,
                'cand2_votes' => $c2Votes,
                'mov_pct' => $mov,
                'ci' => $ci,
                'mvi' => $mvi,
                'vote_gap' => $gap
            ];
        }
    }

    // =========================================================================
    // 8. BANCO DE INSIGHTS ESTRATÉGICOS E INTELIGÊNCIA ELEITORAL (COOK & POLITICO BENCHMARKS)
    // =========================================================================
    $insights = [];

    // Insight 1: Herfindahl-Hirschman Party Concentration Index (HHI)
    $insights[] = [
        'type' => 'hhi',
        'badge' => 'Concentração Partidária (HHI)',
        'title' => "Índice HHI: " . number_format($hhi, 0, ',', '.') . " pts (ENP: {$enp})",
        'message' => "A concentração de forças partidárias registra **" . number_format($hhi, 0, ',', '.') . " pontos** no Índice HHI, o que corresponde a **{$enp} partidos efetivos** (ENP). Classificação: **{$hhiClassification}**.",
        'action' => "Recomendação Estratégica: " . ($hhi >= 2500 ? "Dominância concentrada — essencial estruturar alianças majoritárias para romper a barreira do partido líder." : "Sistema altamente pulverizado — priorizar captação de sobras partidárias e fortalecimento de puxadores de legenda.")
    ];

    // Insight 2: Projeção de Quociente Eleitoral (QE)
    $insights[] = [
        'type' => 'qe',
        'badge' => 'Quociente Eleitoral (QE)',
        'title' => "QE Projetado: " . number_format($qe, 0, ',', '.') . " votos por cadeira",
        'message' => "Com **" . number_format($totalVotosValidos, 0, ',', '.') . " votos válidos** e **" . number_format($vagas, 0, ',', '.') . " cadeira(s) analisada(s)**, o Quociente Eleitoral (QE) é de **" . number_format($qe, 0, ',', '.') . " votos**. Cláusula de desempenho individual (10% do QE): **" . number_format($threshold10pct, 0, ',', '.') . " votos**.",
        'action' => "Engenharia de Chapas: Candidatos que não atingem " . number_format($threshold10pct, 0, ',', '.') . " votos são desqualificados de puxar sobras eleitorais (Art. 109, Código Eleitoral)."
    ];

    // Insight 3: Volatilidade & Índice de Competitividade Municipal (MVI & CI)
    if ($topRaceComp) {
        $insights[] = [
            'type' => 'volatility',
            'badge' => 'Volatilidade & Competitividade',
            'title' => "Batalha Eleitoral em {$topRaceComp['municipio']} ({$topRaceComp['cargo']})",
            'message' => "O Índice de Competitividade (CI) atinge **{$topRaceComp['ci']}%** com margem de vitória de apenas **{$topRaceComp['mov_pct']}%** (" . number_format($topRaceComp['vote_gap'], 0, ',', '.') . " votos) entre **{$topRaceComp['cand1']}** (" . number_format($topRaceComp['cand1_votes'], 0, ',', '.') . " votos) e **{$topRaceComp['cand2']}** (" . number_format($topRaceComp['cand2_votes'], 0, ',', '.') . " votos). Índice de Volatilidade (MVI): **{$topRaceComp['mvi']}%**.",
            'action' => "Direcionamento Tático: Zonas de margem estreita (< 5%) são classificadas como 'Toss-up' (Batalha Aberta), exigindo concentração imediata de orçamento e presença de rua."
        ];
    }

    // Insight 4: Hegemonia & Dominância Partidária
    if (count($partyVotes) > 0) {
        $topParty = $partyVotes[0];
        $secondParty = $partyVotes[1] ?? null;
        if ($secondParty && $secondParty['votes'] > 0) {
            $diff = $topParty['votes'] - $secondParty['votes'];
            $leadPct = round(($diff / max(1, $secondParty['votes'])) * 100, 1);
            $insights[] = [
                'type' => 'dominance',
                'badge' => 'Dominância Partidária',
                'title' => "Liderança de Votação: {$topParty['party']}",
                'message' => "O partido **{$topParty['party']}** detém **{$topParty['percentage']}%** dos votos válidos (" . number_format($topParty['votes'], 0, ',', '.') . " votos), abrindo **{$leadPct}%** de vantagem (" . number_format($diff, 0, ',', '.') . " votos) sobre o segundo colocado, **{$secondParty['party']}**.",
                'action' => "Análise de Coalizão: Avaliar blocos de oposição para mitigar a expansão territorial do {$topParty['party']}."
            ];
        }
    }

    // Insight 5: Taxa de Conversão Eleitoral / Eficiência de Chapa
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
            'message' => "O **{$efic['sg_partido']}** apresenta a maior eficiência de conversão da base com **{$rate}%** de candidatos eleitos ({$efic['eleitos']} de {$efic['total']} candidaturas).",
            'action' => "Benchmarking: Otimização de recursos de campanha e alocação direcionada a pré-candidatos de alta tração."
        ];
    }

    // Insight 6: Potencial de Suplentes de Alta Votação
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
            'title' => "Principal Suplente: {$topSuplente['nm_urna_candidato']}",
            'message' => "O candidato **{$topSuplente['nm_urna_candidato']}** ({$topSuplente['sg_partido']}) somou **" . number_format($topSuplente['qt_votos_nom_validos'], 0, ',', '.') . " votos** em {$topSuplente['nm_municipio']}, destacando-se como o suplente mais votado.",
            'action' => "Gestão Política: Articulação para ocupação de cargos no executivo ou convocação parlamentar."
        ];
    }

    // Insight 7: Engenharia de Coligações
    $totalRegs = (int)$kpiData['total_candidatos'];
    $emColig = (int)($kpiData['em_coligacao'] ?? 0);
    if ($totalRegs > 0) {
        $pctColig = round(($emColig / $totalRegs) * 100, 1);
        $insights[] = [
            'type' => 'coalition',
            'badge' => 'Engenharia de Coligação',
            'title' => "Alianças Multipartidárias: {$pctColig}%",
            'message' => "Aproximadamente **{$pctColig}%** das candidaturas mapeadas integram coligações multipartidárias no recorte atual.",
            'action' => "Recomendação de Mídia: Coordenação de tempo de propaganda eleitoral e sinergia de palanques."
        ];
    }

    $driverInUse = Database::getDriver();
    $dbType = str_contains(strtolower($driverInUse), 'sqlite') ? 'SQLite' : 'MySQL';

    $response = [
        'success' => true,
        'db_driver' => $driverInUse,
        'db_type' => $dbType,
        'kpis' => [
            'total_votos' => (int)$kpiData['total_votos'],
            'total_candidatos' => (int)$kpiData['total_candidatos'],
            'total_municipios' => (int)$kpiData['total_municipios'],
            'total_partidos' => (int)$kpiData['total_partidos'],
            'hhi' => $hhi,
            'enp' => $enp,
            'hhi_classification' => $hhiClassification,
            'qe' => $qe,
            'vagas_analisadas' => $vagas
        ],
        'party_concentration' => [
            'hhi' => $hhi,
            'enp' => $enp,
            'classification' => $hhiClassification
        ],
        'quociente_eleitoral' => [
            'qe' => $qe,
            'vagas' => $vagas,
            'total_votos' => $totalVotosValidos,
            'threshold_10pct' => $threshold10pct,
            'party_projections' => $partyProjections
        ],
        'competitive_index' => $topRaceComp,
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
