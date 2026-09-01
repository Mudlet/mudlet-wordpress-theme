<?php
/**
 * A single news post.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$release = mudlet_post_release();
	$term    = mudlet_primary_category();

	// Rendered once, then split: the outline pass stamps ids onto the h2s and
	// hands back the list to draw beside them.
	[ $content, $headings ] = mudlet_outline( apply_filters( 'the_content', get_the_content() ) );

	// Built once and printed twice — in the rail, which is sticky and is where
	// it is meant to be read, and in the article for the widths where the rail
	// is gone. The CSS shows exactly one of the two; see .prose .outline.
	// Two headings is a page, not a structure worth a table of contents.
	$outline = '';
	if ( count( $headings ) > 2 ) {
		ob_start();
		?>
		<b><?php esc_html_e( 'In this post', 'mudlet' ); ?></b>
		<div class="olist">
			<?php foreach ( $headings as $h ) : ?>
				<a href="#<?php echo esc_attr( $h['id'] ); ?>"><?php echo esc_html( $h['text'] ); ?></a>
			<?php endforeach; ?>
		</div>
		<?php
		$outline = (string) ob_get_clean();
	}
	?>

	<div class="page page--post">
		<article class="post" id="post-<?php the_ID(); ?>">
			<div class="w">
				<div class="postgrid">
					<div class="postmain">
						<p class="crumbs">
							<a href="<?php echo esc_url( mudlet_news_url() ); ?>"><?php esc_html_e( 'News', 'mudlet' ); ?></a><span class="sep">/</span>
							<a href="<?php echo esc_url( get_year_link( (int) get_the_date( 'Y' ) ) ); ?>"><?php echo esc_html( get_the_date( 'Y' ) ); ?></a><span class="sep">/</span>
							<a href="<?php echo esc_url( get_month_link( (int) get_the_date( 'Y' ), (int) get_the_date( 'm' ) ) ); ?>"><?php echo esc_html( get_the_date( 'F' ) ); ?></a><span class="sep">/</span>
							<span class="here"><?php the_title(); ?></span>
						</p>

						<header class="phead">
							<p class="phead__top">
								<?php mudlet_category_pill(); ?>
								<?php if ( $release ) : ?>
									<span class="verpill"><?php echo esc_html( $release['version'] ); ?></span>
								<?php endif; ?>
							</p>
							<h1><?php the_title(); ?></h1>
							<p class="byline">
								<span class="avatar" aria-hidden="true"><?php echo esc_html( mudlet_author_initials() ); ?></span>
								<span class="who"><?php the_author(); ?></span><span class="dot">·</span>
								<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
							</p>
						</header>

						<div class="prose">
							<?php if ( $outline ) : ?>
								<nav class="outline" aria-label="<?php esc_attr_e( 'What is in this post', 'mudlet' ); ?>">
									<?php echo $outline; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped piece by piece above. ?>
								</nav>
							<?php endif; ?>

							<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already through the_content. ?>

							<?php get_template_part( 'template-parts/post/changelog' ); ?>
							<?php get_template_part( 'template-parts/post/contributors' ); ?>
						</div>

						<div class="pactions">
							<?php // /download/ hands over the newest build, whatever this post is about, so the button says that and not a version it will not serve. ?>
							<a class="btn" href="<?php echo esc_url( mudlet_download_url() ); ?>"><?php mudlet_icon( 'download' ); ?><?php esc_html_e( 'Download Mudlet', 'mudlet' ); ?></a>
							<?php if ( $release && ! empty( $release['url'] ) ) : ?>
								<a class="btn btn--ghost" href="<?php echo esc_url( $release['url'] ); ?>">
									<?php
									mudlet_icon( 'github' );
									printf(
										/* translators: %s: version number */
										esc_html__( 'Download %s from GitHub', 'mudlet' ),
										esc_html( $release['version'] )
									);
									?>
								</a>
							<?php endif; ?>
							<a class="btn btn--ghost" href="https://forums.mudlet.org/"><?php esc_html_e( 'Discuss on the forum', 'mudlet' ); ?></a>
						</div>

						<nav class="postnav" aria-label="<?php esc_attr_e( 'More posts', 'mudlet' ); ?>">
							<?php
							$prev = get_previous_post();
							$next = get_next_post();

							if ( $prev instanceof WP_Post ) :
								?>
								<a class="pnav" href="<?php echo esc_url( get_permalink( $prev ) ); ?>">
									<span><?php esc_html_e( '← Older post', 'mudlet' ); ?></span>
									<b><?php echo esc_html( get_the_title( $prev ) ); ?></b>
								</a>
							<?php endif; ?>

							<?php if ( $next instanceof WP_Post ) : ?>
								<a class="pnav pnav--next" href="<?php echo esc_url( get_permalink( $next ) ); ?>">
									<span><?php esc_html_e( 'Newer post →', 'mudlet' ); ?></span>
									<b><?php echo esc_html( get_the_title( $next ) ); ?></b>
								</a>
							<?php else : ?>
								<a class="pnav pnav--next" href="<?php echo esc_url( mudlet_news_url() ); ?>">
									<span><?php esc_html_e( 'Index →', 'mudlet' ); ?></span>
									<b>
										<?php
										printf(
											/* translators: %s: number of posts */
											esc_html__( 'All %s posts', 'mudlet' ),
											esc_html( number_format_i18n( (int) wp_count_posts()->publish ) )
										);
										?>
									</b>
								</a>
							<?php endif; ?>
						</nav>
					</div><!-- /.postmain -->

					<aside class="prail" aria-label="<?php esc_attr_e( 'About this post', 'mudlet' ); ?>">
						<?php if ( $outline ) : ?>
							<nav class="rpanel outline outline--rail" aria-label="<?php esc_attr_e( 'What is in this post', 'mudlet' ); ?>">
								<?php echo $outline; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped piece by piece above. ?>
							</nav>
						<?php endif; ?>

						<?php if ( $release ) : ?>
							<div class="rpanel">
								<b><?php esc_html_e( 'This release', 'mudlet' ); ?></b>
								<div class="relbox">
									<b><?php echo esc_html( $release['version'] ); ?></b>
									<span class="when">
										<?php
										printf(
											/* translators: %s: release date */
											esc_html__( 'released %s', 'mudlet' ),
											esc_html( $release['date'] )
										);
										?>
									</span>
									<dl>
										<?php foreach ( $release['rows'] as $row ) : ?>
											<dt><?php echo esc_html( $row[0] ); ?></dt><dd><?php echo esc_html( $row[1] ); ?></dd>
										<?php endforeach; ?>
									</dl>
								</div>
								<a class="btn" href="<?php echo esc_url( mudlet_download_url() ); ?>"><?php mudlet_icon( 'download' ); ?><?php esc_html_e( 'Download Mudlet', 'mudlet' ); ?></a>
								<?php if ( ! empty( $release['url'] ) ) : ?>
									<?php // This release, as GitHub published it - the one place an old version is still on offer. ?>
									<a class="btn btn--ghost" href="<?php echo esc_url( $release['url'] ); ?>"><?php mudlet_icon( 'github' ); ?><?php esc_html_e( 'Download from GitHub', 'mudlet' ); ?></a>
								<?php endif; ?>
							</div>
						<?php endif; ?>

						<div class="rpanel">
							<b><?php esc_html_e( 'Discuss', 'mudlet' ); ?></b>
							<div class="rlinks">
								<a href="https://forums.mudlet.org/"><?php esc_html_e( 'Community forum', 'mudlet' ); ?></a>
								<a href="https://discord.gg/kuYvMQ9"><?php esc_html_e( 'Discord server', 'mudlet' ); ?></a>
								<a href="https://github.com/Mudlet/Mudlet/releases"><?php esc_html_e( 'Releases on GitHub', 'mudlet' ); ?></a>
							</div>
						</div>
					</aside>
				</div><!-- /.postgrid -->
			</div>
		</article>

		<?php
		get_template_part(
			'template-parts/newsband',
			null,
			array(
				'eyebrow'  => __( 'related', 'mudlet' ),
				'note'     => __( 'more posts', 'mudlet' ),
				'heading'  => __( 'Keep reading', 'mudlet' ),
				'exclude'  => get_the_ID(),
				'category' => $term ? $term->term_id : 0,
			)
		);
		?>
	</div>

	<?php
endwhile;

get_footer();
