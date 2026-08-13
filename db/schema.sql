-- =========================================================================
-- ESTRUTURA DE TABELAS PARA PHP-CRUD-API & DATA WAREHOUSE ELEITORAL
-- Execute no MySQL / phpMyAdmin ou banco local sspeixot_resultados_eleicoes
-- =========================================================================

CREATE TABLE IF NOT EXISTS `resultados_votacao` (
  `id` VARCHAR(128) NOT NULL PRIMARY KEY,
  `sg_uf` VARCHAR(10) DEFAULT 'PA',
  `nm_municipio` VARCHAR(150) DEFAULT '',
  `cd_cargo` INT DEFAULT 11,
  `ds_cargo` VARCHAR(100) DEFAULT 'Prefeito',
  `nr_candidato` INT DEFAULT 0,
  `nm_candidato` VARCHAR(250) DEFAULT '',
  `nm_urna_candidato` VARCHAR(250) DEFAULT '',
  `sg_partido` VARCHAR(30) DEFAULT '',
  `ds_composicao_coligacao` TEXT,
  `nr_turno` INT DEFAULT 1,
  `ds_sit_totalizacao` VARCHAR(100) DEFAULT 'ELEITO',
  `nm_tipo_destinacao_votos` VARCHAR(100) DEFAULT 'VÁLIDO',
  `dt_ult_totalizacao` VARCHAR(50) DEFAULT '',
  `pc_votos_validos` DOUBLE DEFAULT 0,
  `Ano` INT DEFAULT 2024,
  `qt_votos_nom_validos` INT DEFAULT 0,
  `qt_votos_concorrentes` INT DEFAULT 0,
  `latitude` DOUBLE DEFAULT 0,
  `longitude` DOUBLE DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_ano` (`Ano`),
  INDEX `idx_municipio` (`nm_municipio`),
  INDEX `idx_partido` (`sg_partido`),
  INDEX `idx_uf_muni` (`sg_uf`, `nm_municipio`),
  INDEX `idx_cargo` (`cd_cargo`, `ds_cargo`),
  INDEX `idx_situacao` (`ds_sit_totalizacao`),
  INDEX `idx_filter_composite` (`Ano`, `nm_municipio`, `ds_cargo`, `sg_partido`),
  INDEX `idx_ranking_perf` (`qt_votos_nom_validos` DESC, `nm_urna_candidato` ASC),
  INDEX `idx_cand_search` (`nm_urna_candidato`, `nm_candidato`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

