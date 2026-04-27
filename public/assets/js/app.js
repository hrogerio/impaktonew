// assets/js/app.js - Sistema de notificações e melhorias UX
class NotificationSystem {
    static show(message, type = 'info', duration = 5000) {
        const toast = document.createElement('div');
        toast.className = `notification notification-${type}`;
        toast.innerHTML = `
            <div class="notification-content">
                <span class="notification-icon">${this.getIcon(type)}</span>
                <span class="notification-message">${message}</span>
                <button class="notification-close" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        // Animação de entrada
        setTimeout(() => toast.classList.add('show'), 100);
        
        // Auto remover
        setTimeout(() => {
            toast.classList.add('hide');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }
    
    static getIcon(type) {
        const icons = {
            success: '✓',
            error: '✗',
            warning: '⚠',
            info: 'ℹ'
        };
        return icons[type] || icons.info;
    }
}

class LoadingSystem {
    static show(message = 'Carregando...') {
        const loader = document.createElement('div');
        loader.id = 'global-loader';
        loader.innerHTML = `
            <div class="loader-backdrop">
                <div class="loader-content">
                    <div class="spinner"></div>
                    <p>${message}</p>
                </div>
            </div>
        `;
        document.body.appendChild(loader);
    }
    
    static hide() {
        const loader = document.getElementById('global-loader');
        if (loader) {
            loader.remove();
        }
    }
}

class SearchSystem {
    constructor(config) {
        this.searchInput = document.getElementById(config.inputId);
        this.resultsContainer = document.getElementById(config.resultsId);
        this.apiUrl = config.apiUrl;
        this.debounceTime = config.debounceTime || 300;
        this.minChars = config.minChars || 2;
        
        this.debounceTimer = null;
        this.init();
    }
    
    init() {
        if (!this.searchInput) return;
        
        this.searchInput.addEventListener('input', (e) => {
            clearTimeout(this.debounceTimer);
            
            const query = e.target.value.trim();
            
            if (query.length < this.minChars) {
                this.clearResults();
                return;
            }
            
            this.debounceTimer = setTimeout(() => {
                this.performSearch(query);
            }, this.debounceTime);
        });
        
        // Fechar resultados ao clicar fora
        document.addEventListener('click', (e) => {
            if (!this.searchInput.contains(e.target) && !this.resultsContainer.contains(e.target)) {
                this.clearResults();
            }
        });
    }
    
    async performSearch(query) {
        try {
            LoadingSystem.show('Buscando...');
            
            const response = await fetch(`${this.apiUrl}?busca=${encodeURIComponent(query)}&ajax=1`);
            const data = await response.json();
            
            this.displayResults(data);
            
        } catch (error) {
            console.error('Erro na busca:', error);
            NotificationSystem.show('Erro ao realizar busca', 'error');
        } finally {
            LoadingSystem.hide();
        }
    }
    
    displayResults(data) {
        if (!data.pontos || data.pontos.length === 0) {
            this.resultsContainer.innerHTML = '<div class="search-no-results">Nenhum resultado encontrado</div>';
            return;
        }
        
        const html = data.pontos.map(ponto => `
            <div class="search-result-item" onclick="window.location.href='?page=ponto&id=${ponto.id}'">
                <div class="search-result-main">
                    <strong>Ponto ${ponto.numero}</strong> - ${ponto.logradouro}
                </div>
                <div class="search-result-details">
                    ${ponto.cidade} • ${ponto.cliente || 'Sem cliente'}
                </div>
            </div>
        `).join('');
        
        this.resultsContainer.innerHTML = html;
        this.resultsContainer.style.display = 'block';
    }
    
    clearResults() {
        this.resultsContainer.innerHTML = '';
        this.resultsContainer.style.display = 'none';
    }
}

class DataTable {
    constructor(config) {
        this.table = document.getElementById(config.tableId);
        this.apiUrl = config.apiUrl;
        this.currentPage = 1;
        this.currentFilters = {};
        this.sortColumn = null;
        this.sortDirection = 'asc';
        
        this.init();
    }
    
    init() {
        if (!this.table) return;
        
        // Configurar ordenação nas colunas
        this.table.querySelectorAll('th[data-sortable]').forEach(header => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => {
                const column = header.dataset.sortable;
                this.sort(column);
            });
        });
        
        // Configurar filtros
        this.setupFilters();
    }
    
    setupFilters() {
        const filterForm = document.querySelector('.filtros-form');
        if (!filterForm) return;
        
        filterForm.addEventListener('change', (e) => {
            if (e.target.matches('select')) {
                this.applyFilters();
            }
        });
    }
    
    async applyFilters() {
        const filterForm = document.querySelector('.filtros-form');
        const formData = new FormData(filterForm);
        
        this.currentFilters = {};
        for (let [key, value] of formData.entries()) {
            if (value) {
                this.currentFilters[key] = value;
            }
        }
        
        this.currentPage = 1;
        await this.loadData();
    }
    
    async sort(column) {
        if (this.sortColumn === column) {
            this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            this.sortColumn = column;
            this.sortDirection = 'asc';
        }
        
        await this.loadData();
    }
    
    async loadData() {
        try {
            LoadingSystem.show();
            
            const params = new URLSearchParams({
                ...this.currentFilters,
                pagina: this.currentPage,
                ajax: 1
            });
            
            if (this.sortColumn) {
                params.append('sort', this.sortColumn);
                params.append('direction', this.sortDirection);
            }
            
            const response = await fetch(`${this.apiUrl}?${params}`);
            const data = await response.json();
            
            this.updateTable(data);
            this.updatePagination(data);
            
        } catch (error) {
            console.error('Erro ao carregar dados:', error);
            NotificationSystem.show('Erro ao carregar dados', 'error');
        } finally {
            LoadingSystem.hide();
        }
    }
    
    updateTable(data) {
        const tbody = this.table.querySelector('tbody');
        if (!tbody) return;
        
        if (!data.pontos || data.pontos.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center">Nenhum ponto encontrado</td></tr>';
            return;
        }
        
        tbody.innerHTML = data.pontos.map(ponto => this.renderRow(ponto)).join('');
        
        // Atualizar contador
        const counter = document.querySelector('.pontos-contagem span');
        if (counter) {
            counter.textContent = `${data.total} ponto(s) encontrado(s)`;
        }
    }
    
    renderRow(ponto) {
        return `
            <tr>
                <td>${ponto.numero || ''}</td>
                <td>${ponto.logradouro || ''}</td>
                <td>${ponto.descricao || ''}</td>
                <td>${ponto.cidade || ''}</td>
                <td>${ponto.cliente || ''}</td>
                <td>
                    <span class="badge ${this.getBadgeClass(ponto.situacao)}">
                        ${ponto.situacao || ''}
                    </span>
                </td>
                <td class="tabela-data-fim">
                    <span class="data-compacta">${this.formatDate(ponto.fim_contrato)}</span>
                    <div class="tempo-restante">${this.calculateTimeRemaining(ponto.fim_contrato)}</div>
                </td>
                <td>
                    <a href="?page=ponto&id=${ponto.id}" target="_blank">+Info</a>
                </td>
            </tr>
        `;
    }
    
    getBadgeClass(situacao) {
        const classes = {
            'Disponível': 'disponivel',
            'Reservado': 'reservado',
            'Ocupado': 'ocupado',
            'Vencido': 'vencido',
            'Permuta': 'permuta',
            'Bisemana': 'bisemana'
        };
        return classes[situacao] || 'disponivel';
    }
    
    formatDate(dateString) {
        if (!dateString || dateString === '0000-00-00') return '-';
        
        try {
            const date = new Date(dateString);
            const months = [
                'Janeiro', 'Fevereiro', 'Março', 'Abril',
                'Maio', 'Junho', 'Julho', 'Agosto',
                'Setembro', 'Outubro', 'Novembro', 'Dezembro'
            ];
            return `${months[date.getMonth()]}/${date.getFullYear()}`;
        } catch {
            return 'Data inválida';
        }
    }
    
    calculateTimeRemaining(endDate) {
        if (!endDate || endDate === '0000-00-00') return '';
        
        try {
            const end = new Date(endDate);
            const now = new Date();
            const diffTime = end - now;
            const diffMonths = Math.ceil(diffTime / (1000 * 60 * 60 * 24 * 30));
            
            if (diffMonths < 0) {
                return `Vencido há ${Math.abs(diffMonths)}m`;
            } else if (diffMonths === 0) {
                return 'Vence este mês';
            } else {
                return `Restam ${diffMonths}m`;
            }
        } catch {
            return '';
        }
    }
    
    updatePagination(data) {
        const pagination = document.querySelector('.paginacao');
        if (!pagination || !data.totalPaginas || data.totalPaginas <= 1) {
            if (pagination) pagination.style.display = 'none';
            return;
        }
        
        pagination.style.display = 'block';
        
        let html = '';
        
        // Botão anterior
        if (data.pagina > 1) {
            html += `<a href="#" data-page="${data.pagina - 1}">« Anterior</a>`;
        }
        
        // Números das páginas
        const startPage = Math.max(1, data.pagina - 2);
        const endPage = Math.min(data.totalPaginas, data.pagina + 2);
        
        for (let i = startPage; i <= endPage; i++) {
            const isActive = i === data.pagina ? 'ativo' : '';
            html += `<a href="#" data-page="${i}" class="${isActive}">${i}</a>`;
        }
        
        // Botão próximo
        if (data.pagina < data.totalPaginas) {
            html += `<a href="#" data-page="${data.pagina + 1}">Próximo »</a>`;
        }
        
        pagination.innerHTML = html;
        
        // Configurar eventos dos links de paginação
        pagination.querySelectorAll('a[data-page]').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                this.currentPage = parseInt(e.target.dataset.page);
                this.loadData();
            });
        });
    }
}

// Inicialização quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', function() {
    // Sistema de busca
    if (document.getElementById('inputBusca')) {
        new SearchSystem({
            inputId: 'inputBusca',
            resultsId: 'searchResults',
            apiUrl: '/api/pontos/buscar'
        });
    }
    
    // Tabela de dados
    if (document.getElementById('tabela-pontos')) {
        new DataTable({
            tableId: 'tabela-pontos',
            apiUrl: '/api/pontos'
        });
    }
    
    // Formulários com AJAX
    setupAjaxForms();
    
    // Configurar exportação
    setupExportButtons();
    
    // Sistema de flash messages
    showFlashMessages();
});

function setupAjaxForms() {
    document.querySelectorAll('form[data-ajax]').forEach(form => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(form);
            const url = form.action || window.location.href;
            
            try {
                LoadingSystem.show();
                
                const response = await fetch(url, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    NotificationSystem.show(result.message, 'success');
                    if (result.redirect) {
                        setTimeout(() => window.location.href = result.redirect, 1000);
                    }
                } else {
                    NotificationSystem.show(result.message || 'Erro ao processar solicitação', 'error');
                }
                
            } catch (error) {
                console.error('Erro no formulário:', error);
                NotificationSystem.show('Erro ao enviar formulário', 'error');
            } finally {
                LoadingSystem.hide();
            }
        });
    });
}

function setupExportButtons() {
    document.querySelectorAll('[data-export]').forEach(button => {
        button.addEventListener('click', async (e) => {
            e.preventDefault();
            
            const format = button.dataset.export;
            const currentFilters = getCurrentFilters();
            
            try {
                LoadingSystem.show('Preparando exportação...');
                
                const params = new URLSearchParams({
                    ...currentFilters,
                    export: format
                });
                
                window.open(`/api/pontos/export?${params}`, '_blank');
                
                setTimeout(() => {
                    NotificationSystem.show('Exportação iniciada!', 'success');
                }, 1000);
                
            } catch (error) {
                console.error('Erro na exportação:', error);
                NotificationSystem.show('Erro ao exportar dados', 'error');
            } finally {
                LoadingSystem.hide();
            }
        });
    });
}

function getCurrentFilters() {
    const filterForm = document.querySelector('.filtros-form');
    if (!filterForm) return {};
    
    const formData = new FormData(filterForm);
    const filters = {};
    
    for (let [key, value] of formData.entries()) {
        if (value) {
            filters[key] = value;
        }
    }
    
    return filters;
}

function showFlashMessages() {
    // Verificar se há mensagens flash na sessão
    const urlParams = new URLSearchParams(window.location.search);
    
    if (urlParams.get('success')) {
        NotificationSystem.show(urlParams.get('success'), 'success');
    }
    
    if (urlParams.get('error')) {
        NotificationSystem.show(urlParams.get('error'), 'error');
    }
    
    if (urlParams.get('warning')) {
        NotificationSystem.show(urlParams.get('warning'), 'warning');
    }
    
    // Limpar URL dos parâmetros de notificação
    if (urlParams.has('success') || urlParams.has('error') || urlParams.has('warning')) {
        urlParams.delete('success');
        urlParams.delete('error');
        urlParams.delete('warning');
        
        const newUrl = `${window.location.pathname}?${urlParams.toString()}`;
        window.history.replaceState({}, '', newUrl);
    }
}