/**
 * Módulo centralizado para manejo de errores
 */
const ErrorHandler = {
    // Tipos de errores
    ERROR_TYPES: {
        NETWORK: 'network',
        AUTH: 'auth',
        PERMISSION: 'permission',
        SERVER: 'server',
        VALIDATION: 'validation',
        UNKNOWN: 'unknown'
    },

    // Manejar errores generales
    handleError: function(error, response) {
        console.error('Error:', error);
        
        const errorType = this.getErrorType(error, response);
        this.showErrorMessage(errorType, error, response);
        
        // Registrar el error
        this.logError(error, response);
    },

    // Determinar el tipo de error
    getErrorType: function(error, response) {
        if (!response) {
            return this.ERROR_TYPES.NETWORK;
        }

        const status = response?.status || 0;
        
        if (status === 401) return this.ERROR_TYPES.AUTH;
        if (status === 403) return this.ERROR_TYPES.PERMISSION;
        if (status >= 500) return this.ERROR_TYPES.SERVER;
        
        return this.ERROR_TYPES.UNKNOWN;
    },

    // Mostrar mensaje de error al usuario
    showErrorMessage: function(type, error, response) {
        let message = 'Error desconocido. Por favor, intenta de nuevo.';
        
        switch(type) {
            case this.ERROR_TYPES.NETWORK:
                message = 'Error de conexión con el servidor. Por favor, verifica tu conexión a internet.';
                break;
            case this.ERROR_TYPES.AUTH:
                message = 'Sesión expirada. Por favor, inicia sesión nuevamente.';
                window.location.href = AppConfig.buildQuery('auth', 'login');
                break;
            case this.ERROR_TYPES.PERMISSION:
                message = 'No tienes permisos para realizar esta acción.';
                break;
            case this.ERROR_TYPES.SERVER:
                message = 'Error interno del servidor. Por favor, intenta más tarde.';
                break;
            case this.ERROR_TYPES.VALIDATION:
                message = error.message || 'Datos inválidos. Por favor, verifica la información.';
                break;
        }
        
        this.showError(message);
    },

    // Mostrar mensaje de error en el DOM
    showError: function(message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-danger alert-dismissible fade show';
        errorDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        // Insertar el mensaje de error en el contenedor apropiado
        const container = document.querySelector('.container-fluid');
        if (container) {
            container.insertBefore(errorDiv, container.firstChild);
        }
    },

    // Registrar error en el servidor
    logError: function(error, response) {
        try {
            // Solo enviar si hay token de autenticación
            if (Auth.getToken()) {
                fetch(AppConfig.buildQuery('logs', 'error'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${Auth.getToken()}`
                    },
                    body: JSON.stringify({
                        error: error.message,
                        type: this.getErrorType(error, response),
                        response: response?.status,
                        timestamp: new Date().toISOString()
                    })
                }).catch(console.error);
            }
        } catch (e) {
            console.error('Error al registrar error:', e);
        }
    }
};

// Exportar el módulo
window.ErrorHandler = ErrorHandler;
