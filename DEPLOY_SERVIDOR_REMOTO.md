# 🚀 Guia de Implantação em Servidor Remoto (sspeixoto.com.br / cPanel)

Este guia orienta o envio e a execução do **Data Warehouse Eleitoral** no seu servidor remoto ou cPanel (`sspeixoto.com.br`).

---

## 📁 1. Arquivos de Configuração Prontos no Projeto

Os seguintes arquivos de servidor já foram criados e configurados na raiz e na pasta `/public`:

| Arquivo | Função no Servidor Remoto |
| :--- | :--- |
| [`index.php`](file:///C:/Dev/Resultados-Eleicoes/index.php) | Entrada principal na raiz da hospedagem (`public_html/eleicoes/`). Redireciona automaticamente o navegador para a pasta `/public/`. |
| [`.htaccess`](file:///C:/Dev/Resultados-Eleicoes/.htaccess) | Configura o Apache sem diretivas `php_value` incompatíveis (evitando erros 500) e garante o redirecionamento limpo para `/public/`. |
| [`public/.htaccess`](file:///C:/Dev/Resultados-Eleicoes/public/.htaccess) | Ativa compressão GZIP, cache de arquivos estáticos e bloqueia download direto de arquivos `.sqlite`, `.sql` e `.log`. |
| [`db/schema.sql`](file:///C:/Dev/Resultados-Eleicoes/db/schema.sql) | Script SQL contendo as estruturas da tabela `resultados_votacao` com os índices otimizados. |
| [`config/database.php`](file:///C:/Dev/Resultados-Eleicoes/config/database.php) | Conexão PDO pré-configurada com as credenciais de produção. |

> [!NOTE]
> **Aviso sobre Limites PHP no cPanel (Erro 500)**: Servidores cPanel modernos usam PHP-FPM / LiteSpeed e disparam **Erro 500 (Internal Server Error)** se houver `php_value` ou `php_flag` dentro do `.htaccess`. Os limites de upload (`upload_max_filesize = 100M`, `post_max_size = 100M`, `memory_limit = 512M`) devem ser configurados na aba **Select PHP Version > Options** ou **MultiPHP INI Editor** do cPanel.

---

## 🗄️ 2. Instalação do Banco de Dados no Servidor Remoto

1. Acesse o **phpMyAdmin** do seu cPanel ou painel de controle do domínio `sspeixoto.com.br`.
2. Selecione o banco de dados `sspeixot_resultados_eleicoes`.
3. Clique na aba **Importar** ou **SQL**.
4. Copie e cole ou selecione o arquivo [`db/schema.sql`](file:///C:/Dev/Resultados-Eleicoes/db/schema.sql) do projeto e clique em **Executar**.

---

## 📤 3. Envio dos Arquivos por FTP / Gerenciador de Arquivos

1. Envie todos os arquivos do projeto para o diretório de hospedagem (`public_html/eleicoes/`).
2. O arquivo [`index.php`](file:///C:/Dev/Resultados-Eleicoes/index.php) na raiz enviará o usuário diretamente para a pasta `/public/` sem travar o servidor.

---

## 🔑 4. Credenciais de Banco de Dados Ativas em `config/database.php`

O arquivo [`config/database.php`](file:///C:/Dev/Resultados-Eleicoes/config/database.php) está configurado com as credenciais de produção:

```php
$config = new Config([
    'driver'   => 'mysql',
    'address'  => 'srv24.prodns.com.br',
    'port'     => '3306',
    'username' => 'sspeixot_resultado_eleicoes',
    'password' => 'Senh@2026',
    'database' => 'sspeixot_resultados_eleicoes',
]);
```

---

## ✅ 5. Verificação Pós-Implantação

1. Acesse a URL do seu site (`http://sspeixoto.com.br/eleicoes/`) no navegador.
2. O sistema redirecionará para `public/` e carregará o Dashboard instantaneamente.
3. Acesse a aba **Importar CSV** para carregar arquivos eleitorais volumosos em tempo real.
