<?php

namespace Drupal\webform\Element;

use Drupal\Core\Render\Element\RenderElement;
use Drupal\webform\Entity\Webform as WebformEntity;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformTokenManagerInterface;

/**
 * Provides a render element to display a webform.
 *
 * @RenderElement("webform")
 */
class Webform extends RenderElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#pre_render' => [
        [$class, 'preRenderWebformElement'],
      ],
      '#webform' => NULL,
      '#default_data' => [],
    ];
  }

  /**
   * Webform element pre render callback.
   */
  public static function preRenderWebformElement($element) {
    $webform = ($element['#webform'] instanceof WebformInterface) ? $element['#webform'] : WebformEntity::load($element['#webform']);
    if (!$webform) {
      return $element;
    }

    if ($webform->access('submission_create')) {
      $values = ['data' => $element['#default_data']];
      $element['webform_build'] = $webform->getSubmissionForm($values);
    }
    elseif ($webform->getSetting('form_access_denied') !== WebformInterface::ACCESS_DENIED_DEFAULT) {
      $element['webform_access_denied'] = static::buildAccessDenied($webform);
    }
    else {
      // Add config and webform to cache contexts.
      $config = \Drupal::configFactory()->get('webform.settings');
      $renderer = \Drupal::service('renderer');
      $renderer->addCacheableDependency($element, $config);
      $renderer->addCacheableDependency($element, $webform);
    }

    return $element;
  }

  /**
   * Build access denied message for a webform.
   *
   * @param \Drupal\webform\WebformInterface $webform
   *   A webform.
   *
   * @return array
   *   A renderable array containing thea ccess denied message for a webform.
   */
  public static function buildAccessDenied(WebformInterface $webform) {
    /** @var WebformTokenManagerInterface $webform_token_manager */
    $webform_token_manager = \Drupal::service('webform.token_manager');

    // Message.
    $config = \Drupal::configFactory()->get('webform.settings');
    $message = $webform->getSetting('form_access_denied_message')
      ?: $config->get('settings.default_form_access_denied_message');
    $message = $webform_token_manager->replace($message, $webform);

    // Attributes.
    $attributes = $webform->getSetting('form_access_denied_attributes');
    $attributes['class'][] = 'webform-access-denied';

    $build = [
      '#type' => 'container',
      '#attributes' => $attributes,
      'message' => WebformHtmlEditor::checkMarkup($message),
    ];

    // Add config and webform to cache contexts.
    $renderer = \Drupal::service('renderer');
    $renderer->addCacheableDependency($build, $config);
    $renderer->addCacheableDependency($build, $webform);

    return $build;
  }

}
