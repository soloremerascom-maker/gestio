document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('items-container');
    const addButton = document.getElementById('add-item');

    if (!container || !addButton) {
        return;
    }

    function createInput(labelText, name, type = 'text', options = null) {
        const wrapper = document.createElement('div');
        const label = document.createElement('label');
        label.textContent = labelText;
        wrapper.appendChild(label);

        let input;
        if (options) {
            input = document.createElement('select');
            options.forEach(opt => {
                const option = document.createElement('option');
                option.value = opt.value;
                option.textContent = opt.label;
                input.appendChild(option);
            });
        } else if (type === 'textarea') {
            input = document.createElement('textarea');
            input.rows = 2;
        } else {
            input = document.createElement('input');
            input.type = type;
        }
        input.name = name;
        wrapper.appendChild(input);
        return wrapper;
    }

    function createItemRow() {
        const row = document.createElement('div');
        row.className = 'item-row';

        row.appendChild(createInput('Tipo de prenda', 'item_garment_type[]'));
        row.appendChild(createInput('Material', 'item_material[]'));
        row.appendChild(createInput('Color', 'item_color[]'));
        row.appendChild(createInput('Talle', 'item_size[]'));
        row.appendChild(createInput('Cantidad', 'item_quantity[]', 'number'));
        row.appendChild(createInput('Impresiones por prenda', 'item_prints[]', 'number'));
        row.appendChild(createInput('Tipo de archivo', 'item_file_type[]'));
        const fileSentWrapper = createInput('Archivo entregado', 'item_file_sent[]', null, [
            { value: 'si', label: 'Sí' },
            { value: 'no', label: 'No' },
        ]);
        row.appendChild(fileSentWrapper);

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.textContent = 'Eliminar prenda';
        removeButton.className = 'remove-item';
        removeButton.addEventListener('click', () => {
            row.remove();
        });
        const removeWrapper = document.createElement('div');
        removeWrapper.className = 'full-width';
        removeWrapper.appendChild(removeButton);
        row.appendChild(removeWrapper);

        return row;
    }

    function addItemRow() {
        container.appendChild(createItemRow());
    }

    addButton.addEventListener('click', addItemRow);
    addItemRow();
});
