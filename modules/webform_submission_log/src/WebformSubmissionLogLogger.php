<?php

namespace Drupal\webform_submission_log;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Psr\Log\LoggerInterface;

/**
 * Logger that listens for 'webform_submission' channel.
 */
class WebformSubmissionLogLogger implements LoggerInterface {

  use RfcLoggerTrait;
  use StringTranslationTrait;

  /**
   * Name of the table where log entries are stored.
   */
  const TABLE = 'webform_submission_log';

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The message's placeholders parser.
   *
   * @var \Drupal\Core\Logger\LogMessageParserInterface
   */
  protected $parser;

  /**
   * WebformSubmissionLog constructor.
   *
   * @param \Drupal\Core\Database\Connection $datababse
   *   The database service.
   * @param \Drupal\Core\Logger\LogMessageParserInterface $parser
   *   The log message parser service.
   */
  public function __construct(Connection $datababse, LogMessageParserInterface $parser) {
    $this->database = $datababse;
    $this->parser = $parser;
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []) {
    // Only log the 'webform_submission' channel.
    if ($context['channel'] !== 'webform_submission') {
      return;
    }

    // Make sure the context contains a webform submission.
    if (!isset($context['webform_submission'])) {
      return;
    }

    /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
    $webform_submission = $context['webform_submission'];

    // Make sure webform submission log is enabled.
    if (!$webform_submission->getWebform()->hasSubmissionLog()) {
      return;
    }

    // Set default values.
    $context += [
      'handler_id' => '',
      'operation' => '',
      'data' => [],
    ];

    // Cast message to string.
    $message = (string) $message;
    $message_placeholders = $this->parser->parseMessagePlaceholders($message, $context);

    $this->database->insert(self::TABLE)
      ->fields([
        'webform_id' => $webform_submission->getWebform()->id(),
        'sid' => $webform_submission->id(),
        'handler_id' => $context['handler_id'],
        'operation' => $context['operation'],
        'uid' => $context['uid'],
        'message' => $message,
        'variables' => serialize($message_placeholders),
        'data' => serialize($context['data']),
        'timestamp' => $context['timestamp'],
      ])
      ->execute();
  }

}
