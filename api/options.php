<?php
/**
 * API - Opções para Filtros & Selects Autocomplete (Cached)
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=3600');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/cache.php';

try {
    $cacheKey = 'options_distinct_v3';
    $cached = Cache::get($cacheKey, 7200);
    if ($cached !== null) {
        header('X-Cache: HIT');
        echo json_encode($cached, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo = Database::getConnection();
    $driverInUse = Database::getDriver();
    $dbType = str_contains(strtolower($driverInUse), 'sqlite') ? 'SQLite' : 'MySQL';
    $totalRecords = (int)$pdo->query("SELECT COUNT(*) FROM resultados_votacao")->fetchColumn();

    $anos = $pdo->query("SELECT DISTINCT `Ano` FROM resultados_votacao WHERE `Ano` IS NOT NULL ORDER BY `Ano` DESC")->fetchAll(PDO::FETCH_COLUMN);
    $municipios = $pdo->query("SELECT DISTINCT `nm_municipio` FROM resultados_votacao WHERE `nm_municipio` != '' ORDER BY `nm_municipio` ASC")->fetchAll(PDO::FETCH_COLUMN);
    $cargos = $pdo->query("SELECT DISTINCT `ds_cargo` FROM resultados_votacao WHERE `ds_cargo` != '' ORDER BY `ds_cargo` ASC")->fetchAll(PDO::FETCH_COLUMN);
    $partidos = $pdo->query("SELECT DISTINCT `sg_partido` FROM resultados_votacao WHERE `sg_partido` != '' ORDER BY `sg_partido` ASC")->fetchAll(PDO::FETCH_COLUMN);
    $situacoes = $pdo->query("SELECT DISTINCT `ds_sit_totalizacao` FROM resultados_votacao WHERE `ds_sit_totalizacao` != '' ORDER BY `ds_sit_totalizacao` ASC")->fetchAll(PDO::FETCH_COLUMN);

    $response = [
        'success' => true,
        'db_driver' => $driverInUse,
        'db_type' => $dbType,
        'total_db_records' => $totalRecords,
        'anos' => $anos,
        'municipios' => $municipios,
        'cargos' => $cargos,
        'partidos' => $partidos,
        'situacoes' => $situacoes,
        'candidatos' => [] // Candidates loaded on demand via /api/candidates.php
    ];

    Cache::set($cacheKey, $response);
    header('X-Cache: MISS');
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
