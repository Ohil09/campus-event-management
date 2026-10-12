/**
 * Campus Event & Volunteer Management System
 * Shared client-side functionality.
 */

(function () {
    'use strict';

    /* -------------------------------------------------------
       Mobile sidebar toggle
    ------------------------------------------------------- */
    const menuToggle = document.querySelector('.menu-toggle');
    const sidebar = document.getElementById('organizer-navigation');

    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function () {
            const isExpanded = menuToggle.getAttribute('aria-expanded') === 'true';
            menuToggle.setAttribute('aria-expanded', String(!isExpanded));
            sidebar.classList.toggle('sidebar-open');
        });
    }
})();
