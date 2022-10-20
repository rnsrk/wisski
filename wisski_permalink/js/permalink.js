(function ($, Drupal, drupalSettings) {
  var url = drupalSettings.wisski_permalink.permalink.url;
  try {
    history.replaceState(null, "", url);
  }
  catch (e) { }
})(jQuery, Drupal, drupalSettings);
