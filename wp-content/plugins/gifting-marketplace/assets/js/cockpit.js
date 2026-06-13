/* Giftelier — Shopping Cockpit */
(function ($) {
    'use strict';

    if (typeof gmCockpit === 'undefined') return;

    // ajaxUrl and nonce come from gmCockpit (product pages) or fall back to gmDash (browse page)
    var ajaxUrl  = gmCockpit.ajaxUrl || (typeof gmDash !== 'undefined' ? gmDash.ajaxUrl : '');
    var nonce    = gmCockpit.nonce   || (typeof gmDash !== 'undefined' ? gmDash.nonce   : '');
    var segment  = gmCockpit.segment;
    var config   = gmCockpit.config;
    var budgetUrl= gmCockpit.budgetUrl;

    /* Product page flag + product price */
    var isProductPage  = $('.gm-cockpit--product-page').length > 0;
    var productPrice   = isProductPage ? parseFloat($('.gm-cockpit--product-page').data('product-price') || 0) : 0;

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

        if (isProductPage) {
            hideSummary();
            $('#gm-ck-no-selection').toggle(!activeL1);
            if (!activeL1) { updateCartFormInputs(); return; }
            updateCartFormInputs();
            $.post(ajaxUrl, { action: 'gm_cockpit_set_active', nonce: nonce, l1_id: activeL1, l2_id: 0 })
             .always(function () { loadL1Summary(); });
            return;
        }

        /* Browse page: load L1 summary directly — no L2 needed */
        resetSummary();
        updateCartFormInputs();
        if (!activeL1) return;
        $.post(ajaxUrl, { action: 'gm_cockpit_set_active', nonce: nonce, l1_id: activeL1, l2_id: 0 })
         .always(function () {
            loadL1Summary();
            applyProductBadges();
        });
    });

    /* ── Inject hidden inputs into product Add to Cart form ───────── */
    function updateCartFormInputs() {
        var $form = $('form.cart');
        if (!$form.length) return;
        $form.find('input[name="gm_cockpit_l1_id"], input[name="gm_cockpit_l2_id"]').remove();
        if (activeL1) {
            $form.append('<input type="hidden" name="gm_cockpit_l1_id" value="' + activeL1 + '">');
            if (activeL2) {
                $form.append('<input type="hidden" name="gm_cockpit_l2_id" value="' + activeL2 + '">');
            }
        }
    }

    /* ── Reset summary to dashes (browse page always-visible state) ── */
    function resetSummary() {
        $('#gm-ck-allocated, #gm-ck-spent, #gm-ck-cart-amt, #gm-ck-remaining').text('—');
        $('#gm-ck-bar-spent, #gm-ck-bar-cart').css('width', '0%');
        $('#gm-ck-warn').hide();
        $('#gm-ck-capacity').hide();
        $('#gm-ck-extra-info').hide();
        $('#gm-ck-cart-section').hide();
        clearProductBadges();
    }

    /* ── Tier buttons (Construction) ──────────────────────────────── */
    $(document).on('click', '.gm-cockpit-tier-btn', function () {
        activeTier = $(this).data('tier');
        $('.gm-cockpit-tier-btn').removeClass('gm-cockpit-tier-btn--active');
        $(this).addClass('gm-cockpit-tier-btn--active');
        applyProductBadges();
        updateCapacity();
    });

    /* ── Product page: L1-level summary (no department breakdown) ─── */
    function loadL1Summary() {
        if (!activeL1) return;
        $.post(ajaxUrl, { action: 'gm_cockpit_l1_summary', nonce: nonce, l1_id: activeL1 })
         .done(function (res) {
            if (!res.success) return;
            renderL1Summary(res.data);
        });
    }

    function renderL1Summary(data) {
        var allocated      = data.allocated       || 0;
        var spent          = data.spent           || 0;
        var cartTot        = data.cart_total      || 0;
        var itemCount      = data.item_count      || 0;
        var itemsPurchased = data.items_purchased || 0;
        var itemsInCart    = data.items_in_cart   || 0;
        var itemsRemaining = data.items_remaining;  // null when no item_count set

        /* Remaining budget — also constrained by item budget if item_count is set */
        var budgetRemaining = Math.max(0, allocated - spent - cartTot);
        var remaining = budgetRemaining;
        if (itemCount > 0 && allocated > 0) {
            var perItemBudget = allocated / itemCount;
            var itemBudgetLeft = Math.max(0, (itemsRemaining !== null ? itemsRemaining : itemCount) * perItemBudget);
            remaining = Math.min(budgetRemaining, itemBudgetLeft);
        }

        var spentPct = allocated > 0 ? Math.min(100, (spent / allocated) * 100) : 0;
        var cartPct  = allocated > 0 ? Math.min(100 - spentPct, (cartTot / allocated) * 100) : 0;

        $('#gm-ck-allocated').text(allocated > 0 ? fmtINR(allocated) : '—');
        $('#gm-ck-spent').text(fmtINR(spent));
        $('#gm-ck-cart-amt').text(fmtINR(cartTot));
        $('#gm-ck-remaining').text(fmtINR(remaining));
        $('#gm-ck-bar-spent').css('width', spentPct.toFixed(1) + '%');
        $('#gm-ck-bar-cart').css('width', cartPct.toFixed(1) + '%');
        $('#gm-ck-summary').show();

        /* Item count block */
        if (itemCount > 0) {
            $('#gm-ck-items-planned').text(itemCount + ' items');
            $('#gm-ck-items-purchased').text(itemsPurchased + ' items');
            $('#gm-ck-items-in-cart').text(itemsInCart + ' items');
            $('#gm-ck-items-remaining').text((itemsRemaining !== null ? itemsRemaining : itemCount) + ' items');
            $('#gm-ck-items-section').show();
        } else {
            $('#gm-ck-items-section').hide();
        }

        /* "After adding" impact */
        if (productPrice > 0) {
            var afterAdd   = Math.max(0, remaining - productPrice);
            var willExceed = productPrice > remaining;
            var usedAfter  = allocated > 0 ? ((spent + cartTot + productPrice) / allocated) : 0;

            $('#gm-ck-after-add').text(fmtINR(afterAdd));
            $('#gm-ck-product-impact').show();

            if (willExceed) {
                var over = productPrice - remaining;
                $('#gm-ck-warn')
                    .removeClass('gm-cockpit-warn--amber').addClass('gm-cockpit-warn--red')
                    .text('This item exceeds your remaining budget by ' + fmtINR(over)).show();
            } else if (usedAfter >= 0.8) {
                var pct = Math.round(usedAfter * 100);
                $('#gm-ck-warn')
                    .removeClass('gm-cockpit-warn--red').addClass('gm-cockpit-warn--amber')
                    .text('Adding this item will use ' + pct + '% of your budget.').show();
            } else {
                $('#gm-ck-warn').hide();
            }
        }
    }

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
        $('#gm-ck-product-impact').hide();
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
        if (!activeL1) return;
        var data = originalOptions.data || '';
        if (typeof data === 'string' && data.indexOf('woocommerce_ajax_add_to_cart') !== -1) {
            options.data = data + '&gm_cockpit_l1_id=' + activeL1;
            if (activeL2) options.data += '&gm_cockpit_l2_id=' + activeL2;
        }
    });

    /* After WC cart fragment refresh — reload cockpit summary */
    $(document.body).on('wc_fragments_refreshed wc_fragments_loaded', function () {
        if (activeL1) loadL1Summary();
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

        if (isProductPage) {
            if (activeL1) {
                $('#gm-ck-l1').val(activeL1);
                $('#gm-ck-no-selection').hide();
                loadL1Summary();
                updateCartFormInputs();
            }
            return;
        }

        /* Browse page: restore L1 and load summary immediately */
        if (activeL1) {
            $('#gm-ck-l1').val(activeL1);
            loadL1Summary();
            applyProductBadges();
            updateCartFormInputs();
        }

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
