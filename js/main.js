/* Blog Pro — minimal vanilla JS, no dependencies. */
(function () {
	'use strict';

	var toggle = document.querySelector( '.nav-toggle' );
	var nav = document.querySelector( '.primary-nav' );

	if ( toggle && nav ) {
		toggle.addEventListener( 'click', function () {
			var isOpen = nav.classList.toggle( 'is-open' );
			toggle.setAttribute( 'aria-expanded', isOpen ? 'true' : 'false' );
		} );
	}

	// Belt-and-suspenders lazy loading for any browser/embed that missed
	// the native loading="lazy" attribute (e.g. dynamically injected iframes).
	if ( 'IntersectionObserver' in window ) {
		var lazyTargets = document.querySelectorAll( 'img:not([loading]), iframe:not([loading])' );
		var io = new IntersectionObserver( function ( entries, observer ) {
			entries.forEach( function ( entry ) {
				if ( entry.isIntersecting ) {
					entry.target.setAttribute( 'loading', 'lazy' );
					observer.unobserve( entry.target );
				}
			} );
		} );
		lazyTargets.forEach( function ( el ) { io.observe( el ); } );
	}
})();
