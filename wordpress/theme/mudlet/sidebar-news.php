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

	<?php
	if ( $years ) :
		// On a year archive the summary carries the year in place of the
		// prompt, which is the panel saying where you are without having to
		// stay open to say it. It is deliberately not rendered `open` there:
		// picking a year navigates, and a list that comes back expanded reads
		// as a press that did not take.
		$here = is_year() ? (string) get_query_var( 'year' ) : '';
		?>
		<div class="rpanel">
			<b><?php esc_html_e( 'Archive', 'mudlet' ); ?></b>
			<?php
			// A <details> and a grid of real archive links, not a <select>: the
			// select was the one control on the site the browser drew rather
			// than the stylesheet, and - because a select cannot be a link - it
			// needed a change handler to go anywhere, so it did nothing at all
			// without JavaScript. These are the same URLs as <a>, which is what
			// the rest of the rail is.
			?>
			<details class="ydrop">
				<summary>
					<span><?php echo $here ? esc_html( $here ) : esc_html__( 'Jump to a year…', 'mudlet' ); ?></span>
					<?php mudlet_icon( 'caret', 'crt' ); ?>
				</summary>
				<div class="ylist">
					<?php foreach ( $years as $year => $count ) : ?>
						<a href="<?php echo esc_url( get_year_link( (int) $year ) ); ?>"<?php echo $here === (string) $year ? ' aria-current="true"' : ''; ?>>
							<?php echo esc_html( $year ); ?><span class="n"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>
			</details>
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

	<?php
	// Both rows are feeds, because the panel says Subscribe. The second used to
	// be the releases *page* on GitHub, which is a place to read them and not a
	// thing a reader can follow - GitHub publishes the same list at
	// `releases.atom`, so the row now points at that and says which it is.
	?>
	<div class="rpanel">
		<b><?php esc_html_e( 'Subscribe', 'mudlet' ); ?></b>
		<div class="rlinks">
			<a href="<?php echo esc_url( get_feed_link() ); ?>"><?php esc_html_e( 'News (RSS)', 'mudlet' ); ?></a>
			<a href="https://github.com/Mudlet/Mudlet/releases.atom"><?php esc_html_e( 'GitHub releases (Atom)', 'mudlet' ); ?></a>
		</div>
	</div>
</aside>
