<?php

namespace Drupal\Tests\webform_entity_print_attachment\Functional;

use Drupal\Tests\webform_entity_print\Functional\WebformEntityPrintFunctionalTestBase;
use Drupal\webform\Entity\Webform;

/**
 * Webform entity print attachment test.
 *
 * @group webform_browser
 */
class WebformEntityPrintAttachmentFunctionalTest extends WebformEntityPrintFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['webform_entity_print_attachment_test'];

  /**
   * Test entity print attachment.
   */
  public function testEntityPrintAttachment() {
    $webform = Webform::load('test_entity_print_attachment');

    $this->drupalLogin($this->rootUser);

    /**************************************************************************/

    // Check that the PDF attachment is added to the sent email.
    $this->postSubmission($webform);
    $sent_email = $this->getLastEmail();
    $this->assertEqual($sent_email['params']['attachments'][0]['filename'], 'entity_print_pdf_html.pdf', "The PDF attachment's file name");
    $this->assertEqual($sent_email['params']['attachments'][0]['filemime'], 'application/pdf', "The PDF attachment's file mime type");
    $this->assertEqual($sent_email['params']['attachments'][0]['filecontent'], 'Using testprintengine', "The attachment's file content");
  }

}
