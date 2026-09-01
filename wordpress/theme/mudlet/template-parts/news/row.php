<?php
/**
 * One row in the news list.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

$term = mudlet_primary_category();
?>
<a class="prow" href="<?php the_permalink(); ?>" data-year="<?php echo esc_attr( get_the_date( 'Y' ) ); ?>"<?php echo $term ? ' data-cat="' . esc_attr( mudlet_category_family( $term->slug ) ) . '"' : ''; ?>>
	<span class="prow__d"><?php echo esc_html( get_the_date( 'd M' ) ); ?></span>
	<h3><?php the_title(); ?></h3>
	<?php mudlet_category_pill(); ?>
	<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 30, '…' ) ); ?></p>
	<p class="prow__m"><?php the_author(); ?></p>
</a>
