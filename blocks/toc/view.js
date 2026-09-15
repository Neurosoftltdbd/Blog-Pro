/**
 * TOC — frontend enhancement (view script, deferred).
 *
 * All behavior is progressive enhancement: if anything is missing the
 * native anchor links still work.
 *   - smooth scroll to headings (respects scroll-mt-24 offset)
 *   - scrollspy: highlights the section being read (all links across
 *     every .bp-toc on the page — mobile card + desktop sidebar sync)
 *   - reading progress bar (.bp-toc__bar) tracks the article, not the
 *     viewport, so it fills 0→100% across the post body
 */
document.addEventListener( 'DOMContentLoaded', function () {
	var navs = document.querySelectorAll( '.bp-toc' );
	if ( ! navs.length ) {
		return;
	}

	var allLinks = [];
	Array.prototype.forEach.call( navs, function ( nav ) {
		Array.prototype.forEach.call( nav.querySelectorAll( '.bp-toc__link' ), function ( link ) {
			allLinks.push( link );
		} );
	} );
	if ( ! allLinks.length ) {
		return;
	}

	// Smooth scroll targeting heading anchors.
	allLinks.forEach( function ( link ) {
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
			target.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			if ( history.replaceState ) {
				history.replaceState( null, '', href );
			}
		} );
	} );

	// Map heading element → link indexes (several navs share anchors).
	var headings = [];
	allLinks.forEach( function ( link ) {
		var href = link.getAttribute( 'href' );
		var el   = href ? document.getElementById( href.slice( 1 ) ) : null;
		if ( ! el ) {
			return;
		}
		var found = false;
		for ( var i = 0; i < headings.length; i++ ) {
			if ( headings[ i ].el === el ) {
				headings[ i ].links.push( link );
				found = true;
				break;
			}
		}
		if ( ! found ) {
			headings.push( { el: el, links: [ link ] } );
		}
	} );

	// Toggled classes must already exist in the compiled Tailwind build
	// (input.css only scans *.php/*.html). Both are used by the TOC markup.
	var ACTIVE_CLASSES = [ 'bg-indigo-50', 'text-indigo-700' ];
	var activeIndex = -1;

	function markActive( i ) {
		if ( i === activeIndex ) {
			return;
		}
		var prev = activeIndex;
		activeIndex = i;
		if ( prev > -1 && headings[ prev ] ) {
			headings[ prev ].links.forEach( function ( link ) {
				ACTIVE_CLASSES.forEach( function ( c ) { link.classList.remove( c ); } );
			} );
		}
		if ( i > -1 ) {
			headings[ i ].links.forEach( function ( link ) {
				ACTIVE_CLASSES.forEach( function ( c ) { link.classList.add( c ); } );
			} );
			// keep the active row visible inside a scrolled sidebar
			var last = headings[ i ].links[ headings[ i ].links.length - 1 ];
			var box  = last.closest ? last.closest( '.overflow-y-auto' ) : null;
			if ( box ) {
				var lr = last.getBoundingClientRect();
				var br = box.getBoundingClientRect();
				var delta = 0;
				if ( lr.top < br.top + 8 ) {
					delta = lr.top - br.top - 24;
				} else if ( lr.bottom > br.bottom - 8 ) {
					delta = lr.bottom - br.bottom + 24;
				}
				if ( delta ) {
					box.scrollTo( { top: box.scrollTop + delta, behavior: 'smooth' } );
				}
			}
		}
	}

	// Scrollspy — highlight current section.
	if ( 'IntersectionObserver' in window && headings.length ) {
		var visible = {};
		var spy = new IntersectionObserver(
			function ( entries ) {
				entries.forEach( function ( entry ) {
					var i = headings.findIndex( function ( h ) { return h.el === entry.target; } );
					if ( i < 0 ) {
						return;
					}
					if ( entry.isIntersecting ) {
						visible[ i ] = true;
					} else {
						delete visible[ i ];
					}
				} );
				var keys = Object.keys( visible ).map( Number );
				if ( keys.length ) {
					markActive( Math.min.apply( null, keys ) );
				}
			},
			{ rootMargin: '-72px 0px -70% 0px', threshold: 0 }
		);
		headings.forEach( function ( h ) {
			spy.observe( h.el );
		} );
	}

	// Reading progress bar — fill across the article body.
	var bars = document.querySelectorAll( '.bp-toc__bar' );
	if ( bars.length ) {
		var article = document.querySelector( 'article' ) || document.body;
		var raf = false;
		var update = function () {
			raf = false;
			var rect  = article.getBoundingClientRect();
			var total = rect.height - window.innerHeight;
			var done  = total > 0 ? Math.min( 1, Math.max( 0, -rect.top / total ) ) : 1;
			Array.prototype.forEach.call( bars, function ( bar ) {
				bar.style.width = Math.round( done * 100 ) + '%';
			} );
		};
		window.addEventListener( 'scroll', function () {
			if ( ! raf ) {
				raf = true;
				window.requestAnimationFrame( update );
			}
		}, { passive: true } );
		update();
	}
} );
