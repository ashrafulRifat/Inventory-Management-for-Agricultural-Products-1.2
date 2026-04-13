document.addEventListener('DOMContentLoaded', function () {
    var deleteForms = document.querySelectorAll('form[data-confirm]');
    deleteForms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var message = form.getAttribute('data-confirm') || 'Are you sure?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    var addLineItemButton = document.getElementById('add-line-item');
    if (addLineItemButton) {
        addLineItemButton.addEventListener('click', function () {
            var container = document.getElementById('line-items-container');
            var template = document.getElementById('line-item-template');
            if (!container || !template) {
                return;
            }

            var clone = template.content.cloneNode(true);
            container.appendChild(clone);
        });
    }

    document.addEventListener('click', function (event) {
        var target = event.target;
        if (target && target.classList.contains('remove-line-item')) {
            var row = target.closest('.line-item-row');
            if (row) {
                row.remove();
            }
        }
    });
});
