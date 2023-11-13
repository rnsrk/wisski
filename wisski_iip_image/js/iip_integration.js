(function ($, Drupal, drupalSettings) {
  var makeMooConfig = function(element) {
    // get the iiip server url
    var server = drupalSettings.wisski.iip.iip_server_url || "/fcgi-bin/iipsrv.fcgi";

    // get the image url
    var image = element.attr("iip") || '';

    // remove the prefix url (if any)
    var imagePrefix = drupalSettings.wisski.iip.iip_fs_prefix || "";
    if (image.indexOf(imagePrefix) === 0) {
      image = image.substr(imagePrefix.length);
    }

    // The prefix to the images diretory
    var prefix = drupalSettings.path.baseUrl + 'libraries/iipmooviewer/images/';

    // and build the client config
    return $.extend({
      server: server,
      prefix: prefix,
      image: [image],
      // credit: credit,
      showNavWindow: true,
      showNavButtons: true,
      winResize: true,
      protocol: 'iip',
    }, drupalSettings.wisski.iip.config);
    
  }

  /*
    jQuery 2.9 compat
    Unsure if this is still needed

    jQuery = jQuery29;
    $ = jQuery29;
  */


  $(document).bind('cbox_complete', function () {
    // get the config and make a new viewer
    var config = makeMooConfig(jQuery.colorbox.element())
    new IIPMooViewer("cboxLoadedContent", config);

    // resize the colorbox
    jQuery.colorbox.resize({ width: 1000, height: 600 });
  });

  Drupal.behaviors.iip_integration_Behavior = {
    attach: function (context, settings) {
      once('iipIntegrationBehaviour', context).each(function () {
        // ensure that we requested an inline element
        var element = $('.wisski-inline-iip');
        if (!element.attr('wisski-inline-iip')) {
          return;
        }
        
        // and use it all
        var config = makeMooConfig(element);
        new IIPMooViewer("wisski-iip-cont", iipConfig);
      });
    }
  };

})(jQuery, Drupal, drupalSettings);
