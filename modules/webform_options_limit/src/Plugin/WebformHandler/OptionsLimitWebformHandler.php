<?php

namespace Drupal\webform_options_limit\Plugin\WebformHandler;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\webform\Plugin\WebformElementManagerInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\Utility\WebformElementHelper;
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
   * Cache of default configuration values.
   *
   * @var array
   */
  protected $defaultValues;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerChannelFactoryInterface $logger_factory, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, WebformSubmissionConditionsValidatorInterface $conditions_validator, WebformTokenManagerInterface $token_manager, WebformElementManagerInterface $element_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger_factory, $config_factory, $entity_type_manager, $conditions_validator);
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
      $container->get('webform.token_manager'),
      $container->get('plugin.manager.webform.element')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'limit' => NULL,
      'limit_meet' => 'disable',
      'limit_source_entity' => TRUE,

      'option' => 'label',
      'option_multiple_message' => '(@remaining remaining/@total total)',
      'option_single_message' => '(1 remaining/@total total)',
      'option_none_message' => '(0 remaining/@total total)',

      'element' => '',
      'options' => [],

      'debug' => FALSE,
    ];
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
      '#type' => 'fieldset',
      '#title' => $this->t('Element settings'),
    ];
    $form['element_settings']['element'] = [
      '#type' => 'select',
      '#title' => $this->t('Element'),
      '#options' => $this->getElementsWithOptions(),
      '#default_value' => $this->configuration['element'],
      '#required' => TRUE,
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
    if ($element_options = $this->getElementOptions()) {
      $form['element_settings']['options_container']['xxxoptions'] = [
        '#markup' => print_r($element_options , TRUE),
        '#prefix' => '<pre>',
        '#suffix' => '</pre>',
      ];
    }

    // Limit settings.
    $form['limit_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Default limit settings'),
    ];
    $form['limit_settings']['limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Default submission limit for each option'),
      '#min' => 1,
      '#default_value' => $this->configuration['limit'],
    ];
    $form['limit_settings']['limit_meet'] = [
      '#type' => 'select',
      '#title' => $this->t('Limit meet option behavior'),
      '#options' => [
        'disable' => $this->t('Disable the option'),
        'remove' => $this->t('Remove the option'),
        'none' => $this->t('Do not alter the option'),
      ],
      '#default_value' => $this->configuration['limit_meet'],
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
      '#type' => 'fieldset',
      '#title' => $this->t('Option settings'),
    ];
    $form['option_settings']['option'] = [
      '#type' => 'select',
      '#title' => $this->t('Option behavior'),
      '#options' => [
        'label' => $this->t("Append message to the option's text"),
        'description' => $this->t("Append message to the option's description"),
        'none' => $this->t("Do not display messages"),
      ],
      '#default_value' => $this->configuration['option'],
    ];
    $form['option_settings']['option_message'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="settings[option]"]' => ['!value' => 'none'],
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

    // ISSUE: TranslatableMarkup is breaking the #ajax.
    // WORKAROUND: Convert all Render/Markup to strings.
    WebformElementHelper::convertRenderMarkupToStrings($form);

    return $this->setSettingsParents($form);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    $this->applyFormStateToConfiguration($form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);


    $values = $form_state->getValues();

    foreach ($this->configuration as $name => $value) {
      if (isset($values[$name])) {
        // Convert options array to safe config array to prevent errors.
        // @see https://www.drupal.org/node/2297311
        if (preg_match('/_options$/', $name)) {
          // $this->configuration[$name] = $values[$name];
          // $this->configuration[$name] = WebformOptionsHelper::encodeConfig($values[$name]);
        }
        else {
          $this->configuration[$name] = $values[$name];
        }
      }
    }

    // Cast debug.
    $this->configuration['debug'] = (bool) $this->configuration['debug'];
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
  // Helper methods.
  /****************************************************************************/

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

  protected function getElementOptions() {
    $element = $this->getWebform()->getElement($this->configuration['element']);
    $options = $element['#options'];
    return $options;
  }

}
