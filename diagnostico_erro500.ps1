# DIAGNOSTICO DE ERROS - BURGER HOUSE
# Verifica logs e erros do Apache/PHP

Write-Host "DIAGNOSTICANDO ERRO 500..." -ForegroundColor Red
Write-Host ""

# 1. Verificar log de erros do Apache
Write-Host "1. Verificando logs do Apache..." -ForegroundColor Cyan

$apacheLogs = @(
    "C:\xampp\apache\logs\error.log",
    "C:\wamp\logs\apache_error.log",
    "C:\wamp64\logs\apache_error.log"
)

$logFound = $false
foreach ($log in $apacheLogs) {
    if (Test-Path $log) {
        $logFound = $true
        Write-Host "   Log encontrado: $log" -ForegroundColor Green
        Write-Host ""
        Write-Host "   ULTIMAS 20 LINHAS DO LOG:" -ForegroundColor Yellow
        Write-Host "   ========================================" -ForegroundColor Gray
        Get-Content $log -Tail 20 | ForEach-Object {
            if ($_ -match "error|fatal|warning") {
                Write-Host "   $_" -ForegroundColor Red
            } else {
                Write-Host "   $_" -ForegroundColor Gray
            }
        }
        Write-Host ""
        break
    }
}

if (-not $logFound) {
    Write-Host "   AVISO: Log do Apache nao encontrado" -ForegroundColor Yellow
    Write-Host "   Locais verificados:" -ForegroundColor Gray
    foreach ($log in $apacheLogs) {
        Write-Host "     - $log" -ForegroundColor Gray
    }
}

Write-Host ""

# 2. Verificar sintaxe dos arquivos PHP principais
Write-Host "2. Verificando sintaxe dos arquivos PHP..." -ForegroundColor Cyan
Write-Host ""

$filesToCheck = @(
    "public\index.php",
    "config\paths.php",
    "config\config.php"
)

foreach ($file in $filesToCheck) {
    if (Test-Path $file) {
        Write-Host "   Verificando: $file" -ForegroundColor Gray
        try {
            $result = php -l $file 2>&1
            if ($result -match "No syntax errors") {
                Write-Host "   OK - Sem erros de sintaxe" -ForegroundColor Green
            } else {
                Write-Host "   ERRO - $result" -ForegroundColor Red
            }
        } catch {
            Write-Host "   AVISO - Nao foi possivel verificar" -ForegroundColor Yellow
        }
    } else {
        Write-Host "   AVISO - $file nao encontrado" -ForegroundColor Yellow
    }
}

Write-Host ""

# 3. Verificar .htaccess
Write-Host "3. Verificando .htaccess..." -ForegroundColor Cyan
Write-Host ""

if (Test-Path ".htaccess") {
    Write-Host "   .htaccess existe na raiz" -ForegroundColor Green
    Write-Host ""
    Write-Host "   Conteudo:" -ForegroundColor Yellow
    Get-Content ".htaccess" | ForEach-Object {
        Write-Host "   $_" -ForegroundColor Gray
    }
} else {
    Write-Host "   AVISO - .htaccess nao encontrado" -ForegroundColor Yellow
}

Write-Host ""

# 4. Testar acesso direto ao index
Write-Host "4. Testando acesso direto ao public/index.php..." -ForegroundColor Cyan
Write-Host ""

if (Test-Path "public\index.php") {
    Write-Host "   Arquivo existe: public\index.php" -ForegroundColor Green
    Write-Host ""
    Write-Host "   Primeiras 30 linhas:" -ForegroundColor Yellow
    Get-Content "public\index.php" -TotalCount 30 | ForEach-Object {
        Write-Host "   $_" -ForegroundColor Gray
    }
} else {
    Write-Host "   ERRO - public\index.php NAO ENCONTRADO!" -ForegroundColor Red
}

Write-Host ""

# 5. Verificar paths.php
Write-Host "5. Verificando config/paths.php..." -ForegroundColor Cyan
Write-Host ""

if (Test-Path "config\paths.php") {
    Write-Host "   Arquivo existe: config\paths.php" -ForegroundColor Green
    Write-Host ""
    Write-Host "   Conteudo:" -ForegroundColor Yellow
    Get-Content "config\paths.php" | ForEach-Object {
        Write-Host "   $_" -ForegroundColor Gray
    }
} else {
    Write-Host "   ERRO - config\paths.php NAO ENCONTRADO!" -ForegroundColor Red
}

Write-Host ""

# 6. Verificar permissoes
Write-Host "6. Verificando permissoes das pastas..." -ForegroundColor Cyan
Write-Host ""

$foldersToCheck = @("config", "src", "public", "uploads", "relatorios")
foreach ($folder in $foldersToCheck) {
    if (Test-Path $folder) {
        Write-Host "   OK - $folder existe" -ForegroundColor Green
    } else {
        Write-Host "   ERRO - $folder NAO EXISTE" -ForegroundColor Red
    }
}

Write-Host ""

# SOLUCOES
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "POSSIVEIS SOLUCOES" -ForegroundColor Yellow
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

Write-Host "SOLUCAO 1: Desabilitar .htaccess temporariamente" -ForegroundColor Green
Write-Host "  Renomeie o .htaccess para testar sem ele:" -ForegroundColor White
Write-Host "  Rename-Item .htaccess .htaccess.bak" -ForegroundColor Cyan
Write-Host "  Depois acesse: http://localhost/2025-TCC/public/" -ForegroundColor Cyan
Write-Host ""

Write-Host "SOLUCAO 2: Verificar mod_rewrite do Apache" -ForegroundColor Green
Write-Host "  1. Abra: C:\xampp\apache\conf\httpd.conf" -ForegroundColor White
Write-Host "  2. Procure: #LoadModule rewrite_module" -ForegroundColor White
Write-Host "  3. Remova o # (descomentar)" -ForegroundColor White
Write-Host "  4. Reinicie o Apache" -ForegroundColor White
Write-Host ""

Write-Host "SOLUCAO 3: Acessar diretamente o public/" -ForegroundColor Green
Write-Host "  Tente acessar diretamente:" -ForegroundColor White
Write-Host "  http://localhost/2025-TCC/public/index.php" -ForegroundColor Cyan
Write-Host ""

Write-Host "SOLUCAO 4: Verificar erro especifico no log" -ForegroundColor Green
Write-Host "  Abra o arquivo de log do Apache (caminho acima)" -ForegroundColor White
Write-Host "  Procure pela ultima mensagem de erro" -ForegroundColor White
Write-Host ""

Write-Host "SOLUCAO 5: Criar arquivo de teste simples" -ForegroundColor Green
Write-Host "  Execute: .\criar_teste_php.ps1" -ForegroundColor Cyan
Write-Host ""

Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Criar arquivo de teste
Write-Host "Criando arquivo de teste..." -ForegroundColor Cyan
$testPhp = @'
<?php
echo "PHP FUNCIONANDO!<br>";
echo "Versao do PHP: " . phpversion() . "<br>";
echo "Pasta atual: " . __DIR__ . "<br>";
echo "<hr>";
echo "TESTE CONCLUIDO - Se voce ve esta mensagem, o PHP esta funcionando!";
?>
'@

Set-Content -Path "public\teste.php" -Value $testPhp -Encoding UTF8
Write-Host "Arquivo de teste criado: public\teste.php" -ForegroundColor Green
Write-Host ""
Write-Host "Acesse: http://localhost/2025-TCC/public/teste.php" -ForegroundColor Cyan
Write-Host ""
