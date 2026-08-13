<?php
/**
 * API - Comparador Direto de Candidatos (Head-to-Head)
 * Análise comparativa, cálculo da vantagem eleitoral e sugestões estratégicas
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../config/database.php';

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

    // Cálculo de Vantagem Eleitoral
    $votosA = (int)$candA['qt_votos_nom_validos'];
    $votosB = (int)$candB['qt_votos_nom_validos'];

    $diffVotos = abs($votosA - $votosB);
    
    if ($votosA > $votosB) {
        $winner = 'A';
        $winnerName = $candA['nm_urna_candidato'];
        $runnerUpName = $candB['nm_urna_candidato'];
        $leadPct = $votosB > 0 ? round((($votosA - $votosB) / $votosB) * 100, 2) : 100;
    } elseif ($votosB > $votosA) {
        $winner = 'B';
        $winnerName = $candB['nm_urna_candidato'];
        $runnerUpName = $candA['nm_urna_candidato'];
        $leadPct = $votosA > 0 ? round((($votosB - $votosA) / $votosA) * 100, 2) : 100;
    } else {
        $winner = 'EMPATE';
        $winnerName = 'Empate Técnico';
        $runnerUpName = '';
        $leadPct = 0;
    }

    $diffPercentValidos = round(abs((float)$candA['pc_votos_validos'] - (float)$candB['pc_votos_validos']), 2);

    // Sugestões Estratégicas de Comparação
    $tacticalSuggestions = [];

    if ($winner !== 'EMPATE') {
        $tacticalSuggestions[] = [
            'type' => 'advantage',
            'title' => "Vantagem Apurada: {$winnerName}",
            'message' => "**{$winnerName}** possui uma liderança de **" . number_format($diffVotos, 0, ',', '.') . " votos** sobre **{$runnerUpName}**, representando uma margem superior de **{$leadPct}%** de vantagem nominal.",
            'strategy' => "Recomendação: O candidato em 2º lugar precisa capturar " . number_format(ceil($diffVotos / 2), 0, ',', '.') . " votos da base adversária ou do eleitorado indeciso para reverter o cenário."
        ];
    } else {
        $tacticalSuggestions[] = [
            'type' => 'tie',
            'title' => "Empate Técnico Registrado",
            'message' => "Ambos os candidatos possuem exatamente o mesmo número de votos nominais (**" . number_format($votosA, 0, ',', '.') . " votos**).",
            'strategy' => "Recomendação: Qualquer ação direcionada de marketing e mobilização local definirá o resultado."
        ];
    }

    $coligA = count(explode('/', $candA['ds_composicao_coligacao'] ?? ''));
    $coligB = count(explode('/', $candB['ds_composicao_coligacao'] ?? ''));
    
    if ($coligA != $coligB) {
        $maiorColig = $coligA > $coligB ? $candA['nm_urna_candidato'] : $candB['nm_urna_candidato'];
        $partidosCount = max($coligA, $coligB);
        $tacticalSuggestions[] = [
            'type' => 'coalition',
            'title' => "Capacidade de Aliança Política",
            'message' => "**{$maiorColig}** lidera a ampla coligação reunindo **{$partidosCount} partidos**, garantindo maior tempo de rádio/TV e capilaridade nas ruas.",
            'strategy' => "Estratégia: A chapa adversária deve contrabalançar apostando em comunicação direta em redes sociais e engajamento orgânico."
        ];
    }

    $tacticalSuggestions[] = [
        'type' => 'party_strength',
        'title' => "Confronto Partidário ({$candA['sg_partido']} vs {$candB['sg_partido']})",
        'message' => "Disputa direta entre a tradição eleitoral do partido **{$candA['sg_partido']}** e do **{$candB['sg_partido']}** no município de **{$candA['nm_municipio']}**.",
        'strategy' => "Ações recomendadas: Avaliar a rejeição partidária nas zonas eleitorais e ajustar a mensagem de campanha focando em propostas locais."
    ];

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
            'percent_validos_diff' => $diffPercentValidos
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
