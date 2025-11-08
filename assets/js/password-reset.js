/**
 * PASSWORD RESET SYSTEM
 * Uživatel resetuje heslo s registration klíčem
 */

let currentUserData = null;

// ============================================================
// STEP 1: VERIFY IDENTITY
// ============================================================
const verifyForm = document.getElementById('verifyForm');
if (verifyForm) {
  verifyForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const email = document.getElementById('resetEmail').value.trim();
    const registrationKey = document.getElementById('resetKey').value.trim();
    
    if (!email || !registrationKey) {
      showNotification('Vyplňte email a klíč', 'error');
      return;
    }
    
    try {
      const response = await fetch('app/controllers/password_reset_controller.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        credentials: 'include',
        body: `action=verify&email=${encodeURIComponent(email)}&registration_key=${encodeURIComponent(registrationKey)}`
      });
      
      const data = await response.json();
      
      if (data.status === 'success') {
        showNotification(data.message, 'success');
        currentUserData = {
          email: email,
          registrationKey: registrationKey,
          user: data.user
        };
        
        // Zobrazit jméno uživatele
        document.getElementById('userNameDisplay').textContent = data.user.name;
        
        // Skrýt step 1, zobrazit step 2
        document.getElementById('step1-verify').style.display = 'none';
        document.getElementById('step2-change').style.display = 'block';
      } else {
        showNotification(data.message || 'Ověření selhalo', 'error');
      }
    } catch (error) {
      logger.error('Verify error:', error);
      showNotification('Chyba při ověřování', 'error');
    }
  });
}

// ============================================================
// STEP 2: CHANGE PASSWORD
// ============================================================
const changePasswordForm = document.getElementById('changePasswordForm');
if (changePasswordForm) {
  changePasswordForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const newPassword = document.getElementById('newPassword').value.trim();
    const newPasswordConfirm = document.getElementById('newPasswordConfirm').value.trim();
    
    if (!newPassword || !newPasswordConfirm) {
      showNotification('Vyplňte obě hesla', 'error');
      return;
    }
    
    if (newPassword.length < 8) {
      showNotification('Heslo musí mít alespoň 8 znaků', 'error');
      return;
    }
    
    if (newPassword !== newPasswordConfirm) {
      showNotification('Hesla se neshodují', 'error');
      return;
    }
    
    try {
      const response = await fetch('app/controllers/password_reset_controller.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        credentials: 'include',
        body: `action=change_password&email=${encodeURIComponent(currentUserData.email)}&registration_key=${encodeURIComponent(currentUserData.registrationKey)}&new_password=${encodeURIComponent(newPassword)}&new_password_confirm=${encodeURIComponent(newPasswordConfirm)}`
      });
      
      const data = await response.json();
      
      if (data.status === 'success') {
        showNotification('✅ Heslo úspěšně změněno! Přesměrovávám na přihlášení...', 'success');
        setTimeout(() => {
          window.location.href = 'login.php';
        }, 2000);
      } else {
        showNotification(data.message || 'Změna hesla selhala', 'error');
      }
    } catch (error) {
      logger.error('Change password error:', error);
      showNotification('Chyba při změně hesla', 'error');
    }
  });
}

// ============================================================
// GO BACK
// ============================================================
function goBack() {
  document.getElementById('step1-verify').style.display = 'block';
  document.getElementById('step2-change').style.display = 'none';
  document.getElementById('verifyForm').reset();
  document.getElementById('changePasswordForm').reset();
  currentUserData = null;
}

// ============================================================
// NOTIFICATIONS
// ============================================================
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

logger.log('✅ Password reset system loaded');
