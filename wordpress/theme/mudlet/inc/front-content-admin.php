<?php
/**
 * The front page's three editable regions, on the front page's own edit screen.
 *
 * Where somebody goes to change the front page is Pages -> Home, so that is
 * where the fields are: three meta boxes under a title with no body editor.
 * Same pattern the theme already sets twice - inc/contact.php and
 * inc/makers.php both hang a dedicated field on the one page that renders it.
 *
 * **The body editor is removed rather than left empty.** front-page.php never
 * calls the_content(), so anything written there renders nowhere, and an editor
 * that silently discards what you type is worse than no editor. Removing
 * support also drops this screen to the classic editor, which is where a meta
 * box is a first-class citizen rather than a panel bolted under a canvas - the
 * same trade inc/makers.php makes, and for the same reason.
 *
 * **What is stored is an option, not post meta**, even though the fields live on
 * a post. These are regions of a template rather than facts about this page:
 * `page_on_front` can be pointed at a different page, and the copy should not
 * travel with whichever post happened to be the front page when somebody wrote
 * it. Location and storage are separate questions and get separate answers.
 *
 * **A card's picture is not a field.** Each card carries a small figure drawn in
 * inc/front-art.php and picked here by name, because four of the six claims have
 * no screenshot that could fill an image slot - which is what the previous
 * version of this screen offered, and why it did not work. The real screenshots
 * are the thumbnail row on the front page, and they come from /media/ rather
 * than from anything typed here.
 *
 * The defaults live in inc/front-content.php and are never written here: saving
 * an empty form stores an empty array, and the templates fall back to the copy
 * they shipped with.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

/** Nonce action for the whole screen. */
const MUDLET_FRONT_NONCE = 'mudlet_front_save';

/**
 * Whether a page is the one set as the front page.
 *
 * @param WP_Post|int|null $post Page.
 * @return bool
 */
function mudlet_is_front_page_post( $post = null ): bool {
	$post = get_post( $post );
	if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
		return false;
	}

	return 'page' === get_option( 'show_on_front' ) && $post->ID === (int) get_option( 'page_on_front' );
}

add_action( 'load-post.php', 'mudlet_front_hide_editor' );
/**
 * Take the body editor off the page that is the front page.
 *
 * On load-post.php rather than add_meta_boxes, because the block editor decides
 * whether it is rendering at all well before meta boxes are collected.
 */
function mudlet_front_hide_editor(): void {
	$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( $post_id && mudlet_is_front_page_post( $post_id ) ) {
		remove_post_type_support( 'page', 'editor' );
	}
}

add_action( 'add_meta_boxes_page', 'mudlet_front_boxes' );
/**
 * The three regions, in the order they appear on the page.
 *
 * @param WP_Post $post Page being edited.
 */
function mudlet_front_boxes( WP_Post $post ): void {
	if ( ! mudlet_is_front_page_post( $post ) ) {
		return;
	}

	add_meta_box( 'mudlet-front-cards', __( 'Cards', 'mudlet' ), 'mudlet_front_box_cards', 'page', 'normal', 'high' );
	add_meta_box( 'mudlet-front-specs', __( 'Features', 'mudlet' ), 'mudlet_front_box_specs', 'page', 'normal', 'default' );
	add_meta_box( 'mudlet-front-about', __( 'What is Mudlet? / What are MUDs?', 'mudlet' ), 'mudlet_front_box_about', 'page', 'normal', 'default' );
}

add_action( 'edit_form_after_title', 'mudlet_front_intro' );
/**
 * One line where the editor used to be, so the missing canvas is explained.
 *
 * @param WP_Post $post Page being edited.
 */
function mudlet_front_intro( $post ): void {
	if ( ! $post instanceof WP_Post || ! mudlet_is_front_page_post( $post ) ) {
		return;
	}
	?>
	<p class="description" style="margin:1em 0">
		<?php esc_html_e( 'The theme draws this page from a template, so it has no body to write in. The parts of it that change are below; everything else - the hero, the headings, the games grid, the order of the sections - belongs to the theme.', 'mudlet' ); ?>
	</p>
	<?php
}

add_action( 'admin_enqueue_scripts', 'mudlet_front_admin_assets' );
/**
 * The repeater, on that one screen.
 *
 * @param string $hook Current admin page.
 */
function mudlet_front_admin_assets( string $hook ): void {
	if ( 'post.php' !== $hook ) {
		return;
	}

	$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! $post_id || ! mudlet_is_front_page_post( $post_id ) ) {
		return;
	}

	$uri = get_template_directory_uri();
	$dir = get_template_directory();

	wp_enqueue_style( 'mudlet-front-admin', $uri . '/assets/css/admin-front.css', array(), mudlet_asset_version( $dir . '/assets/css/admin-front.css' ) );

	// jquery-ui-sortable ships with WordPress and is what the nav-menu and
	// widget screens drag with, so dragging costs an enqueue rather than a
	// vendored library. The script degrades to the up/down buttons without it.
	wp_enqueue_script(
		'mudlet-front-admin',
		$uri . '/assets/js/admin-front.js',
		array( 'jquery', 'jquery-ui-sortable' ),
		mudlet_asset_version( $dir . '/assets/js/admin-front.js' ),
		true
	);
}

/**
 * The six cards.
 */
function mudlet_front_box_cards(): void {
	// One nonce for the screen, printed by the first box to draw.
	wp_nonce_field( MUDLET_FRONT_NONCE, 'mudlet_front_nonce' );
	?>
	<div class="mudlet-front">
		<p class="description">
			<?php esc_html_e( 'Each card is a small drawn figure, a title and a sentence. Clearing a card\'s title and sentence removes it when you update.', 'mudlet' ); ?>
		</p>
		<p class="description">
			<?php esc_html_e( 'The figures are drawn by the theme rather than uploaded — two of them show live numbers. Screenshots are not here: the front page takes three at random from the Media page.', 'mudlet' ); ?>
		</p>

		<?php
		/*
		 * Not "mudlet-front-cards": that is the meta box's own id, and
		 * do_meta_boxes() puts it on the .postbox wrapper around all of this.
		 * Two nodes with one id means getElementById returns the wrapper, and
		 * the repeater ends up bound to the box instead of the list.
		 */
		?>
		<div class="mudlet-front__panels" id="mudlet-front-rows">
			<?php
			foreach ( array_values( mudlet_front_cards() ) as $i => $card ) {
				mudlet_front_card_row( (int) $i, $card );
			}
			?>
		</div>

		<p class="mudlet-front__add">
			<button type="button" class="button" id="mudlet-front-add"><?php esc_html_e( 'Add a card', 'mudlet' ); ?></button>
		</p>

		<?php // The blank row the Add button clones. __i__ becomes a number on insert. ?>
		<script type="text/html" id="tmpl-mudlet-front-panel">
			<?php
			mudlet_front_card_row(
				'__i__',
				array(
					'art'   => 'lua',
					'title' => '',
					'body'  => '',
				)
			);
			?>
		</script>
	</div>
	<?php
}

/**
 * The run of short phrases under the cards.
 */
function mudlet_front_box_specs(): void {
	?>
	<div class="mudlet-front">
		<p class="description">
			<?php esc_html_e( 'The run of short phrases under the cards, separated by dots. One per line - this is the list that grows as features ship. The page adds “and more…” at the end on its own.', 'mudlet' ); ?>
		</p>
		<textarea name="<?php echo esc_attr( MUDLET_FRONT_OPTION ); ?>[specs]" rows="10" class="widefat code"
			><?php echo esc_textarea( implode( "\n", mudlet_front_specs() ) ); ?></textarea>
	</div>
	<?php
}

/**
 * The two prose columns.
 */
function mudlet_front_box_about(): void {
	$about = mudlet_front_about();
	$name  = MUDLET_FRONT_OPTION;
	?>
	<div class="mudlet-front">
		<p class="description">
			<?php esc_html_e( 'The two headings belong to the theme; this is what sits under them. A link, bold and italic are allowed.', 'mudlet' ); ?>
		</p>

		<p class="mudlet-front__field">
			<label>
				<span><?php esc_html_e( 'What is Mudlet?', 'mudlet' ); ?></span>
				<textarea class="widefat" rows="7" name="<?php echo esc_attr( $name ); ?>[about][intro]"
					><?php echo esc_textarea( implode( "\n\n", (array) $about['intro'] ) ); ?></textarea>
				<span class="mudlet-front__hint"><?php esc_html_e( 'One blank line between paragraphs.', 'mudlet' ); ?></span>
			</label>
		</p>

		<p class="mudlet-front__field">
			<label>
				<span><?php esc_html_e( 'The quotation', 'mudlet' ); ?></span>
				<textarea class="widefat" rows="4" name="<?php echo esc_attr( $name ); ?>[about][quote]"
					><?php echo esc_textarea( (string) $about['quote'] ); ?></textarea>
			</label>
		</p>

		<p class="mudlet-front__field">
			<label>
				<span><?php esc_html_e( 'Attributed to', 'mudlet' ); ?></span>
				<input type="text" class="widefat" name="<?php echo esc_attr( $name ); ?>[about][cite]"
					value="<?php echo esc_attr( (string) $about['cite'] ); ?>">
			</label>
		</p>

		<p class="mudlet-front__field">
			<label>
				<span><?php esc_html_e( 'The line under it', 'mudlet' ); ?></span>
				<textarea class="widefat" rows="3" name="<?php echo esc_attr( $name ); ?>[about][note]"
					><?php echo esc_textarea( (string) $about['note'] ); ?></textarea>
			</label>
		</p>
	</div>
	<?php
}

/**
 * One card's fields.
 *
 * @param int|string           $index Row index, or the '__i__' placeholder.
 * @param array<string, mixed> $card  Card row.
 */
function mudlet_front_card_row( $index, array $card ): void {
	$name = MUDLET_FRONT_OPTION . '[cards][' . $index . ']';
	$art  = (string) ( $card['art'] ?? 'lua' );
	?>
	<div class="mudlet-front__panel">
		<?php
		/*
		 * The drag handle, down the left edge of the whole row. Handles go on
		 * the left of a vertical list - that is where wp-admin's own widget
		 * and menu screens put theirs.
		 */
		?>
		<span class="mudlet-front__grip dashicons dashicons-menu" aria-hidden="true"></span>

		<div class="mudlet-front__fields">
			<p class="mudlet-front__field">
				<label>
					<span><?php esc_html_e( 'Title', 'mudlet' ); ?></span>
					<input type="text" class="widefat" name="<?php echo esc_attr( $name ); ?>[title]"
						value="<?php echo esc_attr( (string) ( $card['title'] ?? '' ) ); ?>">
				</label>
			</p>

			<p class="mudlet-front__field">
				<label>
					<span><?php esc_html_e( 'Figure', 'mudlet' ); ?></span>
					<select class="widefat" name="<?php echo esc_attr( $name ); ?>[art]">
						<?php foreach ( mudlet_front_arts() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $art, $key ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<span class="mudlet-front__hint">
						<?php esc_html_e( 'Drawn by the theme. A new kind of figure has to be added in code — see inc/front-art.php.', 'mudlet' ); ?>
					</span>
				</label>
			</p>

			<p class="mudlet-front__field">
				<label>
					<span><?php esc_html_e( 'Sentence', 'mudlet' ); ?></span>
					<textarea class="widefat" rows="3" name="<?php echo esc_attr( $name ); ?>[body]"
						><?php echo esc_textarea( (string) ( $card['body'] ?? '' ) ); ?></textarea>
				</label>
			</p>
		</div>

		<?php
		/*
		 * Why these exist beside a drag handle: dragging cannot be done from a
		 * keyboard, so up and down are the accessible way to reorder rather
		 * than a duplicate of it. Core's nav-menu screen makes the same
		 * pairing. Dashicons and not "^ v x" through .button-link - that class
		 * draws a blue text link, which reads as a hyperlink rather than a
		 * control, and makes a destructive action look like navigation.
		 */
		?>
		<div class="mudlet-front__ops">
			<button type="button" class="mudlet-front__op mudlet-front__up" aria-label="<?php esc_attr_e( 'Move card up', 'mudlet' ); ?>">
				<span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span>
			</button>
			<button type="button" class="mudlet-front__op mudlet-front__down" aria-label="<?php esc_attr_e( 'Move card down', 'mudlet' ); ?>">
				<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
			</button>
			<button type="button" class="mudlet-front__op mudlet-front__remove" aria-label="<?php esc_attr_e( 'Remove card', 'mudlet' ); ?>">
				<span class="dashicons dashicons-trash" aria-hidden="true"></span>
			</button>
		</div>
	</div>
	<?php
}

add_action( 'save_post_page', 'mudlet_front_save', 10, 2 );
/**
 * Store what was typed.
 *
 * @param int     $post_id Page id.
 * @param WP_Post $post    Page.
 */
function mudlet_front_save( $post_id, $post ): void {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	// Absence is not emptiness: a save from Quick Edit, REST or a bulk edit
	// carries none of these fields, and must leave what is stored alone rather
	// than wiping the front page.
	if ( ! isset( $_POST[ MUDLET_FRONT_OPTION ], $_POST['mudlet_front_nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['mudlet_front_nonce'] ) ), MUDLET_FRONT_NONCE )
		|| ! current_user_can( 'edit_post', $post_id )
		|| ! mudlet_is_front_page_post( $post ) ) {
		return;
	}

	// mudlet_front_sanitize() is the only cleaner. What it is handed is raw
	// $_POST, unslashed first and untouched otherwise.
	$raw = wp_unslash( $_POST[ MUDLET_FRONT_OPTION ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

	update_option( MUDLET_FRONT_OPTION, mudlet_front_sanitize( $raw ) );
}
