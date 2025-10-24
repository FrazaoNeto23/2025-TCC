# CRIAR .HTACCESS CORRETO - SEM LOOP

Write-Host "Criando .htaccess correto..." -ForegroundColor Green
Write-Host ""

# Criar .htaccess correto na raiz
$htaccessRaiz = @'
# Redirecionar para public/ apenas se nao estiver ja em public/
<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # Se a requisicao NAO for para public/ e o arquivo/pasta nao existir
    RewriteCond %{REQUEST_URI} !^/2025-TCC/public/
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ public/$1 [L]
    
    # Bloquear acesso direto a pastas sensiveis
    RewriteRule ^(config|src|database|backup_original)/ - [F,L]
</IfModule>
'@

Set-Content -Path ".htaccess" -Value $htaccessRaiz -Encoding UTF8
Write-Host "OK - .htaccess criado na raiz" -ForegroundColor Green

# Criar .htaccess na pasta public
$htaccessPublic = @'
# Habilitar rewrite
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /2025-TCC/public/
    
    # Redirecionar tudo para index.php se arquivo nao existir
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule . index.php [L]
</IfModule>

# Permitir acesso
<IfModule mod_authz_core.c>
    Require all granted
</IfModule>
'@

Set-Content -Path "public\.htaccess" -Value $htaccessPublic -Encoding UTF8
Write-Host "OK - .htaccess criado em public/" -ForegroundColor Green

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ".HTACCESS CONFIGURADO!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Agora voce pode acessar de 2 formas:" -ForegroundColor Yellow
Write-Host ""
Write-Host "1. Diretamente:" -ForegroundColor White
Write-Host "   http://localhost/2025-TCC/public/" -ForegroundColor Cyan
Write-Host ""
Write-Host "2. Com redirecionamento automatico:" -ForegroundColor White
Write-Host "   http://localhost/2025-TCC/" -ForegroundColor Cyan
Write-Host "   (vai redirecionar para public/ automaticamente)" -ForegroundColor Gray
Write-Host ""
