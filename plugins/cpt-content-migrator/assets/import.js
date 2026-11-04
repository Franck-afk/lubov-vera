(function () {
	'use strict';

	var config = window.cptMigratorImport;

	if (!config || !config.importId) {
		return;
	}

	var root = document.getElementById('cpt-migrator-import-progress');
	var progressBar = document.getElementById('cpt-migrator-import-progress-bar');
	var statusNode = document.getElementById('cpt-migrator-import-status');
	var errorBox = document.getElementById('cpt-migrator-import-error');
	var errorText = errorBox ? errorBox.querySelector('p') : null;
	var retryButton = document.getElementById('cpt-migrator-import-retry');
	var running = false;

	if (!root) {
		return;
	}

	function formatProgress(processed, total) {
		return config.strings.progress
			.replace('%1$d', String(processed))
			.replace('%2$d', String(total));
	}

	function updateProgress(data) {
		var total = parseInt(data.total, 10) || 0;
		var processed = parseInt(data.processed, 10) || 0;

		if (progressBar) {
			progressBar.max = Math.max(1, total);
			progressBar.value = processed;
		}

		if (statusNode) {
			statusNode.textContent = formatProgress(processed, total);
		}
	}

	function hideError() {
		if (errorBox) {
			errorBox.hidden = true;
		}

		if (errorText) {
			errorText.textContent = '';
		}

		if (retryButton) {
			retryButton.hidden = true;
		}
	}

	function showError(message) {
		if (errorBox) {
			errorBox.hidden = false;
		}

		if (errorText) {
			errorText.textContent = message || config.strings.error;
		}

		if (retryButton) {
			retryButton.hidden = false;
		}
	}

	function requestBatch() {
		var body = new URLSearchParams();

		body.append('action', config.action);
		body.append('nonce', config.nonce);
		body.append('import_id', config.importId);

		return fetch(config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: body.toString()
		}).then(function (response) {
			return response.text().then(function (text) {
				var payload;

				try {
					payload = JSON.parse(text);
				} catch (error) {
					throw new Error(config.strings.invalidJson);
				}

				if (!payload || !payload.success) {
					if (payload && payload.data && payload.data.message) {
						throw new Error(payload.data.message);
					}

					throw new Error(config.strings.error);
				}

				return payload.data || {};
			});
		});
	}

	function sendNextBatch() {
		requestBatch().then(function (data) {
			updateProgress(data);

			if (data.done) {
				if (statusNode) {
					statusNode.textContent = config.strings.completing;
				}

				if (data.redirect) {
					window.location.href = data.redirect;
				}

				running = false;
				return;
			}

			sendNextBatch();
		}).catch(function (error) {
			running = false;
			showError(error && error.message ? error.message : config.strings.error);
		});
	}

	function start() {
		if (running) {
			return;
		}

		running = true;
		hideError();

		if (statusNode) {
			statusNode.textContent = config.strings.starting;
		}

		sendNextBatch();
	}

	if (retryButton) {
		retryButton.addEventListener('click', start);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})();
