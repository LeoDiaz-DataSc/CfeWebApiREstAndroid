/**
 * Script principal para la aplicación CFEwEB
 * Inicializa todos los módulos y gestiona la navegación
 */

// Objeto principal de la aplicación
const App = {
    // Página actual
    currentPage: 'map',
    
    // Modo de producción
    get isProduction() {
        return AppConfig && AppConfig.isProduction();
    },
    
    // Versión de la aplicación
    version: '1.1.0',
    
    // URL de la API
    get apiBaseUrl() {
        return AppConfig ? AppConfig.getApiBaseUrl() : '';
    },
    
    /**
     * Inicializa la aplicación
     */
    init: function() {
        // Mostrar versión y entorno
        console.log(`CFEwEB v${this.version} - Entorno: ${this.isProduction ? 'PRODUCCIÓN' : 'DESARROLLO'}`);
        console.log(`API: ${this.apiBaseUrl}`);
        
        // Inicializar navegación
        this.initNavigation();
        
        // Cargar datos iniciales si hay autenticación
        if (Auth.isAuthenticated()) {
            this.loadData();
        }
        
        // Mostrar notificación en modo producción
        if (this.isProduction) {
            this.showEnvironmentBanner();
        }
    },
    
    /**
     * Muestra una notificación del entorno actual
     */
    showEnvironmentBanner: function() {
        // Crear banner
        const banner = document.createElement('div');
        banner.className = 'environment-banner';
        banner.textContent = 'PRODUCCIÓN';
        document.body.appendChild(banner);
        
        // Agregar estilo
        const style = document.createElement('style');
        style.textContent = `
            .environment-banner {
                position: fixed;
                bottom: 10px;
                right: 10px;
                background-color: rgba(220, 53, 69, 0.8);
                color: white;
                padding: 5px 10px;
                border-radius: 3px;
                font-size: 12px;
                z-index: 9999;
            }
        `;
        document.head.appendChild(style);
    },
    
    /**
     * Inicializa la navegación entre páginas
     */
    initNavigation: function() {
        // Obtener todos los enlaces de navegación
        const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
        
        // Añadir evento de clic a cada enlace
        navLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                
                // Obtener la página de destino
                const targetPage = link.getAttribute('data-page');
                if (targetPage) {
                    this.showPage(targetPage);
                    
                    // Actualizar enlaces activos
                    navLinks.forEach(l => l.classList.remove('active'));
                    link.classList.add('active');
                }
            });
        });
    },
    
    /**
     * Muestra una página específica y oculta las demás
     * @param {string} pageName - Nombre de la página a mostrar
     */
    showPage: function(pageName) {
        // Guardar página actual
        this.currentPage = pageName;
        
        // Obtener todas las páginas
        const pages = document.querySelectorAll('.page-content');
        
        // Ocultar todas las páginas
        pages.forEach(page => {
            page.classList.remove('active');
        });
        
        // Mostrar la página solicitada
        const targetPage = document.getElementById(`${pageName}Page`);
        if (targetPage) {
            targetPage.classList.add('active');
        }
        
        // Detener actualizaciones de chat si no estamos en esa página
        if (pageName !== 'chat') {
            if (ChatModule.intervalId) {
                ChatModule.stopUpdateInterval();
            }
        } else {
            // Reiniciar actualizaciones si estamos en la página de chat
            ChatModule.startUpdateInterval();
        }
    },
    
    /**
     * Carga los datos iniciales de la aplicación
     */
    loadData: function() {
        // Inicializar módulos
        MapModule.init();
        ReportsModule.init();
        ChatModule.init();
    }
};

// Al cargar el documento, inicializar la aplicación
document.addEventListener('DOMContentLoaded', function() {
    App.init();
});
