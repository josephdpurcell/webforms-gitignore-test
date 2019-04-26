<?php

namespace Drupal\webform_entity_print\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformThirdPartySettingsManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides route responses for Webform Entity Print.
 */
class WebformEntityPrintController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The webform third party settings manager.
   *
   * @var \Drupal\webform\WebformThirdPartySettingsManagerInterface
   */
  protected $thirdPartySettingsManager;

  /**
   * Constructs a WebformEntityPrintController object.
   *
   * @param \Drupal\webform\WebformThirdPartySettingsManagerInterface $third_party_settings_manager
   *   The webform third party settings manager.
   */
  public function __construct(WebformThirdPartySettingsManagerInterface $third_party_settings_manager) {
    $this->thirdPartySettingsManager = $third_party_settings_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('webform.third_party_settings_manager')
    );
  }

  /**
   * Returns Webform Entity Print custom CSS.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\webform\WebformInterface $webform
   *   The webform.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response object.
   */
  public function css(Request $request, WebformInterface $webform) {
    $css = '';

    // Append default print template CSS.
    $default_template = $this->thirdPartySettingsManager->getThirdPartySetting('webform_entity_print', 'template') ?: [];
    if (!empty($default_template['css'])) {
      $css .= PHP_EOL . $default_template['css'];
    }

    // Append webform print template CSS.
    $webform_template = $webform->getThirdPartySetting('webform_entity_print', 'template') ?: [];
    if (!empty($webform_template['css'])) {
      $css .= PHP_EOL . $webform_template['css'];
    }

    return new Response(trim($css), 200, ['Content-Type' => 'text/css']);
  }

}
