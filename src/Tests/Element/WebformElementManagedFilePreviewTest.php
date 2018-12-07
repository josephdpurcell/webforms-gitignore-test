<?php

namespace Drupal\webform\Tests\Element;

use Drupal\webform\Entity\Webform;

/**
 * Test for webform element managed file preview.
 *
 * @group Webform
 */
class WebformElementManagedFilePreviewTest extends WebformElementManagedFileTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['file', 'image', 'webform'];

  /**
   * Webforms to load.
   *
   * @var array
   */
  protected static $testWebforms = ['test_element_managed_file_prev'];

  /**
   * Test image file upload.
   */
  public function testImageFileUpload() {
    global $base_url;

    // Check that anonymous users can not preview files.
    $this->drupalGet('webform/test_element_managed_file_prev/test');
    $this->assertRaw('<span data-drupal-selector="edit-webform-image-file-file-1-filename" class="file file--mime-image-gif file--image"> webform_image_file.gif</span>');
    $this->assertRaw('<span data-drupal-selector="edit-webform-audio-file-file-2-filename" class="file file--mime-audio-mpeg file--audio"> webform_audio_file.mp3</span>');
    $this->assertRaw('<span data-drupal-selector="edit-webform-video-file-file-3-filename" class="file file--mime-video-mp4 file--video"> webform_video_file.mp4</span>');
    $this->assertRaw('<span data-drupal-selector="edit-webform-document-file-file-4-filename" class="file file--mime-text-plain file--text"> webform_document_file.txt</span>');

    // Check that authenticated users can preview files.
    $this->drupalLogin($this->rootUser);
    $this->drupalGet('webform/test_element_managed_file_prev/test');
    $this->assertNoRaw('<span data-drupal-selector="edit-webform-image-file-file-1-filename" class="file file--mime-image-gif file--image"> ');
    $this->assertNoRaw('<span data-drupal-selector="edit-webform-audio-file-file-2-filename" class="file file--mime-audio-mpeg file--audio">');
    $this->assertNoRaw('<span data-drupal-selector="edit-webform-video-file-file-3-filename" class="file file--mime-video-mp4 file--video">');
    $this->assertNoRaw('<span data-drupal-selector="edit-webform-document-file-file-4-filename" class="file file--mime-text-plain file--text">');

    $this->assertRaw('<div data-drupal-selector="edit-webform-image-file-file-5-filename" class="webform-image-file-preview js-form-wrapper form-wrapper" id="edit-webform-image-file-file-5-filename">');
    $this->assertRaw('<a href="' . $base_url . '/system/files/webform/test_element_managed_file_prev/_sid_/webform_image_file_0.gif" class="js-webform-image-file-modal webform-image-file-modal">');

    $this->assertRaw('<div data-drupal-selector="edit-webform-audio-file-file-6-filename" class="webform-audio-file-preview js-form-wrapper form-wrapper" id="edit-webform-audio-file-file-6-filename">');
    $this->assertRaw('<source src="' . $base_url . '/system/files/webform/test_element_managed_file_prev/_sid_/webform_audio_file_0.mp3" type="audio/mpeg">');

    $this->assertRaw('<div data-drupal-selector="edit-webform-video-file-file-7-filename" class="webform-video-file-preview js-form-wrapper form-wrapper" id="edit-webform-video-file-file-7-filename">');
    $this->assertRaw('<source src="' . $base_url . '/system/files/webform/test_element_managed_file_prev/_sid_/webform_video_file_0.mp4" type="video/mp4">');

    $this->assertRaw('<div data-drupal-selector="edit-webform-document-file-file-8-filename" class="webform-document-file-preview js-form-wrapper form-wrapper" id="edit-webform-document-file-file-8-filename">');
    $this->assertRaw($base_url . '/system/files/webform/test_element_managed_file_prev/_sid_/webform_document_file_0.txt');
  }

}
