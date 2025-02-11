/**
 * Hooks.
 */

import { registerBlockVariation } from '@wordpress/blocks';

registerBlockVariation( 'core/query', {
	name: 'related-posts-query-loop',
	title: 'Related Posts Query Loop',
	isActive: [ 'namespace' ],
	attributes: {
		namespace: 'related-posts-query-loop',
	},
	scope: [ 'inserter', 'transform' ],
} );
