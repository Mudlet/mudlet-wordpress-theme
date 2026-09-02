<?php
/**
 * The front page.
 *
 * The page is a template: which sections exist, and in what order, is decided
 * here and nowhere else. `the_content()` is deliberately never called - the
 * Home page's body renders nothing, and inc/front-content-admin.php takes the
 * editor off that screen and says why.
 *
 * Three regions inside these sections *are* editable, because they change on a
 * cadence that has nothing to do with deploying a theme: the switcher's panels,
 * the spec line under them, and the two prose columns of "What is Mudlet? /
 * What are MUDs?". They live in an option, they fall back to the copy the
 * templates shipped with, and they are edited on this page's own edit screen.
 * See inc/front-content.php.
 *
 * Everything else here is markup - the hero (whose headline wants its <em>, and
 * whose terminal has to keep agreeing with the demo world's opening room), the
 * headings, the eyebrows, the games grid, the closing call to action. The news
 * band reads real posts.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="page page--home">
	<?php
	get_template_part( 'template-parts/home/hero' );
	get_template_part( 'template-parts/home/showcase' );
	get_template_part( 'template-parts/home/about' );
	get_template_part( 'template-parts/home/games' );

	get_template_part(
		'template-parts/newsband',
		null,
		array(
			'eyebrow' => __( 'news', 'mudlet' ),
			'note'    => __( 'latest releases', 'mudlet' ),
			'heading' => __( 'Latest from the team', 'mudlet' ),
		)
	);

	get_template_part( 'template-parts/home/hopin' );
	?>
</div>

<?php
get_footer();
