(function () {
    'use strict';

    const data = window.CPTMS_DATA || { menus: {}, strings: {} };
    const rulesContainer = document.getElementById('cptms-rules');
    const addButton = document.getElementById('cptms-add-rule');
    const template = document.getElementById('cptms-rule-template');
    const emptyState = document.getElementById('cptms-empty');

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
        placeholder.value = '0';
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

        if (menuSelect) {
            menuSelect.addEventListener('change', function () {
                const parentSelect = rule.querySelector('.cptms-parent-select');
                if (parentSelect) {
                    parentSelect.dataset.selected = '0';
                }
                populateParents(rule);
            });
        }

        if (removeButton) {
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

    rulesContainer.querySelectorAll('.cptms-rule').forEach(bindRule);
    addButton.addEventListener('click', addRule);
    updateEmptyState();
})();
