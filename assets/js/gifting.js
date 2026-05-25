/* Gifting Marketplace — frontend interactions v1.0.0 */
(function ($) {
    'use strict';

    var GM = {

        init: function () {
            GM.smoothAnchorScroll();
            GM.cartToast();
            GM.ameliaBookingCallback();
            GM.stickyCartUpdate();
        },

        /* ── Smooth scroll for #gm-* anchor links ───────────────── */
        smoothAnchorScroll: function () {
            $(document).on('click', 'a[href^="#gm-"]', function (e) {
                var target = $($.attr(this, 'href'));
                if (!target.length) return;
                e.preventDefault();
                $('html,body').animate(
                    { scrollTop: target.offset().top - 90 },
                    480,
                    'swing'
                );
            });
        },

        /* ── Toast when product added to cart via AJAX ───────────── */
        cartToast: function () {
            $(document.body).on('added_to_cart', function () {
                var msg = (gmData && gmData.strings && gmData.strings.added)
                    ? gmData.strings.added
                    : '✓ Added to cart!';

                var $toast = $('<div class="gm-added-toast">' + msg + '</div>');
                $('body').append($toast);

                // Small RAF so the browser paints the element before transitioning
                requestAnimationFrame(function () {
                    requestAnimationFrame(function () {
                        $toast.addClass('gm-added-toast--visible');
                    });
                });

                setTimeout(function () {
                    $toast.removeClass('gm-added-toast--visible');
                    setTimeout(function () { $toast.remove(); }, 320);
                }, 2600);
            });
        },

        /* ── After Amelia booking completes: nudge toward gifts ─── */
        ameliaBookingCallback: function () {
            // Amelia fires a custom JS event after a booking is confirmed
            // (exact event name varies by Amelia version; cover both variants)
            var events = [
                'amelia.booking.confirmed',
                'amelia_booking_confirmed',
                'ameliaBookingCanceled' // not a typo — Amelia uses this label
            ];

            events.forEach(function (evtName) {
                document.addEventListener(evtName, function () {
                    var giftsSection = document.getElementById('gm-gifts');
                    if (!giftsSection) return;

                    // Small delay so the Amelia success UI renders first
                    setTimeout(function () {
                        giftsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });

                        var msg = (gmData && gmData.strings && gmData.strings.booked)
                            ? gmData.strings.booked
                            : '🎉 Event booked — now pick your gifts below.';

                        var $toast = $('<div class="gm-added-toast">' + msg + '</div>');
                        $('body').append($toast);
                        requestAnimationFrame(function () {
                            requestAnimationFrame(function () { $toast.addClass('gm-added-toast--visible'); });
                        });
                        setTimeout(function () {
                            $toast.removeClass('gm-added-toast--visible');
                            setTimeout(function () { $toast.remove(); }, 320);
                        }, 3200);
                    }, 600);
                });
            });
        },

        /* ── Keep cart icon count fresh after AJAX add ──────────── */
        stickyCartUpdate: function () {
            $(document.body).on(
                'wc_fragments_refreshed wc_fragments_loaded added_to_cart',
                function () {
                    // Astra cart count selector — update aria-label for a11y too
                    var $count = $('.ast-cart-menu-wrap .count');
                    if ($count.length) {
                        var n = parseInt($count.text(), 10) || 0;
                        $count.attr('aria-label', n + ' items in cart');
                    }
                }
            );
        }
    };

    $(function () { GM.init(); });

}(jQuery));
