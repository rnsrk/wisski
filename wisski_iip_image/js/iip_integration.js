jQuery(document).bind('cbox_complete', function() {
  var server = "/fcgi-bin/iipsrv.fcgi";
  var images = ["/srv/www/htdocs/dev/sites/default/files/de514d90b6b6862363818e117ebf7e59.tif"];

  var images = [jQuery.colorbox.element().context.href];
  
//  (function($, window, undefined) {  
//    console.log( jQuery.colorbox.element().context.href );
//  })(jQuery, window);
  var credit = '&copy; <a href="http://www.gnm.de/">Germanisches Nationalmuseum</a>';
  var iipmooviewer = new IIPMooViewer( "cboxLoadedContent", {
    image: images,
    server: server,
    credit: credit,
    prefix: '/dev/libraries/iipmooviewer/images/',
//    prefix: \'' . $base_path . drupal_get_path('module', 'wisski_iip') . '/iipmooviewer/images/\',
//    ' . $scale . '
    showNavWindow: true,
    showNavButtons: true,
    winResize: true,
    protocol: 'iip',
  });
  
});