(function (jq, Drupal, drupalSettings) {
  Drupal.behaviors.wisski_mirador_Behavior = {
    attach: function (context, settings) {
//      alert($.fn.jquery);
      jq('div#viewer', context).once('wisski_mirador').each(function () {
//        alert($.fn.jquery);
//        alert(jQuery19.fn.jquery);
//        (function($, jQuery) {
//          alert(jQuery.fn.jquery);

          console.log('yay', drupalSettings.wisski.mirador.data);          
        
          $(function() {
            jQuery = jQuery19;
            $ = jQuery19;          
//            alert(jQuery.fn.jquery);
            Mirador({
              id: "viewer",
              data:  drupalSettings.wisski.mirador.data
            });
          });
          jQuery.noConflict(true);
          alert(jQuery.fn.jquery);
          
//        })(jQuery19, jQuery19);
                
//        alert(jQuery.fn.jquery);
//        alert($.fn.jquery);
      });
    }
  };
})(jQuery, Drupal, drupalSettings);