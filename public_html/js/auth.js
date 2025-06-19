/**
 * Módulo de autenticación para la aplicación CFEwEB
 * Maneja el inicio de sesión, registro y gestión de tokens JWT
 */

// URL base (index.php) para la API usando AppConfig
const API_BASE_URL = AppConfig.getApiIndexUrl();

// Para depuración
console.log('API URL:', API_BASE_URL);

// Objeto que contiene las funciones relacionadas con la autenticación
const Auth = {
    // Almacena el token JWT
    token: null,
    
    // Almacena información del usuario autenticado
    userData: null,
    
    /**
     * Inicializa el módulo de autenticación
     */
    init: function() {
        // Comprueba si hay un token almacenado en localStorage
        this.token = localStorage.getItem('token');
        this.userData = JSON.parse(localStorage.getItem('userData') || '{}');
        
        // Configura los eventos de formulario de login
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const username = document.getElementById('username').value;
                const password = document.getElementById('password').value;
                this.login(username, password);
            });
        }
        
        // Configura el evento de logout
        const logoutBtn = document.getElementById('btnLogout');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', () => this.logout());
        }
        
        // Comprueba si el usuario está autenticado
        this.checkAuth();
    },
    
    /**
     * Comprueba si el usuario está autenticado
     * Si no está autenticado, muestra el modal de login
     */
    checkAuth: function() {
        if (!this.token) {
            // Mostrar el modal de login
            const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
            loginModal.show();
            return false;
        } else {
            // Actualiza la información del usuario en la interfaz
            this.updateUserInfo();
            return true;
        }
    },
    
    /**
     * Inicia sesión con las credenciales proporcionadas
     * @param {string} username - Nombre de usuario
     * @param {string} password - Contraseña
     */
    login: function(username, password) {
        // Mostrar indicador de carga
        const loginForm = document.getElementById('loginForm');
        const loginError = document.getElementById('loginError');
        loginError.classList.add('d-none');
        loginForm.classList.add('loading');
        
        // Preparar los datos para la solicitud
        const data = {
            username: username,
            password: password
        };
        
        // URL completa para depuración
        const loginUrl = AppConfig.buildQuery('auth', 'login');
        console.log('Intentando login en:', loginUrl);
        
        // Realizar la solicitud a la API con mejor manejo de errores
        fetch(loginUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(response => {
            // Verificar si la respuesta es OK (código 200-299)
            if (!response.ok) {
                throw new Error(`Error HTTP: ${response.status} ${response.statusText}`);
            }
            
            // Verificar el tipo de contenido de la respuesta
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('La respuesta no es JSON válido. Tipo de contenido: ' + contentType);
            }
            
            return response.json();
        })
        .then(data => {
            loginForm.classList.remove('loading');
            
            console.log('Respuesta del servidor:', data);
            
            if ((data.status === 'success' || data.success === true) && data.data && data.data.token) {
                console.log('Login exitoso, guardando token y recargando página...');
                
                // Almacena el token y los datos del usuario
                this.token = data.data.token;
                this.userData = data.data.user;
                
                // Guarda en localStorage
                localStorage.setItem('token', this.token);
                localStorage.setItem('userData', JSON.stringify(this.userData));
                
                // Mostrar un mensaje al usuario
                const loginForm = document.getElementById('loginForm');
                if (loginForm) {
                    // Ocultar el formulario y mostrar mensaje de éxito
                    loginForm.innerHTML = '<div class="alert alert-success">Login exitoso. Cargando aplicación...</div>';
                }
                
                // Cerrar modal de login y recargar datos sin refrescar toda la página
                const modalEl = document.getElementById('loginModal');
                if (modalEl) {
                    const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                    modal.hide();
                }
                // Re-inicializar la app (cargar reportes, mapa, etc.)
                if (typeof Main !== 'undefined' && Main.loadData) {
                    Main.loadData();
                }
                
                return; // Importante: detener la ejecución aquí
            } else {
                // Muestra el mensaje de error
                loginError.textContent = data.message || 'Error de autenticación. Verifique sus credenciales.';
                loginError.classList.remove('d-none');
            }
        })
        .catch(error => {
            loginForm.classList.remove('loading');
            
            // Mensajes de error personalizados según el tipo de error
            let errorMsg = 'Error de conexión. Intente de nuevo más tarde.';
            
            // Error 404: Recurso no encontrado
            if (error.message.includes('404')) {
                errorMsg = 'Error: API no encontrada. Verifique la configuración del servidor.';
            }
            // Error de JSON (como el que aparece en la captura)
            else if (error.message.includes('JSON') || error.message.includes('Unexpected token')) {
                errorMsg = 'Error: El servidor respondió con formato incorrecto. Contacte al administrador.';
            }
            
            loginError.textContent = errorMsg;
            loginError.classList.remove('d-none');
            
            console.error('Error en login:', error.message);
            console.log('Revise la URL de la API:', API_BASE_URL);
        });
    },
    
    /**
     * Cierra la sesión del usuario
     */
    logout: function() {
        // Elimina el token y los datos del usuario
        this.token = null;
        this.userData = null;
        localStorage.removeItem('token');
        localStorage.removeItem('userData');
        
        // Redirige al usuario a la página de inicio
        window.location.reload();
    },
    
    /**
     * Actualiza la información del usuario en la interfaz
     */
    updateUserInfo: function() {
        const userInfoEl = document.getElementById('userInfo');
        if (userInfoEl && this.userData) {
            userInfoEl.textContent = `Usuario: ${this.userData.name || 'Desconocido'}`;
        }
    },
    
    /**
     * Obtiene el token de autenticación para usar en las peticiones
     * @returns {string} El token JWT si existe, o null si no hay token
     */
    getToken: function() {
        return this.token;
    },
    
    /**
     * Comprueba si el usuario está autenticado
     * @returns {boolean} True si el usuario está autenticado, false en caso contrario
     */
    isAuthenticated: function() {
        return !!this.token;
    },
    
    /**
     * Obtiene los datos del usuario autenticado
     * @returns {Object} Datos del usuario autenticado
     */
    getUser: function() {
        return this.userData;
    },
    
    /**
     * Obtiene el ID del usuario autenticado
     * @returns {number|null} ID del usuario autenticado, o null si no hay usuario
     */
    getUserId: function() {
        return this.userData ? this.userData.id : null;
    }
};

// Al cargar el documento, inicializar el módulo de autenticación
document.addEventListener('DOMContentLoaded', function() {
    Auth.init();
});
