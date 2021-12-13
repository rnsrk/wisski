(function ($, Drupal, drupalSettings, once) {
  Drupal.behaviors.wisski_mirador_Behavior = {
    attach: function (context, settings) {
//      alert($.fn.jquery);
      $('div#viewer', context).once('wisski_mirador').each(function () {
//        once('wisski_mirador_Behavior', 'html', context).forEach( function () {
//        alert($.fn.jquery);
//        alert(jQuery19.fn.jquery);
//        (function($, jQuery) {
//          alert(jQuery.fn.jquery);

          console.log('yay', drupalSettings.wisski.mirador.data);          
          console.log('yay', drupalSettings.wisski.mirador.windowObjects);
        
//          $(function() {
//            jQuery = jQuery19;
//            $ = jQuery19;          
//            alert(jQuery.fn.jquery);
        const mirador = Mirador.viewer({
          id: "viewer",
          allowFullscreen: true,
          windows: drupalSettings.wisski.mirador.data, //[
            //{ manifestId: "https://wisskid9.gnm.de/wisski/navigate/426/iiif_manifest" },
            //{ manifestId: "https://wisskid9.gnm.de/wisski/navigate/269/iiif_manifest" },
            


//              drupalSettings.wisski.mirador.data
              //{manifestId: iiif_manifest}
//          ],
 //         catalog: [
//            { manifestId: "https://wisskid9.gnm.de/wisski/navigate/426/iiif_manifest" }
//
//            drupalSettings.wisski.mirador.data
//          ]
          // All of the settings (with descriptions (ﾉ^∇^)ﾉﾟ) located here:
          // https://github.com/ProjectMirador/mirador/blob/master/src/config/settings.js
        });

/*
            Mirador({
              id: "viewer",
              buildPath: "/libraries/mirador/",
              layout: drupalSettings.wisski.mirador.layout,
              data:  drupalSettings.wisski.mirador.data,
              "windowObjects" : drupalSettings.wisski.mirador.windowObjects
            });
            */
//          });
//          jQuery.noConflict(true);
//          alert(jQuery.fn.jquery);
          
//        })(jQuery19, jQuery19);
                
//        alert(jQuery.fn.jquery);
//        alert($.fn.jquery);
      });
    }
  };
})(jQuery, Drupal, drupalSettings, once);