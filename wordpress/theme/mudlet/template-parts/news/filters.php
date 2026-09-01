<?php
/**
 * The narrow-screen half of the category filter.
 *
 * The rail carries the other half; only one of the two is ever on screen. Both
 * are links to category archives rather than buttons that hide rows - a filter
 * you can bookmark, send to somebody, and page through.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

$terms   = mudlet_news_categories();
$current = is_category() ? (int) get_queried_object_id() : 0;
$total   = (int) wp_count_posts()->publish;
?>
<div class="filters" role="group" aria-label="<?php esc_attr_e( 'Filter posts by category', 'mudlet' ); ?>">
	<a class="chip" href="<?php echo esc_url( mudlet_news_url() ); ?>" aria-pressed="<?php echo 0 === $current ? 'true' : 'false'; ?>">
		<?php esc_html_e( 'All', 'mudlet' ); ?> <b><?php echo esc_html( number_format_i18n( $total ) ); ?></b>
	</a>
	<?php foreach ( $terms as $term ) : ?>
		<a class="chip" href="<?php echo esc_url( get_category_link( $term ) ); ?>" data-cat="<?php echo esc_attr( mudlet_category_family( $term->slug ) ); ?>" aria-pressed="<?php echo $current === (int) $term->term_id ? 'true' : 'false'; ?>">
			<?php echo esc_html( $term->name ); ?> <b><?php echo esc_html( number_format_i18n( $term->count ) ); ?></b>
		</a>
	<?php endforeach; ?>
</div>
