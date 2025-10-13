# Gestio - Sistema de Invitaciones

Este proyecto implementa un flujo completo de registro y control de acceso para eventos utilizando PHP, HTML y JavaScript.

## Características principales

- Registro para empleados y proveedores con campos personalizados.
- Generación de códigos QR únicos y código de respaldo para validaciones sin conexión.
- Envío automático de invitaciones por correo electrónico con plantilla HTML editable.
- Panel administrativo con estadísticas, escaneo de QR (cámara o manual), envíos masivos y generador de QR sin correo.
- Persistencia en archivos CSV para operar en entornos sin base de datos.

## Estructura

- `index.php`: formulario de registro público.
- `register.php`: procesa el alta de invitados.
- `admin/`: panel y utilidades para el equipo del evento.
- `assets/`: estilos y scripts front-end.
- `templates/email_template.html`: diseño del correo enviado a cada invitado.
- `data/registrations.csv`: archivo CSV generado automáticamente con todos los registros.
- `qrcodes/`: imágenes PNG de los QR generados (creadas dinámicamente).

## Acceso administrador

- URL: `/admin/`
- Contraseña: `Fiestasd2025*1`

## Requisitos

- Servidor con PHP 8+.
- Extensión `openssl` habilitada para `random_bytes`.
- Conectividad saliente para generar códigos QR (el sistema probará automáticamente múltiples servicios públicos).
- Permisos de escritura en las carpetas `data/` y `qrcodes/`.

## Descarga

Tenés dos opciones para obtener el proyecto en tu equipo o servidor:

1. **Descarga directa (ZIP)**
   - Entrá a la página del repositorio en GitHub.
   - Hacé clic en el botón **Code ▾** y elegí **Download ZIP**.
   - Descomprimí el archivo y subí el contenido por FTP a tu hosting.

2. **Clonar con Git**
   - En tu terminal ejecutá:
     ```bash
     git clone https://github.com/<tu-usuario>/gestio.git
     ```
   - Copiá los archivos al servidor (por ejemplo con `git pull` en el propio hosting o sincronizando por FTP).

## Personalización

- Modificá `templates/email_template.html` para cambiar el contenido del correo.
- Actualizá `assets/styles.css` para ajustar la estética del sitio.
