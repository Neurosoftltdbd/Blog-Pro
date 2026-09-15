/**
 * HowTo block — editor registration.
 * Plain JS (no build step; mirrors blocks/faq/index.js).
 */
(function ( wp ) {
	var registerBlockType = wp.blocks.registerBlockType;
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;
	var RichText = wp.blockEditor.RichText;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var Button = wp.components.Button;
	var TextControl = wp.components.TextControl;
	var TextareaControl = wp.components.TextareaControl;

	var STEP_DEFAULT = { name: '', text: '' };

	registerBlockType( 'blog-pro/howto', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var steps = attributes.steps || [];

			function setStep( index, key, value ) {
				var next = steps.slice();
				var patch = {};
				patch[ key ] = value;
				next[ index ] = Object.assign( {}, STEP_DEFAULT, next[ index ] || {}, patch );
				setAttributes( { steps: next } );
			}

			function addStep() {
				setAttributes( { steps: steps.concat( [ Object.assign( {}, STEP_DEFAULT ) ] ) } );
			}

			function removeStep( index ) {
				setAttributes( { steps: steps.filter( function ( _, i ) { return i !== index; } ) } );
			}

			function moveStep( index, dir ) {
				var to = index + dir;
				if ( to < 0 || to >= steps.length ) {
					return;
				}
				var next = steps.slice();
				var tmp = next[ index ];
				next[ index ] = next[ to ];
				next[ to ] = tmp;
				setAttributes( { steps: next } );
			}

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'How-To Settings', 'blog-pro' ), initialOpen: true },
						el( TextControl, {
							label: __( 'Block title', 'blog-pro' ),
							value: attributes.title,
							onChange: function ( value ) { setAttributes( { title: value } ); }
						} ),
						el( TextControl, {
							label: __( 'Total time (ISO 8601, e.g. PT15M or PT1H30M)', 'blog-pro' ),
							value: attributes.totalTime,
							onChange: function ( value ) { setAttributes( { totalTime: value } ); },
							placeholder: 'PT15M'
						} )
					)
				),
				el(
					'div',
					useBlockProps( { className: 'bp-howto' } ),
					el(
						'div',
						{ className: 'bp-howto__header' },
						el( RichText, {
							tagName: 'h2',
							value: attributes.title,
							onChange: function ( value ) { setAttributes( { title: value } ); },
							placeholder: __( 'How To', 'blog-pro' )
						} )
					),
					steps.map( function ( step, index ) {
						return el(
							'div',
							{ key: index, className: 'bp-howto__step' },
							el( 'span', { className: 'bp-howto__num' }, String( index + 1 ) ),
							el( 'div', { className: 'bp-howto__fields' },
								el( TextControl, {
									label: __( 'Step name', 'blog-pro' ),
									value: step.name,
									onChange: function ( value ) { setStep( index, 'name', value ); },
									placeholder: __( 'Step name', 'blog-pro' )
								} ),
								el( TextareaControl, {
									label: __( 'Details', 'blog-pro' ),
									value: step.text,
									onChange: function ( value ) { setStep( index, 'text', value ); },
									placeholder: __( 'What to do in this step', 'blog-pro' ),
									rows: 2
								} )
							),
							el( 'div', { className: 'bp-howto__actions' },
								el( Button, { isSmall: true, isSecondary: true, disabled: 0 === index, onClick: function () { moveStep( index, -1 ); }, 'aria-label': __( 'Move up', 'blog-pro' ) }, '↑' ),
								el( Button, { isSmall: true, isSecondary: true, disabled: index === steps.length - 1, onClick: function () { moveStep( index, 1 ); }, 'aria-label': __( 'Move down', 'blog-pro' ) }, '↓' ),
								el( Button, { isSmall: true, isDestructive: true, onClick: function () { removeStep( index ); } }, __( 'Remove', 'blog-pro' ) )
							)
						);
					} ),
					el( Button, { isSecondary: true, onClick: addStep }, __( 'Add step', 'blog-pro' ) )
				)
			);
		},
		save: function () {
			return null; // dynamic block — rendered server-side in render.php
		}
	} );
} )( window.wp );
