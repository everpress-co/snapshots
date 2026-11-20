import './style.scss';

(function () {
	'use strict';

	var searchTimeout;
	var snapshotsElements = [];
	var foundElements = [];
	var currentFocus;

	function getSnapshotsRoot() {
		return document.getElementById('wp-admin-bar-snapshots');
	}

	function nodeListToArray(nl) {
		return Array.prototype.slice.call(nl || []);
	}

	function init() {
		var defaultEl = document.getElementById('wp-admin-bar-snapshots-default');

		snapshotsElements = defaultEl
			? nodeListToArray(defaultEl.querySelectorAll('li'))
			: [];

		document.addEventListener('click', onDocumentClick);
		document.addEventListener('keyup', onSearchFieldEvent);
		document.addEventListener('paste', onSearchFieldEvent);
		document.addEventListener('change', onSearchFieldEvent);
		document.addEventListener('keydown', toggleMenu);

		var root = getSnapshotsRoot();
		if (root) {
			root.addEventListener('mouseenter', enabledKeyboardSearch);
			root.addEventListener('mouseleave', disableKeyboardSearch);
		}
	}

	function onDocumentClick(e) {
		var t = e.target;
		if (!t.closest) {
			return;
		}

		var createLink = t.closest('#wp-admin-bar-snapshots > a');
		if (createLink) {
			e.preventDefault();
			var submitButton = t.closest('.snapshot-name-submit');
			if (submitButton) {
				submitNameEdit(createLink.querySelector('.snapshot-name-input'));
				return;
			}
			var nameInput = t.closest('.snapshot-name-input');
			if (nameInput) {
				focusNameInput(nameInput);
				return;
			}
			beginNameEdit(createLink);
			return;
		}

		var restore = t.closest('#wp-admin-bar-snapshots .restore-snapshot');
		if (restore) {
			if (!restoreSnapshot.call(restore, e)) {
				e.preventDefault();
			}
			return;
		}

		var del = t.closest('#wp-admin-bar-snapshots .delete-snapshot');
		if (del) {
			if (!deleteSnapshot.call(del, e)) {
				e.preventDefault();
			}
		}
	}

	function onSearchFieldEvent(e) {
		var input =
			e.target &&
			e.target.closest &&
			e.target.closest('#wp-admin-bar-snapshots .search-snapshot input');
		if (!input) {
			return;
		}
		searchSnapshot.call(input, e);
	}

	function beginNameEdit(link) {
		var existing = link.querySelector('.snapshot-name-input');
		if (existing) {
			focusNameInput(existing);
			return;
		}

		var label = link.querySelector('.snapshot-extra-title');
		var original = label ? label.textContent.replace(/^\s+|\s+$/g, '') : '';
		var fallback = original || snapshots.blogname || '';
		var field = document.createElement('span');
		field.className = 'snapshot-name-field';

		var input = document.createElement('input');
		input.type = 'text';
		input.className = 'snapshot-name-input';
		input.value = fallback;
		input.placeholder = fallback;
		input.setAttribute('aria-label', snapshots.nameLabel || snapshots.prompt || '');
		input.setAttribute('data-original', original);
		input.setAttribute('data-fallback', fallback);

		var button = document.createElement('button');
		button.type = 'button';
		button.className = 'snapshot-name-submit';
		button.textContent = snapshots.createLabel || 'OK';
		button.setAttribute('aria-label', snapshots.createAction || button.textContent);

		field.appendChild(input);
		field.appendChild(button);

		if (label && label.parentNode) {
			label.parentNode.replaceChild(field, label);
		} else {
			link.appendChild(field);
		}

		input.addEventListener('keydown', onNameInputKeydown);
		input.addEventListener('click', function (event) {
			event.preventDefault();
			event.stopPropagation();
		});
		button.addEventListener('mousedown', function (event) {
			event.preventDefault();
		});
		button.addEventListener('click', function (event) {
			event.preventDefault();
			event.stopPropagation();
			submitNameEdit(input);
		});
		input.addEventListener('blur', function () {
			window.setTimeout(function () {
				closeNameEditOnBlur(input);
			}, 0);
		});
		button.addEventListener('blur', function () {
			window.setTimeout(function () {
				closeNameEditOnBlur(input);
			}, 0);
		});
		focusNameInput(input);
	}

	function focusNameInput(input) {
		input.focus();
		input.select();
		window.setTimeout(function () {
			if (document.activeElement !== input) {
				input.focus();
				input.select();
			}
		}, 0);
	}

	function onNameInputKeydown(event) {
		if (event.key === 'Enter') {
			event.preventDefault();
			event.stopPropagation();
			submitNameEdit(event.currentTarget);
			return;
		}
		if (event.key === 'Escape') {
			event.preventDefault();
			event.stopPropagation();
			cancelNameEdit(event.currentTarget);
		}
	}

	function submitNameEdit(input) {
		var link = input.closest('#wp-admin-bar-snapshots > a');
		if (!link || input.disabled) {
			return;
		}
		input.disabled = true;
		var name = input.value.replace(/^\s+|\s+$/g, '');
		if (!name) {
			name = (input.getAttribute('data-fallback') || '').replace(/^\s+|\s+$/g, '');
		}
		if (name) {
			link.href = link.href.replace(
				/([?&])snaphot_create=?[^&#]*/,
				'$1snaphot_create=' + encodeURIComponent(name),
			);
		}
		link.href +=
			'&snapshot_location=' +
			encodeURIComponent(
				document.location.pathname +
					document.location.search +
					document.location.hash,
			);
		var root = getSnapshotsRoot();
		if (root) {
			root.classList.add('loading', 'create');
		}
		window.location.href = link.href;
	}

	function closeNameEditOnBlur(input) {
		if (!input.parentNode || input.disabled) {
			return;
		}
		var active = document.activeElement;
		if (active && active.closest && active.closest('.snapshot-name-field')) {
			return;
		}
		cancelNameEdit(input);
	}

	function cancelNameEdit(input) {
		var field = input.closest('.snapshot-name-field') || input;
		var original = input.getAttribute('data-original') || '';
		var span = document.createElement('span');
		span.className = 'snapshot-extra-title';
		span.textContent = original;
		if (field.parentNode) {
			field.parentNode.replaceChild(span, field);
		}
	}

	function restoreSnapshot(event) {
		var synthetic = event.isTrusted === false;
		var date = this.getAttribute('data-date') || '';
		if (synthetic || window.confirm(sprintf(snapshots.restore, date))) {
			var root = getSnapshotsRoot();
			if (root) {
				root.classList.add('loading');
			}
			return true;
		}

		return false;
	}

	function deleteSnapshot() {
		var name = this.getAttribute('data-name') || '';
		var date = this.getAttribute('data-date') || '';
		return window.confirm(sprintf(snapshots.delete, '"' + name + '"', date));
	}

	function toggleMenu(event) {
		if (event.keyCode === 83 && event.ctrlKey && !event.shiftKey) {
			var root = getSnapshotsRoot();
			if (!root) {
				return;
			}
			if (root.classList.contains('hover')) {
				root.classList.remove('hover');
				if (currentFocus && typeof currentFocus.focus === 'function') {
					currentFocus.focus();
				}
			} else {
				currentFocus = document.activeElement;
				root.classList.add('hover');
				var searchInput = document.querySelector(
					'#wp-admin-bar-snapshots .search-snapshot input',
				);
				if (searchInput) {
					searchInput.focus();
				}
			}
		}
	}

	function searchSnapshot(event) {
		var string = this.value;
		clearTimeout(searchTimeout);
		searchTimeout = window.setTimeout(function () {
			var i;
			for (i = 0; i < foundElements.length; i++) {
				foundElements[i].classList.remove('snapshot-found');
			}
			var root = getSnapshotsRoot();
			if (string) {
				if (root) {
					root.classList.add('is-snapshot-search');
				}
			} else if (root) {
				root.classList.remove('is-snapshot-search');
			}
			foundElements = [];
			for (i = 0; i < snapshotsElements.length; i++) {
				var li = snapshotsElements[i];
				var a = li.querySelector("a[rel*='" + string + "']");
				if (a && a.parentElement) {
					a.parentElement.classList.add('snapshot-found');
					foundElements.push(a.parentElement);
				}
			}
			if (string && event.key === 'Enter') {
				var first = foundElements[0];
				var restoreSpan = first && first.querySelector('span.restore-snapshot');
				if (restoreSpan) {
					restoreSpan.click();
				}
			}
		}, 50);
	}

	function enabledKeyboardSearch() {
		document.addEventListener('keypress', keyPressEvent);
	}

	function disableKeyboardSearch() {
		document.removeEventListener('keypress', keyPressEvent);
	}

	function keyPressEvent(event) {
		if (
			event.target &&
			event.target.closest &&
			event.target.closest('input, textarea, select')
		) {
			return;
		}
		var searchInput = document.querySelector(
			'#wp-admin-bar-snapshots .search-snapshot input',
		);
		if (searchInput) {
			searchInput.focus();
		}
		disableKeyboardSearch();
	}

	function sprintf() {
		var a = Array.prototype.slice.call(arguments),
			str = a.shift(),
			total = a.length,
			reg;
		for (var i = 0; i < total; i++) {
			reg = new RegExp('%(' + (i + 1) + '\\$)?(s|d|f)');
			str = str.replace(reg, a[i]);
		}
		return str;
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
