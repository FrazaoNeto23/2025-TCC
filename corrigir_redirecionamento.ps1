# CORRECAO DE PROBLEMAS - Loop de Redirecionamento

Write-Host "Corrigindo problemas de redirecionamento..." -ForegroundColor Green
Write-Host ""

# 1. Remover .htaccess temporariamente
Write-Host "1. Desabilitando .htaccess..." -ForegroundColor Cyan
if (Test-Path ".htaccess") {
    Rename-Item ".htaccess" ".htaccess.DESABILITADO" -Force
    Write-Host "   OK - .htaccess desabilitado" -ForegroundColor Green
}

# 2. Corrigir index.php da raiz
Write-Host ""
Write-Host "2. Corrigindo index.php da raiz..." -ForegroundColor Cyan
$indexRaiz = @'
<?php
// Redirecionar para a pasta public
header('Location: public/');
exit;
?>
'@
Set-Content -Path "index.php" -Value $indexRaiz -Encoding UTF8
Write-Host "   OK - index.php da raiz corrigido" -ForegroundColor Green

# 3. Verificar se existe backup do index.php original
Write-Host ""
Write-Host "3. Procurando index.php original no backup..." -ForegroundColor Cyan

if (Test-Path "backup_original\index.php") {
    Write-Host "   OK - Backup encontrado!" -ForegroundColor Green
    
    # Copiar o index.php original para public/
    Copy-Item "backup_original\index.php" "public\index.php" -Force
    Write-Host "   OK - index.php original copiado para public/" -ForegroundColor Green
    
    # Adicionar require do paths.php no inicio
    $content = Get-Content "public\index.php" -Raw
    
    if ($content -notmatch "paths\.php") {
        $content = $content -replace "(<\?php)", "`$1`r`nrequire_once __DIR__ . '/../config/paths.php';"
        Set-Content "public\index.php" -Value $content -Encoding UTF8 -NoNewline
        Write-Host "   OK - require paths.php adicionado" -ForegroundColor Green
    }
} else {
    Write-Host "   AVISO - Backup nao encontrado" -ForegroundColor Yellow
    Write-Host "   Vamos criar um index.php basico..." -ForegroundColor Yellow
    
    $basicIndex = @'
<?php
require_once __DIR__ . '/../config/paths.php';
require_once CONFIG_PATH . '/config.php';

// Seu codigo do index aqui
echo "Burger House - Sistema funcionando!";
?>
'@
    Set-Content "public\index.php" -Value $basicIndex -Encoding UTF8
    Write-Host "   OK - index.php basico criado" -ForegroundColor Green
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "CORRECAO CONCLUIDA!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Agora tente acessar:" -ForegroundColor Yellow
Write-Host "  http://localhost/2025-TCC/public/" -ForegroundColor Cyan
Write-Host ""
Write-Host "OU diretamente:" -ForegroundColor Yellow
Write-Host "  http://localhost/2025-TCC/public/index.php" -ForegroundColor Cyan
Write-Host ""
Write-Host "Se funcionar, podemos reativar o .htaccess depois!" -ForegroundColor Green
Write-Host ""
