/* Giftelier — Sync Hub Wizard */
(function ($) {
    'use strict';

    if (typeof gmSyncHub === 'undefined') return;

    var ajaxUrl      = gmSyncHub.ajaxUrl;
    var nonce        = gmSyncHub.nonce;
    var currentStep  = 1;
    var activePlatform = '';

    /* ── Step navigation ─────────────────────────────────────────── */
    function showStep(n) {
        currentStep = n;
        // Hide/show panes
        $('.gm-sync-pane').each(function () {
            var pane = parseInt($(this).data('pane'), 10);
            if (pane === n) {
                $(this).removeAttr('hidden').addClass('gm-sync-pane--active');
            } else {
                $(this).attr('hidden', '').removeClass('gm-sync-pane--active');
            }
        });
        // Update step indicators
        $('.gm-sync-steps__item').each(function () {
            var step = parseInt($(this).data('step'), 10);
            $(this).removeClass('gm-sync-steps__item--active gm-sync-steps__item--completed');
            if (step === n) {
                $(this).addClass('gm-sync-steps__item--active').attr('aria-selected', 'true');
            } else {
                if (step < n) $(this).addClass('gm-sync-steps__item--completed');
                $(this).attr('aria-selected', 'false');
            }
        });
    }

    /* ── Platform card selection ──────────────────────────────────── */
    $(document).on('click', '.gm-sync-platform-card', function () {
        var platform = $(this).data('platform');
        if (!platform) return;

        activePlatform = platform;

        // Mark selected card
        $('.gm-sync-platform-card').removeClass('gm-sync-platform-card--selected');
        $(this).addClass('gm-sync-platform-card--selected');

        if (platform === 'manual') {
            // "Start Fresh" — no wizard needed, handled by the link in the card
            return;
        }

        // Update Step 2 heading
        var label = $(this).find('.gm-sync-platform-card__name').text();
        $('.gm-sync-platform-label').text(label);

        // Show the right credentials form (template uses data-platform-form + gm-sync-form--*)
        $('.gm-sync-form').hide();
        $('[data-platform-form="' + platform + '"]').show();

        // Store in hidden field
        $('#gm-sync-chosen-platform').val(platform);

        showStep(2);
    });

    /* ── Back button ─────────────────────────────────────────────── */
    $(document).on('click', '.gm-sync-back-btn', function () {
        var target = parseInt($(this).data('back'), 10) || (currentStep - 1);
        showStep(target);
    });

    /* ── Test connection ─────────────────────────────────────────── */
    $(document).on('click', '.gm-sync-test-btn', function () {
        var $btn      = $(this);
        var platform  = $btn.data('test') || activePlatform;
        var $feedback = $btn.closest('.gm-sync-creds-form, .gm-sync-field-group')
                            .find('.gm-sync-test-feedback');
        if (!$feedback.length) {
            $feedback = $('<span class="gm-sync-test-feedback"></span>').insertAfter($btn);
        }

        $btn.prop('disabled', true).text('Testing…');
        $feedback.text('').removeClass('gm-sync-test-feedback--ok gm-sync-test-feedback--err');

        $.post(ajaxUrl, {
            action:      'gm_sync_test',
            nonce:       nonce,
            source_type: platform,
            credentials: JSON.stringify(collectCredentials(platform)),
        })
        .done(function (res) {
            if (res.success) {
                $feedback.addClass('gm-sync-test-feedback--ok').text('✓ Connection successful');
            } else {
                $feedback.addClass('gm-sync-test-feedback--err').text('✗ ' + (res.data || 'Connection failed'));
            }
        })
        .fail(function () {
            $feedback.addClass('gm-sync-test-feedback--err').text('✗ Request failed. Check your connection.');
        })
        .always(function () {
            $btn.prop('disabled', false).text('Test Connection');
        });
    });

    /* ── Collect credentials from active form ────────────────────── */
    function collectCredentials(platform) {
        var creds = {};
        var $form = $('[data-platform-form="' + (platform || activePlatform) + '"]');
        $form.find('input[name], select[name], textarea[name]').each(function () {
            var name = $(this).attr('name');
            if (name) creds[name] = $(this).val();
        });
        return creds;
    }

    /* ── Fetch products ──────────────────────────────────────────── */
    $('#gm-sync-fetch-btn, .gm-sync-fetch-btn').on('click', function () {
        fetchProducts();
    });
    $(document).on('click', '#gm-sync-fetch-btn', fetchProducts);

    function fetchProducts() {
        var platform = activePlatform || $('#gm-sync-chosen-platform').val();
        if (!platform) {
            alert('Please select a platform first.');
            return;
        }
        activePlatform = platform;

        // Move to step 3 and show loader
        showStep(3);
        $('#gm-sync-review-loading').removeAttr('hidden').show();
        $('#gm-sync-review-wrap').attr('hidden', '').hide();
        $('#gm-sync-review-platform').text(
            $('.gm-sync-platform-card[data-platform="' + platform + '"] .gm-sync-platform-card__name').text()
        );

        var isFile = (platform === 'csv');
        var request;

        if (isFile) {
            var fileInput = document.getElementById('gm-sync-csv-file');
            if (!fileInput || !fileInput.files.length) {
                showFetchError('Please select a CSV file.');
                return;
            }
            var fd = new FormData();
            fd.append('action',      'gm_sync_fetch');
            fd.append('nonce',       nonce);
            fd.append('source_type', platform);
            fd.append('file',        fileInput.files[0]);

            request = $.ajax({
                url:         ajaxUrl,
                type:        'POST',
                data:        fd,
                processData: false,
                contentType: false,
            });
        } else {
            var creds = collectCredentials(platform);
            request = $.post(ajaxUrl, {
                action:      'gm_sync_fetch',
                nonce:       nonce,
                source_type: platform,
                credentials: JSON.stringify(creds),
            });
        }

        request
            .done(function (res) {
                $('#gm-sync-review-loading').hide();
                if (res.success && res.data && res.data.products) {
                    renderProductTable(res.data.products);
                } else {
                    showFetchError(res.data || 'Failed to fetch products.');
                }
            })
            .fail(function () {
                showFetchError('Request failed. Please try again.');
            });
    }

    function showFetchError(msg) {
        $('#gm-sync-review-loading').hide();
        $('#gm-sync-review-wrap').removeAttr('hidden').show();
        $('#gm-sync-product-tbody').html(
            '<tr><td colspan="5" class="gm-sync-table__empty">⚠️ ' + escHtml(msg) + '</td></tr>'
        );
        $('#gm-sync-import-btn').prop('disabled', true);
    }

    /* ── Render product table ────────────────────────────────────── */
    function renderProductTable(products) {
        window.gmSyncProducts = products;
        var $tbody = $('#gm-sync-product-tbody');
        $tbody.empty();

        if (!products.length) {
            $tbody.html('<tr><td colspan="5" class="gm-sync-table__empty">No products found in this source.</td></tr>');
            $('#gm-sync-import-btn').prop('disabled', true);
            $('#gm-sync-review-wrap').removeAttr('hidden').show();
            return;
        }

        $.each(products, function (i, p) {
            var thumb = (p.images && p.images[0])
                ? '<img src="' + escAttr(p.images[0]) + '" width="40" height="40" style="object-fit:cover;border-radius:4px;" alt="" loading="lazy">'
                : '<span class="gm-sync-thumb-placeholder" aria-hidden="true">📦</span>';

            var price = (p.price !== undefined && p.price !== '')
                ? '₹' + parseFloat(p.price).toFixed(2) : '—';

            var row = '<tr>' +
                '<td class="gm-sync-table__check"><input type="checkbox" class="gm-sync-product-check" data-index="' + i + '" checked aria-label="Select ' + escAttr(p.title || '') + '"></td>' +
                '<td class="gm-sync-table__thumb">' + thumb + '</td>' +
                '<td class="gm-sync-table__title">' + escHtml(p.title || '(no title)') + '</td>' +
                '<td class="gm-sync-table__price">' + escHtml(price) + '</td>' +
                '<td class="gm-sync-table__sku"><code>' + escHtml(p.sku || '—') + '</code></td>' +
                '</tr>';
            $tbody.append(row);
        });

        $('#gm-sync-product-count').text(products.length);
        updateImportCount();
        $('#gm-sync-review-wrap').removeAttr('hidden').show();
        $('#gm-sync-import-btn').prop('disabled', false);
    }

    /* ── Select all checkbox ─────────────────────────────────────── */
    $(document).on('change', '#gm-sync-select-all', function () {
        var checked = $(this).prop('checked');
        $('.gm-sync-product-check').prop('checked', checked);
        updateImportCount();
    });

    $(document).on('change', '.gm-sync-product-check', function () {
        var total   = $('.gm-sync-product-check').length;
        var checked = $('.gm-sync-product-check:checked').length;
        $('#gm-sync-select-all').prop('indeterminate', checked > 0 && checked < total);
        $('#gm-sync-select-all').prop('checked', checked === total);
        updateImportCount();
    });

    function updateImportCount() {
        var count = $('.gm-sync-product-check:checked').length;
        $('#gm-sync-import-count').text(count);
        $('#gm-sync-import-btn').prop('disabled', count === 0);
    }

    /* ── Import products ─────────────────────────────────────────── */
    $(document).on('click', '#gm-sync-import-btn', function () {
        importProducts();
    });

    function importProducts() {
        var selected = [];
        $('.gm-sync-product-check:checked').each(function () {
            var idx = parseInt($(this).data('index'), 10);
            if (window.gmSyncProducts && window.gmSyncProducts[idx]) {
                selected.push(window.gmSyncProducts[idx]);
            }
        });

        if (!selected.length) return;

        showStep(4);
        $('#gm-sync-progress-wrap').removeAttr('hidden').show();
        $('#gm-sync-results-summary').attr('hidden', '').hide();
        startProgressAnimation(selected.length);

        $.post(ajaxUrl, {
            action:      'gm_sync_import',
            nonce:       nonce,
            source_type: activePlatform,
            products:    JSON.stringify(selected),
        })
        .done(function (res) {
            stopProgressAnimation();
            if (res.success) {
                showResults(res.data);
                saveConnection(res.data);
            } else {
                showResults({ synced: 0, failed: selected.length });
            }
        })
        .fail(function () {
            stopProgressAnimation();
            showResults({ synced: 0, failed: selected.length });
        });
    }

    /* ── Progress bar ────────────────────────────────────────────── */
    var progressTimer = null;
    var progressVal   = 0;

    function startProgressAnimation(total) {
        progressVal = 0;
        $('#gm-sync-progress-fill').css('width', '0%');
        $('#gm-sync-progress-fraction').text('0 / ' + total);
        $('#gm-sync-progress-label').text('Importing…');

        progressTimer = setInterval(function () {
            progressVal = Math.min(progressVal + Math.random() * 8, 85);
            $('#gm-sync-progress-fill').css('width', progressVal.toFixed(0) + '%');
            var approxDone = Math.round((progressVal / 100) * total);
            $('#gm-sync-progress-fraction').text(approxDone + ' / ' + total);
        }, 400);
    }

    function stopProgressAnimation() {
        if (progressTimer) { clearInterval(progressTimer); progressTimer = null; }
        $('#gm-sync-progress-fill').css('width', '100%');
    }

    /* ── Show results ────────────────────────────────────────────── */
    function showResults(data) {
        var synced = data.synced || 0;
        var failed = data.failed || 0;

        $('#gm-sync-progress-wrap').hide();
        $('#gm-sync-success-count').text(synced);
        $('#gm-sync-fail-count').text(failed);

        if (failed > 0) {
            $('#gm-sync-fail-stat').removeAttr('hidden').show();
        } else {
            $('#gm-sync-fail-stat').attr('hidden', '').hide();
        }

        if (data.failed_items && data.failed_items.length) {
            var $list = $('#gm-sync-failed-list').empty();
            $.each(data.failed_items, function (_, item) {
                $list.append('<li><strong>' + escHtml(item.title) + '</strong> — ' + escHtml(item.error) + '</li>');
            });
            $('#gm-sync-failed-items').removeAttr('hidden').show();
        }

        $('#gm-sync-results-summary').removeAttr('hidden').show();
        loadHistory();
    }

    /* ── Sync Again ──────────────────────────────────────────────── */
    $(document).on('click', '#gm-sync-again-btn', function () {
        activePlatform = '';
        window.gmSyncProducts = [];
        $('.gm-sync-platform-card').removeClass('gm-sync-platform-card--selected');
        $('#gm-sync-product-tbody').empty();
        showStep(1);
    });

    /* ── Load sync history ───────────────────────────────────────── */
    function loadHistory() {
        $.post(ajaxUrl, { action: 'gm_sync_history', nonce: nonce })
         .done(function (res) {
            if (!res.success || !res.data || !res.data.jobs) return;
            renderHistory(res.data.jobs);
        });
    }

    function renderHistory(jobs) {
        var $wrap = $('#gm-sync-history');
        var $table = $wrap.find('.gm-sync-history-table tbody');
        if (!$table.length) {
            $wrap.append(
                '<table class="gm-sync-history-table">' +
                '<thead><tr><th>Date</th><th>Source</th><th>Total</th><th>Synced</th><th>Failed</th><th>Status</th></tr></thead>' +
                '<tbody></tbody></table>'
            );
            $table = $wrap.find('tbody');
        }
        $table.empty();

        if (!jobs.length) {
            $table.html('<tr><td colspan="6" class="gm-sync-table__empty">No sync jobs yet.</td></tr>');
            return;
        }

        $.each(jobs, function (_, job) {
            var date   = job.created_at ? job.created_at.slice(0, 16).replace('T', ' ') : '—';
            var source = escHtml(job.source_type || '—');
            var badge  = '<span class="gm-sync-status-badge gm-sync-status-badge--' + escAttr(job.status) + '">' + escHtml(job.status) + '</span>';
            $table.append(
                '<tr>' +
                '<td>' + escHtml(date) + '</td>' +
                '<td>' + source + '</td>' +
                '<td>' + (job.total  || 0) + '</td>' +
                '<td>' + (job.synced || 0) + '</td>' +
                '<td>' + (job.failed || 0) + '</td>' +
                '<td>' + badge + '</td>' +
                '</tr>'
            );
        });
    }

    /* ── Drag-and-drop file zone ─────────────────────────────────── */
    $(document).on('dragover dragenter', '#gm-sync-dropzone', function (e) {
        e.preventDefault();
        $(this).addClass('gm-sync-dropzone--over');
    });
    $(document).on('dragleave drop', '#gm-sync-dropzone', function (e) {
        e.preventDefault();
        $(this).removeClass('gm-sync-dropzone--over');
        if (e.type === 'drop') {
            var files = e.originalEvent.dataTransfer.files;
            if (files.length) {
                $('#gm-sync-csv-file').prop('files', files);
                $(this).find('.gm-sync-dropzone__label').text('📄 ' + files[0].name);
            }
        }
    });
    $(document).on('click', '#gm-sync-dropzone', function () {
        $('#gm-sync-csv-file').trigger('click');
    });
    $(document).on('change', '#gm-sync-csv-file', function () {
        var name = this.files[0] ? this.files[0].name : 'Choose a file';
        $('#gm-sync-dropzone .gm-sync-dropzone__label').text('📄 ' + name);
    });

    /* ── CONNECTION MANAGEMENT ───────────────────────────────────── */

    function loadConnections() {
        $.post(ajaxUrl, { action: 'gm_sync_get_connections', nonce: nonce })
         .done(function (res) {
            if (res.success && res.data && res.data.connections) {
                renderConnections(res.data.connections);
            } else {
                renderConnections([]);
            }
        })
        .fail(function () {
            renderConnections([]);
        });
    }

    function getPlatformIcon(platform) {
        var icons = {
            shopify:    '🛍️',
            woocommerce:'🛒',
            csv:        '📄',
            amazon:     '📦',
            etsy:       '🎨',
            ebay:       '🏷️',
        };
        return icons[platform] || '🔗';
    }

    function renderConnections(connections) {
        var $list = $('#gm-sync-connections-list');

        if (!connections || !connections.length) {
            $list.hide();
            // Show the wizard area
            $('.gm-sync-wizard').show();
            return;
        }

        $list.empty().show();

        $.each(connections, function (_, conn) {
            var statusClass = conn.status === 'active' ? 'gm-sync-status-badge--active' : 'gm-sync-status-badge--paused';
            var statusLabel = conn.status === 'active' ? 'Active' : 'Paused';
            var toggleLabel = conn.status === 'active' ? 'Pause' : 'Resume';
            var toggleStatus = conn.status === 'active' ? 'paused' : 'active';
            var icon = getPlatformIcon(conn.platform);
            var platformLabel = escHtml(conn.platform_label || conn.platform || '');
            var lastSynced = conn.last_synced ? timeAgo(conn.last_synced) : 'Never';
            var nextSync = conn.next_sync ? escHtml(conn.next_sync) : '—';
            var productCount = conn.product_count !== undefined ? parseInt(conn.product_count, 10) : 0;

            var card =
                '<div class="gm-sync-connection-card" data-connection-id="' + escAttr(String(conn.id)) + '">' +
                    '<div class="gm-sync-connection-card__header">' +
                        '<span class="gm-sync-connection-card__icon" aria-hidden="true">' + icon + '</span>' +
                        '<span class="gm-sync-connection-card__name">' + platformLabel + '</span>' +
                        '<span class="gm-sync-status-badge ' + statusClass + '">' + statusLabel + '</span>' +
                    '</div>' +
                    '<div class="gm-sync-connection-card__meta">' +
                        '<span>Last synced: <strong>' + escHtml(lastSynced) + '</strong></span>' +
                        '<span>Next sync: <strong>' + nextSync + '</strong></span>' +
                        '<span>Products: <strong>' + productCount + '</strong></span>' +
                    '</div>' +
                    '<div class="gm-sync-connection-card__actions">' +
                        '<button class="button gm-sync-now-btn" data-id="' + escAttr(String(conn.id)) + '">Sync Now</button>' +
                        '<button class="button gm-sync-toggle-btn" data-id="' + escAttr(String(conn.id)) + '" data-status="' + escAttr(conn.status) + '">' + toggleLabel + '</button>' +
                        '<button class="button gm-sync-delete-btn" data-id="' + escAttr(String(conn.id)) + '">Delete</button>' +
                    '</div>' +
                '</div>';

            $list.append(card);
        });
    }

    // Sync Now
    $(document).on('click', '.gm-sync-now-btn', function () {
        var $btn = $(this);
        var connectionId = $btn.data('id');

        $btn.prop('disabled', true).html('Syncing… <span class="gm-sync-spinner"></span>');

        $.post(ajaxUrl, {
            action:        'gm_sync_now',
            nonce:         nonce,
            connection_id: connectionId,
        })
        .done(function (res) {
            if (res.success) {
                var count = (res.data && res.data.synced) ? res.data.synced : 0;
                showToast('Synced ' + count + ' products', 'success');
                loadConnections();
            } else {
                showToast((res.data && res.data.message) ? res.data.message : 'Sync failed. Please try again.', 'error');
                $btn.prop('disabled', false).text('Sync Now');
            }
        })
        .fail(function () {
            showToast('Sync request failed. Please check your connection.', 'error');
            $btn.prop('disabled', false).text('Sync Now');
        });
    });

    // Pause / Resume
    $(document).on('click', '.gm-sync-toggle-btn', function () {
        var $btn = $(this);
        var connectionId = $btn.data('id');
        var currentStatus = $btn.data('status');
        var newStatus = (currentStatus === 'active') ? 'paused' : 'active';

        $btn.prop('disabled', true);

        $.post(ajaxUrl, {
            action:        'gm_sync_toggle_connection',
            nonce:         nonce,
            connection_id: connectionId,
            status:        newStatus,
        })
        .done(function (res) {
            if (res.success) {
                loadConnections();
            } else {
                $btn.prop('disabled', false);
            }
        })
        .fail(function () {
            $btn.prop('disabled', false);
        });
    });

    // Delete
    $(document).on('click', '.gm-sync-delete-btn', function () {
        var $btn = $(this);
        var connectionId = $btn.data('id');

        if (!confirm('Delete this connection? Products already imported will remain.')) {
            return;
        }

        $btn.prop('disabled', true);

        $.post(ajaxUrl, {
            action:        'gm_sync_delete_connection',
            nonce:         nonce,
            connection_id: connectionId,
        })
        .done(function (res) {
            if (res.success) {
                loadConnections();
            } else {
                $btn.prop('disabled', false);
            }
        })
        .fail(function () {
            $btn.prop('disabled', false);
        });
    });

    // Add New Connection button
    $(document).on('click', '.gm-sync-add-new-btn', function () {
        var $wizard = $('.gm-sync-wizard');
        showStep(1);
        $wizard.show();
        $('html, body').animate({ scrollTop: $wizard.offset().top - 40 }, 400);
    });

    /* ── SAVE CONNECTION (after Step 4 import) ───────────────────── */
    function saveConnection(importData) {
        var interval = $('#gm-sync-interval').val() || 'daily';
        var webhooksEnabled = $('#gm-sync-enable-webhooks').is(':checked');

        $.post(ajaxUrl, {
            action:      'gm_sync_save_connection',
            nonce:       nonce,
            platform:    activePlatform,
            credentials: JSON.stringify(collectCredentials()),
            interval:    interval,
        })
        .done(function (res) {
            if (res.success) {
                var $step4msg = $('#gm-sync-autosync-msg');
                if (!$step4msg.length) {
                    $step4msg = $('<p id="gm-sync-autosync-msg" class="gm-sync-autosync-msg"></p>');
                    $('#gm-sync-results-summary').append($step4msg);
                }
                $step4msg.text('Auto-sync activated');

                if (webhooksEnabled) {
                    var connectionId = res.data && res.data.connection_id ? res.data.connection_id : null;
                    registerWebhook(connectionId, $step4msg);
                }
            }
        });
    }

    function registerWebhook(connectionId, $msgEl) {
        $.post(ajaxUrl, {
            action:        'gm_sync_register_webhook',
            nonce:         nonce,
            connection_id: connectionId,
            platform:      activePlatform,
            credentials:   JSON.stringify(collectCredentials()),
        })
        .done(function (res) {
            if ($msgEl && $msgEl.length) {
                if (res.success) {
                    $msgEl.text('Auto-sync activated · Webhook registered');
                } else {
                    $msgEl.text('Auto-sync activated · Webhook registration failed');
                }
            }
        })
        .fail(function () {
            if ($msgEl && $msgEl.length) {
                $msgEl.text('Auto-sync activated · Webhook registration failed');
            }
        });
    }

    /* ── TIME FORMATTING ─────────────────────────────────────────── */
    function timeAgo(datetimeString) {
        if (!datetimeString) return 'Never';

        var then = new Date(datetimeString);
        if (isNaN(then.getTime())) return 'Never';

        var now = new Date();
        var diffMs = now - then;
        var diffSeconds = Math.floor(diffMs / 1000);
        var diffMinutes = Math.floor(diffSeconds / 60);
        var diffHours   = Math.floor(diffMinutes / 60);
        var diffDays    = Math.floor(diffHours / 24);

        if (diffDays > 0) {
            return diffDays + (diffDays === 1 ? ' day ago' : ' days ago');
        }
        if (diffHours > 0) {
            return diffHours + (diffHours === 1 ? ' hour ago' : ' hours ago');
        }
        if (diffMinutes > 0) {
            return diffMinutes + (diffMinutes === 1 ? ' minute ago' : ' minutes ago');
        }
        return 'Just now';
    }

    /* ── TOAST NOTIFICATIONS ─────────────────────────────────────── */
    function showToast(message, type) {
        type = type || 'success';

        var bgColor = (type === 'error') ? '#c0392b' : '#27ae60';

        var $toast = $(
            '<div class="gm-sync-toast" role="alert" aria-live="polite">' +
                escHtml(message) +
            '</div>'
        ).css({
            position:     'fixed',
            bottom:       '24px',
            right:        '24px',
            background:   bgColor,
            color:        '#fff',
            padding:      '12px 20px',
            borderRadius: '6px',
            boxShadow:    '0 4px 12px rgba(0,0,0,0.2)',
            fontSize:     '14px',
            fontWeight:   '500',
            zIndex:       99999,
            opacity:      0,
            transition:   'opacity 0.3s ease',
        });

        $('body').append($toast);

        // Fade in
        setTimeout(function () {
            $toast.css('opacity', 1);
        }, 10);

        // Auto-remove after 3s
        setTimeout(function () {
            $toast.css('opacity', 0);
            setTimeout(function () {
                $toast.remove();
            }, 300);
        }, 3000);
    }

    /* ── Utilities ───────────────────────────────────────────────── */
    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function escAttr(str) {
        return String(str).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    /* ── Init ────────────────────────────────────────────────────── */
    $(function () {
        showStep(1);
        loadHistory();
        loadConnections();
    });

})(jQuery);
