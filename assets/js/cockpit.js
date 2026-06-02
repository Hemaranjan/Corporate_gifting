/* Giftelier — Shopping Cockpit */
(function ($) {
    'use strict';

    if (typeof gmCockpit === 'undefined') return;

    var ajaxUrl  = gmDash.ajaxUrl;
    var nonce    = gmDash.nonce;
    var segment  = gmCockpit.segment;
    var config   = gmCockpit.config;
    var budgetUrl= gmCockpit.budgetUrl;

    /* Active state */
    var activeL1 = gmCockpit.activeL1 || 0;
    var activeL2 = gmCockpit.activeL2 || 0;
    var activeTier = '';

    /* Product prices on current page — for capacity estimate */
    var pagePrices = [];
    function collectPagePrices() {
        pagePrices = [];
        $('.gm-product-card').each(function () {
            var price = parseFloat($(this).find('.gm-product-card__price').data('price') || 0);
            if (price > 0) pagePrices.push(price);
        });
    }
    function avgPagePrice() {
        if (!pagePrices.length) return 0;
        return pagePrices.reduce(function (a, b) { return a + b; }, 0) / pagePrices.length;
    }

    /* ── Format helpers ───────────────────────────────────────────── */
    function fmtINR(n) {
        return '₹' + Math.round(n).toLocaleString('en-IN');
    }

    /* ── L1 selector change ───────────────────────────────────────── */
    $(document).on('change', '#gm-ck-l1', function () {
        activeL1 = parseInt($(this).val()) || 0;
        activeL2 = 0;
        $('#gm-ck-l2').html('<option value="">Loading…</option>').prop('disabled', true);
        hideSummary();

        if (!activeL1) {
            $('#gm-ck-l2').html('<option value="">Select ' + escHtml(config.l2_label) + '…</option>');
            return;
        }

        $.post(ajaxUrl, { action: 'gm_cockpit_get_data', nonce: nonce, l1_id: activeL1 })
         .done(function (res) {
            if (!res.success) return;
            var opts = '<option value="">Select ' + escHtml(config.l2_label) + '…</option>';
            res.data.l2_list.forEach(function (l2) {
                opts += '<option value="' + l2.id + '">' + escHtml(l2.name) +
                        ' — ' + fmtINR(l2.allocated) + '</option>';
            });
            $('#gm-ck-l2').html(opts).prop('disabled', false);
            if (res.data.l2_list.length === 0) {
                $('#gm-ck-l2').html('<option value="">No ' + escHtml(config.l2_label) + 's yet — set up on Budget page</option>');
            }
        });
    });

    /* ── L2 selector change ───────────────────────────────────────── */
    $(document).on('change', '#gm-ck-l2', function () {
        activeL2 = parseInt($(this).val()) || 0;
        if (!activeL2) { hideSummary(); return; }

        // Save to session
        $.post(ajaxUrl, { action: 'gm_cockpit_set_active', nonce: nonce, l1_id: activeL1, l2_id: activeL2 });

        loadSummary();
        applyProductBadges();
    });

    /* ── Tier buttons (Construction) ──────────────────────────────── */
    $(document).on('click', '.gm-cockpit-tier-btn', function () {
        activeTier = $(this).data('tier');
        $('.gm-cockpit-tier-btn').removeClass('gm-cockpit-tier-btn--active');
        $(this).addClass('gm-cockpit-tier-btn--active');
        applyProductBadges();
        updateCapacity();
    });

    /* ── Load summary + cart ──────────────────────────────────────── */
    function loadSummary() {
        if (!activeL1 || !activeL2) return;
        $.post(ajaxUrl, { action: 'gm_cockpit_get_data', nonce: nonce, l1_id: activeL1, l2_id: activeL2 })
         .done(function (res) {
            if (!res.success) return;
            var l2 = null;
            res.data.l2_list.forEach(function (item) { if (item.id === activeL2) l2 = item; });
            if (!l2) return;

            renderSummary(l2);
            renderCartPreview(res.data.cart_items || []);
            updateCapacityFromL2(l2);
            applyProductBadges(l2);
        });
    }

    function hideSummary() {
        $('#gm-ck-summary').hide();
        $('#gm-ck-capacity').hide();
        $('#gm-ck-cart-section').hide();
        $('#gm-ck-warn').hide();
        clearProductBadges();
    }

    /* ── Render summary block ─────────────────────────────────────── */
    function renderSummary(l2) {
        var allocated = l2.allocated || 0;
        var spent     = l2.spent || 0;
        var cartTot   = l2.cart_total || 0;
        var remaining = Math.max(0, allocated - spent - cartTot);

        var spentPct  = allocated > 0 ? Math.min(100, (spent / allocated) * 100) : 0;
        var cartPct   = allocated > 0 ? Math.min(100 - spentPct, (cartTot / allocated) * 100) : 0;
        var totalUsedPct = spentPct + cartPct;

        $('#gm-ck-allocated').text(fmtINR(allocated));
        $('#gm-ck-spent').text(fmtINR(spent));
        $('#gm-ck-cart-amt').text(fmtINR(cartTot));
        $('#gm-ck-remaining').text(fmtINR(remaining));
        $('#gm-ck-bar-spent').css('width', spentPct.toFixed(1) + '%');
        $('#gm-ck-bar-cart').css('width', cartPct.toFixed(1) + '%');

        $('#gm-ck-summary').show();

        // Warning strip
        if (totalUsedPct >= 80 && totalUsedPct < 100) {
            $('#gm-ck-warn').removeClass('gm-cockpit-warn--red').addClass('gm-cockpit-warn--amber')
                .text('You are near the ' + escHtml(config.l2_label) + ' budget limit.').show();
        } else if (totalUsedPct >= 100) {
            $('#gm-ck-warn').removeClass('gm-cockpit-warn--amber').addClass('gm-cockpit-warn--red')
                .text('Budget limit reached.').show();
        } else {
            $('#gm-ck-warn').hide();
        }

        // Segment-specific extra info
        if (segment === 'wedding' && l2.meta) {
            var gc = l2.meta.guest_count || (gmCockpit.activeL1Meta && gmCockpit.activeL1Meta.guest_count) || 0;
            if (gc) {
                $('#gm-ck-extra-info').text('Planning for ' + gc + ' guests').show();
            }
        }
        if (segment === 'hospitals' && l2.meta && l2.meta.compliance_cap) {
            $('#gm-ck-extra-info')
                .text('Compliance cap: ' + fmtINR(l2.meta.compliance_cap) + ' per recipient').show();
        }
        if (segment === 'construction' && gmCockpit.activeL1Meta && gmCockpit.activeL1Meta.project_code) {
            $('#gm-ck-extra-info').text('Project: ' + escHtml(gmCockpit.activeL1Meta.project_code)).show();
        }
    }

    /* ── Render cart preview ──────────────────────────────────────── */
    function renderCartPreview(items) {
        var $items = $('#gm-ck-cart-items');
        $items.empty();
        if (!items.length) {
            $('#gm-ck-cart-section').hide();
            return;
        }
        items.forEach(function (item) {
            $items.append(
                '<div class="gm-ck-cart-item">' +
                '<span class="gm-ck-cart-item__name" title="' + escHtml(item.name) + '">' + escHtml(item.name) +
                ' &times;' + item.qty + '</span>' +
                '<span class="gm-ck-cart-item__price">' + fmtINR(item.subtotal) + '</span>' +
                '</div>'
            );
        });
        $('#gm-ck-cart-section').show();
    }

    /* ── Capacity estimate ────────────────────────────────────────── */
    function updateCapacityFromL2(l2) {
        var remaining = Math.max(0, (l2.allocated || 0) - (l2.spent || 0) - (l2.cart_total || 0));
        var capType   = config.capacity_type;
        var divisor   = 0;
        var unitLabel = '';
        var meta      = l2.meta || {};

        if (capType === 'avg_page') {
            collectPagePrices();
            divisor   = avgPagePrice();
            unitLabel = 'item';
        } else if (capType === 'cap') {
            var capField = config.cap_field || '';
            divisor   = parseFloat(meta[capField] || 0);
            unitLabel = segment === 'school' ? 'student/teacher' : 'recipient';
        } else if (capType === 'per_head') {
            var l1meta = gmCockpit.activeL1Meta || {};
            divisor   = parseFloat(l1meta.per_head_budget || 0);
            unitLabel = 'guest';
        }

        // Construction: use tier band avg if tier is selected
        if (segment === 'construction' && activeTier && gmCockpit.tierBands && gmCockpit.tierBands[activeTier]) {
            var band  = gmCockpit.tierBands[activeTier];
            divisor   = (band.min + band.max) / 2;
            unitLabel = band.label.toLowerCase() + ' contractor';
        }

        if (divisor > 0 && remaining > 0) {
            var count = Math.floor(remaining / divisor);
            $('#gm-ck-capacity').text('~' + count + ' more ' + unitLabel + (count !== 1 ? 's' : '') +
                ' at ' + fmtINR(divisor) + '/ea').show();
        } else {
            $('#gm-ck-capacity').hide();
        }
    }

    function updateCapacity() {
        if (!activeL1 || !activeL2) return;
        loadSummary();
    }

    /* ── Product warning badges ───────────────────────────────────── */
    function applyProductBadges(l2) {
        clearProductBadges();
        if (!activeL2 && !l2) return;

        if (!l2) {
            /* fetch l2 data if not passed */
            $.post(ajaxUrl, { action: 'gm_cockpit_get_data', nonce: nonce, l1_id: activeL1, l2_id: activeL2 })
             .done(function (res) {
                if (!res.success) return;
                res.data.l2_list.forEach(function (item) { if (item.id === activeL2) applyProductBadges(item); });
            });
            return;
        }

        var remaining    = Math.max(0, (l2.allocated || 0) - (l2.spent || 0) - (l2.cart_total || 0));
        var capBlocking  = config.cap_blocking;
        var capField     = config.cap_field;
        var capValue     = capField ? parseFloat((l2.meta || {})[capField] || 0) : 0;
        var tierMin      = 0, tierMax = 0;

        if (segment === 'construction' && activeTier && gmCockpit.tierBands && gmCockpit.tierBands[activeTier]) {
            tierMin = gmCockpit.tierBands[activeTier].min;
            tierMax = gmCockpit.tierBands[activeTier].max;
        }

        $('.gm-product-card').each(function () {
            var $card  = $(this);
            var price  = parseFloat($card.data('price') || $card.find('[data-price]').data('price') || 0);
            var $btn   = $card.find('.gm-add-to-cart');

            if (!price) return;

            var badgeText = '';
            var badgeClass = '';
            var block = false;

            if (capValue > 0 && price > capValue) {
                badgeText  = capBlocking
                    ? 'Exceeds ' + fmtINR(capValue) + ' compliance cap'
                    : 'Exceeds ' + fmtINR(capValue) + ' cap';
                badgeClass = capBlocking ? 'gm-warn-badge--red' : 'gm-warn-badge--amber';
                block      = capBlocking;
            } else if (price > remaining && remaining >= 0) {
                var over   = price - remaining;
                badgeText  = 'Exceeds budget by ' + fmtINR(over);
                badgeClass = 'gm-warn-badge--amber';
            } else if (tierMin > 0 && price < tierMin) {
                badgeText  = 'Below ' + escHtml(gmCockpit.tierBands[activeTier].label) + ' tier (min ' + fmtINR(tierMin) + ')';
                badgeClass = 'gm-warn-badge--amber';
            } else if (tierMax > 0 && price > tierMax) {
                badgeText  = 'Above ' + escHtml(gmCockpit.tierBands[activeTier].label) + ' tier (max ' + fmtINR(tierMax) + ')';
                badgeClass = 'gm-warn-badge--amber';
            }

            if (badgeText) {
                $card.prepend('<span class="gm-warn-badge ' + badgeClass + '">' + escHtml(badgeText) + '</span>');
            }
            if (block) {
                $btn.addClass('gm-blocked').attr('disabled', true).attr('title', badgeText);
            }
        });
    }

    function clearProductBadges() {
        $('.gm-warn-badge').remove();
        $('.gm-add-to-cart').removeClass('gm-blocked').removeAttr('disabled').removeAttr('title');
    }

    /* ── Intercept Add to Cart to inject L1/L2 IDs ───────────────── */
    $.ajaxPrefilter(function (options, originalOptions) {
        if (!activeL1 || !activeL2) return;
        var data = originalOptions.data || '';
        if (typeof data === 'string' && data.indexOf('woocommerce_ajax_add_to_cart') !== -1) {
            options.data = data +
                '&gm_cockpit_l1_id=' + activeL1 +
                '&gm_cockpit_l2_id=' + activeL2;
        }
    });

    /* After WC cart fragment refresh — reload cockpit summary */
    $(document.body).on('wc_fragments_refreshed wc_fragments_loaded', function () {
        if (activeL1 && activeL2) loadSummary();
    });

    /* ── Mobile drawer ────────────────────────────────────────────── */
    $(document).on('click', '#gm-cockpit-mobile-bar', function () {
        $('#gm-cockpit-drawer').addClass('gm-drawer--open');
    });
    $(document).on('click', '#gm-cockpit-drawer .gm-cockpit-drawer__backdrop', function () {
        $('#gm-cockpit-drawer').removeClass('gm-drawer--open');
    });

    /* ── Budget page: add L1 ──────────────────────────────────────── */
    $(document).on('click', '#gm-setup-add-l1', function () {
        $('#gm-setup-l1-form').slideToggle(200);
    });

    $(document).on('submit', '#gm-setup-l1-form form', function (e) {
        e.preventDefault();
        var $form = $(this);
        var $btn  = $form.find('button[type="submit"]');
        $btn.prop('disabled', true).text('Saving…');

        var meta = {};
        $form.find('[name^="l1_meta_"]').each(function () {
            meta[$(this).attr('name').replace('l1_meta_', '')] = $(this).val();
        });

        $.post(ajaxUrl, {
            action:    'gm_save_l1',
            nonce:     nonce,
            segment:   segment,
            name:      $form.find('[name="l1_name"]').val(),
            meta_json: JSON.stringify(meta),
        }).done(function (res) {
            $btn.prop('disabled', false).text('Add');
            if (res.success) {
                window.location.reload();
            } else {
                alert(res.data || 'Save failed.');
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Add');
        });
    });

    /* ── Budget page: delete L1 ───────────────────────────────────── */
    $(document).on('click', '.gm-l1-delete-btn', function () {
        var $btn = $(this);
        var id   = parseInt($btn.data('id'));
        var name = $btn.closest('.gm-l1-item').find('.gm-l1-item-name').text().trim();
        if (!confirm('Delete "' + name + '" and all its departments? This cannot be undone.')) return;
        $btn.prop('disabled', true);
        $.post(ajaxUrl, { action: 'gm_delete_l1', nonce: nonce, id: id })
         .done(function (res) {
            if (res.success) {
                $btn.closest('.gm-l1-item').fadeOut(200, function () { $(this).remove(); });
            } else {
                $btn.prop('disabled', false);
                alert(res.data || 'Delete failed.');
            }
        });
    });

    /* ── Budget page: show add-L2 form ───────────────────────────── */
    $(document).on('click', '.gm-l1-add-l2', function () {
        var $item = $(this).closest('.gm-l1-item');
        $item.find('.gm-setup-l2-form').slideToggle(200);
    });

    /* ── Budget page: submit add-L2 form ─────────────────────────── */
    $(document).on('submit', '.gm-setup-l2-form form', function (e) {
        e.preventDefault();
        var $form  = $(this);
        var $item  = $form.closest('.gm-l1-item');
        var l1_id  = parseInt($item.data('id'));
        var $btn   = $form.find('button[type="submit"]');
        $btn.prop('disabled', true).text('Saving…');

        var meta = {};
        $form.find('[name^="l2_meta_"]').each(function () {
            meta[$(this).attr('name').replace('l2_meta_', '')] = $(this).val();
        });

        $.post(ajaxUrl, {
            action:    'gm_save_l2',
            nonce:     nonce,
            l1_id:     l1_id,
            name:      $form.find('[name="l2_name"]').val(),
            allocated: $form.find('[name="l2_allocated"]').val(),
            meta_json: JSON.stringify(meta),
        }).done(function (res) {
            $btn.prop('disabled', false).text('Add');
            if (res.success) {
                window.location.reload();
            } else {
                alert(res.data || 'Save failed.');
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Add');
        });
    });

    /* ── Budget page: cancel add-L2 form ────────────────────────────── */
    $(document).on('click', '.gm-l2-cancel-btn', function () {
        $(this).closest('.gm-setup-l2-form').slideUp(200);
    });

    /* ── Budget page: delete L2 ───────────────────────────────────── */
    $(document).on('click', '.gm-l2-delete-btn', function () {
        var $btn = $(this);
        var id   = parseInt($btn.data('id'));
        if (!confirm('Remove this entry?')) return;
        $btn.prop('disabled', true);
        $.post(ajaxUrl, { action: 'gm_delete_l2', nonce: nonce, id: id })
         .done(function (res) {
            if (res.success) {
                $btn.closest('.gm-l2-item').fadeOut(200, function () { $(this).remove(); });
            } else {
                $btn.prop('disabled', false);
            }
        });
    });

    /* ── Init on page load ────────────────────────────────────────── */
    function init() {
        collectPagePrices();

        if (activeL1) {
            $('#gm-ck-l1').val(activeL1).trigger('change');
            if (activeL2) {
                // Wait for L2 options to populate, then set value
                setTimeout(function () {
                    $('#gm-ck-l2').val(activeL2);
                    loadSummary();
                }, 600);
            }
        }

        // Update mobile bar text
        updateMobileBar();
    }

    function updateMobileBar() {
        var l1text = $('#gm-ck-l1 option:selected').text() || 'Select ' + config.l1_label;
        var remText = $('#gm-ck-remaining').text() || '';
        $('#gm-ck-mobile-l1').text(l1text);
        if (remText) $('#gm-ck-mobile-remaining').text('Remaining: ' + remText);
    }

    /* ── Utility ──────────────────────────────────────────────────── */
    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    $(document).ready(init);

})(jQuery);
