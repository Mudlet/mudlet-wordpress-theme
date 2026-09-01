<?php
/**
 * The front page.
 *
 * The sections below are theme markup, not editable content. That is a
 * deliberate stage-one choice and worth being honest about: the copy here is
 * load-bearing on the layout (the hero headline wants its <em>, the switcher
 * wants exactly six panels with an image each), so putting it in the database
 * on day one would trade Divi's problem for a smaller version of the same one.
 * Moving individual strings out to options is a follow-up, not a rewrite - the
 * markup is already split into parts along the seams where it would happen.
 *
 * The one genuinely dynamic section is the news band, which reads real posts.
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
