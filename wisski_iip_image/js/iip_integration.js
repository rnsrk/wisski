jQuery(document).bind('cbox_complete', function() {
  var server = "/fcgi-bin/iipsrv.fcgi";

  var images = [jQuery.colorbox.element().attr("iip")];

  var prefix = drupalSettings.path.baseUrl + '/libraries/iipmooviewer/images/';

//  var credit = '&copy; <a href="http://www.gnm.de/">Germanisches Nationalmuseum</a>';

  var iipmooviewer = new IIPMooViewer( "cboxLoadedContent", {
    image: images,
    server: server,
//    credit: credit,
    prefix: prefix,
//    ' . $scale . '
    showNavWindow: true,
    showNavButtons: true,
    winResize: true,
    protocol: 'iip',
  });

  jQuery.colorbox.resize({width: 1000, height: 600});
  
});



(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.iip_integration_Behavior = {
    attach: function (context, settings) {
//       alert("yay!");
//      $(context).find('input.iipIntegrationBehaviour').once('iipIntegrationBehaviour').each(function () {
      $(context).once('iipIntegrationBehaviour').each(function () {
//        alert("yay!1");

        var server = "/fcgi-bin/iipsrv.fcgi";

        var images = [$('.wisski-inline-iip').attr("iip")];

        if($('.wisski-inline-iip').attr('wisski-inline-iip')) {
          var prefix = drupalSettings.path.baseUrl + '/libraries/iipmooviewer/images/';

//  var credit = '&copy; <a href="http://www.gnm.de/">Germanisches Nationalmuseum</a>'

          var iipmooviewer = new IIPMooViewer( "wisski-iip-cont", {
            image: images,
            server: server,
//    credit: credit,
            prefix: prefix,
//    ' . $scale . '
            showNavWindow: true,
            showNavButtons: true,
            winResize: true,
            protocol: 'iip',
          });
        }
        
//        jQuery.colorbox.resize({width: 1000, height: 600});
      });

    }

  };
})(jQuery, Drupal, drupalSettings);
