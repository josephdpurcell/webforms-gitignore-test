<?php

namespace Drupal\webform;

use Drupal\Core\Session\AccountInterface;

/**
 * Interface of webform access rules manager.
 */
interface WebformAccessRulesManagerInterface {

  /**
   * Check if operation is allowed through access rules for a given webform.
   *
   * @param string $operation
   *   Operation to check.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account who is requesting the operation.
   * @param \Drupal\webform\WebformInterface $webform
   *   Webform on which the operation is requested.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   Access result.
   */
  public function checkWebformAccess($operation, AccountInterface $account, WebformInterface $webform);

  /**
   * Check if operation is allowed through access rules for a submission.
   *
   * @param string $operation
   *   Operation to check.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account who is requesting the operation.
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   Webform submission on which the operation is requested.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   Access result.
   */
  public function checkWebformSubmissionAccess($operation, AccountInterface $account, WebformSubmissionInterface $webform_submission);

  /**
   * Returns the webform default access rules.
   *
   * @return array
   *   A structured array containing all the webform default access rules.
   */
  public function getDefaultAccessRules();

}
