# SCRIPT DE ATUALIZACAO DE CAMINHOS
# PowerShell - Windows

Write-Host "Atualizando caminhos nos arquivos PHP..." -ForegroundColor Green
Write-Host ""

function Update-FileContent {
    param(
        [string]$FilePath,
        [hashtable]$Replacements
    )
    
    if (Test-Path $FilePath) {
        $content = Get-Content $FilePath -Raw -Encoding UTF8
        $modified = $false
        
        foreach ($old in $Replacements.Keys) {
            if ($content -match [regex]::Escape($old)) {
                $content = $content -replace [regex]::Escape($old), $Replacements[$old]
                $modified = $true
            }
        }
        
        if ($modified) {
            Set-Content -Path $FilePath -Value $content -Encoding UTF8 -NoNewline
            return $true
        }
    }
    return $false
}

function Add-RequireAtStart {
    param(
        [string]$FilePath,
        [string]$RequireLine
    )
    
    if (Test-Path $FilePath) {
        $content = Get-Content $FilePath -Raw -Encoding UTF8
        
        if ($content -notmatch [regex]::Escape("paths.php")) {
            if ($content -match "^\s*<\?php") {
                $content = $content -replace "(<\?php)", "`$1`r`n$RequireLine"
                Set-Content -Path $FilePath -Value $content -Encoding UTF8 -NoNewline
                return $true
            }
        }
    }
    return $false
}

# Atualizar arquivos em public/
Write-Host "Atualizando arquivos publicos..." -ForegroundColor Cyan

$publicFiles = Get-ChildItem -Path "public\*.php" -ErrorAction SilentlyContinue

foreach ($file in $publicFiles) {
    Write-Host "  Processando: $($file.Name)" -ForegroundColor Gray
    
    $requireLine = "require_once __DIR__ . '/../config/paths.php';"
    Add-RequireAtStart -FilePath $file.FullName -RequireLine $requireLine | Out-Null
    
    $replacements = @{
        'include "config.php"' = 'require_once CONFIG_PATH . "/config.php"'
        'include "config_seguro.php"' = 'require_once CONFIG_PATH . "/config.php"'
        "require_once 'config.php'" = 'require_once CONFIG_PATH . "/config.php"'
        "require_once 'notificacoes.php'" = '// Autoload carrega automaticamente'
        "require_once 'gestor_pedidos.php'" = '// Autoload carrega automaticamente'
        "require_once 'fila_impressao.php'" = '// Autoload carrega automaticamente'
        'include "../config.php"' = 'require_once CONFIG_PATH . "/config.php"'
        'require_once "../config.php"' = 'require_once CONFIG_PATH . "/config.php"'
    }
    
    if (Update-FileContent -FilePath $file.FullName -Replacements $replacements) {
        Write-Host "  OK - $($file.Name) atualizado" -ForegroundColor Green
    } else {
        Write-Host "  - $($file.Name) (sem alteracoes)" -ForegroundColor DarkGray
    }
}

Write-Host "OK - Arquivos publicos processados" -ForegroundColor Green
Write-Host ""

# Atualizar arquivos de API
Write-Host "Atualizando arquivos de API..." -ForegroundColor Cyan

$apiFiles = Get-ChildItem -Path "api\*.php" -ErrorAction SilentlyContinue

foreach ($file in $apiFiles) {
    Write-Host "  Processando: $($file.Name)" -ForegroundColor Gray
    
    $requireLine = "require_once __DIR__ . '/../config/paths.php';"
    Add-RequireAtStart -FilePath $file.FullName -RequireLine $requireLine | Out-Null
    
    $replacements = @{
        'include "config_seguro.php"' = 'require_once CONFIG_PATH . "/config.php"'
        'include "../config_seguro.php"' = 'require_once CONFIG_PATH . "/config.php"'
        'include "Pagamento.php"' = '// Autoload carrega automaticamente'
        'require_once "Pagamento.php"' = '// Autoload carrega automaticamente'
        'include "../Pagamento.php"' = '// Autoload carrega automaticamente'
    }
    
    if (Update-FileContent -FilePath $file.FullName -Replacements $replacements) {
        Write-Host "  OK - $($file.Name) atualizado" -ForegroundColor Green
    } else {
        Write-Host "  - $($file.Name) (sem alteracoes)" -ForegroundColor DarkGray
    }
}

Write-Host "OK - APIs processadas" -ForegroundColor Green
Write-Host ""

# Atualizar config/config.php
Write-Host "Atualizando configuracoes..." -ForegroundColor Cyan

if (Test-Path "config\config.php") {
    $replacements = @{
        "define('UPLOAD_DIR', 'uploads/')" = "define('UPLOAD_DIR', UPLOADS_PATH . '/')"
    }
    
    if (Update-FileContent -FilePath "config\config.php" -Replacements $replacements) {
        Write-Host "  OK - config.php atualizado" -ForegroundColor Green
    } else {
        Write-Host "  - config.php (sem alteracoes)" -ForegroundColor DarkGray
    }
}

Write-Host "OK - Configuracoes processadas" -ForegroundColor Green
Write-Host ""

# Atualizar classes em src/classes/
Write-Host "Atualizando classes..." -ForegroundColor Cyan

$classFiles = Get-ChildItem -Path "src\classes\*.php" -ErrorAction SilentlyContinue

foreach ($file in $classFiles) {
    Write-Host "  Processando: $($file.Name)" -ForegroundColor Gray
    
    $content = Get-Content $file.FullName -Raw -Encoding UTF8
    
    if ($content -match 'require.*config' -and $content -notmatch 'paths\.php') {
        $requireLine = "require_once __DIR__ . '/../../config/paths.php';"
        Add-RequireAtStart -FilePath $file.FullName -RequireLine $requireLine | Out-Null
        
        $replacements = @{
            'require_once "config.php"' = 'require_once CONFIG_PATH . "/config.php"'
            'require_once "../config.php"' = 'require_once CONFIG_PATH . "/config.php"'
            'require_once "../../config.php"' = 'require_once CONFIG_PATH . "/config.php"'
            'include "config.php"' = 'require_once CONFIG_PATH . "/config.php"'
        }
        
        if (Update-FileContent -FilePath $file.FullName -Replacements $replacements) {
            Write-Host "  OK - $($file.Name) atualizado" -ForegroundColor Green
        }
    } else {
        Write-Host "  - $($file.Name) (sem alteracoes)" -ForegroundColor DarkGray
    }
}

Write-Host "OK - Classes processadas" -ForegroundColor Green
Write-Host ""

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "ATUALIZACAO DE CAMINHOS CONCLUIDA!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Proximos passos:" -ForegroundColor Yellow
Write-Host "  1. Teste o sistema no navegador: http://localhost/2025-TCC/" -ForegroundColor White
Write-Host "  2. Verifique erros no console do PHP" -ForegroundColor White
Write-Host "  3. Ajuste manualmente se necessario" -ForegroundColor White
Write-Host ""
