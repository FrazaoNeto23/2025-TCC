# ============================================
# MENU INTERATIVO - BURGER HOUSE
# Facilita a execucao dos scripts
# ============================================

function Show-Menu {
    Clear-Host
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "   BURGER HOUSE - REORGANIZACAO" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Escolha uma opcao:" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "  [1] Analisar Projeto" -ForegroundColor White
    Write-Host "      Ver todos os arquivos e estrutura atual" -ForegroundColor Gray
    Write-Host ""
    Write-Host "  [2] Corrigir Duplicatas" -ForegroundColor White
    Write-Host "      Resolver arquivos com nomes duplicados" -ForegroundColor Gray
    Write-Host ""
    Write-Host "  [3] Reorganizar Estrutura" -ForegroundColor White
    Write-Host "      Mover arquivos para pastas corretas" -ForegroundColor Gray
    Write-Host ""
    Write-Host "  [4] Atualizar Caminhos" -ForegroundColor White
    Write-Host "      Corrigir requires e includes" -ForegroundColor Gray
    Write-Host ""
    Write-Host "  [5] Verificar Sistema" -ForegroundColor White
    Write-Host "      Checar se esta tudo funcionando" -ForegroundColor Gray
    Write-Host ""
    Write-Host "  [6] Executar Tudo (Automatico)" -ForegroundColor Green
    Write-Host "      Faz todo o processo automaticamente" -ForegroundColor Gray
    Write-Host ""
    Write-Host "  [7] Restaurar Backup" -ForegroundColor Yellow
    Write-Host "      Voltar ao estado original" -ForegroundColor Gray
    Write-Host ""
    Write-Host "  [0] Sair" -ForegroundColor Red
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
}

function Pause {
    Write-Host ""
    Write-Host "Pressione qualquer tecla para continuar..." -ForegroundColor Gray
    $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
}

function Execute-Script {
    param([string]$ScriptName, [string]$Description)
    
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "  $Description" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""
    
    if (Test-Path $ScriptName) {
        & ".\$ScriptName"
    } else {
        Write-Host "ERRO: Script $ScriptName nao encontrado!" -ForegroundColor Red
    }
    
    Pause
}

# Loop principal
do {
    Show-Menu
    $choice = Read-Host "Digite o numero da opcao"
    
    switch ($choice) {
        "1" {
            Execute-Script "analisar_projeto_limpo.ps1" "Analisando Projeto"
        }
        "2" {
            Execute-Script "corrigir_duplicatas_limpo.ps1" "Corrigindo Duplicatas"
        }
        "3" {
            Execute-Script "reorganizar_limpo.ps1" "Reorganizando Estrutura"
        }
        "4" {
            Execute-Script "atualizar_caminhos_limpo.ps1" "Atualizando Caminhos"
        }
        "5" {
            Execute-Script "verificar_limpo.ps1" "Verificando Sistema"
        }
        "6" {
            Write-Host ""
            Write-Host "========================================" -ForegroundColor Cyan
            Write-Host " EXECUCAO AUTOMATICA COMPLETA" -ForegroundColor Green
            Write-Host "========================================" -ForegroundColor Cyan
            Write-Host ""
            Write-Host "Serao executados na ordem:" -ForegroundColor Yellow
            Write-Host "  1. Analisar Projeto" -ForegroundColor Gray
            Write-Host "  2. Corrigir Duplicatas" -ForegroundColor Gray
            Write-Host "  3. Reorganizar Estrutura" -ForegroundColor Gray
            Write-Host "  4. Atualizar Caminhos" -ForegroundColor Gray
            Write-Host "  5. Verificar Sistema" -ForegroundColor Gray
            Write-Host ""
            $confirm = Read-Host "Confirma? (S/N)"
            
            if ($confirm -eq "S" -or $confirm -eq "s") {
                Execute-Script "corrigir_duplicatas_limpo.ps1" "1/4 - Corrigindo Duplicatas"
                Execute-Script "reorganizar_limpo.ps1" "2/4 - Reorganizando Estrutura"
                Execute-Script "atualizar_caminhos_limpo.ps1" "3/4 - Atualizando Caminhos"
                
                Write-Host ""
                Write-Host "========================================" -ForegroundColor Cyan
                Write-Host " PROCESSO COMPLETO!" -ForegroundColor Green
                Write-Host "========================================" -ForegroundColor Cyan
                Write-Host ""
                Write-Host "Teste agora no navegador:" -ForegroundColor Yellow
                Write-Host "  http://localhost/2025-TCC/" -ForegroundColor Cyan
                Write-Host ""
                
                $openBrowser = Read-Host "Abrir no navegador? (S/N)"
                if ($openBrowser -eq "S" -or $openBrowser -eq "s") {
                    Start-Process "http://localhost/2025-TCC/"
                }
                
                Pause
            } else {
                Write-Host "Cancelado pelo usuario." -ForegroundColor Yellow
                Pause
            }
        }
        "7" {
            Write-Host ""
            Write-Host "ATENCAO: Isso vai desfazer a reorganizacao!" -ForegroundColor Yellow
            Write-Host ""
            $confirm = Read-Host "Confirma restauracao? (S/N)"
            
            if ($confirm -eq "S" -or $confirm -eq "s") {
                Execute-Script "restaurar.ps1" "Restaurando Backup"
            } else {
                Write-Host "Restauracao cancelada." -ForegroundColor Yellow
                Pause
            }
        }
        "0" {
            Write-Host ""
            Write-Host "Ate logo!" -ForegroundColor Green
            Write-Host ""
            exit
        }
        default {
            Write-Host ""
            Write-Host "Opcao invalida! Tente novamente." -ForegroundColor Red
            Pause
        }
    }
} while ($true)
