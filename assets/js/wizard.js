/**
 * WP Dashboard Management — setup wizard navigation.
 */
( function () {
	'use strict';

	var steps = Array.prototype.slice.call( document.querySelectorAll( '.wpdm-wizard-step' ) );
	var dots = Array.prototype.slice.call( document.querySelectorAll( '.wpdm-wizard-dot' ) );
	var current = 1;
	var total = steps.length;

	function show( step ) {
		current = Math.min( Math.max( step, 1 ), total );
		steps.forEach( function ( section ) {
			section.classList.toggle( 'is-active', parseInt( section.dataset.step, 10 ) === current );
		} );
		dots.forEach( function ( dot ) {
			var n = parseInt( dot.dataset.step, 10 );
			dot.classList.toggle( 'is-active', n === current );
			dot.classList.toggle( 'is-done', n < current );
		} );
		if ( current === total ) {
			buildSummary();
		}
	}

	function buildSummary() {
		var list = document.getElementById( 'wpdm-wizard-summary' );
		var empty = document.getElementById( 'wpdm-wizard-summary-empty' );
		if ( ! list ) {
			return;
		}
		list.innerHTML = '';
		var checked = document.querySelectorAll( '.wpdm-wizard-card input:checked' );
		checked.forEach( function ( input ) {
			var label = input.closest( '.wpdm-wizard-card' ).querySelector( 'strong' ).textContent;
			var li = document.createElement( 'li' );
			var icon = document.createElement( 'span' );
			icon.className = 'dashicons dashicons-yes-alt';
			li.appendChild( icon );
			li.appendChild( document.createTextNode( label ) );
			list.appendChild( li );
		} );
		if ( empty ) {
			empty.hidden = checked.length > 0;
		}
	}

	document.querySelectorAll( '.wpdm-wizard-next' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			show( current + 1 );
		} );
	} );

	document.querySelectorAll( '.wpdm-wizard-prev' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			show( current - 1 );
		} );
	} );

	// Card highlight follows the checkbox.
	document.querySelectorAll( '.wpdm-wizard-card input' ).forEach( function ( input ) {
		input.addEventListener( 'change', function () {
			input.closest( '.wpdm-wizard-card' ).classList.toggle( 'is-checked', input.checked );
		} );
	} );

	show( 1 );
} )();
