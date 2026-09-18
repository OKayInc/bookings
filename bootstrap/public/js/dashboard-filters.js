(function () {
    'use strict';

    document.querySelectorAll('[data-dashboard-filters]').forEach(function (form) {
        form.querySelectorAll('select').forEach(function (select) {
            select.addEventListener('change', function () {
                form.requestSubmit();
            });
        });
    });
})();
