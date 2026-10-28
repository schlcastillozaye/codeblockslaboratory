/* ==========================================================================
   Code Blocks Laboratory Equipment Borrowing System - UI interactions
   Vanilla JavaScript. All business rules are still enforced by PHP.
   ========================================================================== */
(function () {
    'use strict';

    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var $ = function (selector, scope) { return (scope || document).querySelector(selector); };
    var $$ = function (selector, scope) { return Array.prototype.slice.call((scope || document).querySelectorAll(selector)); };

    /* ---------------------------------------------------------------------
       Sidebar (mobile off-canvas)
       --------------------------------------------------------------------- */
    var sidebar = $('.sidebar');
    var overlay = $('.sidebar-overlay');

    function closeSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove('is-open');
        if (overlay) overlay.classList.remove('is-open');
        document.body.style.overflow = '';
    }

    function openSidebar() {
        if (!sidebar) return;
        sidebar.classList.add('is-open');
        if (overlay) overlay.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        var first = $('.nav-link', sidebar);
        if (first) first.focus({ preventScroll: true });
    }

    $$('[data-sidebar-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (sidebar && sidebar.classList.contains('is-open')) closeSidebar();
            else openSidebar();
        });
    });
    $$('[data-sidebar-close]').forEach(function (btn) { btn.addEventListener('click', closeSidebar); });
    if (overlay) overlay.addEventListener('click', closeSidebar);

    /* ---------------------------------------------------------------------
       Dropdowns (user menu, notifications)
       --------------------------------------------------------------------- */
    var dropdowns = $$('[data-dropdown]');

    function closeDropdowns(except) {
        dropdowns.forEach(function (d) {
            if (d !== except) {
                d.classList.remove('is-open');
                var trigger = $('[data-dropdown-trigger]', d);
                if (trigger) trigger.setAttribute('aria-expanded', 'false');
            }
        });
    }

    dropdowns.forEach(function (dropdown) {
        var trigger = $('[data-dropdown-trigger]', dropdown);
        if (!trigger) return;
        trigger.addEventListener('click', function (event) {
            event.stopPropagation();
            var open = dropdown.classList.contains('is-open');
            closeDropdowns(dropdown);
            dropdown.classList.toggle('is-open', !open);
            trigger.setAttribute('aria-expanded', String(!open));
        });
        dropdown.addEventListener('click', function (event) { event.stopPropagation(); });
    });

    document.addEventListener('click', function () { closeDropdowns(); });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeDropdowns();
            closeSidebar();
            closeModal();
        }
    });

    /* ---------------------------------------------------------------------
       Reveal on scroll
       --------------------------------------------------------------------- */
    var reveals = $$('.reveal');
    if (!reduceMotion && 'IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.08, rootMargin: '0px 0px -20px 0px' });
        reveals.forEach(function (el) { observer.observe(el); });
    } else {
        reveals.forEach(function (el) { el.classList.add('is-visible'); });
    }

    /* ---------------------------------------------------------------------
       Animated number counters
       --------------------------------------------------------------------- */
    var counters = $$('[data-counter]');
    counters.forEach(function (el) {
        var target = parseInt(el.getAttribute('data-counter'), 10);
        if (isNaN(target)) return;
        if (reduceMotion || target === 0) {
            el.textContent = String(target);
            return;
        }
        var duration = 900;
        var start = null;
        function step(ts) {
            if (!start) start = ts;
            var progress = Math.min((ts - start) / duration, 1);
            var eased = 1 - Math.pow(1 - progress, 3);
            el.textContent = String(Math.round(target * eased));
            if (progress < 1) window.requestAnimationFrame(step);
            else el.textContent = String(target);
        }
        window.requestAnimationFrame(step);
    });

    /* ---------------------------------------------------------------------
       Live client-side table filter (server search remains authoritative)
       --------------------------------------------------------------------- */
    $$('[data-live-filter]').forEach(function (input) {
        var targetSelector = input.getAttribute('data-live-filter');
        var table = $(targetSelector);
        if (!table) return;
        var empty = $(targetSelector + '-empty');
        var countEl = $(input.getAttribute('data-count-target') || '');

        function apply() {
            var term = input.value.trim().toLowerCase();
            var rows = table.matches('table') ? $$('tbody tr[data-search]', table) : $$('[data-search]', table);
            var visible = 0;
            rows.forEach(function (row) {
                var haystack = (row.getAttribute('data-search') || row.textContent).toLowerCase();
                var match = term === '' || haystack.indexOf(term) !== -1;
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            if (empty) empty.style.display = visible === 0 ? '' : 'none';
            if (countEl) countEl.textContent = String(visible);
        }

        input.addEventListener('input', apply);
        input.addEventListener('search', apply);
    });

    /* ---------------------------------------------------------------------
       Confirmation modal for important actions
       --------------------------------------------------------------------- */
    var modal = null;
    var pendingForm = null;
    var lastFocus = null;

    function buildModal() {
        if (modal) return modal;
        modal = document.createElement('div');
        modal.className = 'modal';
        modal.id = 'confirm-modal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-hidden', 'true');
        modal.setAttribute('aria-labelledby', 'confirm-modal-title');
        modal.innerHTML =
            '<div class="modal__backdrop" data-modal-close></div>' +
            '<div class="modal__panel" role="document">' +
                '<div class="modal__head">' +
                    '<div class="modal__icon modal__icon--warning" data-modal-icon></div>' +
                    '<div><h2 class="modal__title" id="confirm-modal-title" data-modal-title>Confirm action</h2>' +
                    '<p class="modal__text" data-modal-text></p></div>' +
                    '<button type="button" class="modal__close" data-modal-close aria-label="Close dialog">' +
                        '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>' +
                    '</button>' +
                '</div>' +
                '<div class="modal__body" data-modal-extra></div>' +
                '<div class="modal__foot">' +
                    '<button type="button" class="btn btn--ghost" data-modal-close>Cancel</button>' +
                    '<button type="button" class="btn btn--primary" data-modal-confirm>Confirm</button>' +
                '</div>' +
            '</div>';
        document.body.appendChild(modal);
        $('[data-modal-confirm]', modal).addEventListener('click', function () {
            if (!pendingForm) return;
            var form = pendingForm;
            pendingForm = null;
            closeModal();
            // Native submit bypasses the submit event that opened this dialog.
            HTMLFormElement.prototype.submit.call(form);
        });
        $$('[data-modal-close]', modal).forEach(function (el) {
            el.addEventListener('click', closeModal);
        });
        return modal;
    }

    function openModal(options, form) {
        buildModal();
        lastFocus = document.activeElement;
        // A programmatic submit can leave focus on <body>; send it back to the
        // control that opened the dialog when the dialog closes.
        if ((!lastFocus || lastFocus === document.body) && form) {
            lastFocus = form.querySelector('button[type="submit"], button, [href]');
        }
        pendingForm = form || null;
        $('[data-modal-title]', modal).textContent = options.title || 'Confirm action';
        $('[data-modal-text]', modal).textContent = options.message || 'Are you sure you want to continue?';
        var confirm = $('[data-modal-confirm]', modal);
        confirm.textContent = options.confirm || 'Confirm';
        confirm.className = 'btn ' + (options.danger ? 'btn--danger-solid' : 'btn--primary');
        var icon = $('[data-modal-icon]', modal);
        icon.className = 'modal__icon ' + (options.danger ? 'modal__icon--danger' : 'modal__icon--warning');
        icon.innerHTML = '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.7 2.4 17.3A2 2 0 0 0 4.1 20h15.8a2 2 0 0 0 1.7-2.7L13.7 3.7a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/></svg>';
        $('[data-modal-extra]', modal).innerHTML = '';
        modal.setAttribute('aria-hidden', 'false');
        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        setTimeout(function () { confirm.focus(); }, 40);
    }

    function closeModal() {
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        pendingForm = null;
        if (lastFocus && lastFocus.focus) lastFocus.focus({ preventScroll: true });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Tab' || !modal || !modal.classList.contains('is-open')) return;
        var focusable = $$('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])', modal)
            .filter(function (el) { return !el.disabled && el.offsetParent !== null; });
        if (focusable.length === 0) return;
        var first = focusable[0];
        var last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    $$('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.getAttribute('data-confirmed') === '1') return;
            event.preventDefault();
            openModal({
                title: form.getAttribute('data-confirm-title') || 'Confirm action',
                message: form.getAttribute('data-confirm') || 'Are you sure?',
                confirm: form.getAttribute('data-confirm-button') || 'Confirm',
                danger: form.getAttribute('data-confirm-danger') === '1'
            }, form);
        });
    });

    /* ---------------------------------------------------------------------
       Alerts: dismiss + auto-hide
       --------------------------------------------------------------------- */
    $$('[data-dismiss-alert]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var alert = btn.closest('.alert');
            if (!alert) return;
            alert.style.transition = 'opacity .22s ease, transform .22s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-4px)';
            setTimeout(function () { alert.remove(); }, 240);
        });
    });

    $$('.alert--success, .alert--info').forEach(function (alert) {
        if (alert.hasAttribute('data-persist')) return;
        setTimeout(function () {
            if (!document.body.contains(alert)) return;
            alert.style.transition = 'opacity .3s ease, transform .3s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-4px)';
            setTimeout(function () { alert.remove(); }, 320);
        }, 7000);
    });

    /* ---------------------------------------------------------------------
       Password visibility
       --------------------------------------------------------------------- */
    $$('[data-password-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.getAttribute('data-password-toggle'));
            if (!input) return;
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            btn.innerHTML = show
                ? '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3l18 18"/><path d="M10.6 5.2A10.8 10.8 0 0 1 12 5c6.5 0 10 7 10 7a18 18 0 0 1-3 3.9M6.7 6.8C3.8 8.7 2 12 2 12s3.5 7 10 7a11 11 0 0 0 4.3-.8"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>'
                : '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';
        });
    });

    /* ---------------------------------------------------------------------
       Demo account quick fill
       --------------------------------------------------------------------- */
    $$('[data-demo-login]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var login = document.getElementById('login');
            var password = document.getElementById('password');
            if (login) login.value = btn.getAttribute('data-demo-login');
            if (password) {
                password.value = btn.getAttribute('data-demo-password');
                password.focus();
            }
            btn.classList.add('is-active');
        });
    });

    /* ---------------------------------------------------------------------
       Date constraints
       --------------------------------------------------------------------- */
    $$('[data-min-days], [data-max-days]').forEach(function (input) {
        var today = new Date();
        var minDays = parseInt(input.getAttribute('data-min-days'), 10);
        var maxDays = parseInt(input.getAttribute('data-max-days'), 10);
        if (!isNaN(minDays)) {
            var min = new Date(today.getTime() + minDays * 86400000);
            input.min = min.toISOString().slice(0, 10);
        }
        if (!isNaN(maxDays)) {
            var max = new Date(today.getTime() + maxDays * 86400000);
            input.max = max.toISOString().slice(0, 10);
        }
    });

    /* ---------------------------------------------------------------------
       Equipment selection helper
       --------------------------------------------------------------------- */
    var equipmentSelect = $('[data-equipment-select]');
    if (equipmentSelect) {
        var stockHint = $('[data-stock-hint]');
        var qtyInput = $('[data-quantity-input]');
        function updateStockHint() {
            var option = equipmentSelect.options[equipmentSelect.selectedIndex];
            if (!option || !option.value) {
                if (stockHint) stockHint.textContent = 'Select equipment to see current availability.';
                if (qtyInput) qtyInput.removeAttribute('max');
                return;
            }
            var stock = parseInt(option.getAttribute('data-stock') || '0', 10);
            var category = option.getAttribute('data-category') || '';
            var location = option.getAttribute('data-location') || '';
            if (stockHint) {
                stockHint.textContent = stock + ' unit(s) available' + (category ? ' • ' + category : '') + (location ? ' • ' + location : '');
            }
            if (qtyInput) qtyInput.max = String(Math.max(1, stock));
        }
        equipmentSelect.addEventListener('change', updateStockHint);
        updateStockHint();
    }

    /* ---------------------------------------------------------------------
       Form submit feedback (does not block submission)
       --------------------------------------------------------------------- */
    $$('form[data-loading]').forEach(function (form) {
        form.addEventListener('submit', function () {
            var btn = $('button[type="submit"]', form) || $('[data-submit]', form);
            if (btn) {
                btn.classList.add('is-submitting');
                btn.setAttribute('aria-busy', 'true');
            }
        });
    });

    /* ---------------------------------------------------------------------
       Print buttons
       --------------------------------------------------------------------- */
    $$('[data-print]').forEach(function (btn) {
        btn.addEventListener('click', function () { window.print(); });
    });

    /* ---------------------------------------------------------------------
       Auto-submit filter selects (keeps server-side filtering authoritative)
       --------------------------------------------------------------------- */
    $$('[data-auto-submit]').forEach(function (select) {
        select.addEventListener('change', function () {
            var form = select.closest('form');
            if (form) form.submit();
        });
    });
    /* ---------------------------------------------------------------------
       Theme toggle (dark / light) with persisted preference
       --------------------------------------------------------------------- */
    function currentTheme() {
        return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    }

    function syncThemeControls(theme) {
        var dark = theme === 'dark';
        $$('[data-theme-toggle]').forEach(function (btn) {
            btn.setAttribute('aria-pressed', dark ? 'true' : 'false');
            btn.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
            btn.setAttribute('title', dark ? 'Switch to light mode' : 'Switch to dark mode');
        });
        var meta = $('meta[name="theme-color"]');
        if (meta) meta.setAttribute('content', dark ? '#06101f' : '#eef3fb');
    }

    function applyTheme(theme, persist) {
        theme = theme === 'dark' ? 'dark' : 'light';
        var root = document.documentElement;
        if (!reduceMotion) {
            root.classList.add('theme-transition');
            window.clearTimeout(applyTheme.transitionTimer);
            applyTheme.transitionTimer = window.setTimeout(function () {
                root.classList.remove('theme-transition');
            }, 360);
        }
        root.setAttribute('data-theme', theme);
        root.style.colorScheme = theme;
        syncThemeControls(theme);
        if (persist !== false) {
            try { localStorage.setItem('codeblocks-theme', theme); } catch (e) {}
        }
    }

    syncThemeControls(currentTheme());

    $$('[data-theme-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
        });
    });

    window.addEventListener('storage', function (event) {
        if (event.key === 'codeblocks-theme' && event.newValue) {
            applyTheme(event.newValue, false);
        }
    });

    /* ---------------------------------------------------------------------
       Toast feedback for in-page actions
       --------------------------------------------------------------------- */
    function showToast(message, type) {
        var stack = document.getElementById('toast-stack');
        if (!stack || !message) return;
        type = type || 'success';
        var icons = {
            success: '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.7 2.7L16 9.5"/></svg>',
            error: '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.7 2.4 17.3A2 2 0 0 0 4.1 20h15.8a2 2 0 0 0 1.7-2.7L13.7 3.7a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/></svg>',
            warning: '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>'
        };
        var toast = document.createElement('div');
        toast.className = 'toast toast--' + type;
        toast.setAttribute('role', 'status');
        toast.innerHTML = (icons[type] || icons.success) + '<span></span>';
        $('span', toast).textContent = message;
        stack.appendChild(toast);
        setTimeout(function () {
            if (!document.body.contains(toast)) return;
            toast.classList.add('is-leaving');
            setTimeout(function () { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 260);
        }, 3600);
    }

    /* ---------------------------------------------------------------------
       Notifications: instant, persisted read/dismiss actions
       --------------------------------------------------------------------- */
    function notificationToken(form) {
        var input = $('input[name="_token"]', form);
        return input ? input.value : '';
    }

    function setUnreadIndicators(count) {
        count = Math.max(0, parseInt(count, 10) || 0);

        $$('[data-notification-unread-label], [data-testid="unread-notification-count"]').forEach(function (el) {
            el.textContent = count + ' unread';
            if (el.classList.contains('badge')) {
                el.classList.toggle('badge--info', count > 0);
                el.classList.toggle('badge--muted', count === 0);
            }
        });

        var topCount = $('[data-testid="notification-count"]');
        if (topCount) {
            if (count > 0) topCount.textContent = count > 99 ? '99+' : String(count);
            else topCount.remove();
        }

        var navCount = $('[data-notification-nav-count]');
        if (navCount) {
            navCount.textContent = String(count);
            navCount.hidden = count === 0;
        }

        var dropdownCount = $('[data-notification-dropdown-count]');
        if (dropdownCount) {
            if (count > 0) dropdownCount.textContent = count + ' new';
            else dropdownCount.remove();
        }

        var bell = $('[data-testid="notification-bell"]');
        if (bell) bell.setAttribute('aria-label', 'Notifications (' + count + ' unread)');

        var markAll = $('[data-testid="mark-all-read-form"]');
        if (markAll && count === 0) markAll.remove();
    }

    function notificationEmptyMarkup() {
        return '<div class="empty-state" style="padding: 26px 18px;" data-notification-dropdown-empty>' +
            '<div class="empty-state__icon" style="width:44px;height:44px;margin-bottom:8px;">' +
            '<svg class="icon icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.7 2.7L16 9.5"/></svg>' +
            '</div><p style="font-size:12px;margin:0;">You are all caught up.</p></div>';
    }

    function refreshNotificationEmptyStates() {
        var pageList = $('[data-notification-list]');
        if (pageList) {
            var pageEmpty = $('[data-notification-empty]', pageList);
            if (pageEmpty) pageEmpty.hidden = $$('[data-notification-item]', pageList).length > 0;
        }

        var dropdown = $('[data-notification-dropdown-list]');
        if (dropdown) {
            var dropdownEmpty = $('[data-notification-dropdown-empty]', dropdown);
            if (!dropdownEmpty) {
                dropdownEmpty = document.createElement('div');
                dropdownEmpty.innerHTML = notificationEmptyMarkup();
                dropdownEmpty = dropdownEmpty.firstChild;
                dropdown.appendChild(dropdownEmpty);
            }
            dropdownEmpty.hidden = $$('[data-notification-item]', dropdown).length > 0;
        }
    }

    function markNotificationReadInPlace(item) {
        item.classList.add('is-read');
        item.setAttribute('data-notification-unread', '0');
        var badge = $('[data-notification-new]', item);
        if (badge) badge.remove();
        var readForm = item.querySelector('form[data-notification-action="read"]');
        if (readForm) readForm.remove();
    }

    function removeNotification(item) {
        if (!item) return;
        item.classList.add('is-removing');
        setTimeout(function () {
            if (item.parentNode) item.parentNode.removeChild(item);
            refreshNotificationEmptyStates();
        }, 210);
    }

    function notificationRequest(form, successMessage, onSuccess) {
        if (!window.fetch) { form.submit(); return; }
        var button = $('button[type="submit"]', form);
        if (button) button.disabled = true;

        fetch(form.action, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-Token': notificationToken(form)
            },
            body: new FormData(form),
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().then(function (data) {
                return { ok: response.ok, data: data };
            });
        }).then(function (result) {
            if (!result.ok || !result.data || !result.data.ok) {
                throw new Error((result.data && result.data.message) || 'The action could not be completed.');
            }
            setUnreadIndicators(result.data.unread);
            if (typeof onSuccess === 'function') onSuccess(result.data);
            showToast(result.data.message || successMessage, 'success');
        }).catch(function () {
            // Progressive fallback: use the regular form flow if the request fails.
            form.submit();
        });
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form || !form.matches) return;

        if (form.matches('[data-notification-open]')) {
            if (!window.fetch) return;
            event.preventDefault();
            var item = form.closest('[data-notification-item]');
            notificationRequest(form, 'Notification opened.', function (data) {
                removeNotification(item);
                var target = form.getAttribute('data-notification-link') || data.link;
                if (target) setTimeout(function () { window.location.href = target; }, 170);
            });
            return;
        }

        var action = form.getAttribute('data-notification-action');
        if (!action) return;

        if (action === 'read') {
            event.preventDefault();
            var readItem = form.closest('[data-notification-item]');
            var mode = readItem && readItem.closest('[data-notification-mode]');
            notificationRequest(form, 'Notification marked as read.', function () {
                if (mode && mode.getAttribute('data-notification-mode') === 'all') {
                    markNotificationReadInPlace(readItem);
                } else {
                    removeNotification(readItem);
                }
            });
            return;
        }

        if (action === 'dismiss') {
            event.preventDefault();
            removeNotification(form.closest('[data-notification-item]'));
            notificationRequest(form, 'Notification dismissed.', function () {
                refreshNotificationEmptyStates();
            });
            return;
        }

        if (action === 'read-all') {
            event.preventDefault();
            var list = $('[data-notification-list]');
            var listMode = list ? list.getAttribute('data-notification-mode') : 'unread';
            notificationRequest(form, 'All notifications marked as read.', function () {
                if (listMode === 'all' && list) {
                    $$('[data-notification-item]', list).forEach(markNotificationReadInPlace);
                } else {
                    $$('[data-notification-item]').forEach(removeNotification);
                }
                form.remove();
                refreshNotificationEmptyStates();
            });
        }
    });

    refreshNotificationEmptyStates();
})();


