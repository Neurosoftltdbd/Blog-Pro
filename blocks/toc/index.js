/**
 * TOC block — editor registration + viewScript for smooth scroll & scrollspy.
 * Plain JS, no build step. Server renders the heading list in render.php.
 */
(function ( wp ) {
	var registerBlockType = wp.blocks.registerBlockType;
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;
	var RichText = wp.blockEditor.RichText;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var useBlockProps = wp.blockEditor.useBlockProps;

	registerBlockType( 'blog-pro/toc', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'TOC Settings', 'blog-pro' ), initialOpen: true },
						el( TextControl, {
							label: __( 'Block title', 'blog-pro' ),
							value: attributes.title,
							onChange: function ( value ) { setAttributes( { title: value } ); }
						} )
					)
				),
				el(
					'div',
					useBlockProps( { className: 'bp-toc' } ),
					el(
						'div',
						{ className: 'bp-toc__header' },
						el( RichText, {
							tagName: 'h2',
							value: attributes.title,
							onChange: function ( value ) { setAttributes( { title: value } ); },
							placeholder: __( 'Table of Contents', 'blog-pro' )
						} )
					),
					el(
						'p',
						{ className: 'bp-toc__hint' },
						__( 'Automatically lists this post\'s H2/H3 headings with anchor links.', 'blog-pro' )
					),
					el(
						'p',
						{ className: 'bp-toc__hint' },
						__( 'Scrollspy highlights the section you\'re reading; click to scroll smoothly.', 'blog-pro' )
					)
				)
			);
		},
		save: function () {
			return null; // dynamic block
		}
	} );
} )( window.wp );

/* ---------------------------------------------------------------------
 * Frontend enhancement — runs after block renders (deferred view script).
 * Guards: anything missing (ancient browsers, blockers) -> no-op.
 * ------------------------------------------------------------------- */
document.addEventListener( 'DOMContentLoaded', function () {
	var nav = document.querySelector( '.bp-toc' );
	if ( ! nav ) {
		return;
	}

	var links = Array.prototype.slice.call( nav.querySelectorAll( '.bp-toc__link' ) );
	if ( ! links.length ) {
		return;
	}

	// Smooth scroll targeting heading anchors.
	links.forEach( function ( link ) {
		link.addEventListener( 'click', function ( e ) {
			var id = link.getAttribute( 'href' );
			if ( ! id || '#' !== id.charAt( 0 ) ) {
				return;
			}
			var target = document.getElementById( id.slice( 1 ) );
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

	// Scrollspy — highlight active section.
	if ( ! ( 'IntersectionObserver' in window ) ) {
		return;
	}
	var headings = links
		.map( function ( link ) {
			return document.getElementById( link.getAttribute( 'href' ).slice( 1 ) );
		} )
		.filter( Boolean );

	var activeIndex = -1;
	var markActive = function ( i ) {
		if ( i === activeIndex ) {
			return;
		}
		activeIndex = i;
		links.forEach( function ( link, j ) {
			link.classList.toggle( 'is-active', j === i );
		} );
	};

	// Twice the top offsets: sticky-header offset + scroll-mt-24.
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
