# RESTAURAR E REORGANIZAR CORRETAMENTE

Write-Host "RESTAURANDO E REORGANIZANDO..." -ForegroundColor Green
Write-Host ""

# 1. Deletar pastas criadas (exceto backup)
Write-Host "1. Limpando estrutura atual..." -ForegroundColor Cyan

$foldersToRemove = @("config", "src", "public", "api", "assets", "views", "database")
foreach ($folder in $foldersToRemove) {
    if (Test-Path $folder) {
        Remove-Item $folder -Recurse -Force -ErrorAction SilentlyContinue
        Write-Host "   Removido: $folder" -ForegroundColor Gray
    }
}

# Remover arquivos da raiz
if (Test-Path "index.php") {
    Remove-Item "index.php" -Force -ErrorAction SilentlyContinue
}

Write-Host "   OK - Limpeza concluida" -ForegroundColor Green
Write-Host ""

# 2. Restaurar do backup
Write-Host "2. Restaurando arquivos do backup..." -ForegroundColor Cyan

if (Test-Path "backup_original") {
    Get-ChildItem "backup_original\*.php" | ForEach-Object {
        Copy-Item $_.FullName -Destination "." -Force
        Write-Host "   Restaurado: $($_.Name)" -ForegroundColor Gray
    }
    Write-Host "   OK - Arquivos restaurados" -ForegroundColor Green
} else {
    Write-Host "   ERRO - Pasta backup_original nao encontrada!" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "RESTAURACAO CONCLUIDA!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Agora voce tem 2 opcoes:" -ForegroundColor Yellow
Write-Host ""
Write-Host "OPCAO 1: Usar SEM reorganizar (mais rapido)" -ForegroundColor White
Write-Host "  Acesse: http://localhost/2025-TCC/index.php" -ForegroundColor Cyan
Write-Host "  (usa a estrutura original)" -ForegroundColor Gray
Write-Host ""
Write-Host "OPCAO 2: Reorganizar novamente (corretamente)" -ForegroundColor White
Write-Host "  Execute: .\reorganizar_limpo.ps1" -ForegroundColor Cyan
Write-Host "  (mas dessa vez vou corrigir o script antes)" -ForegroundColor Gray
Write-Host ""
