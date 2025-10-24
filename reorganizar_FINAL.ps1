# REORGANIZACAO FINAL - CORRIGIDA
# Versao que NAO causa loops de redirecionamento

Write-Host "Iniciando reorganizacao CORRIGIDA do projeto..." -ForegroundColor Green
Write-Host ""

# Verificar duplicatas
Write-Host "Verificando duplicatas..." -ForegroundColor Cyan
$hasDuplicates = $false

$duplicateChecks = @(
    @("pagamento.php", "Pagamento.php"),
    @("notificacoes.php", "Notificacoes.php")
)

foreach ($check in $duplicateChecks) {
    if ((Test-Path $check[0]) -and (Test-Path $check[1])) {
        Write-Host "  AVISO: Duplicata: $($check[0]) e $($check[1])" -ForegroundColor Yellow
        $hasDuplicates = $true
    }
}

if ($hasDuplicates) {
    Write-Host ""
    Write-Host "Execute primeiro: .\corrigir_duplicatas_limpo.ps1" -ForegroundColor Red
    exit 1
}

Write-Host "OK - Sem duplicatas" -ForegroundColor Green
Write-Host ""

# Criar estrutura
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
    "backup_reorganizacao"
)

foreach ($dir in $directories) {
    if (-not (Test-Path $dir)) {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
    }
}
Write-Host "OK" -ForegroundColor Green
Write-Host ""

# Backup
Write-Host "Criando backup de seguranca..." -ForegroundColor Cyan
if (-not (Test-Path "backup_reorganizacao")) {
    New-Item -ItemType Directory -Path "backup_reorganizacao" -Force | Out-Null
}
Get-ChildItem -File -Filter "*.php" | ForEach-Object {
    Copy-Item $_.FullName -Destination "backup_reorganizacao\" -Force -ErrorAction SilentlyContinue
}
Write-Host "OK - Backup em backup_reorganizacao\" -ForegroundColor Green
Write-Host ""

# Mover CONFIGURACOES
Write-Host "Movendo configuracoes..." -ForegroundColor Cyan
$configFiles = @("config.php", "config_seguro.php")
foreach ($file in $configFiles) {
    if (Test-Path $file) {
        Move-Item $file -Destination "config\" -Force
        Write-Host "  -> $file" -ForegroundColor Gray
    }
}
Write-Host "OK" -ForegroundColor Green
Write-Host ""

# Mover CLASSES
Write-Host "Movendo classes..." -ForegroundColor Cyan
$classMapping = @{
    "Pagamento.php" = "src\classes\Pagamento.php"
    "Notificacoes.php" = "src\classes\Notificacoes.php"
    "GestorPedidos.php" = "src\classes\GestorPedidos.php"
    "FilaImpressao.php" = "src\classes\FilaImpressao.php"
    "RelatoriosGerenciais.php" = "src\classes\RelatoriosGerenciais.php"
}

foreach ($source in $classMapping.Keys) {
    if (Test-Path $source) {
        Move-Item $source -Destination $classMapping[$source] -Force
        Write-Host "  -> $source" -ForegroundColor Gray
    }
}
Write-Host "OK" -ForegroundColor Green
Write-Host ""

# Mover HELPERS
Write-Host "Movendo helpers..." -ForegroundColor Cyan
if (Test-Path "helpers.php") {
    Move-Item "helpers.php" -Destination "src\helpers\" -Force
    Write-Host "  -> helpers.php" -ForegroundColor Gray
}
Write-Host "OK" -ForegroundColor Green
Write-Host ""

# Mover PAGINAS PUBLICAS
Write-Host "Movendo paginas publicas..." -ForegroundColor Cyan
$publicFiles = @(
    "index.php", "painel_cliente.php", "painel_dono.php", "painel_funcionario.php",
    "carrinho.php", "finalizar_carrinho.php", "pedidos.php", "cardapio.php",
    "cardapio_especial.php", "funcionario.php", "editar_produto.php",
    "editar_produto_especial.php", "logout.php", "login.php", "cadastro.php"
)

foreach ($file in $publicFiles) {
    if (Test-Path $file) {
        Move-Item $file -Destination "public\" -Force
        Write-Host "  -> $file" -ForegroundColor Gray
    }
}
Write-Host "OK" -ForegroundColor Green
Write-Host ""

# Mover APIs
Write-Host "Movendo APIs..." -ForegroundColor Cyan
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
Write-Host "OK" -ForegroundColor Green
Write-Host ""

# Mover ASSETS
Write-Host "Movendo assets..." -ForegroundColor Cyan
if (Test-Path "css") {
    Get-ChildItem "css\*" | Move-Item -Destination "assets\css\" -Force -ErrorAction SilentlyContinue
    Remove-Item "css" -Force -ErrorAction SilentlyContinue
    Write-Host "  -> CSS" -ForegroundColor Gray
}
if (Test-Path "js") {
    Get-ChildItem "js\*" | Move-Item -Destination "assets\js\" -Force -ErrorAction SilentlyContinue
    Remove-Item "js" -Force -ErrorAction SilentlyContinue
    Write-Host "  -> JS" -ForegroundColor Gray
}
Write-Host "OK" -ForegroundColor Green
Write-Host ""

# Criar PATHS.PHP
Write-Host "Criando config/paths.php..." -ForegroundColor Cyan
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
Write-Host "OK" -ForegroundColor Green
Write-Host ""

# Criar .htaccess de SEGURANCA (SEM redirecionamento por enquanto)
Write-Host "Criando arquivos de seguranca..." -ForegroundColor Cyan

$htaccessDeny = "Deny from all"
Set-Content -Path "config\.htaccess" -Value $htaccessDeny -Encoding UTF8
Set-Content -Path "src\.htaccess" -Value $htaccessDeny -Encoding UTF8
Write-Host "OK" -ForegroundColor Green
Write-Host ""

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "REORGANIZACAO CONCLUIDA!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "IMPORTANTE:" -ForegroundColor Yellow
Write-Host "  Agora acesse: http://localhost/2025-TCC/public/" -ForegroundColor Cyan
Write-Host ""
Write-Host "Proximos passos:" -ForegroundColor Yellow
Write-Host "  1. Teste se funciona: http://localhost/2025-TCC/public/" -ForegroundColor White
Write-Host "  2. Se funcionar, execute: .\atualizar_caminhos_limpo.ps1" -ForegroundColor White
Write-Host "  3. Depois disso, podemos criar o .htaccess para facilitar o acesso" -ForegroundColor White
Write-Host ""
