/**
 * @file
 * JavaScript behaviors for webform_image_select and jQuery Image Picker integration.
 */

(function ($, Drupal) {

  'use strict';

  // @see https://rvera.github.io/image-picker/
  Drupal.webform = Drupal.webform || {};
  Drupal.webform.imageSelect = Drupal.webform.imageSelect || {};
  Drupal.webform.imageSelect.options = Drupal.webform.imageSelect.options || {};

  /**
   * Initialize image select.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.webformImageSelect = {
    attach: function (context) {
      if (!$.fn.imagepicker) {
        return;
      }

      $('.js-webform-image-select', context).once('webform-image-select').each(function () {
        var $select = $(this);
        var isMultiple = $select.attr('multiple');

        // Apply image data to options.
        var images = JSON.parse($select.attr('data-images'));
        for (var value in images) {
          if (images.hasOwnProperty(value)) {
            var image = images[value];
            // Escape double quotes in value
            value = value.toString().replace(/"/g, '\\"');
            $select.find('option[value="' + value + '"]').attr({
              'data-img-src': image.src,
              'data-img-label': image.text,
              'data-img-alt': image.text
            });
          }
        }

        var options = $.extend({
          hide_select: false
        }, Drupal.webform.imageSelect.options);

        if ($select.attr('data-show-label')) {
          options.show_label = true;
        }

        $select.imagepicker(options);

        // Add very basic accessibility to the image picker by
        // enabling tabbing and toggling via the spacebar.
        // @see https://github.com/rvera/image-picker/issues/108

        if (isMultiple) {
          $select.next('.image_picker_selector').attr('role', 'radiogroup');
        }

        $select.next('.image_picker_selector').find('.thumbnail')
          .prop('tabindex', '0')
          .attr('role', isMultiple ? 'checkbox' : 'radio')
          .each(function() {
            $(this).attr('title', $(this).find('img').attr('alt'));
          })
          .on('focus', function(event) {
            $(this).addClass('focused');
          })
          .on('blur', function(event) {
            $(this).removeClass('focused');
          })
          .on('keydown', function(event) {
            if(event.which === 32) {
              // Space.
              $(this).click();
              event.preventDefault();
            }
            else if(event.which === 37 || event.which === 38) {
              // Left or Up.
              $(this).parent().prev().find('.thumbnail').focus();
              event.preventDefault();
            }
            else if(event.which === 39 || event.which === 40) {
              // Right or Down.
              $(this).parent().next().find('.thumbnail').focus();
              event.preventDefault();
            }
          })
          .on('click', function(event) {
            var selected = $(this).hasClass('selected');
            $(this).attr('aria-checked', selected);
          });
      });
    }
  };

})(jQuery, Drupal);
