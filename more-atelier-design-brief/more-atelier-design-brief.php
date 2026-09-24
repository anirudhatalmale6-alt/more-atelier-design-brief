<?php
/**
 * Plugin Name: More Atelier — Design Brief
 * Description: The design brief enquiry form. Place [more_atelier_brief] on a page. Answers and uploads are emailed to the studio.
 * Version:     1.1.0
 * Author:      Anirudha Talmale
 * License:     GPL-2.0-or-later
 * Text Domain: madb
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'MADB_VER',  '1.1.0' );
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

/**
 * Does this post carry the brief?
 *
 * Checks the Elementor payload as well as post_content. If the page is built
 * with Elementor, the shortcode lives in the `_elementor_data` meta and
 * post_content can be empty or stale — so a plain has_shortcode() check would
 * say no, and then the styles would never load and the page would never be set
 * to noindex. Both of those fail silently, which is the worst kind.
 */
function madb_post_has_brief( $post = null ) {
	$post = get_post( $post );
	if ( ! $post ) { return false; }

	if ( has_shortcode( (string) $post->post_content, 'more_atelier_brief' ) ) { return true; }

	$elementor = get_post_meta( $post->ID, '_elementor_data', true );
	if ( is_array( $elementor ) ) { $elementor = wp_json_encode( $elementor ); }
	if ( is_string( $elementor ) && '' !== $elementor && false !== strpos( $elementor, 'more_atelier_brief' ) ) {
		return true;
	}

	return false;
}

/**
 * Unwrap a redirect-tracker URL.
 *
 * Copying a link out of a chat app often yields the app's click-tracker rather
 * than the file — Freelancer rewrites links as
 * `freelancer.com/users/l.php?url=<encoded>&sig=...`. Pasted into the video or
 * image box that gives a broken panel with no clue why, so dig the real URL
 * back out instead of storing the wrapper.
 */
function madb_unwrap_url( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) { return ''; }

	for ( $i = 0; $i < 3; $i++ ) { // a wrapper can wrap a wrapper
		$query = wp_parse_url( $url, PHP_URL_QUERY );
		if ( ! $query ) { break; }
		parse_str( $query, $args );
		if ( empty( $args['url'] ) ) { break; }
		$inner = urldecode( $args['url'] );
		if ( ! preg_match( '#^https?://#i', $inner ) ) { break; }
		$url = $inner;
	}
	return $url;
}

/**
 * Does this URL actually serve the kind of file we expect? Returns '' when fine,
 * otherwise a sentence for the settings screen.
 */
function madb_check_media_url( $url, $expect ) {
	if ( '' === $url ) { return ''; }
	$res = wp_remote_head( $url, array( 'timeout' => 8, 'redirection' => 3 ) );
	if ( is_wp_error( $res ) ) {
		return 'could not be reached (' . $res->get_error_message() . ')';
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	if ( $code >= 400 ) {
		return 'returned HTTP ' . $code;
	}
	$type = strtolower( (string) wp_remote_retrieve_header( $res, 'content-type' ) );
	if ( '' !== $type && 0 !== strpos( $type, $expect ) ) {
		$parts = explode( ';', $type );
		return 'is being served as ' . $parts[0] . ', not ' . $expect;
	}
	return '';
}

/** Kept for readability at the call sites. */
function madb_max_files() { return MADB_MAX_FILES; }

require_once MADB_DIR . 'includes/seo.php';
require_once MADB_DIR . 'includes/contact-style.php';
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
				'video_url' => esc_url_raw( madb_unwrap_url( $in['video_url'] ?? '' ) ),
				'poster_url'=> esc_url_raw( madb_unwrap_url( $in['poster_url'] ?? '' ) ),
				'style_cf7' => empty( $in['style_cf7'] ) ? '0' : '1',
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
				<tr>
					<th scope="row">Contact form styling</th>
					<td>
						<label>
							<input name="madb_settings[style_cf7]" type="checkbox" value="1"
							       <?php checked( madb_style_contact_enabled() ); ?>>
							Style Contact Form 7 forms to match the brief
						</label>
						<p class="description">Applies the brief's hairline fields and open circles to any Contact Form 7 form on the site. Only changes how they look &mdash; the fields, the wording and where the form sends are untouched. The brief page is left alone, it already has its own styling.</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<p>Put <code>[more_atelier_brief]</code> on the page you want the brief to appear on.</p>
		<p><strong>Privacy:</strong> <?php echo esc_html( madb_seo_status() ); ?></p>
		<?php
		foreach ( array( 'video_url' => 'video', 'poster_url' => 'image' ) as $madb_k => $madb_expect ) {
			$madb_u = madb_opt( $madb_k );
			if ( '' === $madb_u ) { continue; }
			$madb_problem = madb_check_media_url( $madb_u, $madb_expect );
			if ( $madb_problem ) {
				printf(
					'<div class="notice notice-error inline"><p>The %s URL %s. Upload the file to Media and paste the link from there - a link copied out of a chat app is often a redirect rather than the file itself.</p></div>',
					esc_html( $madb_expect ), esc_html( $madb_problem )
				);
			} else {
				printf( '<div class="notice notice-success inline"><p>The %s loads correctly.</p></div>', esc_html( $madb_expect ) );
			}
		}
		?>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Assets — only on pages that actually carry the shortcode.
 * ---------------------------------------------------------------------- */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular() || ! madb_post_has_brief() ) { return; }

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

/** Body class so the full-bleed breakout can trim the scrollbar overflow. */
add_filter( 'body_class', function ( $classes ) {
	if ( is_singular() && madb_post_has_brief() ) {
		$classes[] = 'madb-page';
	}
	return $classes;
} );

/** Page carrying the shortcode, cached for a day. */
function madb_brief_page_id() {
	$id = get_transient( 'madb_page_id' );
	if ( false !== $id ) { return (int) $id; }
	$id = 0;
	$pages = get_posts( array( 'post_type' => 'page', 'numberposts' => 200, 'fields' => 'ids' ) );
	foreach ( $pages as $pid ) {
		if ( madb_post_has_brief( $pid ) ) {
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
