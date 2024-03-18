/**
* @file
* Expands the behaviour of the default autocompletion.
*/

(function (Drupal) {

  Drupal.behaviors.wisskiAutocomplete = {
    attach: function (context, settings) {

      // Escape if field do not support autocomplete.
      if (!Drupal.hasOwnProperty('autocomplete')) {
        return
      }

      // Override the "select" option of the jQueryUI autocomplete
      // to make sure we do not use quot es for inputs with comma.
      // By Mark:
      // This does not seem to work anymore with Drupal 10.2
      // I don't really know why. The function from the core is typically
      // called and I can't override it. I tested with selectHandler
      // below afterwards, but it did not work either.
      /*
      Drupal.autocomplete.options.select = function (event, ui) {

        //var terms = Drupal.autocomplete.splitValues(event.target.value);
        // start empty, otherwise cases with a , do not get created correctly...
        var terms = [];

        // Remove the current input.
        //terms.pop();
        // Add the selected item.
        terms.push(ui.item.value);
        event.target.value = terms.join(', ');
        // Return false to tell jQuery UI that we've filled in the value already.
        return false;
      };
      */

      // By Mark:
      // this did not work either... I ended up with the one below.
      /*      
      Drupal.autocomplete.selectHandler = function (event, ui) {
        var terms = [];
        terms.push(ui.item.value);
        event.target.value = terms.join(', ');
        
        // Return false to tell jQuery UI that we've filled in the value already.
        return false;
      };
      */
      
      // Override the "select" option of the jQueryUI autocomplete
      // to make sure we do not use quot es for inputs with comma.
      Drupal.autocomplete.splitValues = function (value) {
        return [];
      };
      
      //Overrides the default extractLastTerm found in core/misc/autocomplete.js
      Drupal.autocomplete.extractLastTerm = function extractLastTerm(terms) {
//        return ('"' + terms + '"');
        return terms;
        //alert(terms);
        //alert("yay?");
        //Implement your override behavior here
      };
    }
  }
})(Drupal);


