/**
 * TOC block — frontend enhancement (view script, deferred).
 *
 * All behavior is progressive enhancement: if anything is missing the
 * native anchor links still work.
 *   - smooth scroll to headings (respects scroll-mt-24 offset)
 *   - scrollspy: highlights the section being read
 */
document.addEventListener( 'DOMContentLoaded', function () {
	var navs = document.querySelectorAll( '.bp-toc' );

	Array.prototype.forEach.call( navs, function ( nav ) {
		var links = Array.prototype.slice.call( nav.querySelectorAll( '.bp-toc__link' ) );
		if ( ! links.length ) {
			return;
		}

		// Smooth scroll targeting heading anchors.
		links.forEach( function ( link ) {
			link.addEventListener( 'click', function ( e ) {
				var href = link.getAttribute( 'href' );
				if ( ! href || '#' !== href.charAt( 0 ) ) {
					return;
				}
				var target = document.getElementById( href.slice( 1 ) );
				if ( ! target ) {
					return;
				}
				e.preventDefault();
				target.scrollIntoView( {
					behavior: 'smooth',
					block: 'start',
				} );
			} );
		} );

		// Scrollspy — highlight current section.
		if ( ! ( 'IntersectionObserver' in window ) ) {
			return;
		}
		var headings = links
			.map( function ( link ) {
				var href = link.getAttribute( 'href' );
				return href ? document.getElementById( href.slice( 1 ) ) : null;
			} )
			.filter( Boolean );

		var activeIndex = -1;
		// Toggled classes must already exist in the compiled Tailwind build
		// (input.css only scans *.php/*.html). Both are used by single.php.
		var ACTIVE_CLASSES = [ 'bg-indigo-50', 'text-indigo-700' ];
		var markActive = function ( i ) {
			if ( i === activeIndex ) {
				return;
			}
			var prev = activeIndex;
			activeIndex = i;
			links.forEach( function ( link, j ) {
				if ( j === i ) {
					ACTIVE_CLASSES.forEach( function ( c ) { link.classList.add( c ); } );
				} else if ( j === prev ) {
					ACTIVE_CLASSES.forEach( function ( c ) { link.classList.remove( c ); } );
				}
			} );
		};

		var spy = new IntersectionObserver(
			function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( entry.isIntersecting ) {
						markActive( headings.indexOf( entry.target ) );
					}
				} );
			},
			{ rootMargin: '-64px 0px -70% 0px', threshold: 0 }
		);
		headings.forEach( function ( h ) {
			spy.observe( h );
		} );
	} );
} );
