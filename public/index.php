<?php
declare(strict_types=1);

$maybeConfig = __DIR__ . '/api/config.php';
if (file_exists($maybeConfig)) {
    require_once $maybeConfig;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Generador WAN - DashScope Internacional</title>
  <link rel="stylesheet" href="assets/css/style.css" />
</head>
<body>
  <div class="app-container">
    <header>
      <div>
        <h1>WAN Video Studio</h1>
        <p class="subtitle">Genera videos con modelos WAN i2v y t2v de DashScope International y chatea con Qwen en un mismo panel optimizado para hosting compartido.</p>
      </div>
      <nav>
        <button class="tab-button" data-target="panel-t2v">Texto → Video</button>
        <button class="tab-button" data-target="panel-i2v">Imagen → Video</button>
        <button class="tab-button" data-target="panel-chat">Chat Qwen</button>
      </nav>
    </header>

    <section class="panels">
      <article class="panel" id="panel-t2v">
        <h2>Texto → Video (WAN t2v)</h2>
        <form id="t2v-form">
          <label>
            Prompt principal
            <textarea name="prompt" placeholder="Describe la escena, estilo, iluminación…" required></textarea>
          </label>
          <label>
            Formato de salida
            <select name="format">
              <option value="youtube">YouTube (16:9, 1080p)</option>
              <option value="tiktok">TikTok / Reels (9:16, 1080x1920)</option>
              <option value="square">Cuadrado (1:1, 1080x1080)</option>
            </select>
          </label>
          <label>
            Prompt negativo (opcional)
            <input type="text" name="negative_prompt" placeholder="Qué quieres evitar en el video" />
          </label>
          <button class="button-primary" type="submit">Generar video</button>
        </form>
        <div class="status-card">
          <div class="status-header">
            <span>Estado del trabajo</span>
          </div>
          <div class="status-progress"><span class="bar"></span></div>
          <span class="status-message">Esperando…</span>
          <div class="video-result"></div>
        </div>
      </article>

      <article class="panel" id="panel-i2v" style="display:none;">
        <h2>Imagen → Video (WAN i2v)</h2>
        <form id="i2v-form" enctype="multipart/form-data">
          <label>
            Prompt principal
            <textarea name="prompt" placeholder="Explica qué debe ocurrir en la animación" required></textarea>
          </label>
          <label>
            Imagen inicial
            <input type="file" name="image" accept="image/*" required />
          </label>
          <label>
            Formato de salida
            <select name="format">
              <option value="youtube">YouTube (16:9, 1080p)</option>
              <option value="tiktok">TikTok / Reels (9:16, 1080x1920)</option>
              <option value="square">Cuadrado (1:1, 1080x1080)</option>
            </select>
          </label>
          <label>
            Prompt negativo (opcional)
            <input type="text" name="negative_prompt" placeholder="Qué quieres evitar en el video" />
          </label>
          <button class="button-primary" type="submit">Animar imagen</button>
        </form>
        <div class="status-card">
          <div class="status-header">
            <span>Estado del trabajo</span>
          </div>
          <div class="status-progress"><span class="bar"></span></div>
          <span class="status-message">Esperando…</span>
          <div class="video-result"></div>
        </div>
      </article>

      <article class="panel" id="panel-chat" style="display:none;">
        <h2>Chat Qwen en tiempo real</h2>
        <div class="chat-history" aria-live="polite"></div>
        <form id="chat-form">
          <label>
            Instrucción del sistema (opcional)
            <input type="text" name="system" placeholder="Ej. Eres un asistente creativo" />
          </label>
          <label>
            Mensaje
            <textarea name="message" placeholder="Pregunta o describe qué necesitas" required></textarea>
          </label>
          <div style="display:flex; gap:1rem; flex-wrap:wrap; align-items:center;">
            <button class="button-primary" type="submit">Enviar</button>
            <button class="button-secondary" type="button" onclick="document.querySelector('.chat-history').innerHTML=''">Limpiar chat</button>
          </div>
        </form>
      </article>
    </section>
  </div>

  <script src="assets/js/app.js" defer></script>
</body>
</html>
