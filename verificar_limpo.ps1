# SCRIPT DE VERIFICACAO - BURGER HOUSE
# PowerShell - Windows

Write-Host "Verificando estrutura do projeto..." -ForegroundColor Green
Write-Host ""

$errors = 0
$warnings = 0

function Test-Folder {
    param([string]$Path, [string]$Description)
    
    if (Test-Path $Path) {
        Write-Host "  OK - $Description" -ForegroundColor Green
        return $true
    } else {
        Write-Host "  ERRO - $Description - NAO ENCONTRADO" -ForegroundColor Red
        $script:errors++
        return $false
    }
}

function Test-FileExists {
    param([string]$Path, [string]$Description, [bool]$Critical = $true)
    
    if (Test-Path $Path) {
        Write-Host "  OK - $Description" -ForegroundColor Green
        return $true
    } else {
        if ($Critical) {
            Write-Host "  ERRO - $Description - NAO ENCONTRADO" -ForegroundColor Red
            $script:errors++
        } else {
            Write-Host "  AVISO - $Description - NAO ENCONTRADO" -ForegroundColor Yellow
            $script:warnings++
        }
        return $false
    }
}

# Verificar estrutura de pastas
Write-Host "Verificando estrutura de pastas..." -ForegroundColor Cyan
Write-Host ""

Test-Folder "config" "Pasta config/"
Test-Folder "src" "Pasta src/"
Test-Folder "src\classes" "Pasta src/classes/"
Test-Folder "src\helpers" "Pasta src/helpers/"
Test-Folder "public" "Pasta public/"
Test-Folder "api" "Pasta api/"
Test-Folder "assets" "Pasta assets/"
Test-Folder "assets\css" "Pasta assets/css/"
Test-Folder "assets\js" "Pasta assets/js/"
Test-Folder "views" "Pasta views/"
Test-Folder "uploads" "Pasta uploads/"
Test-Folder "relatorios" "Pasta relatorios/"
Test-Folder "database" "Pasta database/"
Test-Folder "backup_original" "Pasta backup_original/"

Write-Host ""

# Verificar arquivos criticos de configuracao
Write-Host "Verificando arquivos de configuracao..." -ForegroundColor Cyan
Write-Host ""

Test-FileExists "config\paths.php" "config/paths.php" $true
Test-FileExists "config\config.php" "config/config.php" $false
Test-FileExists "config\.htaccess" "config/.htaccess (seguranca)" $true

Write-Host ""

# Verificar classes
Write-Host "Verificando classes PHP..." -ForegroundColor Cyan
Write-Host ""

Test-FileExists "src\classes\Notificacoes.php" "Notificacoes.php" $false
Test-FileExists "src\classes\Pagamento.php" "Pagamento.php" $false
Test-FileExists "src\classes\GestorPedidos.php" "GestorPedidos.php" $false
Test-FileExists "src\classes\FilaImpressao.php" "FilaImpressao.php" $false

Write-Host ""

# Verificar arquivos publicos principais
Write-Host "Verificando paginas publicas..." -ForegroundColor Cyan
Write-Host ""

Test-FileExists "public\index.php" "index.php" $true
Test-FileExists "public\painel_cliente.php" "painel_cliente.php" $false
Test-FileExists "public\painel_dono.php" "painel_dono.php" $false
Test-FileExists "public\carrinho.php" "carrinho.php" $false
Test-FileExists "public\cardapio.php" "cardapio.php" $false

Write-Host ""

# Verificar APIs
Write-Host "Verificando APIs..." -ForegroundColor Cyan
Write-Host ""

Test-FileExists "api\api_notificacoes.php" "api_notificacoes.php" $false
Test-FileExists "api\confirmar_pix.php" "confirmar_pix.php" $false
Test-FileExists "api\pagamento_pix.php" "pagamento_pix.php" $false

Write-Host ""

# Verificar seguranca
Write-Host "Verificando configuracoes de seguranca..." -ForegroundColor Cyan
Write-Host ""

Test-FileExists ".htaccess" ".htaccess (raiz)" $true
Test-FileExists "src\.htaccess" "src/.htaccess" $true
Test-FileExists "index.php" "index.php (redirecionador)" $true

Write-Host ""

# Verificar permissoes de escrita (Windows)
Write-Host "Verificando permissoes de escrita..." -ForegroundColor Cyan
Write-Host ""

$writableFolders = @("uploads", "relatorios")
foreach ($folder in $writableFolders) {
    if (Test-Path $folder) {
        try {
            $testFile = Join-Path $folder "test_write.tmp"
            "test" | Out-File $testFile -ErrorAction Stop
            Remove-Item $testFile -ErrorAction SilentlyContinue
            Write-Host "  OK - $folder/ - Gravavel" -ForegroundColor Green
        } catch {
            Write-Host "  ERRO - $folder/ - SEM PERMISSAO DE ESCRITA" -ForegroundColor Red
            $script:errors++
        }
    }
}

Write-Host ""

# Verificar se PHP esta configurado
Write-Host "Verificando PHP..." -ForegroundColor Cyan
Write-Host ""

try {
    $phpVersion = php -v 2>&1
    if ($phpVersion -match "PHP (\d+\.\d+\.\d+)") {
        Write-Host "  OK - PHP instalado: $($matches[1])" -ForegroundColor Green
    } else {
        Write-Host "  AVISO - PHP instalado mas versao nao detectada" -ForegroundColor Yellow
        $script:warnings++
    }
} catch {
    Write-Host "  ERRO - PHP nao encontrado no PATH" -ForegroundColor Red
    Write-Host "    Configure o PHP no PATH ou use XAMPP/WAMP" -ForegroundColor Yellow
    $script:warnings++
}

Write-Host ""

# Verificar arquivo paths.php em detalhes
Write-Host "Analisando paths.php..." -ForegroundColor Cyan
Write-Host ""

if (Test-Path "config\paths.php") {
    $pathsContent = Get-Content "config\paths.php" -Raw
    
    $requiredDefines = @(
        "ROOT_PATH",
        "CONFIG_PATH",
        "SRC_PATH",
        "PUBLIC_PATH",
        "UPLOADS_PATH"
    )
    
    foreach ($define in $requiredDefines) {
        if ($pathsContent -match "define\('$define'") {
            Write-Host "  OK - $define definido" -ForegroundColor Green
        } else {
            Write-Host "  ERRO - $define NAO ENCONTRADO" -ForegroundColor Red
            $script:errors++
        }
    }
    
    # Verificar autoload
    if ($pathsContent -match "spl_autoload_register") {
        Write-Host "  OK - Autoload de classes configurado" -ForegroundColor Green
    } else {
        Write-Host "  AVISO - Autoload nao encontrado" -ForegroundColor Yellow
        $script:warnings++
    }
}

Write-Host ""

# Estatisticas finais
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan

if ($errors -eq 0 -and $warnings -eq 0) {
    Write-Host "VERIFICACAO COMPLETA - TUDO OK!" -ForegroundColor Green
    Write-Host ""
    Write-Host "O projeto esta corretamente estruturado!" -ForegroundColor Green
    Write-Host ""
    Write-Host "Proximos passos:" -ForegroundColor Yellow
    Write-Host "  1. Inicie seu servidor (XAMPP/WAMP)"
    Write-Host "  2. Acesse: http://localhost/2025-TCC/"
    Write-Host "  3. Teste todas as funcionalidades"
    
} elseif ($errors -eq 0) {
    Write-Host "VERIFICACAO COMPLETA - AVISOS" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Avisos encontrados: $warnings" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "O projeto deve funcionar, mas revise os avisos acima." -ForegroundColor Yellow
    
} else {
    Write-Host "VERIFICACAO COMPLETA - ERROS ENCONTRADOS" -ForegroundColor Red
    Write-Host ""
    Write-Host "Erros encontrados: $errors" -ForegroundColor Red
    Write-Host "Avisos encontrados: $warnings" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Acoes recomendadas:" -ForegroundColor Yellow
    Write-Host "  1. Revise os erros listados acima"
    Write-Host "  2. Execute novamente: .\reorganizar_limpo.ps1"
    Write-Host "  3. Execute: .\atualizar_caminhos_limpo.ps1"
    Write-Host "  4. Se necessario, restaure: .\restaurar.ps1"
}

Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Criar relatorio em arquivo
$reportPath = "verificacao_$(Get-Date -Format 'yyyyMMdd_HHmmss').txt"
$report = @"
RELATORIO DE VERIFICACAO - BURGER HOUSE
Data: $(Get-Date -Format 'dd/MM/yyyy HH:mm:ss')

RESULTADO:
- Erros: $errors
- Avisos: $warnings

STATUS: $(if ($errors -eq 0) { "OK" } else { "PROBLEMAS ENCONTRADOS" })
"@

$report | Out-File $reportPath -Encoding UTF8
Write-Host "Relatorio salvo em: $reportPath" -ForegroundColor Cyan
Write-Host ""
