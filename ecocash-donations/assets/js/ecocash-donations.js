/**
 * EcoCash Donations — frontend logic.
 *
 * Flow:
 *  1. Donor submits form → AJAX create donation → EcoCash STK push.
 *  2. Poll status endpoint every 3s (up to ~90s) until SUCCESS/FAILED.
 *  3. On completion, the donor receives an email receipt (server-side).
 */
(function () {
	'use strict';

	if (typeof ecocashDonations === 'undefined') {
		return;
	}

	document.addEventListener('submit', function (event) {
		var form = event.target;
		if (!form || !form.classList.contains('ecocash-donate-form')) {
			return;
		}
		event.preventDefault();
		handleSubmit(form);
	});

	function handleSubmit(form) {
		var wrap = form.closest('.ecocash-donate-wrap');
		var statusBox = wrap ? wrap.querySelector('.ecocash-status') : null;
		var button = form.querySelector('.ecocash-donate-btn');
		var phone = (form.querySelector('.ecocash-phone') || {}).value || '';
		var customInput = form.querySelector('.ecocash-custom-amount');
		var chosen = form.querySelector('input[name="ecocash_amount"]:checked');
		var nameInput = form.querySelector('.ecocash-name');
		var emailInput = form.querySelector('.ecocash-email');
		var messageInput = form.querySelector('.ecocash-message');

		if (!chosen) {
			show(statusBox, 'Please choose a donation amount.', 'is-error');
			return;
		}

		var amount = chosen.value;
		var custom = '';

		if (amount === 'custom') {
			custom = customInput ? customInput.value : '';
			if (!custom || parseFloat(custom) <= 0) {
				show(statusBox, 'Please enter a donation amount.', 'is-error');
				return;
			}
		}

		var name = nameInput ? nameInput.value.trim() : '';
		var email = emailInput ? emailInput.value.trim() : '';

		if (name.length < 2) {
			show(statusBox, 'Please enter your name.', 'is-error');
			return;
		}

		if (!email || email.indexOf('@') === -1) {
			show(statusBox, 'Please enter a valid email address for your receipt.', 'is-error');
			return;
		}

		if (!phone.trim()) {
			show(statusBox, 'Please enter your EcoCash number.', 'is-error');
			return;
		}

		setBusy(button, true);
		show(statusBox, 'Sending payment request…', 'is-pending');

		var body = new URLSearchParams();
		body.append('action', 'ecocash_donate');
		body.append('nonce', getNonce(form));
		body.append('phone', phone.trim());
		body.append('amount', amount);
		body.append('name', name);
		body.append('email', email);
		if (amount === 'custom') {
			body.append('custom', custom);
		}
		if (messageInput && messageInput.value.trim()) {
			body.append('message', messageInput.value.trim());
		}

		post(body)
			.then(function (res) {
				if (!res.success) {
					setBusy(button, false);
					show(statusBox, res.data && res.data.message ? res.data.message : 'Something went wrong.', 'is-error');
					return;
				}

				show(statusBox, res.data.message || 'Waiting for approval on your phone…', 'is-pending');
				poll(res.data.donationId, form, statusBox, button);
			})
			.catch(function () {
				setBusy(button, false);
				show(statusBox, 'Network error. Please try again.', 'is-error');
			});
	}

	/**
	 * Poll the status endpoint every 3 seconds, max 30 attempts (~90s).
	 */
	function poll(donationId, form, statusBox, button) {
		var attempts = 0;
		var maxAttempts = 30;
		var timer = null;

		timer = setInterval(function () {
			attempts += 1;

			if (attempts > maxAttempts) {
				clearInterval(timer);
				setBusy(button, false);
				show(
					statusBox,
					'This is taking longer than expected. If you approved the payment, your donation will be recorded shortly.',
					'is-pending'
				);
				return;
			}

			var body = new URLSearchParams();
			body.append('action', 'ecocash_status');
			body.append('nonce', getNonce(form));
			body.append('donationId', donationId);

			post(body)
				.then(function (res) {
					if (!res || !res.success || !res.data) {
						return;
					}

					if (res.data.status === 'completed') {
						clearInterval(timer);
						setBusy(button, false);
						form.reset();
						show(statusBox, res.data.message, 'is-success');
						return;
					}

					if (res.data.status === 'failed') {
						clearInterval(timer);
						setBusy(button, false);
						show(statusBox, res.data.message, 'is-error');
						return;
					}

					// Still pending — keep waiting.
				})
				.catch(function () {
					/* transient network hiccup — keep polling */
				});
		}, 3000);
	}

	function post(body) {
		return fetch(ecocashDonations.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
			},
			body: body.toString(),
		}).then(function (response) {
			return response.json();
		});
	}

	function getNonce(form) {
		var field = form.querySelector('input[name="ecocash_nonce"]');
		return field ? field.value : '';
	}

	function show(box, message, cls) {
		if (!box) {
			return;
		}
		box.hidden = false;
		box.className = 'ecocash-status ' + cls;
		box.textContent = message;
	}

	function setBusy(button, busy) {
		if (!button) {
			return;
		}
		if (busy) {
			if (!button.dataset.label) {
				button.dataset.label = button.textContent;
			}
			button.disabled = true;
			button.textContent = 'Processing…';
		} else {
			button.disabled = false;
			button.textContent = button.dataset.label || 'Donate with EcoCash';
		}
	}
})();
