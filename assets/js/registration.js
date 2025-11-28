/**
 * REGISTRATION SYSTEM
 * Prodejce/Technik se registrují s klíčem od admina
 */

const registrationForm = document.getElementById('registrationForm');

async function getCsrfToken(form) {
  if (!form) return null;
  const input = form.querySelector('input[name="csrf_token"]');
  if (input && input.value) {
    return input.value;
  }

  try {
    const response = await fetch('app/controllers/get_csrf_token.php', {
      credentials: 'same-origin'
    });
    const data = await response.json();
    if ((data.status === 'success' || data.success === true) && data.token) {
      if (input) input.value = data.token;
      return data.token;
    }
  } catch (error) {
    logger.error('CSRF fetch failed:', error);
  }
  return null;
}

registrationForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const key = document.getElementById('regKey').value.trim();
  const name = document.getElementById('regName').value.trim();
  const email = document.getElementById('regEmail').value.trim();
  const phone = document.getElementById('regPhone').value.trim();
  const password = document.getElementById('regPassword').value;
  const passwordConfirm = document.getElementById('regPasswordConfirm').value;
  
  // Validace
  if (!key || !name || !email || !password) {
    showNotification('Vyplňte všechna povinná pole', 'error');
    return;
  }

  if (password.length < 12) {
    showNotification('Heslo musí mít alespoň 12 znaků', 'error');
    return;
  }
  
  if (password !== passwordConfirm) {
    showNotification('Hesla se neshodují', 'error');
    return;
  }
  
  try {
    const csrfToken = await getCsrfToken(registrationForm);
    if (!csrfToken) {
      showNotification('Nepodařilo se ověřit bezpečnostní token. Obnovte stránku a zkuste to znovu.', 'error');
      return;
    }

    const response = await fetch('app/controllers/registration_controller.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      credentials: 'include',
      body: `registration_key=${encodeURIComponent(key)}&name=${encodeURIComponent(name)}&email=${encodeURIComponent(email)}&phone=${encodeURIComponent(phone)}&password=${encodeURIComponent(password)}&csrf_token=${encodeURIComponent(csrfToken)}`
    });
    
    const data = await response.json();
    
    if (data.status === 'success') {
      showNotification(`Registrace úspěšná! Vaše ID: ${data.user_id}. Přesměrovávám...`, 'success');
      setTimeout(() => {
        window.location.href = 'login.php';
      }, 2000);
    } else {
      showNotification(data.message || 'Registrace selhala', 'error');
    }
  } catch (error) {
    logger.error('Registration error:', error);
    showNotification('Chyba při registraci', 'error');
  }
});

function showNotification(message, type = 'info') {
  const notification = document.getElementById('notification');
  if (!notification) return;
  
  notification.textContent = message;
  notification.className = `notification ${type}`;
  notification.style.display = 'block';
  
  if (type !== 'error') {
    setTimeout(() => {
      notification.style.display = 'none';
    }, 3000);
  }
}

logger.log('Registration system loaded');
