/**
 * @file
 * JavaScript behaviors for computed elements.
 */

(function ($, Drupal, debounce) {

  'use strict';

  Drupal.webform = Drupal.webform || {};
  Drupal.webform.computed = Drupal.webform.computed || {};
  Drupal.webform.computed.delay = Drupal.webform.computed.delay || 500;

  var computedElements = [];

  /**
   * Initialize computed elements.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.webformComputed = {
    attach: function (context) {
      // Attach behaviors to computed elements.
      $(context).find('.js-webform-computed').once('webform-computed').each(function () {
        // Get computed element and form.
        var $element = $(this);
        var $form = $element.closest('form');

        // Get unique id for computed element based on the element name
        // and form id.
        var id = $form.attr('id') + '-' + $element.find('input[type="hidden"]').attr('name');

        // Get elements that are used by the computed element.
        var elementKeys = $(this).data('webform-element-keys').split(',');
        if (!elementKeys) {
          return;
        }

        // Get computed element triggers.
        var inputs = [];
        $.each(elementKeys, function (i, key) {
          // Exact input match.
          inputs.push(':input[name="' + key + '"]');
          // Sub inputs. (aka #tree)
          inputs.push(':input[name^="' + key + '["]');
        });
        var triggers = inputs.join(',');

        // Track computed elements.
        computedElements.push({
          id: id,
          element: $element,
          form: $form,
          triggers: triggers
        });
      });

      // Initialize triggers for each computed element.
      $.each(computedElements, function (index, computedElement) {
        // Get trigger from the current context.
        var $triggers = $(context).find(computedElement.triggers);
        if (!$triggers.length) {
          return;
        }

        // Make sure triggers are within the computed element's form and only
        // initialized once.
        $triggers = computedElement.form.find($triggers)
          .once('webform-computed-triggers-' + computedElement.id);
        if (!$triggers.length) {
          return;
        }

        initializeTriggers(computedElement.element, $triggers);
      });

      /**
       * Initialize computed element triggers.
       *
       * @param {jQuery} $element
       *   An jQuery object containing the computed element.
       * @param {jQuery} $triggers
       *   An jQuery object containing the computed element triggers.
       */
      function initializeTriggers($element, $triggers) {
        // Add event handler to computed element triggers.
        $triggers.on('keyup change',
          debounce(triggerUpdate, Drupal.webform.computed.delay));

        // Track tabledrag events.
        var $draggable = $triggers.closest('tr.draggable');
        if ($draggable.length) {
          $draggable.find('.tabledrag-handle').on('mouseup pointerup touchend',
            debounce(triggerUpdate, Drupal.webform.computed.delay));
        }

        // Initialize computed element update which refreshes the displayed
        // value and accounts for any changes to the #default_value for a
        // computed element.
        triggerUpdate(true);

        function triggerUpdate(initialize) {
          // Prevent duplicate computations.
          // @see Drupal.behaviors.formSingleSubmit
          if (initialize !== true) {
            var formValues = $triggers.serialize();
            var previousValues = $element.attr('data-webform-computed-last');
            if (previousValues === formValues) {
              return;
            }
            $element.attr('data-webform-computed-last', formValues);
          }

          // Add loading class to computed wrapper.
          $element.find('.js-webform-computed-wrapper')
            .addClass('webform-computed-loading');

          // Trigger computation.
          $element.find('.js-form-submit').mousedown();
        }

      }

    }
  };

})(jQuery, Drupal, Drupal.debounce);
