<?php

namespace Drupal\webform_submission_log\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Remove webform node log routes.
 */
class WebformSubmissionLogRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if (!\Drupal::moduleHandler()->moduleExists('webform_node')) {
      $collection->remove('entity.node.webform.results_log');
      $collection->remove('entity.node.webform_submission.log');
    }
  }

}
