<?php
/**
 * Keep the brief page out of search — and make sure only ONE thing is saying so.
 *
 * moreatelier.com.au runs Rank Math. An SEO plugin writes its own robots meta
 * tag and replaces WordPress's core sitemap with its own. If we just printed a
 * second robots tag of our own, the page would carry two conflicting
 * instructions and Rank Math's "index, follow" could be the one that wins —
 * the page would look hidden to us and be perfectly visible to Google.
 *
 * So: always set noindex on WordPress's own robots tag, AND tell any SEO plugin
 * to say noindex too. Both routes agree, and a plugin that is installed but not
 * yet configured cannot leave the page silently indexable.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Is the thing currently being viewed the brief page? */
function madb_is_brief_page() {
	return is_singular() && madb_post_has_brief();
}

function madb_rank_math_active() { return defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ); }
function madb_yoast_active()     { return defined( 'WPSEO_VERSION' ); }

/* -------------------------------------------------------------------------
 * Rank Math
 * ---------------------------------------------------------------------- */
add_filter( 'rank_math/frontend/robots', function ( $robots ) {
	if ( ! madb_is_brief_page() ) { return $robots; }
	return array(
		'index'  => 'noindex',
		'follow' => 'nofollow',
		'noarchive' => 'noarchive',
	);
} );

/** Drop it from Rank Math's sitemap. */
add_filter( 'rank_math/sitemap/entry', function ( $url, $type, $object ) {
	$id = madb_brief_page_id();
	if ( $id && 'post' === $type && isset( $object->ID ) && (int) $object->ID === $id ) {
		return false;
	}
	return $url;
}, 10, 3 );

/* -------------------------------------------------------------------------
 * Yoast, in case it is ever swapped in
 * ---------------------------------------------------------------------- */
add_filter( 'wpseo_robots_array', function ( $robots ) {
	if ( ! madb_is_brief_page() ) { return $robots; }
	$robots['index']  = 'noindex';
	$robots['follow'] = 'nofollow';
	return $robots;
} );

add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', function ( $ids ) {
	$id = madb_brief_page_id();
	if ( $id ) { $ids[] = $id; }
	return $ids;
} );

/* -------------------------------------------------------------------------
 * WordPress core's own robots tag.
 *
 * Contribute to the tag core already prints rather than echoing a second one.
 * This ALWAYS runs — earlier I skipped it whenever an SEO plugin was installed,
 * and that was a real hole: a plugin that is present but not yet configured
 * prints no robots tag at all, so the page was left fully indexable. If an SEO
 * plugin does take over the tag, the filters above make it say noindex too, so
 * the two can never disagree.
 * ---------------------------------------------------------------------- */
add_filter( 'wp_robots', function ( $robots ) {
	if ( ! madb_is_brief_page() ) { return $robots; }
	unset( $robots['index'], $robots['follow'] );
	$robots['noindex']   = true;
	$robots['nofollow']  = true;
	$robots['noarchive'] = true;
	return $robots;
} );

/** WordPress's own sitemap (only generated when no SEO plugin has replaced it). */
add_filter( 'wp_sitemaps_posts_query_args', function ( $args, $type ) {
	if ( 'page' !== $type ) { return $args; }
	$id = madb_brief_page_id();
	if ( $id ) {
		$args['post__not_in'] = array_merge( isset( $args['post__not_in'] ) ? $args['post__not_in'] : array(), array( $id ) );
	}
	return $args;
}, 10, 2 );

/** Keep it out of the site's own search results whatever is installed. */
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ! $q->is_search() || ! $q->is_main_query() ) { return; }
	$id = madb_brief_page_id();
	if ( $id ) {
		$q->set( 'post__not_in', array_merge( (array) $q->get( 'post__not_in' ), array( $id ) ) );
	}
} );

/**
 * Surface the truth on the settings screen rather than leaving him to trust it.
 */
function madb_seo_status() {
	$base = 'The brief page is set to noindex, kept out of the sitemap and hidden from site search.';
	if ( madb_rank_math_active() ) { return $base . ' Rank Math is also told to mark it noindex, so the two cannot disagree.'; }
	if ( madb_yoast_active() )     { return $base . ' Yoast is also told to mark it noindex, so the two cannot disagree.'; }
	return $base;
}
