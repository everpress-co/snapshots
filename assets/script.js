(function ($) {
	'use strict';

	var last = getLastName(),
		searchTimeout,
		snapshotsElements = $('#wp-admin-bar-snapshots-default li'),
		foundElements = $(),
		currentFocus;

	$(document)
		.on('click', '#wp-admin-bar-snapshots > a', createSnapshot)
		.on(
			'click',
			'#wp-admin-bar-snapshots .restore-snapshot',
			restoreSnapshot
		)
		.on('click', '#wp-admin-bar-snapshots .delete-snapshot', deleteSnapshot)
		.on(
			'keyup paste change',
			'#wp-admin-bar-snapshots .search-snapshot input',
			searchSnapshot
		)
		.on('mouseenter', '#wp-admin-bar-snapshots', enabledKeyboardSearch)
		.on('mouseleave', '#wp-admin-bar-snapshots', disableKeyboardSearch)
		.on('keydown', toggleMenu);

	if (last) {
		$('#wp-admin-bar-snapshots > a').append(
			'<span class="snapshot-extra-title" title="' +
				sprintf(snapshots.currently, last) +
				'">' +
				last +
				'</span>'
		);
	}

	function createSnapshot() {
		var name = getLastName() || snapshots.blogname;
		var snapshotsname = prompt(snapshots.prompt, name);

		if (snapshotsname) {
			this.href = this.href.replace(
				'snaphot_create=1',
				'snaphot_create=' + encodeURIComponent(snapshotsname)
			);
			$('#wp-admin-bar-snapshots').addClass('loading create');
			localStorage.setItem('snapshot_current', snapshotsname);
		}

		return !!snapshotsname;
	}

	function restoreSnapshot(event) {
		if (
			event.isTrigger ||
			confirm(sprintf(snapshots.restore, $(this).data('date')))
		) {
			localStorage.setItem('snapshot_current', this.innerText);
			$('#wp-admin-bar-snapshots').addClass('loading');
			return true;
		}

		return false;
	}

	function deleteSnapshot() {
		return confirm(
			sprintf(
				snapshots.delete,
				'"' + $(this).data('name') + '"',
				$(this).data('date')
			)
		);
	}

	function toggleMenu(event) {
		if (event.keyCode === 83 && event.ctrlKey && !event.shiftKey) {
			if ($('#wp-admin-bar-snapshots').is('.hover')) {
				$('#wp-admin-bar-snapshots').removeClass('hover');
				currentFocus && $(currentFocus).focus();
			} else {
				currentFocus = document.activeElement;
				$('#wp-admin-bar-snapshots').addClass('hover');
				$('#wp-admin-bar-snapshots .search-snapshot input').focus();
			}
		}
	}

	function searchSnapshot(event) {
		var string = this.value;
		clearTimeout(searchTimeout);
		searchTimeout = setTimeout(function () {
			foundElements.removeClass('snapshot-found');
			if (string) {
				$('#wp-admin-bar-snapshots').addClass('is-snapshot-search');
			} else {
				$('#wp-admin-bar-snapshots').removeClass('is-snapshot-search');
			}
			foundElements = snapshotsElements
				.find("a[rel*='" + string + "']")
				.parent()
				.addClass('snapshot-found');
			if (string && event.which === 13) {
				foundElements
					.first()
					.find('span.restore-snapshot')
					.trigger('click');
			}
		}, 50);
	}

	function getLastName() {
		return localStorage.getItem('snapshot_current') || '';
	}

	function enabledKeyboardSearch() {
		$(document).on('keypress', keyPressEvent);
	}
	function disableKeyboardSearch() {
		$(document).off('keypress', keyPressEvent);
	}

	function keyPressEvent(event) {
		$('#wp-admin-bar-snapshots .search-snapshot input').focus();
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
})(jQuery);
