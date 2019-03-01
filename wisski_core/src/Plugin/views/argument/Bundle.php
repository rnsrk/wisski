<?php

namespace Drupal\wisski_core\Plugin\views\argument;

use Drupal\wisski_core\Entity\WisskiBundle;

/**
 * Numeric argument for fields.
 *
 * @ingroup views_argument_handlers
 *
 * @ViewsArgument("wisski_bundle")
 */
class Bundle extends StringArgument {

  /**
   * We don't support every operator from the parent class ("not between", for example),
   * hence the need to define only the operators we do support.
   */
  public function operators() {
    dpm(__METHOD__, __METHOD__);
    $operators = [
      'IN' => [
        'title' => t('Is equal to'),
        'method' => 'opSimple',
        'short' => t('='),
        'values' => 1,
      ],
      'NOT IN' => [
        'title' => t('Is not equal to'),
        'method' => 'opSimple',
        'short' => t('!='),
        'values' => 1,
      ],
    ];

    return $operators;
  }

  /**
   *
   */
  public function title() {
    // Init the value field.
    $this->prepareValue();
    // Go thru the bundles and collect their labels.
    $labels = [];
    foreach (WisskiBundle::loadMultiple($this->value) as $bundle) {
      if (!empty($bundle)) {
        $labels[] = $bundle->label();
      }
    }
    // TODO: apparently there is no way to compose a translatable list with t() or similar
    // to take care of language specific concat syntaxes
    // (cf. en: "A, B, and C" / de: "A, B, und C" / zh: "A、B、C" )
    return join(', ', $labels);

  }

}
