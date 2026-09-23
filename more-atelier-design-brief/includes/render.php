<?php
/**
 * Builds the brief markup from the schema.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_shortcode( 'more_atelier_brief', 'madb_render' );

function madb_render() {
	ob_start();
	$schema = madb_schema();
	?>
	<form class="ma-brief" id="ma-brief" novalidate enctype="multipart/form-data">
		<div class="ma-flow">
			<?php foreach ( $schema as $panel ) { madb_render_panel( $panel ); } ?>
		</div>
		<?php madb_render_stage(); ?>
	</form>
	<?php
	return ob_get_clean();
}

function madb_render_panel( $panel ) {
	$attrs = '';
	if ( ! empty( $panel['num'] ) )   { $attrs .= ' data-num="' . esc_attr( $panel['num'] ) . '"'; }
	if ( ! empty( $panel['title'] ) ) { $attrs .= ' data-title="' . esc_attr( $panel['title'] ) . '"'; }
	$id = ( 'thanks' === $panel['panel'] ) ? ' id="ma-thanks"' : '';
	?>
	<section class="ma-panel"<?php echo $id . $attrs; // phpcs:ignore ?>>
		<h2 class="ma-h"><?php echo esc_html( $panel['heading'] ); ?></h2>
		<?php
		if ( ! empty( $panel['lede'] ) ) {
			foreach ( $panel['lede'] as $p ) {
				echo '<p class="ma-lede">' . esc_html( $p ) . '</p>';
			}
		}
		if ( ! empty( $panel['fields'] ) ) {
			foreach ( $panel['fields'] as $f ) { madb_render_field( $f ); }
		}
		if ( ! empty( $panel['submit'] ) ) {
			?>
			<div class="ma-hp" aria-hidden="true">
				<label>Website<input type="text" name="ma_website" tabindex="-1" autocomplete="off"></label>
			</div>
			<div class="ma-foot">
				<span class="ma-err" id="ma-err" role="alert"></span>
				<button type="submit" class="ma-submit" id="ma-submit">Submit</button>
			</div>
			<?php
		}
		?>
	</section>
	<?php
}

function madb_render_field( $f ) {
	$name = $f['name'];
	$id   = 'f-' . $name;
	$type = $f['type'];
	echo '<div class="ma-field">';

	// label
	if ( '' !== $f['label'] && ! in_array( $type, array( 'radio', 'checkbox', 'file' ), true ) ) {
		printf( '<label class="ma-q" for="%s">%s</label>', esc_attr( $id ), esc_html( $f['label'] ) );
	} elseif ( '' !== $f['label'] && in_array( $type, array( 'radio', 'checkbox' ), true ) ) {
		printf( '<span class="ma-q">%s</span>', esc_html( $f['label'] ) );
	}

	if ( ! empty( $f['note'] ) ) {
		printf( '<p class="ma-note">%s</p>', esc_html( $f['note'] ) );
	}

	switch ( $type ) {

		case 'textarea':
			printf(
				'<textarea class="ma-ta" id="%s" name="%s" rows="1"%s></textarea>',
				esc_attr( $id ), esc_attr( $name ),
				! empty( $f['placeholder'] ) ? ' placeholder="' . esc_attr( $f['placeholder'] ) . '"' : ''
			);
			break;

		case 'unit':
			echo '<div class="ma-inline">';
			printf( '<input class="ma-in" type="text" inputmode="numeric" id="%s" name="%s">', esc_attr( $id ), esc_attr( $name ) );
			printf( '<span class="ma-unit">%s</span>', esc_html( $f['unit'] ) );
			echo '</div>';
			break;

		case 'radio':
		case 'checkbox':
			$multi   = ( 'checkbox' === $type );
			$opts    = $f['options'];
			$assoc   = array_values( $opts ) !== $opts; // option => description
			$sent    = isset( $f['sentence_case'] ) ? $f['sentence_case'] : array();
			echo '<div class="ma-opts">';
			foreach ( $opts as $k => $v ) {
				$value = $assoc ? $k : $v;
				$desc  = $assoc ? $v : '';
				$cls   = in_array( $value, $sent, true ) ? ' is-sentence' : '';
				printf(
					'<label class="ma-opt"><input type="%s" name="%s" value="%s"><span class="ma-dot"></span><span class="ma-optlabel%s">%s</span></label>',
					esc_attr( $multi ? 'checkbox' : 'radio' ),
					esc_attr( $multi ? $name . '[]' : $name ),
					esc_attr( $value ), esc_attr( $cls ), esc_html( $value )
				);
				if ( $desc ) {
					printf( '<p class="ma-optdesc">%s</p>', esc_html( $desc ) );
				}
			}
			echo '</div>';
			break;

		case 'file':
			echo '<div class="ma-up">';
			printf(
				'<input type="file" id="%s" name="%s[]" multiple accept="%s">',
				esc_attr( $id ), esc_attr( $name ), esc_attr( $f['accept'] )
			);
			printf( '<label class="ma-uplabel" for="%s">%s</label>', esc_attr( $id ), esc_html( $f['label'] ) );
			printf( '<ul class="ma-files" data-for="%s"></ul>', esc_attr( $id ) );
			printf(
				'<p class="ma-uphint">%s — up to %d files, %dMB each</p>',
				esc_html( $f['hint'] ), (int) MADB_MAX_FILES,
				(int) floor( madb_max_file_bytes() / 1048576 )
			);
			echo '</div>';
			break;

		default: // text, email, tel
			printf(
				'<input class="ma-in" type="%s" id="%s" name="%s"%s>',
				esc_attr( $type ), esc_attr( $id ), esc_attr( $name ),
				! empty( $f['autocomplete'] ) ? ' autocomplete="' . esc_attr( $f['autocomplete'] ) . '"' : ''
			);
	}

	echo '</div>';
}

function madb_render_stage() {
	$video  = madb_opt( 'video_url' );
	$poster = madb_opt( 'poster_url' );
	?>
	<div class="ma-stage" id="ma-stage">
		<?php if ( $poster ) : ?>
			<img class="ma-poster" id="ma-poster" src="<?php echo esc_url( $poster ); ?>" alt="">
		<?php endif; ?>
		<?php if ( $video ) : ?>
			<video id="ma-video" muted loop playsinline preload="metadata"
			       <?php echo $poster ? 'poster="' . esc_url( $poster ) . '"' : ''; ?>>
				<source src="<?php echo esc_url( $video ); ?>" type="video/mp4">
			</video>
		<?php endif; ?>
		<div class="ma-scrim"></div>
		<div class="ma-marker" id="ma-marker">
			<div class="ma-mk" data-slot="a"><span class="ma-mk-n"></span><span class="ma-mk-t"></span></div>
			<div class="ma-mk" data-slot="b"><span class="ma-mk-n"></span><span class="ma-mk-t"></span></div>
		</div>
	</div>
	<?php
}
