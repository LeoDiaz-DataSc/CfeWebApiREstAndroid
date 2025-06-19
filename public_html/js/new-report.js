/**
 * Módulo para manejar la creación de nuevos reportes
 */
const NewReport = {
    // Elementos del DOM
    elements: {
        form: document.getElementById('reportForm'),
        matricula: document.getElementById('matricula'),
        grupo: document.getElementById('grupo'),
        anomalia: document.getElementById('anomalia'),
        material: document.getElementById('material'),
        descripcion: document.getElementById('descripcion'),
        ubicacion: document.getElementById('ubicacion'),
        btnGetLocation: document.getElementById('btnGetLocation'),
        image: document.getElementById('image'),
        imagePreview: document.getElementById('imagePreview'),
        imagePreviewImg: document.querySelector('#imagePreview img')
    },

    // Estado del formulario
    state: {
        hasLocation: false,
        hasImage: false,
        latitude: null,
        longitude: null
    },

    // Inicializar el módulo
    init: function() {
        // Verificar autenticación
        if (!Auth.isAuthenticated()) {
            window.location.href = AppConfig.buildQuery('auth', 'login');
            return;
        }

        // Cargar datos iniciales
        this.loadInitialData();

        // Configurar eventos
        this.setupEvents();
    },

    // Cargar datos iniciales (grupos, anomalías, materiales)
    loadInitialData: async function() {
        try {
            // Cargar grupos
            const grupos = await this.loadGroups();
            this.populateSelect(this.elements.grupo, grupos, 'grupo_nombre');

            // Cargar anomalías
            const anomalías = await this.loadAnomalies();
            this.populateSelect(this.elements.anomalia, anomalías, 'anomalia_nombre');

            // Cargar materiales
            const materiales = await this.loadMaterials();
            this.populateSelect(this.elements.material, materiales, 'material_nombre');
        } catch (error) {
            console.error('Error al cargar datos iniciales:', error);
            alert('Error al cargar los datos necesarios. Por favor, inténtelo de nuevo.');
        }
    },

    // Cargar grupos desde la API
    loadGroups: async function() {
        const response = await fetch(AppConfig.buildQuery('catalog', 'groups'), {
            headers: {
                'Authorization': `Bearer ${Auth.getToken()}`
            }
        });
        const data = await response.json();
        return data.data || [];
    },

    // Cargar anomalías desde la API
    loadAnomalies: async function() {
        const response = await fetch(AppConfig.buildQuery('catalog', 'anomalies'), {
            headers: {
                'Authorization': `Bearer ${Auth.getToken()}`
            }
        });
        const data = await response.json();
        return data.data || [];
    },

    // Cargar materiales desde la API
    loadMaterials: async function() {
        const response = await fetch(AppConfig.buildQuery('catalog', 'materials'), {
            headers: {
                'Authorization': `Bearer ${Auth.getToken()}`
            }
        });
        const data = await response.json();
        return data.data || [];
    },

    // Poblar un select con datos
    populateSelect: function(select, data, labelField) {
        data.forEach(item => {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = item[labelField];
            select.appendChild(option);
        });
    },

    // Configurar eventos
    setupEvents: function() {
        // Evento para obtener ubicación
        this.elements.btnGetLocation.addEventListener('click', () => this.getLocation());

        // Evento para previsualizar imagen
        this.elements.image.addEventListener('change', (e) => this.previewImage(e));

        // Evento de envío del formulario
        this.elements.form.addEventListener('submit', (e) => this.handleSubmit(e));
    },

    // Obtener ubicación del usuario
    getLocation: async function() {
        // Verificar si el navegador soporta geolocalización
        if (!navigator.geolocation) {
            this.showError('La geolocalización no está disponible en su navegador.');
            return;
        }

        // Verificar el estado del permiso
        const permission = await this.checkGeolocationPermission();
        if (permission === 'denied') {
            this.showError('Por favor, permita el acceso a su ubicación en las configuraciones del navegador.');
            return;
        }

        this.elements.btnGetLocation.disabled = true;
        this.elements.btnGetLocation.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        try {
            const position = await new Promise((resolve, reject) => {
                navigator.geolocation.getCurrentPosition(resolve, reject, {
                    timeout: 10000,
                    maximumAge: 0,
                    enableHighAccuracy: true
                });
            });

            this.state.latitude = position.coords.latitude;
            this.state.longitude = position.coords.longitude;
            this.state.hasLocation = true;
            
            this.elements.ubicacion.value = `${position.coords.latitude.toFixed(6)}, ${position.coords.longitude.toFixed(6)}`;
            this.elements.ubicacion.classList.remove('is-invalid');
            
            this.elements.btnGetLocation.innerHTML = '<i class="fas fa-location-dot"></i> Ubicación Obtenida';
            this.elements.btnGetLocation.classList.add('btn-success');
        } catch (error) {
            this.elements.btnGetLocation.innerHTML = '<i class="fas fa-location-dot"></i> Obtener Ubicación';
            this.elements.btnGetLocation.disabled = false;
            
            let errorMessage = 'Error al obtener su ubicación.';
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    errorMessage = 'Por favor, permita el acceso a su ubicación.';
                    break;
                case error.POSITION_UNAVAILABLE:
                    errorMessage = 'No se pudo obtener su ubicación.';
                    break;
                case error.TIMEOUT:
                    errorMessage = 'Tiempo agotado al obtener su ubicación.';
                    break;
            }
            this.showError(errorMessage);
        }
    },

    // Verificar estado del permiso de geolocalización
    checkGeolocationPermission: async function() {
        if (!navigator.permissions) {
            return 'granted'; // Para navegadores que no soportan la API de permisos
        }

        try {
            const permission = await navigator.permissions.query({ name: 'geolocation' });
            return permission.state;
        } catch (error) {
            return 'granted'; // Si hay error, asumimos permiso concedido
        }
    },

    // Mostrar mensaje de error con estilos
    showError: function(message) {
        alert(message);
        // También podríamos mostrar un mensaje más visual en el formulario
        this.elements.ubicacion.classList.add('is-invalid');
    },

    // Manejar subida de archivo
    handleFileUpload: function(e) {
        const file = e.target.files[0];
        if (!file) return;

        // Validar tipo de archivo
        if (!file.type.startsWith('image/')) {
            this.showError('Solo se permiten archivos de imagen (JPG, PNG, GIF)');
            this.elements.image.value = ''; // Limpiar el input
            return;
        }

        // Validar tamaño máximo (2MB)
        const maxSize = 2 * 1024 * 1024; // 2MB
        if (file.size > maxSize) {
            this.showError('El archivo es demasiado grande. Máximo permitido: 2MB.');
            this.elements.image.value = '';
            return;
        }

        // Previsualizar la imagen
        const reader = new FileReader();
        reader.onload = (e) => {
            this.elements.imagePreviewImg.src = e.target.result;
            this.elements.imagePreviewImg.style.display = 'block';
            this.state.hasImage = true;
            
            // Limpiar el mensaje de error si existe
            this.elements.image.classList.remove('is-invalid');
        };
        reader.readAsDataURL(file);
    },

    // Manejar envío del formulario
    handleSubmit: async function(e) {
        e.preventDefault();

        // Validar formulario
        if (!this.elements.form.checkValidity()) {
            e.stopPropagation();
            this.elements.form.classList.add('was-validated');
            return;
        }

        // Validar ubicación e imagen
        if (!this.state.hasLocation) {
            this.elements.ubicacion.classList.add('is-invalid');
            this.showError('Por favor, obtén tu ubicación primero.');
            return;
        }

        if (!this.state.hasImage) {
            this.elements.image.classList.add('is-invalid');
            this.showError('Por favor, selecciona una imagen.');
            return;
        }

        // Deshabilitar botón de envío
        const submitBtn = e.submitter;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';

        // Configurar timeout
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 30000); // 30 segundos

        try {
            // Crear FormData
            const formData = new FormData();
            formData.append('matricula', this.elements.matricula.value);
            formData.append('grupo_id', this.elements.grupo.value);
            formData.append('anomalia_id', this.elements.anomalia.value);
            formData.append('material_id', this.elements.material.value);
            formData.append('descripcion', this.elements.descripcion.value);
            formData.append('latitude', this.state.latitude);
            formData.append('longitude', this.state.longitude);
            formData.append('image', this.elements.image.files[0]);

            // Enviar a la API
            const response = await fetch(AppConfig.buildQuery('reports'), {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${Auth.getToken()}`,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData,
                signal: controller.signal
            });

            const data = await response.json();

            if (response.ok) {
                alert('Reporte enviado exitosamente!');
                window.location.href = AppConfig.buildQuery('reports');
            } else {
                throw new Error(data.message || 'Error al enviar el reporte.');
            }
        } catch (error) {
            if (error.name === 'AbortError') {
                this.showError('Tiempo de espera agotado. Por favor, intenta de nuevo.');
            } else {
                console.error('Error detallado:', error);
                this.showError('Error al enviar el reporte. Por favor, intenta de nuevo.');
            }
        } finally {
            clearTimeout(timeoutId);
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Enviar Reporte';
        }
    }
};

// Inicializar el módulo cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    NewReport.init();
});
