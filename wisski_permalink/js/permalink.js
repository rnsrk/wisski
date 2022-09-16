(function ($, Drupal, drupalSettings) {
  var url = drupalSettings.wisski_permalink.permalink.url;
  try {
    history.pushState(null, "", url);
  }
  catch (e) { }
})(jQuery, Drupal, drupalSettings);
