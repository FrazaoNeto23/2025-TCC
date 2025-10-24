# ============================================
# SCRIPT DE CORRECAO DE DUPLICATAS
# PowerShell - Windows
# ============================================

Write-Host "Verificando arquivos duplicados..." -ForegroundColor Green
Write-Host ""

# Verificar se existem arquivos duplicados (case-insensitive)
$duplicates = @()

# Verificar pagamento.php vs Pagamento.php
if ((Test-Path "pagamento.php") -and (Test-Path "Pagamento.php")) {
    Write-Host "AVISO: Encontrado pagamento.php e Pagamento.php" -ForegroundColor Yellow
    $duplicates += "pagamento"
}

# Verificar notificacoes.php vs Notificacoes.php
if ((Test-Path "notificacoes.php") -and (Test-Path "Notificacoes.php")) {
    Write-Host "AVISO: Encontrado notificacoes.php e Notificacoes.php" -ForegroundColor Yellow
    $duplicates += "notificacoes"
}

# Verificar outros possíveis duplicados
$possibleDuplicates = @(
    @("gestor_pedidos.php", "GestorPedidos.php"),
    @("fila_impressao.php", "FilaImpressao.php")
)

foreach ($pair in $possibleDuplicates) {
    if ((Test-Path $pair[0]) -and (Test-Path $pair[1])) {
        Write-Host "AVISO: Encontrado $($pair[0]) e $($pair[1])" -ForegroundColor Yellow
        $duplicates += $pair[0] -replace '.php', ''
    }
}

if ($duplicates.Count -eq 0) {
    Write-Host "OK - Nenhuma duplicata encontrada!" -ForegroundColor Green
    Write-Host ""
    Write-Host "Pode executar: .\reorganizar.ps1" -ForegroundColor Cyan
    exit 0
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "RESOLVER DUPLICATAS" -ForegroundColor Yellow
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Analisar e mesclar pagamento.php e Pagamento.php
if ($duplicates -contains "pagamento") {
    Write-Host "Analisando pagamento.php vs Pagamento.php..." -ForegroundColor Cyan
    
    $size1 = (Get-Item "pagamento.php").Length
    $size2 = (Get-Item "Pagamento.php").Length
    $date1 = (Get-Item "pagamento.php").LastWriteTime
    $date2 = (Get-Item "Pagamento.php").LastWriteTime
    
    Write-Host ""
    Write-Host "Arquivo 1: pagamento.php" -ForegroundColor White
    Write-Host "  Tamanho: $size1 bytes" -ForegroundColor Gray
    Write-Host "  Modificado: $date1" -ForegroundColor Gray
    Write-Host ""
    Write-Host "Arquivo 2: Pagamento.php" -ForegroundColor White
    Write-Host "  Tamanho: $size2 bytes" -ForegroundColor Gray
    Write-Host "  Modificado: $date2" -ForegroundColor Gray
    Write-Host ""
    
    # Comparar conteúdo
    $content1 = Get-Content "pagamento.php" -Raw -ErrorAction SilentlyContinue
    $content2 = Get-Content "Pagamento.php" -Raw -ErrorAction SilentlyContinue
    
    if ($content1 -eq $content2) {
        Write-Host "OK - Arquivos IDENTICOS - Mantendo apenas Pagamento.php" -ForegroundColor Green
        Remove-Item "pagamento.php" -Force
        Write-Host "  pagamento.php removido" -ForegroundColor Gray
    }
    else {
        Write-Host "AVISO: Arquivos DIFERENTES!" -ForegroundColor Yellow
        Write-Host ""
        Write-Host "Escolha uma opcao:" -ForegroundColor Cyan
        Write-Host "  1 - Manter Pagamento.php (mais recente/maior)" -ForegroundColor White
        Write-Host "  2 - Manter pagamento.php" -ForegroundColor White
        Write-Host "  3 - Criar backup de ambos e manter Pagamento.php" -ForegroundColor White
        Write-Host ""
        
        $choice = Read-Host "Opcao (1-3)"
        
        switch ($choice) {
            "1" {
                Remove-Item "pagamento.php" -Force
                Write-Host "OK - pagamento.php removido, mantendo Pagamento.php" -ForegroundColor Green
            }
            "2" {
                Remove-Item "Pagamento.php" -Force
                Rename-Item "pagamento.php" "Pagamento.php" -Force
                Write-Host "OK - Pagamento.php removido, renomeando pagamento.php" -ForegroundColor Green
            }
            "3" {
                # Criar pasta de backup se não existir
                if (-not (Test-Path "backup_duplicatas")) {
                    New-Item -ItemType Directory -Path "backup_duplicatas" -Force | Out-Null
                }
                Copy-Item "pagamento.php" "backup_duplicatas\pagamento_minusculo.php" -Force
                Copy-Item "Pagamento.php" "backup_duplicatas\Pagamento_maiusculo.php" -Force
                Remove-Item "pagamento.php" -Force
                Write-Host "OK - Ambos salvos em backup_duplicatas\" -ForegroundColor Green
                Write-Host "OK - Mantendo Pagamento.php no projeto" -ForegroundColor Green
            }
            default {
                Write-Host "ERRO: Opcao invalida. Execute o script novamente." -ForegroundColor Red
                exit 1
            }
        }
    }
}

# Fazer o mesmo para notificacoes
if ($duplicates -contains "notificacoes") {
    Write-Host ""
    Write-Host "Analisando notificacoes.php vs Notificacoes.php..." -ForegroundColor Cyan
    
    $content1 = Get-Content "notificacoes.php" -Raw -ErrorAction SilentlyContinue
    $content2 = Get-Content "Notificacoes.php" -Raw -ErrorAction SilentlyContinue
    
    if ($content1 -eq $content2) {
        Write-Host "OK - Arquivos IDENTICOS - Mantendo apenas Notificacoes.php" -ForegroundColor Green
        Remove-Item "notificacoes.php" -Force
        Write-Host "  notificacoes.php removido" -ForegroundColor Gray
    }
    else {
        Write-Host "AVISO: Arquivos diferentes - removendo minusculo, mantendo Notificacoes.php" -ForegroundColor Yellow
        Remove-Item "notificacoes.php" -Force
    }
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "DUPLICATAS RESOLVIDAS!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Proximo passo:" -ForegroundColor Yellow
Write-Host "  .\reorganizar.ps1" -ForegroundColor Cyan
Write-Host ""
