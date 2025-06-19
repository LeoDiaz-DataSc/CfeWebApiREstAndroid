/**
 * Módulo de mapa para la aplicación CFEwEB
 * Utiliza Leaflet para mostrar los reportes en un mapa interactivo
 * Incluye geolocalización para centrar el mapa en la ubicación del usuario
 */

// Verificar que las dependencias estén disponibles
if (typeof L === 'undefined') {
    console.error('Error: Leaflet no está disponible. Asegúrate de incluir la biblioteca en tu HTML.');
}

if (typeof AppConfig === 'undefined') {
    console.error('Error: AppConfig no está disponible. Asegúrate de cargar config.js antes que map.js.');
}

if (typeof Auth === 'undefined') {
    console.error('Error: Auth no está disponible. Asegúrate de cargar auth.js antes que map.js.');
}

const MapModule = {
    // Mapa de Leaflet
    map: null,
    
    // Capa de marcadores
    markersLayer: null,
    
    // Datos actuales de reportes
    reportsData: [],
    
    // Marcador de ubicación del usuario
    userLocationMarker: null,
    
    // Círculo de precisión de la ubicación
    accuracyCircle: null,
    
    // Estado de la geolocalización
    locationWatchId: null,
    
    // Configuración del mapa (usando AppConfig si está disponible)
    mapConfig: {
        // Usar valores desde AppConfig si están disponibles
        get center() {
            return (typeof AppConfig !== 'undefined' && AppConfig.maps) ? 
                   AppConfig.maps.defaultCenter : [23.634501, -102.552784]; // México como predeterminado
        },
        get zoom() {
            return (typeof AppConfig !== 'undefined' && AppConfig.maps) ? 
                   AppConfig.maps.defaultZoom : 5;
        },
        minZoom: 4,
        get maxZoom() {
            return (typeof AppConfig !== 'undefined' && AppConfig.maps) ? 
                   AppConfig.maps.maxZoom : 18;
        },
        get useUserLocation() {
            return (typeof AppConfig !== 'undefined' && AppConfig.maps) ? 
                   AppConfig.maps.useUserLocation : true;
        },
        get locationOptions() {
            return (typeof AppConfig !== 'undefined' && AppConfig.maps && AppConfig.maps.locationOptions) ? 
                   AppConfig.maps.locationOptions : {
                       enableHighAccuracy: true,
                       maximumAge: 30000,
                       timeout: 10000
                   };
        },
        tileLayer: {
            get url() {
                return (typeof AppConfig !== 'undefined' && AppConfig.maps) ? 
                       AppConfig.maps.tileProvider : 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
            },
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19,
            subdomains: 'abc'
        }
    },
    
    /**
     * Inicializa el módulo de mapa
     */
    init: function() {
        console.log('Inicializando módulo de mapa...');
        
        // Verifica si Leaflet está disponible
        if (typeof L === 'undefined') {
            console.error('Error: Leaflet no está disponible para inicializar el mapa.');
            return;
        }
        
        // Verifica si existe el contenedor del mapa
        const mapContainer = document.getElementById('map');
        if (!mapContainer) {
            console.error('Error: No se encontró el contenedor del mapa (#map).');
            return;
        }
        
        try {
            // Inicializa el mapa con la configuración
            this.map = L.map('map', {
                center: this.mapConfig.center,
                zoom: this.mapConfig.zoom,
                minZoom: this.mapConfig.minZoom,
                maxZoom: this.mapConfig.maxZoom,
                zoomControl: true,
                attributionControl: true
            });
            
            // Añade la capa de tiles de OpenStreetMap con opciones optimizadas
            L.tileLayer(this.mapConfig.tileLayer.url, {
                attribution: this.mapConfig.tileLayer.attribution,
                maxZoom: this.mapConfig.tileLayer.maxZoom,
                subdomains: this.mapConfig.tileLayer.subdomains,
                updateWhenIdle: true,
                updateWhenZooming: false
            }).addTo(this.map);
            
            // Crea la capa para los marcadores con opciones de rendimiento
            this.markersLayer = L.layerGroup({
                updateWhenIdle: true,
                updateWhenZooming: false
            }).addTo(this.map);
            
            // Configura los eventos para el filtrado
            const filterBtn = document.getElementById('btnApplyFilter');
            if (filterBtn) {
                filterBtn.addEventListener('click', () => this.applyFilters());
            }
            
            // Añade controles adicionales al mapa
            this.addMapControls();
            
            // Activar geolocalización si está configurado
            if (this.mapConfig.useUserLocation) {
                this.enableUserLocation();
            }
            
            // Cargar datos iniciales
            this.loadReportLocations();
            
            console.log('Mapa inicializado correctamente');
        } catch (error) {
            console.error('Error al inicializar el mapa:', error);
        }
    },
    
    /**
     * Añade controles adicionales al mapa
     */
    addMapControls: function() {
        // Añade control de escala
        L.control.scale({
            imperial: false,
            metric: true
        }).addTo(this.map);

        // Añade control de capas
        const baseMaps = {
            "OpenStreetMap": L.tileLayer(this.mapConfig.tileLayer.url, {
                attribution: this.mapConfig.tileLayer.attribution,
                maxZoom: this.mapConfig.tileLayer.maxZoom
            })
        };

        const overlayMaps = {
            "Reportes": this.markersLayer
        };

        L.control.layers(baseMaps, overlayMaps, {
            position: 'topright',
            collapsed: false
        }).addTo(this.map);
    },
    
    /**
     * Carga las ubicaciones de los reportes desde la API
     * @param {string} status - Filtro de estado opcional
     */
    loadReportLocations: function(status = '') {
        // Si no hay autenticación, no continuar
        if (typeof Auth === 'undefined' || !Auth.isAuthenticated()) {
            console.log('Usuario no autenticado, no se cargarán las ubicaciones');
            return;
        }
        
        // Mostrar indicador de carga
        const mapContainer = document.getElementById('map');
        if (mapContainer) mapContainer.classList.add('loading');
        
        // URL de la API para obtener ubicaciones
        let url;
        if (status) {
            url = AppConfig.getApiIndexUrl() + `?controller=map&action=filter&status=${encodeURIComponent(status)}`;
        } else {
            url = AppConfig.buildQuery('map', 'locations');
        }
        
        console.log(`Cargando ubicaciones desde: ${url}`);
        
        // Realizar la solicitud a la API
        fetch(url, {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${Auth.getToken()}`
            }
        })
        .then(response => response.json())
        .then(data => {
            if (mapContainer) mapContainer.classList.remove('loading');
            
            if (data.status === 'success' && data.data) {
                this.reportsData = data.data;
                this.renderMarkers();
            } else {
                console.error('Error al cargar ubicaciones:', data.message);
            }
        })
        .catch(error => {
            if (mapContainer) mapContainer.classList.remove('loading');
            console.error('Error de conexión al cargar ubicaciones:', error);
        });
    },
    
    /**
     * Renderiza los marcadores en el mapa
     */
    renderMarkers: function() {
        // Limpia los marcadores anteriores
        this.markersLayer.clearLayers();
        
        // Comprueba si hay datos de reportes
        if (!this.reportsData || !this.reportsData.features) {
            console.warn('No hay datos de reportes para mostrar en el mapa');
            return;
        }
        
        // Puntos para ajustar el zoom
        const points = [];
        
        // Itera sobre las características del GeoJSON
        this.reportsData.features.forEach(feature => {
            const props = feature.properties;
            const coords = feature.geometry.coordinates;
            
            // Verifica que las coordenadas sean válidas
            if (!coords || coords.length < 2) return;
            
            // Añade un marcador al mapa
            const marker = this.createMarker(
                [coords[1], coords[0]], // Leaflet usa [lat, lng], GeoJSON usa [lng, lat]
                props.status,
                props.id
            );
            
            // Añade el marcador a la capa
            marker.addTo(this.markersLayer);
            
            // Añade el punto para ajuste de zoom
            points.push([coords[1], coords[0]]);
            
            // Configura el popup con información del reporte
            marker.bindPopup(this.createPopupContent(props, coords));
            
            // Evento de clic en el marcador
            marker.on('click', () => {
                this.showReportInfo(props.id);
            });
        });
        
        // Ajusta el zoom para mostrar todos los marcadores si hay más de uno
        if (points.length > 1) {
            this.map.fitBounds(points);
        } else if (points.length === 1) {
            this.map.setView(points[0], 15);
        }
    },
    
    /**
     * Crea un marcador personalizado según el estado del reporte
     * @param {Array} coords - Coordenadas [lat, lng]
     * @param {string} status - Estado del reporte
     * @param {number} id - ID del reporte
     * @returns {L.Marker} Marcador de Leaflet
     */
    createMarker: function(coords, status, id) {
        // Determina la clase CSS según el estado
        let markerClass = 'marker-pending';
        if (status === 'En proceso') {
            markerClass = 'marker-in-progress';
        } else if (status === 'Completado') {
            markerClass = 'marker-completed';
        }
        
        // Crea el icono HTML personalizado
        const icon = L.divIcon({
            className: `custom-marker ${markerClass}`,
            html: `<span>${id}</span>`,
            iconSize: [30, 30]
        });
        
        // Crea y devuelve el marcador
        return L.marker(coords, { icon: icon });
    },
    
    /**
     * Crea el contenido HTML para el popup del marcador
     * @param {Object} props - Propiedades del reporte
     * @param {Array} coords - Coordenadas [lng, lat]
     * @returns {string} Contenido HTML del popup
     */
    createPopupContent: function(props, coords) {
        return `
            <div class="popup-content">
                <div class="popup-title">Reporte #${props.id}</div>
                <div class="popup-matricula">Matrícula: ${props.matricula || 'No especificada'}</div>
                <div class="popup-coordinates">
                    Lat: ${coords[1].toFixed(6)}, Lng: ${coords[0].toFixed(6)}
                </div>
                <div>
                    <span class="badge ${this.getStatusBadgeClass(props.status)}">${props.status || 'Desconocido'}</span>
                </div>
                <div class="popup-date">
                    Fecha: ${props.created_at ? new Date(props.created_at).toLocaleDateString() : 'Desconocida'}
                </div>
                <div class="mt-2">
                    <button class="btn btn-sm btn-primary" onclick="MapModule.showReportDetail(${props.id})">
                        Ver detalles
                    </button>
                </div>
            </div>
        `;
    },
    
    /**
     * Obtiene la clase CSS para el badge de estado
     * @param {string} status - Estado del reporte
     * @returns {string} Clase CSS para el badge
     */
    getStatusBadgeClass: function(status) {
        if (status === 'En proceso') return 'badge-in-progress';
        if (status === 'Completado') return 'badge-completed';
        return 'badge-pending';
    },
    
    /**
     * Muestra información del reporte en el panel lateral
     * @param {number} reportId - ID del reporte
     */
    showReportInfo: function(reportId) {
        const reportInfoEl = document.getElementById('reportInfo');
        if (!reportInfoEl) return;
        
        // Busca el reporte en los datos cargados
        const feature = this.reportsData.features.find(f => f.properties.id === reportId);
        if (!feature) {
            reportInfoEl.innerHTML = '<p class="text-danger">Reporte no encontrado</p>';
            return;
        }
        
        const props = feature.properties;
        const coords = feature.geometry.coordinates;
        
        // Muestra la información en el panel
        reportInfoEl.innerHTML = `
            <h6 class="mb-2">Reporte #${props.id}</h6>
            <p><strong>Matrícula:</strong> ${props.matricula || 'No especificada'}</p>
            <p><strong>Estado:</strong> <span class="badge ${this.getStatusBadgeClass(props.status)}">${props.status || 'Desconocido'}</span></p>
            <p><strong>Tipo:</strong> ${props.anomaly_name || 'No especificado'}</p>
            <p><strong>Descripción:</strong> ${props.description || 'No hay descripción'}</p>
            <p><strong>Ubicación:</strong> <br>Lat: ${coords[1].toFixed(6)}, Lng: ${coords[0].toFixed(6)}</p>
            <p><strong>Fecha:</strong> ${props.created_at ? new Date(props.created_at).toLocaleDateString() : 'Desconocida'}</p>
            <button class="btn btn-sm btn-primary" onclick="ReportsModule.showDetailModal(${props.id})">
                Ver detalles completos
            </button>
        `;
    },
    
    /**
     * Muestra el modal de detalle del reporte
     * @param {number} reportId - ID del reporte
     */
    showReportDetail: function(reportId) {
        // Delegar al módulo de reportes
        if (typeof ReportsModule !== 'undefined') {
            ReportsModule.showDetailModal(reportId);
        } else {
            console.error('ReportsModule no está definido');
        }
    },
    
    /**
     * Habilita la geolocalización del usuario
     */
    enableUserLocation: function() {
        if (!navigator.geolocation) {
            console.log('Geolocalización no soportada por este navegador');
            return;
        }

        try {
            // Agregar un botón de geolocalización al mapa
            const locationControl = L.control({ position: 'bottomright' });
            locationControl.onAdd = () => {
                const div = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
                div.innerHTML = '<a class="location-button" title="Ubicar mi posición" role="button" aria-label="Ubicar mi posición"><i class="fas fa-location-arrow"></i></a>';
                div.onclick = () => this.centerOnUserLocation();
                return div;
            };
            locationControl.addTo(this.map);

            // Iniciar geolocalización
            this.startLocationWatch();
        } catch (error) {
            console.error('Error al habilitar la geolocalización:', error);
        }
    },

    /**
     * Inicia el seguimiento de la ubicación del usuario
     */
    startLocationWatch: function() {
        if (this.locationWatchId) {
            navigator.geolocation.clearWatch(this.locationWatchId);
        }

        this.locationWatchId = navigator.geolocation.watchPosition(
            position => this.onLocationUpdate(position),
            error => this.onLocationError(error),
            this.mapConfig.locationOptions
        );
    },

    /**
     * Actualiza la posición del usuario en el mapa
     */
    onLocationUpdate: function(position) {
        const { latitude, longitude, accuracy } = position.coords;
        const latlng = [latitude, longitude];

        // Si no hay marcador de ubicación, crear uno nuevo
        if (!this.userLocationMarker) {
            // Crear icono personalizado para la ubicación del usuario
            const userIcon = L.divIcon({
                className: 'user-location-icon',
                html: '<div class="user-marker"><div class="pulse"></div></div>',
                iconSize: [22, 22],
                iconAnchor: [11, 11]
            });

            // Crear marcador y círculo de precisión
            this.userLocationMarker = L.marker(latlng, {
                icon: userIcon,
                zIndexOffset: 1000
            }).addTo(this.map);

            // Añadir círculo de precisión
            this.accuracyCircle = L.circle(latlng, {
                radius: accuracy,
                weight: 1,
                color: '#4285F4',
                fillColor: '#4285F4',
                fillOpacity: 0.15
            }).addTo(this.map);

            // Centrar el mapa en la ubicación del usuario al inicio
            this.centerOnUserLocation();
        } else {
            // Actualizar la posición del marcador y círculo
            this.userLocationMarker.setLatLng(latlng);
            this.accuracyCircle.setLatLng(latlng);
            this.accuracyCircle.setRadius(accuracy);
        }

        console.log(`Posición del usuario actualizada: ${latitude}, ${longitude} (precisión: ${accuracy}m)`);
    },

    /**
     * Maneja errores de geolocalización
     */
    onLocationError: function(error) {
        let message = '';
        switch (error.code) {
            case error.PERMISSION_DENIED:
                message = 'Acceso a la ubicación denegado por el usuario.';
                break;
            case error.POSITION_UNAVAILABLE:
                message = 'La información de ubicación no está disponible.';
                break;
            case error.TIMEOUT:
                message = 'Tiempo de espera agotado para obtener la ubicación.';
                break;
            case error.UNKNOWN_ERROR:
            default:
                message = 'Error desconocido al obtener la ubicación.';
                break;
        }
        console.warn(`Error de geolocalización: ${message}`);
        
        // Mostrar mensaje al usuario
        const mapContainer = document.getElementById('map-container');
        if (mapContainer) {
            const errorDiv = L.DomUtil.create('div', 'location-error');
            errorDiv.innerHTML = `<div class="alert alert-warning alert-dismissible fade show" role="alert">
                <strong>Error de ubicación:</strong> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>`;
            mapContainer.prepend(errorDiv);
            
            // Eliminar el mensaje después de 5 segundos
            setTimeout(() => {
                if (errorDiv.parentNode) {
                    errorDiv.parentNode.removeChild(errorDiv);
                }
            }, 5000);
        }
    },

    /**
     * Centra el mapa en la ubicación actual del usuario
     */
    centerOnUserLocation: function() {
        if (this.userLocationMarker) {
            this.map.setView(this.userLocationMarker.getLatLng(), 16);
        } else {
            navigator.geolocation.getCurrentPosition(
                position => {
                    const { latitude, longitude } = position.coords;
                    this.map.setView([latitude, longitude], 16);
                },
                error => this.onLocationError(error),
                this.mapConfig.locationOptions
            );
        }
    },

    /**
     * Aplica los filtros seleccionados
     */
    applyFilters: function() {
        const statusFilter = document.getElementById('statusFilter');
        const status = statusFilter ? statusFilter.value : '';
        
        // Cargar datos con el filtro
        this.loadReportLocations(status);
    }
};

// Al cargar el documento, inicializar el módulo de mapa
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar el módulo de mapa
    if (typeof MapModule !== 'undefined') {
        console.log('Inicializando MapModule desde event listener');
        MapModule.init();
    }
});
