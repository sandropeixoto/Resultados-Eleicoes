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
  <header class="navbar">
    <a href="#" class="brand">
      <div class="brand-icon">
        <i class="fas fa-chart-pie"></i>
      </div>
      <div class="brand-text">
        <h1>Data Warehouse Eleitoral</h1>
        <span>Painel Analítico & Inteligência de Campanhas</span>
      </div>
    </a>

    <!-- Nav Tabs -->
    <nav class="nav-tabs">
      <button class="tab-btn active" data-tab="visao-geral">
        <i class="fas fa-th-large"></i> Visão Geral & Análise
      </button>
      <button class="tab-btn" data-tab="comparador">
        <i class="fas fa-balance-scale"></i> Comparador Direto
      </button>
      <button class="tab-btn" data-tab="importador">
        <i class="fas fa-file-import"></i> Importar CSV
      </button>
    </nav>

    <!-- Header Actions & Theme Switcher -->
    <div class="header-actions">
      <button class="theme-toggle-btn" id="themeToggleBtn" title="Alternar Tema">
        <i class="fas fa-sun"></i>
      </button>
    </div>
  </header>

  <!-- Main Container -->
  <main class="container">

    <!-- TAB 1: VISÃO GERAL & ANÁLISE -->
    <section id="visao-geral" class="tab-content active">
      
      <!-- Filter Bar -->
      <div class="filter-bar">
        <div class="filter-grid">
          <div class="filter-group">
            <label for="filterAno"><i class="fas fa-calendar-alt"></i> Ano</label>
            <select id="filterAno" class="form-select">
              <option value="">Carregando...</option>
            </select>
          </div>

          <div class="filter-group">
            <label for="filterMunicipio"><i class="fas fa-map-marker-alt"></i> Município (Busca)</label>
            <select id="filterMunicipio" class="form-select">
              <option value="">Carregando...</option>
            </select>
          </div>

          <div class="filter-group">
            <label for="filterCargo"><i class="fas fa-user-tie"></i> Cargo</label>
            <select id="filterCargo" class="form-select">
              <option value="">Carregando...</option>
            </select>
          </div>

          <div class="filter-group">
            <label for="filterPartido"><i class="fas fa-flag"></i> Partido</label>
            <select id="filterPartido" class="form-select">
              <option value="">Carregando...</option>
            </select>
          </div>

          <div class="filter-group">
            <label for="filterSituacao"><i class="fas fa-award"></i> Situação</label>
            <select id="filterSituacao" class="form-select">
              <option value="">Carregando...</option>
            </select>
          </div>

          <div class="filter-group" style="grid-column: span 2;">
            <label for="filterSearch"><i class="fas fa-search"></i> Busca Global</label>
            <input type="text" id="filterSearch" class="form-control" placeholder="Buscar candidato, nome de urna, município ou partido...">
          </div>

          <div class="filter-group">
            <button id="btnResetFilters" class="btn-filter-reset">
              <i class="fas fa-undo"></i> Limpar Filtros
            </button>
          </div>
        </div>
      </div>

      <!-- KPIs Grid -->
      <div class="kpi-grid">
        <div class="kpi-card">
          <div class="kpi-icon blue"><i class="fas fa-vote-yea"></i></div>
          <div class="kpi-info">
            <span class="kpi-label">Votos Nominais Válidos</span>
            <span class="kpi-value" id="kpiTotalVotos">0</span>
          </div>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon emerald"><i class="fas fa-users"></i></div>
          <div class="kpi-info">
            <span class="kpi-label">Total de Candidatos</span>
            <span class="kpi-value" id="kpiTotalCandidatos">0</span>
          </div>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon amber"><i class="fas fa-city"></i></div>
          <div class="kpi-info">
            <span class="kpi-label">Municípios Mapeados</span>
            <span class="kpi-value" id="kpiTotalMunicipios">0</span>
          </div>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon purple"><i class="fas fa-landmark"></i></div>
          <div class="kpi-info">
            <span class="kpi-label">Partidos Participantes</span>
            <span class="kpi-value" id="kpiTotalPartidos">0</span>
          </div>
        </div>
      </div>

      <!-- Grid 2-Col: Votação por Partido & Tendência Histórica -->
      <div class="grid-2col">
        <div class="card">
          <div class="card-header">
            <h2 class="card-title"><i class="fas fa-chart-pie" style="color: var(--accent-primary);"></i> Votação por Partido</h2>
          </div>
          <div class="card-body" style="height: 300px;">
            <canvas id="chartPartyCanvas"></canvas>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <h2 class="card-title"><i class="fas fa-chart-line" style="color: var(--emerald);"></i> Tendência Histórica (Anos)</h2>
          </div>
          <div class="card-body" style="height: 300px;">
            <canvas id="chartTrendCanvas"></canvas>
          </div>
        </div>
      </div>

      <!-- 1. Situação & Totalização em Coluna Única (Full Width) -->
      <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header">
          <h2 class="card-title"><i class="fas fa-chart-bar" style="color: var(--purple);"></i> Situação & Totalização</h2>
        </div>
        <div class="card-body" style="height: 280px;">
          <canvas id="chartSituationCanvas"></canvas>
        </div>
      </div>

      <!-- 2. Ranking por Candidato em Coluna Única -->
      <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header">
          <h2 class="card-title"><i class="fas fa-trophy" style="color: var(--amber);"></i> Ranking por Candidato</h2>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="data-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Candidato</th>
                  <th>Partido</th>
                  <th>Município</th>
                  <th>Cargo</th>
                  <th>Situação</th>
                  <th>Votos (%)</th>
                </tr>
              </thead>
              <tbody id="tableRankingBody">
                <tr><td colspan="7" style="text-align:center; padding: 2rem;">Carregando dados...</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Módulo de Insights Estratégicos de Campanha -->
      <div class="card">
        <div class="card-header">
          <h2 class="card-title"><i class="fas fa-lightbulb" style="color: var(--amber);"></i> Insights Estratégicos baseados no Banco de Dados</h2>
        </div>
        <div class="card-body">
          <div id="strategicInsightsContainer" class="insights-list">
            <!-- Renderizado dinamicamente via JS -->
          </div>
        </div>
      </div>

    </section>

    <!-- TAB 2: COMPARADOR DIRETO DE CANDIDATOS -->
    <section id="comparador" class="tab-content">
      
      <!-- Candidate Selectors -->
      <div class="compare-selectors">
        <div class="filter-group">
          <label for="compareCandA"><i class="fas fa-user"></i> Candidato A</label>
          <select id="compareCandA" class="form-select">
            <option value="">Carregando...</option>
          </select>
        </div>

        <div class="vs-badge">VS</div>

        <div class="filter-group">
          <label for="compareCandB"><i class="fas fa-user"></i> Candidato B</label>
          <select id="compareCandB" class="form-select">
            <option value="">Carregando...</option>
          </select>
        </div>
      </div>

      <!-- Banner de Vantagem Eleitoral Apurada -->
      <div id="advantageBanner" class="advantage-banner">
        <!-- Renderizado dinamicamente via compare.js -->
      </div>

      <!-- Candidate Battle Grid -->
      <div class="candidate-battle-grid">
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
            <h2 class="card-title"><i class="fas fa-chart-bar" style="color: var(--accent-primary);"></i> Comparativo Nominal em Barras</h2>
          </div>
          <div class="card-body" style="height: 300px;">
            <canvas id="chartCompareBarCanvas"></canvas>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <h2 class="card-title"><i class="fas fa-chess" style="color: var(--emerald);"></i> Recomendação Tática para Transferência de Votos</h2>
          </div>
          <div class="card-body">
            <div id="tacticalSuggestionsContainer">
              <!-- Renderizado dinamicamente via compare.js -->
            </div>
          </div>
        </div>
      </div>

    </section>

    <!-- TAB 3: IMPORTADOR CSV -->
    <section id="importador" class="tab-content">
      
      <div class="card">
        <div class="card-header">
          <h2 class="card-title"><i class="fas fa-cloud-upload-alt" style="color: var(--accent-primary);"></i> Importador de Resultados Eleitorais (.CSV)</h2>
        </div>
        <div class="card-body">
          
          <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">
            Selecione ou arraste um arquivo <code>.csv</code> contendo os dados dos resultados eleitorais para gravação e atualização em tempo real do Data Warehouse.
          </p>

          <!-- Formulário com target para o Iframe de Streaming -->
          <form id="importForm" action="api/import.php" method="POST" enctype="multipart/form-data" target="importFrame">
            <div id="dropzoneArea" class="dropzone-area">
              <div class="dropzone-icon"><i class="fas fa-file-csv"></i></div>
              <div class="dropzone-text">
                <h3>Clique para selecionar ou arraste o arquivo CSV aqui</h3>
                <p>Suporta delimitadores por ponto e vírgula (<code>;</code>), vírgula (<code>,</code>) ou TAB</p>
              </div>
              <input type="file" id="csvFileInput" name="csv_file" accept=".csv" style="display: none;">
            </div>
          </form>

          <!-- Barra de Progresso em Tempo Real -->
          <div id="importProgressContainer" class="progress-container" style="display: none; margin-top: 1.5rem;">
            <div id="progressStatusText" style="font-weight: 600; font-size: 0.9rem; color: var(--text-primary); margin-bottom: 0.5rem;">Preparando importação...</div>
            <div class="progress-bar-bg">
              <div id="progressBarFill" class="progress-bar-fill" style="width: 0%;"></div>
            </div>
          </div>

          <!-- Iframe do Terminal de Log do Andamento da Importação -->
          <div id="importStreamContainer" style="display: none; margin-top: 1.5rem;">
            <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.5rem; display: flex; align-items: center; justify-content: space-between;">
              <span><i class="fas fa-terminal" style="color: var(--accent-primary);"></i> Andamento da Importação (Streaming em Tempo Real)</span>
              <span id="importLiveBadge" class="badge badge-eleito" style="background: var(--accent-primary); color: white;"><i class="fas fa-sync fa-spin"></i> PROCESSANDO</span>
            </div>
            <iframe id="importFrame" name="importFrame" style="width: 100%; height: 260px; border: 1px solid var(--border-color); border-radius: var(--radius-sm); background: #0b0f19;"></iframe>
          </div>

          <!-- Referência de Campos Suportados -->
          <div style="background: var(--bg-input); padding: 1.25rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color); margin-top: 1.5rem;">
            <h4 style="font-size: 0.9rem; font-weight: 700; margin-bottom: 0.5rem;"><i class="fas fa-info-circle" style="color: var(--accent-primary);"></i> Colunas Reconhecidas Automaticamente no CSV:</h4>
            <p style="font-size: 0.8rem; color: var(--text-muted); line-height: 1.6;">
              <code>id</code>, <code>sg_uf</code>, <code>nm_municipio</code>, <code>cd_cargo</code>, <code>ds_cargo</code>, <code>nr_candidato</code>, <code>nm_candidato</code>, <code>nm_urna_candidato</code>, <code>sg_partido</code>, <code>ds_composicao_coligacao</code>, <code>nr_turno</code>, <code>ds_sit_totalizacao</code>, <code>nm_tipo_destinacao_votos</code>, <code>dt_ult_totalizacao</code>, <code>pc_votos_validos</code>, <code>Ano</code>, <code>qt_votos_nom_validos</code>, <code>qt_votos_concorrentes</code>, <code>latitude</code>, <code>longitude</code>.
            </p>
            <div style="margin-top: 0.75rem;">
              <a href="../sample_eleicoes.csv" download class="btn-filter-reset" style="display: inline-flex; text-decoration: none; border-color: var(--accent-primary); color: var(--accent-primary);">
                <i class="fas fa-download"></i> Baixar CSV de Exemplo de Teste
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
