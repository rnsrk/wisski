(function ($, Drupal, once) {
  'use strict';

  const CLASS_COLLAPSED = 'wisski-pathbuilder-caret-collapsed';
  const CLASS_EXPANDED = 'wisski-pathbuilder-caret-expanded';

  const SELECTOR_TR = 'tr.menu-enabled';
  const SELECTOR_ISGROUP = 'td:first-child:has(label[data-pathbuilder-group="true"])';

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

        const updateView = () => {
          let group = { 'depth': -1, 'collapse': false /*,'label': '(root)'*/ };
          const groups = [];

          $(form).find(SELECTOR_TR).each(function() {
            const tr = $(this);
            const depth = tr.find('div.js-indentation').length;

            // If we are suddenly in a smaller depth than before:
            if (depth < group.depth) {
              // Restore the nearest parent group:
              while (depth <= group.depth && groups.length > 0) {
                group = groups.pop();
              }
            }

            // Show or hide the current group.
            if (group.collapse || groups.reduce((x, y) => x.collapse || y.collapse, false)) {
              tr.hide();
            } else {
              tr.show();
            }

            // Check if this element is a group!
            const isGroup = tr.has(SELECTOR_ISGROUP).length > 0;
            if (!isGroup) {
              return;
            }

            // And if so add it to the list of groups!
            groups.push(group);

            group = {
              'depth': depth,
              'collapse': tr.find('td:first-child').hasClass(CLASS_COLLAPSED),
              // 'label': tr.find('label').first().text(),
            }
          });
        }

        // Find lal
        $(form).find(SELECTOR_TR).find(SELECTOR_ISGROUP)
          .addClass(CLASS_COLLAPSED)
          .click(function () {
            $(this).toggleClass([CLASS_COLLAPSED, CLASS_EXPANDED]);
            updateView();
          });

        updateView();
      });
    }
  };
})(jQuery, Drupal, once);
