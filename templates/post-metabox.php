<?php

/**
 * Post metabox form
 *
 * @version 1.3.0
 * @package stampd-ext-wordpress
 * @author Hypermetron (Minas Antonios)
 * @copyright Copyright (c) 2018, Minas Antonios
 * @license http://opensource.org/licenses/gpl-2.0.php GPL v2 or later
 */

global $post;
global $_StampdExtWordpress;

wp_nonce_field( 'stampd_ext_wp_post_metabox', 'stampd_ext_wp_nonce' );

//var_dump($post->post_content);
//die();

$hashed_content   = hash( 'sha256', $post->post_content );
$stamp_active     = false;
$post_sig_enabled = get_option( 'stampd_ext_wp_enable_post_signature' );

$post_meta = $_StampdExtWordpress->getPostStampdMeta( $post->ID );

// $post_meta['stamped']
// $post_meta['blockchain']
// $post_meta['link']
// $post_meta['date']
// $post_meta['hash']
// $post_meta['txid']
// $post_meta['show_sig']

?>
<div class="inside inside--actual">
	<?php
	if ( ! is_array( $post_meta ) || ! $post_meta || ! isset( $post_meta['stamped'] ) ) {
		// not stamped
		?>
        <label for="stampd_ext_wp_hash"><?php _e( 'SHA256 hash derived from last save', 'stampd' ); ?></label>
        <input id="stampd_ext_wp_hash" class="full-width" type="text" autocomplete="off"
               placeholder="<?php _e( 'Hash not calculated yet', 'stampd' ); ?>"
               name="stampd_ext_wp_hash" value="<?php echo $hashed_content; ?>" readonly>
		<?php
	} else if ( $post_meta['stamped'] === true ) {
		// stamped
		if ( $hashed_content === $post_meta['hash'] ) {
			// current revision is stamped
			$stamp_active = true;
			?>
            <p>
                <span class="dashicons dashicons-lock"></span><?php _e( 'This post has been stamped on the blockchain via <a href="https://stampd.io" target="_blank">stampd.io</a>.', 'stampd' ); ?>
            </p>
            <label for="stampd_ext_wp_hash"><?php _e( 'SHA256 hash', 'stampd' ); ?></label>
            <input id="stampd_ext_wp_hash" class="full-width" type="text" autocomplete="off"
                   placeholder="<?php _e( 'Hash not calculated yet', 'stampd' ); ?>"
                   name="stampd_ext_wp_hash" value="<?php echo $hashed_content; ?>" readonly>
            <label for="stampd_ext_wp_txid"><?php _e( 'Transaction ID', 'stampd' ); ?>
                <small><a class="float-right"
                          target="_blank"
                          href="<?php echo $post_meta['link']; ?>"><?php _e( 'View transaction', 'stampd' ); ?></a>
                </small>
            </label>
            <input id="stampd_ext_wp_txid" class="full-width" type="text" autocomplete="off"
                   name="stampd_ext_wp_txid" value="<?php echo $post_meta['txid']; ?>" readonly>
            <label for="stampd_ext_wp_date"><?php _e( 'Blockchain', 'stampd' ); ?></label>
            <input id="stampd_ext_wp_date" class="full-width" type="text" autocomplete="off"
                   name="stampd_ext_wp_date"
                   value="<?php echo $_StampdExtWordpress->blockchainToReadable( $post_meta['blockchain'] ); ?>"
                   readonly>
            <label for="stampd_ext_wp_date"><?php _e( 'Date', 'stampd' ); ?></label>
            <input id="stampd_ext_wp_date" class="full-width" type="text" autocomplete="off"
                   name="stampd_ext_wp_date" value="<?php echo $post_meta['date']; ?>" readonly>
			<?php

			if ( $post_sig_enabled ) {
				$slug = 'stampd_ext_wp_hide_signature';
				?>
                <input type="hidden" name="stampd_ext_wp_update_post_meta" value="true">
                <label for="<?php echo $slug; ?>">
                    <input name="<?php echo $slug; ?>" type="checkbox" id="<?php echo $slug; ?>"
                           value="enable" <?php echo ! $post_meta['show_sig'] ? 'checked' : ''; ?>>
					<?php _e( 'Hide the post signature from this post', 'stampd' ); ?>
                </label>
				<?php
			}
		} else {
			// other revision is stamped
			?>
            <label for="stampd_ext_wp_hash"><?php _e( 'SHA256 hash derived from last save', 'stampd' ); ?></label>
            <input id="stampd_ext_wp_hash" class="full-width" type="text" autocomplete="off"
                   placeholder="<?php _e( 'Hash not calculated yet', 'stampd' ); ?>"
                   name="stampd_ext_wp_hash" value="<?php echo $hashed_content; ?>" readonly>
            <p class="description"><?php _e( 'An older revision of this post is stamped on the blockchain. Return to the previous revision or stamp the new hash.', 'stampd' ); ?></p>
			<?php
		}
	}
	?>

</div>
<?php
if ( ! $stamp_active ) {
	?>
    <div id="major-publishing-actions">

        <div id="delete-action">
            <div class="js-stampd-post-changed" style="display:none;">
				<?php _e( 'Please <strong>update</strong> the post', 'stampd' ); ?>
            </div>
            <div class="js-stampd-post-settings">
                <a href="options-general.php?page=stampd_ext_wp_plugin_options">Settings</a>
            </div>
        </div>

        <div id="publishing-action">
            <input name="stampd_ext_wp_stamp_btn" type="submit" class="button button-primary button-large"
                   id="stampd_ext_wp_stamp_btn" value="<?php _e( 'Stamp', 'stampd' ); ?>">
        </div>
        <div class="clear"></div>
    </div>
	<?php
}

$current_screen = get_current_screen();
if ( method_exists( $current_screen, 'is_block_editor' ) &&
     $current_screen->is_block_editor()
) {
	?>
    <script type="text/javascript">
	
	if (!$) var $ = jQuery;

    var stampdSavingInterval;
    var stampdAJAXURL = '<?php echo admin_url( 'admin-ajax.php' ); ?>';

    var stampdCreateNotice = function (type, message) {
      wp.data.dispatch('core/notices').createNotice(
        type, // Can be one of: success, info, warning, error.
        message,
        {
          isDismissible: true,
        }
      );
    };

    var stampdUpdateMetabox = function (cb) {
      $.get(window.location, function (data) {
        var updatedPage = $(data);
        var stampdMetaboxCurrent = $('#stampd_ext_wp_post_metabox');
        var stampdMetaboxUpdated = updatedPage.find('#stampd_ext_wp_post_metabox');
        stampdMetaboxCurrent.html(stampdMetaboxUpdated.html());
        if (cb) cb();
      });
    };

    jQuery(document).ready(function ($) {
      if (wp && wp.data) {

        var $body = $('body');

        $body.on('click', '#stampd_ext_wp_stamp_btn', function (e) {

          e.preventDefault();
          e.stopPropagation();

          var $this = $(this);
          var $form = $this.parents('form');
          var originalContent = wp.data.select("core/editor").getCurrentPost().content;
          var editedContent = wp.data.select("core/editor").getEditedPostContent();

          $this.prop('disabled', true);

          if (originalContent !== editedContent) {
            stampdCreateNotice('info', 'Please save your post before stamping');
            $this.prop('disabled', false);
            return;
          }

          var data = {
            action: 'stampd_perform_stamping',
            post_id: <?php echo get_the_ID(); ?>,
          };

          var formData = $form.serializeArray();
          formData.map(function (datum) {
            data[datum.name] = datum.value;
          });

          console.log(stampdAJAXURL, data);

          $.post(stampdAJAXURL, data)
            .done(function (res) {

              console.log(res);

              var jsonRes = JSON.parse(res);

              if (jsonRes.error) {
                stampdCreateNotice('warning', jsonRes.data && jsonRes.data.error ? jsonRes.data.error : jsonRes.message);
              } else {
                stampdUpdateMetabox(function () {
                  stampdCreateNotice('success', 'Your post has been stamped.');
                });
              }

              $this.prop('disabled', false);
            });
        });

        wp.data.subscribe(function () {
          var isSavingPost = wp.data.select('core/editor').isSavingPost();

          if (isSavingPost) {
            window.clearInterval(stampdSavingInterval);
            stampdSavingInterval = window.setInterval(function () {
              var didPostSaveRequestSucceed = wp.data.select('core/editor').didPostSaveRequestSucceed();
              if (didPostSaveRequestSucceed) {
                window.clearInterval(stampdSavingInterval);
                var stampdMetaboxCurrent = $('#stampd_ext_wp_post_metabox');
                stampdMetaboxCurrent.find('input[type="button"]').prop('disabled', true);
                stampdUpdateMetabox();
              }
            }, 500)
          }
        })

      }

    });
</script>
	<?php
}
?>

