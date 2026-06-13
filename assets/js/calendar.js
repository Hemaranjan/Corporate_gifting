/* Giftelier — Custom Calendar + Occasion Assignment */
(function ($) {
    'use strict';

    var D = window.gmOcc;
    if (!D) return;

    /* ── Init by page context ─────────────────────────────────────── */
    $(function () {
        if ($('#gm-cal-grid').length)  initCalendar();
        if ($('#gm-occ-panel').length) initProductPanel();
    });

    /* ════════════════════════════════════════════════════════════════
       CALENDAR PAGE
    ════════════════════════════════════════════════════════════════ */
    function initCalendar() {
        var today    = new Date();
        var year     = today.getFullYear();
        var month    = today.getMonth();
        var occasions = D.occasions ? D.occasions.slice() : [];

        var MONTHS = ['January','February','March','April','May','June',
                      'July','August','September','October','November','December'];
        var DAYS   = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

        function pad(n)        { return n < 10 ? '0' + n : '' + n; }
        function dateKey(y,m,d){ return y + '-' + pad(m + 1) + '-' + pad(d); }
        function todayKey()    { return dateKey(today.getFullYear(), today.getMonth(), today.getDate()); }

        function hexRgba(hex, a) {
            var r = parseInt(hex.slice(1,3),16),
                g = parseInt(hex.slice(3,5),16),
                b = parseInt(hex.slice(5,7),16);
            return 'rgba(' + r + ',' + g + ',' + b + ',' + a + ')';
        }

        function escH(s) {
            return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;')
                            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        /* ── Render grid ─────────────────────────────────────────── */
        function render() {
            $('#gm-cal-heading').text(MONTHS[month] + ' ' + year);

            var firstDay    = new Date(year, month, 1).getDay();
            var daysInMonth = new Date(year, month + 1, 0).getDate();
            var html        = '';

            DAYS.forEach(function (d) {
                html += '<div class="gm-cal-dh">' + d + '</div>';
            });

            for (var i = 0; i < firstDay; i++) {
                html += '<div class="gm-cal-cell"></div>';
            }

            for (var d = 1; d <= daysInMonth; d++) {
                var k      = dateKey(year, month, d);
                var fests  = (D.festivals && D.festivals[k]) ? D.festivals[k] : [];
                var occs   = occasions.filter(function (o) { return o.date === k; });
                var isToday = (k === todayKey());

                var cls = 'gm-cal-cell';
                if (isToday)     cls += ' is-today';
                if (fests.length) cls += ' has-festival';
                if (occs.length)  cls += ' has-occasion';

                html += '<div class="' + cls + '" data-date="' + k + '">';
                html += '<span class="gm-cal-dn">' + d + '</span>';
                html += '<div class="gm-cal-chips">';

                // Festivals (show up to 2, then +N more)
                fests.slice(0, 2).forEach(function (f) {
                    var c = f.color || '#6366f1';
                    html += '<span class="gm-chip gm-chip--f" style="background:' + hexRgba(c, 0.14) + ';color:' + c + '" title="' + escH(f.name) + '">' +
                            f.icon + ' <span>' + escH(f.name) + '</span></span>';
                });
                if (fests.length > 2) {
                    html += '<span class="gm-chip gm-chip--more">+' + (fests.length - 2) + ' more</span>';
                }

                // User occasions
                occs.forEach(function (o) {
                    var c      = o.color || '#E8386D';
                    var gCount = 0;
                    if (D.giftsBy && D.giftsBy[o.id]) gCount = D.giftsBy[o.id].length;
                    var gLabel = gCount ? ' <em>(' + gCount + ' gift' + (gCount > 1 ? 's' : '') + ')</em>' : '';
                    html += '<span class="gm-chip gm-chip--o" style="background:' + hexRgba(c, 0.14) + ';color:' + c + '" data-id="' + o.id + '">' +
                            escH(o.icon) + ' <span>' + escH(o.title) + gLabel + '</span>' +
                            '<button class="gm-chip-del" data-id="' + o.id + '" aria-label="Remove">×</button>' +
                            '</span>';
                });

                html += '</div>'; // chips
                html += '<button class="gm-cal-add" data-date="' + k + '" aria-label="Add occasion">+</button>';
                html += '</div>'; // cell
            }

            $('#gm-cal-grid').html(html);
            renderList();
        }

        /* ── Occasions list ──────────────────────────────────────── */
        function renderList() {
            var $list  = $('#gm-occasions-list');
            var $count = $('#gm-occ-count');
            $count.text(occasions.length + ' saved');

            if (!occasions.length) {
                $list.html('<p class="gm-dash-empty-text">Click any date on the calendar to add an occasion.</p>');
                return;
            }

            var tk       = todayKey();
            var upcoming = occasions.filter(function (o) { return o.date >= tk; });
            var past     = occasions.filter(function (o) { return o.date < tk; });
            var html     = '';

            if (upcoming.length) {
                html += '<div class="gm-occ-section"><p class="gm-occ-section__label">Upcoming</p>';
                upcoming.forEach(function (o) { html += rowHtml(o); });
                html += '</div>';
            }
            if (past.length) {
                html += '<div class="gm-occ-section"><p class="gm-occ-section__label">Past</p>';
                past.forEach(function (o) { html += rowHtml(o); });
                html += '</div>';
            }
            $list.html(html);
        }

        function fmtINR(n) {
            return '₹' + Number(n).toLocaleString('en-IN');
        }

        function rowHtml(o) {
            var gCount  = D.giftsBy && D.giftsBy[o.id] ? D.giftsBy[o.id].length : 0;
            var dateObj = new Date(o.date + 'T00:00:00');
            var months  = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            var dateStr = dateObj.getDate() + ' ' + months[dateObj.getMonth()] + ' ' + dateObj.getFullYear();
            var diff    = Math.ceil((dateObj - today) / 86400000);
            var dLabel  = diff > 0  ? 'in ' + diff + ' day' + (diff !== 1 ? 's' : '') :
                          diff === 0 ? 'Today!' :
                          Math.abs(diff) + ' day' + (Math.abs(diff) !== 1 ? 's' : '') + ' ago';

            var budgetHtml = o.budget ? '<span class="gm-occ-row__budget">' + fmtINR(o.budget) + ' limit</span>' : '';

            return '<div class="gm-occ-row" data-id="' + o.id + '">' +
                   '<div class="gm-occ-row__dot" style="background:' + hexRgba(o.color || '#E8386D', 0.15) + ';color:' + (o.color || '#E8386D') + '">' + escH(o.icon) + '</div>' +
                   '<div class="gm-occ-row__info">' +
                   '<strong class="gm-occ-row__title">' + escH(o.title) + '</strong>' +
                   '<span class="gm-occ-row__date">' + dateStr + ' · <em>' + dLabel + '</em></span>' +
                   budgetHtml +
                   (gCount ? '<span class="gm-occ-row__gifts">🎁 ' + gCount + ' gift' + (gCount > 1 ? 's' : '') + ' planned</span>' : '') +
                   '</div>' +
                   '<div class="gm-occ-row__actions">' +
                   '<a href="' + escH(D.browseUrl || '') + '" class="gm-btn gm-btn--sm gm-btn--outline">+ Gift</a>' +
                   '<button class="gm-btn gm-btn--sm gm-btn--ghost-rose gm-del-occ" data-id="' + o.id + '">Remove</button>' +
                   '</div></div>';
        }

        /* ── Modal ───────────────────────────────────────────────── */
        function openModal(dateStr) {
            var parts = dateStr.split('-');
            var d     = new Date(+parts[0], +parts[1] - 1, +parts[2]);
            var days  = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
            var months= ['January','February','March','April','May','June','July','August','September','October','November','December'];
            var label = days[d.getDay()] + ', ' + d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();

            $('#gm-occ-date-display').text(label);
            $('#gm-occ-date').val(dateStr);
            $('#gm-occ-title').val('').removeClass('is-error');
            $('#gm-occ-budget').val('');

            // Suggest a name if festival
            var fests = D.festivals && D.festivals[dateStr] ? D.festivals[dateStr] : [];
            $('#gm-occ-title').attr('placeholder',
                fests.length ? fests[0].name + ' celebration…' : 'e.g. Mom\'s Birthday, Team Lunch…');

            $('#gm-occ-modal').addClass('is-open');
            setTimeout(function () { $('#gm-occ-title').trigger('focus'); }, 150);
        }

        function closeModal() { $('#gm-occ-modal').removeClass('is-open'); }

        function saveOccasion() {
            var title  = $.trim($('#gm-occ-title').val());
            var date   = $('#gm-occ-date').val();
            var icon   = $('#gm-occ-icon').val()  || '🎉';
            var color  = $('#gm-occ-color').val() || '#E8386D';
            var budget = parseInt($('#gm-occ-budget').val()) || 0;

            if (!title) { $('#gm-occ-title').addClass('is-error').trigger('focus'); return; }

            var $btn = $('#gm-occ-save').prop('disabled', true).text('Saving…');

            $.post(D.ajaxUrl, { action: 'gm_save_occasion', nonce: D.nonce, title: title, date: date, icon: icon, color: color, budget: budget })
             .done(function (resp) {
                if (resp.success) {
                    occasions = resp.data.occasions;
                    D.occasions = occasions;
                    closeModal();
                    // Navigate to saved month
                    var p = date.split('-');
                    year  = +p[0];
                    month = +p[1] - 1;
                    render();
                }
             })
             .always(function () { $btn.prop('disabled', false).text('Save Occasion'); });
        }

        function deleteOccasion(id) {
            if (!confirm('Remove this occasion?')) return;
            $.post(D.ajaxUrl, { action: 'gm_delete_occasion', nonce: D.nonce, id: id })
             .done(function (resp) {
                if (resp.success) { occasions = resp.data.occasions; D.occasions = occasions; render(); }
             });
        }

        /* ── Events ──────────────────────────────────────────────── */
        $(document)
            .on('click', '#gm-cal-prev', function () { if (--month < 0)  { month = 11; year--; } render(); })
            .on('click', '#gm-cal-next', function () { if (++month > 11) { month = 0;  year++; } render(); })
            .on('click', '#gm-cal-today', function () { year = today.getFullYear(); month = today.getMonth(); render(); })
            .on('click', '.gm-cal-add', function (e) { e.stopPropagation(); openModal($(this).data('date')); })
            .on('click', '.gm-cal-cell', function (e) {
                if ($(e.target).closest('.gm-chip-del, .gm-cal-add').length) return;
                var dt = $(this).data('date');
                if (dt) openModal(dt);
            })
            .on('click', '.gm-chip-del', function (e) { e.stopPropagation(); deleteOccasion($(this).data('id')); })
            .on('click', '.gm-del-occ',  function ()  { deleteOccasion($(this).data('id')); })
            .on('click', '#gm-occ-save', saveOccasion)
            .on('click', '#gm-occ-modal-close, #gm-occ-cancel', closeModal)
            .on('click', '#gm-occ-modal', function (e) { if ($(e.target).is('#gm-occ-modal')) closeModal(); })
            .on('keydown', '#gm-occ-title', function (e) {
                if (e.key === 'Enter')  saveOccasion();
                if (e.key === 'Escape') closeModal();
                $(this).removeClass('is-error');
            })
            .on('click', '.gm-icon-opt', function () {
                $('.gm-icon-opt').removeClass('is-selected');
                $(this).addClass('is-selected');
                $('#gm-occ-icon').val($(this).data('icon'));
            })
            .on('click', '.gm-color-opt', function () {
                $('.gm-color-opt').removeClass('is-selected');
                $(this).addClass('is-selected');
                $('#gm-occ-color').val($(this).data('color'));
            });

        render();
    }

    /* ════════════════════════════════════════════════════════════════
       PRODUCT PAGE PANEL
    ════════════════════════════════════════════════════════════════ */
    function initProductPanel() {
        var assigned = (D.assigned || []).slice();

        $(document).on('click', '.gm-occ-item__btn', function () {
            var $btn    = $(this);
            var $item   = $btn.closest('.gm-occ-item');
            var occ_id  = $btn.data('occasion');
            var prod_id = D.productId;
            var wasAssigned = $item.hasClass('is-assigned');

            $btn.prop('disabled', true);

            $.post(D.ajaxUrl, {
                action:      wasAssigned ? 'gm_unassign_gift' : 'gm_assign_gift',
                nonce:       D.nonce,
                occasion_id: occ_id,
                product_id:  prod_id,
            }).done(function (resp) {
                if (resp.success) {
                    if (wasAssigned) {
                        $item.removeClass('is-assigned');
                        $btn.removeClass('gm-btn--assigned').addClass('gm-btn--primary').text('+ Add');
                    } else {
                        $item.addClass('is-assigned');
                        $btn.removeClass('gm-btn--primary').addClass('gm-btn--assigned').text('✓ Added');
                    }
                }
            }).always(function () { $btn.prop('disabled', false); });
        });
    }

}(jQuery));
