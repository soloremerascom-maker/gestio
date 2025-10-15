# WAN Video Studio (DashScope International)

Aplicación web mínima en PHP + HTML + CSS + JS pensada para hosting compartido (por ejemplo Hostinger) que permite:

- Generar video texto → video (t2v) con modelos WAN.
- Generar video imagen → video (i2v) con los modelos WAN.
- Conversar con modelos Qwen en un chat síncrono.

## Requisitos

- PHP 8.0 o superior con cURL habilitado.
- Clave de API de [Alibaba Cloud DashScope International Edition](https://dashscope-intl.aliyuncs.com/).

No requiere Node, Composer ni frameworks adicionales.

## Instalación

1. Copia la carpeta `public` al directorio público de tu hosting.
2. Copia `public/api/config.sample.php` a `config.php` y añade tu clave:
   ```php
   <?php
   define('DASH_SCOPE_API_KEY', 'TU_CLAVE_DASHSCOPE');
   ```
   Si lo prefieres, también puedes declarar una variable global en vez del `define`:
   ```php
   <?php
   $DASH_SCOPE_API_KEY = 'TU_CLAVE_DASHSCOPE';
   ```
   Si tu hosting no permite crear archivos nuevos, edita directamente `config.sample.php` con cualquiera de las dos opciones; la
   aplicación cargará ambos archivos siempre que el valor no sea el texto por defecto. También puedes usar las variables de entorno
   `DASH_SCOPE_API_KEY` o `DASHSCOPE_API_KEY` si tu hosting lo permite; la aplicación las buscará automáticamente tanto en `getenv()`
   como en los arreglos `$_ENV` y `$_SERVER`.
   Cuando recibas errores 404 de la API es probable que tu cuenta use rutas distintas; en ese caso descomenta y ajusta en `config.php`
   la constante `DASH_SCOPE_API_ENDPOINT` y las listas `$DASH_SCOPE_VIDEO_TASK_CREATE_PATHS` / `$DASH_SCOPE_VIDEO_TASK_STATUS_PATHS`
   para que apunten a los endpoints reales que te indique la documentación de tu región.
3. Asegúrate de que la carpeta `public/uploads` tenga permisos de escritura si planeas guardar imágenes (por defecto solo se usan temporalmente).

## Uso

- **Texto → Video**: Escribe el prompt, elige formato (YouTube 16:9, TikTok 9:16 o cuadrado) y envía. Se mostrará una tarjeta de progreso con actualización periódica del estado hasta obtener la URL del video.
- **Imagen → Video**: Carga una imagen base, añade el prompt y formato deseado. El archivo se transforma a Base64 y se envía a DashScope.
- **Chat Qwen**: Interfaz simple para consultas rápidas con respuesta inmediata.

Si ocurre un error, la tarjeta de estado lo mostrará en rojo con el mensaje devuelto por la API.

## Personalización

- Modifica `public/assets/css/style.css` para ajustar la apariencia.
- Ajusta modelos o parámetros en `public/api/dashscope_client.php` según la versión del modelo WAN/Qwen que prefieras.

## Seguridad

- Mantén tu `config.php` fuera del control de versiones.
- Asegúrate de usar HTTPS en producción.
- Limita el tamaño máximo de subida de archivos (e.g., en `.htaccess` o `php.ini`).
