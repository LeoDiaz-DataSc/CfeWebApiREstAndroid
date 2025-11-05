# CfeWebApiREstAndroid
Este es un proyecto de logística implementado para la gestión de reporte de anomalías en un entorno para una empresa federal (CFE) cuyo objetivo es facilitar el manejo de estos, utilizando un entorno de pagina web con una api rest, con la posibilidad de implementar una aplicación Android

FRONT-END - Carpeta public_html
─────────────────────────────────

index.html
Landing page. Contiene la estructura base (navbar, secciones “Mapa”, “Reportes”, “Chat”, etc.) y los modales de login.
Carga Bootstrap 5, FontAwesome y todos los scripts de /js.
new-report.html
Vista autónoma para crear reportes cuando se abre en ventana aparte (por ejemplo desde la app móvil).
css/
styles.css Estilos generales (navbar, cards, etc.).
map-styles.css Estilos específicos del mapa Leaflet.
img/
Recursos estáticos (logo CFE, iconos personalizados).
js/
 (módulos en vanilla JS, patrón IIFE/objeto).
config.js
 Configuración centralizada:
– Detecta entorno (dev/prod) y expone AppConfig.
– Construye URLs de API (
getApiUrl
, 
buildQuery
).
auth.js
 Gestión de autenticación JWT (login, logout, localStorage). Exporte objeto Auth.
main.js
 Orquestador de la SPA: navegación entre secciones, banner de entorno, inicializa módulos (MapModule, ReportsModule, ChatModule).
map.js
 MapModule con Leaflet: muestra markers de reportes, geolocaliza usuario, refresco periódico.
reports.js
 ReportsModule: CRUD de reportes desde la web (tabla, filtros, detalles).
new-report.js
 Lógica para formulario de nuevo reporte (captura de cámara, GPS, validaciones).
chat.js
 ChatModule: polling cada N s, envía y renderiza mensajes.
error-handler.js
 Función global handleError para mostrar alertas y loguear en consola.
.htaccess
 y log/
Reescrituras para despliegue en hosting Apache y carpeta para logs web.
─────────────────────────────────
BACK-END - Carpeta public_html/cfeapirest
─────────────────────────────────

index.php
 (Front-Controller)
Punto único de entrada. Lee controller y action vía $_GET, enruta al Controller correspondiente.
config/
config.php
 Clase 
Config
 autodetecta entorno, rutas de uploads, secretos JWT, CORS, etc.
database.php Conexión PDO a MySQL.
controllers/ (capa de lógica HTTP)
auth_controller.php
 Login (genera token), registro, refresh.
report_controller.php
 CRUD de reportes, subida de imágenes (usa UploadManager).
catalog_controller.php
 Catálogos (grupos, anomalías, materiales).
chat_controller.php
 Mensajes de chat (lista/envío).
map_controller.php
, 
export_controller.php
, 
user_controller.php
 funciones auxiliares.
models/ (ORM manual)
Report.php
, 
Chat.php
, 
Catalog.php
, 
User.php
 encapsulan consultas SQL y validaciones de dominio.
Report_extension.php
 muestra cómo extender lógica sin tocar core.
utils/ (servicios compartidos)
auth.php
 Verifica y decodifica JWT.
jwt_handler.php
 Firmar/verificar tokens.
api_response.php
 Respuestas JSON uniformes (success, error, notFound, etc.).
upload_manager.php
 Guarda imágenes y crea thumbnails.
validator.php
 Helpers de validación de datos.
logger.php
 Escritura en archivos de log.
uploads/
Directorio público donde se guardan las imágenes de reportes (images/).
vendor/
Dependencias instaladas por Composer (Firebase JWT, etc.).
composer.json
Define librerías PHP necesarias.
─────────────────────────────────
FLUJO END-TO-END RESUMIDO

Usuario abre 
index.html
, 
main.js
 inicializa la SPA y muestra el modal de login.
auth.js
 envía POST index.php?controller=auth&action=login.
auth_controller.php
 valida usuario, responde {token,user}.
Token se guarda en localStorage; a partir de aquí cada módulo (
reports.js
, 
chat.js
, etc.) llama a la API con Authorization: Bearer ....
Por ejemplo, al crear reporte:
– 
new-report.js
 hace POST multipart index.php?controller=report&action=create con foto y campos.
– 
ReportController::createReport()
 valida, almacena imagen en uploads/images/, inserta en MySQL y responde con los datos.
map.js
 refresca markers consultando GET index.php?controller=report y pinta en Leaflet.
chat.js
 realiza polling continuo a GET|POST index.php?controller=chat para conversación en tiempo real.
