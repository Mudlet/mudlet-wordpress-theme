<?php
/**
 * A plain page — about, contribute, vision, the legal ones.
 *
 * Anything with a template of its own (/download/, /contact/, /the-makers/,
 * /games/) is not drawn here and does not get the outline: those pages are
 * built out of sections this file knows nothing about, and their h2s are the
 * template's rather than an editor's.
 *
 * The column and the rail are the post's, and deliberately: /contribute/ is
 * the same kind of document a release post is, and it was being read at the
 * full 74rem of the container with no way to see its shape.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	// Rendered once, then split: the outline pass stamps ids onto the h2s and
	// hands back the list to draw beside them.
	[ $content, $headings ] = mudlet_outline( apply_filters( 'the_content', get_the_content() ) );

	// Two questions, and they are not the same one. Is this page a column of
	// prose or a canvas - which the content answers, not the heading count -
	// and if it is a column, is there anything to hang beside it. A canvas
	// takes the container and is never given a rail; /media/ is the canvas.
	//
	// The threshold is 1 here against a post's 3 because a page's rail holds
	// the outline and nothing else, so the number is deciding whether the page
	// has a rail at all rather than whether an outline is worth reading. See
	// mudlet_outline_panel().
	$canvas  = mudlet_page_is_canvas( $content );
	$outline = $canvas ? '' : mudlet_outline_panel( $headings, __( 'On this page', 'mudlet' ), 1 );

	// Every other head on the site opens with the > mark, and these pages were
	// the one place a title stood on its own. The word is not typed: a child page
	// is labelled by its parent, which makes it a breadcrumb (Vision sits under
	// About), and a top-level page answers with its own title, the way /download/
	// and /contact/ do with theirs. Lowercase, because an eyebrow here is a
	// prompt rather than a second heading.
	$mudlet_ancestors = get_post_ancestors( get_the_ID() );
	$mudlet_eyebrow   = $mudlet_ancestors ? get_the_title( end( $mudlet_ancestors ) ) : get_the_title();
	$mudlet_eyebrow   = mb_strtolower( wp_strip_all_tags( $mudlet_eyebrow ) );
	?>
	<div class="page page--page">
		<section class="sec">
			<div class="w">
				<?php
				// No outline, no rail, and no column held open for one - which
				// now catches both a canvas and a page with no headings at all,
				// because $outline is already '' for either.
				?>
				<div class="pagegrid<?php echo $outline ? '' : ' pagegrid--solo'; ?>">
					<div class="pagemain">
						<div class="head">
							<p class="eyebrow"><?php echo esc_html( $mudlet_eyebrow ); ?></p>
							<h2><?php the_title(); ?></h2>
							<?php if ( has_excerpt() ) : ?>
								<p class="sub"><?php echo esc_html( get_the_excerpt() ); ?></p>
							<?php endif; ?>
						</div>

						<div class="prose">
							<?php if ( $outline ) : ?>
								<nav class="outline" aria-label="<?php esc_attr_e( 'What is on this page', 'mudlet' ); ?>">
									<?php echo $outline; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped piece by piece in mudlet_outline_panel(). ?>
								</nav>
							<?php endif; ?>

							<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already through the_content. ?>

							<?php
							wp_link_pages(
								array(
									'before' => '<p class="pagelinks">' . esc_html__( 'Pages:', 'mudlet' ) . ' ',
									'after'  => '</p>',
								)
							);
							?>
						</div>
					</div><!-- /.pagemain -->

					<?php if ( $outline ) : ?>
						<aside class="prail" aria-label="<?php esc_attr_e( 'About this page', 'mudlet' ); ?>">
							<nav class="rpanel outline outline--rail" aria-label="<?php esc_attr_e( 'What is on this page', 'mudlet' ); ?>">
								<?php echo $outline; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped piece by piece in mudlet_outline_panel(). ?>
							</nav>
						</aside>
					<?php endif; ?>
				</div><!-- /.pagegrid -->
			</div>
		</section>
	</div>
	<?php
endwhile;

get_footer();
