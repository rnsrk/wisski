(function ($, Drupal, once) {
  'use strict';
  /**
   * Implements collapsing of individual pathbuilder rows using a caret
   */
  Drupal.behaviors.pathbuilderCollapse = {
    attach: function (context, settings) {
      once('pathbuilderCollapse', '#wisski-pathbuilder-edit-form', context).forEach(function (form) {
        // hack to detect active theme by checking if a '#block-' + theme + '-content' element exists
        const supportedThemes = ['seven', 'claro'];
        const activeTheme = supportedThemes.find(function(theme){
          return $('#block-' + theme + '-content').length > 0;
        });

        // don't do anything on unsupported themes!
        if (typeof activeTheme === 'undefined') return;

        const collapsedClass = 'wisski-pathbuilder-caret-collapsed';
        const expandedClass = 'wisski-pathbuilder-caret-expanded';
        $(form)
          .find('tr > td:first-child:has(label[data-pathbuilder-group="true"])')
          .addClass(expandedClass)
          .click(function () {
            const me = $(this);
            const isCollapsed = me
              .toggleClass([collapsedClass, expandedClass])
              .hasClass(collapsedClass);

            const row = me.closest('tr');
            const pathDepth = row.find('div.js-indentation').length;
            row.nextAll('tr').each(function () {
              const me = $(this);
              if (me.find('div.js-indentation').length <= pathDepth) {
                return false;
              }

              if (isCollapsed) {
                me.hide();
              } else {
                me.show();
              }
              return true;
            })
          })
          .click();
      });
    }
  };
})(jQuery, Drupal, once);
