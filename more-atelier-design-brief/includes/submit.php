<?php
/**
 * Receives the brief, stores the uploads, emails the studio.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'rest_api_init', function () {
	register_rest_route( 'madb/v1', '/submit', array(
		'methods'  => 'POST',
		'callback' => 'madb_handle_submit',
		// A public enquiry form. Nonces are deliberately not required: they
		// expire under page caching and there is no privileged action here.
		// The real protections are the honeypot, the time trap, the per-IP
		// rate limit and strict validation below.
		'permission_callback' => '__return_true',
	) );
} );

function madb_client_ip() {
	$ip = $_SERVER['REMOTE_ADDR'] ?? '';
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
}

function madb_handle_submit( WP_REST_Request $req ) {

	/* ---- bots -------------------------------------------------------- */
	if ( '' !== trim( (string) $req->get_param( 'ma_website' ) ) ) {
		// Honeypot filled. Look successful; tell them nothing.
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	$elapsed = (int) $req->get_param( 'ma_elapsed' );
	if ( $elapsed > 0 && $elapsed < 4 ) {
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	// Counts only briefs that actually went out — see the increment after
	// sending. A visitor who mistypes their email must never be locked out
	// of a form this long.
	$rl_key = 'madb_rl_' . md5( madb_client_ip() );
	if ( (int) get_transient( $rl_key ) >= 5 ) {
		return new WP_Error( 'madb_rate', 'Too many submissions. Please try again shortly.', array( 'status' => 429 ) );
	}

	/* ---- the answers ------------------------------------------------- */
	$schema  = madb_fields();
	$answers = array();

	foreach ( $schema as $name => $f ) {
		if ( 'file' === $f['type'] ) { continue; }

		if ( 'checkbox' === $f['type'] ) {
			$raw = $req->get_param( $name );
			$raw = is_array( $raw ) ? $raw : ( null === $raw ? array() : array( $raw ) );
			$valid = array_values( array_intersect(
				array_map( 'strval', $raw ),
				array_map( 'strval', array_keys( madb_option_values( $f ) ) )
			) );
			$answers[ $name ] = $valid;
			continue;
		}

		if ( 'radio' === $f['type'] ) {
			$raw = (string) $req->get_param( $name );
			$answers[ $name ] = array_key_exists( $raw, madb_option_values( $f ) ) ? $raw : '';
			continue;
		}

		$raw = (string) $req->get_param( $name );
		if ( 'textarea' === $f['type'] ) {
			$answers[ $name ] = sanitize_textarea_field( $raw );
		} elseif ( 'email' === $f['type'] ) {
			$answers[ $name ] = sanitize_email( $raw );
		} else {
			$answers[ $name ] = sanitize_text_field( $raw );
		}
	}

	/* ---- the two that actually matter -------------------------------- */
	if ( '' === trim( $answers['name'] ) ) {
		return new WP_Error( 'madb_name', 'Please add your name.', array( 'status' => 400 ) );
	}
	if ( ! is_email( $answers['email'] ) ) {
		return new WP_Error( 'madb_email', 'Please check your email address — that’s where we’ll reply.', array( 'status' => 400 ) );
	}

	/* ---- uploads ----------------------------------------------------- */
	$stored = array();
	$errors = array();
	$folder = wp_generate_password( 20, false, false ) . '-' . gmdate( 'Ymd' );

	foreach ( $schema as $name => $f ) {
		if ( 'file' !== $f['type'] ) { continue; }
		$stored[ $name ] = madb_store_files( $name, $f, $folder, $errors );
	}

	if ( $errors ) {
		madb_cleanup( $folder );
		return new WP_Error( 'madb_files', implode( ' ', $errors ), array( 'status' => 400 ) );
	}

	/* ---- send -------------------------------------------------------- */
	$sent = madb_send( $answers, $stored );

	if ( ! $sent ) {
		// Keep the files — he can still be told a brief arrived.
		return new WP_Error(
			'madb_mail',
			'Your brief was saved but the email did not go out. Please contact the studio directly.',
			array( 'status' => 500 )
		);
	}

	set_transient( $rl_key, (int) get_transient( $rl_key ) + 1, HOUR_IN_SECONDS );

	return new WP_REST_Response( array( 'ok' => true ), 200 );
}

/** option value => description, for both flat and assoc option arrays. */
function madb_option_values( $f ) {
	$opts = isset( $f['options'] ) ? $f['options'] : array();
	if ( array_values( $opts ) !== $opts ) { return $opts; }
	$out = array();
	foreach ( $opts as $o ) { $out[ $o ] = ''; }
	return $out;
}

/**
 * Validate and move one upload field's files. Extension AND real type are
 * checked — the browser's accept attribute is a convenience, not a control.
 */
function madb_store_files( $field, $f, $folder, array &$errors ) {
	if ( empty( $_FILES[ $field ] ) || ! is_array( $_FILES[ $field ]['name'] ) ) {
		return array();
	}

	$allowed = array();
	foreach ( explode( ',', $f['accept'] ) as $ext ) {
		$ext = ltrim( trim( $ext ), '.' );
		if ( $ext ) { $allowed[] = strtolower( $ext ); }
	}

	$files = $_FILES[ $field ];
	$count = count( $files['name'] );
	$out   = array();

	if ( $count > MADB_MAX_FILES ) {
		$errors[] = sprintf( 'Please choose no more than %d files.', MADB_MAX_FILES );
		return array();
	}

	$dir = trailingslashit( madb_upload_root() ) . $folder;
	$url = trailingslashit( madb_upload_url() ) . $folder;

	for ( $i = 0; $i < $count; $i++ ) {
		if ( UPLOAD_ERR_NO_FILE === $files['error'][ $i ] || '' === $files['name'][ $i ] ) {
			continue; // an untouched file input still arrives, empty
		}
		if ( UPLOAD_ERR_OK !== $files['error'][ $i ] ) {
			$errors[] = sprintf( '“%s” did not upload correctly.', sanitize_file_name( $files['name'][ $i ] ) );
			continue;
		}
		if ( $files['size'][ $i ] > madb_max_file_bytes() ) {
			$errors[] = sprintf( '“%s” is larger than %dMB.',
				sanitize_file_name( $files['name'][ $i ] ),
				(int) floor( madb_max_file_bytes() / 1048576 ) );
			continue;
		}
		if ( ! is_uploaded_file( $files['tmp_name'][ $i ] ) ) {
			$errors[] = 'Upload failed.';
			continue;
		}

		$clean = sanitize_file_name( $files['name'][ $i ] );
		$ext   = strtolower( pathinfo( $clean, PATHINFO_EXTENSION ) );

		if ( ! in_array( $ext, $allowed, true ) ) {
			$errors[] = sprintf( '“%s” is not a file type we accept.', $clean );
			continue;
		}
		// WordPress's own check, against the real bytes where it can.
		$check = wp_check_filetype_and_ext( $files['tmp_name'][ $i ], $clean );
		if ( empty( $check['ext'] ) && ! in_array( $ext, array( 'dwg', 'dxf', 'heic' ), true ) ) {
			$errors[] = sprintf( '“%s” does not look like a %s file.', $clean, strtoupper( $ext ) );
			continue;
		}

		if ( ! file_exists( $dir ) ) { wp_mkdir_p( $dir ); }

		$final = wp_unique_filename( $dir, $clean );
		$path  = trailingslashit( $dir ) . $final;

		if ( ! move_uploaded_file( $files['tmp_name'][ $i ], $path ) ) {
			$errors[] = sprintf( 'Could not save “%s”.', $clean );
			continue;
		}
		@chmod( $path, 0644 );

		$out[] = array(
			'name' => $final,
			'path' => $path,
			'url'  => trailingslashit( $url ) . rawurlencode( $final ),
			'size' => (int) $files['size'][ $i ],
		);
	}

	return $out;
}

function madb_cleanup( $folder ) {
	$dir = trailingslashit( madb_upload_root() ) . $folder;
	if ( ! is_dir( $dir ) ) { return; }
	foreach ( (array) glob( $dir . '/*' ) as $f ) { @unlink( $f ); }
	@rmdir( $dir );
}

function madb_bytes( $b ) {
	if ( $b < 1024 ) { return $b . ' B'; }
	if ( $b < 1048576 ) { return round( $b / 1024 ) . ' KB'; }
	return round( $b / 1048576, 1 ) . ' MB';
}

/**
 * The email. Laid out so a quote can be written straight from it.
 */
function madb_send( $answers, $stored ) {

	$to = madb_opt( 'to', get_option( 'admin_email' ) );
	$to = array_filter( array_map( 'trim', explode( ',', $to ) ) );
	if ( ! $to ) { return false; }

	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	$host = preg_replace( '/^www\./', '', (string) $host );

	// Strip anything that could forge a header.
	$from_name = str_replace( array( "\r", "\n" ), '', $answers['name'] );

	$subject = sprintf(
		'Design Brief — %s%s',
		$from_name,
		$answers['location'] ? ' — ' . str_replace( array( "\r", "\n" ), '', $answers['location'] ) : ''
	);

	$headers = array(
		'Content-Type: text/html; charset=UTF-8',
		sprintf( 'From: More Atelier Brief <noreply@%s>', $host ),
		sprintf( 'Reply-To: %s <%s>', $from_name, $answers['email'] ),
	);

	/* ---- body -------------------------------------------------------- */
	$schema  = madb_schema();
	$fields  = madb_fields();

	$css_h2  = 'font:400 13px/1.4 Georgia,serif;letter-spacing:.08em;text-transform:uppercase;color:#1a1a1a;margin:28px 0 10px;padding-bottom:6px;border-bottom:1px solid #e2e2e2';
	$css_q   = 'font:400 10px/1.5 Helvetica,Arial,sans-serif;letter-spacing:.11em;text-transform:uppercase;color:#8a8a8a;padding:9px 0 0;vertical-align:top;width:38%';
	$css_a   = 'font:400 13px/1.7 Helvetica,Arial,sans-serif;color:#1a1a1a;padding:9px 0 0;vertical-align:top';

	$b  = '<div style="max-width:660px;margin:0 auto;padding:24px;background:#fff">';
	$b .= '<p style="font:400 11px/1.5 Helvetica,Arial,sans-serif;letter-spacing:.14em;text-transform:uppercase;color:#8a8a8a;margin:0 0 4px">More Atelier</p>';
	$b .= '<h1 style="font:400 20px/1.3 Georgia,serif;letter-spacing:.05em;color:#1a1a1a;margin:0 0 4px">Design Brief</h1>';
	$b .= '<p style="font:400 12px/1.6 Helvetica,Arial,sans-serif;color:#8a8a8a;margin:0">Received ' .
	      esc_html( wp_date( 'j F Y, g:ia' ) ) . '</p>';

	$last_section = '';

	foreach ( $schema as $panel ) {
		if ( empty( $panel['fields'] ) ) { continue; }

		if ( ! empty( $panel['section'] ) && $panel['section'] !== $last_section ) {
			$b .= '<h2 style="' . $css_h2 . '">' . esc_html( $panel['section'] ) . '</h2>';
			$last_section = $panel['section'];
		}

		$rows = '';
		foreach ( $panel['fields'] as $f ) {
			$name = $f['name'];

			if ( 'file' === $f['type'] ) {
				$files = isset( $stored[ $name ] ) ? $stored[ $name ] : array();
				$label = ( 'plans' === $name ) ? 'Plans &amp; documents' : 'Inspiration files';
				if ( ! $files ) {
					$rows .= madb_row( $label, '<span style="color:#b8b8b8">None uploaded</span>', $css_q, $css_a );
					continue;
				}
				$list = '';
				foreach ( $files as $file ) {
					$list .= sprintf(
						'<div style="padding:2px 0"><a href="%s" style="color:#1a1a1a">%s</a> <span style="color:#b0b0b0">(%s)</span></div>',
						esc_url( $file['url'] ), esc_html( $file['name'] ), esc_html( madb_bytes( $file['size'] ) )
					);
				}
				$rows .= madb_row( $label, $list, $css_q, $css_a );
				continue;
			}

			// "Unsure" is not its own question — it is the answer to the size one.
			if ( 'size_unsure' === $name ) { continue; }

			$v = isset( $answers[ $name ] ) ? $answers[ $name ] : '';
			if ( is_array( $v ) ) { $v = implode( ', ', $v ); }
			$v = trim( (string) $v );

			if ( 'project_size' === $name ) {
				$unsure = ! empty( $answers['size_unsure'] );
				if ( $unsure ) {
					$v = 'Unsure';
				} elseif ( '' !== $v ) {
					$v .= ' m²';
				}
			}

			$label = $f['label'];
			if ( '' === $label ) {
				$label = isset( $panel['heading'] ) ? $panel['heading'] : $name;
			}

			$rows .= madb_row(
				$label,
				'' === $v ? '<span style="color:#b8b8b8">—</span>' : nl2br( esc_html( $v ) ),
				$css_q, $css_a
			);
		}

		if ( $rows ) {
			$b .= '<p style="font:400 11px/1.4 Georgia,serif;letter-spacing:.06em;text-transform:uppercase;color:#1a1a1a;margin:16px 0 0">'
			      . esc_html( $panel['heading'] ) . '</p>';
			$b .= '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse">' . $rows . '</table>';
		}
	}

	$b .= '<p style="font:400 11px/1.6 Helvetica,Arial,sans-serif;color:#b0b0b0;margin:32px 0 0;padding-top:12px;border-top:1px solid #eee">'
	    . 'Reply to this email to answer ' . esc_html( $from_name ) . ' directly.</p>';
	$b .= '</div>';

	/* ---- attachments, while the budget lasts ------------------------- */
	$attach = array();
	$budget = MADB_ATTACH_BUDGET;
	foreach ( $stored as $files ) {
		foreach ( $files as $file ) {
			if ( $file['size'] <= $budget ) {
				$attach[] = $file['path'];
				$budget  -= $file['size'];
			}
		}
	}

	return wp_mail( $to, $subject, $b, $headers, $attach );
}

function madb_row( $label, $value, $css_q, $css_a ) {
	return '<tr><td style="' . $css_q . '">' . wp_kses_post( $label ) . '</td>'
	     . '<td style="' . $css_a . '">' . $value . '</td></tr>';
}
