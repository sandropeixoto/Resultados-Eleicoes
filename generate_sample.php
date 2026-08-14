<?php
/**
 * Data Warehouse Eleitoral - Gerador & Semeador de Dados baseado em exemplo.csv
 * v2.0 - Usa generateElectionId() centralizado, Cache::clear() e elimina election_records
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/cache.php';

/**
 * Gera ID determinístico e estável para registros eleitorais.
 * Replica exatamente a mesma lógica de public/api/import.php
 */
function generateElectionId(string $uf, string $municipio, int $ano, int $turno, int $cargo, int $candidato, int $rowIndex = 0): string {
    $slugMuni = '';
    if (function_exists('transliterator_transliterate')) {
        $trans = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $municipio);
        $slugMuni = is_string($trans) ? $trans : strtolower($municipio);
    } else {
        $trans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $municipio);
        $slugMuni = is_string($trans) ? strtolower($trans) : strtolower($municipio);
    }
    $slugMuni = preg_replace('/[^a-z0-9]/', '', $slugMuni);
    if ($slugMuni === '') {
        $slugMuni = 'geral';
    }
    $slugUf = strtolower(trim($uf));
    if ($slugUf === '') {
        $slugUf = 'pa';
    }
    
    $id = "{$ano}_{$slugUf}_{$slugMuni}_{$cargo}_{$candidato}_{$turno}";
    
    if ($candidato === 0) {
        $id .= "_{$rowIndex}";
    }
    
    return $id;
}

try {
    echo "Conectando ao banco de dados...\n";
    $pdo = Database::getConnection();
    $driver = Database::getDriver();

    echo "Banco ativo [Driver: {$driver}]. Limpando tabela...\n";

    // Limpa tabela existente para garantir alinhamento 100% dos IDs
    try {
        $pdo->exec("TRUNCATE TABLE resultados_votacao");
    } catch (Exception $e) {
        $pdo->exec("DELETE FROM resultados_votacao");
    }

} catch (Exception $e) {
    die("Erro na conexão: " . $e->getMessage() . "\n");
}

$csvPath = __DIR__ . '/exemplo.csv';
if (!file_exists($csvPath)) {
    die("Erro: Arquivo exemplo.csv não encontrado no diretório raiz.\n");
}

$handle = fopen($csvPath, 'r');
if (!$handle) {
    die("Erro: Falha ao abrir arquivo exemplo.csv.\n");
}

$firstLine = fgets($handle);
if ($firstLine === false) {
    fclose($handle);
    die("Erro: Arquivo exemplo.csv está vazio.\n");
}
rewind($handle);

$delimiter = ";";
if (substr_count($firstLine, "\t") > substr_count($firstLine, ';')) {
    $delimiter = "\t";
}

$rawHeader = fgetcsv($handle, 0, $delimiter, '"', '\\');
if (!is_array($rawHeader)) {
    fclose($handle);
    die("Erro: Cabeçalho inválido no arquivo CSV.\n");
}

$header = array_map(function($col) {
    $col = preg_replace('/\x{EF}\x{BB}\x{BF}/', '', $col);
    if (!mb_check_encoding($col, 'UTF-8')) {
        $col = mb_convert_encoding($col, 'UTF-8', 'ISO-8859-1');
    }
    return mb_strtolower(trim(str_replace(['"', "'"], '', $col)), 'UTF-8');
}, $rawHeader);

if (str_contains($driver, 'sqlite')) {
    $sql = "INSERT OR REPLACE INTO resultados_votacao 
        (id, sg_uf, nm_municipio, cd_cargo, ds_cargo, nr_candidato, nm_candidato, nm_urna_candidato, sg_partido, ds_composicao_coligacao, nr_turno, ds_sit_totalizacao, nm_tipo_destinacao_votos, dt_ult_totalizacao, pc_votos_validos, Ano, qt_votos_nom_validos, qt_votos_concorrentes, latitude, longitude)
        VALUES (:id, :sg_uf, :nm_municipio, :cd_cargo, :ds_cargo, :nr_candidato, :nm_candidato, :nm_urna_candidato, :sg_partido, :ds_composicao_coligacao, :nr_turno, :ds_sit_totalizacao, :nm_tipo_destinacao_votos, :dt_ult_totalizacao, :pc_votos_validos, :Ano, :qt_votos_nom_validos, :qt_votos_concorrentes, :latitude, :longitude)";
} else {
    $sql = "INSERT INTO resultados_votacao 
        (id, sg_uf, nm_municipio, cd_cargo, ds_cargo, nr_candidato, nm_candidato, nm_urna_candidato, sg_partido, ds_composicao_coligacao, nr_turno, ds_sit_totalizacao, nm_tipo_destinacao_votos, dt_ult_totalizacao, pc_votos_validos, Ano, qt_votos_nom_validos, qt_votos_concorrentes, latitude, longitude)
        VALUES (:id, :sg_uf, :nm_municipio, :cd_cargo, :ds_cargo, :nr_candidato, :nm_candidato, :nm_urna_candidato, :sg_partido, :ds_composicao_coligacao, :nr_turno, :ds_sit_totalizacao, :nm_tipo_destinacao_votos, :dt_ult_totalizacao, :pc_votos_validos, :Ano, :qt_votos_nom_validos, :qt_votos_concorrentes, :latitude, :longitude)
        ON DUPLICATE KEY UPDATE 
            sg_uf = VALUES(sg_uf), nm_municipio = VALUES(nm_municipio), cd_cargo = VALUES(cd_cargo), ds_cargo = VALUES(ds_cargo), nr_candidato = VALUES(nr_candidato), nm_candidato = VALUES(nm_candidato), nm_urna_candidato = VALUES(nm_urna_candidato), sg_partido = VALUES(sg_partido), ds_composicao_coligacao = VALUES(ds_composicao_coligacao), nr_turno = VALUES(nr_turno), ds_sit_totalizacao = VALUES(ds_sit_totalizacao), nm_tipo_destinacao_votos = VALUES(nm_tipo_destinacao_votos), dt_ult_totalizacao = VALUES(dt_ult_totalizacao), pc_votos_validos = VALUES(pc_votos_validos), Ano = VALUES(Ano), qt_votos_nom_validos = VALUES(qt_votos_nom_validos), qt_votos_concorrentes = VALUES(qt_votos_concorrentes), latitude = VALUES(latitude), longitude = VALUES(longitude)";
}

$stmt = $pdo->prepare($sql);

$pdo->beginTransaction();
$inserted = 0;
$rowIndex = 0;

while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
    $rowIndex++;
    if (count($row) < 2) continue;

    $row = array_map(function($val) {
        if (!mb_check_encoding($val, 'UTF-8')) {
            return mb_convert_encoding($val, 'UTF-8', 'ISO-8859-1');
        }
        return $val;
    }, $row);

    $getVal = function($colName, $default = '') use ($header, $row) {
        $idx = array_search($colName, $header);
        if ($idx !== false && isset($row[$idx])) {
            $val = trim($row[$idx]);
            return $val !== '' ? $val : (string)$default;
        }
        return (string)$default;
    };

    $ufVal = strtoupper($getVal('sg_uf', 'PA'));
    
    $anoRaw = $getVal('ano', '');
    $anoVal = $anoRaw !== '' ? (int)$anoRaw : 2024;
    
    $muniVal = $getVal('nm_municipio', 'ABAETETUBA');
    $dsCargoVal = $getVal('ds_cargo', 'Prefeito');
    
    $cdCargoRaw = $getVal('cd_cargo', '');
    $cdCargoVal = $cdCargoRaw !== '' ? (int)$cdCargoRaw : 11;
    
    $nrCandRaw = $getVal('nr_candidato', '');
    $nrCandVal = $nrCandRaw !== '' ? (int)$nrCandRaw : 0;
    
    $nmCandVal = $getVal('nm_candidato', 'Candidato');
    
    $nrTurnoRaw = $getVal('nr_turno', '');
    $nrTurnoVal = $nrTurnoRaw !== '' ? (int)$nrTurnoRaw : 1;

    // Usa a mesma função generateElectionId() que o import.php
    $id = generateElectionId($ufVal, $muniVal, $anoVal, $nrTurnoVal, $cdCargoVal, $nrCandVal, $rowIndex);

    $pcRaw = str_replace(',', '.', $getVal('pc_votos_validos', '0'));
    $pcVal = (float)$pcRaw;
    if ($pcVal > 0 && $pcVal <= 1.0) {
        $pcVal = round($pcVal * 100, 2);
    } else {
        $pcVal = round($pcVal, 2);
    }

    $qtVotosRaw = $getVal('qt_votos_nom_validos', '0');
    $qtConcRaw = $getVal('qt_votos_concorrentes', '0');
    $dsColigacaoVal = $getVal('ds_composicao_coligacao', '');

    $record = [
        ':id'                       => $id,
        ':sg_uf'                    => $ufVal,
        ':nm_municipio'             => $muniVal,
        ':cd_cargo'                 => $cdCargoVal,
        ':ds_cargo'                 => $dsCargoVal,
        ':nr_candidato'             => $nrCandVal,
        ':nm_candidato'             => $nmCandVal,
        ':nm_urna_candidato'        => $getVal('nm_urna_candidato') !== '' ? $getVal('nm_urna_candidato') : $nmCandVal,
        ':sg_partido'               => strtoupper($getVal('sg_partido', 'PARTIDO')),
        ':ds_composicao_coligacao'  => $dsColigacaoVal !== '' ? $dsColigacaoVal : null,
        ':nr_turno'                 => $nrTurnoVal,
        ':ds_sit_totalizacao'       => strtoupper($getVal('ds_sit_totalizacao', 'ELEITO')),
        ':nm_tipo_destinacao_votos' => strtoupper($getVal('nm_tipo_destinacao_votos', 'VÁLIDO')),
        ':dt_ult_totalizacao'       => $getVal('dt_ult_totalizacao', date('d/m/Y')),
        ':pc_votos_validos'         => $pcVal,
        ':Ano'                      => $anoVal,
        ':qt_votos_nom_validos'     => (int)$qtVotosRaw,
        ':qt_votos_concorrentes'    => (int)$qtConcRaw,
        ':latitude'                 => (float)str_replace(',', '.', $getVal('latitude', '0')),
        ':longitude'                => (float)str_replace(',', '.', $getVal('longitude', '0')),
    ];

    $stmt->execute($record);
    $inserted++;
}

$pdo->commit();
fclose($handle);

// Limpa o cache após a gravação dos dados no banco
Cache::clear();

echo "Semeação concluída! {$inserted} registros importados de exemplo.csv com IDs determinísticos.\n";
