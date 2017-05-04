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
