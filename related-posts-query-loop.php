<?php
/**
 * Plugin Name: Related Posts Query Loop
 * Description: Add a related posts query loop block.
 * Version: 0.1.0
 * Author: HAMWORKS
 * License: GPL-2.0+
 * GitHub Plugin URI: https://github.com/hamworks/related-posts-query-loop
 *
 * @package related-posts-query-loop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'enqueue_block_editor_assets',
	function () {
		$asset_file = include __DIR__ . '/build/index.asset.php';
		wp_enqueue_script(
			'related-posts-query-loop',
			plugin_dir_url( __FILE__ ) . '/build/index.js',
			$asset_file['dependencies'],
			filemtime( __DIR__ . '/build/index.js' ),
			true
		);
	}
);

add_action(
	'pre_render_block',
	function ( $block_content, $query_block ) {

		if ( 'core/query' !== $query_block['blockName'] ) {
			return;
		}

		add_filter(
			'query_loop_block_query_vars',
			function ( array $query ) use ( $query_block ) {
				if ( ! isset( $query_block['attrs']['namespace'] ) || 'related-posts-query-loop' !== $query_block['attrs']['namespace'] ) {
					return $query;
				}
				$query['orderby'] = 'term_match_count';

				return $query;
			},
			10,
			2
		);
	},
	10,
	2
);

add_filter(
	'posts_clauses',
	function ( $clauses, WP_Query $query ) {

		global $wpdb;

		$field_name = 'term_match_count';

		if ( ! isset( $query->query_vars['orderby'] ) ) {
			return $clauses;
		}
		if ( is_array( $query->query_vars['orderby'] ) ) {
			if ( ! in_array( $field_name, array_keys( $query->query_vars['orderby'] ), true ) ) {
				return $clauses;
			}
		} elseif ( ! in_array( $field_name, explode( ' ', $query->query_vars['orderby'] ), true ) ) {
			return $clauses;
		}

		if ( ! empty( $query->tax_query ) ) {
			$sql_arr = array();

			foreach ( $query->tax_query->queries as $tax_query ) {
				if ( isset( $tax_query['terms'] ) ) {
					$field = esc_sql( $tax_query['field'] ) ?? 'term_id';
					$terms = (array) $tax_query['terms'];
					if ( 'slug' === $field ) {
						$terms = get_terms(
							array(
								'taxonomy'   => $tax_query['taxonomy'],
								'slug'       => $terms,
								'fields'     => 'ids',
								'hide_empty' => false,
							)
						);
					}
					$joined_terms = ( join( ',', $terms ) );
					$sql_arr[]    = <<<SQL
					(
						SELECT COUNT(*) FROM {$wpdb->term_relationships} AS tr
						INNER JOIN {$wpdb->term_taxonomy} AS tt
						ON tr.term_taxonomy_id = tt.term_taxonomy_id
						WHERE tr.object_id = {$wpdb->posts}.ID
						AND tt.taxonomy = '{$tax_query['taxonomy']}'
						AND tt.term_id IN ($joined_terms)
					)
					SQL;
				}
			}

			if ( ! empty( $sql_arr ) ) {
				$clauses['fields'] .= ', (' . join( ' + ', $sql_arr ) . ') AS ' . $field_name;
				$order              = $query->query_vars['order'];
				$clauses['orderby'] = "term_match_count {$order}, " . $clauses['orderby'];
			}
		}

		return $clauses;
	},
	10,
	2
);
