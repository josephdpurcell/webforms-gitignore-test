<?php

namespace Drupal\webform_options_limit\Plugin\WebformHandler;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Database\Connection;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\OptGroup;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\webform\Plugin\WebformElementManagerInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\Utility\WebformOptionsHelper;
use Drupal\webform\WebformSubmissionConditionsValidatorInterface;
use Drupal\webform\WebformTokenManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Webform options limit handler.
 *
 * @WebformHandler(
 *   id = "options_limit",
 *   label = @Translation("Options limit"),
 *   category = @Translation("Options"),
 *   description = @Translation("Define options submission limit."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_UNLIMITED,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_IGNORED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_REQUIRED,
 * )
 */
class OptionsLimitWebformHandler extends WebformHandlerBase {

  /**
   * Default option value.
   */
  const DEFAULT_LIMIT = '_default_';

  /**
   * Option limit single remaining.
   */
  const LIMIT_STATUS_SINGLE = 'single';

  /**
   * Option limit multiple remaining.
   */
  const LIMIT_STATUS_MULTIPLE = 'multiple';

  /**
   * Option limit none remaining.
   */
  const LIMIT_STATUS_NONE = 'none';

  /**
   * Option limit unlimited.
   */
  const LIMIT_STATUS_UNLIMITED = 'unlimited';

  /**
   * Option limit eror.
   */
  const LIMIT_STATUS_ERROR = 'error';

  /**
   * Option limit action disable.
   */
  const LIMIT_ACTION_DISABLE = 'disable';

  /**
   * Option limit action remove.
   */
  const LIMIT_ACTION_REMOVE = 'remove';

  /**
   * Option limit action none.
   */
  const LIMIT_ACTION_NONE = 'none';

  /**
   * Option message label.
   */
  const MESSAGE_DISPLAY_LABEL = 'label';

  /**
   * Option message none.
   */
  const MESSAGE_DISPLAY_DESCRIPTION = 'description';

  /**
   * Option message none.
   */
  const MESSAGE_DISPLAY_NONE = 'none';

  /**
   * The element (cached) label.
   *
   * @var string
   */
  protected $elementLabel;

  /**
   * The database object.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The webform token manager.
   *
   * @var \Drupal\webform\WebformTokenManagerInterface
   */
  protected $tokenManager;

  /**
   * A webform element plugin manager.
   *
   * @var \Drupal\webform\Plugin\WebformElementManagerInterface
   */
  protected $elementManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerChannelFactoryInterface $logger_factory, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, WebformSubmissionConditionsValidatorInterface $conditions_validator, Connection $database, WebformTokenManagerInterface $token_manager, WebformElementManagerInterface $element_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger_factory, $config_factory, $entity_type_manager, $conditions_validator);
    $this->database = $database;
    $this->tokenManager = $token_manager;
    $this->elementManager = $element_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory'),
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('webform_submission.conditions_validator'),
      $container->get('database'),
      $container->get('webform.token_manager'),
      $container->get('plugin.manager.webform.element')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'element_key' => '',
      'limits' => [],
      'limit_reached_action' => 'disable',
      'limit_source_entity' => TRUE,
      'option_message_display' => 'label',
      'option_multiple_message' => '[@remaining remaining]',
      'option_single_message' => '[@remaining remaining]',
      'option_unlimited_message' => '[Unlimited]',
      'option_none_message' => '[@remaining remaining]',
      'option_error_message' => '@name: @label is unavailable.',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $configuration = $this->getConfiguration();
    $settings = $configuration['settings'];

    $element = $this->getWebform()->getElement($settings['element_key']);
    if ($element) {
      $webform_element = $this->elementManager->getElementInstance($element);
      $t_args = [
        '@title' => $webform_element->getAdminLabel($element),
        '@type' => $webform_element->getPluginLabel(),
      ];
      $settings['element_key'] = $this->t('@title (@type)', $t_args);
    }

    return [
      '#settings' => $settings,
    ] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $this->applyFormStateToConfiguration($form_state);

    // Attached webform.form library for Ajax submit trigger behavior.
    $form['#attached']['library'][] = 'webform/webform.form';
    $ajax_wrapper = 'webform-options-limit-ajax-wrapper';

    // Element settings.
    $form['element_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Element settings'),
      '#open' => TRUE,
    ];
    $form['element_settings']['element_key'] = [
      '#type' => 'select',
      '#title' => $this->t('Element'),
      '#options' => $this->getElementsWithOptions(),
      '#default_value' => $this->configuration['element_key'],
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select -') ,
      '#attributes' => [
        'data-webform-trigger-submit' => ".js-$ajax_wrapper-submit",
      ],
    ];
    $form['element_settings']['update'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update'),
      '#validate' => [],
      '#submit' => [[get_called_class(), 'rebuildCallback']],
      '#ajax' => [
        'callback' => [get_called_class(), 'ajaxCallback'],
        'wrapper' => $ajax_wrapper,
        'progress' => ['type' => 'fullscreen'],
      ],
      // Disable validation, hide button, add submit button trigger class.
      '#attributes' => [
        'formnovalidate' => 'formnovalidate',
        'class' => [
          'js-hide',
          "js-$ajax_wrapper-submit",
        ],
      ],
    ];
    $form['element_settings']['options_container'] = [
      '#type' => 'container',
      '#attributes' => ['id' => $ajax_wrapper],
    ];
    $element = $this->getElement();
    if ($element) {
      $webform_element = $this->getWebformElement();
      $element_options = $this->getElementOptions() + [
        static::DEFAULT_LIMIT => $this->t('Default (Used when option has no limit)'),
      ];
      $t_args = [
        '@title' => $webform_element->getAdminLabel($element),
        '@type' => $this->t('option'),
      ];
      $form['element_settings']['options_container']['limits'] = [
        '#type' => 'webform_mapping',
        '#title' => $this->t('@title @type limits', $t_args),
        '#description_display' => 'before',
        '#source' => $element_options,
        '#source__title' => $this->t('Options'),
        '#destination__type' => 'number',
        '#destination__min' => 1,
        '#destination__title' => $this->t('Limit'),
        '#destination__description' => NULL,
        '#default_value' => $this->configuration['limits'],
      ];
    }
    else {
      $form['element_settings']['options_container']['limits'] = [
        '#type' => 'value',
        '#value' => [],
      ];
    }

    // Limit settings.
    $form['limit_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Limit settings'),
    ];
    $form['limit_settings']['limit_reached_action'] = [
      '#type' => 'select',
      '#title' => $this->t('Limit reached behavior'),
      '#options' => [
        static::LIMIT_ACTION_DISABLE => $this->t('Disable the option'),
        static::LIMIT_ACTION_REMOVE => $this->t('Remove the option'),
        static::LIMIT_ACTION_NONE => $this->t('Do not alter the option'),
      ],
      '#default_value' => $this->configuration['limit_reached_action'],
    ];
    $form['limit_settings']['limit_source_entity'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Apply options limit to each source entity'),
      '#description' => $this->t('If checked, options limit will be applied to this webform and each source entity individually.'),
      '#return_value' => TRUE,
      '#default_value' => $this->configuration['limit_source_entity'],
    ];

    // Option settings.
    $form['option_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Message settings'),
    ];
    $form['option_settings']['option_message_display'] = [
      '#type' => 'select',
      '#title' => $this->t('Option message display'),
      '#options' => [
        static::MESSAGE_DISPLAY_LABEL => $this->t("Append message to the option's text"),
        static::MESSAGE_DISPLAY_DESCRIPTION => $this->t("Append message to the option's description"),
        static::MESSAGE_DISPLAY_NONE => $this->t("Do not display a message"),
      ],
      '#default_value' => $this->configuration['option_message_display'],
    ];
    $form['option_settings']['option_message'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="settings[option_message_display]"]' => ['!value' => 'none'],
        ],
      ],
    ];
    $form['option_settings']['option_message']['option_multiple_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Option multiple remaining message'),
      '#default_value' => $this->configuration['option_multiple_message'],
    ];
    $form['option_settings']['option_message']['option_single_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Option one remaining message'),
      '#default_value' => $this->configuration['option_single_message'],
    ];
    $form['option_settings']['option_message']['option_none_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Option none remaining message'),
      '#default_value' => $this->configuration['option_none_message'],
    ];
    $form['option_settings']['option_message']['option_unlimited_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Option unlimited message'),
      '#default_value' => $this->configuration['option_unlimited_message'],
    ];
    $form['option_settings']['option_error_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Option validation error message'),
      '#default_value' => $this->configuration['option_error_message'],
      '#required' => TRUE,
    ];
    $form['option_settings']['placeholders'] = [
      '#type' => 'details',
      '#title' => $this->t('Placeholder help'),
      'title' => ['#markup' => $this->t('The following placeholders can be used:')],
      'items' => [
        '#theme' => 'item_list',
        '#items' => [
          $this->t('@limit - The total number of submissions allowed for the option.'),
          $this->t('@total - The current number of submissions for the option.'),
          $this->t('@remaining - The remaining number of submissions for the option.'),
          $this->t("@label - The element option's label."),
          $this->t("@name - The element's title."),
        ],
      ],
    ];

    return $this->setSettingsParents($form);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->applyFormStateToConfiguration($form_state);

    foreach ($this->configuration['limits'] as $key => $value) {
      $this->configuration['limits'][$key] = (int) $value;
    }

    // Clear cached element label.
    // @see \Drupal\webform_options_limit\Plugin\WebformHandler\OptionsLimitWebformHandler::getElementLabel
    $this->elementLabel = NULL;
  }

  /**
   * Rebuild callback.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function rebuildCallback(array $form, FormStateInterface $form_state) {
    $form_state->setRebuild();
  }

  /**
   * Ajax callback.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   An associative array containing options element.
   */
  public function ajaxCallback(array $form, FormStateInterface $form_state) {
    return NestedArray::getValue($form, [
      'settings',
      'element_settings',
      'options_container',
    ]);
  }

  /****************************************************************************/
  // Alter element methods.
  /****************************************************************************/

  /**
   * {@inheritdoc}
   */
  public function alterElement(array &$element, FormStateInterface $form_state, array $context) {
    if (empty($element['#webform_key'])
      || $element['#webform_key'] !== $this->configuration['element_key']) {
      return;
    }

    // Set webform submission for form object.
    /** @var \Drupal\webform\WebformSubmissionForm $form_object */
    $form_object = $form_state->getFormObject();
    /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
    $webform_submission = $form_object->getEntity();
    $this->setWebformSubmission($webform_submission);

    // Get limits, disabled, and (form) operation.
    $limits = $this->getLimits();
    $disabled = $this->getDisabled($limits);
    $operation = $form_object->getOperation();

    // Cleanup default value.
    $this->setElementDefaultValue($element, $limits, $disabled, $operation);

    // Alter element options label.
    $options =& $element['#options'];
    $this->alterElementOptions($options, $limits);

    // Disable element options.
    if ($disabled) {
      switch ($this->configuration['limit_reached_action']) {
        case static::LIMIT_ACTION_DISABLE:
          $this->disableElementOptions($element, $disabled);
          break;

        case static::LIMIT_ACTION_REMOVE:
          $this->removeElementOptions($element, $disabled);
          break;
      }
    }

    // Add validate callback.
    $element['#element_validate'][] = [get_called_class(), 'validateWebformOptionsLimit'];
    $element['#webform_option_limit_handler_id'] = $this->getHandlerId();

    // Append option limit summary to edit form for admins only.
    $is_edit_operation = in_array($operation, ['edit', 'edit_all']);
    $has_update_any = $this->getWebform()->access('submission_update_any');
    if ($is_edit_operation && $has_update_any) {
      $this->appendLimitSummary($element, $limits);
    }
  }

  /**
   * Set and cleanup the element's default value.
   *
   * @param array $element
   *   A webform element with options limit.
   * @param array $limits
   *   A webform element's option limits.
   * @param array $disabled
   *   A webform element's disabled options.
   * @param $operation
   *   The form's current operation.
   */
  protected function setElementDefaultValue(array &$element, array $limits, array $disabled, $operation) {
    $webform_element = $this->getWebformElement();
    $has_multiple_values = $webform_element->hasMultipleValues($element);
    // Make sure the test default value is an enabled option.
    if ($operation === 'test') {
      $test_values = array_keys($disabled ? array_diff_key($limits, $disabled) : $limits);
      if ($test_values) {
        $test_value = $test_values[array_rand($test_values)];
        $element['#default_value'] = ($has_multiple_values) ? [$test_value] : $test_value;
      }
      else {
        $element['#default_value'] = NULL;
      }
    }
    // Cleanup default values.
    elseif (!empty($element['#default_value'])) {
      $default_value = $element['#default_value'];
      if ($has_multiple_values) {
        $element['#default_value'] = array_values(array_diff($default_value, $disabled));
      }
      else {
        if (isset($disabled[$default_value])) {
          unset($element['#default_value']);
        }
      }
    }
  }

  /**
   * Alter an element's options recursively.
   *
   * @param array $options
   *   An array of options.
   * @param array $limits
   *   A webform element's option limits.
   */
  protected function alterElementOptions(array &$options, array $limits) {
    foreach ($options as $option_value => $option_text) {
      if (is_array($option_text)) {
        $this->alterElementOptions($option_text, $limits);
      }
      elseif (isset($limits[$option_value])) {
        $options[$option_value] = $this->getLimitLabel(
          $option_text,
          $limits[$option_value]
        );
      }
    }
  }

  /**
   * Disable element options.
   *
   * @param array $element
   *   A webform element with options limit.
   * @param array $disabled
   *   An array of disabled options.
   */
  protected function disableElementOptions(array &$element, array $disabled) {
    $webform_element = $this->getWebformElement();
    if ($webform_element->hasProperty('options__properties')) {
      // Set element options disabled properties.
      foreach ($disabled as $disabled_option) {
        $element['#options__properties'][$disabled_option] = [
          '#disabled' => TRUE,
        ];
      }
    }
    else {
      // Serialize disabled options so that <option> can be disabled
      // via JavaScript.
      // @see Drupal.behaviors.webformOptionsLimit
      $element['#attributes']['data-webform-options-limit-disabled'] = Json::encode($disabled);
      $element['#attached']['library'][] = 'webform_options_limit/webform_options_limit.element';
    }
  }

  /**
   * Remove element options.
   *
   * @param array $element
   *   A webform element with options limit.
   * @param array $disabled
   *   An array of disabled options.
   */
  protected function removeElementOptions(array &$element, array $disabled) {
    $options =& $element['#options'];
    $this->removeElementOptionsRecursive($options, $disabled);
  }

  /**
   * Remove element options recursively.
   *
   * @param array $options
   *   An array options (and optgroups).
   * @param array $disabled
   *   An array of disabled options.
   */
  protected function removeElementOptionsRecursive(array &$options, array $disabled) {
    foreach ($options as $option_value => &$option_text) {
      if (is_array($option_text)) {
        $this->removeElementOptionsRecursive($option_text, $disabled);
        if (empty($option_text)) {
          unset($options[$option_value]);
        }
      }
      elseif (isset($disabled[$option_value])) {
        unset($options[$option_value]);
      }
    }
  }

  /**
   * Append limit summary to element.
   *
   * @param array $element
   *   A webform element with options limit.
   * @param array $limits
   *   A webform element's option limits.
   */
  protected function appendLimitSummary(array &$element, array $limits) {
    $webform_element = $this->getWebformElement();
    $rows = [];
    foreach ($limits as $limit) {
      $rows[] = [
        $limit['label'],
        ['data' => $limit['limit'], 'style' => 'text-align: right'],
        ['data' => $limit['remaining'], 'style' => 'text-align: right'],
        ['data' => $limit['total'], 'style' => 'text-align: right'],
      ];
    }
    $build = [
      '#type' => 'details',
      '#title' => $this->t('Options limit summary'),
      '#description' => $this->t('(For submission administors only)'),
      'limits' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Option'),
          ['data' => $this->t('Limit'), 'style' => 'text-align: right'],
          ['data' => $this->t('Remaining'), 'style' => 'text-align: right'],
          ['data' => $this->t('Total'), 'style' => 'text-align: right'],
        ],
        '#rows' => $rows,
      ],
    ];
    $property = $webform_element->hasProperty('field_suffix') ? '#field_suffix' : '#suffix';
    $element += [$property => ''];
    $element[$property] = \Drupal::service('renderer')->render($build);
  }

  /****************************************************************************/
  // Validation methods.
  /****************************************************************************/

  /**
   * Validate webform options limit.
   */
  public static function validateWebformOptionsLimit(&$element, FormStateInterface $form_state, &$complete_form) {
    // Skip if element is not visible.
    if (isset($element['#access']) && $element['#access'] === FALSE) {
      return;
    }

    /** @var \Drupal\webform\WebformSubmissionForm $form_object */
    $form_object = $form_state->getFormObject();
    $webform = $form_object->getWebform();

    /** @var \Drupal\webform_options_limit\Plugin\WebformHandler\OptionsLimitWebformHandler $handler */
    $handler = $webform->getHandler($element['#webform_option_limit_handler_id']);
    $handler->validateElement($element, $form_state);
  }

  /**
   * Validate a webform element with options limit.
   *
   * @param array $element
   *   A webform element with options limit.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @internal
   *   This method should only called by
   *   OptionsLimitWebformHandler::validateWebformOptionsLimit.
   */
  public function validateElement(array $element, FormStateInterface $form_state) {
    /** @var \Drupal\webform\WebformSubmissionForm $form_object */
    $form_object = $form_state->getFormObject();
    /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
    $webform_submission = $form_object->getEntity();
    $this->setWebformSubmission($webform_submission);

    $element_key = $this->configuration['element_key'];

    // Get casting as array to support multiple options.
    $original_values = (array) $webform_submission->getElementOriginalData($element_key);
    $values = (array) $form_state->getValue($element_key);
    if (empty($values) || $values === ['']) {
      return;
    }

    $limits = $this->getLimits($values);
    foreach ($limits as $value => $limit) {
      // Do not apply option limit to any previously selected option value.
      if (in_array($value, $original_values)) {
        continue;
      }
      if ($limit['status'] === static::LIMIT_STATUS_NONE) {
        $message = $this->getLimitMessage(static::LIMIT_STATUS_ERROR, $limit);
        $form_state->setError($element, $message);
      }
    }
  }

  /****************************************************************************/
  // Element methods.
  /****************************************************************************/

  /**
   * Get selected element.
   *
   * @return array
   *   Selected element.
   */
  protected function getElement() {
    return $this->getWebform()->getElement($this->configuration['element_key']);
  }

  /**
   * Get selected webform element plugin.
   *
   * @return \Drupal\webform\Plugin\WebformElementInterface|null
   *   A webform element plugin instance.
   */
  protected function getWebformElement() {
    $element = $this->getElement();
    return ($element) ? $this->elementManager->getElementInstance($element) : NULL;
  }

  /**
   * Get selected webform element label.
   *
   * @return string
   *   A webform element label.
   */
  protected function getElementLabel() {
    if (isset($this->elementLabel)) {
      return $this->elementLabel;
    }

    $element = $this->getElement();
    $webform_element = $this->getWebformElement();
    $this->elementLabel = $webform_element->getLabel($element);
    return $this->elementLabel;
  }

  /**
   * Get key/value array of webform elements with options.
   *
   * @return array
   *   A key/value array of webform elements with options.
   */
  protected function getElementsWithOptions() {
    $webform = $this->getWebform();
    $elements = $webform->getElementsInitializedAndFlattened();

    $options = [];
    foreach ($elements as $element_key => $element) {
      $webform_element = $this->elementManager->getElementInstance($element);
      if ($webform_element->hasProperty('options')
        && strpos($webform_element->getPluginLabel(), 'tableselect') === FALSE) {
        $webform_key = $element['#webform_key'];
        $t_args = [
          '@title' => $webform_element->getAdminLabel($element),
          '@type' => $webform_element->getPluginLabel(),
        ];
        $options[$webform_key] = $this->t('@title (@type)', $t_args);
      }
    }
    return $options;
  }

  /**
   * Get selected element's options.
   *
   * @return array
   *   A key/value array of options.
   */
  protected function getElementOptions() {
    $element = $this->getElement();
    return ($element) ? OptGroup::flattenOptions($element['#options']) : [];
  }

  /****************************************************************************/
  // Limits methods.
  /****************************************************************************/

  /**
   * Get an associative array of options limits.
   *
   * @param array $values
   *   Optional array of values to get options limit.
   *
   * @return array
   *   An associative array of options limits keyed by option value and
   *   including the option's limit, total, remaining, and status.
   */
  protected function getLimits(array $values = []) {
    $default_limit = isset($this->configuration['limits'][static::DEFAULT_LIMIT])
      ? $this->configuration['limits'][static::DEFAULT_LIMIT]
      : NULL;

    $totals = $this->getTotals($values);

    $options = $this->getElementOptions();
    if ($values) {
      $options = array_intersect_key($options, array_combine($values, $values));
    }

    $limits = [];
    foreach ($options as $option_key => $option_label) {
      $limit = (isset($this->configuration['limits'][$option_key]))
        ? $this->configuration['limits'][$option_key]
        : $default_limit;

      $total = (isset($totals[$option_key])) ? $totals[$option_key] : 0;

      $remaining = ($limit) ? $limit - $total : NULL;

      if (empty($limit)) {
        $status = static::LIMIT_STATUS_UNLIMITED;
      }
      elseif ($remaining <= 0) {
        $status = static::LIMIT_STATUS_NONE;
      }
      elseif ($remaining === 1) {
        $status = static::LIMIT_STATUS_SINGLE;
      }
      else {
        $status = static::LIMIT_STATUS_MULTIPLE;
      }

      $limits[$option_key] = [
        'label' => $option_label,
        'limit' => $limit,
        'total' => $total,
        'remaining' => $remaining,
        'status' => $status,
      ];
    }
    return $limits;
  }

  /**
   * Get value array of disabled options.
   *
   * @param array $limits
   *   An associative array of options limits.
   *
   * @return array
   *   A value array of disabled options.
   */
  protected function getDisabled(array $limits) {
    $element_key = $this->configuration['element_key'];
    $webform_submission = $this->getWebformSubmission();
    $element_values = (array) $webform_submission->getElementOriginalData($element_key) ?: [];
    $disabled = [];
    foreach ($limits as $option_value => $limit) {
      if ($element_values && in_array($option_value, $element_values)) {
        continue;
      }
      if ($limit['status'] === static::LIMIT_STATUS_NONE) {
        $disabled[$option_value] = $option_value;
      }
    }
    return $disabled;
  }

  /**
   * Get options submission totals for the current webform and source entity.
   *
   * @param array $values
   *   Optional array of values to get totals.
   *
   * @return array
   *   A key/value array of options totals.
   */
  protected function getTotals(array $values = []) {
    $webform = $this->getWebform();

    /** @var \Drupal\Core\Database\StatementInterface $result */
    $query = $this->database->select('webform_submission', 's');
    $query->join('webform_submission_data', 'sd', 's.sid = sd.sid');
    $query->fields('sd', ['value']);
    $query->addExpression('COUNT(value)', 'total');
    $query->condition('sd.name', $this->configuration['element_key']);
    $query->condition('sd.webform_id', $webform->id());
    $query->groupBy('value');

    // Limit by option values.
    if ($values) {
      $query->condition('sd.value', $values, 'IN');
    }

    // Limit by source entity.
    if ($this->configuration['limit_source_entity']) {
      $source_entity = $this->getWebformSubmission()->getSourceEntity();
      if ($source_entity) {
        $query->condition('s.entity_type', $source_entity->getEntityTypeId());
        $query->condition('s.entity_id', $source_entity->id());
      }
      else {
        $query->isNull('s.entity_type');
        $query->isNull('s.entity_id');
      }
    }

    return $query->execute()->fetchAllKeyed();
  }

  /****************************************************************************/
  // Labels and messages methods.
  /****************************************************************************/

  /**
   * Get limit message.
   *
   * @param string $type
   *   Type of message.
   * @param array $limit
   *   Associative array containing limit, total, remaining, and label.
   *
   * @return \Drupal\Component\Render\FormattableMarkup|string
   *   A limit message.
   */
  protected function getLimitMessage($type, array $limit) {
    $message = $this->configuration['option_' . $type . '_message'];
    if (!$message) {
      return '';
    }

    return new FormattableMarkup($message, [
      '@name' => $this->getElementLabel(),
      '@label' => $limit['label'],
      '@limit' => $limit['limit'],
      '@total' => $limit['total'],
      '@remaining' => $limit['remaining'],
    ]);
  }

  /**
   * Get option limit label.
   *
   * @param string $label
   *   An option's label.
   * @param array $limit
   *   The option's limit information.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   An option's limit label.
   */
  protected function getLimitLabel($label, array $limit) {
    $message_display = $this->configuration['option_message_display'];
    if ($message_display === static::MESSAGE_DISPLAY_NONE) {
      return $label;
    }

    $message = $this->getLimitMessage($limit['status'], $limit);
    if (!$message) {
      return $label;
    }

    switch ($message_display) {
      case static::MESSAGE_DISPLAY_LABEL:
        $t_args = ['@label' => $label, '@message' => $message];
        return $this->t('@label @message', $t_args);

      case static::MESSAGE_DISPLAY_DESCRIPTION:
        return $label
          . (strpos($label, WebformOptionsHelper::DESCRIPTION_DELIMITER) === FALSE ? WebformOptionsHelper::DESCRIPTION_DELIMITER : '')
          . $message;
    }
  }

}
