// ========================================
// SISTEMA DE NOTIFICAÇÕES EM TEMPO REAL - JAVASCRIPT
// FASE 2 - ITEM 14 (Frontend)
// ========================================

class NotificacoesRealTime {
    constructor() {
        this.interval = null;
        this.intervalTime = 5000; // 5 segundos
        this.isActive = false;
        this.lastCheck = null;
        this.audio = null;
        this.initializeAudio();
        this.createNotificationContainer();
    }

    /**
     * Inicializar áudio para notificações
     */
    initializeAudio() {
        // Criar áudio para notificação
        this.audio = new Audio('data:audio/mpeg;base64,SUQzBAAAAAABEVRYWFgAAAAtAAADY29tbWVudABCaWdTb3VuZEJhbmsuY29tIC8gTGFTb25vdGhlcXVlLm9yZwBURU5DAAAAHQAAAFN3aXRjaCBQbHVzIMKpIE5DSCBTb2Z0d2FyZQBUSVQyAAAABgAAAzIyMzUAVFNTRQAAAA8AAANMYXZmNTcuODMuMTAwAAAAAAAAAAAAAAD/80DEAAAAA0gAAAAATEFNRTMuMTAwVVVVVVVVVVVVVUxBTUUzLjEwMFVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVf/zQsQAAAAAAzAAAAB4ADQ==');
        this.audio.volume = 0.3;
    }

    /**
     * Criar container para notificações
     */
    createNotificationContainer() {
        // Verificar se já existe
        if (document.getElementById('notifications-container')) {
            return;
        }

        const container = document.createElement('div');
        container.id = 'notifications-container';
        container.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 350px;
            width: 100%;
        `;

        document.body.appendChild(container);

        // Criar sino de notificações se não existir
        this.createNotificationBell();
    }

    /**
     * Criar sino de notificações
     */
    createNotificationBell() {
        // Procurar por elemento existente
        let bell = document.getElementById('notification-bell');

        if (!bell) {
            // Criar novo sino se não existir
            bell = document.createElement('div');
            bell.id = 'notification-bell';
            bell.innerHTML = `
                <button class="btn btn-outline-light position-relative" onclick="notificacoes.toggleDropdown()">
                    <i class="fas fa-bell"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notification-count" style="display: none;">
                        0
                    </span>
                </button>
                <div class="dropdown-menu dropdown-menu-end" id="notifications-dropdown" style="display: none; width: 350px; max-height: 400px; overflow-y: auto;">
                    <div class="dropdown-header d-flex justify-content-between">
                        <span>Notificações</span>
                        <button class="btn btn-sm btn-link p-0" onclick="notificacoes.marcarTodasLidas()">
                            Marcar todas como lidas
                        </button>
                    </div>
                    <div class="dropdown-divider"></div>
                    <div id="notifications-list">
                        <div class="dropdown-item text-center text-muted">
                            Nenhuma notificação
                        </div>
                    </div>
                </div>
            `;

            // Adicionar à navbar se existir
            const navbar = document.querySelector('.navbar .navbar-nav');
            if (navbar) {
                const li = document.createElement('li');
                li.className = 'nav-item dropdown';
                li.appendChild(bell);
                navbar.appendChild(li);
            }
        }
    }

    /**
     * Iniciar sistema de notificações
     */
    start() {
        if (this.isActive) {
            return;
        }

        this.isActive = true;
        this.checkNotifications();
        this.interval = setInterval(() => {
            this.checkNotifications();
        }, this.intervalTime);

        console.log('Sistema de notificações iniciado');
    }

    /**
     * Parar sistema de notificações
     */
    stop() {
        if (this.interval) {
            clearInterval(this.interval);
            this.interval = null;
        }
        this.isActive = false;

        console.log('Sistema de notificações parado');
    }

    /**
     * Verificar novas notificações
     */
    async checkNotifications() {
        try {
            const response = await fetch('notificacoes.php?action=buscar_notificacoes');
            const data = await response.json();

            if (data.erro) {
                console.error('Erro ao buscar notificações:', data.erro);
                return;
            }

            // Atualizar contador
            this.updateNotificationCount(data.total_nao_lidas);

            // Processar notificações
            if (data.notificacoes && data.notificacoes.length > 0) {
                this.processNotifications(data.notificacoes);
            }

        } catch (error) {
            console.error('Erro na requisição de notificações:', error);
        }
    }

    /**
     * Processar notificações recebidas
     */
    processNotifications(notificacoes) {
        notificacoes.forEach(notificacao => {
            // Verificar se é nova (comparar com último check)
            const dataNotificacao = new Date(notificacao.data_criacao);

            if (!this.lastCheck || dataNotificacao > this.lastCheck) {
                this.showToastNotification(notificacao);
                this.playNotificationSound(notificacao.prioridade);
            }
        });

        // Atualizar dropdown
        this.updateNotificationsDropdown(notificacoes);

        // Atualizar último check
        this.lastCheck = new Date();
    }

    /**
     * Mostrar notificação toast
     */
    showToastNotification(notificacao) {
        const toast = document.createElement('div');
        toast.className = `alert alert-${this.getPriorityColor(notificacao.prioridade)} alert-dismissible fade show notification-toast`;
        toast.style.cssText = `
            margin-bottom: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            animation: slideInRight 0.5s ease-out;
        `;

        toast.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fas fa-${notificacao.icone || 'bell'} me-2"></i>
                <div class="flex-grow-1">
                    <strong>${notificacao.titulo}</strong>
                    <div class="small">${notificacao.mensagem}</div>
                    <div class="small text-muted">${this.formatarTempo(notificacao.data_criacao)}</div>
                </div>
                <button type="button" class="btn-close" onclick="this.parentElement.parentElement.remove()"></button>
            </div>
        `;

        // Adicionar click handler se tem link
        if (notificacao.link) {
            toast.style.cursor = 'pointer';
            toast.addEventListener('click', (e) => {
                if (e.target.classList.contains('btn-close')) return;
                window.location.href = notificacao.link;
            });
        }

        document.getElementById('notifications-container').appendChild(toast);

        // Remover automaticamente após 8 segundos
        setTimeout(() => {
            if (toast.parentElement) {
                toast.style.animation = 'slideOutRight 0.5s ease-in';
                setTimeout(() => toast.remove(), 500);
            }
        }, 8000);
    }

    /**
     * Tocar som de notificação
     */
    playNotificationSound(prioridade) {
        if (!this.audio) return;

        try {
            this.audio.currentTime = 0;

            // Ajustar volume baseado na prioridade
            switch (prioridade) {
                case 'urgente':
                    this.audio.volume = 0.8;
                    break;
                case 'alta':
                    this.audio.volume = 0.6;
                    break;
                case 'media':
                    this.audio.volume = 0.4;
                    break;
                default:
                    this.audio.volume = 0.2;
            }

            this.audio.play().catch(e => {
                console.log('Não foi possível tocar som de notificação:', e);
            });
        } catch (error) {
            console.error('Erro ao tocar som:', error);
        }
    }

    /**
     * Atualizar contador de notificações
     */
    updateNotificationCount(total) {
        const badge = document.getElementById('notification-count');
        if (badge) {
            if (total > 0) {
                badge.textContent = total > 99 ? '99+' : total;
                badge.style.display = 'block';

                // Animar sino se há novas notificações
                const bell = document.querySelector('#notification-bell i');
                if (bell) {
                    bell.classList.add('fa-shake');
                    setTimeout(() => bell.classList.remove('fa-shake'), 1000);
                }
            } else {
                badge.style.display = 'none';
            }
        }
    }

    /**
     * Atualizar dropdown de notificações
     */
    updateNotificationsDropdown(notificacoes) {
        const list = document.getElementById('notifications-list');
        if (!list) return;

        if (notificacoes.length === 0) {
            list.innerHTML = '<div class="dropdown-item text-center text-muted">Nenhuma notificação</div>';
            return;
        }

        list.innerHTML = notificacoes.map(notificacao => `
            <div class="dropdown-item notification-item ${notificacao.lida ? 'read' : 'unread'}" 
                 onclick="notificacoes.marcarLida(${notificacao.id})" 
                 ${notificacao.link ? `style="cursor: pointer;" data-link="${notificacao.link}"` : ''}>
                <div class="d-flex align-items-start">
                    <i class="fas fa-${notificacao.icone || 'bell'} me-2 mt-1" style="color: ${notificacao.cor || '#007bff'};"></i>
                    <div class="flex-grow-1">
                        <div class="fw-bold">${notificacao.titulo}</div>
                        <div class="small text-muted">${notificacao.mensagem}</div>
                        <div class="small text-muted">${this.formatarTempo(notificacao.data_criacao)}</div>
                    </div>
                    ${!notificacao.lida ? '<span class="badge bg-primary rounded-pill">Nova</span>' : ''}
                </div>
            </div>
        `).join('');
    }

    /**
     * Toggle dropdown de notificações
     */
    toggleDropdown() {
        const dropdown = document.getElementById('notifications-dropdown');
        if (dropdown) {
            const isVisible = dropdown.style.display === 'block';
            dropdown.style.display = isVisible ? 'none' : 'block';

            if (!isVisible) {
                // Buscar notificações atualizadas ao abrir
                this.checkNotifications();
            }
        }
    }

    /**
     * Marcar notificação como lida
     */
    async marcarLida(id) {
        try {
            const response = await fetch(`notificacoes.php?action=marcar_lida&id=${id}`);
            const data = await response.json();

            if (data.sucesso) {
                // Atualizar visualmente
                const item = document.querySelector(`[onclick="notificacoes.marcarLida(${id})"]`);
                if (item) {
                    item.classList.remove('unread');
                    item.classList.add('read');

                    const badge = item.querySelector('.badge');
                    if (badge) badge.remove();

                    // Redirecionar se tem link
                    const link = item.dataset.link;
                    if (link) {
                        setTimeout(() => window.location.href = link, 300);
                    }
                }

                // Atualizar contador
                this.checkNotifications();
            }
        } catch (error) {
            console.error('Erro ao marcar notificação como lida:', error);
        }
    }

    /**
     * Marcar todas como lidas
     */
    async marcarTodasLidas() {
        try {
            const items = document.querySelectorAll('.notification-item.unread');

            for (const item of items) {
                const onclick = item.getAttribute('onclick');
                const id = onclick.match(/\d+/)[0];
                await this.marcarLida(id);
            }

            // Fechar dropdown
            document.getElementById('notifications-dropdown').style.display = 'none';

        } catch (error) {
            console.error('Erro ao marcar todas como lidas:', error);
        }
    }

    /**
     * Obter cor baseada na prioridade
     */
    getPriorityColor(prioridade) {
        const cores = {
            'urgente': 'danger',
            'alta': 'warning',
            'media': 'info',
            'baixa': 'secondary'
        };

        return cores[prioridade] || 'info';
    }

    /**
     * Formatar tempo relativo
     */
    formatarTempo(dataString) {
        const data = new Date(dataString);
        const agora = new Date();
        const diff = Math.floor((agora - data) / 1000);

        if (diff < 60) {
            return 'Agora';
        } else if (diff < 3600) {
            const min = Math.floor(diff / 60);
            return `${min} min atrás`;
        } else if (diff < 86400) {
            const horas = Math.floor(diff / 3600);
            return `${horas}h atrás`;
        } else {
            const dias = Math.floor(diff / 86400);
            return `${dias}d atrás`;
        }
    }

    /**
     * Configurar intervalo de verificação
     */
    setInterval(seconds) {
        this.intervalTime = seconds * 1000;

        if (this.isActive) {
            this.stop();
            this.start();
        }
    }
}

// ========================================
// CSS PARA NOTIFICAÇÕES
// ========================================

// Adicionar estilos CSS dinamicamente
const styles = `
<style>
@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(100%);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes slideOutRight {
    from {
        opacity: 1;
        transform: translateX(0);
    }
    to {
        opacity: 0;
        transform: translateX(100%);
    }
}

@keyframes shake {
    0%, 100% { transform: rotate(0deg); }
    25% { transform: rotate(-5deg); }
    75% { transform: rotate(5deg); }
}

.fa-shake {
    animation: shake 0.5s ease-in-out;
}

.notification-toast {
    max-width: 350px;
    word-wrap: break-word;
}

.notification-item {
    border-bottom: 1px solid #eee;
    padding: 12px 16px;
    transition: background-color 0.3s ease;
}

.notification-item:hover {
    background-color: #f8f9fa;
}

.notification-item.unread {
    background-color: #e3f2fd;
    border-left: 4px solid #2196f3;
}

.notification-item.read {
    opacity: 0.7;
}

.notification-item:last-child {
    border-bottom: none;
}

#notifications-dropdown {
    border: none;
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    border-radius: 10px;
}

#notification-bell {
    position: relative;
}

#notification-bell .dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 8px;
}

/* Responsivo */
@media (max-width: 768px) {
    #notifications-container {
        top: 10px;
        right: 10px;
        left: 10px;
        max-width: none;
    }
    
    .notification-toast {
        max-width: none;
    }
    
    #notifications-dropdown {
        width: 300px !important;
    }
}

/* Badge animado */
#notification-count {
    animation: pulse 1s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}
</style>
`;

// Adicionar estilos ao documento
if (!document.getElementById('notification-styles')) {
    const styleElement = document.createElement('div');
    styleElement.id = 'notification-styles';
    styleElement.innerHTML = styles;
    document.head.appendChild(styleElement);
}

// ========================================
// INICIALIZAÇÃO GLOBAL
// ========================================

// Criar instância global
let notificacoes;

// Inicializar quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', function () {
    notificacoes = new NotificacoesRealTime();

    // Iniciar automaticamente se estiver logado
    if (document.body.dataset.usuarioLogado === 'true' ||
        sessionStorage.getItem('usuario_logado') === 'true') {
        notificacoes.start();
    }

    // Fechar dropdown ao clicar fora
    document.addEventListener('click', function (e) {
        const dropdown = document.getElementById('notifications-dropdown');
        const bell = document.getElementById('notification-bell');

        if (dropdown && bell && !bell.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });
});

// Parar notificações ao sair da página
window.addEventListener('beforeunload', function () {
    if (notificacoes) {
        notificacoes.stop();
    }
});

// ========================================
// FUNÇÕES AUXILIARES PARA INTEGRAÇÃO
// ========================================

/**
 * Iniciar notificações manualmente
 */
function iniciarNotificacoes() {
    if (notificacoes) {
        notificacoes.start();
    }
}

/**
 * Parar notificações manualmente  
 */
function pararNotificacoes() {
    if (notificacoes) {
        notificacoes.stop();
    }
}

/**
 * Verificar notificações manualmente
 */
function verificarNotificacoes() {
    if (notificacoes) {
        notificacoes.checkNotifications();
    }
}

/**
 * Configurar intervalo de verificação
 */
function configurarIntervaloNotificacoes(segundos) {
    if (notificacoes) {
        notificacoes.setInterval(segundos);
    }
}

// Exportar para uso global
window.NotificacoesRealTime = NotificacoesRealTime;
window.iniciarNotificacoes = iniciarNotificacoes;
window.pararNotificacoes = pararNotificacoes;
window.verificarNotificacoes = verificarNotificacoes;
window.configurarIntervaloNotificacoes = configurarIntervaloNotificacoes;