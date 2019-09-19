/**
 * @file
 * JavaScript behaviors for webform options limit integration.
 */

(function ($, Drupal) {

  'use strict';

  /**
   * Initialize webform options limit select.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.webformOptionsLimit = {
    attach: function (context) {
      $('select[data-webform-options-limit-disabled]', context).once('webform-options-limit').each(function () {
        var $select = $(this);
        var disabled = $select.data('webform-options-limit-disabled');
        $select.find('option').filter(function isDisabled() {
          return disabled[this.value] ? true : false;
        }).attr('disabled', 'disabled');
      });
    }
  };

})(jQuery, Drupal);
