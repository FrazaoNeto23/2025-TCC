# SCRIPT DE REORGANIZACAO - BURGER HOUSE
# PowerShell - Windows

Write-Host "Iniciando reorganizacao do projeto Burger House..." -ForegroundColor Green
Write-Host ""

# Verificar duplicatas ANTES de comecar
Write-Host "Verificando duplicatas..." -ForegroundColor Cyan
$hasDuplicates = $false

$duplicateChecks = @(
    @("pagamento.php", "Pagamento.php"),
    @("notificacoes.php", "Notificacoes.php"),
    @("gestor_pedidos.php", "GestorPedidos.php"),
    @("fila_impressao.php", "FilaImpressao.php")
)

foreach ($check in $duplicateChecks) {
    if ((Test-Path $check[0]) -and (Test-Path $check[1])) {
        Write-Host "  AVISO: Duplicata encontrada: $($check[0]) e $($check[1])" -ForegroundColor Yellow
        $hasDuplicates = $true
    }
}

if ($hasDuplicates) {
    Write-Host ""
    Write-Host "ERRO: Arquivos duplicados encontrados!" -ForegroundColor Red
    Write-Host ""
    Write-Host "Execute primeiro:" -ForegroundColor Yellow
    Write-Host "  .\corrigir_duplicatas_limpo.ps1" -ForegroundColor Cyan
    Write-Host ""
    exit 1
}

Write-Host "OK - Nenhuma duplicata encontrada" -ForegroundColor Green
Write-Host ""

# Criar estrutura de diretorios
Write-Host "Criando estrutura de diretorios..." -ForegroundColor Cyan

$directories = @(
    "config",
    "src\classes",
    "src\helpers",
    "public",
    "api",
    "assets\css",
    "assets\js",
    "views",
    "uploads",
    "relatorios",
    "database",
    "backup_original"
)

foreach ($dir in $directories) {
    if (-not (Test-Path $dir)) {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
    }
}

Write-Host "OK - Estrutura criada" -ForegroundColor Green
Write-Host ""

# Fazer backup
Write-Host "Criando backup dos arquivos originais..." -ForegroundColor Cyan
if (-not (Test-Path "backup_original")) {
    New-Item -ItemType Directory -Path "backup_original" -Force | Out-Null
}
Get-ChildItem -File | ForEach-Object {
    Copy-Item $_.FullName -Destination "backup_original\" -Force -ErrorAction SilentlyContinue
}
Write-Host "OK - Backup criado em backup_original\" -ForegroundColor Green
Write-Host ""

# Mover arquivos de CONFIGURACAO
Write-Host "Movendo arquivos de configuracao..." -ForegroundColor Cyan
$configFiles = @("config.php", "config_seguro.php")
foreach ($file in $configFiles) {
    if (Test-Path $file) {
        Move-Item $file -Destination "config\" -Force
        Write-Host "  -> $file" -ForegroundColor Gray
    }
}
Write-Host "OK - Configuracoes movidas" -ForegroundColor Green
Write-Host ""

# Mover CLASSES
Write-Host "Movendo classes PHP..." -ForegroundColor Cyan

$classFiles = @{
    "Notificacoes.php" = "src\classes\Notificacoes.php"
    "Pagamento.php" = "src\classes\Pagamento.php"
    "GestorPedidos.php" = "src\classes\GestorPedidos.php"
    "FilaImpressao.php" = "src\classes\FilaImpressao.php"
    "RelatoriosGerenciais.php" = "src\classes\RelatoriosGerenciais.php"
}

if ((Test-Path "notificacoes.php") -and -not (Test-Path "Notificacoes.php")) {
    Rename-Item "notificacoes.php" "Notificacoes.php" -Force
}
if ((Test-Path "gestor_pedidos.php") -and -not (Test-Path "GestorPedidos.php")) {
    Rename-Item "gestor_pedidos.php" "GestorPedidos.php" -Force
}
if ((Test-Path "fila_impressao.php") -and -not (Test-Path "FilaImpressao.php")) {
    Rename-Item "fila_impressao.php" "FilaImpressao.php" -Force
}
if ((Test-Path "relatorios_gerenciais.php") -and -not (Test-Path "RelatoriosGerenciais.php")) {
    Rename-Item "relatorios_gerenciais.php" "RelatoriosGerenciais.php" -Force
}

foreach ($source in $classFiles.Keys) {
    if (Test-Path $source) {
        Move-Item $source -Destination $classFiles[$source] -Force
        Write-Host "  -> $($classFiles[$source])" -ForegroundColor Gray
    }
}
Write-Host "OK - Classes movidas" -ForegroundColor Green
Write-Host ""

# Mover HELPERS
Write-Host "Movendo arquivos auxiliares..." -ForegroundColor Cyan
if (Test-Path "helpers.php") {
    Move-Item "helpers.php" -Destination "src\helpers\" -Force
    Write-Host "  -> helpers.php" -ForegroundColor Gray
}
Write-Host "OK - Helpers movidos" -ForegroundColor Green
Write-Host ""

# Mover paginas PUBLICAS
Write-Host "Movendo paginas publicas..." -ForegroundColor Cyan

$knownPublicFiles = @(
    "index.php", "painel_cliente.php", "painel_dono.php", "painel_funcionario.php",
    "carrinho.php", "finalizar_carrinho.php", "pedidos.php", "cardapio.php",
    "cardapio_especial.php", "funcionario.php", "editar_produto.php",
    "editar_produto_especial.php", "logout.php", "teste_form.php", "login.php",
    "cadastro.php", "processar_login.php", "processar_cadastro.php"
)

foreach ($file in $knownPublicFiles) {
    if (Test-Path $file) {
        Move-Item $file -Destination "public\" -Force
        Write-Host "  -> $file" -ForegroundColor Gray
    }
}

$otherPhpFiles = Get-ChildItem -Path . -Filter "*.php" -File | Where-Object {
    $name = $_.Name.ToLower()
    $name -notmatch "^(config|database|conexao)" -and
    $name -notmatch "^[A-Z]" -and
    $name -notmatch "^(notificacoes|pagamento|gestor|fila|relatorios)" -and
    $name -notmatch "^(api_|confirmar_|validar_|pagamento_)" -and
    $_.Name -ne "index.php"
}

foreach ($file in $otherPhpFiles) {
    if (Test-Path $file.FullName) {
        Write-Host "  AVISO: Arquivo adicional encontrado: $($file.Name)" -ForegroundColor Yellow
        Write-Host "     Movendo para public/" -ForegroundColor Gray
        Move-Item $file.FullName -Destination "public\" -Force -ErrorAction SilentlyContinue
    }
}

Write-Host "OK - Paginas publicas movidas" -ForegroundColor Green
Write-Host ""

# Mover APIs
Write-Host "Movendo endpoints da API..." -ForegroundColor Cyan
$apiFiles = @(
    "api_notificacoes.php", "confirmar_pix.php", "validar_cupom.php",
    "pagamento_pix.php", "confirmar_pagamento.php"
)

foreach ($file in $apiFiles) {
    if (Test-Path $file) {
        Move-Item $file -Destination "api\" -Force
        Write-Host "  -> $file" -ForegroundColor Gray
    }
}
Write-Host "OK - APIs movidas" -ForegroundColor Green
Write-Host ""

# Mover ASSETS
Write-Host "Movendo assets (CSS/JS)..." -ForegroundColor Cyan
if (Test-Path "css") {
    Get-ChildItem "css\*" | Move-Item -Destination "assets\css\" -Force -ErrorAction SilentlyContinue
    Remove-Item "css" -Force -ErrorAction SilentlyContinue
    Write-Host "  -> CSS movido" -ForegroundColor Gray
}
if (Test-Path "js") {
    Get-ChildItem "js\*" | Move-Item -Destination "assets\js\" -Force -ErrorAction SilentlyContinue
    Remove-Item "js" -Force -ErrorAction SilentlyContinue
    Write-Host "  -> JS movido" -ForegroundColor Gray
}
if (Test-Path "notificacoes-realtime.js") {
    Move-Item "notificacoes-realtime.js" -Destination "assets\js\" -Force
    Write-Host "  -> notificacoes-realtime.js" -ForegroundColor Gray
}
Write-Host "OK - Assets movidos" -ForegroundColor Green
Write-Host ""

# Mover DATABASE
Write-Host "Movendo arquivos de banco de dados..." -ForegroundColor Cyan
if (Test-Path "burger_house.sql") {
    Move-Item "burger_house.sql" -Destination "database\" -Force
    Write-Host "  -> burger_house.sql" -ForegroundColor Gray
}
Write-Host "OK - Database movido" -ForegroundColor Green
Write-Host ""

# Criar .htaccess
Write-Host "Criando arquivos de seguranca..." -ForegroundColor Cyan

$htaccessMain = @'
RewriteEngine On
RewriteCond %{REQUEST_URI} !^/public/
RewriteRule ^(.*)$ public/$1 [L]
RewriteRule ^(config|src|database|backup_original)/ - [F,L]
'@
Set-Content -Path ".htaccess" -Value $htaccessMain -Encoding UTF8
Write-Host "  -> .htaccess criado" -ForegroundColor Gray

$htaccessDeny = "Deny from all"
Set-Content -Path "config\.htaccess" -Value $htaccessDeny -Encoding UTF8
Write-Host "  -> config\.htaccess criado" -ForegroundColor Gray

Set-Content -Path "src\.htaccess" -Value $htaccessDeny -Encoding UTF8
Write-Host "  -> src\.htaccess criado" -ForegroundColor Gray

Write-Host "OK - Seguranca configurada" -ForegroundColor Green
Write-Host ""

# Criar index.php redirecionador
$indexPhp = @'
<?php
header('Location: public/index.php');
exit;
?>
'@
Set-Content -Path "index.php" -Value $indexPhp -Encoding UTF8
Write-Host "OK - Index de redirecionamento criado" -ForegroundColor Green
Write-Host ""

# Criar paths.php
$pathsPhp = @'
<?php
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'config');
define('SRC_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'src');
define('PUBLIC_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'public');
define('API_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'api');
define('ASSETS_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'assets');
define('VIEWS_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'views');
define('UPLOADS_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'uploads');
define('REPORTS_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'relatorios');

define('BASE_URL', 'http://localhost/2025-TCC');
define('ASSETS_URL', BASE_URL . '/assets');
define('UPLOADS_URL', BASE_URL . '/uploads');

spl_autoload_register(function ($class) {
    $file = SRC_PATH . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
?>
'@
Set-Content -Path "config\paths.php" -Value $pathsPhp -Encoding UTF8
Write-Host "OK - config\paths.php criado" -ForegroundColor Green
Write-Host ""

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "REORGANIZACAO CONCLUIDA COM SUCESSO!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Proximo passo:" -ForegroundColor Yellow
Write-Host "  .\atualizar_caminhos_limpo.ps1" -ForegroundColor Cyan
Write-Host ""
