<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Data Warehouse Eleitoral - Resultados & Análise Estratégica</title>
  
  <!-- SEO Meta Tags -->
  <meta name="description" content="Dashboard de Data Warehouse Eleitoral com análise de votos, estatísticas por partido, comparador direto de candidatos e importador de CSV.">
  
  <!-- Fonts & Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <!-- TomSelect CSS (Searchable Selects) -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css">
  
  <!-- Custom CSS Design System -->
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

  <!-- Top Navigation Header -->
  <header class="navbar" role="banner">
    <a href="#" class="brand" aria-label="Data Warehouse Eleitoral - Voltar ao início">
      <div class="brand-icon">
        <i class="fas fa-chart-pie" aria-hidden="true"></i>
      </div>
      <div class="brand-text">
        <h1>Data Warehouse Eleitoral</h1>
        <span>Painel Analítico & Inteligência de Campanhas</span>
      </div>
    </a>

    <!-- Nav Tabs -->
    <nav class="nav-tabs" role="tablist" aria-label="Navegação Principal do Painel">
      <button class="tab-btn active" id="tab-btn-visao-geral" role="tab" aria-selected="true" aria-controls="visao-geral" data-tab="visao-geral">
        <i class="fas fa-th-large" aria-hidden="true"></i> Visão Geral & Análise
      </button>
      <button class="tab-btn" id="tab-btn-comparador" role="tab" aria-selected="false" aria-controls="comparador" data-tab="comparador">
        <i class="fas fa-balance-scale" aria-hidden="true"></i> Comparador Direto
      </button>
      <button class="tab-btn" id="tab-btn-importador" role="tab" aria-selected="false" aria-controls="importador" data-tab="importador">
        <i class="fas fa-file-import" aria-hidden="true"></i> Importar CSV
      </button>
    </nav>

    <!-- Header Actions & Theme Switcher -->
    <div class="header-actions">
      <div id="dbStatusBadge" class="db-status-badge" title="Status do Banco de Dados & Registros em Leitura" role="status" aria-live="polite">
        <span id="dbDriverTag" class="db-driver-tag sqlite">
          <i class="fas fa-database" aria-hidden="true"></i>
          <span id="dbDriverName">Carregando...</span>
        </span>
        <span class="db-records-tag">
          <i class="fas fa-layer-group" aria-hidden="true"></i>
          <span id="dbRecordCount">0</span> reg.
        </span>
      </div>

      <button class="theme-toggle-btn" id="themeToggleBtn" aria-label="Alternar para Tema Clean" title="Alternar Tema">
        <i class="fas fa-sun" aria-hidden="true"></i>
      </button>
    </div>
  </header>

  <!-- Main Container -->
  <main class="container" role="main">

    <!-- TAB 1: VISÃO GERAL & ANÁLISE -->
    <section id="visao-geral" class="tab-content active" role="tabpanel" aria-labelledby="tab-btn-visao-geral" tabindex="0">
      
      <!-- Filter Bar -->
      <div class="filter-bar" aria-label="Filtros de Pesquisa">
        <div class="filter-grid">
          <div class="filter-group">
            <label for="filterAno"><i class="fas fa-calendar-alt" aria-hidden="true"></i> Ano</label>
            <select id="filterAno" class="form-select" aria-label="Filtrar por Ano Eleitoral">
              <option value="">Carregando...</option>
            </select>
          </div>

          <div class="filter-group">
            <label for="filterMunicipio"><i class="fas fa-map-marker-alt" aria-hidden="true"></i> Município (Busca)</label>
            <select id="filterMunicipio" class="form-select" aria-label="Filtrar por Município">
              <option value="">Carregando...</option>
            </select>
          </div>

          <div class="filter-group">
            <label for="filterCargo"><i class="fas fa-user-tie" aria-hidden="true"></i> Cargo</label>
            <select id="filterCargo" class="form-select" aria-label="Filtrar por Cargo">
              <option value="">Carregando...</option>
            </select>
          </div>

          <div class="filter-group">
            <label for="filterPartido"><i class="fas fa-flag" aria-hidden="true"></i> Partido</label>
            <select id="filterPartido" class="form-select" aria-label="Filtrar por Partido">
              <option value="">Carregando...</option>
            </select>
          </div>

          <div class="filter-group">
            <label for="filterSituacao"><i class="fas fa-award" aria-hidden="true"></i> Situação</label>
            <select id="filterSituacao" class="form-select" aria-label="Filtrar por Situação da Totalização">
              <option value="">Carregando...</option>
            </select>
          </div>

          <div class="filter-group filter-group-wide">
            <label for="filterSearch"><i class="fas fa-search" aria-hidden="true"></i> Busca Global</label>
            <input type="text" id="filterSearch" class="form-control" placeholder="Buscar candidato, nome de urna, município ou partido..." aria-label="Busca global em tempo real">
          </div>

          <div class="filter-group filter-group-action">
            <button id="btnResetFilters" class="btn-filter-reset" aria-label="Limpar todos os filtros aplicados">
              <i class="fas fa-undo" aria-hidden="true"></i> Limpar Filtros
            </button>
          </div>
        </div>
      </div>

      <!-- KPIs Grid -->
      <div class="kpi-grid" aria-label="Indicadores Chave de Desempenho (KPIs)">
        <div class="kpi-card">
          <div class="kpi-icon blue"><i class="fas fa-vote-yea" aria-hidden="true"></i></div>
          <div class="kpi-info">
            <span class="kpi-label">Votos Nominais Válidos</span>
            <span class="kpi-value" id="kpiTotalVotos">0</span>
          </div>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon emerald"><i class="fas fa-users" aria-hidden="true"></i></div>
          <div class="kpi-info">
            <span class="kpi-label">Total de Candidatos</span>
            <span class="kpi-value" id="kpiTotalCandidatos">0</span>
          </div>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon amber"><i class="fas fa-city" aria-hidden="true"></i></div>
          <div class="kpi-info">
            <span class="kpi-label">Municípios Mapeados</span>
            <span class="kpi-value" id="kpiTotalMunicipios">0</span>
          </div>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon purple"><i class="fas fa-landmark" aria-hidden="true"></i></div>
          <div class="kpi-info">
            <span class="kpi-label">Partidos Participantes</span>
            <span class="kpi-value" id="kpiTotalPartidos">0</span>
          </div>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon blue"><i class="fas fa-network-wired" aria-hidden="true"></i></div>
          <div class="kpi-info">
            <span class="kpi-label">Concentração (HHI / ENP)</span>
            <span class="kpi-value" id="kpiHHI">0</span>
          </div>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon emerald"><i class="fas fa-calculator" aria-hidden="true"></i></div>
          <div class="kpi-info">
            <span class="kpi-label">Quociente Eleitoral (QE)</span>
            <span class="kpi-value" id="kpiQE">0</span>
          </div>
        </div>
      </div>

      <!-- Grid 2-Col: Votação por Partido & Tendência Histórica -->
      <div class="grid-2col">
        <div class="card">
          <div class="card-header">
            <h2 class="card-title"><i class="fas fa-chart-pie" style="color: var(--accent-primary);" aria-hidden="true"></i> Votação por Partido</h2>
          </div>
          <div class="card-body chart-container-wrapper">
            <canvas id="chartPartyCanvas" aria-label="Gráfico de Rosca demonstrando Votação por Partido" role="img"></canvas>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <h2 class="card-title"><i class="fas fa-chart-line" style="color: var(--emerald);" aria-hidden="true"></i> Tendência Histórica (Anos)</h2>
          </div>
          <div class="card-body chart-container-wrapper">
            <canvas id="chartTrendCanvas" aria-label="Gráfico de Linha com a Tendência Histórica de Votos" role="img"></canvas>
          </div>
        </div>
      </div>

      <!-- Situação & Totalização (Full Width) -->
      <div class="card card-mb">
        <div class="card-header">
          <h2 class="card-title"><i class="fas fa-chart-bar" style="color: var(--purple);" aria-hidden="true"></i> Situação & Totalização</h2>
        </div>
        <div class="card-body chart-container-wrapper-sm">
          <canvas id="chartSituationCanvas" aria-label="Gráfico de Barras por Situação da Totalização dos Candidatos" role="img"></canvas>
        </div>
      </div>

      <!-- Ranking por Candidato -->
      <div class="card card-mb">
        <div class="card-header">
          <h2 class="card-title"><i class="fas fa-trophy" style="color: var(--amber);" aria-hidden="true"></i> Ranking por Candidato</h2>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="data-table" aria-label="Tabela de Ranking de Candidatos">
              <thead>
                <tr>
                  <th scope="col">#</th>
                  <th scope="col">Candidato</th>
                  <th scope="col">Partido</th>
                  <th scope="col">Município</th>
                  <th scope="col">Cargo</th>
                  <th scope="col">Situação</th>
                  <th scope="col">Votos (%)</th>
                </tr>
              </thead>
              <tbody id="tableRankingBody">
                <tr><td colspan="7" class="td-status-msg">Carregando dados...</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Módulo de Insights Estratégicos de Campanha -->
      <div class="card">
        <div class="card-header">
          <h2 class="card-title"><i class="fas fa-lightbulb" style="color: var(--amber);" aria-hidden="true"></i> Insights Estratégicos baseados no Banco de Dados</h2>
        </div>
        <div class="card-body">
          <div id="strategicInsightsContainer" class="insights-list" aria-live="polite">
            <!-- Renderizado dinamicamente via JS -->
          </div>
        </div>
      </div>

    </section>

    <!-- TAB 2: COMPARADOR DIRETO DE CANDIDATOS -->
    <section id="comparador" class="tab-content" role="tabpanel" aria-labelledby="tab-btn-comparador" tabindex="0">
      
      <!-- Candidate Selectors -->
      <div class="compare-selectors" aria-label="Seleção de Candidatos para Comparação">
        <div class="filter-group">
          <label for="compareCandA"><i class="fas fa-user" aria-hidden="true"></i> Candidato A</label>
          <select id="compareCandA" class="form-select" aria-label="Selecionar Primeiro Candidato para Comparativo">
            <option value="">Carregando...</option>
          </select>
        </div>

        <div class="vs-badge" aria-hidden="true">VS</div>

        <div class="filter-group">
          <label for="compareCandB"><i class="fas fa-user" aria-hidden="true"></i> Candidato B</label>
          <select id="compareCandB" class="form-select" aria-label="Selecionar Segundo Candidato para Comparativo">
            <option value="">Carregando...</option>
          </select>
        </div>
      </div>

      <!-- Banner de Vantagem Eleitoral Apurada -->
      <div id="advantageBanner" class="advantage-banner" aria-live="polite">
        <!-- Renderizado dinamicamente via compare.js -->
      </div>

      <!-- Candidate Battle Grid -->
      <div class="candidate-battle-grid" aria-label="Confronto Direto de Candidatos">
        <div id="candACard" class="candidate-card">
          <!-- Card Candidato A -->
        </div>

        <div id="candBCard" class="candidate-card">
          <!-- Card Candidato B -->
        </div>
      </div>

      <!-- Grid 2-Col: Gráfico Comparativo & Sugestões Táticas -->
      <div class="grid-2col">
        <div class="card">
          <div class="card-header">
            <h2 class="card-title"><i class="fas fa-chart-bar" style="color: var(--accent-primary);" aria-hidden="true"></i> Comparativo Nominal em Barras</h2>
          </div>
          <div class="card-body chart-container-wrapper">
            <canvas id="chartCompareBarCanvas" aria-label="Gráfico de Barras Comparativo de Votos entre Candidato A e B" role="img"></canvas>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <h2 class="card-title"><i class="fas fa-chess" style="color: var(--emerald);" aria-hidden="true"></i> Recomendação Tática para Transferência de Votos</h2>
          </div>
          <div class="card-body">
            <div id="tacticalSuggestionsContainer" aria-live="polite">
              <!-- Renderizado dinamicamente via compare.js -->
            </div>
          </div>
        </div>
      </div>

    </section>

    <!-- TAB 3: IMPORTADOR CSV -->
    <section id="importador" class="tab-content" role="tabpanel" aria-labelledby="tab-btn-importador" tabindex="0">
      
      <div class="card">
        <div class="card-header">
          <h2 class="card-title"><i class="fas fa-cloud-upload-alt" style="color: var(--accent-primary);" aria-hidden="true"></i> Importador de Resultados Eleitorais (.CSV)</h2>
        </div>
        <div class="card-body">
          
          <p class="import-description">
            Selecione ou arraste um arquivo <code>.csv</code> contendo os dados dos resultados eleitorais para gravação e atualização em tempo real do Data Warehouse.
          </p>

          <!-- Formulário com target para o Iframe de Streaming -->
          <form id="importForm" action="api/import.php" method="POST" enctype="multipart/form-data" target="importFrame">
            <div id="dropzoneArea" class="dropzone-area" role="button" tabindex="0" aria-label="Área para selecionar ou arrastar arquivo CSV">
              <div class="dropzone-icon"><i class="fas fa-file-csv" aria-hidden="true"></i></div>
              <div class="dropzone-text">
                <h3>Clique para selecionar ou arraste o arquivo CSV aqui</h3>
                <p>Suporta delimitadores por ponto e vírgula (<code>;</code>), vírgula (<code>,</code>) ou TAB</p>
              </div>
              <input type="file" id="csvFileInput" name="csv_file" accept=".csv" style="display: none;" aria-hidden="true" tabindex="-1">
            </div>
          </form>

          <!-- Barra de Progresso em Tempo Real -->
          <div id="importProgressContainer" class="progress-container" aria-live="polite">
            <div id="progressStatusText" class="progress-status-text">Preparando importação...</div>
            <div class="progress-bar-bg" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
              <div id="progressBarFill" class="progress-bar-fill"></div>
            </div>
          </div>

          <!-- Iframe do Terminal de Log do Andamento da Importação -->
          <div id="importStreamContainer" class="terminal-container">
            <div class="terminal-header">
              <span><i class="fas fa-terminal" style="color: var(--accent-primary);" aria-hidden="true"></i> Andamento da Importação (Streaming em Tempo Real)</span>
              <span id="importLiveBadge" class="badge badge-eleito badge-live"><i class="fas fa-sync fa-spin" aria-hidden="true"></i> PROCESSANDO</span>
            </div>
            <iframe id="importFrame" name="importFrame" title="Terminal de Ingestão de Dados em Tempo Real" class="terminal-iframe"></iframe>
          </div>

          <!-- Referência de Campos Suportados -->
          <div class="supported-columns-box">
            <h4 class="supported-columns-title"><i class="fas fa-info-circle" style="color: var(--accent-primary);" aria-hidden="true"></i> Colunas Reconhecidas Automaticamente no CSV:</h4>
            <p class="supported-columns-list">
              <code>id</code>, <code>sg_uf</code>, <code>nm_municipio</code>, <code>cd_cargo</code>, <code>ds_cargo</code>, <code>nr_candidato</code>, <code>nm_candidato</code>, <code>nm_urna_candidato</code>, <code>sg_partido</code>, <code>ds_composicao_coligacao</code>, <code>nr_turno</code>, <code>ds_sit_totalizacao</code>, <code>nm_tipo_destinacao_votos</code>, <code>dt_ult_totalizacao</code>, <code>pc_votos_validos</code>, <code>Ano</code>, <code>qt_votos_nom_validos</code>, <code>qt_votos_concorrentes</code>, <code>latitude</code>, <code>longitude</code>.
            </p>
            <div class="download-sample-wrapper">
              <a href="../sample_eleicoes.csv" download class="btn-filter-reset btn-sample-download">
                <i class="fas fa-download" aria-hidden="true"></i> Baixar CSV de Exemplo de Teste
              </a>
            </div>
          </div>

        </div>
      </div>

    </section>

  </main>

  <!-- External JS Libraries (Chart.js & TomSelect) -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

  <!-- Application Modules -->
  <script src="js/app.js"></script>
  <script src="js/dashboard.js"></script>
  <script src="js/compare.js"></script>
  <script src="js/import.js"></script>

</body>
</html>
