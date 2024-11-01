<?php

/*
Plugin Name: Stampd.io Blockchain Stamping
Description: A blockchain stamping plugin that helps protect your rights in your digital creations by posting a unique imprint of your posts on the blockchain.
Author: stampd.io
Version: 1.3.0
Author URI: https://stampd.io
*/

/**
 * Stampd.io Blockchain Stamping
 *
 * Enables blockchain stamping of post content
 *
 * @version 1.3.0
 * @package stampd-ext-wordpress
 * @author Stampd.io
 * @license http://opensource.org/licenses/gpl-2.0.php GPL v2 or later
 */
class StampdExtWordpress {

	// Statics
	private static $pluginVersion = '1.3.0';
	private static $pluginPrefix = 'stampd_ext_wp_';
//	private static $APIBaseURL = 'http://dev.stampd.io/api/v2';
	private static $APIBaseURL = 'https://stampd.io/api/v2';
	private static $blockchains = array(
		'BTC'  => 'Bitcoin',
		//'ETH'  => 'Ethereum',
		//'BCH'  => 'Bitcoin Cash',
		'DASH' => 'Dash',
		//'FCT'  => 'Factom',
	);
	private static $blockchainLinks = array(
		'BTC'  => 'https://blockchain.info/tx/[txid]',
		'ETH'  => 'https://etherscan.io/tx/[txid]',
		'BCH'  => 'https://blockdozer.com/insight/tx/[txid]',
		'DASH' => 'https://live.blockcypher.com/dash/tx/[txid]',
		'FCT'  => 'https://explorer.factom.com/chains/d75e9894cd4cb7370460e2b36d75ce25b74263eb0f9921f1571d332cdc6858fa/entries/[txid]',
	);
	private static $defaultPostSignature = '<hr><p><small>This post has been stamped on the [blockchain] blockchain via <a target="_blank" href="https://stampd.io">stampd.io</a> on [date]. Using the SHA256 hashing algorithm on the content of the post produced the following hash [hash]. The ID of the pertinent transaction is [txid]. <a target="_blank" href="[txlink]">View the transaction on a blockchain explorer.</a></small></p>';

	function __construct() {
		$this->_hookOptions();
		$this->_hookAdminNotices();
		$this->_loadAssets();
		$this->_hookAdminSettingsPage();
		$this->_hookMetaboxes();
		$this->_addPluginSettingsLink();
		$this->_hookPostContentFilter();
	}

	/*
	 * Filter post content
	 */
	private function _hookPostContentFilter() {
		if ( get_option( $this::$pluginPrefix . 'enable_post_signature' ) ) {
			add_filter( 'the_content', array( $this, 'addContentSignature' ) );
		}
	}

	/*
	 * Add post signature
	 */
	function addContentSignature( $content ) {

		global $post;

		$post_meta = $this->getPostStampdMeta( $post->ID );

		if ( $post_meta && isset( $post_meta['stamped'] ) && isset( $post_meta['hash'] ) ) {

			$hashed_content = hash( 'sha256', $post->post_content );

			if ( $hashed_content === $post_meta['hash'] && $post_meta['show_sig'] ) {
				// is valid stamp
				$post_sig      = get_option( $this::$pluginPrefix . 'signature_text' );
				$formulate_sig = str_replace( array(
					'[hash]',
					'[date]',
					'[blockchain]',
					'[txid]',
					'[txlink]',
				), array(
					$post_meta['hash'],
					$post_meta['date'],
					$this->blockchainToReadable( $post_meta['blockchain'] ),
					$post_meta['txid'],
					$post_meta['link'],
				), $post_sig );

				$content = $content . $formulate_sig;
			}
		}

		return $content;
	}

	/*
	 * Hook admin notices
	 */
	private function _hookAdminNotices() {
		if ( is_admin() ) {
			add_action( 'admin_notices', array( $this, 'renderAdminNotices' ) );
		}
	}

	/*
	 * Render admin notices
	 */
	function renderAdminNotices() {
		$trans_slug = $this::$pluginPrefix . '_notices';
		if ( $notice = get_transient( $trans_slug ) ) { ?>
        <div class="notice notice-<?php echo $notice['type']; ?> is-dismissible">
            <p><?php echo $notice['message']; ?></p>
            <button type="button" class="notice-dismiss"><span
                        class="screen-reader-text"><?php _e( 'Dismiss this notice.' ); ?></span></button>
            </div><?php

			delete_transient( $trans_slug );
		}
	}

	/*
	 * Add plugin settings link
	 */
	private function _addPluginSettingsLink() {
		$plugin = plugin_basename( __FILE__ );
		add_filter( 'plugin_action_links_' . $plugin, array( $this, 'pluginSettingsLinkFilter' ) );
	}

	/*
	 * Display plugin settings link
	 */
	function pluginSettingsLinkFilter( $links ) {
		$settings_link = '<a href="options-general.php?page=' . $this::$pluginPrefix . 'plugin_options">' . __( 'Settings' ) . '</a>';
		array_push( $links, $settings_link );

		return $links;
	}

	/*
	 * Hook options
	 */
	private function _hookOptions() {
		add_action( 'admin_init', array( $this, 'loadOptions' ) );
	}

	/*
	 * Load options
	 */
	function loadOptions() {

		$admin_page_slug = $this::$pluginPrefix . 'plugin_options';

		$general_settings_section_slug = $this::$pluginPrefix . 'general_settings';

		// add sections
		add_settings_section( $general_settings_section_slug, __( 'API Credentials', 'stampd' ), array(
			$this,
			'renderAPICredentialsOptionHeader'
		), $admin_page_slug );

		// add inputs
		$input_slug = $this::$pluginPrefix . 'client_id';
		add_settings_field( $input_slug, __( 'Client ID', 'stampd' ), array(
			$this,
			'renderClientIDInput'
		), $admin_page_slug, $general_settings_section_slug );
		register_setting( $general_settings_section_slug, $input_slug );

		$input_slug = $this::$pluginPrefix . 'secret_key';
		add_settings_field( $input_slug, __( 'Secret Key', 'stampd' ), array(
			$this,
			'renderSecretKeyInput'
		), $admin_page_slug, $general_settings_section_slug );
		register_setting( $general_settings_section_slug, $input_slug, array( $this, 'sanitizeSecretKey' ) );

		add_settings_section( $general_settings_section_slug, __( 'General Settings', 'stampd' ), array(
			$this,
			'renderGeneralSettingsOptionHeader'
		), $admin_page_slug );

		$input_slug = $this::$pluginPrefix . 'blockchain';
		add_settings_field( $input_slug, __( 'Blockchain', 'stampd' ), array(
			$this,
			'renderBlockchainSelect'
		), $admin_page_slug, $general_settings_section_slug );
		register_setting( $general_settings_section_slug, $input_slug );

		$input_slug = $this::$pluginPrefix . 'enable_post_signature';
		add_settings_field( $input_slug, __( 'Post Signature', 'stampd' ), array(
			$this,
			'renderPostSignatureCheckbox'
		), $admin_page_slug, $general_settings_section_slug );
		register_setting( $general_settings_section_slug, $input_slug );

		$input_slug = $this::$pluginPrefix . 'signature_text';
		add_settings_field( $input_slug, __( 'Signature Text', 'stampd' ), array(
			$this,
			'renderSignatureTextTextarea'
		), $admin_page_slug, $general_settings_section_slug );
		register_setting( $general_settings_section_slug, $input_slug );
	}

	/*
	 * Sanitize secret key
	 *
	 * @param $value string
	 */
	function sanitizeSecretKey( $value ) {

		$client_id  = get_option( $this::$pluginPrefix . 'client_id' );
		$secret_key = $value;

		$init       = $this->_APIInitCall( $client_id, $secret_key );
		$valid_init = $this->_isValidLogin( $init );

		if ( ! $valid_init ) {
			$input_slug = $this::$pluginPrefix . 'secret_key';
			add_settings_error(
				$input_slug,
				esc_attr( 'settings_updated' ),
				__( 'Invalid credentials', 'stampd' ),
				'error'
			);
		}

		return $value;
	}

	/*
	 * Post sig textbox
	 */
	function renderSignatureTextTextarea() {
		$slug = $this::$pluginPrefix . 'signature_text';
		?>
        <textarea name="<?php echo $slug; ?>" id="<?php echo $slug; ?>" class="large-text code"
                  rows="5"><?php echo get_option( $slug, $this::$defaultPostSignature ); ?></textarea>
        <p class="description">
			<?php _e( 'These shortcodes will be replaced with actual values when used within the signature text field:', 'stampd' ); ?>
        </p>
        <ul>
            <li><strong>[hash]</strong> <?php _e( 'hash result from applying SHA256 to the content', 'stampd' ); ?></li>
            <li><strong>[date]</strong> <?php _e( 'stamping date', 'stampd' ); ?></li>
            <li><strong>[blockchain]</strong> <?php _e( 'selected blockchain', 'stampd' ); ?></li>
            <li><strong>[txid]</strong> <?php _e( 'transaction ID', 'stampd' ); ?></li>
            <li><strong>[txlink]</strong> <?php _e( 'link to the transaction on a blockchain explorer', 'stampd' ); ?>
            </li>
        </ul>
		<?php
	}

	/*
	 * Post sig checkbox
	 */
	function renderPostSignatureCheckbox() {
		$slug = $this::$pluginPrefix . 'enable_post_signature';
		?>
        <label for="<?php echo $slug; ?>">
            <input name="<?php echo $slug; ?>" type="checkbox" id="<?php echo $slug; ?>"
                   value="enable" <?php echo get_option( $slug ) !== '' ? 'checked' : ''; ?>>
			<?php _e( 'The following signature will be appended to every stamped post', 'stampd' ); ?>
        </label>
		<?php
	}

	/*
	 * Client ID input
	 */
	function renderClientIDInput() {
		$slug = $this::$pluginPrefix . 'client_id';
		?>
        <input autocomplete="false" type="text" name="<?php echo $slug; ?>"
               id="<?php echo $slug; ?>"
               value="<?php echo get_option( $slug ); ?>"/>
		<?php
	}

	/*
     * Secret key input
     */
	function renderSecretKeyInput() {
		$slug = $this::$pluginPrefix . 'secret_key';
		?>
        <input autocomplete="new-password" type="password" name="<?php echo $slug; ?>"
               id="<?php echo $slug; ?>"
               value="<?php echo get_option( $slug ); ?>"/>
		<?php
	}

	/*
     * Blockchain select
     */
	function renderBlockchainSelect() {
		$client_id_input_slug = $this::$pluginPrefix . 'blockchain';
		?>
        <select name="<?php echo $client_id_input_slug; ?>" id="<?php echo $client_id_input_slug; ?>">
			<?php
			foreach ( $this::$blockchains as $blockchain_id => $blockchain_name ) {
				?>
                <option value="<?php echo $blockchain_id; ?>" <?php selected( get_option( $client_id_input_slug ), $blockchain_id ); ?>><?php echo $blockchain_name; ?></option>
				<?php
			}
			?>
        </select>
		<?php
	}

	/*
	 * API creds opts header
	 */
	function renderAPICredentialsOptionHeader() {
		echo __( 'Get your client ID and secret key on <a href="https://stampd.io" target="_blank">stampd.io</a>.', 'stampd' );
	}

	/*
	 * General settings opts header
	 */
	function renderGeneralSettingsOptionHeader() {
		echo __( 'General plugin settings', 'stampd' );
	}

	/*
	 * Hook admin settings page
	 */
	private function _hookAdminSettingsPage() {
		add_action( 'admin_menu', array( $this, 'addAdminMenuItems' ) );
	}

	/*
	 * Add admin menu items
	 */
	function addAdminMenuItems() {
		add_submenu_page(
			'options-general.php',
			'Stampd.io',
			'Stampd.io',
			'manage_options',
			$this::$pluginPrefix . 'plugin_options',
			array(
				$this,
				'displayAdminSettingsPage'
			),
			"",
			100
		);
	}

	/*
	 * Display admin settings page
	 */
	function displayAdminSettingsPage() {
		require_once 'templates/admin-settings-page.php';
	}

	/*
	 * Hook metaboxes to actions
	 */
	private function _hookMetaboxes() {
		if ( is_admin() ) {
			add_action( 'load-post.php', array( $this, 'hookPostMetabox' ) );
			add_action( 'load-post-new.php', array( $this, 'hookPostMetabox' ) );
		}
	}

	/*
	 * Hook post metabox
	 */
	function hookPostMetabox() {
		add_action( 'add_meta_boxes', array( $this, 'addPostMetabox' ) );
		add_action( 'save_post', array( $this, 'savePostMetabox' ), 9999, 2 );
	}

	/*
	 * Save post //  $this::$pluginPrefix . 'post_metabox'
	 */
	public function savePostMetabox( $post_id ) {

		$in_ajax_mode = ! $post_id;

		if ( $in_ajax_mode ) {
			$post_id = $_REQUEST['post_id'];
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			if ( $in_ajax_mode ) {
				die( json_encode( array(
					'error'   => true,
					'type'    => 1,
					'message' => 'Please save your post before stamping',
				) ) );
			}
			return $post_id;
		}

		if ( ! isset( $_POST[ $this::$pluginPrefix . 'nonce' ] ) ) {
			if ( $in_ajax_mode ) {
				die( json_encode( array(
					'error'   => true,
					'type'    => 2,
					'message' => 'Error in stamping, please try again',
				) ) );
			}

			return $post_id;
		}

		if ( isset( $_POST[ $this::$pluginPrefix . 'update_post_meta' ] ) ) {
			$stampd_post_meta         = $this->getPostStampdMeta( $post_id );
			$initial_stampd_post_meta = $stampd_post_meta;

			if ( isset( $stampd_post_meta['stamped'] ) && $stampd_post_meta['stamped'] === true ) {
				// show sig
				$stampd_post_meta['show_sig'] = ! isset( $_POST[ $this::$pluginPrefix . 'hide_signature' ] );
			}

			// changed, save
			if ( $initial_stampd_post_meta !== $stampd_post_meta ) {
				$this->savePostStampdMeta( $post_id, $stampd_post_meta );
			}
		}

		if ( ! $in_ajax_mode && ! isset( $_POST[ $this::$pluginPrefix . 'stamp_btn' ] ) ) {
			return $post_id;
		}

		if ( ! wp_verify_nonce( $_POST[ $this::$pluginPrefix . 'nonce' ], $this::$pluginPrefix . 'post_metabox' ) ) {
			if ( $in_ajax_mode ) {
				die( json_encode( array(
					'error'   => true,
					'type'    => 4,
					'message' => 'Error in stamping, please try again',
				) ) );
			}
			return $post_id;
		}

		if ( ! current_user_can( 'edit_page', $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			if ( $in_ajax_mode ) {
				die( json_encode( array(
					'error'   => true,
					'type'    => 5,
					'message' => 'You lack edit post permissions, stamping cancelled',
				) ) );
			}
			return $post_id;
		}

		if ( ! $in_ajax_mode ) {
			global $post;
		} else {
			$post = get_post( $post_id );
		}

		$client_id  = get_option( $this::$pluginPrefix . 'client_id' );
		$secret_key = get_option( $this::$pluginPrefix . 'secret_key' );
		$blockchain = get_option( $this::$pluginPrefix . 'blockchain' );
		$hash       = hash( 'sha256', $post->post_content );

		$init       = $this->_APIInitCall( $client_id, $secret_key );
		$valid_init = $this->_isValidLogin( $init );

		if ( ! $valid_init ) {
			$this->_addNotice( __( 'Invalid credentials', 'stampd' ), 'error' );

			if ( $in_ajax_mode ) {
				die( json_encode( array(
					'error'   => true,
					'type'    => 6,
					'message' => 'Invalid stampd API credentials',
				) ) );
			}
			return $post_id;
		}

		$session_id = $init->session_id;

		// Post hash
		$fields = array(
//			'requestedURL'  => '/hash',
//			'force_method'  => 'POST', // method can also be forced via a parameter
			'sess_id'       => $session_id, // old param name: session_id
			'blockchain'    => $blockchain,
			'hash'          => $hash,
//    		'meta_emails'   => $email,
//    		'meta_notes'    => $notes,
//    		'meta_filename' => $filename,
			'meta_category' => 'WordPress',
		);

		$post_response = $this->_performPostCall( $this::$APIBaseURL . '/hash', $fields )['json'];

		if ( is_object( $post_response ) && property_exists( $post_response, 'code' ) ) {
			$code = $post_response->code;

			if ( $code === 106 ) {
				$this->_addNotice( __( 'You have run out of stamps. Please visit <a href="https://stampd.io">stampd.io</a> to get more.', 'stampd' ), 'error' );

				if ( $in_ajax_mode ) {
					die( json_encode( array(
						'error'   => true,
						'type'    => 106,
						'message' => 'You have run out stamps. Please visit https://stampd.io to get more.',
					) ) );
				}
				return $post_id;
			} else if ( $code === 202 ) {
				$this->_addNotice( __( 'This post has already been stamped.', 'stampd' ), 'info' );

				if ( $in_ajax_mode ) {
					die( json_encode( array(
						'error'   => true,
						'type'    => 202,
						'message' => 'This post is already stamped',
					) ) );
				}
				return $post_id;
			} else if ( $code === 301 ) {
				// success

				$link = str_replace( '[txid]', $post_response->txid, self::$blockchainLinks[ $blockchain ] );

				$this->savePostStampdMeta( $post_id, array(
					'stamped'    => true,
					'blockchain' => $blockchain,
					'link'       => $link,
					'date'       => date( 'Y-m-d', current_time( 'timestamp', 0 ) ),
					'hash'       => $hash,
					'txid'       => $post_response->txid,
					'show_sig'   => true, // default
				) );

				$this->_addNotice( __( 'Post stamped successfully. You have ', 'stampd' ) . $post_response->stamps_remaining . __( ' stamps remaining.', 'stampd' ) );

				if ( $in_ajax_mode ) {
					die( json_encode( array(
						'error'   => false,
						'type'    => 301,
						'message' => 'Post stamped successfully. You have' . $post_response->stamps_remaining . ' stamps remaining.',
					) ) );
				}
				return $post_id;
			}

		} else {
			$this->_addNotice( __( 'There was an error with your stamping. Please try again.', 'stampd' ), 'error' );

			if ( $in_ajax_mode ) {
				die( json_encode( array(
					'error'   => true,
					'type'    => 999,
					'message' => 'There was an error with your stamping. Please try again.',
					'data'    => $this->_performPostCall( $this::$APIBaseURL . '/hash', $fields ),
				) ) );
			}
		}

		if ( $in_ajax_mode ) {
			die( json_encode( array(
				'error'   => true,
				'type'    => 0,
				'message' => 'There was an error with your stamping. Please try again.',
			) ) );
		}
		return $post_id;
	}

	/*
	 * Save post stampd meta
	 *
	 * @param $post_id string
	 * @return mixed
	 */
	function savePostStampdMeta( $post_id, $meta ) {
		return update_post_meta( $post_id, $this::$pluginPrefix . 'stampd_meta', $meta );
	}

	/*
	 * Get post stampd meta
	 *
	 * @param $post_id string
	 * @return mixed
	 */
	function getPostStampdMeta( $post_id ) {
		return get_post_meta( $post_id, $this::$pluginPrefix . 'stampd_meta', true );
	}

	/*
	 * Return blockchain in human readable form
	 *
	 * @param $blockchain string
	 * @return string
	 */
	function blockchainToReadable( $blockchain ) {
		return isset( self::$blockchains[ $blockchain ] ) ? self::$blockchains[ $blockchain ] : null;
	}

	/*
	 * Add notice
	 *
	 * @param $message string
	 * @param $type string [error|warning|info|success]
	 */
	private function _addNotice( $message, $type = 'success' ) {
		set_transient( $this::$pluginPrefix . '_notices', array(
			'message' => $message,
			'type'    => $type
		), 45 );
	}

	/*
	 * Add post metabox
	 */
	function addPostMetabox() {
		add_meta_box(
			$this::$pluginPrefix . 'post_metabox',
			__( 'Stampd.io Blockchain Stamping', 'stampd' ),
			array( $this, 'renderPostMetabox' ),
			array( 'post', 'page' ),
			'side',
			'default'
		);
	}

	function renderPostMetabox() {
		require_once 'templates/post-metabox.php';
	}

	/*
	 * Load all assets CSS, JS
	 */
	private function _loadAssets() {
//		add_action( 'wp_enqueue_scripts', array( $this, 'loadFrontCSS' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'loadAdminCSS' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'loadAdminJS' ) );
	}

//	/*
//	 * Load front CSS
//	 * @note can't be private because it is hooked
//	 */
//	function loadFrontCSS() {
//		$style_name = $this::$pluginPrefix . 'front_css';
//		wp_register_style( $style_name, plugins_url( '/assets/css/front.min.css', __FILE__ ), false, $this::$pluginVersion );
//		wp_enqueue_style( $style_name );
//	}

	/*
	 * Load admin CSS
	 * @note can't be private because it is hooked
	 */
	function loadAdminCSS() {
		$style_name = $this::$pluginPrefix . 'admin_css';
		wp_register_style( $style_name, plugins_url( '/assets/css/admin.min.css', __FILE__ ), false, $this::$pluginVersion );
		wp_enqueue_style( $style_name );
	}

	/*
	 * Load admin JS
	 * @note can't be private because it is hooked
	 */
	function loadAdminJS() {
		$script_name = $this::$pluginPrefix . 'admin_js';
		wp_enqueue_script( $script_name, plugins_url( '/assets/js/admin.min.js', __FILE__ ), array( 'jquery' ), $this::$pluginVersion );
	}

	/*
	 * Perform post call
	 *
	 * @param $url string
	 * @param $fields array of strings
	 * @return JSON, false
	 */
	private function _performPostCall( $url, $fields = array() ) {
		$fields_string = '';

		foreach ( $fields as $key => $value ) {
			$fields_string .= $key . '=' . $value . '&';
		}
		$fields_string = rtrim( $fields_string, '&' );

		try {

			$ch = curl_init();

			if ( $ch === false ) {
				throw new Exception( 'Failed to initialize curl' );
			}

			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_POST, count( $fields ) );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $fields_string );

			$res = curl_exec( $ch );

			if ( $res === false ) {
				throw new Exception( curl_error( $ch ), curl_errno( $ch ) );
			}

			curl_close( $ch );

		} catch ( Exception $e ) {

			return array(
				'json'   => false,
				'raw'    => $res,
				'error'  => $e->getMessage(),
				'code'   => $e->getCode(),
				'fields' => $fields_string,
				'url'    => $url,
			);

		}


		return array(
			'json'   => json_decode( $res ),
			'raw'    => $res,
			'error'  => curl_error( $ch ),
			'fields' => $fields_string,
			'url'    => $url,
		);
	}

	/*
     * Perform get call
     *
     * @param $url string
     * @return JSON, false
     */
	private function _performGetCall( $url ) {
		$res = file_get_contents( $url );

		return json_decode( $res );
	}

	/*
	 * API /init
	 *
	 * @param $client_id string
	 * @param $secret_key string
	 * @return _performGetCall
	 */
	private function _APIInitCall( $client_id, $secret_key ) {
		$url = $this::$APIBaseURL . '/init?client_id=' . $client_id . '&secret_key=' . $secret_key;

		return $this->_performGetCall( $url );
	}

	/*
	 * Check init validity
	 *
	 * @return boolean
	 */
	private function _isValidLogin( $init_res ) {
		if ( $init_res && is_object( $init_res ) && property_exists( $init_res, 'code' ) && ( in_array( $init_res->code, array(
				200, // already logged
				300 // success login
			) ) )
		) {
			return true;
		} else {
			return false;
		}
	}
}

// Init the plugin
global $_StampdExtWordpress;
$_StampdExtWordpress = new StampdExtWordpress();

add_action( 'wp_ajax_stampd_perform_stamping', array( $_StampdExtWordpress, 'savePostMetabox' ) );
add_action( 'wp_ajax_nopriv_stampd_perform_stamping', array( $_StampdExtWordpress, 'savePostMetabox' ) );
