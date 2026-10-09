/* Initialisation commune de la sidebar */
(function () {
  'use strict';

  const user = JSON.parse(localStorage.getItem('user') || '{}');
  const prenom = user.prenom || '';
  const nom    = user.nom    || '';
  const fullName = [prenom, nom].filter(Boolean).join(' ') || 'Utilisateur';
  const initials = ((prenom[0] || '') + (nom[0] || '')).toUpperCase() || '?';

  const avatarEl  = document.getElementById('sidebarAvatar');
  const nameEl    = document.getElementById('userMenuText');
  const roleEl    = document.getElementById('sidebarRole');

  if (avatarEl) avatarEl.textContent = initials;
  if (nameEl && !nameEl.dataset.managed) nameEl.textContent = fullName;
  if (roleEl) roleEl.textContent = user.role || '';

  /* Logout — certaines pages ajoutent leur propre listener, c'est ok */
  const logoutBtn = document.getElementById('logoutBtn');
  if (logoutBtn && !logoutBtn.dataset.managed) {
    logoutBtn.addEventListener('click', function (e) {
      e.preventDefault();
      localStorage.removeItem('jwt');
      localStorage.removeItem('user');
      window.location.href = '/index.html';
    });
  }

  /* Mobile toggle */
  const toggle  = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');

  if (toggle && sidebar) {
    toggle.addEventListener('click', function () {
      sidebar.classList.toggle('open');
      if (overlay) overlay.classList.toggle('open');
    });
  }
  if (overlay && sidebar) {
    overlay.addEventListener('click', function () {
      sidebar.classList.remove('open');
      overlay.classList.remove('open');
    });
  }
})();
