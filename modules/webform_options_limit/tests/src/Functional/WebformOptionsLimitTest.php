<?php

namespace Drupal\Tests\webform_options_limit\Functional;

use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\Tests\webform\Functional\WebformBrowserTestBase;

/**
 * Webform options limit test.
 *
 * @group webform_browser
 */
class WebformOptionsLimitTest extends WebformBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'webform',
    'webform_options_limit',
    'webform_options_limit_test',
  ];


  /**
   * Test entity print.
   */
  public function testEntityPrint() {
    $this->drupalLogin($this->rootUser);

    $webform = Webform::load('test_handler_options_limit');
    $sid = $this->postSubmissionTest($webform);
    $this->assertRaw('hi');
  }

}
