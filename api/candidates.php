<?php
/**
 * API - Autocomplete Paginado de Candidatos para TomSelect / Comparador
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';

try {
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 30)));

    $pdo = Database::getConnection();

    if (strlen($q) < 2) {
        $stmt = $pdo->prepare("SELECT `id`, `nm_urna_candidato`, `nm_candidato`, `nr_candidato`, `sg_partido`, `nm_municipio`, `Ano`, `ds_cargo`, `qt_votos_nom_validos`, `pc_votos_validos`, `ds_sit_totalizacao`, `ds_composicao_coligacao` 
                               FROM resultados_votacao 
                               ORDER BY `qt_votos_nom_validos` DESC 
                               LIMIT {$limit}");
        $stmt->execute();
        $candidatos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'candidatos' => $candidatos], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare("SELECT `id`, `nm_urna_candidato`, `nm_candidato`, `nr_candidato`, `sg_partido`, `nm_municipio`, `Ano`, `ds_cargo`, `qt_votos_nom_validos`, `pc_votos_validos`, `ds_sit_totalizacao`, `ds_composicao_coligacao` 
                           FROM resultados_votacao 
                           WHERE `nm_urna_candidato` LIKE :q1 OR `nm_candidato` LIKE :q2 OR `sg_partido` LIKE :q3 OR `nr_candidato` LIKE :q4 OR `nm_municipio` LIKE :q5
                           ORDER BY `qt_votos_nom_validos` DESC 
                           LIMIT {$limit}");
    $paramVal = "%{$q}%";
    $stmt->execute([
        ':q1' => $paramVal,
        ':q2' => $paramVal,
        ':q3' => $paramVal,
        ':q4' => $paramVal,
        ':q5' => $paramVal
    ]);
    $candidatos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'candidatos' => $candidatos], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
