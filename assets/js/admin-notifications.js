/**
 * ADMIN NOTIFICATIONS - Email & SMS Management
 */

let notificationState = {
  notifications: [],
  currentEditing: null,
  ccEmails: [],
  bccEmails: []
};

const ADMIN_SESSION_EXPIRED_MESSAGE = 'Vaše administrátorská relace vypršela. Přihlaste se prosím znovu.';

function escapeHtml(text) {
  if (text === null || text === undefined) {
    return '';
  }
  const div = document.createElement('div');
  div.textContent = String(text);
  return div.innerHTML;
}

function redirectToAdminLogin(tab = '') {
  const redirectTarget = tab ? `admin.php?tab=${tab}` : 'admin.php';
  window.location.href = `login.php?redirect=${encodeURIComponent(redirectTarget)}`;
}

function handleNotificationsUnauthorized(response, container, tab = 'notifications') {
  if (response && (response.status === 401 || response.status === 403)) {
    if (container) {
      container.innerHTML = `<div class="error-message">${ADMIN_SESSION_EXPIRED_MESSAGE}</div>`;
    } else {
      alert(ADMIN_SESSION_EXPIRED_MESSAGE);
    }

    setTimeout(() => redirectToAdminLogin(tab), 800);
    return true;
  }

  return false;
}

// ============================================
// LOAD NOTIFICATIONS
// ============================================
async function loadNotifications() {
  const container = document.getElementById('notifications-container');
  if (!container) return;

  try {
    const res = await fetch('/api/notification_list_direct.php', {
      credentials: 'same-origin'
    });

    let result;

    if (!res.ok) {
      if (handleNotificationsUnauthorized(res, container)) {
        return;
      }

      try {
        result = await res.json();
      } catch (parseErr) {
        result = null;
      }

      const message = result?.message ? `: ${result.message}` : '';
      throw new Error(`HTTP ${res.status}${message}`);
    }

    result = await res.json();

    if (result.status === 'success') {
      notificationState.notifications = result.data || [];
      renderNotifications();
    } else {
      container.innerHTML = `<div class="error-message">${result.message || 'Chyba při načítání notifikací'}</div>`;
    }
  } catch (err) {
    console.error('Load notifications failed:', err);
    const message = err && err.message ? err.message : 'Chyba při načítání notifikací';
    container.innerHTML = `<div class="error-message">${message}</div>`;
  }
}

// ============================================
// RENDER NOTIFICATIONS
// ============================================
function renderNotifications() {
  const container = document.getElementById('notifications-container');
  
  if (!notificationState.notifications || notificationState.notifications.length === 0) {
    container.innerHTML = '<div class="loading">Žádné notifikace k zobrazení</div>';
    return;
  }
  
  container.innerHTML = notificationState.notifications.map(notif => {
    const recipientName = {
      'customer': 'Zákazník',
      'admin': 'Admin',
      'technician': 'Technik',
      'seller': 'Prodejce'
    }[notif.recipient_type] || notif.recipient_type;

    const typeName = notif.type === 'both' ? 'Email + SMS' :
                     notif.type === 'email' ? 'Email' : 'SMS';

    const safeName = escapeHtml(notif.name);
    const safeDescription = escapeHtml(notif.description || 'Bez popisu');
    const safeTrigger = escapeHtml(notif.trigger_event || '');
    const safeSubject = escapeHtml(notif.subject || '');
    const safeTemplate = escapeHtml(notif.template || '').replace(/\n/g, '<br>');

    return `
      <div class="notification-card">
        <div class="notification-header" onclick="toggleNotificationBody('${notif.id}')">
          <div class="notification-title">
            <span class="badge badge-${notif.active ? 'active' : 'inactive'}">${notif.active ? 'Aktivní' : 'Neaktivní'}</span>
            <span>${safeName}</span>
          </div>
          <div class="notification-toggle">
            <div class="toggle-switch ${notif.active ? 'active' : ''}"
                 onclick="event.stopPropagation(); toggleNotification('${notif.id}')"></div>
          </div>
        </div>
        <div class="notification-body" id="notification-body-${notif.id}">
          <div class="notification-info">
            <div class="notification-info-label">Popis</div>
            <div class="notification-info-value">${safeDescription}</div>

            <div class="notification-info-label">Spouštěč</div>
            <div class="notification-info-value">${safeTrigger}</div>

            <div class="notification-info-label">Příjemce</div>
            <div class="notification-info-value">${recipientName}</div>

            <div class="notification-info-label">Typ</div>
            <div class="notification-info-value">${typeName}</div>
          </div>

          ${notif.subject ? `
            <div style="margin: 1rem 0;">
              <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.5rem; text-transform: uppercase;">Předmět emailu:</div>
              <div style="background: #f5f5f5; padding: 0.8rem; border: 1px solid #ddd;">${safeSubject}</div>
            </div>
          ` : ''}

          <div style="margin: 1rem 0;">
            <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.5rem; text-transform: uppercase;">Šablona zprávy:</div>
            <div class="template-preview">${safeTemplate}</div>
          </div>

          <button class="btn btn-sm" onclick="openEditNotificationModal('${notif.id}')">Editovat šablonu</button>
        </div>
      </div>
    `;
  }).join('');
}

// ============================================
// TOGGLE HANDLERS
// ============================================
function toggleNotificationBody(notificationId) {
  openEditNotificationModal(notificationId);
}

async function toggleNotification(notificationId) {
  const notif = notificationState.notifications.find(n => n.id == notificationId);
  if (!notif) return;

  const newActive = !notif.active;

  try {
    const csrfToken = typeof getCSRFToken === 'function' ? await getCSRFToken() : null;
    if (!csrfToken) {
      throw new Error('CSRF token not available');
    }

    const res = await fetch('/api/notification_api.php?action=toggle', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ notification_id: notificationId, active: newActive, csrf_token: csrfToken })
    });

    if (!res.ok) {
      if (handleNotificationsUnauthorized(res, null, 'notifications')) {
        return;
      }

      const message = await res.text();
      throw new Error(message || 'Chyba při změně stavu notifikace');
    }

    const result = await res.json();
    if (result.status === 'success') {
      notif.active = newActive;
      renderNotifications();
    }
  } catch (err) {
    console.error('Toggle failed:', err);
    alert('Chyba při změně stavu notifikace');
  }
}

// Load on tab switch
function initNotifications() {
  const urlParams = new URLSearchParams(window.location.search);
  const hasContainer = document.getElementById('notifications-container');
  const tab = urlParams.get('tab');

  // Načíst notifikace jen pokud jsme skutečně na notifications záložce
  if (hasContainer && tab === 'notifications') {
    loadNotifications();
  }
}

// Inicializace - podpora pro defer i normální načítání
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initNotifications);
} else {
  initNotifications();
}

console.log('✅ admin-notifications.js loaded');

// ============================================
// MODAL FUNCTIONS
// ============================================
function openEditNotificationModal(notificationId) {
  const notif = notificationState.notifications.find(n => n.id == notificationId);
  if (!notif) return;
  
  notificationState.currentEditing = notif;
  
  document.getElementById('editNotificationTitle').textContent = `Editovat: ${notif.name}`;
  document.getElementById('edit-recipient').value = notif.recipient_type;
  document.getElementById('edit-subject').value = notif.subject || '';
  document.getElementById('edit-template').value = notif.template;
  
  // Render available variables
  const variablesContainer = document.getElementById('available-variables');
  if (variablesContainer && notif.variables) {
    variablesContainer.innerHTML = notif.variables.map(v => 
      `<span class="variable-tag" onclick="insertVariable('${v}')">${v}</span>`
    ).join('');
  }
  
  // Load CC and BCC emails
  notificationState.ccEmails = notif.cc_emails || [];
  notificationState.bccEmails = notif.bcc_emails || [];
  renderCCEmails();
  renderBCCEmails();
  
  updateTemplatePreview();
  
  const modal = document.getElementById("editNotificationModal");
  modal.style.display = "flex";
  modal.style.position = "fixed";
  modal.style.top = "0";
  modal.style.left = "0";
  modal.style.width = "100vw";
  modal.style.height = "100vh";
  modal.style.alignItems = "center";
  modal.style.justifyContent = "center";
  modal.style.zIndex = "9999";
  
}

function closeEditNotificationModal() {
  document.getElementById('editNotificationModal').style.display = 'none';
  notificationState.currentEditing = null;
  document.getElementById('edit-notification-error').style.display = 'none';
  document.getElementById('edit-notification-success').style.display = 'none';
}

function insertVariable(variable) {
  const textarea = document.getElementById('edit-template');
  const start = textarea.selectionStart;
  const end = textarea.selectionEnd;
  const text = textarea.value;
  
  textarea.value = text.substring(0, start) + variable + text.substring(end);
  textarea.focus();
  textarea.setSelectionRange(start + variable.length, start + variable.length);
  
  updateTemplatePreview();
}

function updateTemplatePreview() {
  const template = document.getElementById('edit-template').value;
  const preview = document.getElementById('template-preview');
  
  if (!preview) return;
  
  if (!template) {
    preview.textContent = 'Začněte psát...';
    return;
  }
  
  // Replace variables with example values
  let previewText = template
    .replace(/\{\{customer_name\}\}/g, 'Jan Novák')
    .replace(/\{\{date\}\}/g, '5.11.2025')
    .replace(/\{\{time\}\}/g, '14:00')
    .replace(/\{\{order_id\}\}/g, 'WGS-12345')
    .replace(/\{\{customer_phone\}\}/g, '+420 123 456 789')
    .replace(/\{\{address\}\}/g, 'Hlavní 123, Praha')
    .replace(/\{\{product\}\}/g, 'Pračka XYZ')
    .replace(/\{\{description\}\}/g, 'Nefunguje odstřeďování')
    .replace(/\{\{technician_name\}\}/g, 'Petr Technik')
    .replace(/\{\{seller_name\}\}/g, 'Marie Prodejce')
    .replace(/\{\{created_at\}\}/g, '5.11.2025 14:30');
  
  preview.textContent = previewText;
}

// Listen for template changes
function attachTemplateListener() {
  const templateInput = document.getElementById('edit-template');
  if (templateInput) {
    templateInput.addEventListener('input', updateTemplatePreview);
  }
}

// Inicializace template listeneru
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', attachTemplateListener);
} else {
  attachTemplateListener();
}

async function saveNotificationTemplate() {
  const errorDiv = document.getElementById('edit-notification-error');
  const successDiv = document.getElementById('edit-notification-success');
  errorDiv.style.display = 'none';
  successDiv.style.display = 'none';
  
  if (!notificationState.currentEditing) return;
  
  const data = {
    recipient: document.getElementById('edit-recipient').value,
    subject: document.getElementById('edit-subject').value,
    template: document.getElementById('edit-template').value,
    cc_emails: notificationState.ccEmails,
    bcc_emails: notificationState.bccEmails
  };
  
  if (!data.template.trim()) {
    errorDiv.textContent = 'Text zprávy nesmí být prázdný';
    errorDiv.style.display = 'block';
    return;
  }
  
  try {
    const csrfToken = typeof getCSRFToken === 'function' ? await getCSRFToken() : null;
    if (!csrfToken) {
      throw new Error('CSRF token not available');
    }

    const res = await fetch('/api/notification_api.php?action=update', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: notificationState.currentEditing.id, ...data, csrf_token: csrfToken })
    });

    if (!res.ok) {
      if (handleNotificationsUnauthorized(res, null, 'notifications')) {
        return;
      }

      const message = await res.text();
      throw new Error(message || 'Chyba při ukládání šablony');
    }

    const result = await res.json();
    if (result.status === 'success') {
      successDiv.textContent = 'Šablona byla úspěšně uložena!';
      successDiv.style.display = 'block';

      // Update state
      notificationState.notifications = notificationState.notifications.map(n => 
        n.id === notificationState.currentEditing.id 
          ? { ...n, ...data, recipient_type: data.recipient }
          : n
      );
      
      renderNotifications();
      
      setTimeout(() => {
        closeEditNotificationModal();
      }, 1500);
    } else {
      errorDiv.textContent = result.message || 'Chyba při ukládání šablony';
      errorDiv.style.display = 'block';
    }
  } catch (err) {
    errorDiv.textContent = 'Chyba při ukládání šablony';
    errorDiv.style.display = 'block';
    console.error(err);
  }
}

// ============================================
// CC/BCC EMAIL FUNCTIONS
// ============================================
function addCCEmail() {
  const input = document.getElementById('new-cc-email');
  const email = input.value.trim();
  
  if (!email) return;
  if (!email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
    alert('Neplatný formát emailu');
    return;
  }
  if (notificationState.ccEmails.includes(email)) {
    alert('Tento email už je přidán');
    return;
  }
  
  notificationState.ccEmails.push(email);
  input.value = '';
  renderCCEmails();
}

function removeCCEmail(email) {
  notificationState.ccEmails = notificationState.ccEmails.filter(e => e !== email);
  renderCCEmails();
}

function renderCCEmails() {
  const container = document.getElementById('cc-emails-list');
  if (!container) return;
  container.innerHTML = notificationState.ccEmails.map(email => `
    <div class="email-tag">
      ${email}
      <span class="email-tag-remove" onclick="removeCCEmail('${email}')">×</span>
    </div>
  `).join('');
}

function addBCCEmail() {
  const input = document.getElementById('new-bcc-email');
  const email = input.value.trim();
  
  if (!email) return;
  if (!email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
    alert('Neplatný formát emailu');
    return;
  }
  if (notificationState.bccEmails.includes(email)) {
    alert('Tento email už je přidán');
    return;
  }
  
  notificationState.bccEmails.push(email);
  input.value = '';
  renderBCCEmails();
}

function removeBCCEmail(email) {
  notificationState.bccEmails = notificationState.bccEmails.filter(e => e !== email);
  renderBCCEmails();
}

function renderBCCEmails() {
  const container = document.getElementById('bcc-emails-list');
  if (!container) return;
  container.innerHTML = notificationState.bccEmails.map(email => `
    <div class="email-tag">
      ${email}
      <span class="email-tag-remove" onclick="removeBCCEmail('${email}')">×</span>
    </div>
  `).join('');
}

console.log('✅ Modal functions loaded');

// Override pro zajištění správné velikosti
function addModalStyles() {
  const style = document.createElement('style');
  style.textContent = `
    #editNotificationModal {
      display: none !important;
    }
    #editNotificationModal[style*="display: flex"] {
      display: flex !important;
      position: fixed !important;
      top: 0 !important;
      left: 0 !important;
      width: 100vw !important;
      height: 100vh !important;
      align-items: center !important;
      justify-content: center !important;
    }
    #editNotificationModal .modal-content {
      width: 90vw !important;
      height: 80vh !important;
      max-width: 90vw !important;
      max-height: 80vh !important;
    }
  `;
  document.head.appendChild(style);
}

// Inicializace stylů
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', addModalStyles);
} else {
  addModalStyles();
}
