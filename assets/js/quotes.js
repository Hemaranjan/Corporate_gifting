/**
 * Gifting Marketplace — Quotes JS
 *
 * Handles:
 *   1. Product page quote request modal.
 *   2. Customer dashboard: accept / reject quotes.
 *   3. Vendor dashboard: reply with a quoted price.
 */

(function ($) {
	'use strict';

	$(function () {

		/* =============================================================
		   1. PRODUCT PAGE — Quote Request Modal
		   ============================================================= */

		var $overlay = $('#gm-quote-modal');

		// Open modal when "Request a Quote" is clicked.
		$(document).on('click', '.gm-quote-btn', function () {
			var $btn        = $(this);
			var productId   = $btn.data('product');
			var productName = $btn.data('product-name');
			var vendorId    = $btn.data('vendor');

			// Populate hidden inputs.
			$overlay.find('#gm-quote-product-id').val(productId);
			$overlay.find('#gm-quote-vendor-id').val(vendorId);

			// Show product name.
			$overlay.find('.gm-quote-modal__product-name').text(productName);

			// Reset fields.
			$overlay.find('#gm-quote-qty').val(1);
			$overlay.find('#gm-quote-msg').val('');

			// Open modal.
			$overlay.addClass('is-open');
		});

		// Close modal — header X button or footer Cancel button.
		$(document).on('click', '#gm-quote-cancel, .gm-quote-dismiss', function () {
			$overlay.removeClass('is-open');
		});

		// Close modal on overlay backdrop click.
		$(document).on('click', '.gm-quote-modal-overlay', function (e) {
			if ($(e.target).hasClass('gm-quote-modal-overlay')) {
				$overlay.removeClass('is-open');
			}
		});

		// Close on Escape key.
		$(document).on('keydown', function (e) {
			if (e.key === 'Escape' && $overlay.hasClass('is-open')) {
				$overlay.removeClass('is-open');
			}
		});

		// Submit quote request.
		$(document).on('click', '#gm-quote-submit', function () {
			var $btn      = $(this);
			var productId = $overlay.find('#gm-quote-product-id').val();
			var vendorId  = $overlay.find('#gm-quote-vendor-id').val();
			var qty       = $overlay.find('#gm-quote-qty').val();
			var message   = $overlay.find('#gm-quote-msg').val();

			if (!productId || !vendorId) {
				alert('Unable to identify product or vendor. Please refresh and try again.');
				return;
			}

			$btn.prop('disabled', true).text('Sending…');

			$.post(
				window.ajaxurl || (window.gmQuoteNonce && window.gmQuotesPage ? window.gmQuotesPage.ajaxUrl : '/wp-admin/admin-ajax.php'),
				{
					action:     'gm_request_quote',
					nonce:      window.gmQuoteNonce,
					product_id: productId,
					vendor_id:  vendorId,
					quantity:   qty,
					message:    message,
				},
				function (resp) {
					$overlay.removeClass('is-open');

					// Replace the whole quote wrap with a confirmation notice.
					var $wrap = $('.gm-quote-btn[data-product="' + productId + '"]').closest('.gm-quote-wrap');
					$wrap.replaceWith(
						'<div style="border-top:1px solid #f3f4f6;padding-top:14px;margin-top:8px;font-family:\'Plus Jakarta Sans\',sans-serif;font-size:13.5px;color:#10B981;font-weight:700;">&#10003; Quote Requested — we\'ll notify you when the vendor responds.</div>'
					);
				}
			).fail(function () {
				alert('Something went wrong. Please try again.');
				$btn.prop('disabled', false).text('Send Request');
			});
		});


		/* =============================================================
		   2. CUSTOMER QUOTES PAGE — Accept / Reject
		   ============================================================= */

		if ($('.gm-quotes-page').length) {

			var page = window.gmQuotesPage || {};

			// Accept quote.
			$(document).on('click', '.gm-accept-quote', function () {
				var $btn    = $(this);
				var quoteId = $btn.data('id');
				var amount  = $btn.data('amount');

				if (!confirm('Accept this quote for ₹' + Number(amount).toLocaleString('en-IN') + '?')) {
					return;
				}

				$btn.prop('disabled', true).text('Processing…');

				$.post(
					page.ajaxUrl,
					{
						action:   'gm_accept_quote',
						nonce:    page.nonce,
						quote_id: quoteId,
					},
					function (resp) {
						if (resp.success && resp.data && resp.data.redirect) {
							// Show redirecting message.
							$btn.closest('.gm-quote-actions')
								.html('<span style="color:#10B981;font-weight:700;">' + (page.redirecting || 'Redirecting to checkout…') + '</span>');
							window.location.href = resp.data.redirect;
						} else {
							var msg = (resp.data && resp.data.message) ? resp.data.message : 'Something went wrong.';
							alert(msg);
							$btn.prop('disabled', false).text('Accept');
						}
					}
				).fail(function () {
					alert('Something went wrong. Please try again.');
					$btn.prop('disabled', false).text('Accept');
				});
			});

			// Reject quote.
			$(document).on('click', '.gm-reject-quote', function () {
				var $btn    = $(this);
				var quoteId = $btn.data('id');

				if (!confirm('Reject this quote?')) {
					return;
				}

				$btn.prop('disabled', true).text('Rejecting…');

				$.post(
					page.ajaxUrl,
					{
						action:   'gm_reject_quote',
						nonce:    page.nonce,
						quote_id: quoteId,
					},
					function (resp) {
						if (resp.success) {
							// Remove action buttons and update status badge.
							var $row    = $btn.closest('tr');
							var $status = $row.find('.gm-status-badge');

							$status
								.removeClass('gm-status--quoted gm-status--pending gm-status--accepted')
								.addClass('gm-status--rejected')
								.text('Rejected');

							$row.find('.gm-quote-actions').html(
								'<span class="gm-status-badge gm-status--rejected">Rejected</span>'
							);
						} else {
							var msg = (resp.data && resp.data.message) ? resp.data.message : 'Something went wrong.';
							alert(msg);
							$btn.prop('disabled', false).text('Reject');
						}
					}
				).fail(function () {
					alert('Something went wrong. Please try again.');
					$btn.prop('disabled', false).text('Reject');
				});
			});
		}


		/* =============================================================
		   3. VENDOR QUOTES PAGE — Reply with quoted price
		   ============================================================= */

		if ($('.gm-vendor-quotes').length) {

			var vq = window.gmVendorQuotes || {};

			$(document).on('submit', '.gm-reply-form', function (e) {
				e.preventDefault();

				var $form   = $(this);
				var quoteId = $form.data('quote');
				var amount  = parseFloat($form.find('[name="gm_reply_amount"]').val());
				var message = $form.find('[name="gm_reply_msg"]').val();
				var $submit = $form.find('[type="submit"]');

				if (!amount || amount <= 0) {
					alert('Please enter a valid quoted price greater than 0.');
					return;
				}

				$submit.prop('disabled', true).text('Sending…');

				$.post(
					vq.ajaxUrl,
					{
						action:   'gm_vendor_reply',
						nonce:    vq.nonce,
						quote_id: quoteId,
						amount:   amount,
						message:  message,
					},
					function (resp) {
						if (resp.success) {
							// Replace form with success notice.
							$form.replaceWith(
								'<div class="gm-qcard__response">' +
									'<p><strong>Quote sent!</strong> ₹' + Number(amount).toLocaleString('en-IN') + '</p>' +
									(message ? '<p>' + $('<span>').text(message).html() + '</p>' : '') +
								'</div>'
							);

							// Update status badge.
							$('.js-quote-status-' + quoteId)
								.removeClass('gm-status--pending gm-status--accepted gm-status--rejected')
								.addClass('gm-status--quoted')
								.text('Quote Received');
						} else {
							var msg = (resp.data && resp.data.message) ? resp.data.message : 'Something went wrong.';
							alert(msg);
							$submit.prop('disabled', false).text('Send Quote');
						}
					}
				).fail(function () {
					alert('Something went wrong. Please try again.');
					$submit.prop('disabled', false).text('Send Quote');
				});
			});
		}

	}); // end document-ready

})(jQuery);
