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
2. Renombra `public/api/config.sample.php` a `config.php` y añade tu clave:
   ```php
   <?php
   define('DASH_SCOPE_API_KEY', 'TU_CLAVE_DASHSCOPE');
   ```
   También puedes usar la variable de entorno `DASHSCOPE_API_KEY` si tu hosting lo permite.
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
