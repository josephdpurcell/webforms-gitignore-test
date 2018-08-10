<?php

namespace Drupal\webform\Utility;

use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * Provides helper to operate on strings.
 */
class WebformTextHelper {

  /**
   * @var \Symfony\Component\Serializer\NameConverter\NameConverterInterface
   */
  static $converter;

  /**
   * Get camel case to snake case converter.
   *
   * @return \Symfony\Component\Serializer\NameConverter\NameConverterInterface
   *   Camel case to snake case converter.
   */
  protected static function getCamelCaseToSnakeCaseNameConverter() {
    if (!isset(static::$converter)) {
      static::$converter = new CamelCaseToSnakeCaseNameConverter();
    }
    return static::$converter;
  }

  /**
   * Converts camel case to snake case (i.e. underscores).
   *
   * @param string $string
   *   String to be converted.
   *
   * @return string
   *   String with camel case converted to snake case.
   */
  public static function camelToSnake($string) {
    return static::getCamelCaseToSnakeCaseNameConverter()->normalize($string);
  }

  /**
   * Converts snake case to camel case
   *
   * @param string $string
   *   String to be converted.
   *
   * @return string
   *   String with snake case converted to camel case.
   */
  public static function snakeToCamel($string) {
    return static::getCamelCaseToSnakeCaseNameConverter()->denormalize($string);
  }

}
