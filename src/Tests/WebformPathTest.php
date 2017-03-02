<?php

namespace Drupal\webform\Tests;

use Drupal\Core\Serialization\Yaml;
use Drupal\webform\Entity\Webform;
use Drupal\webform\WebformInterface;

/**
 * Tests for webform path and page.
 *
 * @group Webform
 */
class WebformPathTest extends WebformTestBase {

  protected static $modules = ['path', 'webform'];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Create users.
    $this->createUsers();
  }

  /**
   * Tests YAML page and title.
   */
  public function testPaths() {
    $webform = Webform::create([
      'langcode' => 'en',
      'status' => WebformInterface::STATUS_OPEN,
      'id' => 'test_paths',
      'title' => 'test_paths',
      'elements' => Yaml::encode([
        'test' => ['#markup' => 'test'],
      ]),
    ]);
    $webform->save();

    // Check default system submit path.
    $this->drupalGet('webform/' . $webform->id());
    $this->assertResponse(200, 'Submit system path exists');

    // Check default alias submit path.
    $this->drupalGet('form/' . str_replace('_', '-', $webform->id()));
    $this->assertResponse(200, 'Submit URL alias exists');

    // Check default alias confirm path.
    $this->drupalGet('form/' . str_replace('_', '-', $webform->id()) . '/confirmation');
    $this->assertResponse(200, 'Confirm URL alias exists');

    // Check page hidden (ie access denied).
    $webform->setSettings(['page' => FALSE])->save();
    $this->drupalGet('webform/' . $webform->id());
    $this->assertResponse(403, 'Submit system path access denied');
    $this->drupalGet('form/' . str_replace('_', '-', $webform->id()));
    $this->assertResponse(403, 'Submit URL alias access denied');

    // Check hidden page visible to admin.
    $this->drupalLogin($this->adminWebformUser);
    $this->drupalGet('webform/' . $webform->id());
    $this->assertResponse(200, 'Submit system path access permitted');
    $this->drupalGet('form/' . str_replace('_', '-', $webform->id()));
    $this->assertResponse(200, 'Submit URL alias access permitted');
    $this->drupalLogout();

    // Check custom submit and confirm path.
    $webform->setSettings(['page_submit_path' => 'page_submit_path', 'page_confirm_path' => 'page_confirm_path'])->save();
    $this->drupalGet('page_submit_path');
    $this->assertResponse(200, 'Submit system path access permitted');
    $this->drupalGet('page_confirm_path');
    $this->assertResponse(200, 'Submit URL alias access permitted');

    // Check custom base path.
    $webform->setSettings([])->save();
    $this->drupalLogin($this->adminWebformUser);
    $this->drupalPostForm('admin/structure/webform/settings', ['page[default_page_base_path]' => 'base/path'], t('Save configuration'));
    $this->drupalGet('base/path/' . str_replace('_', '-', $webform->id()));
    $this->assertResponse(200, 'Submit URL alias with custom base path exists');
    $this->drupalGet('base/path/' . str_replace('_', '-', $webform->id()) . '/confirmation');
    $this->assertResponse(200, 'Confirm URL alias with custom base path exists');
  }

}
