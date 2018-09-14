<?php

namespace Drupal\webform;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the access control handler for the webform submission entity type.
 *
 * @see \Drupal\webform\Entity\WebformSubmission.
 */
class WebformSubmissionAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  /**
   * Webform access rules manager service.
   *
   * @var \Drupal\webform\WebformAccessRulesManagerInterface
   */
  protected $accessRulesManager;

  /**
   * WebformSubmissionAccessControlHandler constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\webform\WebformAccessRulesManagerInterface $access_rules_manager
   *   Webform access rules manager service.
   */
  public function __construct(EntityTypeInterface $entity_type, WebformAccessRulesManagerInterface $access_rules_manager) {
    parent::__construct($entity_type);

    $this->accessRulesManager = $access_rules_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('webform.access_rules_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\webform\WebformSubmissionInterface $entity */

    // Always grant access to user that can administer webforms.
    if ($account->hasPermission('administer webform')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    // Grant user with administer webform submission access to view all webform submissions.
    if ($account->hasPermission('administer webform submission')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    // Check webform submission access permissions.
    // @todo: Refactor and consolidate below code after there are tests.
    switch ($operation) {
      case 'view':
        // Allow users with 'view any webform submission' to view all submissions.
        if ($account->hasPermission('view any webform submission')) {
          return AccessResult::allowed();
        }

        // Allow users with 'view own webform submission' to view own submission.
        if ($account->hasPermission('view own webform submission') && $entity->getOwnerId() == $account->id()) {
          return AccessResult::allowed();
        }
        break;

      case 'update':
        // Allow users with 'edit any webform submission' to edit all submissions.
        if ($account->hasPermission('edit any webform submission')) {
          return AccessResult::allowed();
        }
        // Allow users with 'edit own webform submission' to edit own submission.
        if ($account->hasPermission('edit own webform submission') && $entity->getOwnerId() == $account->id()) {
          return AccessResult::allowed();
        }
        break;

      case 'delete':
        // Allow users with 'delete any webform submission' to edit all submissions.
        if ($account->hasPermission('delete any webform submission')) {
          return AccessResult::allowed();
        }
        // Allow users with 'delete own webform submission' to edit own submission.
        if ($account->hasPermission('delete own webform submission') && $entity->getOwnerId() == $account->id()) {
          return AccessResult::allowed();
        }
        break;
    }

    // Check webform rules for access.
    $webform_access = $this->accessRulesManager->checkWebformSubmissionAccess($operation, $account, $entity);
    if ($webform_access->isAllowed()) {
      return $webform_access;
    }

    return parent::checkAccess($entity, $operation, $account);
  }

}
