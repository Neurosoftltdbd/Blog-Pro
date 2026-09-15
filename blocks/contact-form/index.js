import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';

registerBlockType('blog-pro/contact-form', {
    edit: function Edit() {
        const blockProps = useBlockProps();
        return (
            <div {...blockProps}>
                <p>Contact form will render on frontend.</p>
                <small>Edit in the block settings panel.</small>
            </div>
        );
    },
    save: function Save() {
        return null; // dynamic block, server-rendered
    },
});