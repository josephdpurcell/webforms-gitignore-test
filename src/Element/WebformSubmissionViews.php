<?php

namespace Drupal\webform\Element;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Entity\View;

/**
 * Provides a form element for selecting webform submission views.
 *
 * @FormElement("webform_submission_views")
 */
class WebformSubmissionViews extends WebformMultiple {

  /**
   * {@inheritdoc}
   */
  public static function processWebformMultiple(&$element, FormStateInterface $form_state, &$complete_form) {
    $element['#key'] = 'name';
    $element['#header'] = TRUE;
    $element['#empty_items'] = 0;
    $element['#min_items'] = 1;
    $element['#add_more_input_label'] = t('more submission views');

    // Get view options.
    $view_options = [];
    /** @var \Drupal\views\ViewEntityInterface[] $views */
    $views = View::loadMultiple();
    foreach ($views as $view) {
      // Only include webform submission views.
      if ($view->get('base_table') !== 'webform_submission' || $view->get('base_field') !== 'sid') {
        continue;
      }

      $optgroup = $view->label();
      $displays = $view->get('display');
      foreach ($displays as $display_id => $display) {
        // Only include embed displays.
        if ($display['display_plugin'] === 'embed') {
          $view_options [$optgroup][$view->id() . ':' . $display_id] = $optgroup . ': ' . $display['display_title'];
        }
      }
    }

    // Get route options.
    $route_options = [
      'entity.webform.results_submissions' => t('Webform: Submissions'),
      'entity.webform.user.drafts' => t('Webform: User drafts'),
      'entity.webform.user.submissions' => t('Webform: User submissions'),
      'entity.webform.results_user' => t('Webform: User results'),
    ];
    if (\Drupal::moduleHandler()->moduleExists('webform_node')) {
      $route_options += [
        'entity.node.webform.results_submissions' => t('Node: Submission'),
        'entity.node.webform.user.drafts' => t('Node: User drafts'),
        'entity.node.webform.user.submissions' => t('Node: User submissions'),
        'entity.node.webform.results_user' => t('Node: User results'),
      ];
    }

    // Build element.
    $element['#element'] = [
      'name_title_view' => [
        '#type' => 'container',
        '#title' => t('Name / Title / View'),
        'name' => [
          '#type' => 'textfield',
          '#title' => t('Name'),
          '#title_display' => 'invisible',
          '#placeholder' => t('Enter name…'),
          '#size' => 20,
          '#pattern' => '^[a-z0-9_]+$',
          '#error_no_message' => TRUE,
        ],
        'title' => [
          '#type' => 'textfield',
          '#title' => t('Title'),
          '#title_display' => 'invisible',
          '#placeholder' => t('Enter title…'),
          '#size' => 20,
          '#error_no_message' => TRUE,
        ],
        'view' => [
          '#type' => 'select',
          '#title' => t('View'),
          '#title_display' => 'invisible',
          '#empty_option' => t('Select view…'),
          '#options' => $view_options,
          '#error_no_message' => TRUE,
        ],
      ],
      'routes' => [
        '#type' => 'checkboxes',
        '#title' => t('Apply to'),
        '#options_display' => (count($route_options) > 4) ? 'two_columns' : NULL,
        '#options' => $route_options,
        '#error_no_message' => TRUE,
      ],
    ];
    parent::processWebformMultiple($element, $form_state, $complete_form);
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function validateWebformMultiple(&$element, FormStateInterface $form_state, &$complete_form) {
    parent::validateWebformMultiple($element, $form_state, $complete_form);
    $items = NestedArray::getValue($form_state->getValues(), $element['#parents']);
    foreach ($items as $name => &$item) {
      $item['routes'] = array_values(array_filter($item['routes']));

      // Remove empty view references.
      if ($name === '' && empty($item['view']) && empty($item['routes'])) {
        unset($items[$name]);
        continue;
      }

      if ($name === '') {
        $form_state->setError($element, t('Name is required.'));
      }
      if (empty($item['title'])) {
        $form_state->setError($element, t('Title is required.'));
      }
      if (empty($item['view'])) {
        $form_state->setError($element, t('View name/display id is required.'));
      }
      if (empty($item['routes'])) {
        $form_state->setError($element, t('Apply to is required.'));
      }
    }
    $element['#value'] = $items;
    $form_state->setValueForElement($element, $items);

  }

}
