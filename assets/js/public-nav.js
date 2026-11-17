(function () {
    const mobileToggle = document.querySelector('.public-mobile-toggle');
    const mobileNav = document.getElementById('public-mobile-nav');
    const dropdowns = document.querySelectorAll('.public-nav-dropdown, .public-mobile-dropdown');

    function setDropdownOpen(dropdown, isOpen) {
        const toggle = dropdown.querySelector('button');
        const menu = dropdown.querySelector('[id$="services-menu"]');
        if (!toggle || !menu) {
            return;
        }
        toggle.setAttribute('aria-expanded', String(isOpen));
        if (dropdown.classList.contains('public-mobile-dropdown')) {
            menu.hidden = !isOpen;
        }
    }

    dropdowns.forEach(function (dropdown) {
        const toggle = dropdown.querySelector('button');
        if (!toggle) {
            return;
        }
        toggle.addEventListener('click', function () {
            setDropdownOpen(dropdown, toggle.getAttribute('aria-expanded') !== 'true');
        });
        dropdown.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                setDropdownOpen(dropdown, false);
                toggle.focus();
            }
        });
    });

    document.addEventListener('click', function (event) {
        dropdowns.forEach(function (dropdown) {
            if (!dropdown.contains(event.target)) {
                setDropdownOpen(dropdown, false);
            }
        });
    });

    function setMobileNavOpen(isOpen) {
        if (!mobileToggle || !mobileNav) {
            return;
        }
        mobileToggle.setAttribute('aria-expanded', String(isOpen));
        mobileToggle.setAttribute('aria-label', isOpen ? 'Tutup navigasi' : 'Buka navigasi');
        mobileNav.hidden = !isOpen;
    }

    if (mobileToggle && mobileNav) {
        mobileToggle.addEventListener('click', function () {
            setMobileNavOpen(mobileToggle.getAttribute('aria-expanded') !== 'true');
        });

        mobileNav.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                setMobileNavOpen(false);
                mobileToggle.focus();
            }
        });

        mobileNav.addEventListener('click', function (event) {
            if (event.target.closest('a')) {
                setMobileNavOpen(false);
            }
        });

        mobileToggle.addEventListener('click', function () {
            if (mobileNav.hidden) {
                dropdowns.forEach(function (dropdown) {
                    if (dropdown.classList.contains('public-mobile-dropdown')) {
                        setDropdownOpen(dropdown, false);
                    }
                });
            }
        });

        document.addEventListener('click', function (event) {
            if (!mobileNav.hidden && !mobileNav.contains(event.target) && !mobileToggle.contains(event.target)) {
                setMobileNavOpen(false);
            }
        });
    }
})();
