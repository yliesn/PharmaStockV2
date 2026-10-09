/*
 * Menu latéral commun à toutes les pages de views/.
 *
 * Utilisation dans une page :
 *   <aside class="sidebar" id="sidebar"></aside>
 *   <script src="<chemin>/assets/js/sidebar.js"></script>
 *
 * Le script doit être placé juste après l'<aside> : il construit le menu
 * immédiatement, avant le reste de la page, pour éviter tout clignotement.
 *
 * API exposée : window.Sidebar.setUser(user)
 *   Met à jour le bloc utilisateur et les entrées réservées aux admins
 *   (à appeler après un rafraîchissement du profil via /api/user/me.php).
 *
 * Masquer une entrée admin n'est qu'un confort d'affichage : les droits
 * sont vérifiés côté API (is_admin) et par la page admin elle-même.
 */
(function () {
  'use strict';

  const ROLE_ADMIN = 'ADMIN';

  /* Racine de src/, déduite de l'emplacement de ce script (…/assets/js/sidebar.js) */
  const scriptSrc = (document.currentScript && document.currentScript.src) || '';
  const ROOT = scriptSrc ? new URL('../../', scriptSrc) : new URL('/', location.href);
  const url = (path) => new URL(path, ROOT).href;

  /*
   * Structure du menu. `adminOnly` masque l'entrée pour les non-admins.
   * Une section sans entrée visible n'est pas affichée.
   */
  const MENU = [
    { label: 'Principal', items: [
      { href: 'views/dashboard.html', icon: 'fa-tachometer-alt', text: 'Dashboard' },
    ]},
    { label: 'Stock', items: [
      { href: 'views/supplies/list.html',             icon: 'fa-boxes',             text: 'Produits' },
      { href: 'views/supplies/entry.html',            icon: 'fa-arrow-circle-down', text: 'Entrées' },
      { href: 'views/supplies/exit.html',             icon: 'fa-arrow-circle-up',   text: 'Sorties' },
      { href: 'views/supplies/history.html',          icon: 'fa-list-alt',          text: 'Historique mvt.' },
      { href: 'views/supplies/generate-barcode.html', icon: 'fa-barcode',           text: 'Codes-barres' },
    ]},
    { label: 'Inventaire', items: [
      { href: 'views/inventaires/history.html', icon: 'fa-clipboard-list', text: 'Historique inv.' },
      { href: 'views/inventaires/create.html',  icon: 'fa-plus-circle',    text: 'Nouvel inventaire' },
    ]},
    { label: 'Administration', labelNonAdmin: 'Compte', items: [
      { href: 'views/admin/users.html', icon: 'fa-users',       text: 'Utilisateurs', adminOnly: true },
      { href: 'views/profile.html',     icon: 'fa-user-circle', text: 'Mon profil' },
    ]},
  ];

  /* Pages sans entrée propre : on met en surbrillance l'entrée parente. */
  const ACTIVE_ALIASES = {
    'views/supplies/view.html':     'views/supplies/list.html',
    'views/inventaires/session.html': 'views/inventaires/history.html',
    'views/inventaires/summary.html': 'views/inventaires/history.html',
  };

  function currentMenuHref() {
    const here = location.pathname;
    for (const [page, target] of Object.entries(ACTIVE_ALIASES)) {
      if (new URL(page, ROOT).pathname === here) return target;
    }
    for (const section of MENU) {
      for (const item of section.items) {
        if (new URL(item.href, ROOT).pathname === here) return item.href;
      }
    }
    return null;
  }

  function readCachedUser() {
    try { return JSON.parse(localStorage.getItem('user') || '{}') || {}; }
    catch (e) { return {}; }
  }

  function el(tag, attrs, children) {
    const node = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs || {})) {
      if (k === 'text') node.textContent = v;
      else node.setAttribute(k, v);
    }
    (children || []).forEach((c) => node.appendChild(c));
    return node;
  }

  function icon(name) {
    return el('i', { class: 'fas ' + name });
  }

  function buildNav(isAdmin) {
    const active = currentMenuHref();
    const nav = el('nav', { class: 'sidebar-nav' });

    MENU.forEach((section) => {
      const items = section.items.filter((it) => !it.adminOnly || isAdmin);
      if (!items.length) return;

      const label = (!isAdmin && section.labelNonAdmin) || section.label;
      const block = el('div', { class: 'nav-section' }, [
        el('div', { class: 'nav-section-label', text: label }),
      ]);
      items.forEach((it) => {
        const link = el('a', {
          class: 'sidebar-link' + (it.href === active ? ' active' : ''),
          href: url(it.href),
        }, [icon(it.icon)]);
        link.append(' ' + it.text);
        block.appendChild(link);
      });
      nav.appendChild(block);
    });
    return nav;
  }

  function buildFooter() {
    const logout = el('a', { href: '#', class: 'sidebar-logout', id: 'logoutBtn' }, [icon('fa-sign-out-alt')]);
    logout.append(' Déconnexion');

    return el('div', { class: 'sidebar-footer' }, [
      el('div', { class: 'sidebar-user' }, [
        el('div', { class: 'sidebar-avatar', id: 'sidebarAvatar', text: '?' }),
        el('div', { class: 'sidebar-user-info' }, [
          el('div', { class: 'sidebar-user-name', id: 'userMenuText', text: 'Chargement…' }),
          el('div', { class: 'sidebar-user-role', id: 'sidebarRole' }),
        ]),
      ]),
      logout,
    ]);
  }

  /* ── Rendu ─────────────────────────────────────────────── */

  let sidebar = document.getElementById('sidebar');
  if (!sidebar) {
    console.warn('sidebar.js : élément #sidebar introuvable');
    return;
  }

  let navIsAdmin = null;

  function renderNav(isAdmin) {
    if (navIsAdmin === isAdmin) return;
    navIsAdmin = isAdmin;
    const old = sidebar.querySelector('.sidebar-nav');
    const nav = buildNav(isAdmin);
    if (old) old.replaceWith(nav);
    else sidebar.insertBefore(nav, sidebar.querySelector('.sidebar-footer'));
  }

  const brand = el('a', { class: 'sidebar-brand', href: url('views/dashboard.html') }, [icon('fa-pills')]);
  brand.append(' PharmaStock');
  sidebar.replaceChildren(brand, buildFooter());

  if (!document.getElementById('sidebarOverlay')) {
    sidebar.insertAdjacentElement('afterend', el('div', { class: 'sidebar-overlay', id: 'sidebarOverlay' }));
  }

  function setUser(user) {
    user = user || {};
    const prenom = user.prenom || '';
    const nom = user.nom || '';
    const fullName = [prenom, nom].filter(Boolean).join(' ') || 'Utilisateur';
    const initials = ((prenom[0] || '') + (nom[0] || '')).toUpperCase() || '?';

    document.getElementById('sidebarAvatar').textContent = initials;
    document.getElementById('userMenuText').textContent = fullName;
    document.getElementById('sidebarRole').textContent = user.role || '';

    renderNav(user.role === ROLE_ADMIN);

    /* Garde le cache à jour pour les pages suivantes (ex. rôle modifié par un admin) */
    if (user.id !== undefined) {
      try { localStorage.setItem('user', JSON.stringify({ ...readCachedUser(), ...user })); }
      catch (e) { /* stockage indisponible : on ignore */ }
    }
  }

  setUser(readCachedUser());

  /* ── Comportements ─────────────────────────────────────── */

  document.getElementById('logoutBtn').addEventListener('click', function (e) {
    e.preventDefault();
    localStorage.removeItem('jwt');
    localStorage.removeItem('user');
    window.location.href = url('index.html');
  });

  /* Délégation : le bouton #sidebarToggle est dans la topbar, après ce script. */
  function closeSidebar() {
    sidebar.classList.remove('open');
    const overlay = document.getElementById('sidebarOverlay');
    if (overlay) overlay.classList.remove('open');
  }

  document.addEventListener('click', function (e) {
    if (e.target.closest('#sidebarToggle')) {
      sidebar.classList.toggle('open');
      const overlay = document.getElementById('sidebarOverlay');
      if (overlay) overlay.classList.toggle('open', sidebar.classList.contains('open'));
    } else if (e.target.closest('#sidebarOverlay')) {
      closeSidebar();
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeSidebar();
  });

  window.Sidebar = { setUser: setUser };
})();
