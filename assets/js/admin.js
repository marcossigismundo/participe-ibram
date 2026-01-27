/**
 * CRM Developer - Admin Scripts
 */

(function($) {
    'use strict';

    // Objeto global do CRM Admin
    window.CRMDevAdmin = {
        init: function() {
            this.initTooltips();
            this.initNotices();
        },

        initTooltips: function() {
            // Tooltips simples
            $('[data-tooltip]').hover(
                function() {
                    const text = $(this).data('tooltip');
                    const tooltip = $('<div class="crm-tooltip">' + text + '</div>');
                    $('body').append(tooltip);

                    const pos = $(this).offset();
                    tooltip.css({
                        top: pos.top - tooltip.outerHeight() - 5,
                        left: pos.left + ($(this).outerWidth() / 2) - (tooltip.outerWidth() / 2)
                    });
                },
                function() {
                    $('.crm-tooltip').remove();
                }
            );
        },

        initNotices: function() {
            // Auto-dismiss notices
            setTimeout(function() {
                $('.notice.is-dismissible').fadeOut();
            }, 5000);
        },

        // Utilitário: Formatar data
        formatDate: function(dateString, format) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');

            if (format === 'datetime') {
                return `${day}/${month}/${year} ${hours}:${minutes}`;
            }
            return `${day}/${month}/${year}`;
        },

        // Utilitário: Formatar telefone
        formatPhone: function(phone) {
            if (!phone) return '';
            phone = phone.replace(/\D/g, '');
            if (phone.length === 11) {
                return `(${phone.substr(0, 2)}) ${phone.substr(2, 5)}-${phone.substr(7)}`;
            } else if (phone.length === 10) {
                return `(${phone.substr(0, 2)}) ${phone.substr(2, 4)}-${phone.substr(6)}`;
            }
            return phone;
        },

        // Utilitário: Escapar HTML
        escapeHtml: function(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        },

        // Utilitário: Score color
        getScoreColor: function(score) {
            if (score >= 70) return '#27ae60';
            if (score >= 40) return '#f39c12';
            return '#e74c3c';
        },

        // Utilitário: Score label
        getScoreLabel: function(score) {
            if (score >= 70) return 'Alto';
            if (score >= 40) return 'Médio';
            return 'Baixo';
        },

        // Notificação
        notify: function(message, type) {
            type = type || 'success';
            const notice = $(`
                <div class="notice notice-${type} is-dismissible" style="position: fixed; top: 50px; right: 20px; z-index: 99999; min-width: 300px;">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss"></button>
                </div>
            `);

            $('body').append(notice);

            notice.find('.notice-dismiss').on('click', function() {
                notice.fadeOut(function() { $(this).remove(); });
            });

            setTimeout(function() {
                notice.fadeOut(function() { $(this).remove(); });
            }, 4000);
        },

        // Confirmar ação
        confirm: function(message, callback) {
            if (confirm(message)) {
                callback();
            }
        },

        // Loading overlay
        showLoading: function(container) {
            const overlay = $('<div class="crm-loading-overlay"><i class="fas fa-spinner fa-spin"></i></div>');
            $(container).css('position', 'relative').append(overlay);
        },

        hideLoading: function(container) {
            $(container).find('.crm-loading-overlay').remove();
        }
    };

    // Inicializar quando o DOM estiver pronto
    $(document).ready(function() {
        CRMDevAdmin.init();
    });

})(jQuery);
