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

//import Mirador from 'mirador/dist/es/src/index';
//import { miradorImageToolsPlugin } from 'mirador-image-tools';
//import annotationPlugin from 'mirador-annotations';
//import LocalStorageAdapter from 'mirador-annotations/lib/LocalStorageAdapter'

          let plugins = [];    
//          console.log(window.miradorPlugins);
          if (window.miradorPlugins && window.miradorPlugins.length) {
            console.log(window.miradorPlugins);
//            window.miradorPlugins.        
            for (let {plugin, name} of window.miradorPlugins) {	
              //if (window.globalMiradorPlugins.includes(name)) {                
                plugins = [...plugins, ...plugin];            
              //}          
            }
          }
//            alert(jQuery.fn.jquery);
        const annotationEndpoint = '/wisski/mirador-annotations';
        const mirador = Mirador.viewer({
/*
          annotation: {
            adapter: (canvasId) => new LocalStorageAdapter(`localStorage://?canvasId=${canvasId}`),
            //adapter: (canvasId) => new LocalStorageAdapter(`localStorage://?canvasId=${canvasId}`),
            // adapter: (canvasId) => new AnnototAdapter(canvasId, endpointUrl),
            exportLocalStorageAnnotations: false, // display annotation JSON export button
          },*/
          annotation: {
            adapter: (canvasId) => window.miradorAnnotationServerAdapter(canvasId, annotationEndpoint),
          },
          id: "viewer",
          allowFullscreen: true,
          "window": {
            "allowClose": false,
            "allowFullscreen": true,
            "allowMaximize": false,
            "allowTopMenuButton": true,
            "allowWindowSideBar": true,
            "sideBarPanel": "info",
            "defaultSideBarPanel": "attribution",
            "sideBarOpenByDefault": false,
            "defaultView": "single",
            "forceDrawAnnotations": true,
            "hideWindowTitle": true,
            "highlightAllAnnotations": false,
            "showLocalePicker": true,
            "sideBarOpen": true,
            "switchCanvasOnSearch": true,
            "panels": {
              "info": true,
              "attribution": true,
              "canvas": true,
              "annotations": true,
              "search": true,
              "layers": true
            }
          },
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
        }, plugins);

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