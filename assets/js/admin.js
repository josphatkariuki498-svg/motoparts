// MotoParts Admin - JavaScript

// Close modal on outside click
document.querySelectorAll('[id$="Modal"]').forEach(modal => {
  modal.addEventListener('click', e => {
    if (e.target === modal) modal.style.display = 'none';
  });
});

// Highlight active sidebar link
const currentPath = window.location.pathname;
document.querySelectorAll('.sidebar-link').forEach(link => {
  if (link.href === window.location.href) link.classList.add('active');
});
