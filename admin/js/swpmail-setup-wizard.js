/**
 * SWPMail Setup Wizard JS
 *
 * @package SWPMail
 * @since   1.0.0
 */
(function ($) {
  "use strict";

  var state = {
    step: 1,
    provider: "",
    providerLabel: "",
    testPassed: false,
  };

  /* ================================================================
     Provider label map
     ================================================================ */
  var providerLabels = {
    phpmail: "PHP Mail (Default)",
    smtp: "Other SMTP",
    sendlayer: "SendLayer",
    smtpcom: "SMTP.com",
    gmail: "Gmail",
    outlook: "365 / Outlook",
    mailgun: "Mailgun",
    sendgrid: "SendGrid",
    postmark: "Postmark",
    brevo: "Brevo",
    ses: "Amazon SES",
    resend: "Resend",
    elasticemail: "Elastic Email",
    mailjet: "Mailjet",
    mailersend: "MailerSend",
    smtp2go: "SMTP2GO",
    sparkpost: "SparkPost",
    zoho: "Zoho Mail",
  };

  /* ================================================================
     Step navigation
     ================================================================ */
  function goToStep(step) {
    state.step = step;

    // Hide all panels.
    $(".swpm-wizard-panel").hide();
    $("#swpm-wizard-step-" + step)
      .css({ opacity: 0, transform: "translateY(10px)" })
      .show()
      .animate({ opacity: 1 }, 250)
      .css("transform", "translateY(0)");

    // Update step indicators.
    $(".swpm-wizard-step").each(function () {
      var s = $(this).data("step");
      $(this)
        .removeClass("active completed")
        .addClass(s < step ? "completed" : s === step ? "active" : "");
    });

    $(".swpm-wizard-step-connector").each(function () {
      var after = $(this).data("after");
      $(this).toggleClass("active", after < step);
    });

    // Populate step 3 summary.
    if (step === 3) {
      populateSummary();
    }
  }

  /* ================================================================
     Step 1 — Provider selection
     ================================================================ */
  $(document).on(
    "click",
    ".swpm-wizard-provider-grid .swpm-provider-option",
    function () {
      var $btn = $(this);
      state.provider = $btn.data("provider");
      state.providerLabel = providerLabels[state.provider] || state.provider;

      $(".swpm-wizard-provider-grid .swpm-provider-option").removeClass(
        "active",
      );
      $btn.addClass("active");
    },
  );

  /* ================================================================
     Step 2 — Show provider fields
     ================================================================ */
  function showProviderFields() {
    $(".swpm-wizard-provider-fields").hide();
    if (state.provider && state.provider !== "phpmail") {
      $(".swpm-wizard-pf-" + state.provider).show();
    }
  }

  /* ================================================================
     Step 3 — Populate summary
     ================================================================ */
  function populateSummary() {
    $("#swpm-wizard-summary-provider").text(state.providerLabel);
    $("#swpm-wizard-summary-from").text(
      ($("#swpm-wizard-from-name").val() || "—") +
        " <" +
        ($("#swpm-wizard-from-email").val() || "—") +
        ">",
    );

    // Reset test state.
    state.testPassed = false;
    $("#swpm-wizard-test-result").removeClass("success error").hide().text("");
    $("#swpm-wizard-finish").hide();
    $("#swpm-wizard-test-btn").show().prop("disabled", false);
  }

  /* ================================================================
     Collect form data for AJAX
     ================================================================ */
  function collectFormData() {
    var data = {
      action: "swpm_wizard_save_and_test",
      nonce: swpmWizard.nonce,
      provider: state.provider,
      from_name: $("#swpm-wizard-from-name").val(),
      from_email: $("#swpm-wizard-from-email").val(),
    };

    // Collect all inputs in the visible provider field section.
    var $section = $(".swpm-wizard-pf-" + state.provider);
    if ($section.length) {
      $section.find("input, select").each(function () {
        var name = $(this).attr("name");
        if (name) {
          data[name] = $(this).val();
        }
      });
    }

    return data;
  }

  /* ================================================================
     Navigation button handlers
     ================================================================ */

  // Step 1 → Step 2.
  $(document).on("click", "#swpm-wizard-next-1", function () {
    if (!state.provider) {
      alert(swpmWizard.i18n.selectProvider);
      return;
    }
    goToStep(2);
    showProviderFields();
  });

  // Step 2 → Step 1.
  $(document).on("click", "#swpm-wizard-back-2", function () {
    goToStep(1);
  });

  // Step 2 → Step 3.
  $(document).on("click", "#swpm-wizard-next-2", function () {
    goToStep(3);
  });

  // Step 3 → Step 2.
  $(document).on("click", "#swpm-wizard-back-3", function () {
    goToStep(2);
    showProviderFields();
  });

  /* ================================================================
     Test connection (Step 3)
     ================================================================ */
  $(document).on("click", "#swpm-wizard-test-btn", function () {
    var $btn = $(this);
    var $result = $("#swpm-wizard-test-result");

    $btn.prop("disabled", true).text(swpmWizard.i18n.testing);
    $result.removeClass("success error").hide().text("");

    $.ajax({
      url: swpmWizard.ajaxUrl,
      type: "POST",
      data: collectFormData(),
      dataType: "json",
      success: function (response) {
        if (response.success) {
          state.testPassed = true;
          $result.addClass("success").text(swpmWizard.i18n.testSuccess).show();
          $btn.hide();
          $("#swpm-wizard-finish").show();
        } else {
          $result
            .addClass("error")
            .text(
              swpmWizard.i18n.testFailed +
                (response.data && response.data.message
                  ? response.data.message
                  : ""),
            )
            .show();
        }
      },
      error: function () {
        $result
          .addClass("error")
          .text(swpmWizard.i18n.testFailed + "Request failed.")
          .show();
      },
      complete: function () {
        $btn.prop("disabled", false).text($btn.data("label"));
      },
    });
  });

  /* ================================================================
     Finish setup
     ================================================================ */
  $(document).on("click", "#swpm-wizard-finish", function () {
    window.location.href = swpmWizard.dashboardUrl;
  });

  /* ================================================================
     Skip wizard
     ================================================================ */
  $(document).on("click", ".swpm-wizard-skip", function (e) {
    e.preventDefault();

    $.ajax({
      url: swpmWizard.ajaxUrl,
      type: "POST",
      data: {
        action: "swpm_wizard_skip",
        nonce: swpmWizard.nonce,
      },
      dataType: "json",
      complete: function () {
        window.location.href = swpmWizard.dashboardUrl;
      },
    });
  });

  /* ================================================================
     Init
     ================================================================ */
  $(document).ready(function () {
    goToStep(1);
  });
})(jQuery);
