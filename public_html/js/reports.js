/**
 * Módulo de reportes para la aplicación CFEwEB
 * Maneja la visualización, filtrado y exportación de reportes
 */

const ReportsModule = {
    // Datos actuales de reportes
    reportsData: [],
    
    // Paginación actual
    currentPage: 1,
    totalPages: 1,
    itemsPerPage: 10,
    
    /**
     * Inicializa el módulo de reportes
     */
    init: function() {
        // Configura los eventos para el filtrado
        const filterBtn = document.getElementById('btnApplyReportFilter');
        if (filterBtn) {
            filterBtn.addEventListener('click', () => this.loadReports());
        }
        
        // Configura el evento para exportar a Excel
        const exportBtn = document.getElementById('btnExportXlsx');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => this.exportToExcel());
        }
        
        // Inicialmente carga todos los reportes
        this.loadReports();
    },
    
    /**
     * Carga los reportes desde la API con filtros opcionales
     */
    loadReports: function() {
        // Si no hay autenticación, no continuar
        if (!Auth.isAuthenticated()) return;
        
        // Obtener valores de los filtros
        const dateFromEl = document.getElementById('dateFromFilter');
        const dateToEl = document.getElementById('dateToFilter');
        const statusEl = document.getElementById('statusFilterReports');
        
        const dateFrom = dateFromEl ? dateFromEl.value : '';
        const dateTo = dateToEl ? dateToEl.value : '';
        const status = statusEl ? statusEl.value : '';
        
        // Mostrar indicador de carga
        const tableBody = document.getElementById('reportsTableBody');
        if (tableBody) {
            tableBody.innerHTML = '<tr><td colspan="8" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></td></tr>';
        }
        
        // URL de la API para obtener reportes usando la configuración centralizada
        let url = AppConfig.buildQuery('reports');
        
        // Realizar la solicitud a la API
        fetch(url, {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${Auth.getToken()}`
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && Array.isArray(data.data)) {
                // Aplicar filtros localmente
                let filteredData = data.data;
                
                // Filtrar por fecha de inicio
                if (dateFrom) {
                    const fromDate = new Date(dateFrom);
                    filteredData = filteredData.filter(report => {
                        const reportDate = new Date(report.created_at);
                        return reportDate >= fromDate;
                    });
                }
                
                // Filtrar por fecha de fin
                if (dateTo) {
                    const toDate = new Date(dateTo);
                    // Ajustar a final del día
                    toDate.setHours(23, 59, 59, 999);
                    filteredData = filteredData.filter(report => {
                        const reportDate = new Date(report.created_at);
                        return reportDate <= toDate;
                    });
                }
                
                // Filtrar por estado
                if (status) {
                    filteredData = filteredData.filter(report => report.status === status);
                }
                
                // Guardar datos filtrados
                this.reportsData = filteredData;
                
                // Calcular paginación
                this.totalPages = Math.ceil(filteredData.length / this.itemsPerPage);
                this.currentPage = 1;
                
                // Renderizar tabla y paginación
                this.renderTable();
                this.renderPagination();
            } else {
                if (tableBody) {
                    tableBody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-danger">Error al cargar los reportes</td></tr>';
                }
                console.error('Error al cargar reportes:', data.message);
            }
        })
        .catch(error => {
            if (tableBody) {
                tableBody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-danger">Error de conexión</td></tr>';
            }
            console.error('Error de conexión al cargar reportes:', error);
        });
    },
    
    /**
     * Renderiza la tabla de reportes
     */
    renderTable: function() {
        const tableBody = document.getElementById('reportsTableBody');
        if (!tableBody) return;
        
        // Limpiar tabla
        tableBody.innerHTML = '';
        
        // Calcular índices para la página actual
        const startIndex = (this.currentPage - 1) * this.itemsPerPage;
        const endIndex = Math.min(startIndex + this.itemsPerPage, this.reportsData.length);
        
        // Si no hay datos
        if (this.reportsData.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="8" class="text-center py-4">No se encontraron reportes con los filtros aplicados</td></tr>';
            return;
        }
        
        // Mostrar los reportes de la página actual
        for (let i = startIndex; i < endIndex; i++) {
            const report = this.reportsData[i];
            const row = document.createElement('tr');
            
            // Determinar la clase del badge según el estado
            let badgeClass = 'badge-pending';
            if (report.status === 'En proceso') {
                badgeClass = 'badge-in-progress';
            } else if (report.status === 'Completado') {
                badgeClass = 'badge-completed';
            }
            
            // Crear el contenido de la fila
            row.innerHTML = `
                <td>${report.id}</td>
                <td>${report.matricula || 'N/A'}</td>
                <td>${report.anomaly_name || 'N/A'}</td>
                <td>${report.description ? (report.description.substring(0, 30) + (report.description.length > 30 ? '...' : '')) : 'N/A'}</td>
                <td>${report.latitude && report.longitude ? `${report.latitude.toFixed(6)}, ${report.longitude.toFixed(6)}` : 'No disponible'}</td>
                <td><span class="badge ${badgeClass}">${report.status || 'Pendiente'}</span></td>
                <td>${report.created_at ? new Date(report.created_at).toLocaleString() : 'N/A'}</td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="ReportsModule.showDetailModal(${report.id})">
                        <i class="fas fa-eye"></i>
                    </button>
                </td>
            `;
            
            tableBody.appendChild(row);
        }
    },
    
    /**
     * Renderiza la paginación
     */
    renderPagination: function() {
        const paginationEl = document.getElementById('reportsPagination');
        if (!paginationEl) return;
        
        // Limpiar paginación
        paginationEl.innerHTML = '';
        
        // Si hay una sola página, no mostrar paginación
        if (this.totalPages <= 1) return;
        
        // Crear elemento de navegación
        const nav = document.createElement('nav');
        nav.setAttribute('aria-label', 'Paginación de reportes');
        
        const ul = document.createElement('ul');
        ul.className = 'pagination';
        
        // Botón anterior
        const prevLi = document.createElement('li');
        prevLi.className = `page-item ${this.currentPage === 1 ? 'disabled' : ''}`;
        prevLi.innerHTML = `
            <a class="page-link" href="#" aria-label="Anterior" ${this.currentPage !== 1 ? `onclick="ReportsModule.goToPage(${this.currentPage - 1}); return false;"` : ''}>
                <span aria-hidden="true">&laquo;</span>
            </a>
        `;
        ul.appendChild(prevLi);
        
        // Páginas
        for (let i = 1; i <= this.totalPages; i++) {
            // Limitar el número de botones de página
            if (this.totalPages > 7) {
                if (i !== 1 && i !== this.totalPages && (i < this.currentPage - 1 || i > this.currentPage + 1)) {
                    if (i === 2 || i === this.totalPages - 1) {
                        const ellipsisLi = document.createElement('li');
                        ellipsisLi.className = 'page-item disabled';
                        ellipsisLi.innerHTML = '<a class="page-link" href="#">...</a>';
                        ul.appendChild(ellipsisLi);
                    }
                    continue;
                }
            }
            
            const pageLi = document.createElement('li');
            pageLi.className = `page-item ${this.currentPage === i ? 'active' : ''}`;
            pageLi.innerHTML = `
                <a class="page-link" href="#" onclick="ReportsModule.goToPage(${i}); return false;">${i}</a>
            `;
            ul.appendChild(pageLi);
        }
        
        // Botón siguiente
        const nextLi = document.createElement('li');
        nextLi.className = `page-item ${this.currentPage === this.totalPages ? 'disabled' : ''}`;
        nextLi.innerHTML = `
            <a class="page-link" href="#" aria-label="Siguiente" ${this.currentPage !== this.totalPages ? `onclick="ReportsModule.goToPage(${this.currentPage + 1}); return false;"` : ''}>
                <span aria-hidden="true">&raquo;</span>
            </a>
        `;
        ul.appendChild(nextLi);
        
        nav.appendChild(ul);
        paginationEl.appendChild(nav);
    },
    
    /**
     * Navega a una página específica
     * @param {number} page - Número de página
     */
    goToPage: function(page) {
        if (page < 1 || page > this.totalPages) return;
        this.currentPage = page;
        this.renderTable();
        this.renderPagination();
    },
    
    /**
     * Muestra el modal con los detalles de un reporte
     * @param {number} reportId - ID del reporte
     */
    showDetailModal: function(reportId) {
        // Si no hay autenticación, no continuar
        if (!Auth.isAuthenticated()) return;
        
        // Obtener elementos del modal
        const modal = document.getElementById('reportDetailModal');
        const modalTitle = document.getElementById('reportDetailModalLabel');
        const modalContent = document.getElementById('reportDetailContent');
        
        if (!modal || !modalContent) return;
        
        // Mostrar indicador de carga
        modalContent.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
        
        // Mostrar el modal
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
        
        // URL de la API para obtener detalles del reporte
        const url = AppConfig.buildQuery('reports', reportId);
        
        // Realizar la solicitud a la API
        fetch(url, {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${Auth.getToken()}`
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.data) {
                const report = data.data;
                
                // Actualizar título del modal
                if (modalTitle) {
                    modalTitle.textContent = `Detalles del Reporte #${report.id}`;
                }
                
                // Determinar la clase del badge según el estado
                let badgeClass = 'badge-pending';
                if (report.status === 'En proceso') {
                    badgeClass = 'badge-in-progress';
                } else if (report.status === 'Completado') {
                    badgeClass = 'badge-completed';
                }
                
                // Generar contenido del modal
                modalContent.innerHTML = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Información General</h6>
                            <table class="table table-striped table-bordered">
                                <tr>
                                    <th width="40%">ID</th>
                                    <td>${report.id}</td>
                                </tr>
                                <tr>
                                    <th>Matrícula</th>
                                    <td>${report.matricula || 'No especificada'}</td>
                                </tr>
                                <tr>
                                    <th>Estado</th>
                                    <td><span class="badge ${badgeClass}">${report.status || 'Pendiente'}</span></td>
                                </tr>
                                <tr>
                                    <th>Fecha de Creación</th>
                                    <td>${report.created_at ? new Date(report.created_at).toLocaleString() : 'No disponible'}</td>
                                </tr>
                                <tr>
                                    <th>Última Actualización</th>
                                    <td>${report.updated_at ? new Date(report.updated_at).toLocaleString() : 'No disponible'}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Detalles de la Anomalía</h6>
                            <table class="table table-striped table-bordered">
                                <tr>
                                    <th width="40%">Grupo</th>
                                    <td>${report.group_name || 'No especificado'}</td>
                                </tr>
                                <tr>
                                    <th>Anomalía</th>
                                    <td>${report.anomaly_name || 'No especificada'}</td>
                                </tr>
                                <tr>
                                    <th>Material</th>
                                    <td>${report.material_name || 'No especificado'}</td>
                                </tr>
                                <tr>
                                    <th>Coordenadas</th>
                                    <td>${report.latitude && report.longitude ? `${report.latitude.toFixed(6)}, ${report.longitude.toFixed(6)}` : 'No disponibles'}</td>
                                </tr>
                                <tr>
                                    <th>Reportado por</th>
                                    <td>${report.user_name || 'Desconocido'}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6>Descripción</h6>
                            <div class="p-3 bg-light rounded">
                                ${report.description || 'No hay descripción disponible para este reporte.'}
                            </div>
                        </div>
                    </div>
                    
                    ${report.image_url ? `
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6>Imagen</h6>
                            <div class="text-center">
                                <img src="${report.image_url}" alt="Imagen del reporte" class="img-fluid rounded" style="max-height: 300px;">
                            </div>
                        </div>
                    </div>` : ''}
                    
                    ${report.latitude && report.longitude ? `
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6>Ubicación</h6>
                            <div id="detailMap" style="height: 200px;"></div>
                        </div>
                    </div>` : ''}
                `;
                
                // Si hay coordenadas, mostrar un pequeño mapa
                if (report.latitude && report.longitude) {
                    setTimeout(() => {
                        const detailMap = L.map('detailMap').setView([report.latitude, report.longitude], 15);
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                        }).addTo(detailMap);
                        
                        // Añadir marcador
                        L.marker([report.latitude, report.longitude]).addTo(detailMap)
                            .bindPopup(`Reporte #${report.id}`).openPopup();
                    }, 300);
                }
                
                // Configurar botón de actualización de estado
                const updateBtn = document.getElementById('btnUpdateStatus');
                if (updateBtn) {
                    if (report.status === 'Completado') {
                        updateBtn.classList.add('d-none');
                    } else {
                        updateBtn.classList.remove('d-none');
                        updateBtn.textContent = report.status === 'Pendiente' ? 'Marcar En Proceso' : 'Marcar Completado';
                        updateBtn.onclick = () => this.updateReportStatus(report.id, report.status);
                    }
                }
            } else {
                modalContent.innerHTML = '<div class="alert alert-danger">Error al cargar los detalles del reporte</div>';
            }
        })
        .catch(error => {
            modalContent.innerHTML = '<div class="alert alert-danger">Error de conexión al cargar los detalles</div>';
            console.error('Error al obtener detalles del reporte:', error);
        });
    },
    
    /**
     * Actualiza el estado de un reporte
     * @param {number} reportId - ID del reporte
     * @param {string} currentStatus - Estado actual del reporte
     */
    updateReportStatus: function(reportId, currentStatus) {
        // Si no hay autenticación, no continuar
        if (!Auth.isAuthenticated()) return;
        
        // Determinar el nuevo estado
        const newStatus = currentStatus === 'Pendiente' ? 'En proceso' : 'Completado';
        
        // URL de la API para actualizar el reporte
        const url = AppConfig.buildQuery('reports', reportId);
        
        // Datos para la actualización
        const data = {
            status: newStatus
        };
        
        // Desactivar el botón durante la actualización
        const updateBtn = document.getElementById('btnUpdateStatus');
        if (updateBtn) {
            updateBtn.disabled = true;
            updateBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Actualizando...';
        }
        
        // Realizar la solicitud a la API
        fetch(url, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${Auth.getToken()}`
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Cerrar el modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('reportDetailModal'));
                if (modal) modal.hide();
                
                // Mostrar mensaje de éxito
                alert('Estado del reporte actualizado correctamente');
                
                // Recargar reportes
                this.loadReports();
                
                // Recargar mapa si está disponible
                if (typeof MapModule !== 'undefined') {
                    MapModule.loadReportLocations();
                }
            } else {
                alert('Error al actualizar el estado: ' + (data.message || 'Error desconocido'));
                
                // Reactivar el botón
                if (updateBtn) {
                    updateBtn.disabled = false;
                    updateBtn.textContent = currentStatus === 'Pendiente' ? 'Marcar En Proceso' : 'Marcar Completado';
                }
            }
        })
        .catch(error => {
            alert('Error de conexión al actualizar el estado');
            console.error('Error al actualizar estado:', error);
            
            // Reactivar el botón
            if (updateBtn) {
                updateBtn.disabled = false;
                updateBtn.textContent = currentStatus === 'Pendiente' ? 'Marcar En Proceso' : 'Marcar Completado';
            }
        });
    },
    
    /**
     * Exporta los reportes a Excel con los filtros aplicados
     */
    exportToExcel: function() {
        // Si no hay autenticación, no continuar
        if (!Auth.isAuthenticated()) return;
        
        // Obtener valores de los filtros
        const dateFromEl = document.getElementById('dateFromFilter');
        const dateToEl = document.getElementById('dateToFilter');
        const statusEl = document.getElementById('statusFilterReports');
        
        const dateFrom = dateFromEl ? dateFromEl.value : '';
        const dateTo = dateToEl ? dateToEl.value : '';
        const status = statusEl ? statusEl.value : '';
        
        // Construir URL para exportación
        let url = AppConfig.buildQuery('export', 'reports');
        
        // Añadir parámetros de filtro si están presentes
        const params = [];
        if (dateFrom) params.push(`start_date=${encodeURIComponent(dateFrom)}`);
        if (dateTo) params.push(`end_date=${encodeURIComponent(dateTo)}`);
        if (status) params.push(`status=${encodeURIComponent(status)}`);
        
        if (params.length > 0) {
            url += `&${params.join('&')}`;
        }
        
        // Abrir la URL en una nueva ventana para descargar el archivo
        window.open(url, '_blank');
    }
};

// Al cargar el documento, inicializar el módulo de reportes
document.addEventListener('DOMContentLoaded', function() {
    // Se inicializará desde main.js
});
