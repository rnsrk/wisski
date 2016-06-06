jQuery(document).ready(function() {
  var server = "/fcgi-bin/iipsrv.fcgi";
  var images = ["/srv/www/htdocs/dev/sites/default/files/de514d90b6b6862363818e117ebf7e59.tif"];
  var credit = '&copy; <a href="http://www.gnm.de/">Germanisches Nationalmuseum</a>';
  var iipmooviewer = new IIPMooViewer( "cboxContent", {
    image: images,
    server: server,
    credit: credit,
//    prefix: \'' . $base_path . drupal_get_path('module', 'wisski_iip') . '/iipmooviewer/images/\',
//    ' . $scale . '
    showNavWindow: true,
    showNavButtons: true,
    winResize: true,
    protocol: 'iip',
  });
  
  alert("Hallo welt!");
});