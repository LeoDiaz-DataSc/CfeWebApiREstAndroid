/**
 * Archivo de configuración centralizada para la aplicación CFEwEB
 * Define parámetros según el entorno (desarrollo o producción)
 * Victoria de dios, no nuestra
 */

const AppConfig = {
    // Entorno actual
    environment: 'auto', // 'production', 'development', 'auto' (detecta automáticamente)
    
    // API URLs
    api: {
        production: {
            baseUrl: 'https://cfeapirest.cfedis.space',
            timeout: 30000 // 30 segundos
        },
        development: {
            // TEMPORAL: Usando HTTP en lugar de HTTPS para pruebas
            baseUrl: 'http://localhost', 
            timeout: 10000 // 10 segundos
        }
    },
    
    // Configuración de mapas
    maps: {
        defaultCenter: [19.570240, -96.927798], // Xalapa, Veracruz
        defaultZoom: 14,
        maxZoom: 18,
        tileProvider: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        useUserLocation: true, // Solicitar ubicación del usuario
        locationTimeout: 5000, // Tiempo de espera para obtener ubicación (5 segundos)
        locationOptions: {
            enableHighAccuracy: true,
            maximumAge: 30000, // Usar cache de ubicación de hasta 30 segundos
            timeout: 10000 // Timeout de 10 segundos para obtener ubicación
        }
    },
    
    // Intervalos de actualización (en milisegundos)
    refreshIntervals: {
        reports: 300000, // 5 minutos
        chat: 10000,     // 10 segundos
        map: 60000       // 1 minuto
    },
    
    // Parámetros de caché
    cache: {
        enabled: true,
        maxAge: 600000, // 10 minutos
        localStoragePrefix: 'cfe_web_'
    },
    
    /**
     * Determina si la aplicación está en entorno de producción
     * @returns {boolean} Verdadero si estamos en producción
     */
    isProduction: function() {
        if (this.environment === 'production') return true;
        if (this.environment === 'development') return false;
        
        // Detección automática basada en el hostname
        return window.location.hostname === 'cfedis.space' || 
               window.location.hostname === 'www.cfedis.space';
    },
    
    /**
     * Obtiene la URL base de la API según el entorno
     * @returns {string} URL base para las peticiones API
     */
    getApiBaseUrl: function() {
        const env = this.isProduction() ? 'production' : 'development';
        return this.api[env].baseUrl;
    },
    
    /**
     * Combina la URL base con una ruta específica
     * @param {string} path - Ruta específica de la API (por ejemplo, '/auth/login')
     * @returns {string} URL completa para la petición
     */
    getApiUrl: function(path) {
        // Asegurarse de que path empiece con /
        if (!path.startsWith('/')) {
            path = '/' + path;
        }
        return this.getApiBaseUrl() + path;
    },
    
    /**
     * Devuelve la URL a index.php para compatibilidad con la API PHP
     */
    getApiIndexUrl: function() {
        // Mantener compatibilidad con rutas actuales
        return this.getApiBaseUrl() + '/index.php';
    },
    
    /**
     * Construye la URL completa con controlador y acción usando el front-controller PHP
     * @param {string} controller
     * @param {string} [action]
     * @returns {string}
     */
    buildQuery: function(controller, action = '') {
        let url = this.getApiIndexUrl() + `?controller=${encodeURIComponent(controller)}`;
        if (action) url += `&action=${encodeURIComponent(action)}`;
        return url;
    },
    
    /**
     * Inicializa la configuración
     */
    init: function() {
        console.log(`CFEwEB iniciado en modo: ${this.isProduction() ? 'PRODUCCIÓN' : 'DESARROLLO'}`);
        console.log(`API Base URL: ${this.getApiBaseUrl()}`);
    }
};

// Auto-inicializar la configuración
document.addEventListener('DOMContentLoaded', function() {
    AppConfig.init();
});
