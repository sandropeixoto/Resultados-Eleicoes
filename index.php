<?php
/**
 * Data Warehouse Eleitoral - Entrada Raiz
 * Redireciona dinamicamente para o subdiretório /public/
 */

$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$targetUrl = ($scriptDir === '' ? '' : $scriptDir) . '/public/';

header("Location: {$targetUrl}", true, 302);
exit;
