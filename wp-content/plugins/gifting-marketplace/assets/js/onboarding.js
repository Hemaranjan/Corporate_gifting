/* Giftelier — Vendor Onboarding Sync Step */
(function ($) {
    'use strict';

    if (!$('.gm-ob-wrap').length) return;

    /* ── Platform card selection ──────────────────────────────────── */
    $(document).on('click', '.gm-ob-platform', function () {
        var platform = $(this).data('platform');

        // Update selection state
        $('.gm-ob-platform').removeClass('gm-ob-platform--selected');
        $(this).addClass('gm-ob-platform--selected');

        // Set hidden field
        $('#gm-ob-platform').val(platform);
        $('#gm-ob-skip').val('');

        // Hide all creds panels
        $('.gm-ob-creds').attr('hidden', '').hide();
        $('#gm-ob-interval-row').attr('hidden', '').hide();
        $('#gm-ob-actions').attr('hidden', '').hide();
        $('#gm-ob-skip-actions').attr('hidden', '').hide();

        if (platform === 'skip') {
            // Show just "Continue" — no creds needed
            $('#gm-ob-skip').val('1');
            $('#gm-ob-skip-actions').removeAttr('hidden').show();
            return;
        }

        // Show matching creds panel
        $('[data-creds-for="' + platform + '"]').removeAttr('hidden').show();
        $('#gm-ob-interval-row').removeAttr('hidden').show();
        $('#gm-ob-actions').removeAttr('hidden').show();

        // Smooth scroll to credentials
        $('html, body').animate({
            scrollTop: $('.gm-ob-creds:visible').offset().top - 60
        }, 300);
    });

    /* ── Form submit validation ───────────────────────────────────── */
    $('#gm-ob-form').on('submit', function (e) {
        var platform = $('#gm-ob-platform').val();
        var isSkip   = $('#gm-ob-skip').val() === '1';

        if (isSkip || !platform || platform === 'skip') return; // allow submit

        // Validate required fields in the visible creds panel
        var $panel  = $('[data-creds-for="' + platform + '"]');
        var valid   = true;

        $panel.find('input[name][type!="file"]').each(function () {
            if ($(this).prop('required') || $(this).closest('.gm-ob-field').find('.gm-ob-req').length) {
                if (!$(this).val().trim()) {
                    $(this).css('border-color', '#ef4444');
                    valid = false;
                } else {
                    $(this).css('border-color', '');
                }
            }
        });

        if (!valid) {
            e.preventDefault();
            $panel.find('input[style*="ef4444"]').first().focus();
        }
    });

    /* ── Clear error highlight on input ──────────────────────────── */
    $(document).on('input', '.gm-ob-input', function () {
        $(this).css('border-color', '');
    });

})(jQuery);
