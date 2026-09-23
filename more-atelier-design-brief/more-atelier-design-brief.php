<?php
/**
 * Plugin Name: More Atelier — Design Brief
 * Description: The design brief enquiry form. Place [more_atelier_brief] on a page. Answers and uploads are emailed to the studio.
 * Version:     1.0.1
 * Author:      Anirudha Talmale
 * License:     GPL-2.0-or-later
 * Text Domain: madb
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'MADB_VER',  '1.0.1' );
define( 'MADB_FILE', __FILE__ );
define( 'MADB_DIR',  plugin_dir_path( __FILE__ ) );
define( 'MADB_URL',  plugin_dir_url( __FILE__ ) );

/** Attach uploads to the email while the running total stays under this. */
define( 'MADB_ATTACH_BUDGET', 12 * 1024 * 1024 );
/** What we would like to accept per file. The server may allow less. */
define( 'MADB_WANT_MAX_FILE', 24 * 1024 * 1024 );
/** Most files accepted per upload field. */
define( 'MADB_MAX_FILES', 5 );

/**
 * The real per-file ceiling.
 *
 * PHP's own upload_max_filesize / post_max_size win over anything we ask for:
 * a file above them never reaches the handler at all, it just vanishes and the
 * visitor sees nothing. So promise only what this server can actually take.
 */
function madb_max_file_bytes() {
	static $max = null;
	if ( null !== $max ) { return $max; }

	$limits = array( MADB_WANT_MAX_FILE );

	foreach ( array( 'upload_max_filesize', 'post_max_size' ) as $key ) {
		$v = wp_convert_hr_to_bytes( (string) ini_get( $key ) );
		if ( $v > 0 ) { $limits[] = $v; }
	}

	// post_max_size covers the whole request, so leave room for the rest of it.
	$post = wp_convert_hr_to_bytes( (string) ini_get( 'post_max_size' ) );
	if ( $post > 0 ) { $limits[] = max( 262144, $post - 524288 ); }

	$max = (int) min( $limits );
	return $max;
}

/** Kept for readability at the call sites. */
function madb_max_files() { return MADB_MAX_FILES; }

require_once MADB_DIR . 'includes/fonts.php';
require_once MADB_DIR . 'includes/schema.php';
require_once MADB_DIR . 'includes/render.php';
require_once MADB_DIR . 'includes/submit.php';

/* -------------------------------------------------------------------------
 * Settings — where the brief is sent, and the video that backs the panel.
 * ---------------------------------------------------------------------- */
function madb_opt( $key, $fallback = '' ) {
	$o = get_option( 'madb_settings', array() );
	return isset( $o[ $key ] ) && '' !== $o[ $key ] ? $o[ $key ] : $fallback;
}

add_action( 'admin_menu', function () {
	add_options_page( 'Design Brief', 'Design Brief', 'manage_options', 'madb', 'madb_settings_page' );
} );

add_action( 'admin_init', function () {
	register_setting( 'madb', 'madb_settings', array(
		'sanitize_callback' => function ( $in ) {
			return array(
				'to'        => sanitize_text_field( $in['to'] ?? '' ),
				'video_url' => esc_url_raw( $in['video_url'] ?? '' ),
				'poster_url'=> esc_url_raw( $in['poster_url'] ?? '' ),
			);
		},
	) );
} );

function madb_settings_page() {
	?>
	<div class="wrap">
		<h1>Design Brief</h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'madb' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="madb_to">Send enquiries to</label></th>
					<td>
						<input name="madb_settings[to]" id="madb_to" type="text" class="regular-text"
						       value="<?php echo esc_attr( madb_opt( 'to' ) ); ?>"
						       placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
						<p class="description">Leave empty to use the site admin address. Separate several addresses with commas.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="madb_video">Video URL</label></th>
					<td>
						<input name="madb_settings[video_url]" id="madb_video" type="url" class="regular-text"
						       value="<?php echo esc_attr( madb_opt( 'video_url' ) ); ?>">
						<p class="description">Upload the video to Media, copy its URL and paste it here. Leave empty to show the still image only.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="madb_poster">Still image URL</label></th>
					<td>
						<input name="madb_settings[poster_url]" id="madb_poster" type="url" class="regular-text"
						       value="<?php echo esc_attr( madb_opt( 'poster_url' ) ); ?>">
						<p class="description">Shown while the video loads, and on slow connections. Recommended even if a video is set.</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<p>Put <code>[more_atelier_brief]</code> on the page you want the brief to appear on.</p>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Assets — only on pages that actually carry the shortcode.
 * ---------------------------------------------------------------------- */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular() ) { return; }
	$post = get_post();
	if ( ! $post || ! has_shortcode( $post->post_content, 'more_atelier_brief' ) ) { return; }

	wp_enqueue_style( 'madb', MADB_URL . 'assets/brief.css', array(), MADB_VER );

	// The studio's own faces, declared here so the brief never depends on
	// Elementor's per-page CSS happening to include them.
	$faces = madb_font_face_css();
	if ( $faces ) {
		wp_add_inline_style( 'madb', $faces );
	}
	wp_enqueue_script( 'madb', MADB_URL . 'assets/brief.js', array(), MADB_VER, true );
	wp_localize_script( 'madb', 'MADB', array(
		'endpoint'  => esc_url_raw( rest_url( 'madb/v1/submit' ) ),
		'nonce'     => wp_create_nonce( 'wp_rest' ),
		'maxFiles'  => MADB_MAX_FILES,
		'maxBytes'  => madb_max_file_bytes(),
	) );
} );

/* -------------------------------------------------------------------------
 * The brief is for people he sends the link to — keep it out of search.
 * ---------------------------------------------------------------------- */
add_action( 'wp_head', function () {
	if ( ! is_singular() ) { return; }
	$post = get_post();
	if ( ! $post || ! has_shortcode( $post->post_content, 'more_atelier_brief' ) ) { return; }
	echo '<meta name="robots" content="noindex, nofollow, noarchive">' . "\n";
}, 1 );

/* Belt and braces: keep it out of sitemaps and out of site search too. */
add_filter( 'wp_sitemaps_posts_query_args', function ( $args, $type ) {
	if ( 'page' !== $type ) { return $args; }
	$id = madb_brief_page_id();
	if ( $id ) {
		$args['post__not_in'] = array_merge( $args['post__not_in'] ?? array(), array( $id ) );
	}
	return $args;
}, 10, 2 );

add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ! $q->is_search() || ! $q->is_main_query() ) { return; }
	$id = madb_brief_page_id();
	if ( $id ) {
		$q->set( 'post__not_in', array_merge( (array) $q->get( 'post__not_in' ), array( $id ) ) );
	}
} );

/** Page carrying the shortcode, cached for a day. */
function madb_brief_page_id() {
	$id = get_transient( 'madb_page_id' );
	if ( false !== $id ) { return (int) $id; }
	$id = 0;
	$pages = get_posts( array( 'post_type' => 'page', 'numberposts' => 200, 'fields' => 'ids' ) );
	foreach ( $pages as $pid ) {
		if ( has_shortcode( (string) get_post_field( 'post_content', $pid ), 'more_atelier_brief' ) ) {
			$id = (int) $pid;
			break;
		}
	}
	set_transient( 'madb_page_id', $id, DAY_IN_SECONDS );
	return $id;
}
add_action( 'save_post_page', function () { delete_transient( 'madb_page_id' ); } );

/* -------------------------------------------------------------------------
 * Uploads live in their own folder, not the media library — these are a
 * client's private drawings, they should not appear in Media for anyone
 * browsing the site's library.
 * ---------------------------------------------------------------------- */
function madb_upload_root() {
	$up = wp_upload_dir();
	$dir = trailingslashit( $up['basedir'] ) . 'design-brief';
	if ( ! file_exists( $dir ) ) {
		wp_mkdir_p( $dir );
		// no directory listing, and nothing here is ever executable
		@file_put_contents( $dir . '/index.php', "<?php // Silence is golden." );
		@file_put_contents( $dir . '/.htaccess',
			"Options -Indexes\n" .
			"<FilesMatch \"\\.(php|phtml|php3|php4|php5|php7|phps|cgi|pl|py|sh)$\">\n" .
			"  Require all denied\n" .
			"</FilesMatch>\n"
		);
	}
	return $dir;
}
function madb_upload_url() {
	$up = wp_upload_dir();
	return trailingslashit( $up['baseurl'] ) . 'design-brief';
}

register_activation_hook( __FILE__, 'madb_upload_root' );
