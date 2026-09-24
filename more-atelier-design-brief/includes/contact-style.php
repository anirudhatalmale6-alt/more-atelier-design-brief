<?php
/**
 * Dress Contact Form 7 forms in the same clothes as the brief.
 *
 * Off by default and controlled from Settings → Design Brief, because it
 * changes the look of an existing, working form. Nothing about how the form
 * behaves or where it sends is touched — this is purely how it looks.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function madb_style_contact_enabled() {
	return '1' === (string) madb_opt( 'style_cf7', '0' );
}

add_action( 'wp_enqueue_scripts', function () {
	if ( ! madb_style_contact_enabled() ) { return; }
	if ( ! is_singular() ) { return; }

	$post = get_post();
	if ( ! $post ) { return; }

	// Only where a CF7 form actually is. The brief page has its own styling
	// and must not have this layered on top of it.
	if ( madb_post_has_brief( $post ) ) { return; }

	$content   = (string) $post->post_content;
	$elementor = get_post_meta( $post->ID, '_elementor_data', true );
	if ( is_array( $elementor ) ) { $elementor = wp_json_encode( $elementor ); }
	$haystack = $content . ' ' . (string) $elementor;

	if ( ! has_shortcode( $content, 'contact-form-7' ) && false === strpos( $haystack, 'contact-form-7' ) ) {
		return;
	}

	wp_enqueue_style( 'madb-contact', MADB_URL . 'assets/contact.css', array(), MADB_VER );
	wp_enqueue_script( 'madb-contact', MADB_URL . 'assets/contact.js', array(), MADB_VER, true );

	// The studio faces, same as the brief — Elementor only declares Acumin on
	// pages that already use it, so don't rely on it being there.
	$faces = madb_font_face_css();
	if ( $faces ) {
		wp_add_inline_style( 'madb-contact', $faces );
	}
} );
