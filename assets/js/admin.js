/**
 * Admin JS
 *
 * Disables stamping when editors are dirty
 *
 * @version 1.0
 * @package stampd-ext-wordpress
 * @author Hypermetron (Minas Antonios)
 * @copyright Copyright (c) 2018, Minas Antonios
 * @license http://opensource.org/licenses/gpl-2.0.php GPL v2 or later
 */

(function ($) {
  "use strict";

  var $doc = $(document);
  var $win = $(window);

  // Disable stamping when editors are dirty
  $doc.on('tinymce-editor-init', function (event, editor) {
    tinymce.editors[0].on('dirty', function (ed, e) {
      $win.trigger('stampd-ext-wp-editors-dirty', e);
    });
  });

  $win.keypress(function (e) {
    if (e.target) {
      if (e.target.classList.contains('wp-editor-area') && e.target.id === 'content') {
        $win.trigger('stampd-ext-wp-editors-dirty', e);
      }
    }
  });

  $win.on('stampd-ext-wp-editors-dirty', function (e) {
    var $stamp_btn = $('#stampd_ext_wp_stamp_btn');
    var $post_changed_text = $('.js-stampd-post-changed');
    var $post_settings = $('.js-stampd-post-settings');
    $stamp_btn.attr('disabled', true);
    $post_changed_text.show();
    $post_settings.hide();
  });

})(jQuery);