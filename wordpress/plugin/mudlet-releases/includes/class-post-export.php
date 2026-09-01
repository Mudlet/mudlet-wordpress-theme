<?php
/**
 * The export, where the post is written.
 *
 * A panel under the editor holding the post as Markdown, with a button to copy
 * it and one to save it as a .md. Nothing here converts anything - that is
 * class-markdown-export.php - and nothing here is stored: the panel is a view
 * of post_content, the way the record screen is a view of a release.
 *
 * It is drawn from the *saved* post, because that is the only version the
 * server has. Rather than pretend otherwise the panel says so and offers a
 * Refresh, which re-asks over REST - so the loop while writing an announcement
 * is save, refresh, copy, and never a page reload.
 *
 * On ordinary posts, deliberately. A mudlet_release record is read-only and has
 * no prose of its own; the announcement is a normal post, and so is anything
 * else somebody might want to hand to GitHub.
 *
 * @package Mudlet_Releases
 */

defined( 'ABSPATH' ) || exit;

/**
 * The Markdown panel and the endpoint behind its Refresh button.
 */
class Mudlet_Releases_Post_Export {

	/** REST namespace. */
	const REST_NAMESPACE = 'mudlet-releases/v1';

	/**
	 * Hook up.
	 */
	public static function init(): void {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_route' ) );
	}

	/**
	 * Add the panel.
	 */
	public static function add_meta_box(): void {
		foreach ( Mudlet_Releases_Post_Tag::post_types() as $type ) {
			add_meta_box(
				'mudlet-release-markdown',
				__( 'Markdown for GitHub', 'mudlet-releases' ),
				array( __CLASS__, 'render_meta_box' ),
				$type,
				'normal',
				'low'
			);
		}
	}

	/**
	 * Draw it.
	 *
	 * @param WP_Post $post Post being edited.
	 */
	public static function render_meta_box( WP_Post $post ): void {
		$markdown = Mudlet_Releases_Markdown_Export::post( $post );
		$filename = ( $post->post_name ? $post->post_name : 'post-' . $post->ID ) . '.md';

		$config = array(
			'endpoint' => rest_url( self::REST_NAMESPACE . '/markdown/' . $post->ID ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'filename' => $filename,
			'copied'   => __( 'Copied', 'mudlet-releases' ),
			'updated'  => __( 'Updated', 'mudlet-releases' ),
			'failed'   => __( 'Could not read the post', 'mudlet-releases' ),
		);
		?>
		<div class="mudlet-md" id="mudlet-md">
			<p class="mudlet-md__note">
				<?php
				esc_html_e(
					'The post as it would read on a GitHub release: what you wrote, and nothing that gets generated. The changelog, the contributors and the download table are left out — the release already carries its own.',
					'mudlet-releases'
				);
				?>
			</p>

			<textarea id="mudlet-md__text" class="mudlet-md__text" rows="16" readonly spellcheck="false"
				onfocus="this.select()"><?php echo esc_textarea( $markdown ); ?></textarea>

			<p class="mudlet-md__actions">
				<button type="button" class="button button-primary" id="mudlet-md__copy"><?php esc_html_e( 'Copy', 'mudlet-releases' ); ?></button>
				<button type="button" class="button" id="mudlet-md__download"><?php esc_html_e( 'Download .md', 'mudlet-releases' ); ?></button>
				<button type="button" class="button" id="mudlet-md__refresh"><?php esc_html_e( 'Refresh', 'mudlet-releases' ); ?></button>
				<span class="mudlet-md__status" id="mudlet-md__status" role="status"></span>
			</p>

			<p class="mudlet-md__sub">
				<?php esc_html_e( 'Taken from the last save. Save the post and hit Refresh to pick up edits.', 'mudlet-releases' ); ?>
			</p>
		</div>

		<style>
			.mudlet-md__note{margin:0 0 10px;padding:9px 11px;border-left:3px solid #72aee6;
				background:#f0f6fc;color:#50575e;max-width:60em}
			.mudlet-md__text{width:100%;font-family:Menlo,Consolas,monospace;font-size:12px;
				line-height:1.5;white-space:pre;overflow-wrap:normal;overflow-x:auto}
			.mudlet-md__actions{margin:10px 0 2px;display:flex;gap:8px;align-items:center}
			.mudlet-md__status{color:#1e7b34}
			.mudlet-md__sub{margin:4px 0 0;color:#646970}
		</style>

		<script>
		( function () {
			var cfg  = <?php echo wp_json_encode( $config ); ?>;
			var text = document.getElementById( 'mudlet-md__text' );
			var say  = document.getElementById( 'mudlet-md__status' );

			if ( ! text ) { return; }

			function status( message ) {
				say.textContent = message;
				window.setTimeout( function () { say.textContent = ''; }, 2500 );
			}

			document.getElementById( 'mudlet-md__copy' ).addEventListener( 'click', function () {
				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText( text.value ).then( function () {
						status( cfg.copied );
					} );
					return;
				}
				// No clipboard API off https: select and let execCommand try.
				text.focus();
				text.select();
				try { document.execCommand( 'copy' ); status( cfg.copied ); } catch ( e ) {}
			} );

			document.getElementById( 'mudlet-md__download' ).addEventListener( 'click', function () {
				var blob = new Blob( [ text.value ], { type: 'text/markdown;charset=utf-8' } );
				var link = document.createElement( 'a' );
				link.href = URL.createObjectURL( blob );
				link.download = cfg.filename;
				document.body.appendChild( link );
				link.click();
				document.body.removeChild( link );
				window.setTimeout( function () { URL.revokeObjectURL( link.href ); }, 1000 );
			} );

			document.getElementById( 'mudlet-md__refresh' ).addEventListener( 'click', function () {
				window.fetch( cfg.endpoint, {
					credentials: 'same-origin',
					headers: { 'X-WP-Nonce': cfg.nonce }
				} ).then( function ( response ) {
					return response.json();
				} ).then( function ( body ) {
					if ( body && typeof body.markdown === 'string' ) {
						text.value = body.markdown;
						status( cfg.updated );
					} else {
						status( cfg.failed );
					}
				} ).catch( function () {
					status( cfg.failed );
				} );
			} );
		}() );
		</script>
		<?php
	}

	/**
	 * The endpoint behind Refresh.
	 */
	public static function register_route(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/markdown/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'serve' ),
				'permission_callback' => static function ( WP_REST_Request $request ): bool {
					return current_user_can( 'edit_post', (int) $request['id'] );
				},
				'args'                => array(
					'id'    => array(
						'type'     => 'integer',
						'required' => true,
					),
					'link'  => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'title' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);
	}

	/**
	 * Answer with the post's Markdown.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function serve( WP_REST_Request $request ) {
		$post = get_post( (int) $request['id'] );

		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'mudlet_releases_no_post', __( 'No such post.', 'mudlet-releases' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response(
			array(
				'id'       => $post->ID,
				'markdown' => Mudlet_Releases_Markdown_Export::post(
					$post,
					array(
						'link'  => (bool) $request['link'],
						'title' => (bool) $request['title'],
					)
				),
			)
		);
	}
}
