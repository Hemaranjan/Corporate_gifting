/* Giftelier — Budget Planner: segment selector + allocation pots */
(function ($) {
    'use strict';

    if (typeof gmBudgetPlanner === 'undefined') return;

    var ajaxUrl  = gmDash.ajaxUrl;
    var nonce    = gmDash.nonce;
    var segment  = gmBudgetPlanner.segment;
    var annual   = parseFloat(gmBudgetPlanner.annual) || 0;

    /* ── Segment-specific extra field definitions ─────────────────── */

    var SEGMENT_FIELDS = {
        corporate: [
            { key: 'department_code',    label: 'Department Code',            type: 'text',   placeholder: 'e.g. HR, SALES, MKTG' },
            { key: 'approval_threshold', label: 'Approval Threshold (₹)',     type: 'number', placeholder: 'Orders above this need sign-off' },
        ],
        school: [
            { key: 'event_type', label: 'Event Type', type: 'select',
              options: ['Annual Day', 'Sports Day', 'Teacher Appreciation', 'Farewell', 'Graduation', 'Other'] },
            { key: 'term',       label: 'Academic Term', type: 'select',
              options: ['Term 1 (Apr–Jul)', 'Term 2 (Aug–Nov)', 'Term 3 (Dec–Mar)'] },
            { key: 'per_recipient_cap', label: 'Per-Recipient Cap (₹)', type: 'number', placeholder: 'Max gift value per student / teacher' },
        ],
        wedding: [
            { key: 'guest_count',    label: 'Guest Count',        type: 'number', placeholder: 'Number of guests' },
            { key: 'per_head_budget',label: 'Per-Head Budget (₹)',type: 'number', placeholder: 'Budget per guest' },
            { key: 'milestone', label: 'Milestone', type: 'select',
              options: ['Save-the-Date', 'Welcome Kits', 'Favors', 'Thank-You Gifts', 'Registry Item'] },
        ],
        hospitals: [
            { key: 'department',     label: 'Department',                      type: 'text',   placeholder: 'e.g. ICU, Pediatrics, OPD' },
            { key: 'compliance_cap', label: 'Compliance Cap per Recipient (₹)', type: 'number', placeholder: 'Regulatory gift value ceiling' },
        ],
        construction: [
            { key: 'project_code', label: 'Project Code',      type: 'text',   placeholder: 'e.g. PT-2024-001' },
            { key: 'project_name', label: 'Project / Site Name',type: 'text',  placeholder: 'e.g. Prestige Tower Phase 2' },
            { key: 'milestone',    label: 'Milestone Type', type: 'select',
              options: ['Foundation', 'Topping-Out', 'Handover', 'Inauguration', 'Client Gift'] },
            { key: 'contractor_tier', label: 'Contractor Tier', type: 'select',
              options: ['Principal', 'Sub-contractor', 'Labour Contractor', 'Client'] },
        ],
    };

    /* ── Segment selector ─────────────────────────────────────────── */

    $(document).on('click', '.gm-segment-pill', function () {
        var $btn    = $(this);
        var newSeg  = $btn.data('segment');
        if ($btn.hasClass('gm-segment-pill--active')) return;

        $('.gm-segment-pill').addClass('gm-segment-pill--saving');

        $.post(ajaxUrl, {
            action:  'gm_set_segment',
            nonce:   nonce,
            segment: newSeg,
        }).done(function (res) {
            if (res.success) {
                window.location.reload();
            } else {
                $('.gm-segment-pill').removeClass('gm-segment-pill--saving');
                alert('Could not save segment. Please try again.');
            }
        }).fail(function () {
            $('.gm-segment-pill').removeClass('gm-segment-pill--saving');
        });
    });

    /* ── Build the segment-specific extra fields ──────────────────── */

    function buildExtraFields(seg) {
        var fields = SEGMENT_FIELDS[seg] || [];
        var html   = '';
        fields.forEach(function (f) {
            html += '<div class="gm-form-group gm-pot-extra-field gm-pot-extra-field--visible" data-seg-field="' + f.key + '">';
            html += '<label class="gm-form-label">' + f.label + '</label>';
            if (f.type === 'select') {
                html += '<select name="meta_' + f.key + '" class="gm-form-input">';
                html += '<option value="">Select…</option>';
                f.options.forEach(function (o) {
                    html += '<option value="' + o + '">' + o + '</option>';
                });
                html += '</select>';
            } else {
                html += '<input type="' + f.type + '" name="meta_' + f.key + '" class="gm-form-input"' +
                        (f.placeholder ? ' placeholder="' + f.placeholder + '"' : '') + ' />';
            }
            html += '</div>';
        });
        return html;
    }

    /* ── Open "Add allocation" modal ──────────────────────────────── */

    $(document).on('click', '#gm-add-pot-btn', function () {
        var $backdrop = $('#gm-pot-modal-backdrop');
        $backdrop.find('.gm-pot-modal__title').text('Add Allocation');
        $backdrop.find('#gm-pot-id').val('');
        $backdrop.find('#gm-pot-form')[0].reset();
        // Inject segment-specific fields
        $backdrop.find('#gm-pot-extra-fields').html(buildExtraFields(segment));
        $backdrop.addClass('gm-pot-modal--open');
        $backdrop.find('#gm-pot-label').focus();
    });

    /* ── Close modal ──────────────────────────────────────────────── */

    $(document).on('click', '#gm-pot-modal-close, #gm-pot-modal-cancel', function () {
        $('#gm-pot-modal-backdrop').removeClass('gm-pot-modal--open');
    });

    $(document).on('click', '#gm-pot-modal-backdrop', function (e) {
        if ($(e.target).is('#gm-pot-modal-backdrop')) {
            $('#gm-pot-modal-backdrop').removeClass('gm-pot-modal--open');
        }
    });

    /* ── Wedding: auto-calculate allocated from guest × per-head ──── */

    $(document).on('input', 'input[name="meta_guest_count"], input[name="meta_per_head_budget"]', function () {
        var guests   = parseFloat($('input[name="meta_guest_count"]').val()) || 0;
        var perHead  = parseFloat($('input[name="meta_per_head_budget"]').val()) || 0;
        var total    = guests * perHead;
        if (total > 0) {
            $('#gm-pot-allocated').val(total);
            $('#gm-wedding-calc-hint').text('Auto-calculated: ' + guests + ' guests × ₹' + perHead.toLocaleString('en-IN') + ' = ₹' + total.toLocaleString('en-IN'));
        } else {
            $('#gm-wedding-calc-hint').text('');
        }
    });

    /* ── Save pot ─────────────────────────────────────────────────── */

    $(document).on('submit', '#gm-pot-form', function (e) {
        e.preventDefault();
        var $form = $(this);
        var $btn  = $form.find('#gm-pot-save-btn');
        $btn.prop('disabled', true).text('Saving…');

        // Collect segment-specific meta fields
        var meta = {};
        $form.find('[name^="meta_"]').each(function () {
            var key = $(this).attr('name').replace('meta_', '');
            meta[key] = $(this).val();
        });

        $.post(ajaxUrl, {
            action:       'gm_save_pot',
            nonce:        nonce,
            id:           $form.find('#gm-pot-id').val(),
            segment:      segment,
            label:        $form.find('#gm-pot-label').val(),
            allocated:    $form.find('#gm-pot-allocated').val(),
            period_start: $form.find('#gm-pot-period-start').val(),
            period_end:   $form.find('#gm-pot-period-end').val(),
            meta_json:    JSON.stringify(meta),
        }).done(function (res) {
            $btn.prop('disabled', false).text('Save');
            if (!res.success) {
                alert(res.data || 'Save failed.');
                return;
            }
            $('#gm-pot-modal-backdrop').removeClass('gm-pot-modal--open');
            // Reload page to reflect new pot in server-rendered table + totals
            window.location.reload();
        }).fail(function () {
            $btn.prop('disabled', false).text('Save');
            alert('Request failed. Please try again.');
        });
    });

    /* ── Delete pot ───────────────────────────────────────────────── */

    $(document).on('click', '.gm-pots-delete-btn', function () {
        var $btn   = $(this);
        var potId  = $btn.data('id');
        var label  = $btn.closest('tr').find('td:first').text().trim();

        if (!confirm('Remove "' + label + '"? This cannot be undone.')) return;
        $btn.prop('disabled', true).text('…');

        $.post(ajaxUrl, {
            action: 'gm_delete_pot',
            nonce:  nonce,
            id:     potId,
        }).done(function (res) {
            if (res.success) {
                var $row = $btn.closest('tr');
                $row.fadeOut(200, function () {
                    $row.remove();
                    recalcTotals();
                });
            } else {
                $btn.prop('disabled', false).text('Remove');
                alert(res.data || 'Delete failed.');
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Remove');
        });
    });

    /* ── Recalculate summary totals after client-side delete ──────── */

    function recalcTotals() {
        var total = 0;
        $('#gm-pots-tbody .gm-pots-table__amount').each(function () {
            var v = parseFloat($(this).text().replace(/[^\d.]/g, '')) || 0;
            total += v;
        });
        var unallocated = Math.max(0, annual - total);
        var pct         = annual > 0 ? Math.min(100, Math.round((total / annual) * 100)) : 0;

        $('.gm-planner-total-allocated').text('₹' + total.toLocaleString('en-IN'));
        $('.gm-planner-unallocated').text('₹' + unallocated.toLocaleString('en-IN'));

        var $fill = $('.gm-allocation-bar__fill');
        $fill.css('width', pct + '%');
        $fill.toggleClass('gm-allocation-bar__fill--over', total > annual && annual > 0);

        if ($('#gm-pots-tbody tr').length === 0) {
            $('#gm-pots-tbody').html('<tr><td colspan="5" class="gm-pots-empty">No allocations yet. Add your first to start planning.</td></tr>');
        }
    }

})(jQuery);
