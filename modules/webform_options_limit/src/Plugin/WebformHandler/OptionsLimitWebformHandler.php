<?php

namespace Drupal\webform_options_limit\Plugin\WebformHandler;

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
      'element' => '',
      'limits' => [],
      'limit_reached_action' => 'disable',
      'limit_source_entity' => TRUE,
      'option_message_display' => 'label',
      'option_multiple_message' => '[@remaining remaining]',
      'option_single_message' => '[@remaining remaining]',
      'option_none_message' => '[@remaining remaining]',
      'option_error_message' => '@label is unavailable',
      'debug' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $configuration = $this->getConfiguration();
    $settings = $configuration['settings'];

    $element = $this->getWebform()->getElement($settings['element']);
    if ($element) {
      $webform_element = $this->elementManager->getElementInstance($element);
        $t_args = [
          '@title' => $webform_element->getAdminLabel($element),
          '@type' => $webform_element->getPluginLabel(),
        ];
      $settings['element'] = $this->t('@title (@type)', $t_args);
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
    $form['element_settings']['element'] = [
      '#type' => 'select',
      '#title' => $this->t('Element'),
      '#options' => $this->getElementsWithOptions(),
      '#default_value' => $this->configuration['element'],
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
        '@type' => (isset($element['#images'])) ? $this->t('image') : $this->t('option')
      ];
      $form['element_settings']['options_container']['limits'] = [
        '#type' => 'webform_mapping',
        '#title' => $this->t('@title @type limits', $t_args),
        '#description_display' => 'before',
        '#source' => $element_options,
        '#source__title' => (isset($element['#images'])) ? $this->t('Image') : $this->t('Options'),
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
        ]
      ]
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
    $form['option_settings']['option_error_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Option validation error message'),
      '#default_value' => $this->configuration['option_error_message'],
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
          $this->t("@labal - The option's label."),
        ],
      ],
    ];


    // Development.
    $form['development'] = [
      '#type' => 'details',
      '#title' => $this->t('Development settings'),
    ];
    $form['development']['debug'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable debugging'),
      '#description' => $this->t('If checked, every handler method invoked will be displayed onscreen to all users.'),
      '#return_value' => TRUE,
      '#default_value' => $this->configuration['debug'],
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
    return NestedArray::getValue($form, ['settings', 'element_settings', 'options_container']);
  }

  /****************************************************************************/
  // Element methods.
  /****************************************************************************/

  /**
   * {@inheritdoc}
   */
  function alterElement(array &$element, FormStateInterface $form_state, array $context) {
    if ($element['#webform_key'] !== $this->configuration['element']) {
      return;
    }

    $limits = $this->getLimits();
    if (isset($element['#options'])) {
      $options =& $element['#options'];
      $this->alterElementOptions($options, $limits);
    }
  }

  protected function alterElementOptions(array &$options, array $limits) {
    foreach ($options as $option_value => $option_text) {
      if (is_array($option_text)) {
        $this->alterElementOptions($option_text, $limits);
      }
      elseif (isset($limits[$option_value])) {
        // @todo Handler removing option.
        $options[$option_value] = $this->getElementOptionLabel(
          $option_text,
          $limits[$option_value]
        );
      }
    }
  }

  /**
   * Add limit message to an option's label.
   *
   * @param string $label
   *   An option's label.
   * @param array $limit
   *   The option's limit information
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   An option's label with a limit message.
   */
  protected function getElementOptionLabel($label, array $limit) {
    $message_display = $this->configuration['option_message_display'];
    if ($message_display === static::MESSAGE_DISPLAY_NONE) {
      return $label;
    }

    $message = $this->configuration['option_' . $limit['status'] . '_message'];
    if (!$message) {
      return $label;
    }

    $message = $this->t($message, [
      '@limit' => $limit['limit'],
      '@total' => $limit['total'],
      '@remaining' => $limit['remaining'],
      '@label' => $label,
    ]);

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

  /****************************************************************************/
  // Helper methods.
  /****************************************************************************/

  /**
   * Get selected element.
   *
   * @return array
   *   Selected element.
   */
  protected function getElement() {
    return $this->getWebform()->getElement($this->configuration['element']);
  }

  /**
   * Get selected webform element plugin.
   *
   * @return \Drupal\webform\Plugin\WebformElementInterface|null
   *   A webform element plugin instance
   */
  protected function getWebformElement() {
    $element = $this->getElement();
    return ($element) ? $this->elementManager->getElementInstance($element) : NULL;
  }

  /**
   * Get key/value array of webform elements with options or images.
   *
   * @return array
   *   A key/value array of webform elements with options or images.
   */
  protected function getElementsWithOptions() {
    $webform = $this->getWebform();
    $elements = $webform->getElementsInitializedAndFlattened();
    $options = [];
    foreach ($elements as $element_key => $element) {
      $webform_element = $this->elementManager->getElementInstance($element);
      // @todo: Add support for composites which contain options sub-elements.
      if ($webform_element->hasProperty('options') ||
        $webform_element->hasProperty('images') ) {
        $key = $element['#webform_key'];
        $t_args = [
          '@title' => $webform_element->getAdminLabel($element),
          '@type' => $webform_element->getPluginLabel(),
        ];
        $options[$key] = $this->t('@title (@type)', $t_args);
      }
    }
    return $options;
  }

  /**
   * Get selected element's options or images.
   *
   * @return array
   *   A key/value array of options.
   */
  protected function getElementOptions() {
    $element = $this->getElement();
    if (!$element) {
      return [];
    }

    if (isset($element['#images'])) {
      $options = [];
      foreach ($element['#images'] as $key => $image) {
        $options[$key] = (!empty($image['title'])) ? $image['title'] : $key;
      }
    }
    else {
      $options = $element['#options'];
      $options = OptGroup::flattenOptions($options);
    }

    return $options;
  }

  /**
   * Get options submission totals for the current webform and source entity.
   *
   * @return array
   *   A key/value array of options totals.
   */
  protected function getTotals() {
    $webform_submission = $this->getWebformSubmission();
    $webform = $this->getWebform();

    /** @var \Drupal\Core\Database\StatementInterface $result */
    $query = $this->database->select('webform_submission', 's');
    $query->join('webform_submission_data', 'sd', 's.sid = sd.sid');
    $query->fields('sd', ['value']);
    $query->addExpression('COUNT(value)', 'total');
    $query->condition('sd.name', $this->configuration['element']);
    $query->condition('sd.webform_id', $webform->id());
    // @todo Add source entity support.

    $query->groupBy('value');
    return $query->execute()->fetchAllKeyed();
  }

  /**
   * Get an associative array of options limits.
   *
   * @return array
   *   An associative array of options limits keyed by option value and
   *   including the option's limit, total, remaining, and status.
   */
  protected function getLimits() {
    $totals = $this->getTotals();
    $limits = [];
    $option_keys = array_keys($this->getElementOptions());
    foreach ($option_keys as $option_key) {
      $limit = (isset($this->configuration['limits'][$option_key]))
        ? $this->configuration['limits'][$option_key]
        : $this->configuration['limits'][static::DEFAULT_LIMIT];
      if (!$limit) {
        continue;
      }

      $total = (isset($totals[$option_key])) ? $totals[$option_key] : 0;

      $remaining = $limit - $total;

      if ($remaining <= 0) {
        $status = static::LIMIT_STATUS_NONE;
      }
      elseif ($remaining === 1) {
        $status = static::LIMIT_STATUS_SINGLE;
      }
      else {
        $status = static::LIMIT_STATUS_MULTIPLE;
      }

      $limits[$option_key] = [
        'limit' => $limit,
        'total' => $total,
        'remaining' => $remaining,
        'status' => $status,
      ];
    }
    return $limits;
  }

}
