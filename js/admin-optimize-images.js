/* Blog Pro — drives the "Optimize Existing Images" admin page. */
(function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var startBtn   = document.getElementById( 'blogpro-optimize-start' );
		var wrap       = document.getElementById( 'blogpro-optimize-progress' );
		var bar        = document.getElementById( 'blogpro-optimize-bar' );
		var status     = document.getElementById( 'blogpro-optimize-status' );
		if ( ! startBtn ) return;

		var cfg = window.blogproOptimize || {};
		var offset = 0, total = 0, webpTotal = 0;

		function postForm( action, extra ) {
			var body = new URLSearchParams( Object.assign( { action: action, nonce: cfg.nonce }, extra || {} ) );
			return fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } ).then( function ( r ) { return r.json(); } );
		}

		function runBatch() {
			postForm( 'blogpro_optimize_batch', { offset: offset, batch: cfg.batch } ).then( function ( res ) {
				if ( ! res.success ) {
					status.textContent = cfg.i18n.error;
					return;
				}
				offset    += res.data.processed;
				webpTotal += res.data.webp;

				var pct = total > 0 ? Math.min( 100, Math.round( ( offset / total ) * 100 ) ) : 100;
				bar.style.width = pct + '%';
				status.textContent = cfg.i18n.progress
					.replace( '%1$d', offset )
					.replace( '%2$d', total )
					.replace( '%3$d', webpTotal );

				if ( res.data.more && offset < total ) {
					runBatch();
				} else {
					bar.style.width = '100%';
					status.textContent = cfg.i18n.done;
					startBtn.disabled = false;
					startBtn.textContent = startBtn.dataset.originalLabel;
				}
			} ).catch( function () {
				status.textContent = cfg.i18n.error;
			} );
		}

		startBtn.addEventListener( 'click', function () {
			startBtn.disabled = true;
			startBtn.dataset.originalLabel = startBtn.textContent;
			startBtn.textContent = cfg.i18n.start;
			wrap.style.display = 'block';
			offset = 0;
			webpTotal = 0;
			bar.style.width = '0%';
			status.textContent = cfg.i18n.start;

			postForm( 'blogpro_optimize_count' ).then( function ( res ) {
				total = res.success ? res.data.total : 0;
				if ( total === 0 ) {
					status.textContent = cfg.i18n.done;
					startBtn.disabled = false;
					startBtn.textContent = startBtn.dataset.originalLabel;
					return;
				}
				runBatch();
			} );
		} );

		/* --- Cleanup Orphaned Files button --- */
		var cleanupBtn    = document.getElementById( 'blogpro-cleanup-start' );
		var cleanupStatus = document.getElementById( 'blogpro-cleanup-status' );
		if ( cleanupBtn ) {
			cleanupBtn.addEventListener( 'click', function () {
				cleanupBtn.disabled = true;
				cleanupBtn.textContent = 'Scanning…';
				cleanupStatus.style.display = 'block';
				cleanupStatus.textContent = 'Scanning uploads directory for orphaned files…';

				postForm( 'blogpro_cleanup_orphans' ).then( function ( res ) {
					cleanupBtn.disabled = false;
					cleanupBtn.textContent = 'Cleanup Orphans';
					if ( res.success ) {
						cleanupStatus.textContent = res.data.message;
					} else {
						cleanupStatus.textContent = 'Cleanup failed. Please try again.';
					}
				} ).catch( function () {
					cleanupBtn.disabled = false;
					cleanupBtn.textContent = 'Cleanup Orphans';
					cleanupStatus.textContent = 'Something went wrong. Please try again.';
				} );
			} );
		}
	} );
})();
