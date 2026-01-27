/**
 * CRM Developer - Public Scripts
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        // Máscara de telefone simples
        const phoneInputs = document.querySelectorAll('input[type="tel"]');
        phoneInputs.forEach(function(input) {
            input.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 11) value = value.slice(0, 11);

                if (value.length > 6) {
                    value = '(' + value.slice(0, 2) + ') ' + value.slice(2, 7) + '-' + value.slice(7);
                } else if (value.length > 2) {
                    value = '(' + value.slice(0, 2) + ') ' + value.slice(2);
                } else if (value.length > 0) {
                    value = '(' + value;
                }

                e.target.value = value;
            });
        });
    });
})();
