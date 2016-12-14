<?php

namespace Drupal\webform\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\WebformRequestInterface;
use Drupal\webform\WebformSubmissionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Webform for webform results custom(ize) webform.
 */
class WebformResultsCustomForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'webform_results_custom';
  }

  /**
   * The webform entity.
   *
   * @var \Drupal\webform\WebformInterface
   */
  protected $webform;

  /**
   * The webform source entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $sourceEntity;

  /**
   * The webform submission storage.
   *
   * @var \Drupal\webform\WebformSubmissionStorageInterface
   */
  protected $submissionStorage;

  /**
   * Webform request handler.
   *
   * @var \Drupal\webform\WebformRequestInterface
   */
  protected $requestHandler;

  /**
   * Constructs a new WebformResultsDeleteBaseForm object.
   *
   * @param \Drupal\webform\WebformSubmissionStorageInterface $webform_submission_storage
   *   The webform submission storage.
   * @param \Drupal\webform\WebformRequestInterface $request_handler
   *   The webform request handler.
   */
  public function __construct(WebformSubmissionStorageInterface $webform_submission_storage, WebformRequestInterface $request_handler) {
    $this->submissionStorage = $webform_submission_storage;
    $this->requestHandler = $request_handler;
    list($this->webform, $this->sourceEntity) = $this->requestHandler->getWebformEntities();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager')->getStorage('webform_submission'),
      $container->get('webform.request')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $available_columns = $this->submissionStorage->getColumns($this->webform, $this->sourceEntity, NULL, TRUE);
    $custom_columns = $this->submissionStorage->getCustomColumns($this->webform, $this->sourceEntity, NULL, TRUE);
    // Change sid's # to an actual label.
    $available_columns['sid']['title'] = $this->t('Submission ID');
    if (isset($custom_columns['sid'])) {
      $custom_columns['sid']['title'] = $this->t('Submission ID');
    }

    // Columns.
    $columns_options = [];
    foreach ($available_columns as $column_name => $column) {
      $title = (strpos($column_name, 'element__') === 0) ? ['data' => ['#markup' => '<b>' . $column['title'] . '</b>']] : $column['title'];
      $key = (isset($column['key'])) ? str_replace('webform_', '', $column['key']) : $column['name'];
      $columns_options[$column_name] = ['title' => $title, 'key' => $key];
    }
    $columns_keys = array_keys($custom_columns);
    $columns_default_value = array_combine($columns_keys, $columns_keys);
    $form['columns'] = [
      '#type' => 'webform_tableselect_sort',
      '#header' => [
        'title' => $this->t('Title'),
        'key' => $this->t('Key'),
      ],
      '#options' => $columns_options,
      '#default_value' => $columns_default_value,
    ];

    // Get available sort options.
    $sort_options = [];
    $sort_columns = $available_columns;
    ksort($sort_columns);
    foreach ($sort_columns as $column_name => $column) {
      if (!isset($column['sort']) || $column['sort'] === TRUE) {
        $sort_options[$column_name] = (string) $column['title'];
      };
    }
    asort($sort_options);

    // Sort and direction.
    // Display available columns sorted alphabetically.
    $sort = $this->webform->getState($this->getStateKey('sort'), 'serial');
    $direction = $this->webform->getState($this->getStateKey('direction'), 'desc');
    $form['sort'] = [
      '#prefix' => '<div class="container-inline">',
      '#type' => 'select',
      '#field_prefix' => $this->t('Sort by'),
      '#options' => $sort_options,
      '#default_value' => $sort,
    ];
    $form['direction'] = [
      '#type' => 'select',
      '#field_prefix' => ' ' . $this->t('in') . ' ',
      '#field_suffix' => ' ' . $this->t('order.'),
      '#options' => [
        'asc' => $this->t('Ascending (ASC)'),
        'desc' => $this->t('Descending (DESC)'),
      ],
      '#default_value' => $direction,
      '#suffix' => '</div>',
    ];

    // Limit.
    $limit = $this->webform->getState($this->getStateKey('limit'), NULL);
    $form['limit'] = [
      '#type' => 'select',
      '#field_prefix' => $this->t('Show'),
      '#field_suffix' => $this->t('results per page.'),
      '#options' => [
        '20' => '20',
        '50' => '50',
        '100' => '100',
        '200' => '200',
        '500' => '500',
        '1000' => '1000',
        '0' => $this->t('All'),
      ],
      '#default_value' => ($limit != NULL) ? $limit : 50,
    ];

    // Default configuration.
    if (empty($this->sourceEntity)) {
      $form['config'] = [
        '#type' => 'details',
        '#title' => $this->t('Configuration settings'),
      ];
      $form['config']['default'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Use as default configuration'),
        '#description' => $this->t('If checked, the above settings will be used as the default configuration for all associated Webform nodes.'),
        '#return_value' => TRUE,
        '#default_value' => $this->webform->getState($this->getStateKey('default'), TRUE),
      ];
    }

    // Format settings.
    $format = $this->webform->getState($this->getStateKey('format'), [
      'header_format' => 'label',
      'element_format' => 'value',
    ]);
    $form['format'] = [
      '#type' => 'details',
      '#title' => $this->t('Format settings'),
      '#tree' => TRUE,
    ];
    $form['format']['header_format'] = [
      '#type' => 'radios',
      '#title' => $this->t('Column header format'),
      '#description' => $this->t('Choose whether to show the element label or element key in each column header.'),
      '#options' => [
        'label' => $this->t('Element titles (label)'),
        'key' => $this->t('Element keys (key)'),
      ],
      '#default_value' => $format['header_format'],
    ];
    $form['format']['element_format'] = [
      '#type' => 'radios',
      '#title' => $this->t('Element format'),
      '#options' => [
        'value' => $this->t('Labels/values, the human-readable value (value)'),
        'raw' => $this->t('Raw values, the raw value stored in the database (raw)'),
      ],
      '#default_value' => $format['element_format'],
    ];

    // Build actions.
    $form['actions']['#type'] = 'actions';
    $form['actions']['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];
    $form['actions']['delete'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset'),
      '#attributes' => [
        'class' => ['button', 'button--danger'],
      ],
      '#access' => $this->webform->hasState($this->getStateKey('columns')),
      '#submit' => ['::delete'],
    ];
    return $form;
  }

  /**
   * Build table row for a results columns.
   *
   * @param string $column_name
   *   The column name.
   * @param array $column
   *   The column.
   * @param bool $default_value
   *   Whether the column should be checked.
   * @param int $weight
   *   The columns weights.
   * @param int $delta
   *   The max delta for the weight element.
   *
   * @return array
   *   A renderable containing a table row for a results column.
   */
  protected function buildRow($column_name, array $column, $default_value, $weight, $delta) {
    return [
      '#attributes' => ['class' => ['draggable']],
      'name' => [
        '#type' => 'checkbox',
        '#default_value' => $default_value,
      ],
      'title' => [
        '#markup' => $column['title'],
      ],
      'key' => [
        '#markup' => (isset($column['key'])) ? $column['key'] : $column['name'],
      ],
      'weight' => [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @label', ['@label' => $column['title']]),
        '#title_display' => 'invisible',
        '#attributes' => [
          'class' => ['table-sort-weight'],
        ],
        '#delta' => $delta,
        '#default_value' => $weight,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $columns = $form_state->getValue('columns');
    if (empty($columns)) {
      $form_state->setErrorByName('columns', $this->t('At least once column is required'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Set columns.
    $this->webform->setState($this->getStateKey('columns'), $form_state->getValue('columns'));

    // Set sort, direction, limit.
    $this->webform->setState($this->getStateKey('sort'), $form_state->getValue('sort'));
    $this->webform->setState($this->getStateKey('direction'), $form_state->getValue('direction'));
    $this->webform->setState($this->getStateKey('limit'), (int) $form_state->getValue('limit'));
    $this->webform->setState($this->getStateKey('format'), $form_state->getValue('format'));

    // Set default.
    if (empty($this->sourceEntity)) {
      $this->webform->setState($this->getStateKey('default'), $form_state->getValue('default'));
    }

    // Display message.
    drupal_set_message($this->t('The customized table has been saved.'));

    // Set redirect.
    $route_name = $this->requestHandler->getRouteName($this->webform, $this->sourceEntity, 'webform.results_table');
    $route_parameters = $this->requestHandler->getRouteParameters($this->webform, $this->sourceEntity);
    $form_state->setRedirect($route_name, $route_parameters);
  }

  /**
   * Webform delete customized columns handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function delete(array &$form, FormStateInterface $form_state) {
    $this->webform->deleteState($this->getStateKey('columns'));
    $this->webform->deleteState($this->getStateKey('sort'));
    $this->webform->deleteState($this->getStateKey('direction'));
    $this->webform->deleteState($this->getStateKey('limit'));
    $this->webform->deleteState($this->getStateKey('default'));
    $this->webform->deleteState($this->getStateKey('format'));
    drupal_set_message($this->t('The customized table has been reset.'));
  }

  /**
   * Get the state key for the custom data.
   *
   * @return string
   *   The state key for the custom data.
   */
  protected function getStateKey($name) {
    if ($source_entity = $this->sourceEntity) {
      return "results.custom.$name." . $source_entity->getEntityTypeId() . '.' . $source_entity->id();
    }
    else {
      return "results.custom.$name";
    }
  }

}