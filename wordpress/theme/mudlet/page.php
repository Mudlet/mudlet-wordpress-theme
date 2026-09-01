<?php
/**
 * A plain page — about, contribute, the makers, the legal pages.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<div class="page page--page">
		<section class="sec">
			<div class="w">
				<div class="head">
					<h2><?php the_title(); ?></h2>
					<?php if ( has_excerpt() ) : ?>
						<p class="sub"><?php echo esc_html( get_the_excerpt() ); ?></p>
					<?php endif; ?>
				</div>
				<div class="prose">
					<?php the_content(); ?>
					<?php
					wp_link_pages(
						array(
							'before' => '<p class="pagelinks">' . esc_html__( 'Pages:', 'mudlet' ) . ' ',
							'after'  => '</p>',
						)
					);
					?>
				</div>
			</div>
		</section>
	</div>
	<?php
endwhile;

get_footer();
