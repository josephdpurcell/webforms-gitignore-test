<?php

namespace Drupal\webform\Plugin\WebformElement;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformElementBase;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Provides a 'captcha' element.
 *
 * @WebformElement(
 *   id = "captcha",
 *   default_key = "captcha",
 *   api = "https://www.drupal.org/project/captcha",
 *   label = @Translation("CAPTCHA"),
 *   description = @Translation("Provides a form element that determines whether the user is human."),
 *   category = @Translation("Advanced elements"),
 *   states_wrapper = TRUE,
 * )
 */
class Captcha extends WebformElementBase {

  /**
   * {@inheritdoc}
   */
  public function getDefaultProperties() {
    return [
      // Captcha settings.
      'captcha_type' => 'default',
      'captcha_admin_mode' => FALSE,
      'captcha_title' => '',
      'captcha_description' => '',
      // Flexbox.
      'flex' => 1,
      // Conditional logic.
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isInput(array $element) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isContainer(array $element) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getItemDefaultFormat() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getItemFormats() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function prepare(array &$element, WebformSubmissionInterface $webform_submission = NULL) {
    // Hide and solve the element if the user is assigned 'skip CAPTCHA'
    // and '#captcha_admin_mode' is not enabled.
    $is_admin = \Drupal::currentUser()->hasPermission('skip CAPTCHA');
    if ($is_admin && empty($element['#captcha_admin_mode'])) {
      $element['#access'] = FALSE;
      $element['#captcha_admin_mode'] = TRUE;
    }

    // Always enable admin mode for test.
    $is_test = (strpos(\Drupal::routeMatch()->getRouteName(), '.webform.test_form') !== FALSE) ? TRUE : FALSE;
    if ($is_test) {
      $element['#captcha_admin_mode'] = TRUE;
    }

    parent::prepare($element, $webform_submission);

    $element['#after_build'][] = [get_class($this), 'afterBuildCaptcha'];
  }

  /**
   * {@inheritdoc}
   */
  public function preview() {
    $element = parent::preview() + [
      '#captcha_admin_mode' => TRUE,
      // Define empty form id to prevent fatal error when preview is
      // rendered via Ajax.
      // @see \Drupal\captcha\Element\Captcha::processCaptchaElement
      '#captcha_info' => ['form_id' => ''],
    ];
    if (\Drupal::moduleHandler()->moduleExists('image_captcha')) {
      $element['#captcha_type'] = 'image_captcha/Image';
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(array &$element, WebformSubmissionInterface $webform_submission) {
    // Remove all captcha related keys from the webform submission's data.
    $key = $element['#webform_key'];
    $data = $webform_submission->getData();
    unset($data[$key]);
    // @see \Drupal\captcha\Element\Captcha
    $sub_keys = ['sid', 'token', 'response'];
    foreach ($sub_keys as $sub_key) {
      unset($data[$key . '_' . $sub_key]);
    }
    $webform_submission->setData($data);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    // Issue #3090624: Call to undefined function trying to add CAPTCHA
    // element to form.
    // @see _captcha_available_challenge_types();
    // @see \Drupal\captcha\Service\CaptchaService::getAvailableChallengeTypes
    $captcha_types = [];
    $captcha_types['default'] = t('Default challenge type');
    // We do our own version of Drupal's module_invoke_all() here because
    // we want to build an array with custom keys and values.
    foreach (\Drupal::moduleHandler()->getImplementations('captcha') as $module) {
      $result = call_user_func_array($module . '_captcha', ['list']);
      if (is_array($result)) {
        foreach ($result as $type) {
          $captcha_types["$module/$type"] = t('@type (from module @module)', [
            '@type' => $type,
            '@module' => $module,
          ]);
        }
      }
    }

//    $captcha_types = ['default' => $this->t('Default challenge type')];
//    if (\Drupal::moduleHandler()->moduleExists('captcha')) {
//      if (\Drupal::hasService('captcha.helper')) {
//        /** @var \Drupal\captcha\Service\CaptchaService $captcha_helper */
//        $captcha_helper = \Drupal::service('captcha.helper');
//        $captcha_types = $captcha_helper->getAvailableChallengeTypes() ?: $captcha_types;
//      }
//      else {
//        // @todo Webform 8.x-6.x: Remove the below code.
//        // Issue #3086495: Remove deprecated _captcha_available_challenge_types
//        // function.
//        // @see https://www.drupal.org/project/captcha/issues/3086495
//        module_load_include('inc', 'captcha', 'captcha.admin');
//        if (function_exists('_captcha_available_challenge_types')) {
//          $captcha_types = _captcha_available_challenge_types();
//        }
//      }
//    }

    $form['captcha'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('CAPTCHA settings'),
    ];
    $form['captcha']['captcha_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Challenge type'),
      '#required' => TRUE,
      '#options' => $captcha_types,
    ];
    // Custom title and description.
    $form['captcha']['captcha_container'] = [
      '#type' => 'container',
      '#states' => [
        'invisible' => [[':input[name="properties[captcha_type]"]' => ['value' => 'recaptcha/reCAPTCHA']]],
      ],
    ];
    $form['captcha']['captcha_container']['captcha_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Question title'),
    ];
    $form['captcha']['captcha_container']['captcha_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Question description'),
    ];
    // Admin mode.
    $form['captcha']['captcha_admin_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Admin mode'),
      '#description' => $this->t('Presolve the CAPTCHA and always shows it. This is useful for debugging and preview CAPTCHA integration.'),
      '#return_value' => TRUE,
    ];
    return $form;
  }

  /**
   * After build handler for CAPTCHA elements.
   */
  public static function afterBuildCaptcha(array $element, FormStateInterface $form_state) {
    // Make sure that the CAPTCHA response supports #title.
    if (isset($element['captcha_widgets'])
      && isset($element['captcha_widgets']['captcha_response'])
      && isset($element['captcha_widgets']['captcha_response']['#title'])) {
      if (!empty($element['#captcha_title'])) {
        $element['captcha_widgets']['captcha_response']['#title'] = $element['#captcha_title'];
      }
      if (!empty($element['#captcha_description'])) {
        $element['captcha_widgets']['captcha_response']['#description'] = $element['#captcha_description'];
      }
    }
    return $element;
  }

}
