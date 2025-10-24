# CORRIGIR INDEX.PHP EM PUBLIC/
# Remove redirecionamento que causa loop

Write-Host "Corrigindo index.php em public/..." -ForegroundColor Green
Write-Host ""

if (Test-Path "public\index.php") {
    # Ler conteudo atual
    $content = Get-Content "public\index.php" -Raw
    
    Write-Host "Conteudo atual:" -ForegroundColor Yellow
    Write-Host $content.Substring(0, [Math]::Min(200, $content.Length))
    Write-Host "..."
    Write-Host ""
    
    # Remover linhas de redirecionamento
    $content = $content -replace "header\('Location:.*\);?", ""
    $content = $content -replace "header\(`"Location:.*`"\);?", ""
    $content = $content -replace "^\s*exit;\s*$", ""
    
    # Limpar linhas vazias extras
    $content = $content -replace "(\r?\n){3,}", "`r`n`r`n"
    
    # Se ficou muito pequeno, o arquivo original foi perdido
    if ($content.Length -lt 100) {
        Write-Host "AVISO: Arquivo muito pequeno, vamos restaurar do backup..." -ForegroundColor Yellow
        
        if (Test-Path "backup_original\index.php") {
            Copy-Item "backup_original\index.php" "public\index.php" -Force
            Write-Host "OK - Restaurado do backup" -ForegroundColor Green
            
            # Adicionar require do paths.php
            $content = Get-Content "public\index.php" -Raw
            if ($content -notmatch "paths\.php") {
                $content = $content -replace "(<\?php)", "`$1`r`nrequire_once __DIR__ . '/../config/paths.php';"
            }
        } else {
            Write-Host "ERRO: Backup nao encontrado!" -ForegroundColor Red
            Write-Host "Criando index.php basico..." -ForegroundColor Yellow
            
            $content = @'
<?php
require_once __DIR__ . '/../config/paths.php';

// Redirecionar para login se nao estiver logado
session_start();

if (!isset($_SESSION['usuario_id'])) {
    // Mostrar pagina de login
    echo "<!DOCTYPE html>";
    echo "<html><head><title>Burger House</title></head>";
    echo "<body>";
    echo "<h1>Bem-vindo ao Burger House</h1>";
    echo "<p>Sistema em funcionamento!</p>";
    echo "<p><a href='login.php'>Fazer Login</a></p>";
    echo "</body></html>";
} else {
    // Ja logado - redirecionar para painel
    header('Location: painel_cliente.php');
    exit;
}
?>
'@
        }
    }
    
    # Garantir que tem o require do paths.php
    if ($content -notmatch "paths\.php") {
        $content = $content -replace "(<\?php)", "`$1`r`nrequire_once __DIR__ . '/../config/paths.php';"
    }
    
    # Salvar
    Set-Content "public\index.php" -Value $content -Encoding UTF8 -NoNewline
    
    Write-Host ""
    Write-Host "OK - index.php corrigido!" -ForegroundColor Green
    Write-Host ""
    Write-Host "Novo conteudo (primeiras linhas):" -ForegroundColor Yellow
    Get-Content "public\index.php" -TotalCount 15 | ForEach-Object {
        Write-Host "  $_" -ForegroundColor Gray
    }
    
} else {
    Write-Host "ERRO: public\index.php nao encontrado!" -ForegroundColor Red
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "AGORA TESTE:" -ForegroundColor Green
Write-Host "http://localhost/2025-TCC/public/" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
