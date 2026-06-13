/* Giftelier – Dashboard JS */
/* global gmDash, jQuery */

(function ($) {
    'use strict';

    /* ── Budget form ──────────────────────────────────────────────── */

    $(document).on('submit', '#gm-budget-form', function (e) {
        e.preventDefault();
        var $form  = $(this);
        var $btn   = $form.find('[type="submit"]');
        var $fbk   = $('#gm-budget-feedback');
        var data   = {
            action:    'gm_save_budget',
            nonce:     gmDash.nonce,
            yearly:    $form.find('[name="yearly"]').val(),
            monthly:   $form.find('[name="monthly"]').val(),
            quarterly: $form.find('[name="quarterly"]').val(),
        };

        $btn.prop('disabled', true).text('Saving…');
        $fbk.text('').css('color', '');

        $.post(gmDash.ajaxUrl, data, function (res) {
            if (res.success) {
                $fbk.text(res.data.message).css('color', '#10B981');
            } else {
                $fbk.text('Could not save budget.').css('color', '#EF4444');
            }
        }).always(function () {
            $btn.prop('disabled', false).text('Save Budget');
        });
    });

    /* ── Add to cart (browse page) ───────────────────────────────── */

    $(document).on('click', '.gm-add-to-cart', function () {
        var $btn = $(this);
        var pid  = $btn.data('product-id');
        if (!pid) return;

        $btn.prop('disabled', true).text('Adding…');

        $.post(gmDash.ajaxUrl, {
            action:     'woocommerce_ajax_add_to_cart',
            product_id: pid,
            quantity:   1,
            nonce:      gmDash.nonce,
        }, function (res) {
            if (res && res.error) {
                $btn.text('Out of stock').prop('disabled', true);
            } else {
                $btn.text('Added ✓').addClass('gm-btn--outline');
                $(document.body).trigger('wc_fragment_refresh');
                gmShowToast('Added to your celebration! 🎁');
                setTimeout(function () {
                    $btn.text('Add to Cart').prop('disabled', false).removeClass('gm-btn--outline');
                }, 2000);
            }
        });
    });

    /* ── Toast ────────────────────────────────────────────────────── */

    function gmShowToast(msg) {
        var $t = $('<div class="gm-toast">' + msg + '</div>');
        $('body').append($t);
        setTimeout(function () { $t.addClass('gm-toast--show'); }, 10);
        setTimeout(function () {
            $t.removeClass('gm-toast--show');
            setTimeout(function () { $t.remove(); }, 300);
        }, 3000);
    }

    /* ── Budget ring animation on load ───────────────────────────── */

    function animateBudgetBar() {
        $('.gm-budget-bar__fill').each(function () {
            var $el  = $(this);
            var pct  = parseInt($el.data('pct'), 10) || 0;
            $el.css('width', 0);
            setTimeout(function () { $el.css('width', pct + '%'); }, 200);
        });
    }

    /* ── Bar chart: show values on hover ─────────────────────────── */

    function initBarChart() {
        $('.gm-bar-chart__col').on('mouseenter', function () {
            $(this).find('.gm-bar-chart__val').css('opacity', 1);
        }).on('mouseleave', function () {
            $(this).find('.gm-bar-chart__val').css('opacity', '');
        });
    }

    /* ── Filter form: auto-submit on select change ────────────────── */

    function initFilterAutoSubmit() {
        $('.gm-filter-selects select').on('change', function () {
            $(this).closest('form').submit();
        });
    }

    /* ── Analytics bars animate in ───────────────────────────────── */

    function animateAnalyticsBars() {
        $('.gm-analytics-row__bar, .gm-cat-spend-row__bar').each(function () {
            var $b   = $(this);
            var dest = $b.css('width');
            $b.css('width', 0);
            setTimeout(function () { $b.css('width', dest); }, 300);
        });
    }

    /* ── Event date picker (plan/calendar page) ──────────────────── */

    function formatDisplayDate(ymd) {
        if (!ymd) return '';
        var parts = ymd.split('-');
        if (parts.length !== 3) return ymd;
        var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        return months[parseInt(parts[1], 10) - 1] + ' ' + parseInt(parts[2], 10) + ', ' + parts[0];
    }

    function updateEventDateUI(date) {
        var $badge  = $('#gm-event-date-badge');
        var $hint   = $('#gm-date-pick-hint');
        var $banner = $('#gm-event-context-banner');
        var $noDate = $('#gm-no-date-hint-bar');

        if (date) {
            var label = formatDisplayDate(date);
            $('#gm-event-date-label').text(label);
            $('#gm-context-date-label').text(label);
            $badge.show();
            $hint.hide();
            $banner.fadeIn(200);
            $noDate.hide();
            $('#gm-step-1').removeClass('gm-step--active');
            $('#gm-step-2').addClass('gm-step--active');
            $('.gm-product-delivery-badge').show();
        } else {
            $badge.hide();
            $hint.show();
            $banner.hide();
            $noDate.show();
            $('#gm-step-1').addClass('gm-step--active');
            $('#gm-step-2').removeClass('gm-step--active');
            $('.gm-product-delivery-badge').hide();
        }
    }

    function initEventDatePicker() {
        var stored = sessionStorage.getItem('gm_event_date');
        if (stored) {
            $('#gm-event-date').val(stored);
            updateEventDateUI(stored);
        }

        $(document).on('change', '#gm-event-date', function () {
            var date = $(this).val();
            if (date) {
                sessionStorage.setItem('gm_event_date', date);
            } else {
                sessionStorage.removeItem('gm_event_date');
            }
            updateEventDateUI(date || null);
        });

        $(document).on('click', '#gm-scroll-to-gifts', function () {
            var $anchor = $('#gm-step2-gifts');
            if ($anchor.length) {
                $('html, body').animate({ scrollTop: $anchor.offset().top - 20 }, 400);
            }
        });

        $(document).on('click', '#gm-change-date', function () {
            var $card = $('#gm-step1-card');
            if ($card.length) {
                $('html, body').animate({ scrollTop: $card.offset().top - 20 }, 400);
            }
            setTimeout(function () { $('#gm-event-date').focus(); }, 420);
        });

        // Amelia fires this after a booking is completed
        $(document).on('ameliaBookingSuccess', function (e, booking) {
            var raw = booking && booking.bookingData && booking.bookingData.bookingStart;
            if (!raw) return;
            var ymd = raw.split(' ')[0];
            sessionStorage.setItem('gm_event_date', ymd);
            $('#gm-event-date').val(ymd);
            updateEventDateUI(ymd);
            setTimeout(function () {
                var $anchor = $('#gm-step2-gifts');
                if ($anchor.length) {
                    $('html, body').animate({ scrollTop: $anchor.offset().top - 20 }, 600);
                }
            }, 900);
        });
    }

    /* ── Init ──────────────────────────────────────────────────────── */

    $(function () {
        animateBudgetBar();
        initBarChart();
        initFilterAutoSubmit();
        animateAnalyticsBars();
        if ($('#gm-event-date').length) {
            initEventDatePicker();
        }
    });

}(jQuery));
