<?php
// Copy this file to config.php and add your DashScope International API key.
// You can also set the DASH_SCOPE_API_KEY or DASHSCOPE_API_KEY environment variables instead.
// If you can't use define(), you may alternatively set $DASH_SCOPE_API_KEY = '...';
if (!defined('DASH_SCOPE_API_KEY')) {
    define('DASH_SCOPE_API_KEY', 'YOUR_DASHSCOPE_API_KEY_HERE');
}

// Opcional: descomenta para forzar otra URL base (por ejemplo, edición China).
// define('DASH_SCOPE_API_ENDPOINT', 'https://dashscope.aliyuncs.com/api/v1');

// Opcional: redefine rutas para crear o consultar tareas si tu cuenta usa endpoints distintos.
// $DASH_SCOPE_VIDEO_TASK_CREATE_PATHS = [
//     'services/aigc/video-generation/tasks',
// ];
// $DASH_SCOPE_VIDEO_TASK_STATUS_PATHS = [
//     'services/aigc/video-generation/tasks/%s',
//     'services/aigc/video-generation/tasks?task_id=%s',
// ];

// Opcional: redefine los modelos WAN si recibes "Model not exist" (acepta string o arreglo).
// $DASH_SCOPE_MODEL_T2V = ['wanx-v1.1-t2v', 'wanx-v1-t2v'];
// $DASH_SCOPE_MODEL_I2V = 'wanx-v1.2-i2v';
