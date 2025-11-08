// Kontrola online stavu
function updateStatus() {
  const status = document.getElementById('status');
  if (navigator.onLine) {
    status.innerHTML = '✅ Připojení obnoveno – klikněte na tlačítko níže';
    status.style.background = 'rgba(0,255,180,0.1)';
    status.style.borderColor = 'rgba(0,255,180,0.4)';
    status.style.color = '#9fffc9';
  } else {
    status.innerHTML = '⚠️ Stále offline – kontroluji připojení...';
  }
}

window.addEventListener('online', updateStatus);
window.addEventListener('offline', updateStatus);

// Periodická kontrola
setInterval(updateStatus, 5000);

// Retry button event listener
document.addEventListener('DOMContentLoaded', () => {
  const retryBtn = document.getElementById('retryBtn');
  if (retryBtn) {
    retryBtn.addEventListener('click', () => {
      location.reload();
    });
  }
});

updateStatus();