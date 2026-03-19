/**
 * SWPMail Public JS — AJAX subscription handler.
 *
 * @package SWPMail
 * @since   1.0.0
 */
(function ($) {
  "use strict";

  $(document).on("submit", ".swpm-subscribe-form", function (e) {
    e.preventDefault();

    var $form = $(this);
    var $btn = $form.find(".swpm-btn--subscribe");
    var $msg = $form.find(".swpm-message");
    var originalText = $btn.text();

    // Disable button.
    $btn.prop("disabled", true).text(swpmPublic.i18n.subscribing);
    $msg
      .removeClass("swpm-message--success swpm-message--error")
      .text("")
      .hide();

    $.ajax({
      url: swpmPublic.ajaxUrl,
      type: "POST",
      data: $form.serialize() + "&action=swpm_subscribe",
      dataType: "json",
      success: function (response) {
        if (response.success) {
          $msg
            .addClass("swpm-message--success")
            .text(response.data.message)
            .show();
          $form.find('input[type="email"], input[type="text"]').val("");
        } else {
          $msg
            .addClass("swpm-message--error")
            .text(response.data.message || swpmPublic.i18n.error)
            .show();
        }
      },
      error: function () {
        $msg.addClass("swpm-message--error").text(swpmPublic.i18n.error).show();
      },
      complete: function () {
        $btn.prop("disabled", false).text(originalText);
      },
    });
  });
})(jQuery);
