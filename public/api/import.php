<?php
/**
 * API - Importador Inteligente com Streaming em Tempo Real para Iframe e Progresso
 * Data Warehouse Eleitoral - v2.0 (Performance & Reliability)
 */

// Desativa limites de tempo e ativa buffer em tempo real
@ini_set('memory_limit', '512M');
@set_time_limit(600);

// Força saída HTML para rendering direto dentro do <iframe>
header('Content-Type: text/html; charset=utf-8');
header('X-Accel-Buffering: no'); // Para Nginx / FastCGI

// Desativa buffers de saída para permitir streaming instantâneo com flush()
while (ob_get_level()) {
    ob_end_flush();
}
ob_implicit_flush(true);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <style>
    body {
      font-family: 'JetBrains Mono', 'Courier New', monospace;
      background-color: #0b0f19;
      color: #e2e8f0;
      margin: 0;
      padding: 12px;
      font-size: 12px;
      line-height: 1.6;
    }
    .log-entry { margin-bottom: 4px; word-break: break-all; }
    .log-time { color: #64748b; font-weight: bold; }
    .log-info { color: #38bdf8; }
    .log-success { color: #34d399; font-weight: bold; }
    .log-warn { color: #fbbf24; }
    .log-error { color: #f87171; font-weight: bold; }
  </style>
</head>
<body>
  <div id="logStream"></div>
  <script>
    function addLog(msg, type = 'info') {
      const stream = document.getElementById('logStream');
      const time = new Date().toLocaleTimeString('pt-BR');
      const div = document.createElement('div');
      div.className = 'log-entry log-' + type;
      div.innerHTML = `<span class="log-time">[${time}]</span> ${msg}`;
      stream.appendChild(div);
      window.scrollTo(0, document.body.scrollHeight);
    }
    function updateProgress(pct, msg) {
      if (window.parent && typeof window.parent.updateImportProgress === 'function') {
        window.parent.updateImportProgress(pct, msg);
      }
    }
    function completeImport(inserted, skipped, totalInDb) {
      if (window.parent && typeof window.parent.onImportComplete === 'function') {
        window.parent.onImportComplete(inserted, skipped, totalInDb);
      }
    }
  </script>
<?php

function streamLog(string $msg, string $type = 'info'): void {
    $msgEsc = addslashes($msg);
    echo "<script>addLog('{$msgEsc}', '{$type}');</script>\n";
    flush();
}

function streamProgress(float $pct, string $msg): void {
    $msgEsc = addslashes($msg);
    echo "<script>updateProgress({$pct}, '{$msgEsc}');</script>\n";
    flush();
}

/**
 * Gera ID determinístico e estável para registros eleitorais.
 * Usa transliterator para normalizar acentos (BELÉM → belem, SÃO PAULO → saopaulo)
 */
function generateElectionId(string $uf, string $municipio, int $ano, int $turno, int $cargo, int $candidato, int $rowIndex = 0): string {
    // Transliterar acentos para ASCII seguro
    if (function_exists('transliterator_transliterate')) {
        $slugMuni = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $municipio);
    } else {
        $slugMuni = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $municipio));
    }
    $slugMuni = preg_replace('/[^a-z0-9]/', '', $slugMuni);
    $slugUf = strtolower($uf);
    
    $id = "{$ano}_{$slugUf}_{$slugMuni}_{$cargo}_{$candidato}_{$turno}";
    
    // Fallback: se nr_candidato = 0, inclui rowIndex para evitar colisão
    if ($candidato === 0) {
        $id .= "_{$rowIndex}";
    }
    
    return $id;
}

/**
 * Executa batch INSERT multi-row para performance.
 * 500 linhas por batch = ~244 queries para 122k linhas (vs 244.000 antes)
 */
function executeBatchInsert(PDO $pdo, array $batch, string $driver): int {
    if (empty($batch)) return 0;
    
    $columns = ['id', 'sg_uf', 'nm_municipio', 'cd_cargo', 'ds_cargo', 'nr_candidato', 
                'nm_candidato', 'nm_urna_candidato', 'sg_partido', 'ds_composicao_coligacao', 
                'nr_turno', 'ds_sit_totalizacao', 'nm_tipo_destinacao_votos', 'dt_ult_totalizacao', 
                'pc_votos_validos', 'Ano', 'qt_votos_nom_validos', 'qt_votos_concorrentes', 
                'latitude', 'longitude'];
    
    $colCount = count($columns);
    $singlePlaceholder = '(' . implode(',', array_fill(0, $colCount, '?')) . ')';
    $allPlaceholders = implode(',', array_fill(0, count($batch), $singlePlaceholder));
    $colList = '`' . implode('`,`', $columns) . '`';
    
    if (str_contains($driver, 'sqlite')) {
        $sql = "INSERT OR REPLACE INTO resultados_votacao ({$colList}) VALUES {$allPlaceholders}";
    } else {
        $updateCols = array_slice($columns, 1); // skip 'id' in update
        $updateParts = array_map(fn($c) => "`{$c}` = VALUES(`{$c}`)", $updateCols);
        $sql = "INSERT INTO resultados_votacao ({$colList}) VALUES {$allPlaceholders} "
             . "ON DUPLICATE KEY UPDATE " . implode(', ', $updateParts);
    }
    
    $values = [];
    foreach ($batch as $record) {
        foreach ($columns as $col) {
            $values[] = $record[$col];
        }
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
    return count($batch);
}

try {
    streamLog("Iniciando processo de importação...", "info");
    streamProgress(5, "Validando requisição do arquivo...");

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido. Envie via formulário POST.');
    }

    $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if (empty($_FILES) && empty($_POST) && $contentLength > 0) {
        $sizeMb = round($contentLength / (1024 * 1024), 2);
        $postMax = ini_get('post_max_size');
        throw new Exception("O tamanho do arquivo ({$sizeMb} MB) excede o limite post_max_size ({$postMax}) configurado no servidor.");
    }

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $errCode = $_FILES['csv_file']['error'] ?? 'Nenhum arquivo enviado';
        $msgMap = [
            UPLOAD_ERR_INI_SIZE   => 'O arquivo excede upload_max_filesize do PHP (' . ini_get('upload_max_filesize') . ').',
            UPLOAD_ERR_FORM_SIZE  => 'O arquivo excede o limite MAX_FILE_SIZE.',
            UPLOAD_ERR_PARTIAL    => 'O arquivo foi enviado apenas parcialmente.',
            UPLOAD_ERR_NO_FILE    => 'Nenhum arquivo CSV foi selecionado.',
            UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária do servidor ausente.',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar arquivo em disco.'
        ];
        $msg = $msgMap[$errCode] ?? "Erro no upload do arquivo (Código: {$errCode}).";
        throw new Exception($msg);
    }

    $tmpPath = $_FILES['csv_file']['tmp_name'];
    $fileName = $_FILES['csv_file']['name'];
    $fileSize = $_FILES['csv_file']['size'];

    $fileSizeMb = round($fileSize / (1024 * 1024), 2);
    streamLog("Arquivo recebido: <strong>{$fileName}</strong> ({$fileSizeMb} MB)", "success");
    streamProgress(15, "Lendo cabeçalho do CSV...");

    $handle = @fopen($tmpPath, 'r');
    if (!$handle) {
        throw new Exception("Não foi possível abrir o arquivo temporário enviado.");
    }

    // Detectar delimitador
    $firstLine = fgets($handle);
    rewind($handle);

    $delimiter = ';';
    if (substr_count($firstLine, "\t") > substr_count($firstLine, ';') && substr_count($firstLine, "\t") > substr_count($firstLine, ',')) {
        $delimiter = "\t";
        streamLog("Delimitador detectado: TAB (Tabulação)", "info");
    } elseif (substr_count($firstLine, ',') > substr_count($firstLine, ';')) {
        $delimiter = ',';
        streamLog("Delimitador detectado: Vírgula (,)", "info");
    } else {
        streamLog("Delimitador detectado: Ponto e Vírgula (;)", "info");
    }

    $rawHeader = @fgetcsv($handle, 0, $delimiter, '"', '\\');
    if (!$rawHeader) {
        fclose($handle);
        throw new Exception("O arquivo CSV está vazio ou possui formato de cabeçalho inválido.");
    }

    // FIX: Encoding do header - converte Latin-1 e usa mb_strtolower
    $header = array_map(function($col) {
        $col = preg_replace('/\x{EF}\x{BB}\x{BF}/', '', $col);
        if (!mb_check_encoding($col, 'UTF-8')) {
            $col = mb_convert_encoding($col, 'UTF-8', 'ISO-8859-1');
        }
        return mb_strtolower(trim(str_replace(['"', "'"], '', $col)), 'UTF-8');
    }, $rawHeader);

    streamLog("Cabeçalho lido com " . count($header) . " colunas.", "info");

    $columnMap = [
        'id'                       => ['id', 'uuid', 'codigo'],
        'sg_uf'                    => ['sg_uf', 'uf', 'estado'],
        'nm_municipio'             => ['nm_municipio', 'municipio', 'cidade'],
        'cd_cargo'                 => ['cd_cargo', 'codigo_cargo'],
        'ds_cargo'                 => ['ds_cargo', 'cargo'],
        'nr_candidato'             => ['nr_candidato', 'numero_candidato', 'numero'],
        'nm_candidato'             => ['nm_candidato', 'nome_candidato', 'nome_completo'],
        'nm_urna_candidato'        => ['nm_urna_candidato', 'nome_urna', 'urna'],
        'sg_partido'               => ['sg_partido', 'partido', 'sigla_partido'],
        'ds_composicao_coligacao'  => ['ds_composicao_coligacao', 'coligacao', 'composicao_coligacao'],
        'nr_turno'                 => ['nr_turno', 'turno'],
        'ds_sit_totalizacao'       => ['ds_sit_totalizacao', 'situacao', 'sit_totalizacao'],
        'nm_tipo_destinacao_votos' => ['nm_tipo_destinacao_votos', 'tipo_destinacao_votos', 'destinacao'],
        'dt_ult_totalizacao'       => ['dt_ult_totalizacao', 'data_totalizacao'],
        'pc_votos_validos'         => ['pc_votos_validos', 'percentual_votos', 'pc_votos'],
        'Ano'                      => ['ano', 'ano_eleicao'],
        'qt_votos_nom_validos'     => ['qt_votos_nom_validos', 'votos', 'qt_votos', 'votos_validos'],
        'qt_votos_concorrentes'    => ['qt_votos_concorrentes', 'total_votos_concorrentes'],
        'latitude'                 => ['latitude', 'lat'],
        'longitude'                => ['longitude', 'long', 'lng']
    ];

    $colIndexes = [];
    foreach ($columnMap as $field => $aliases) {
        foreach ($aliases as $alias) {
            $idx = array_search(mb_strtolower($alias, 'UTF-8'), $header);
            if ($idx !== false) {
                $colIndexes[$field] = $idx;
                break;
            }
        }
    }

    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../config/cache.php';
    streamProgress(25, "Conectando ao banco de dados...");
    
    $pdo = Database::getConnection();
    $driver = Database::getDriver();
    streamLog("Conexão ativa com o banco de dados [<strong>Driver: {$driver}</strong>]", "success");

    $insertedOrUpdated = 0;
    $skipped = 0;
    $totalLines = 0;
    $batchSize = 500;
    $commitEvery = 2000;
    $batchBuffer = [];

    // Estimar total de linhas para progresso preciso
    $estimatedTotal = max(1, (int)($fileSize / 200)); // ~200 bytes por linha típica
    streamLog("Estimativa: ~" . number_format($estimatedTotal) . " registros no arquivo.", "info");

    // Closure getValue definida UMA VEZ (referência a $currentRow que muda no loop)
    $currentRow = [];
    $getValue = function(string $field, $default = '') use ($colIndexes, &$currentRow): string {
        if (isset($colIndexes[$field]) && isset($currentRow[$colIndexes[$field]])) {
            $val = trim($currentRow[$colIndexes[$field]]);
            return $val !== '' ? $val : (string)$default;
        }
        return (string)$default;
    };

    $pdo->beginTransaction();
    streamLog("Iniciando gravação batch no banco de dados...", "info");

    while (($row = @fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
        $totalLines++;
        if (count($row) < 2) {
            $skipped++;
            continue;
        }

        // Converter encoding de valores para UTF-8
        $currentRow = array_map(function($val) {
            if (!mb_check_encoding($val, 'UTF-8')) {
                return mb_convert_encoding($val, 'UTF-8', 'ISO-8859-1');
            }
            return $val;
        }, $row);

        // Extrair valores com proteção contra falsy PHP ("0" ?: default retorna default!)
        $id = $getValue('id');
        $ufVal = strtoupper($getValue('sg_uf', 'PA'));
        
        $anoRaw = $getValue('Ano', '');
        $anoVal = $anoRaw !== '' ? (int)$anoRaw : 2024;
        
        $municipioVal = $getValue('nm_municipio', 'Município');
        $dsCargoVal = $getValue('ds_cargo', 'Prefeito');
        
        $cdCargoRaw = $getValue('cd_cargo', '');
        $cdCargoVal = $cdCargoRaw !== '' ? (int)$cdCargoRaw : 11;
        
        $nrCandRaw = $getValue('nr_candidato', '');
        $nrCandVal = $nrCandRaw !== '' ? (int)$nrCandRaw : 0;
        
        $nmCandVal = $getValue('nm_candidato', 'Candidato');
        
        $nrTurnoRaw = $getValue('nr_turno', '');
        $nrTurnoVal = $nrTurnoRaw !== '' ? (int)$nrTurnoRaw : 1;
        
        // Gerar ID determinístico se ausente no CSV
        if ($id === '') {
            $id = generateElectionId($ufVal, $municipioVal, $anoVal, $nrTurnoVal, $cdCargoVal, $nrCandVal, $totalLines);
        }

        $pcRaw = str_replace(',', '.', $getValue('pc_votos_validos', '0'));
        $pcVal = (float)$pcRaw;
        if ($pcVal > 0 && $pcVal <= 1.0) {
            $pcVal = round($pcVal * 100, 2);
        } else {
            $pcVal = round($pcVal, 2);
        }

        $qtVotosRaw = $getValue('qt_votos_nom_validos', '0');
        $qtConcRaw = $getValue('qt_votos_concorrentes', '0');

        // Formato de record para batch (sem prefixo ':' pois usaremos positional params)
        $batchBuffer[] = [
            'id'                       => $id,
            'sg_uf'                    => $ufVal,
            'nm_municipio'             => $municipioVal,
            'cd_cargo'                 => $cdCargoVal,
            'ds_cargo'                 => $dsCargoVal,
            'nr_candidato'             => $nrCandVal,
            'nm_candidato'             => $nmCandVal,
            'nm_urna_candidato'        => $getValue('nm_urna_candidato') !== '' ? $getValue('nm_urna_candidato') : $nmCandVal,
            'sg_partido'               => strtoupper($getValue('sg_partido', 'PARTIDO')),
            'ds_composicao_coligacao'  => $getValue('ds_composicao_coligacao', '') ?: null,
            'nr_turno'                 => $nrTurnoVal,
            'ds_sit_totalizacao'       => strtoupper($getValue('ds_sit_totalizacao', 'ELEITO')),
            'nm_tipo_destinacao_votos' => strtoupper($getValue('nm_tipo_destinacao_votos', 'VÁLIDO')),
            'dt_ult_totalizacao'       => $getValue('dt_ult_totalizacao', date('d/m/Y')),
            'pc_votos_validos'         => $pcVal,
            'Ano'                      => $anoVal,
            'qt_votos_nom_validos'     => (int)$qtVotosRaw,
            'qt_votos_concorrentes'    => (int)$qtConcRaw,
            'latitude'                 => (float)str_replace(',', '.', $getValue('latitude', '0')),
            'longitude'                => (float)str_replace(',', '.', $getValue('longitude', '0')),
        ];

        // Flush batch quando atingir batchSize
        if (count($batchBuffer) >= $batchSize) {
            try {
                executeBatchInsert($pdo, $batchBuffer, $driver);
                $insertedOrUpdated += count($batchBuffer);
            } catch (PDOException $ex) {
                // Fallback: inserir um a um para identificar a linha problemática
                foreach ($batchBuffer as $singleRecord) {
                    try {
                        executeBatchInsert($pdo, [$singleRecord], $driver);
                        $insertedOrUpdated++;
                    } catch (PDOException $innerEx) {
                        $skipped++;
                        streamLog("Aviso: Registro ID={$singleRecord['id']} ignorado - " . $innerEx->getMessage(), "warn");
                    }
                }
            }
            $batchBuffer = [];

            // Commit periódico para não acumular locks
            if ($insertedOrUpdated % $commitEvery === 0) {
                $pdo->commit();
                $pdo->beginTransaction();
            }

            // Progresso (log apenas a cada 1000 para não inflar o DOM do iframe)
            if ($insertedOrUpdated % 1000 === 0 || $insertedOrUpdated <= $batchSize) {
                $pct = min(95, round(25 + ($totalLines / max(1, $estimatedTotal)) * 70));
                streamProgress($pct, "Processados " . number_format($insertedOrUpdated) . " registros...");
                streamLog("Batch gravado: <strong>" . number_format($insertedOrUpdated) . " registros</strong> persistidos...", "info");
            }
        }
    }

    // Flush registros restantes no buffer
    if (!empty($batchBuffer)) {
        try {
            executeBatchInsert($pdo, $batchBuffer, $driver);
            $insertedOrUpdated += count($batchBuffer);
        } catch (PDOException $ex) {
            foreach ($batchBuffer as $singleRecord) {
                try {
                    executeBatchInsert($pdo, [$singleRecord], $driver);
                    $insertedOrUpdated++;
                } catch (PDOException $innerEx) {
                    $skipped++;
                }
            }
        }
        $batchBuffer = [];
    }

    $pdo->commit();
    fclose($handle);

    // Consulta de verificação final direta no banco de dados para confirmar contagem real
    $stmtCheck = $pdo->query("SELECT COUNT(*) FROM resultados_votacao");
    $totalInDb = (int)$stmtCheck->fetchColumn();

    streamLog("Finalizando gravação e efetuando commit no banco de dados...", "info");
    Cache::clear();
    streamLog("SUCESSO: " . number_format($insertedOrUpdated) . " registros processados do CSV!", "success");
    if ($skipped > 0) {
        streamLog("Avisos: {$skipped} linhas ignoradas por erros de formato ou duplicidade.", "warn");
    }
    streamLog("Confirmação real no banco de dados: <strong>" . number_format($totalInDb) . " registros totais</strong> na tabela resultados_votacao.", "success");
    streamProgress(100, "Importação concluída com sucesso!");

    echo "<script>completeImport({$insertedOrUpdated}, {$skipped}, {$totalInDb});</script>\n";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $errMessage = addslashes($e->getMessage());
    streamLog("ERRO CRÍTICO: {$errMessage}", "error");
    streamProgress(0, "Erro na importação: {$errMessage}");
    echo "<script>completeImport(0, 0, 0);</script>\n";
}

?>
</body>
</html>
