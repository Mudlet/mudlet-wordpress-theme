<?php
/**
 * The news rail.
 *
 * Categories, an archive jump, and the two or three links a reader who has
 * scrolled this far is most likely to want next.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

$terms   = mudlet_news_categories();
$current = is_category() ? (int) get_queried_object_id() : 0;
$total   = (int) wp_count_posts()->publish;
$years   = mudlet_archive_years();
?>
<aside class="rail" aria-label="<?php esc_attr_e( 'News sidebar', 'mudlet' ); ?>">
	<div class="rpanel rail__cats">
		<b><?php esc_html_e( 'Categories', 'mudlet' ); ?></b>
		<div class="catlist" role="group" aria-label="<?php esc_attr_e( 'Filter posts by category', 'mudlet' ); ?>">
			<a class="cat" href="<?php echo esc_url( mudlet_news_url() ); ?>" data-cat="all" aria-pressed="<?php echo 0 === $current ? 'true' : 'false'; ?>">
				<?php esc_html_e( 'All', 'mudlet' ); ?><span class="n"><?php echo esc_html( number_format_i18n( $total ) ); ?></span>
			</a>
			<?php foreach ( $terms as $term ) : ?>
				<a class="cat" href="<?php echo esc_url( get_category_link( $term ) ); ?>" data-cat="<?php echo esc_attr( mudlet_category_family( $term->slug ) ); ?>" aria-pressed="<?php echo $current === (int) $term->term_id ? 'true' : 'false'; ?>">
					<?php echo esc_html( $term->name ); ?><span class="n"><?php echo esc_html( number_format_i18n( $term->count ) ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>

	<?php if ( $years ) : ?>
		<div class="rpanel">
			<b><?php esc_html_e( 'Archive', 'mudlet' ); ?></b>
			<label class="vh" for="yearsel"><?php esc_html_e( 'Jump to a year', 'mudlet' ); ?></label>
			<?php // Real archive URLs, so the control navigates rather than scrolls. ?>
			<select class="rsel" id="yearsel">
				<option value=""><?php esc_html_e( 'Jump to a year…', 'mudlet' ); ?></option>
				<?php foreach ( $years as $year ) : ?>
					<option value="<?php echo esc_url( get_year_link( (int) $year ) ); ?>"><?php echo esc_html( $year ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	<?php endif; ?>

	<div class="rpanel">
		<b><?php esc_html_e( 'Get help', 'mudlet' ); ?></b>
		<div class="rlinks">
			<a href="https://forums.mudlet.org/"><?php esc_html_e( 'Community forum', 'mudlet' ); ?></a>
			<a href="https://discord.gg/kuYvMQ9"><?php esc_html_e( 'Discord server', 'mudlet' ); ?></a>
			<a href="https://wiki.mudlet.org/w/Manual:Contents"><?php esc_html_e( 'The manual', 'mudlet' ); ?></a>
			<a href="https://wiki.mudlet.org/w/Known_Issues"><?php esc_html_e( 'Known issues', 'mudlet' ); ?></a>
			<a href="<?php echo esc_url( mudlet_page_url( 'contact', '/contact/' ) ); ?>"><?php esc_html_e( 'Contact us', 'mudlet' ); ?></a>
		</div>
	</div>

	<div class="rpanel">
		<b><?php esc_html_e( 'Subscribe', 'mudlet' ); ?></b>
		<div class="rlinks">
			<a href="<?php echo esc_url( get_feed_link() ); ?>"><?php esc_html_e( 'RSS feed', 'mudlet' ); ?></a>
			<a href="https://github.com/Mudlet/Mudlet/releases"><?php esc_html_e( 'Releases on GitHub', 'mudlet' ); ?></a>
		</div>
	</div>
</aside>
