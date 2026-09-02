<?php
/**
\ \*\ \"What\ is\ Mudlet\?\"\ beside\ \"What\ are\ MUDs\?\"\,\ and\ the\ feature\ run\ under\ them\.
 *
 * The two headings are the section; what sits under them is editable copy from
 * inc/front-content.php. Run through wp_kses on the way out as well as on the
 * way in - the stored value was cleaned when it was saved, but a filter can
 * reach these too, and the allowlist is the same one either way.
 *
 * The feature run sits here rather than in the showcase above, where it started:
 * it is a spec sheet, and a spec sheet belongs under the paragraph that says
 * what the thing is, not under six cards each already making a claim of its own.
 * It is still edited in the Features box on the front page.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

$mudlet_about = mudlet_front_about();
$mudlet_tags  = mudlet_front_prose_tags();
$mudlet_specs = mudlet_front_specs();
?>
<section class="sec">
	<div class="w">
		<div class="split">
		<div>
			<h2><?php esc_html_e( 'What is Mudlet?', 'mudlet' ); ?></h2>
			<?php foreach ( (array) $mudlet_about['intro'] as $mudlet_para ) : ?>
				<p><?php echo wp_kses( (string) $mudlet_para, $mudlet_tags ); ?></p>
			<?php endforeach; ?>
		</div>
		<div>
			<h2><?php esc_html_e( 'What are MUDs?', 'mudlet' ); ?></h2>
			<?php if ( '' !== (string) $mudlet_about['quote'] ) : ?>
				<blockquote class="quote">
					<?php echo wp_kses( (string) $mudlet_about['quote'], $mudlet_tags ); ?>
					<?php if ( '' !== (string) $mudlet_about['cite'] ) : ?>
						<cite><?php echo esc_html( (string) $mudlet_about['cite'] ); ?></cite>
					<?php endif; ?>
				</blockquote>
			<?php endif; ?>
			<?php if ( '' !== (string) $mudlet_about['note'] ) : ?>
				<p class="split__note"><?php echo wp_kses( (string) $mudlet_about['note'], $mudlet_tags ); ?></p>
			<?php endif; ?>
			</div>
		</div>

		<?php // Everything the six cards above have no room to say. ?>
		<?php if ( $mudlet_specs ) : ?>
			<p class="specs"><span class="mk">&gt;</span><b><?php esc_html_e( 'features', 'mudlet' ); ?></b><?php
				// The trailing "and more" is the template's, not the list's: it is
				// true of any list this long, and nobody should have to remember to
				// type it as the last row or notice when a reorder buries it.
				echo implode( '<span class="sep">·</span>', array_map( 'esc_html', $mudlet_specs ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					. '<span class="sep">·</span><span class="specs__more">'
					. esc_html__( 'and more…', 'mudlet' )
					. '</span>';
			?></p>
		<?php endif; ?>
	</div>
</section>
