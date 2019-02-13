<?php

namespace Drupal\webform\Plugin\WebformExporter;

use Drupal\Component\Serialization\Yaml;
use Drupal\webform\Plugin\WebformExporterBase;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Defines a machine readable CSV export that can be imported back into the current webform.
 *
 * @WebformExporter(
 *   id = "export",
 *   label = @Translation("Export"),
 *   description = @Translation("Exports results in CSV that can be imported back into the current webform."),
 *   archive = TRUE,
 *   options = FALSE,
 * )
 */
class ExportWebformExporter extends WebformExporterBase {

  use FileHandleTraitWebformExporter;

  /**
   * An array containing webform element names.
   *
   * @var array
   */
  protected $elements;

  /**
   * An array containing a webform's field definition names.
   *
   * @var array
   */
  protected $fieldDefinitions;

  /**
   * {@inheritdoc}
   */
  public function getFileExtension() {
    return 'csv';
  }

  /**
   * {@inheritdoc}
   */
  public function writeHeader() {
    $header = array_merge(
      $this->getFieldDefinitions(),
      $this->getElements()
    );
    fputcsv($this->fileHandle, $header, ',');
  }

  /**
   * {@inheritdoc}
   */
  public function writeSubmission(WebformSubmissionInterface $webform_submission) {
    $submission = $webform_submission->toArray(TRUE);
    $data = $submission['data'];

    $record = [];

    // Append fields.
    $field_definitions = $this->getFieldDefinitions();
    foreach ($field_definitions as $field_name) {
      $record[] = (isset($submission[ $field_name])) ? $submission[ $field_name] : '';
    }
    // Append elements.
    $elements = $this->getElements();
    foreach ($elements as $element_name) {
      if (isset($data[$element_name])) {
        $record[] = (is_array($data[$element_name])) ? Yaml::encode($data[$element_name]) : $data[$element_name];
      }
      else {
        $record[] = '';
      }
    }

    fputcsv($this->fileHandle, $record, ',');
  }

  /****************************************************************************/
  // Webform definitions and elements.
  /****************************************************************************/

  /**
   * Get a webform's field definitions.
   *
   * @return array
   *   An associative array containing a webform's field definitions.
   */
  protected function getFieldDefinitions() {
    if (isset($this->fieldDefinitions)) {
      return $this->fieldDefinitions;
    }

    $this->fieldDefinitions = array_keys($this->entityStorage->getFieldDefinitions());
    return $this->fieldDefinitions ;
  }

  /**
   * Get webform elements.
   *
   * @return array
   *   An associative array containing webform elements keyed by name.
   */
  protected function getElements() {
    if (isset($this->elements)) {
      return $this->elements;
    }

    $this->elements = array_keys($this->getWebform()->getElementsInitializedFlattenedAndHasValue('view'));
    return $this->elements;
  }

}
