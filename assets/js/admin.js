(function () {
    'use strict';

    const data = window.CPTMS_DATA || { menus: {}, strings: {} };
    const rulesContainer = document.getElementById('cptms-rules');
    const addButton = document.getElementById('cptms-add-rule');
    const template = document.getElementById('cptms-rule-template');
    const emptyState = document.getElementById('cptms-empty');
    const noticeArea = document.getElementById('cptms-notice-area');

    if (!rulesContainer || !addButton || !template) {
        return;
    }

    function populateParents(rule) {
        const menuSelect = rule.querySelector('.cptms-menu-select');
        const parentSelect = rule.querySelector('.cptms-parent-select');

        if (!menuSelect || !parentSelect) {
            return;
        }

        const menuId = String(menuSelect.value || '0');
        const selected = String(parentSelect.dataset.selected || parentSelect.value || '0');
        const items = data.menus[menuId] || [];

        parentSelect.innerHTML = '';

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = items.length
            ? (data.strings.selectParent || 'Select a parent item')
            : (data.strings.noItems || 'This menu has no items.');
        parentSelect.appendChild(placeholder);

        items.forEach(function (item) {
            const option = document.createElement('option');
            option.value = String(item.id);
            option.textContent = item.label;
            if (String(item.id) === selected) {
                option.selected = true;
            }
            parentSelect.appendChild(option);
        });

        parentSelect.dataset.selected = '0';
    }

    function bindRule(rule) {
        const menuSelect = rule.querySelector('.cptms-menu-select');
        const removeButton = rule.querySelector('.cptms-remove-rule');

        if (menuSelect && !menuSelect.dataset.cptmsBound) {
            menuSelect.dataset.cptmsBound = '1';
            menuSelect.addEventListener('change', function () {
                const parentSelect = rule.querySelector('.cptms-parent-select');
                if (parentSelect) {
                    parentSelect.dataset.selected = '0';
                }
                populateParents(rule);
            });
        }

        if (removeButton && !removeButton.dataset.cptmsBound) {
            removeButton.dataset.cptmsBound = '1';
            removeButton.addEventListener('click', function () {
                rule.remove();
                updateEmptyState();
            });
        }

        populateParents(rule);
    }

    function updateEmptyState() {
        if (!emptyState) {
            return;
        }
        const hasRules = rulesContainer.querySelector('.cptms-rule') !== null;
        emptyState.classList.toggle('is-hidden', hasRules);
    }

    function addRule() {
        const index = parseInt(rulesContainer.dataset.nextIndex || '0', 10);
        rulesContainer.dataset.nextIndex = String(index + 1);

        const html = template.innerHTML.split('__INDEX__').join(String(index));
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        const rule = wrapper.firstElementChild;

        if (!rule) {
            return;
        }

        rulesContainer.appendChild(rule);
        bindRule(rule);
        updateEmptyState();

        const firstSelect = rule.querySelector('select');
        if (firstSelect) {
            firstSelect.focus();
        }
    }

    function setBusy(button, busy) {
        if (!button) {
            return;
        }

        button.disabled = busy;
        button.classList.toggle('cptms-is-busy', busy);
        button.setAttribute('aria-busy', busy ? 'true' : 'false');
    }

    function addDismissButtons() {
        if (!noticeArea) {
            return;
        }

        noticeArea.querySelectorAll('.notice.is-dismissible').forEach(function (notice) {
            if (notice.querySelector('.notice-dismiss')) {
                return;
            }

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'notice-dismiss';
            button.innerHTML = '<span class="screen-reader-text">Dismiss this notice.</span>';
            button.addEventListener('click', function () {
                notice.remove();
            });
            notice.appendChild(button);
        });
    }

    function showNoticeHtml(html) {
        if (!noticeArea) {
            return;
        }

        noticeArea.innerHTML = html || '';
        addDismissButtons();
    }

    function showClientError(message) {
        if (!noticeArea) {
            return;
        }

        noticeArea.innerHTML = '';
        const notice = document.createElement('div');
        notice.className = 'notice notice-error is-dismissible cptms-notice';
        const paragraph = document.createElement('p');
        const strong = document.createElement('strong');
        strong.textContent = message || data.strings.requestError || 'Something went wrong. Please try again.';
        paragraph.appendChild(strong);
        notice.appendChild(paragraph);
        noticeArea.appendChild(notice);
        addDismissButtons();
    }

    async function postForm(form) {
        const formData = new FormData(form);
        const response = await fetch(data.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        });

        let payload = null;
        try {
            payload = await response.json();
        } catch (error) {
            throw new Error(data.strings.requestError || 'Something went wrong. Please try again.');
        }

        if (!response.ok || !payload || !payload.success) {
            const message = payload && payload.data && payload.data.message
                ? payload.data.message
                : (data.strings.requestError || 'Something went wrong. Please try again.');
            throw new Error(message);
        }

        return payload.data || {};
    }

    function refreshStoredRuleIds(savedRules) {
        if (!Array.isArray(savedRules)) {
            return;
        }

        const cards = Array.from(rulesContainer.querySelectorAll('.cptms-rule'));
        cards.forEach(function (card, index) {
            if (!savedRules[index] || !savedRules[index].id) {
                return;
            }

            const input = card.querySelector('.cptms-rule-id');
            if (input) {
                input.value = savedRules[index].id;
            }
        });
    }

    async function handleAjaxSubmit(form) {
        if (!data.ajaxUrl || !form) {
            return;
        }

        if (typeof form.reportValidity === 'function' && !form.reportValidity()) {
            return;
        }

        const button = form.querySelector('button[type="submit"], input[type="submit"]');
        setBusy(button, true);

        try {
            const result = await postForm(form);

            if (result.notice_html !== undefined) {
                showNoticeHtml(result.notice_html);
            }

            if (form.id === 'cptms-rules-form' && result.rules) {
                refreshStoredRuleIds(result.rules);
            }

            if (form.id === 'cptms-update-form' && result.panel_html !== undefined) {
                const updateContent = document.getElementById('cptms-update-content');
                if (updateContent) {
                    updateContent.innerHTML = result.panel_html;
                }
            }
        } catch (error) {
            showClientError(error && error.message ? error.message : null);
        } finally {
            // The update form may have been replaced by the server response.
            if (document.body.contains(button)) {
                setBusy(button, false);
            }
        }
    }

    rulesContainer.querySelectorAll('.cptms-rule').forEach(bindRule);
    addButton.addEventListener('click', addRule);
    updateEmptyState();
    addDismissButtons();

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (!['cptms-rules-form', 'cptms-sync-form', 'cptms-update-form'].includes(form.id)) {
            return;
        }

        if (!data.ajaxUrl || typeof window.fetch !== 'function' || typeof window.FormData !== 'function') {
            return;
        }

        event.preventDefault();
        handleAjaxSubmit(form);
    });
})();
