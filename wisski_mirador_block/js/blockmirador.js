(function ($, Drupal, drupalSettings) {
  $(function() {

    const iiif_field = drupalSettings.blockmirador.iiif_field;

    const viewer_height = drupalSettings.blockmirador.viewer_height;
    
    const iiif_manifest = $('div.field--name-'+ iiif_field+'> div.field__item').text();
    

    if (iiif_manifest.length > 0){
      if (iiif_manifest.includes('manifest.json') ){
        const mirador = Mirador.viewer({
          id: "mirador_block",
          allowFullscreen: true,
          windows: [
              {manifestId: iiif_manifest}
          ],
          catalog: [
            { manifestId: iiif_manifest },
          ]
          // All of the settings (with descriptions (ﾉ^∇^)ﾉﾟ) located here:
          // https://github.com/ProjectMirador/mirador/blob/master/src/config/settings.js
        });
      };
      $('#mirador_block').height(viewer_height);
    } else {
      $("#mirador_block").hide();
    };

    
 });
})(jQuery, Drupal, drupalSettings);