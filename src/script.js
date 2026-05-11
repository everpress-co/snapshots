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
		snapshotsElements = defaultEl ? nodeListToArray(defaultEl.querySelectorAll('li')) : [];

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
			if (!createSnapshot.call(createLink, e)) {
				e.preventDefault();
			}
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

	function createSnapshot() {
		var name = getLastName() || snapshots.blogname;
		var snapshotsname = window.prompt(snapshots.prompt, name);

		if (snapshotsname) {
			this.href = this.href.replace(
				'snaphot_create=1',
				'snaphot_create=' + encodeURIComponent(snapshotsname)
			);
			this.href +=
				'&snapshot_location=' +
				encodeURIComponent(
					document.location.pathname +
						document.location.search +
						document.location.hash
				);
			var root = getSnapshotsRoot();
			if (root) {
				root.classList.add('loading', 'create');
			}
		}

		return !!snapshotsname;
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
		return window.confirm(
			sprintf(snapshots.delete, '"' + name + '"', date)
		);
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
					'#wp-admin-bar-snapshots .search-snapshot input'
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
				var restoreSpan =
					first &&
					first.querySelector('span.restore-snapshot');
				if (restoreSpan) {
					restoreSpan.click();
				}
			}
		}, 50);
	}

	function getLastName() {
		var el = document.querySelector(
			'#wp-admin-bar-snapshots .snapshot-extra-title'
		);
		return el ? el.textContent : '';
	}

	function enabledKeyboardSearch() {
		document.addEventListener('keypress', keyPressEvent);
	}

	function disableKeyboardSearch() {
		document.removeEventListener('keypress', keyPressEvent);
	}

	function keyPressEvent() {
		var searchInput = document.querySelector(
			'#wp-admin-bar-snapshots .search-snapshot input'
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
