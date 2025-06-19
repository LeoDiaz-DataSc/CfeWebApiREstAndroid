/**
 * Módulo de chat para la aplicación CFEwEB
 * Maneja la comunicación en tiempo real entre usuarios
 */

const ChatModule = {
    // Intervalo de actualización del chat (en ms, usando la configuración centralizada)
    get updateInterval() {
        return AppConfig ? AppConfig.refreshIntervals.chat : 5000;
    },
    
    // ID del intervalo para limpieza
    intervalId: null,
    
    // Último ID de mensaje recuperado
    lastMessageId: 0,
    
    // Datos de mensajes
    messages: [],
    
    /**
     * Inicializa el módulo de chat
     */
    init: function() {
        // Configurar el evento para enviar mensajes
        const sendBtn = document.getElementById('btnSendMessage');
        const messageInput = document.getElementById('messageInput');
        
        if (sendBtn && messageInput) {
            // Evento de clic en el botón de enviar
            sendBtn.addEventListener('click', () => {
                const message = messageInput.value.trim();
                if (message) {
                    this.sendMessage(message);
                    messageInput.value = '';
                }
            });
            
            // Evento de tecla Enter en el input de mensaje
            messageInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    const message = messageInput.value.trim();
                    if (message) {
                        this.sendMessage(message);
                        messageInput.value = '';
                        e.preventDefault();
                    }
                }
            });
        }
        
        // Cargar mensajes iniciales
        this.loadMessages();
        
        // Configurar actualización periódica
        this.startUpdateInterval();
    },
    
    /**
     * Inicia el intervalo de actualización del chat
     */
    startUpdateInterval: function() {
        // Limpiar intervalo anterior si existe
        if (this.intervalId) {
            clearInterval(this.intervalId);
        }
        
        // Crear nuevo intervalo
        this.intervalId = setInterval(() => {
            this.loadMessages();
        }, this.updateInterval);
    },
    
    /**
     * Detiene el intervalo de actualización del chat
     */
    stopUpdateInterval: function() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
    },
    
    /**
     * Carga los mensajes del chat desde la API
     */
    loadMessages: function() {
        // Si no hay autenticación, no continuar
        if (!Auth.isAuthenticated()) return;
        
        // URL de la API para obtener mensajes
        const url = AppConfig.buildQuery('chat');
        
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
                // Verificar si hay nuevos mensajes
                const newMessages = data.data.filter(msg => msg.id > this.lastMessageId);
                
                if (newMessages.length > 0) {
                    // Actualizar último ID de mensaje
                    this.lastMessageId = Math.max(...data.data.map(msg => msg.id));
                    
                    // Guardar todos los mensajes
                    this.messages = data.data;
                    
                    // Renderizar mensajes
                    this.renderMessages();
                    
                    // Actualizar lista de usuarios
                    this.updateUsersList();
                }
            } else {
                console.error('Error al cargar mensajes:', data.message);
            }
        })
        .catch(error => {
            console.error('Error de conexión al cargar mensajes:', error);
        });
    },
    
    /**
     * Renderiza los mensajes en el contenedor de chat
     */
    renderMessages: function() {
        const chatContainer = document.getElementById('chatMessages');
        if (!chatContainer) return;
        
        // Recordar la posición de scroll actual
        const isScrolledToBottom = chatContainer.scrollHeight - chatContainer.clientHeight <= chatContainer.scrollTop + 10;
        
        // Vaciar el contenedor
        chatContainer.innerHTML = '';
        
        // Obtener ID del usuario actual
        const currentUserId = Auth.getUserId();
        
        // Renderizar cada mensaje
        this.messages.forEach(message => {
            const isMine = message.user_id === currentUserId;
            
            // Crear el elemento del mensaje
            const messageDiv = document.createElement('div');
            messageDiv.className = `chat-message ${isMine ? 'message-sent' : 'message-received'}`;
            
            // Formatear la fecha
            const messageDate = new Date(message.created_at);
            const formattedDate = messageDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) + 
                                ' ' + messageDate.toLocaleDateString();
            
            // Crear el contenido del mensaje
            messageDiv.innerHTML = `
                <div class="message-header">
                    <span class="message-sender">${isMine ? 'Yo' : (message.user_name || 'Usuario')}</span>
                    <span class="message-time">${formattedDate}</span>
                </div>
                <div class="message-content">${message.message}</div>
            `;
            
            // Añadir el mensaje al contenedor
            chatContainer.appendChild(messageDiv);
        });
        
        // Si estaba desplazado hasta abajo, mantenerlo así después de añadir nuevos mensajes
        if (isScrolledToBottom) {
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }
    },
    
    /**
     * Actualiza la lista de usuarios conectados
     */
    updateUsersList: function() {
        const usersListEl = document.getElementById('usersList');
        if (!usersListEl) return;
        
        // Obtener lista de usuarios únicos de los mensajes
        const uniqueUsers = {};
        this.messages.forEach(message => {
            if (message.user_id && message.user_name) {
                uniqueUsers[message.user_id] = message.user_name;
            }
        });
        
        // Vaciar la lista
        usersListEl.innerHTML = '';
        
        // Si no hay usuarios
        if (Object.keys(uniqueUsers).length === 0) {
            usersListEl.innerHTML = '<li class="list-group-item">No hay usuarios conectados</li>';
            return;
        }
        
        // Añadir cada usuario a la lista
        Object.entries(uniqueUsers).forEach(([userId, userName]) => {
            const isCurrentUser = userId === Auth.getUserId();
            
            const userItem = document.createElement('li');
            userItem.className = 'list-group-item d-flex justify-content-between align-items-center';
            
            userItem.innerHTML = `
                ${userName} ${isCurrentUser ? '<span class="text-primary">(Tú)</span>' : ''}
                <span class="badge bg-success rounded-pill">
                    <i class="fas fa-circle fa-sm"></i>
                </span>
            `;
            
            usersListEl.appendChild(userItem);
        });
    },
    
    /**
     * Envía un mensaje al chat
     * @param {string} message - Contenido del mensaje
     */
    sendMessage: function(message) {
        // Si no hay autenticación, no continuar
        if (!Auth.isAuthenticated()) return;
        
        // Validar mensaje
        if (!message || message.trim() === '') return;
        
        // Desactivar botón de envío
        const sendBtn = document.getElementById('btnSendMessage');
        const messageInput = document.getElementById('messageInput');
        
        if (sendBtn) {
            sendBtn.disabled = true;
            sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
        }
        
        // URL de la API para enviar mensajes
        const url = AppConfig.buildQuery('chat');
        
        // Datos para la solicitud
        const data = {
            message: message.trim()
        };
        
        // Realizar la solicitud a la API
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${Auth.getToken()}`
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            // Reactivar botón de envío
            if (sendBtn) {
                sendBtn.disabled = false;
                sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar';
            }
            
            if (data.status === 'success') {
                // Limpiar el input de mensaje
                if (messageInput) messageInput.value = '';
                
                // Cargar mensajes actualizados inmediatamente
                this.loadMessages();
            } else {
                alert('Error al enviar el mensaje: ' + (data.message || 'Error desconocido'));
            }
        })
        .catch(error => {
            // Reactivar botón de envío
            if (sendBtn) {
                sendBtn.disabled = false;
                sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar';
            }
            
            alert('Error de conexión al enviar el mensaje');
            console.error('Error al enviar mensaje:', error);
        });
    }
};

// Al cargar el documento, inicializar el módulo de chat
document.addEventListener('DOMContentLoaded', function() {
    // Se inicializará desde main.js
});
