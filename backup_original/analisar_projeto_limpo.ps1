# SCRIPT DE ANALISE AUTOMATICA - BURGER HOUSE
# Detecta e lista TODOS os arquivos PHP

Write-Host "ANALISANDO ESTRUTURA ATUAL DO PROJETO..." -ForegroundColor Green
Write-Host ""

# Coletar todos os arquivos PHP na raiz
$phpFiles = Get-ChildItem -Path . -Filter "*.php" -File | Where-Object { $_.Name -ne "index.php" }

if ($phpFiles.Count -eq 0) {
    Write-Host "OK - Nenhum arquivo PHP na raiz (ja organizado?)" -ForegroundColor Green
    exit 0
}

Write-Host "Arquivos PHP encontrados na raiz:" -ForegroundColor Cyan
Write-Host ""

# Agrupar por tipo
$paineis = @()
$paginas = @()
$classes = @()
$apis = @()
$outros = @()

foreach ($file in $phpFiles) {
    $name = $file.Name.ToLower()
    
    if ($name -match "painel_") {
        $paineis += $file
    }
    elseif ($name -match "^(carrinho|cardapio|pedidos|finalizar|editar|funcionario|logout|teste|login|cadastro|processar)") {
        $paginas += $file
    }
    elseif ($name -match "^(api_|confirmar_|validar_|pagamento_)") {
        $apis += $file
    }
    elseif ($name -match "^[A-Z]|^(notificacoes|pagamento|gestor|fila|relatorios)") {
        $classes += $file
    }
    else {
        $outros += $file
    }
}

# Exibir categorizacao
if ($paineis.Count -gt 0) {
    Write-Host "PAINEIS ($($paineis.Count)):" -ForegroundColor Yellow
    foreach ($f in $paineis) {
        Write-Host "   -> $($f.Name)" -ForegroundColor White
    }
    Write-Host ""
}

if ($paginas.Count -gt 0) {
    Write-Host "PAGINAS ($($paginas.Count)):" -ForegroundColor Yellow
    foreach ($f in $paginas) {
        Write-Host "   -> $($f.Name)" -ForegroundColor White
    }
    Write-Host ""
}

if ($classes.Count -gt 0) {
    Write-Host "CLASSES ($($classes.Count)):" -ForegroundColor Yellow
    foreach ($f in $classes) {
        Write-Host "   -> $($f.Name)" -ForegroundColor White
    }
    Write-Host ""
}

if ($apis.Count -gt 0) {
    Write-Host "APIs ($($apis.Count)):" -ForegroundColor Yellow
    foreach ($f in $apis) {
        Write-Host "   -> $($f.Name)" -ForegroundColor White
    }
    Write-Host ""
}

if ($outros.Count -gt 0) {
    Write-Host "OUTROS ($($outros.Count)):" -ForegroundColor Yellow
    foreach ($f in $outros) {
        Write-Host "   -> $($f.Name)" -ForegroundColor White
    }
    Write-Host ""
}

# Verificar arquivos de configuracao
Write-Host "CONFIGURACOES:" -ForegroundColor Cyan
$configs = @("config.php", "config_seguro.php", "conexao.php", "database.php")
foreach ($cfg in $configs) {
    if (Test-Path $cfg) {
        Write-Host "   -> $cfg" -ForegroundColor White
    }
}
Write-Host ""

# Verificar pastas
Write-Host "PASTAS EXISTENTES:" -ForegroundColor Cyan
$folders = Get-ChildItem -Directory | Where-Object { $_.Name -notmatch "^(vendor|node_modules|\.)" }
foreach ($folder in $folders) {
    $fileCount = (Get-ChildItem $folder.FullName -Recurse -File -ErrorAction SilentlyContinue).Count
    Write-Host "   -> $($folder.Name)\ ($fileCount arquivos)" -ForegroundColor White
}
Write-Host ""

# Estatisticas
$totalPhp = $phpFiles.Count
$totalFolders = $folders.Count

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "RESUMO" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Arquivos PHP na raiz: $totalPhp"
Write-Host "  Paineis: $($paineis.Count)"
Write-Host "  Paginas: $($paginas.Count)"
Write-Host "  Classes: $($classes.Count)"
Write-Host "  APIs: $($apis.Count)"
Write-Host "  Outros: $($outros.Count)"
Write-Host "  Pastas existentes: $totalFolders"
Write-Host ""

# Gerar arquivo com lista completa
$reportFile = "analise_projeto.txt"
$report = @"
ANALISE DO PROJETO BURGER HOUSE
Data: $(Get-Date -Format 'dd/MM/yyyy HH:mm:ss')

PAINEIS ($($paineis.Count)):
$($paineis | ForEach-Object { "  -> $($_.Name)" } | Out-String)

PAGINAS ($($paginas.Count)):
$($paginas | ForEach-Object { "  -> $($_.Name)" } | Out-String)

CLASSES ($($classes.Count)):
$($classes | ForEach-Object { "  -> $($_.Name)" } | Out-String)

APIs ($($apis.Count)):
$($apis | ForEach-Object { "  -> $($_.Name)" } | Out-String)

OUTROS ($($outros.Count)):
$($outros | ForEach-Object { "  -> $($_.Name)" } | Out-String)

TOTAL: $totalPhp arquivos PHP na raiz

RECOMENDACAO:

Execute na ordem:
1. .\corrigir_duplicatas_limpo.ps1  (se tiver duplicatas)
2. .\reorganizar_limpo.ps1           (reorganizar estrutura)
3. .\atualizar_caminhos_limpo.ps1    (atualizar requires)
"@

$report | Out-File $reportFile -Encoding UTF8

Write-Host "Relatorio salvo em: $reportFile" -ForegroundColor Green
Write-Host ""
Write-Host "Proximos passos:" -ForegroundColor Yellow
Write-Host ""

if ($paineis.Count -gt 0 -or $paginas.Count -gt 0) {
    Write-Host "  1. Execute: .\corrigir_duplicatas_limpo.ps1" -ForegroundColor Cyan
    Write-Host "     (para verificar duplicatas)" -ForegroundColor Gray
    Write-Host ""
    Write-Host "  2. Execute: .\reorganizar_limpo.ps1" -ForegroundColor Cyan
    Write-Host "     (vai mover todos os $totalPhp arquivos)" -ForegroundColor Gray
    Write-Host ""
    Write-Host "  3. Execute: .\atualizar_caminhos_limpo.ps1" -ForegroundColor Cyan
    Write-Host "     (vai atualizar os requires)" -ForegroundColor Gray
} else {
    Write-Host "  OK - Projeto ja parece organizado!" -ForegroundColor Green
}

Write-Host ""
