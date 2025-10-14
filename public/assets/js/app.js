const tabButtons = document.querySelectorAll('.tab-button');
const panels = document.querySelectorAll('.panel');

function selectTab(targetId) {
  panels.forEach(panel => {
    panel.style.display = panel.id === targetId ? 'block' : 'none';
  });
  tabButtons.forEach(btn => {
    if (btn.dataset.target === targetId) {
      btn.classList.add('active');
    } else {
      btn.classList.remove('active');
    }
  });
}

tabButtons.forEach(btn => {
  btn.addEventListener('click', () => selectTab(btn.dataset.target));
});

selectTab('panel-t2v');

function buildStatusController(wrapper) {
  const card = wrapper.querySelector('.status-card');
  const message = wrapper.querySelector('.status-message');
  const result = wrapper.querySelector('.video-result');

  function show(msg, isError = false) {
    if (!card || !message) return;
    card.classList.add('visible');
    message.textContent = msg;
    message.classList.toggle('error', isError);
  }

  function hide() {
    if (!card) return;
    card.classList.remove('visible');
  }

  function showResult(videoUrl, raw) {
    if (!result) return;
    result.innerHTML = '';
    const link = document.createElement('a');
    link.href = videoUrl;
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
    link.textContent = 'Descargar / Ver video generado';
    result.appendChild(link);

    if (raw) {
      const details = document.createElement('details');
      const summary = document.createElement('summary');
      summary.textContent = 'Ver respuesta completa de la API';
      details.appendChild(summary);
      const pre = document.createElement('pre');
      pre.textContent = JSON.stringify(raw, null, 2);
      details.appendChild(pre);
      result.appendChild(details);
    }
  }

  return { show, hide, showResult };
}

async function startTask(form, mode) {
  const controller = buildStatusController(form.closest('.panel'));
  controller.show('Enviando solicitud a DashScope…');

  const formData = new FormData(form);
  formData.append('mode', mode);

  try {
    const response = await fetch('api/start_task.php', {
      method: 'POST',
      body: formData,
    });

    const data = await response.json();
    if (!response.ok || !data.success) {
      throw new Error(data.error || 'No se pudo iniciar la tarea.');
    }

    const output = data.data?.output || data.data;
    const taskId = output?.task_id || output?.taskId || data.data?.task_id;
    if (!taskId) {
      throw new Error('No se recibió un task_id. Revisa tu clave API o parámetros.');
    }

    controller.show(`Tarea creada (#${taskId}). Procesando video…`);
    pollTask(taskId, controller);
  } catch (error) {
    console.error(error);
    controller.show(error.message, true);
  }
}

async function pollTask(taskId, controller) {
  const poll = async () => {
    try {
      const response = await fetch(`api/check_task.php?taskId=${encodeURIComponent(taskId)}`);
      const data = await response.json();
      if (!response.ok || !data.success) {
        throw new Error(data.error || 'Error al consultar el estado.');
      }

      const payload = data.data;
      const status = payload.status || payload.task_status || payload.output?.task_status;
      const message = payload.message || payload.output?.message || `Estado: ${status}`;

      if (!status) {
        controller.show('Esperando respuesta de DashScope…');
        setTimeout(poll, 4000);
        return;
      }

      if (['PROCESSING', 'PENDING', 'RUNNING'].includes(status.toUpperCase())) {
        controller.show(message || `Procesando (${status})…`);
        setTimeout(poll, 4500);
        return;
      }

      if (status.toUpperCase() === 'SUCCEEDED') {
        const videoUrl = payload.output?.video_url || payload.video_url || '';
        if (!videoUrl) {
          controller.show('Tarea completada pero no se recibió URL de video.', true);
        } else {
          controller.show('¡Video listo! Descarga disponible abajo.');
          controller.showResult(videoUrl, payload);
        }
        return;
      }

      if (status.toUpperCase() === 'FAILED') {
        const errMsg = payload.output?.error || payload.error || 'La generación falló.';
        controller.show(`Error: ${errMsg}`, true);
        return;
      }

      controller.show(message || `Estado: ${status}`);
      setTimeout(poll, 5000);
    } catch (error) {
      controller.show(error.message, true);
    }
  };

  poll();
}

const t2vForm = document.getElementById('t2v-form');
if (t2vForm) {
  t2vForm.addEventListener('submit', event => {
    event.preventDefault();
    startTask(t2vForm, 't2v');
  });
}

const i2vForm = document.getElementById('i2v-form');
if (i2vForm) {
  i2vForm.addEventListener('submit', event => {
    event.preventDefault();
    startTask(i2vForm, 'i2v');
  });
}

const chatForm = document.getElementById('chat-form');
const chatHistory = document.querySelector('.chat-history');

function appendChatBubble(text, role) {
  if (!chatHistory) return;
  const bubble = document.createElement('div');
  bubble.className = `chat-bubble ${role}`;
  bubble.textContent = text;
  chatHistory.appendChild(bubble);
  chatHistory.scrollTop = chatHistory.scrollHeight;
}

if (chatForm) {
  chatForm.addEventListener('submit', async event => {
    event.preventDefault();
    const input = chatForm.querySelector('textarea[name="message"]');
    const system = chatForm.querySelector('input[name="system"]');
    const message = input.value.trim();
    if (!message) return;

    appendChatBubble(message, 'user');
    input.value = '';

    try {
      const response = await fetch('api/chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message, system: system?.value || '' }),
      });
      const data = await response.json();
      if (!response.ok || !data.success) {
        throw new Error(data.error || 'No se pudo obtener la respuesta.');
      }
      appendChatBubble(data.message || 'Sin respuesta.', 'assistant');
    } catch (error) {
      appendChatBubble(`⚠️ ${error.message}`, 'assistant');
    }
  });
}
