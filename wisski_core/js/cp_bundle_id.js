(function ($, Drupal, once) {
  'use strict';
  /**
   * Implements collapsing of individual pathbuilder rows using a caret
   */
  Drupal.behaviors.cpBundlesId = {
    attach: function (context, settings) {
      once('cpBundlesId', 'html', context).forEach(function (form) {
        $('.menu-label').click(function () {
          let title = $(this).attr('title');
          navigator.clipboard.writeText(title)
        });
      });
    }
  };
})(jQuery, Drupal, once);
