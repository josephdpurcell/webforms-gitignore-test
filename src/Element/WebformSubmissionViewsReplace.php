<?php

namespace Drupal\webform\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;

/**
 * Provides a form element for selecting webform submission views replacement routes.
 *
 * @FormElement("webform_submission_views_replace")
 */
class WebformSubmissionViewsReplace extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#process' => [
        [$class, 'processWebformSubmissionViewsReplace'],
      ],
    ];
  }

    /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input === FALSE) {
      if (!isset($element['#default_value'])) {
        $element['#default_value'] = [];
      }
      return $element['#default_value'];
    }
    else {
      return $input;
    }
  }

 /**
   * Processes a ng webform submission views replacement element.
   */
  public static function processWebformSubmissionViewsReplace(&$element, FormStateInterface $form_state, &$complete_form) {
    $element['#tree'] = TRUE;

    $element['#value'] += [
      'global_routes' => [],
      'webform_routes' => [],
      'node_routes' => [],
    ];

    $element['global_routes'] = [
      '#type' => 'checkboxes',
      '#title' => t('Replace the global results with submission views'),
      '#options' => [
        'entity.webform_submission.collection' => t('Submissions'),
        'entity.webform_submission.user' => t('User'),
      ],
      '#default_value' => $element['#value']['global_routes'],
      '#element_validate' => [['\Drupal\webform\Utility\WebformElementHelper', 'filterValues']],
    ];
    $element['webform_routes'] = [
      '#type' => 'checkboxes',
      '#title' => t('Replace the webform results with submission views'),
      '#options' => [
        'entity.webform.results_submissions' => t('Submissions'),
        'entity.webform.user.drafts' => t('User drafts'),
        'entity.webform.user.submissions' => t('User submissions'),
      ],
      '#default_value' => $element['#value']['webform_routes'],
      '#element_validate' => [['\Drupal\webform\Utility\WebformElementHelper', 'filterValues']],
    ];
    if (\Drupal::moduleHandler()->moduleExists('webform_node')) {
      $element['node_routes'] = [
        '#type' => 'checkboxes',
        '#title' => t('Replace the node results with submission views'),
        '#options' => [
          'entity.node.webform.results_submissions' => t('Submissions'),
          'entity.node.webform.user.drafts' => t('User drafts'),
          'entity.node.webform.user.submissions' => t('User submissions'),
        ],
        '#default_value' => $element['#value']['node_routes'],
        '#element_validate' => [['\Drupal\webform\Utility\WebformElementHelper', 'filterValues']],
      ];
    }
    return $element;
  }

}
