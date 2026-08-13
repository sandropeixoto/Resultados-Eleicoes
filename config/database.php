<?php
/**
 * Configuração de Banco de Dados e Conexão PDO (Servidor Remoto srv24.prodns.com.br)
 * Data Warehouse Eleitoral
 */

class Config {
    private array $data;

    public function __construct(array $data = []) {
        $this->data = $data;
    }

    public function get(string $key, mixed $default = null): mixed {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void {
        $this->data[$key] = $value;
    }

    public function getAll(): array {
        return $this->data;
    }
}

$config = new Config([
    'driver'   => 'mysql',
    'address'  => 'srv24.prodns.com.br',
    'port'     => '3306',
    'username' => 'sspeixot_resultado_eleicoes',
    'password' => 'Senh@2026',
    'database' => 'sspeixot_resultados_eleicoes',
]);

class Database {
    private static ?PDO $instance = null;
    private static string $driverInUse = 'mysql';

    public static function getConnection(?Config $cfg = null): PDO {
        if (self::$instance !== null) {
            return self::$instance;
        }

        global $config;
        $activeConfig = $cfg ?? $config;

        $driver = strtolower(getenv('DB_DRIVER') ?: $activeConfig->get('driver', 'mysql'));
        $host   = getenv('DB_HOST')   ?: $activeConfig->get('address', 'srv24.prodns.com.br');
        $port   = (int)(getenv('DB_PORT') ?: $activeConfig->get('port', 3306));
        $user   = getenv('DB_USER')   ?: $activeConfig->get('username', 'sspeixot_resultado_eleicoes');
        $pass   = getenv('DB_PASS') !== false ? getenv('DB_PASS') : $activeConfig->get('password', 'Senh@2026');
        $dbname = getenv('DB_NAME')   ?: $activeConfig->get('database', 'sspeixot_resultados_eleicoes');

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5,
        ];

        if ($driver === 'mysql') {
            $hostsToTry = [$host, '127.0.0.1', 'localhost'];
            
            foreach ($hostsToTry as $h) {
                // Verificação ultra-rápida de soquete de 150ms para não travar o PHP se a porta 3306 remota estiver bloqueada por firewall
                $fp = @fsockopen($h, $port, $errno, $errstr, 1.0);
                if ($fp) {
                    fclose($fp);
                    try {
                        $dsn = "mysql:host={$h};port={$port};dbname={$dbname};charset=utf8mb4";
                        $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET SESSION wait_timeout=600, interactive_timeout=600";
                        self::$instance = new PDO($dsn, $user, $pass, $options);
                        self::$driverInUse = 'mysql (' . $h . ')';
                        self::ensureTablesExist(self::$instance);
                        return self::$instance;
                    } catch (PDOException $e) {
                        // Tenta o próximo host
                    }
                }
            }

            // Fallback para SQLite local caso o MySQL esteja inacessível localmente
            $sqliteFile = __DIR__ . '/../db/eleicoes_fallback.sqlite';
            $dir = dirname($sqliteFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            self::$instance = new PDO("sqlite:" . $sqliteFile, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            self::$instance->exec("PRAGMA synchronous = NORMAL;");
            self::$instance->exec("PRAGMA journal_mode = WAL;");
            self::$instance->exec("PRAGMA temp_store = MEMORY;");
            
            self::$driverInUse = 'sqlite';
            self::ensureTablesExist(self::$instance);
            return self::$instance;
        } else {
            $sqliteFile = __DIR__ . '/../db/eleicoes_fallback.sqlite';
            self::$instance = new PDO("sqlite:" . $sqliteFile, null, null, $options);
            self::$driverInUse = 'sqlite';
            self::ensureTablesExist(self::$instance);
            return self::$instance;
        }
    }

    public static function getDriver(): string {
        return self::$driverInUse;
    }

    public static function ensureTablesExist(PDO $pdo): void {
        $isSqlite = str_contains(self::$driverInUse, 'sqlite');

        if ($isSqlite) {
            $sql = "
            CREATE TABLE IF NOT EXISTS resultados_votacao (
                id TEXT PRIMARY KEY,
                sg_uf TEXT DEFAULT 'PA',
                nm_municipio TEXT DEFAULT '',
                cd_cargo INTEGER DEFAULT 11,
                ds_cargo TEXT DEFAULT 'Prefeito',
                nr_candidato INTEGER DEFAULT 0,
                nm_candidato TEXT DEFAULT '',
                nm_urna_candidato TEXT DEFAULT '',
                sg_partido TEXT DEFAULT '',
                ds_composicao_coligacao TEXT,
                nr_turno INTEGER DEFAULT 1,
                ds_sit_totalizacao TEXT DEFAULT 'ELEITO',
                nm_tipo_destinacao_votos TEXT DEFAULT 'VÁLIDO',
                dt_ult_totalizacao TEXT DEFAULT '',
                pc_votos_validos REAL DEFAULT 0,
                Ano INTEGER DEFAULT 2024,
                qt_votos_nom_validos INTEGER DEFAULT 0,
                qt_votos_concorrentes INTEGER DEFAULT 0,
                latitude REAL DEFAULT 0,
                longitude REAL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE INDEX IF NOT EXISTS idx_rv_votos ON resultados_votacao(qt_votos_nom_validos DESC);
            CREATE INDEX IF NOT EXISTS idx_rv_filter ON resultados_votacao(Ano, nm_municipio, ds_cargo, sg_partido);
            CREATE INDEX IF NOT EXISTS idx_rv_ranking ON resultados_votacao(qt_votos_nom_validos DESC);
            CREATE INDEX IF NOT EXISTS idx_rv_search ON resultados_votacao(nm_urna_candidato, nm_candidato);
            ";
            $pdo->exec($sql);
        } else {
            $sqlPath = __DIR__ . '/../db/schema.sql';
            if (file_exists($sqlPath)) {
                $sql = file_get_contents($sqlPath);
                $pdo->exec($sql);
            }
            try {
                $pdo->exec("CREATE INDEX idx_filter_composite ON resultados_votacao (Ano, nm_municipio, ds_cargo, sg_partido)");
            } catch (PDOException $e) {}
            try {
                $pdo->exec("CREATE INDEX idx_ranking_perf ON resultados_votacao (qt_votos_nom_validos DESC, nm_urna_candidato ASC)");
            } catch (PDOException $e) {}
            try {
                $pdo->exec("CREATE INDEX idx_cand_search ON resultados_votacao (nm_urna_candidato, nm_candidato)");
            } catch (PDOException $e) {}
        }
    }
}
