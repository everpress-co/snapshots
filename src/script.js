import './style.scss';

( function () {
	'use strict';

	var searchTimeout;
	var snapshotsElements = [];
	var foundElements = [];
	var currentFocus;
	var ajaxInFlight = false;

	function getSnapshotsRoot() {
		return document.getElementById( 'wp-admin-bar-snapshots' );
	}

	function nodeListToArray( nl ) {
		return Array.prototype.slice.call( nl || [] );
	}

	function init() {
		var defaultEl = document.getElementById(
			'wp-admin-bar-snapshots-default'
		);
		snapshotsElements = defaultEl
			? nodeListToArray( defaultEl.querySelectorAll( 'li' ) )
			: [];

		document.addEventListener( 'click', onDocumentClick );
		document.addEventListener( 'keyup', onSearchFieldEvent );
		document.addEventListener( 'paste', onSearchFieldEvent );
		document.addEventListener( 'change', onSearchFieldEvent );
		document.addEventListener( 'keydown', toggleMenu );

		var root = getSnapshotsRoot();
		if ( root ) {
			root.addEventListener( 'mouseenter', enabledKeyboardSearch );
			root.addEventListener( 'mouseleave', disableKeyboardSearch );
		}
	}

	function onDocumentClick( e ) {
		var t = e.target;
		if ( ! t.closest ) {
			return;
		}

		var createLink = t.closest( '#wp-admin-bar-snapshots > a' );
		if ( createLink ) {
			createSnapshot.call( createLink, e );
			return;
		}

		var restore = t.closest( '#wp-admin-bar-snapshots .restore-snapshot' );
		if ( restore ) {
			if ( ! restoreSnapshot.call( restore, e ) ) {
				e.preventDefault();
			}
			return;
		}

		var del = t.closest( '#wp-admin-bar-snapshots .delete-snapshot' );
		if ( del ) {
			if ( ! deleteSnapshot.call( del, e ) ) {
				e.preventDefault();
			}
		}
	}

	function onSearchFieldEvent( e ) {
		var input =
			e.target &&
			e.target.closest &&
			e.target.closest(
				'#wp-admin-bar-snapshots .search-snapshot input'
			);
		if ( ! input ) {
			return;
		}
		searchSnapshot.call( input, e );
	}

	function createSnapshot( event ) {
		var name = getLastName() || snapshots.blogname;
		var snapshotsname = window.prompt( snapshots.prompt, name );

		if ( ! snapshotsname ) {
			event.preventDefault();
			return;
		}

		if ( ! adminBarAjaxAvailable() || ajaxInFlight ) {
			if ( snapshots.ajaxUrl && ajaxInFlight ) {
				event.preventDefault();
				return;
			}
			this.href = this.href.replace(
				'snaphot_create=1',
				'snaphot_create=' + encodeURIComponent( snapshotsname )
			);
			this.href +=
				'&snapshot_location=' +
				encodeURIComponent(
					document.location.pathname +
						document.location.search +
						document.location.hash
				);
			var rootNav = getSnapshotsRoot();
			if ( rootNav ) {
				rootNav.classList.add( 'loading', 'create' );
			}
			return;
		}

		event.preventDefault();

		var root = getSnapshotsRoot();
		if ( root ) {
			root.classList.add( 'loading', 'create' );
		}

		ajaxInFlight = true;
		postAdminBarAjax( 'snapshots_create', {
			name: snapshotsname,
			location:
				document.location.pathname +
				document.location.search +
				document.location.hash,
		} )
			.then( function ( payload ) {
				ajaxInFlight = false;
				if ( root ) {
					root.classList.remove( 'loading', 'create' );
				}
				if (
					! payload.success ||
					! payload.data ||
					! payload.data.snapshot
				) {
					window.alert( getAjaxErrorMessage( payload ) );
					return;
				}

				removePrunedSnapshotMenuItems( payload.data.removed );
				addSnapshotMenuItem(
					payload.data.snapshot,
					payload.data.count
				);
			} )
			.catch( function () {
				ajaxInFlight = false;
				if ( root ) {
					root.classList.remove( 'loading', 'create' );
				}
				window.alert( snapshots.requestFailed );
			} );
	}

	function restoreSnapshot( event ) {
		var synthetic = event.isTrusted === false;
		var date = this.getAttribute( 'data-date' ) || '';
		if (
			! synthetic &&
			! window.confirm( sprintf( snapshots.restore, date ) )
		) {
			return false;
		}

		if ( ! adminBarAjaxAvailable() || ajaxInFlight ) {
			if ( snapshots.ajaxUrl && ajaxInFlight ) {
				return false;
			}
			var rootNav = getSnapshotsRoot();
			if ( rootNav ) {
				rootNav.classList.add( 'loading' );
			}
			return true;
		}

		event.preventDefault();

		var link = this.closest( 'a' );
		var snapshotId = getSnapshotIdFromUrl(
			link ? link.getAttribute( 'href' ) : '',
			'snapshot_restore'
		);
		if ( ! snapshotId ) {
			return false;
		}

		var root = getSnapshotsRoot();
		if ( root ) {
			root.classList.add( 'loading' );
		}

		ajaxInFlight = true;
		postAdminBarAjax( 'snapshots_restore', {
			snapshot: snapshotId,
			location:
				document.location.pathname +
				document.location.search +
				document.location.hash,
		} )
			.then( function ( payload ) {
				if (
					payload.success &&
					payload.data &&
					payload.data.redirect
				) {
					window.location.assign( payload.data.redirect );
					return;
				}
				ajaxInFlight = false;
				if ( root ) {
					root.classList.remove( 'loading' );
				}
				window.alert( getAjaxErrorMessage( payload ) );
			} )
			.catch( function () {
				ajaxInFlight = false;
				if ( root ) {
					root.classList.remove( 'loading' );
				}
				window.alert( snapshots.requestFailed );
			} );

		return false;
	}

	function deleteSnapshot( event ) {
		var name = this.getAttribute( 'data-name' ) || '';
		var date = this.getAttribute( 'data-date' ) || '';
		if (
			! window.confirm(
				sprintf( snapshots.delete, '"' + name + '"', date )
			)
		) {
			return false;
		}

		if ( ! adminBarAjaxAvailable() || ajaxInFlight ) {
			if ( snapshots.ajaxUrl && ajaxInFlight ) {
				return false;
			}
			return true;
		}

		event.preventDefault();

		var snapshotId = getSnapshotIdFromUrl(
			this.getAttribute( 'href' ),
			'snapshot_delete'
		);
		if ( ! snapshotId ) {
			return false;
		}

		var root = getSnapshotsRoot();
		var listItem = this.closest( 'li' );
		var wasCurrent = listItem && listItem.classList.contains( 'current' );

		if ( root ) {
			root.classList.add( 'loading' );
		}

		ajaxInFlight = true;
		postAdminBarAjax( 'snapshots_delete', { snapshot: snapshotId } )
			.then( function ( payload ) {
				ajaxInFlight = false;
				if ( root ) {
					root.classList.remove( 'loading' );
				}
				if ( ! payload.success ) {
					window.alert( getAjaxErrorMessage( payload ) );
					return;
				}

				if ( listItem && listItem.parentNode ) {
					listItem.parentNode.removeChild( listItem );
				}
				snapshotsElements = snapshotsElements.filter( function ( li ) {
					return li !== listItem;
				} );

				if ( wasCurrent ) {
					var extra = document.querySelector(
						'#wp-admin-bar-snapshots .snapshot-extra-title'
					);
					if ( extra ) {
						extra.textContent = '';
					}
				}

				decrementSnapshotCount();
			} )
			.catch( function () {
				ajaxInFlight = false;
				if ( root ) {
					root.classList.remove( 'loading' );
				}
				window.alert( snapshots.requestFailed );
			} );

		return false;
	}

	function getSnapshotIdFromUrl( href, param ) {
		if ( ! href ) {
			return '';
		}
		try {
			var url = new URL( href, window.location.href );
			return url.searchParams.get( param ) || '';
		} catch {
			return '';
		}
	}

	function adminBarAjaxAvailable() {
		if ( ! snapshots.ajaxUrl ) {
			return false;
		}
		try {
			return (
				new URL( snapshots.ajaxUrl, window.location.href ).origin ===
				window.location.origin
			);
		} catch {
			return false;
		}
	}

	function removePrunedSnapshotMenuItems( ids ) {
		if ( ! ids || ! ids.length ) {
			return;
		}
		var i;
		for ( i = 0; i < ids.length; i++ ) {
			var stale = document.getElementById(
				'wp-admin-bar-snapshot-' + ids[ i ]
			);
			if ( stale && stale.parentNode ) {
				stale.parentNode.removeChild( stale );
			}
			snapshotsElements = snapshotsElements.filter( function ( li ) {
				return li !== stale;
			} );
		}
	}

	function postAdminBarAjax( action, fields ) {
		var body = new URLSearchParams( {
			action,
			nonce: snapshots.nonce,
		} );
		var keys = Object.keys( fields || {} );
		for ( var i = 0; i < keys.length; i++ ) {
			body.set( keys[ i ], fields[ keys[ i ] ] );
		}
		return fetch( snapshots.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type':
					'application/x-www-form-urlencoded; charset=UTF-8',
			},
			body: body.toString(),
		} ).then( function ( response ) {
			return response.json();
		} );
	}

	function addSnapshotMenuItem( item, count ) {
		ensureSearchSubmenu();
		updateSnapshotCount( count );

		var extra = document.querySelector(
			'#wp-admin-bar-snapshots .snapshot-extra-title'
		);
		if ( extra ) {
			extra.textContent = item.name;
		}

		clearCurrentSnapshotHighlight();

		var li = document.createElement( 'li' );
		li.id = 'wp-admin-bar-snapshot-' + item.id;
		li.className = 'current';

		var restoreLink = document.createElement( 'a' );
		restoreLink.className = 'ab-item';
		restoreLink.href = item.restoreUrl;
		restoreLink.setAttribute( 'rel', item.searchRel );

		var restoreSpan = document.createElement( 'span' );
		restoreSpan.className = 'restore-snapshot';
		restoreSpan.setAttribute( 'data-status', item.currentLabel );
		restoreSpan.setAttribute( 'title', item.title );
		restoreSpan.setAttribute( 'data-date', item.date );
		restoreSpan.textContent = item.name;
		restoreLink.appendChild( restoreSpan );

		var meta = document.createElement( 'div' );
		var deleteLink = document.createElement( 'a' );
		deleteLink.className = 'delete-snapshot';
		deleteLink.title = 'delete ' + item.name;
		deleteLink.setAttribute( 'data-name', item.name );
		deleteLink.setAttribute( 'data-date', item.date );
		deleteLink.href = item.deleteUrl;
		deleteLink.innerHTML = '&times;';
		meta.appendChild( deleteLink );

		li.appendChild( restoreLink );
		li.appendChild( meta );

		var submenu = document.getElementById(
			'wp-admin-bar-snapshots-default'
		);
		var searchItem = document.getElementById(
			'wp-admin-bar-snapshot-search'
		);
		if ( submenu ) {
			if ( searchItem && searchItem.nextSibling ) {
				submenu.insertBefore( li, searchItem.nextSibling );
			} else if ( searchItem ) {
				submenu.appendChild( li );
			} else {
				submenu.appendChild( li );
			}
			snapshotsElements.unshift( li );
		}
	}

	function clearCurrentSnapshotHighlight() {
		var currentItems = document.querySelectorAll(
			'#wp-admin-bar-snapshots .ab-submenu > li.current, #wp-admin-bar-snapshots .ab-submenu a.ab-item.current'
		);
		for ( var c = 0; c < currentItems.length; c++ ) {
			currentItems[ c ].classList.remove( 'current' );
		}
	}

	function ensureSnapshotsSubmenu() {
		var submenu = document.getElementById(
			'wp-admin-bar-snapshots-default'
		);
		if ( submenu ) {
			return submenu;
		}

		var root = getSnapshotsRoot();
		if ( ! root ) {
			return null;
		}

		root.classList.add( 'menupop' );
		var rootLink = document.querySelector( '#wp-admin-bar-snapshots > a' );
		if ( rootLink && ! rootLink.hasAttribute( 'aria-expanded' ) ) {
			rootLink.setAttribute( 'aria-expanded', 'false' );
		}

		var wrapper = document.createElement( 'div' );
		wrapper.className = 'ab-sub-wrapper';

		submenu = document.createElement( 'ul' );
		submenu.id = 'wp-admin-bar-snapshots-default';
		submenu.className = 'ab-submenu';
		submenu.setAttribute( 'role', 'menu' );
		wrapper.appendChild( submenu );
		root.appendChild( wrapper );

		bindSnapshotsMenuHover( root );
		if ( root.matches && root.matches( ':hover' ) ) {
			root.classList.add( 'hover' );
			if ( rootLink ) {
				rootLink.setAttribute( 'aria-expanded', 'true' );
			}
		}

		return submenu;
	}

	function bindSnapshotsMenuHover( root ) {
		function setHover( on ) {
			if ( on ) {
				root.classList.add( 'hover' );
			} else {
				root.classList.remove( 'hover' );
			}
			var link = document.querySelector( '#wp-admin-bar-snapshots > a' );
			if ( link && link.hasAttribute( 'aria-expanded' ) ) {
				link.setAttribute( 'aria-expanded', on ? 'true' : 'false' );
			}
		}

		if ( typeof window.hoverintent === 'function' ) {
			window
				.hoverintent(
					root,
					function () {
						setHover( true );
					},
					function () {
						setHover( false );
					}
				)
				.options( { timeout: 180 } );
		} else {
			root.addEventListener( 'mouseenter', function () {
				setHover( true );
			} );
			root.addEventListener( 'mouseleave', function () {
				setHover( false );
			} );
		}

		root.addEventListener( 'click', function () {
			setHover( true );
		} );
	}

	function ensureSearchSubmenu() {
		if ( document.getElementById( 'wp-admin-bar-snapshot-search' ) ) {
			return;
		}

		var submenu = ensureSnapshotsSubmenu();
		if ( ! submenu ) {
			return;
		}

		var li = document.createElement( 'li' );
		li.id = 'wp-admin-bar-snapshot-search';

		var link = document.createElement( 'a' );
		link.className = 'ab-item';
		link.href = '#';

		var searchWrap = document.createElement( 'span' );
		searchWrap.className = 'search-snapshot';

		var label = document.createElement( 'label' );
		label.setAttribute( 'for', 'snapshost_search' );
		label.textContent = snapshots.searchLabel;

		var input = document.createElement( 'input' );
		input.id = 'snapshost_search';
		input.type = 'search';
		input.placeholder = snapshots.searchLabel;

		searchWrap.appendChild( label );
		searchWrap.appendChild( input );
		link.appendChild( searchWrap );

		var settings = document.createElement( 'div' );
		var settingsLink = document.createElement( 'a' );
		settingsLink.title = snapshots.settingsTitle;
		settingsLink.href = snapshots.settingsUrl;
		settingsLink.innerHTML =
			'<span class="dashicons dashicons-admin-settings"></span>';
		settings.appendChild( settingsLink );

		li.appendChild( link );
		li.appendChild( settings );
		submenu.appendChild( li );
	}

	function updateSnapshotCount( count ) {
		var countEl = document.querySelector(
			'#wp-admin-bar-snapshots .snapshots-count'
		);
		if ( countEl ) {
			countEl.textContent = String( count );
			return;
		}

		var rootLink = document.querySelector( '#wp-admin-bar-snapshots > a' );
		if ( ! rootLink ) {
			return;
		}

		var icon = rootLink.querySelector( '.ab-icon' );
		var extra = rootLink.querySelector( '.snapshot-extra-title' );
		if ( ! icon || ! extra ) {
			return;
		}

		var node = icon.nextSibling;
		while ( node && node !== extra ) {
			var next = node.nextSibling;
			rootLink.removeChild( node );
			node = next;
		}

		var span = document.createElement( 'span' );
		span.className = 'snapshots-count';
		span.textContent = String( count );
		rootLink.insertBefore( span, extra );
	}

	function getAjaxErrorMessage( payload ) {
		if (
			payload &&
			payload.data &&
			typeof payload.data.message === 'string' &&
			payload.data.message
		) {
			return payload.data.message;
		}
		return snapshots.requestFailed;
	}

	function decrementSnapshotCount() {
		var countEl = document.querySelector(
			'#wp-admin-bar-snapshots .snapshots-count'
		);
		if ( ! countEl ) {
			return;
		}

		var count = parseInt( countEl.textContent, 10 ) || 0;
		count = Math.max( 0, count - 1 );

		if ( count === 0 ) {
			countEl.outerHTML = snapshots.emptyCountLabel || '';
			removeSnapshotSubmenuItems();
			return;
		}

		countEl.textContent = String( count );
	}

	function removeSnapshotSubmenuItems() {
		var items = document.querySelectorAll(
			'#wp-admin-bar-snapshots .ab-submenu > li'
		);
		for ( var i = 0; i < items.length; i++ ) {
			if ( items[ i ].parentNode ) {
				items[ i ].parentNode.removeChild( items[ i ] );
			}
		}
		snapshotsElements = [];
		foundElements = [];
	}

	function toggleMenu( event ) {
		if ( event.keyCode === 83 && event.ctrlKey && ! event.shiftKey ) {
			var root = getSnapshotsRoot();
			if ( ! root ) {
				return;
			}
			if ( root.classList.contains( 'hover' ) ) {
				root.classList.remove( 'hover' );
				if (
					currentFocus &&
					typeof currentFocus.focus === 'function'
				) {
					currentFocus.focus();
				}
			} else {
				currentFocus = document.activeElement;
				root.classList.add( 'hover' );
				var searchInput = document.querySelector(
					'#wp-admin-bar-snapshots .search-snapshot input'
				);
				if ( searchInput ) {
					searchInput.focus();
				}
			}
		}
	}

	function searchSnapshot( event ) {
		var string = this.value;
		clearTimeout( searchTimeout );
		searchTimeout = window.setTimeout( function () {
			var i;
			for ( i = 0; i < foundElements.length; i++ ) {
				foundElements[ i ].classList.remove( 'snapshot-found' );
			}
			var root = getSnapshotsRoot();
			if ( string ) {
				if ( root ) {
					root.classList.add( 'is-snapshot-search' );
				}
			} else if ( root ) {
				root.classList.remove( 'is-snapshot-search' );
			}
			foundElements = [];
			for ( i = 0; i < snapshotsElements.length; i++ ) {
				var li = snapshotsElements[ i ];
				var a = li.querySelector( "a[rel*='" + string + "']" );
				if ( a && a.parentElement ) {
					a.parentElement.classList.add( 'snapshot-found' );
					foundElements.push( a.parentElement );
				}
			}
			if ( string && event.key === 'Enter' ) {
				var first = foundElements[ 0 ];
				var restoreSpan =
					first && first.querySelector( 'span.restore-snapshot' );
				if ( restoreSpan ) {
					restoreSpan.click();
				}
			}
		}, 50 );
	}

	function getLastName() {
		var el = document.querySelector(
			'#wp-admin-bar-snapshots .snapshot-extra-title'
		);
		return el ? el.textContent : '';
	}

	function enabledKeyboardSearch() {
		document.addEventListener( 'keypress', keyPressEvent );
	}

	function disableKeyboardSearch() {
		document.removeEventListener( 'keypress', keyPressEvent );
	}

	function keyPressEvent() {
		var searchInput = document.querySelector(
			'#wp-admin-bar-snapshots .search-snapshot input'
		);
		if ( searchInput ) {
			searchInput.focus();
		}
		disableKeyboardSearch();
	}

	function sprintf() {
		var a = Array.prototype.slice.call( arguments ),
			str = a.shift(),
			total = a.length,
			reg;
		for ( var i = 0; i < total; i++ ) {
			reg = new RegExp( '%(' + ( i + 1 ) + '\\$)?(s|d|f)' );
			str = str.replace( reg, a[ i ] );
		}
		return str;
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
