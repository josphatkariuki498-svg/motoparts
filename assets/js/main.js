// MotoParts - Main JavaScript

// Toast notifications
function showToast(msg, type = 'success') {
  const toast = document.createElement('div');
  toast.className = `alert alert-${type}`;
  toast.style.cssText = `position:fixed;bottom:24px;right:24px;z-index:9999;animation:fadeUp 0.3s ease;min-width:300px;`;
  toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${msg}`;
  document.body.appendChild(toast);
  setTimeout(() => toast.remove(), 3500);
}

// Quantity controls
function adjustQty(inputId, delta, min = 1, max = 999) {
  const input = document.getElementById(inputId);
  if (input) {
    input.value = Math.max(min, Math.min(max, parseInt(input.value || 1) + delta));
  }
}

// Confirm delete
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', e => {
    if (!confirm(el.dataset.confirm)) e.preventDefault();
  });
});

// Auto-dismiss alerts
setTimeout(() => {
  document.querySelectorAll('.alert').forEach(a => {
    a.style.transition = 'opacity 0.5s';
    a.style.opacity = '0';
    setTimeout(() => a.remove(), 500);
  });
}, 4000);

// Table row hover highlight
document.querySelectorAll('tbody tr').forEach(row => {
  row.style.cursor = row.querySelector('a') ? 'pointer' : 'default';
});
