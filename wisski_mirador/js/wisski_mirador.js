(function ($, Drupal) {
  Drupal.behaviors.wisski_mirador_Behavior = {
    attach: function (context, settings) {
      $('div#viewer', context).once('wisski_mirador').each(function () {
        //alert("Hallo welt!");
        //$.getScript( "/libraries/mirador/build/mirador/mirador.js" )
      });
    }
  };
})(jQuery, Drupal);