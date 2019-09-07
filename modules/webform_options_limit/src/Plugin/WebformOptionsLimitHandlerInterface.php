<?php

namespace Drupal\webform_options_limit\Plugin;

use Drupal\webform\Plugin\WebformHandlerInterface;

/**
 * Defines the interface for webform options limit handlers.
 */
interface WebformOptionsLimitHandlerInterface extends WebformHandlerInterface {

  /**
   * Build summary table.
   *
   * @return array
   *   A renderable containing the options limit summary table.
   */
  public function buildSummaryTable();

}
