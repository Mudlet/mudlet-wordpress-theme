<?php
/**
 * The Mudlet makers, from the Mudlet Makers plugin.
 *
 * The same reasoning that moved games and releases out of the theme applies
 * here: who makes Mudlet is a fact about the project, not a decision about how
 * a page looks, so it lives in a plugin that reads it from Mudlet's own About
 * dialog — and the theme asks.
 *
 * Everything goes through function_exists(), because a theme that hard-requires
 * a plugin is a theme that white-screens when somebody deactivates one.
 *
 * What the fallback is, though, is different from the games grid's. There the
 * theme carries fifteen logos and draws those. Here there is nothing to carry:
 * a hardcoded list of people is exactly the thing this replaces — the live
 * site's own /the-makers/ page is one, fifteen years old, crediting a team that
 * has largely moved on and omitting most of the one that exists. Repeating that
 * in the theme would be reintroducing the bug on purpose. With the plugin gone
 * the page keeps its prose and sends people to the contributors graph, which is
 * at least never wrong.
 *
 * @see wordpress/plugin/mudlet-makers/includes/api.php
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

/** Where the full list of contributors lives when we have no list of our own. */
const MUDLET_CONTRIBUTORS_URL = 'https://github.com/Mudlet/Mudlet/graphs/contributors';

/**
 * Whether maker data is available at all.
 *
 * @return bool
 */
function mudlet_has_maker_data(): bool {
	return function_exists( 'mudlet_makers' ) && mudlet_makers_count() > 0;
}

/**
 * How many people Mudlet credits.
 *
 * @return int
 */
function mudlet_maker_count(): int {
	return mudlet_has_maker_data() ? mudlet_makers_count() : 0;
}

/**
 * The current team, in the order the About dialog lists them.
 *
 * @return array<int, array<string, mixed>>
 */
function mudlet_core_makers(): array {
	return mudlet_has_maker_data() ? mudlet_makers( array( 'group' => 'core' ) ) : array();
}

/**
 * Everybody else who has left a mark on the client.
 *
 * @return array<int, array<string, mixed>>
 */
function mudlet_past_makers(): array {
	return mudlet_has_maker_data() ? mudlet_makers( array( 'group' => 'past' ) ) : array();
}

/**
 * Where /the-makers/ is.
 *
 * @return string
 */
function mudlet_makers_page_url(): string {
	return mudlet_page_url( 'the-makers', '/the-makers/' );
}

/**
 * One maker's card.
 *
 * Shared by the roster and by a maker's own page, so the two never drift into
 * two different-looking versions of the same person.
 *
 * @param array<string, mixed> $maker Row from mudlet_makers().
 * @param bool                 $link  Whether the card is a link to their page.
 */
function mudlet_maker_card( array $maker, bool $link = true ): void {
	$tag  = $link && ! empty( $maker['url'] ) ? 'a' : 'div';
	$href = 'a' === $tag ? ' href="' . esc_url( (string) $maker['url'] ) . '"' : '';

	$did = (string) $maker['description'];

	// One of the descriptions upstream ships carries a link — Thorsten Wilms'
	// homepage — and an <a> inside the card's own <a> is not markup any browser
	// will keep: the parser closes the card early and the rest of it lands
	// outside its grid cell entirely. So a linked card keeps the words and
	// drops the anchor. The live link is still on the maker's own page, which
	// is where the card points anyway.
	if ( 'a' === $tag ) {
		$did = (string) preg_replace( '#<a\b[^>]*>(.*?)</a>#is', '$1', $did );
	}

	printf( '<%s class="mkcard"%s>', esc_attr( $tag ), $href ); // phpcs:ignore WordPress.Security.EscapeOutput
	?>
		<span class="mkface">
			<?php if ( ! empty( $maker['avatar_url'] ) ) : ?>
				<img src="<?php echo esc_url( (string) $maker['avatar_url'] ); ?>" alt="" width="88" height="88" loading="lazy" decoding="async">
			<?php else : ?>
				<span class="mkinit" aria-hidden="true"><?php echo esc_html( (string) $maker['initials'] ); ?></span>
			<?php endif; ?>
		</span>
		<span class="mkwho">
			<b><?php echo esc_html( (string) $maker['name'] ); ?></b>
			<?php if ( ! empty( $maker['github'] ) ) : ?>
				<span class="mkhandle"><?php echo esc_html( (string) $maker['github'] ); ?></span>
			<?php endif; ?>
		</span>
		<span class="mkdid"><?php echo wp_kses_post( $did ); ?></span>
	<?php
	printf( '</%s>', esc_attr( $tag ) ); // phpcs:ignore WordPress.Security.EscapeOutput
}

add_action( 'admin_notices', 'mudlet_makers_plugin_notice' );
/**
 * Say so, once, if the plugin is missing.
 *
 * The page still works without it — it just has no names on it, which is
 * exactly the kind of absence that reads as a design decision rather than a
 * deactivated plugin.
 */
function mudlet_makers_plugin_notice(): void {
	if ( mudlet_has_maker_data() || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'themes', 'plugins' ), true ) ) {
		return;
	}

	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		esc_html__(
			'Mudlet: the makers page is showing no roster. Activate the Mudlet Makers plugin to read the credits from Mudlet itself.',
			'mudlet'
		)
	);
}

/**
 * ── the makers page's own editor ──────────────────────────────────────
 *
 * The roster is read from Mudlet and nobody types it. But the client's credits
 * are not the whole truth — three people on the live page (Nickpick, xtian and
 * Larkin) have never been in `dlgAboutDialog.cpp` — and a page that can only
 * print what the client knows drops them silently. Nothing records why they are
 * on one list and not the other, so nothing here guesses: the live page gives a
 * role each, and that is what gets carried over.
 *
 * So the page has a second editable region, under the roster, and the edit
 * screen shows it as what it is: a labelled **Also credited** editor below the
 * body, with a sentence saying where it lands. The first attempt at this split
 * the body on WordPress's own `<!--more-->` marker, which worked and was
 * invisible: the body arrives in the block editor as one Classic block, and
 * that marker is only drawn inside the block's own editor. A seam nobody can
 * see is a seam somebody types on the wrong side of.
 *
 * It is post meta rather than page content because it is a distinct region with
 * a distinct position on the page, not the tail of a document — and because a
 * field that says what it is beats a convention somebody has to be told about.
 */

/** Where the hand-written credits live. */
const MUDLET_MAKERS_EXTRA_META = '_mudlet_makers_extra';

/**
 * Whether a page is drawn by page-the-makers.php.
 *
 * Two ways to land on that template, so two things to check: an explicit
 * "The makers" template assignment, or the slug, which WordPress matches on its
 * own. The slug case is the one the seed produces.
 *
 * @param WP_Post|int|null $post Page.
 * @return bool
 */
function mudlet_is_makers_page( $post = null ): bool {
	$post = get_post( $post );
	if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
		return false;
	}

	return 'page-the-makers.php' === get_page_template_slug( $post ) || 'the-makers' === $post->post_name;
}

/**
 * The hand-written credits, as stored.
 *
 * @param int|null $post_id Page id.
 * @return string HTML, or '' when nothing has been written.
 */
function mudlet_makers_extra( ?int $post_id = null ): string {
	$post_id = $post_id ?: get_the_ID();

	return $post_id ? trim( (string) get_post_meta( $post_id, MUDLET_MAKERS_EXTRA_META, true ) ) : '';
}

add_action( 'add_meta_boxes_page', 'mudlet_makers_editor_box' );
/**
 * Add the second editor, on that one page only.
 *
 * @param WP_Post $post Page being edited.
 */
function mudlet_makers_editor_box( WP_Post $post ): void {
	if ( ! mudlet_is_makers_page( $post ) ) {
		return;
	}

	add_meta_box(
		'mudlet-makers-extra',
		__( 'Also credited', 'mudlet' ),
		'mudlet_makers_editor',
		'page',
		'normal',
		// Below the body, because that is where it renders.
		'low'
	);
}

/**
 * Draw it.
 *
 * @param WP_Post $post Page being edited.
 */
function mudlet_makers_editor( WP_Post $post ): void {
	wp_nonce_field( 'mudlet_makers_extra', 'mudlet_makers_extra_nonce' );
	?>
	<p class="description" style="margin:0 0 10px">
		<?php
		esc_html_e(
			'Renders under the roster. The roster itself comes from Mudlet\'s own credits and cannot be edited here — this is for people the client does not list.',
			'mudlet'
		);
		?>
	</p>
	<?php
	wp_editor(
		mudlet_makers_extra( $post->ID ),
		'mudlet_makers_extra',
		array(
			'textarea_rows' => 10,
			'media_buttons' => false,
			'teeny'         => true,
		)
	);
}

add_filter( 'use_block_editor_for_post', 'mudlet_makers_classic_editor', 10, 2 );
/**
 * Give that page the classic editor.
 *
 * Not a preference about editors — it is what makes the screen coherent. A
 * `wp_editor()` in a meta box is a first-class citizen on the classic screen and
 * an afterthought under the block canvas, and the page's body is already a
 * single Classic block (the seed writes HTML, and nobody has converted it). Two
 * editors that look and behave the same beats a block canvas with a TinyMCE box
 * bolted underneath.
 *
 * @param bool    $use  Whether to use the block editor.
 * @param WP_Post $post Post.
 * @return bool
 */
function mudlet_makers_classic_editor( $use, $post ) {
	return mudlet_is_makers_page( $post ) ? false : $use;
}

add_action( 'save_post_page', 'mudlet_makers_editor_save', 10, 2 );
/**
 * Store what was typed.
 *
 * @param int     $post_id Page id.
 * @param WP_Post $post    Page.
 */
function mudlet_makers_editor_save( $post_id, $post ): void {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	// Absence is not emptiness: any save that did not come from this screen -
	// Quick Edit, REST, a bulk edit - carries no field at all, and must leave
	// what is stored alone rather than wiping it.
	if ( ! isset( $_POST['mudlet_makers_extra'], $_POST['mudlet_makers_extra_nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['mudlet_makers_extra_nonce'] ) ), 'mudlet_makers_extra' )
		|| ! current_user_can( 'edit_post', $post_id )
		|| ! mudlet_is_makers_page( $post ) ) {
		return;
	}

	$html = trim( wp_kses_post( wp_unslash( $_POST['mudlet_makers_extra'] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

	if ( '' === $html ) {
		delete_post_meta( $post_id, MUDLET_MAKERS_EXTRA_META );
		return;
	}

	update_post_meta( $post_id, MUDLET_MAKERS_EXTRA_META, $html );
}
