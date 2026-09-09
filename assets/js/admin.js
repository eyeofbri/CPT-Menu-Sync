(function () {
    'use strict';

    const data = window.CPTMS_DATA || { menus: {}, strings: {} };
    const menuSelect = document.querySelector('.cptms-menu-select');
    const parentSelect = document.querySelector('.cptms-parent-select');

    if (!menuSelect || !parentSelect) {
        return;
    }

    function populateParents() {
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

    menuSelect.addEventListener('change', function () {
        parentSelect.dataset.selected = '0';
        populateParents();
    });

    populateParents();
})();
