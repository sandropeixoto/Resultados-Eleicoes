# 🚀 Guia de Implantação em Servidor Remoto (sspeixoto.com.br / cPanel)

Este guia orienta o envio e a execução do **Data Warehouse Eleitoral** no seu servidor remoto ou cPanel (`sspeixoto.com.br`).

---

## 📁 1. Arquivos de Configuração Prontos no Projeto

Os seguintes arquivos de servidor já foram criados e configurados na raiz e na pasta `/public`:

| Arquivo | Função no Servidor Remoto |
| :--- | :--- |
| [`.htaccess`](file:///C:/Dev/Resultados-Eleicoes/.htaccess) | Configura o Apache no cPanel para aceitar **uploads de até 100 MB**, ativa compressão GZIP, protege arquivos de configuração e redireciona acessos da raiz para a pasta `/public`. |
| [`public/.htaccess`](file:///C:/Dev/Resultados-Eleicoes/public/.htaccess) | Garante o aumento dos limites no Apache dentro da pasta `/public` e bloqueia o download direto de bancos `.sqlite` ou `.sql`. |
| [`.user.ini`](file:///C:/Dev/Resultados-Eleicoes/public/.user.ini) / [`php.ini`](file:///C:/Dev/Resultados-Eleicoes/php.ini) | Aplicável para servidores Nginx, PHP-FPM, SuPHP ou FastCGI no cPanel para elevar `upload_max_filesize = 100M` e `post_max_size = 100M`. |
| [`db/schema.sql`](file:///C:/Dev/Resultados-Eleicoes/db/schema.sql) | Script SQL contendo as estruturas das tabelas `resultados_votacao` e `election_records` com os índices otimizados. |
| [`config/database.php`](file:///C:/Dev/Resultados-Eleicoes/config/database.php) | Conexão PDO pré-configurada com as suas credenciais fornecidas. |

---

## 🗄️ 2. Instalação do Banco de Dados no Servidor Remoto

1. Acesse o **phpMyAdmin** do seu cPanel ou painel de controle do domínio `sspeixoto.com.br`.
2. Selecione o banco de dados `sspeixot_resultados_eleicoes`.
3. Clique na aba **Importar** ou **SQL**.
4. Copie e cole ou selecione o arquivo [`db/schema.sql`](file:///C:/Dev/Resultados-Eleicoes/db/schema.sql) do projeto e clique em **Executar**.

---

## 📤 3. Envio dos Arquivos por FTP / Gerenciador de Arquivos

1. Envie todos os arquivos do projeto para o diretório de hospedagem (geralmente `public_html` ou o subdomínio correspondente).
2. Certifique-se de que os arquivos ocultos (como `.htaccess` e `.user.ini`) foram incluídos na transferência.

---

## 🔑 4. Credenciais de Banco de Dados Ativas em `config/database.php`

O arquivo [`config/database.php`](file:///C:/Dev/Resultados-Eleicoes/config/database.php) já está configurado com os seus dados de produção:

```php
$config = new Config([
    'driver'   => 'mysql',
    'address'  => 'localhost',
    'port'     => '3306',
    'username' => 'sspeixot_resultado_eleicoes',
    'password' => 'Senh@2026',
    'database' => 'sspeixot_resultados_eleicoes',
]);
```

Caso precise alterar o endereço do servidor MySQL remoto (ex: se o banco estiver em um IP diferente de `localhost`), basta editar a linha `'address' => 'localhost'`.

---

## ✅ 5. Verificação Pós-Implantação

1. Acesse a URL do seu site no navegador.
2. Acesse a aba **Importar CSV** e envie o arquivo `exemplo.csv` ou o seu arquivo eleitoral volumoso.
3. O sistema importará os dados e atualizará o Dashboard em tempo real sem estourar o limite de tamanho do PHP.
