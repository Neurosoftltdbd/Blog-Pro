/**
 * FAQ block — editor registration.
 * Plain JS (no build step; theme has no @wordpress/scripts pipeline).
 */
(function ( wp ) {
	var registerBlockType = wp.blocks.registerBlockType;
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var __ = wp.i18n.__;
	var RichText = wp.blockEditor.RichText;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var Button = wp.components.Button;
	var TextControl = wp.components.TextControl;
	var TextareaControl = wp.components.TextareaControl;
	var ToggleControl = wp.components.ToggleControl;

	var ITEM_DEFAULT = { question: '', answer: '' };

	registerBlockType( 'blog-pro/faq', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var items = attributes.items || [];

			function setItem( index, key, value ) {
				var next = items.slice();
				next[ index ] = Object.assign(
					{},
					ITEM_DEFAULT,
					next[ index ] || {},
					( function () {
						var patch = {};
						patch[ key ] = value;
						return patch;
					} )()
				);
				setAttributes( { items: next } );
			}

			function addItem() {
				setAttributes( { items: items.concat( [ Object.assign( {}, ITEM_DEFAULT ) ] ) } );
			}

			function removeItem( index ) {
				setAttributes( { items: items.filter( function ( _, i ) { return i !== index; } ) } );
			}

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'FAQ Settings', 'blog-pro' ), initialOpen: true },
						el( TextControl, {
							label: __( 'Block title', 'blog-pro' ),
							value: attributes.title,
							onChange: function ( value ) { setAttributes( { title: value } ); }
						} ),
						el( ToggleControl, {
							label: __( 'Open first item', 'blog-pro' ),
							checked: !! attributes.openFirst,
							onChange: function ( value ) { setAttributes( { openFirst: value } ); }
						} )
					)
				),
				el(
					'div',
					wp.blockEditor.useBlockProps( { className: 'bp-faq' } ),
					el(
						'div',
						{ className: 'bp-faq__header' },
						el( RichText, {
							tagName: 'h2',
							value: attributes.title,
							onChange: function ( value ) { setAttributes( { title: value } ); },
							placeholder: __( 'Frequently Asked Questions', 'blog-pro' )
						} )
					),
					items.map( function ( item, index ) {
						return el(
							'div',
							{ key: index, className: 'bp-faq__item bp-faq__item--edit' },
							el( TextareaControl, {
								label: __( 'Question', 'blog-pro' ),
								value: item.question,
								onChange: function ( value ) { setItem( index, 'question', value ); },
								placeholder: __( 'Question', 'blog-pro' ),
								rows: 2
							} ),
							el( TextareaControl, {
								label: __( 'Answer', 'blog-pro' ),
								value: item.answer,
								onChange: function ( value ) { setItem( index, 'answer', value ); },
								placeholder: __( 'Answer', 'blog-pro' ),
								rows: 3
							} ),
							el(
								Button,
								{
									isDestructive: true,
									isSmall: true,
									onClick: function () { removeItem( index ); }
								},
								__( 'Remove item', 'blog-pro' )
							)
						);
					} ),
					el( Button, { isSecondary: true, onClick: addItem }, __( 'Add item', 'blog-pro' ) )
				)
			);
		},
		save: function () {
			return null; // dynamic block — rendered server-side in render.php
		}
	} );
} )( window.wp );
