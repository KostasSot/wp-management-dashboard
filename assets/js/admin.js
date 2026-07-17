/**
 * WP Dashboard Management — admin dashboard behaviour.
 *
 * Vanilla JS: AJAX buttons, log filtering, and canvas charts
 * (no external chart library needed).
 */
( function () {
	'use strict';

	if ( typeof wpdmData === 'undefined' ) {
		return;
	}

	var COLORS = [ '#2271b1', '#00a32a', '#dba617', '#d63638', '#9b6dd6', '#72aee6', '#e26f56', '#3db8bd' ];

	var SEVERITY_COLORS = {
		info: '#72aee6',
		notice: '#00a32a',
		warning: '#dba617',
		critical: '#d63638'
	};

	/* ===== Notices ===== */

	function notice( message, type ) {
		var area = document.getElementById( 'wpdm-notices' );
		if ( ! area ) {
			return;
		}
		var div = document.createElement( 'div' );
		div.className = 'notice notice-' + ( type || 'success' ) + ' is-dismissible';
		var p = document.createElement( 'p' );
		p.textContent = message;
		div.appendChild( p );
		area.innerHTML = '';
		area.appendChild( div );
	}

	/* ===== AJAX helper ===== */

	function post( action, onDone, extra ) {
		var body = new URLSearchParams();
		body.append( 'action', action );
		body.append( 'nonce', wpdmData.nonce );
		if ( extra ) {
			Object.keys( extra ).forEach( function ( key ) {
				body.append( key, extra[ key ] );
			} );
		}

		fetch( wpdmData.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		} )
			.then( function ( res ) {
				return res.json();
			} )
			.then( function ( json ) {
				onDone( json );
			} )
			.catch( function () {
				onDone( { success: false, data: { message: wpdmData.i18n.error } } );
			} );
	}

	function bindAjaxButton( id, action, reload ) {
		var btn = document.getElementById( id );
		if ( ! btn ) {
			return;
		}
		btn.addEventListener( 'click', function () {
			if ( 'wpdm-clear-log' === id && ! window.confirm( wpdmData.i18n.confirmClear ) ) {
				return;
			}
			btn.disabled = true;
			var original = btn.innerHTML;
			btn.textContent = wpdmData.i18n.working;
			var extra = btn.dataset.source ? { source: btn.dataset.source } : null;
			post( action, function ( json ) {
				var msg = json.data && json.data.message ? json.data.message : wpdmData.i18n.error;
				notice( msg, json.success ? 'success' : 'error' );
				if ( json.success && reload ) {
					window.location.reload();
					return;
				}
				btn.disabled = false;
				btn.innerHTML = original;
			}, extra );
		} );
	}

	bindAjaxButton( 'wpdm-refresh-updates', 'wpdm_refresh_updates', true );
	bindAjaxButton( 'wpdm-clear-log', 'wpdm_clear_log', true );
	bindAjaxButton( 'wpdm-activate-beqlic', 'wpdm_activate_beqlic', true );
	bindAjaxButton( 'wpdm-install-beqlic', 'wpdm_install_beqlic', true );
	bindAjaxButton( 'wpdm-create-backup', 'wpdm_create_backup', true );

	/* ===== Email reports ===== */

	var saveReportBtn = document.getElementById( 'wpdm-save-report' );
	if ( saveReportBtn ) {
		saveReportBtn.addEventListener( 'click', function () {
			saveReportBtn.disabled = true;
			post(
				'wpdm_save_report_settings',
				function ( json ) {
					var msg = json.data && json.data.message ? json.data.message : wpdmData.i18n.error;
					notice( msg, json.success ? 'success' : 'error' );
					saveReportBtn.disabled = false;
				},
				{
					email: document.getElementById( 'wpdm-report-email' ).value,
					frequency: document.getElementById( 'wpdm-report-frequency' ).value
				}
			);
		} );
	}

	var sendReportBtn = document.getElementById( 'wpdm-send-report' );
	if ( sendReportBtn ) {
		sendReportBtn.addEventListener( 'click', function () {
			sendReportBtn.disabled = true;
			var original = sendReportBtn.innerHTML;
			sendReportBtn.textContent = wpdmData.i18n.working;
			post(
				'wpdm_send_report_now',
				function ( json ) {
					var msg = json.data && json.data.message ? json.data.message : wpdmData.i18n.error;
					notice( msg, json.success ? 'success' : 'error' );
					sendReportBtn.disabled = false;
					sendReportBtn.innerHTML = original;
				}
			);
		} );
	}

	/* ===== Debug mode toggle ===== */

	var debugBtn = document.getElementById( 'wpdm-toggle-debug' );
	if ( debugBtn ) {
		debugBtn.addEventListener( 'click', function () {
			debugBtn.disabled = true;
			debugBtn.textContent = wpdmData.i18n.working;
			post(
				'wpdm_toggle_debug',
				function ( json ) {
					var msg = json.data && json.data.message ? json.data.message : wpdmData.i18n.error;
					notice( msg, json.success ? 'success' : 'error' );
					if ( json.success ) {
						window.location.reload();
						return;
					}
					debugBtn.disabled = false;
					debugBtn.textContent = wpdmData.i18n.error;
				},
				{ value: debugBtn.dataset.value }
			);
		} );
	}

	/* ===== Snapshot restore / delete ===== */

	document.querySelectorAll( '.wpdm-restore-backup-btn' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var msg = wpdmData.i18n.confirmRestore.replace( '%s', btn.dataset.file );
			if ( btn.dataset.wp && btn.dataset.wp !== wpdmData.wpVersion ) {
				msg += '\n\n' + wpdmData.i18n.restoreVersionMismatch.replace( '%s', btn.dataset.wp );
			}
			if ( ! window.confirm( msg ) ) {
				return;
			}
			btn.disabled = true;
			btn.textContent = wpdmData.i18n.working;
			post(
				'wpdm_restore_backup',
				function ( json ) {
					var m = json.data && json.data.message ? json.data.message : wpdmData.i18n.error;
					notice( m, json.success ? 'success' : 'error' );
					if ( json.success ) {
						window.location.reload();
						return;
					}
					btn.disabled = false;
					btn.textContent = wpdmData.i18n.error;
				},
				{ file: btn.dataset.file }
			);
		} );
	} );

	document.querySelectorAll( '.wpdm-delete-backup-btn' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			if ( ! window.confirm( wpdmData.i18n.confirmDeleteBackup.replace( '%s', btn.dataset.file ) ) ) {
				return;
			}
			btn.disabled = true;
			post(
				'wpdm_delete_backup',
				function ( json ) {
					var m = json.data && json.data.message ? json.data.message : wpdmData.i18n.error;
					notice( m, json.success ? 'success' : 'error' );
					if ( json.success ) {
						window.location.reload();
						return;
					}
					btn.disabled = false;
				},
				{ file: btn.dataset.file }
			);
		} );
	} );

	/* ===== Core version switch ===== */

	var coreBtn = document.getElementById( 'wpdm-install-core' );
	if ( coreBtn ) {
		coreBtn.addEventListener( 'click', function () {
			var select = document.getElementById( 'wpdm-core-version' );
			var version = select ? select.value : '';
			if ( ! version ) {
				return;
			}
			if ( ! window.confirm( wpdmData.i18n.confirmCore.replace( '%s', version ) ) ) {
				return;
			}
			coreBtn.disabled = true;
			select.disabled = true;
			coreBtn.textContent = wpdmData.i18n.installingCore;
			notice( wpdmData.i18n.installingCoreLong, 'info' );
			post(
				'wpdm_install_core',
				function ( json ) {
					var msg = json.data && json.data.message ? json.data.message : wpdmData.i18n.error;
					notice( msg, json.success ? 'success' : 'error' );
					if ( json.success ) {
						window.location.reload();
						return;
					}
					coreBtn.disabled = false;
					select.disabled = false;
					coreBtn.textContent = wpdmData.i18n.error;
				},
				{ version: version }
			);
		} );
	}

	/* ===== Plugin activate / deactivate ===== */

	document.querySelectorAll( '.wpdm-toggle-plugin-btn' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var enabling = '1' === btn.dataset.enable;
			if ( ! enabling && ! window.confirm( wpdmData.i18n.confirmDeactivate.replace( '%s', btn.dataset.name ) ) ) {
				return;
			}
			btn.disabled = true;
			btn.textContent = wpdmData.i18n.working;
			post(
				'wpdm_toggle_plugin',
				function ( json ) {
					var msg = json.data && json.data.message ? json.data.message : wpdmData.i18n.error;
					notice( msg, json.success ? 'success' : 'error' );
					if ( json.success ) {
						window.location.reload();
						return;
					}
					btn.disabled = false;
					btn.textContent = wpdmData.i18n.error;
				},
				{ plugin: btn.dataset.plugin, enable: btn.dataset.enable }
			);
		} );
	} );

	/* ===== Theme activation ===== */

	document.querySelectorAll( '.wpdm-activate-theme-btn' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			if ( ! window.confirm( wpdmData.i18n.confirmSwitch.replace( '%s', btn.dataset.name ) ) ) {
				return;
			}
			btn.disabled = true;
			btn.textContent = wpdmData.i18n.working;
			post(
				'wpdm_activate_theme',
				function ( json ) {
					var msg = json.data && json.data.message ? json.data.message : wpdmData.i18n.error;
					notice( msg, json.success ? 'success' : 'error' );
					if ( json.success ) {
						window.location.reload();
						return;
					}
					btn.disabled = false;
					btn.textContent = wpdmData.i18n.error;
				},
				{ stylesheet: btn.dataset.stylesheet }
			);
		} );
	} );

	/* ===== Plugin / theme updates (core update endpoints) ===== */

	document.querySelectorAll( '.wpdm-update-btn' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			btn.disabled = true;
			btn.textContent = wpdmData.i18n.updating;

			var body = new URLSearchParams();
			body.append( 'action', 'plugin' === btn.dataset.type ? 'update-plugin' : 'update-theme' );
			body.append( '_ajax_nonce', wpdmData.updatesNonce );
			body.append( 'slug', btn.dataset.slug );
			if ( btn.dataset.plugin ) {
				body.append( 'plugin', btn.dataset.plugin );
			}

			fetch( wpdmData.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString()
			} )
				.then( function ( res ) {
					return res.json();
				} )
				.then( function ( json ) {
					if ( json.success ) {
						var version = json.data && json.data.newVersion ? json.data.newVersion : '';
						notice( wpdmData.i18n.updated.replace( '%s', version ), 'success' );
						window.location.reload();
						return;
					}
					var msg =
						( json.data && ( json.data.errorMessage || json.data.error ) ) || wpdmData.i18n.error;
					notice( msg, 'error' );
					btn.disabled = false;
					btn.textContent = wpdmData.i18n.error;
				} )
				.catch( function () {
					notice( wpdmData.i18n.error, 'error' );
					btn.disabled = false;
				} );
		} );
	} );

	/* ===== PHP log filtering ===== */

	var filters = document.querySelectorAll( '.wpdm-log-filter' );
	filters.forEach( function ( filterBtn ) {
		filterBtn.addEventListener( 'click', function () {
			filters.forEach( function ( b ) {
				b.classList.remove( 'is-active' );
			} );
			filterBtn.classList.add( 'is-active' );
			var level = filterBtn.getAttribute( 'data-level' );
			document.querySelectorAll( '.wpdm-log-entry' ).forEach( function ( entry ) {
				entry.style.display =
					'all' === level || entry.getAttribute( 'data-level' ) === level ? '' : 'none';
			} );
		} );
	} );

	/* ===== Charts (canvas, HiDPI-aware) ===== */

	function setupCanvas( canvas ) {
		var dpr = window.devicePixelRatio || 1;
		var cssWidth = canvas.clientWidth || canvas.parentNode.clientWidth;
		var cssHeight = parseInt( canvas.getAttribute( 'height' ), 10 ) || 180;
		canvas.width = cssWidth * dpr;
		canvas.height = cssHeight * dpr;
		canvas.style.height = cssHeight + 'px';
		var ctx = canvas.getContext( '2d' );
		ctx.scale( dpr, dpr );
		return { ctx: ctx, w: cssWidth, h: cssHeight };
	}

	function drawBarChart( canvasId, labels, values ) {
		var canvas = document.getElementById( canvasId );
		if ( ! canvas || ! labels.length ) {
			return;
		}
		var c = setupCanvas( canvas );
		var ctx = c.ctx;
		var padL = 30;
		var padB = 24;
		var padT = 10;
		var chartW = c.w - padL - 8;
		var chartH = c.h - padT - padB;
		var max = Math.max.apply( null, values.concat( [ 1 ] ) );
		var barW = ( chartW / labels.length ) * 0.6;
		var gap = chartW / labels.length;

		// Gridlines + y labels.
		ctx.strokeStyle = '#f0f0f1';
		ctx.fillStyle = '#8c8f94';
		ctx.font = '10px sans-serif';
		ctx.textAlign = 'right';
		var steps = 4;
		for ( var s = 0; s <= steps; s++ ) {
			var yVal = Math.round( ( max / steps ) * s );
			var y = padT + chartH - ( chartH / steps ) * s;
			ctx.beginPath();
			ctx.moveTo( padL, y );
			ctx.lineTo( c.w - 8, y );
			ctx.stroke();
			ctx.fillText( String( yVal ), padL - 6, y + 3 );
		}

		// Bars.
		ctx.textAlign = 'center';
		labels.forEach( function ( label, i ) {
			var h = max > 0 ? ( values[ i ] / max ) * chartH : 0;
			var x = padL + gap * i + ( gap - barW ) / 2;
			var y = padT + chartH - h;

			var grad = ctx.createLinearGradient( 0, y, 0, padT + chartH );
			grad.addColorStop( 0, '#2271b1' );
			grad.addColorStop( 1, '#72aee6' );
			ctx.fillStyle = grad;
			if ( ctx.roundRect ) {
				ctx.beginPath();
				ctx.roundRect( x, y, barW, h, [ 3, 3, 0, 0 ] );
				ctx.fill();
			} else {
				ctx.fillRect( x, y, barW, h );
			}

			// Value above the bar.
			if ( values[ i ] > 0 ) {
				ctx.fillStyle = '#50575e';
				ctx.font = '10px sans-serif';
				ctx.fillText( String( values[ i ] ), x + barW / 2, y - 3 );
			}

			// X label — show day-of-month, skip some when crowded.
			if ( labels.length <= 16 || i % 2 === 0 ) {
				ctx.fillStyle = '#8c8f94';
				ctx.font = '10px sans-serif';
				ctx.fillText( label.slice( 5 ), x + barW / 2, c.h - 8 );
			}
		} );
	}

	function drawDonut( canvasId, legendId, data, colorMap ) {
		var canvas = document.getElementById( canvasId );
		if ( ! canvas ) {
			return;
		}
		var labels = Object.keys( data );
		var values = labels.map( function ( k ) {
			return data[ k ];
		} );
		var total = values.reduce( function ( a, b ) {
			return a + b;
		}, 0 );

		var c = setupCanvas( canvas );
		var ctx = c.ctx;
		var cx = c.w / 2;
		var cy = c.h / 2;
		var radius = Math.min( c.w, c.h ) / 2 - 8;
		var inner = radius * 0.62;

		if ( 0 === total ) {
			ctx.fillStyle = '#8c8f94';
			ctx.font = '12px sans-serif';
			ctx.textAlign = 'center';
			ctx.fillText( '—', cx, cy );
			return;
		}

		var start = -Math.PI / 2;
		labels.forEach( function ( label, i ) {
			var slice = ( values[ i ] / total ) * Math.PI * 2;
			ctx.beginPath();
			ctx.moveTo( cx, cy );
			ctx.arc( cx, cy, radius, start, start + slice );
			ctx.closePath();
			ctx.fillStyle = ( colorMap && colorMap[ label ] ) || COLORS[ i % COLORS.length ];
			ctx.fill();
			start += slice;
		} );

		// Punch the hole.
		ctx.globalCompositeOperation = 'destination-out';
		ctx.beginPath();
		ctx.arc( cx, cy, inner, 0, Math.PI * 2 );
		ctx.fill();
		ctx.globalCompositeOperation = 'source-over';

		// Center total.
		ctx.fillStyle = '#1d2327';
		ctx.font = '700 20px sans-serif';
		ctx.textAlign = 'center';
		ctx.fillText( String( total ), cx, cy + 2 );
		ctx.fillStyle = '#8c8f94';
		ctx.font = '10px sans-serif';
		ctx.fillText( 'total', cx, cy + 16 );

		// Legend.
		var legend = document.getElementById( legendId );
		if ( legend ) {
			legend.innerHTML = '';
			labels.forEach( function ( label, i ) {
				var item = document.createElement( 'span' );
				item.className = 'wpdm-legend-item';
				var swatch = document.createElement( 'span' );
				swatch.className = 'wpdm-legend-swatch';
				swatch.style.background = ( colorMap && colorMap[ label ] ) || COLORS[ i % COLORS.length ];
				item.appendChild( swatch );
				item.appendChild( document.createTextNode( label + ' (' + values[ i ] + ')' ) );
				legend.appendChild( item );
			} );
		}
	}

	function renderCharts() {
		var activity = wpdmData.activity;
		if ( ! activity ) {
			return;
		}
		drawBarChart(
			'wpdm-chart-per-day',
			Object.keys( activity.per_day ),
			Object.keys( activity.per_day ).map( function ( k ) {
				return activity.per_day[ k ];
			} )
		);
		drawDonut( 'wpdm-chart-severity', 'wpdm-legend-severity', activity.by_severity, SEVERITY_COLORS );
		drawDonut( 'wpdm-chart-actions', 'wpdm-legend-actions', activity.by_action, null );
	}

	renderCharts();

	var resizeTimer;
	window.addEventListener( 'resize', function () {
		clearTimeout( resizeTimer );
		resizeTimer = setTimeout( renderCharts, 150 );
	} );
} )();
