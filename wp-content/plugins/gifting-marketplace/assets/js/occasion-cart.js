/* Giftelier — Occasion Cart Modal
 * Intercepts Add-to-Cart, prompts the user to pick an occasion,
 * tags the cart item, and assigns the product to the occasion calendar.
 */
(function ($) {
    'use strict';

    if (typeof gmOccCart === 'undefined' || !gmOccCart.occasions || !gmOccCart.occasions.length) return;

    var occasions  = gmOccCart.occasions;
    var ajaxUrl    = gmOccCart.ajaxUrl;
    var nonce      = gmOccCart.nonce;
    var wcAjaxUrl  = gmOccCart.wcAjaxUrl;

    var pendingOccasionId = null;
    var pendingL1Id       = null;
    var $pendingBtn       = null;  // shop-page button waiting to fire

    /* ── Build modal once ──────────────────────────────────────────── */
    function buildModal() {
        if ($('#gm-occ-cart-modal').length) return;

        var rows = '<div class="gm-occ-cart-option gm-occ-cart-option--skip" data-id="" data-l1="0">' +
                   '<span class="gm-occ-cart-option__dot" style="background:#f3f4f6;color:#6b7280">🛍️</span>' +
                   '<span class="gm-occ-cart-option__name">No specific occasion</span>' +
                   '</div>';

        $.each(occasions, function (_, occ) {
            var dateStr = occ.date ? new Date(occ.date).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' }) : '';
            rows += '<div class="gm-occ-cart-option" data-id="' + escAttr(occ.id) + '" data-l1="' + (occ.l1_id || 0) + '">' +
                    '<span class="gm-occ-cart-option__dot" style="background:' + escAttr(occ.color) + '20;color:' + escAttr(occ.color) + '">' + escHtml(occ.icon) + '</span>' +
                    '<div class="gm-occ-cart-option__info">' +
                    '<span class="gm-occ-cart-option__name">' + escHtml(occ.title) + '</span>' +
                    (dateStr ? '<span class="gm-occ-cart-option__date">' + dateStr + '</span>' : '') +
                    '</div>' +
                    '</div>';
        });

        var html =
            '<div id="gm-occ-cart-modal" class="gm-occ-cart-modal" role="dialog" aria-modal="true" aria-label="Choose an occasion">' +
            '  <div class="gm-occ-cart-modal__backdrop"></div>' +
            '  <div class="gm-occ-cart-modal__box">' +
            '    <div class="gm-occ-cart-modal__hdr">' +
            '      <span class="gm-occ-cart-modal__title">🎁 Add to an occasion?</span>' +
            '      <button class="gm-occ-cart-modal__close" aria-label="Close">&times;</button>' +
            '    </div>' +
            '    <p class="gm-occ-cart-modal__sub">Tag this gift with one of your occasions so it shows in your calendar.</p>' +
            '    <div class="gm-occ-cart-modal__list">' + rows + '</div>' +
            '  </div>' +
            '</div>';

        $('body').append(html);
    }

    function openModal() {
        buildModal();
        $('#gm-occ-cart-modal').addClass('is-open');
        $('#gm-occ-cart-modal .gm-occ-cart-option').removeClass('is-selected');
    }

    function closeModal() {
        $('#gm-occ-cart-modal').removeClass('is-open');
        $pendingBtn = null;
    }

    /* ── Modal interactions ────────────────────────────────────────── */
    $(document).on('click', '#gm-occ-cart-modal .gm-occ-cart-modal__backdrop, #gm-occ-cart-modal .gm-occ-cart-modal__close', closeModal);

    $(document).on('click', '#gm-occ-cart-modal .gm-occ-cart-option', function () {
        var $opt      = $(this);
        var occId     = $opt.data('id') || '';
        var l1Id      = parseInt($opt.data('l1')) || 0;

        pendingOccasionId = occId;
        pendingL1Id       = l1Id;

        $('#gm-occ-cart-modal').removeClass('is-open');

        // Fire the pending add-to-cart
        if ($pendingBtn) {
            fireAddToCart($pendingBtn, occId, l1Id);
            $pendingBtn = null;
        }
    });

    /* ── Intercept shop-page Add-to-Cart buttons ────────────────────── */
    $(document).on('click', '.add_to_cart_button:not(.gm-occ-intercepted)', function (e) {
        // Only intercept simple (non-variable, non-grouped) products
        if ($(this).hasClass('product_type_variable') || $(this).hasClass('product_type_grouped')) return;

        e.preventDefault();
        e.stopImmediatePropagation();

        $pendingBtn = $(this);
        openModal();
    });

    /* ── Fire the actual add-to-cart after occasion selected ────────── */
    function fireAddToCart($btn, occId, l1Id) {
        var productId = $btn.data('product_id') || $btn.attr('data-product_id');
        var quantity  = $btn.data('quantity') || 1;

        $btn.addClass('loading');

        var postData = {
            product_id:      productId,
            quantity:        quantity,
            'add-to-cart':   productId,
        };
        if (occId)  postData.gm_occasion_id    = occId;
        if (l1Id)   postData.gm_cockpit_l1_id  = l1Id;

        $.post(wcAjaxUrl, postData)
         .done(function (res) {
            $btn.removeClass('loading').addClass('added');
            $(document.body).trigger('wc_fragment_refresh');
            $(document.body).trigger('added_to_cart', [res.fragments, res.cart_hash, $btn]);

            // Assign gift to occasion calendar
            if (occId) {
                $.post(ajaxUrl, {
                    action:     'gm_assign_gift',
                    nonce:      nonce,
                    occasion_id: occId,
                    product_id:  productId,
                });
            }
        })
        .fail(function () {
            $btn.removeClass('loading');
        });
    }

    /* ── Intercept product-page form submit ─────────────────────────── */
    var formIntercepted = false;

    $(document).on('submit', 'form.cart', function (e) {
        if (formIntercepted) { formIntercepted = false; return; }

        e.preventDefault();
        var $form = $(this);

        $pendingBtn = null;

        openModal();

        // After occasion selected, the modal click handler needs to submit the form
        $(document).one('click.occform', '#gm-occ-cart-modal .gm-occ-cart-option', function () {
            var occId = $(this).data('id') || '';
            var l1Id  = parseInt($(this).data('l1')) || 0;

            // Inject hidden fields into form
            $form.find('[name="gm_occasion_id"],[name="gm_cockpit_l1_id"]').remove();
            if (occId) {
                $form.append('<input type="hidden" name="gm_occasion_id" value="' + escAttr(occId) + '">');
                // Assign to calendar
                $.post(ajaxUrl, {
                    action:      'gm_assign_gift',
                    nonce:       nonce,
                    occasion_id: occId,
                    product_id:  $form.find('[name="add-to-cart"]').val() || $form.find('[name="product_id"]').val(),
                });
            }
            if (l1Id) {
                $form.append('<input type="hidden" name="gm_cockpit_l1_id" value="' + l1Id + '">');
            }

            formIntercepted = true;
            $form.submit();
        });

        // If modal is closed without selecting, unbind the one-time handler
        $(document).one('click.occformclose', '#gm-occ-cart-modal .gm-occ-cart-modal__backdrop, #gm-occ-cart-modal .gm-occ-cart-modal__close', function () {
            $(document).off('click.occform');
        });
    });

    /* ── Utilities ─────────────────────────────────────────────────── */
    function escHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function escAttr(str) {
        return String(str).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

})(jQuery);
