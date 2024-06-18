(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.wisski_mirador_Behavior = {
    attach: function (context, settings) {
      once('wisski_mirador_Behavior', 'div#viewer', context).forEach(function (element) {
        let plugins = [];
        if (window.miradorPlugins && window.miradorPlugins.length) {
          for (let { plugin, name } of window.miradorPlugins) {
            if (name == "annotations" && drupalSettings.wisski.mirador.options.enable_annotations == 0) {
              // in this case we do nothing - because then annotation is disabled!
            } else {
              plugins = [...plugins, ...plugin];
            }
          }
        }
        const annotationEndpoint = '/wisski/mirador-annotations';
        const mirador = Mirador.viewer({
          annotation: {
            adapter: (canvasId) => window.miradorAnnotationServerAdapter(canvasId, annotationEndpoint),
          },
          id: "viewer",
          allowFullscreen: true,
          "window": drupalSettings.wisski.mirador.window_settings,
          windows: drupalSettings.wisski.mirador.data,
        }, plugins);
      });

      const currentEid = document.URL.split('/')[5];
      storeInDrupalSession('mirador', {
        "options": drupalSettings.wisski.mirador.options,
        "context": {
          "currentEid": currentEid,
        }
      });
    }
  };

  function storeInDrupalSession(key, value) {
    const data = { key: key, value: value };
    $.ajax({
      url: '/wisski/mirador-annotations/session-store',
      type: 'POST',
      data: JSON.stringify(data),
      contentType: 'application/json',
      success: function (response) {
      },
      error: function () {
        console.log('Failed to store data in session.');
      }
    });
  }
})(jQuery, Drupal, drupalSettings);
