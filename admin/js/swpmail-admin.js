/**
 * SWPMail Admin JS
 *
 * @package SWPMail
 * @since   1.0.0
 */
(function ($) {
  "use strict";

  /**
   * Safe notification helper — uses a temporary admin notice instead of alert().
   * Ensures server messages are rendered as text, not HTML.
   *
   * @param {string} message Plain text message.
   * @param {string} type    'error' or 'success' (default: 'error').
   */
  function swpmNotice(message, type) {
    type = type || "error";
    var cls = type === "success" ? "notice-success" : "notice-error";
    var $notice = $(
      '<div class="notice ' +
        cls +
        ' is-dismissible"><p></p><button type="button" class="notice-dismiss"></button></div>',
    );
    $notice.find("p").text(message);
    $notice.find(".notice-dismiss").on("click", function () {
      $notice.fadeOut(200, function () {
        $(this).remove();
      });
    });
    $(".wrap h1, .wrap h2").first().after($notice);
    setTimeout(function () {
      $notice.fadeOut(200, function () {
        $(this).remove();
      });
    }, 6000);
  }

  /**
   * Provider card grid: select provider on click.
   */
  $(document).on("click", ".swpm-provider-option", function () {
    var $card = $(this);
    var provider = $card.data("provider");

    // Update active state.
    $(".swpm-provider-option").removeClass("active");
    $card.addClass("active");

    // Update hidden input.
    $("#swpm-provider-select").val(provider);

    // Hide all provider field groups, show selected.
    $(".swpm-provider-fields").slideUp(200);
    $(".swpm-" + provider + "-fields").slideDown(200);
  });

  /**
   * Legacy fallback: if a <select> with id exists, still handle change.
   */
  $(document).on("change", "select#swpm-provider-select", function () {
    var provider = $(this).val();
    $(".swpm-provider-fields").slideUp(200);
    $(".swpm-" + provider + "-fields").slideDown(200);
  });

  /**
   * Test Connection button.
   */
  $(document).on("click", "#swpm-test-connection", function () {
    var $btn = $(this);
    var $result = $("#swpm-test-result");
    var recipient = $("#swpm-test-recipient").val() || "";

    $btn.prop("disabled", true).text(swpmAdmin.i18n.testing);
    $result.removeClass("swpm-test-success swpm-test-error").text("");

    $.ajax({
      url: swpmAdmin.ajaxUrl,
      type: "POST",
      data: {
        action: "swpm_test_connection",
        nonce: swpmAdmin.nonce,
        recipient: recipient,
      },
      dataType: "json",
      success: function (response) {
        if (response.success) {
          $result.addClass("swpm-test-success").text(response.data.message);
        } else {
          $result
            .addClass("swpm-test-error")
            .text(swpmAdmin.i18n.testFailed + (response.data.message || ""));
        }
      },
      error: function () {
        $result
          .addClass("swpm-test-error")
          .text(swpmAdmin.i18n.testFailed + "Request failed.");
      },
      complete: function () {
        $btn.prop("disabled", false).text("Send Test Email");
      },
    });
  });

  /**
   * Template Editor: Save template via AJAX.
   */
  $(document).on("click", "#swpm-save-template", function () {
    var $btn = $(this);
    var $result = $("#swpm-template-result");
    var templateId = $("#swpm-template-id").val();
    var content = "";

    // Get content from CodeMirror instance or textarea.
    if (window.swpmCodeMirrorInstance) {
      content = window.swpmCodeMirrorInstance.getValue();
    } else {
      content = $("#swpm-template-editor").val();
    }

    $btn.prop("disabled", true);
    $result.text("");

    $.ajax({
      url: swpmAdmin.ajaxUrl,
      type: "POST",
      data: {
        action: "swpm_save_template",
        nonce: swpmAdmin.nonce,
        template_id: templateId,
        locale: $("#swpm-template-locale").val() || "",
        content: content,
      },
      dataType: "json",
      success: function (response) {
        if (response.success) {
          $result
            .removeClass("swpm-result-error")
            .addClass("swpm-result-success")
            .text(response.data.message);
        } else {
          $result
            .removeClass("swpm-result-success")
            .addClass("swpm-result-error")
            .text(response.data.message || "Save failed.");
        }
      },
      error: function () {
        $result
          .removeClass("swpm-result-success")
          .addClass("swpm-result-error")
          .text("Request failed.");
      },
      complete: function () {
        $btn.prop("disabled", false);
      },
    });
  });

  /**
   * Template Editor: Reset to default via AJAX.
   */
  $(document).on("click", "#swpm-reset-template", function () {
    var $result = $("#swpm-template-result");
    var templateId = $("#swpm-template-id").val();

    $.ajax({
      url: swpmAdmin.ajaxUrl,
      type: "POST",
      data: {
        action: "swpm_reset_template",
        nonce: swpmAdmin.nonce,
        template_id: templateId,
        locale: $("#swpm-template-locale").val() || "",
      },
      dataType: "json",
      success: function (response) {
        if (response.success) {
          // Update editor content.
          if (window.swpmCodeMirrorInstance) {
            window.swpmCodeMirrorInstance.setValue(response.data.content || "");
          } else {
            $("#swpm-template-editor").val(response.data.content || "");
          }
          $result
            .removeClass("swpm-result-error")
            .addClass("swpm-result-success")
            .text(response.data.message);
        } else {
          $result
            .removeClass("swpm-result-success")
            .addClass("swpm-result-error")
            .text(response.data.message || "Reset failed.");
        }
      },
    });
  });

  /**
   * Template Editor: Preview template via AJAX.
   */
  $(document).on("click", "#swpm-preview-template", function () {
    var $btn = $(this);
    var templateId = $("#swpm-template-id").val();
    var content = "";

    if (window.swpmCodeMirrorInstance) {
      content = window.swpmCodeMirrorInstance.getValue();
    } else {
      content = $("#swpm-template-editor").val();
    }

    $btn.prop("disabled", true);

    $.ajax({
      url: swpmAdmin.ajaxUrl,
      type: "POST",
      data: {
        action: "swpm_preview_template",
        nonce: swpmAdmin.nonce,
        template_id: templateId,
        locale: $("#swpm-template-locale").val() || "",
        content: content,
      },
      dataType: "json",
      success: function (response) {
        if (response.success) {
          var $modal = $("#swpm-preview-modal");
          var iframe = document.getElementById("swpm-preview-iframe");

          $modal.fadeIn(200);
          $("body").css("overflow", "hidden");

          // Write HTML into sandboxed iframe via srcdoc (no allow-same-origin needed).
          iframe.srcdoc = response.data.html;

          // Reset to desktop view.
          $(".swpm-preview-device").removeClass("active");
          $(".swpm-preview-device[data-device='desktop']").addClass("active");
          $("#swpm-preview-iframe").css("max-width", "100%");
        } else {
          swpmNotice(response.data.message || "Preview failed.");
        }
      },
      error: function () {
        swpmNotice("Preview request failed.");
      },
      complete: function () {
        $btn.prop("disabled", false);
      },
    });
  });

  /**
   * Preview Modal: Close.
   */
  $(document).on(
    "click",
    "#swpm-preview-close, .swpm-preview-modal__backdrop",
    function () {
      $("#swpm-preview-modal").fadeOut(200);
      $("body").css("overflow", "");
    },
  );

  /**
   * Preview Modal: Device toggle (desktop / mobile).
   */
  $(document).on("click", ".swpm-preview-device", function () {
    var device = $(this).data("device");
    $(".swpm-preview-device").removeClass("active");
    $(this).addClass("active");

    if (device === "mobile") {
      $("#swpm-preview-iframe").css("max-width", "375px");
    } else {
      $("#swpm-preview-iframe").css("max-width", "100%");
    }
  });

  /* ── New Template: Open / Close Modal ── */

  $(document).on("click", "#swpm-new-template-btn", function () {
    $("#swpm-new-template-modal").fadeIn(200);
    $("body").css("overflow", "hidden");
    $("#swpm-new-tpl-name").val("").focus();
    $("#swpm-new-tpl-vars").val("");
  });

  $(document).on(
    "click",
    ".swpm-new-template-close, #swpm-new-template-modal > .swpm-preview-modal__backdrop",
    function () {
      $("#swpm-new-template-modal").fadeOut(200);
      $("body").css("overflow", "");
    },
  );

  /* ── New Template: Create via AJAX ── */

  $(document).on("click", "#swpm-create-template-submit", function () {
    var $btn = $(this);
    var name = $.trim($("#swpm-new-tpl-name").val());
    var vars = $.trim($("#swpm-new-tpl-vars").val());

    if (!name) {
      swpmNotice("Please enter a template name.");
      return;
    }

    $btn.prop("disabled", true);

    $.ajax({
      url: swpmAdmin.ajaxUrl,
      type: "POST",
      data: {
        action: "swpm_create_template",
        nonce: swpmAdmin.nonce,
        label: name,
        variables: vars,
      },
      dataType: "json",
      success: function (response) {
        if (response.success) {
          // Redirect to the new template editor.
          window.location.href = response.data.redirect;
        } else {
          swpmNotice(response.data.message || "Could not create template.");
        }
      },
      error: function () {
        swpmNotice("Request failed.");
      },
      complete: function () {
        $btn.prop("disabled", false);
      },
    });
  });

  /* ── Delete Custom Template ── */

  $(document).on("click", "#swpm-delete-template", function () {
    var templateId = $(this).data("template");
    var label = $(this).data("label") || templateId;

    if (
      !confirm('Delete custom template "' + label + '"? This cannot be undone.')
    ) {
      return;
    }

    $.ajax({
      url: swpmAdmin.ajaxUrl,
      type: "POST",
      data: {
        action: "swpm_delete_template",
        nonce: swpmAdmin.nonce,
        template_id: templateId,
      },
      dataType: "json",
      success: function (response) {
        if (response.success) {
          // Redirect back to template list (first template).
          window.location.href =
            response.data.redirect ||
            window.location.pathname + "?page=swpmail-templates";
        } else {
          swpmNotice(response.data.message || "Could not delete template.");
        }
      },
      error: function () {
        swpmNotice("Request failed.");
      },
    });
  });

  /* ── New Trigger: Open / Close Modal ── */

  $(document).on("click", "#swpm-new-trigger-btn", function () {
    $("#swpm-new-trigger-modal").fadeIn(200);
    $("body").css("overflow", "hidden");
    $("#swpm-new-trg-label").val("").focus();
    $("#swpm-new-trg-hook").val("");
    $("#swpm-new-trg-hook-args").val("1");
    $("#swpm-new-trg-template").val("");
    $("#swpm-new-trg-subject").val("");
    $("#swpm-new-trg-recipients").val("subscribers");
  });

  $(document).on(
    "click",
    ".swpm-new-trigger-close, #swpm-new-trigger-modal > .swpm-preview-modal__backdrop",
    function () {
      $("#swpm-new-trigger-modal").fadeOut(200);
      $("body").css("overflow", "");
    },
  );

  /* ── New Trigger: Create via AJAX ── */

  $(document).on("click", "#swpm-create-trigger-submit", function () {
    var $btn = $(this);
    var label = $.trim($("#swpm-new-trg-label").val());
    var hook = $.trim($("#swpm-new-trg-hook").val());
    var hookArgs = parseInt($("#swpm-new-trg-hook-args").val(), 10) || 1;
    var templateId = $("#swpm-new-trg-template").val();
    var subject = $.trim($("#swpm-new-trg-subject").val());
    var recipientType = $("#swpm-new-trg-recipients").val();

    if (!label || !hook || !templateId || !subject) {
      swpmNotice("Please fill in all required fields.");
      return;
    }

    $btn.prop("disabled", true);

    $.ajax({
      url: swpmAdmin.ajaxUrl,
      type: "POST",
      data: {
        action: "swpm_create_trigger",
        nonce: swpmAdmin.nonce,
        label: label,
        hook: hook,
        hook_args: hookArgs,
        template_id: templateId,
        subject_template: subject,
        recipient_type: recipientType,
      },
      dataType: "json",
      success: function (response) {
        if (response.success) {
          window.location.reload();
        } else {
          swpmNotice(response.data.message || "Could not create trigger.");
        }
      },
      error: function () {
        swpmNotice("Request failed.");
      },
      complete: function () {
        $btn.prop("disabled", false);
      },
    });
  });

  /* ── Delete Custom Trigger ── */

  $(document).on("click", ".swpm-delete-trigger-btn", function () {
    var key = $(this).data("key");
    var label = $(this).data("label") || key;

    if (
      !confirm('Delete custom trigger "' + label + '"? This cannot be undone.')
    ) {
      return;
    }

    $.ajax({
      url: swpmAdmin.ajaxUrl,
      type: "POST",
      data: {
        action: "swpm_delete_trigger",
        nonce: swpmAdmin.nonce,
        trigger_key: key,
      },
      dataType: "json",
      success: function (response) {
        if (response.success) {
          window.location.reload();
        } else {
          swpmNotice(response.data.message || "Could not delete trigger.");
        }
      },
      error: function () {
        swpmNotice("Request failed.");
      },
    });
  });

  /* ── Escape key: close any open modal ── */

  $(document).on("keydown", function (e) {
    if (e.key === "Escape") {
      if ($("#swpm-preview-modal").is(":visible")) {
        $("#swpm-preview-modal").fadeOut(200);
        $("body").css("overflow", "");
      }
      if ($("#swpm-new-template-modal").is(":visible")) {
        $("#swpm-new-template-modal").fadeOut(200);
        $("body").css("overflow", "");
      }
      if ($("#swpm-new-trigger-modal").is(":visible")) {
        $("#swpm-new-trigger-modal").fadeOut(200);
        $("body").css("overflow", "");
      }
    }
  });

  /* ── OAuth: Connect (start authorization flow) ── */

  $(document).on("click", ".swpm-oauth-connect", function () {
    var $btn = $(this);
    var provider = $btn.data("provider");

    $btn.prop("disabled", true).addClass("swpm-btn--loading");

    $.ajax({
      url: swpmAdmin.ajaxUrl,
      type: "POST",
      data: {
        action: "swpm_oauth_start",
        nonce: swpmAdmin.nonce,
        provider: provider,
      },
      dataType: "json",
      success: function (response) {
        if (response.success && response.data.redirect_url) {
          window.location.href = response.data.redirect_url;
        } else {
          swpmNotice(response.data.message || "OAuth start failed.");
          $btn.prop("disabled", false).removeClass("swpm-btn--loading");
        }
      },
      error: function () {
        swpmNotice("OAuth request failed.");
        $btn.prop("disabled", false).removeClass("swpm-btn--loading");
      },
    });
  });

  /* ── OAuth: Disconnect ── */

  $(document).on("click", ".swpm-oauth-disconnect", function () {
    var $btn = $(this);
    var provider = $btn.data("provider");

    if (
      !confirm(swpmAdmin.i18n.oauthDisconnectConfirm || "Disconnect OAuth?")
    ) {
      return;
    }

    $btn.prop("disabled", true);

    $.ajax({
      url: swpmAdmin.ajaxUrl,
      type: "POST",
      data: {
        action: "swpm_oauth_disconnect",
        nonce: swpmAdmin.nonce,
        provider: provider,
      },
      dataType: "json",
      success: function (response) {
        if (response.success) {
          window.location.reload();
        } else {
          swpmNotice(response.data.message || "Disconnect failed.");
          $btn.prop("disabled", false);
        }
      },
      error: function () {
        swpmNotice("Request failed.");
        $btn.prop("disabled", false);
      },
    });
  });

  /* ── OAuth notice auto-dismiss ── */

  $(document).ready(function () {
    $(".swpm-notice").each(function () {
      var $notice = $(this);
      setTimeout(function () {
        $notice.slideUp(300, function () {
          $notice.remove();
        });
      }, 8000);
    });
  });

  /* ====================================================================
     Failover – Health Check Buttons
     ==================================================================== */
  $(document).on("click", ".swpm-health-check-btn", function () {
    var $btn = $(this);
    var slot = $btn.data("slot");
    var $result = $btn
      .closest(".swpm-connection-slot__actions")
      .find(".swpm-health-check-result");

    $btn.prop("disabled", true);
    $result
      .removeClass("swpm-health-ok swpm-health-fail")
      .text(swpmAdmin.i18n.checking || "Checking…");

    $.ajax({
      url: swpmAdmin.ajaxUrl,
      type: "POST",
      data: {
        action: "swpm_health_check",
        nonce: swpmAdmin.nonce,
        slot: slot,
      },
      dataType: "json",
      success: function (response) {
        if (response.success) {
          $result.addClass("swpm-health-ok").text("✓ Healthy");
        } else {
          $result
            .addClass("swpm-health-fail")
            .text("✗ " + (response.data.message || "Unhealthy"));
        }
      },
      error: function () {
        $result.addClass("swpm-health-fail").text("✗ Request failed");
      },
      complete: function () {
        $btn.prop("disabled", false);
      },
    });
  });

  /* ====================================================================
     Failover – Connection Status Refresh
     ==================================================================== */
  $(document).on("click", "#swpm-refresh-status", function () {
    var $btn = $(this);
    $btn.prop("disabled", true);

    $.ajax({
      url: swpmAdmin.ajaxUrl,
      type: "POST",
      data: {
        action: "swpm_get_connection_status",
        nonce: swpmAdmin.nonce,
      },
      dataType: "json",
      success: function (response) {
        if (response.success) {
          window.location.reload();
        }
      },
      complete: function () {
        $btn.prop("disabled", false);
      },
    });
  });

  /* ====================================================================
     DNS Domain Checker
     ==================================================================== */

  /**
   * Render DNS check results into the page.
   */
  function swpmRenderDnsResults(data) {
    var $container = $("#swpm-dns-ajax-results");
    var html = "";

    // Overall score banner.
    var overallIcon = {
      pass: "yes-alt",
      warning: "warning",
      fail: "dismiss",
    };
    var overallText = {
      pass: swpmAdmin.i18n.dnsAllPassed || "All checks passed",
      warning: swpmAdmin.i18n.dnsSomeIssues || "Some issues found",
      fail: swpmAdmin.i18n.dnsCritical || "Critical issues detected",
    };

    html +=
      '<div class="swpm-dns-overall swpm-dns-overall--' + data.overall + '">';
    html +=
      '<div class="swpm-dns-overall__icon"><span class="dashicons dashicons-' +
      overallIcon[data.overall] +
      '"></span></div>';
    html +=
      '<div class="swpm-dns-overall__text"><strong>' +
      overallText[data.overall] +
      "</strong> ";
    html +=
      '<span class="swpm-dns-overall__domain">' + data.domain + "</span></div>";
    html += "</div>";

    // Render each record type.
    var records = [
      {
        key: "spf",
        label: "SPF",
        sub: "Sender Policy Framework",
        data: data.spf,
      },
      {
        key: "dkim",
        label: "DKIM",
        sub: "DomainKeys Identified Mail",
        data: data.dkim,
      },
      {
        key: "dmarc",
        label: "DMARC",
        sub: "Domain-based Message Authentication",
        data: data.dmarc,
      },
    ];

    $.each(records, function (_, rec) {
      html += '<div class="swpm-card swpm-dns-record-card">';
      html += '<div class="swpm-dns-record-header">';
      html +=
        '<div class="swpm-dns-record-badge swpm-dns-record-badge--' +
        rec.data.status +
        '">' +
        rec.data.status.toUpperCase() +
        "</div>";
      html +=
        "<h3>" +
        rec.label +
        ' <span class="swpm-dns-record-subtitle">(' +
        rec.sub +
        ")</span></h3>";
      html += "</div>";

      // Raw record.
      if (rec.data.record) {
        html +=
          '<div class="swpm-dns-record-raw"><code>' +
          $("<span>").text(rec.data.record).html() +
          "</code></div>";
      }

      // DKIM specific records.
      if (rec.data.records && rec.data.records.length) {
        $.each(rec.data.records, function (_, dkim) {
          html += '<div class="swpm-dns-dkim-entry">';
          html +=
            '<span class="swpm-dns-dkim-selector">' + dkim.selector + "</span>";
          html += '<span class="swpm-dns-dkim-type">' + dkim.type + "</span>";
          var val =
            dkim.value.length > 120
              ? dkim.value.substring(0, 120) + "…"
              : dkim.value;
          html +=
            '<code class="swpm-dns-record-raw-inline">' +
            $("<span>").text(val).html() +
            "</code>";
          html += "</div>";
        });
      }

      // DMARC policy.
      if (rec.data.policy) {
        html +=
          '<div class="swpm-dns-dmarc-policy">Policy: <strong>' +
          rec.data.policy +
          "</strong></div>";
      }

      // Warnings.
      if (rec.data.warnings) {
        $.each(rec.data.warnings, function (_, w) {
          html +=
            '<div class="swpm-dns-msg swpm-dns-msg--warning"><span class="dashicons dashicons-warning"></span> ' +
            $("<span>").text(w).html() +
            "</div>";
        });
      }

      // Details.
      if (rec.data.details) {
        $.each(rec.data.details, function (_, d) {
          html +=
            '<div class="swpm-dns-msg swpm-dns-msg--info"><span class="dashicons dashicons-info-outline"></span> ' +
            $("<span>").text(d).html() +
            "</div>";
        });
      }

      html += "</div>";
    });

    $container.html(html);
    $("#swpm-dns-results").hide();
  }

  /**
   * Handle "Check DNS" button click.
   */
  $(document).on("click", "#swpm-dns-check-btn", function () {
    var $btn = $(this);
    var domain = $.trim($("#swpm-dns-domain").val());

    if (!domain) {
      swpmNotice(
        swpmAdmin.i18n.dnsEnterDomain || "Please enter a domain name.",
      );
      return;
    }

    $btn.prop("disabled", true).addClass("swpm-btn--loading");

    $.ajax({
      url: swpmAdmin.ajaxUrl,
      type: "POST",
      data: {
        action: "swpm_dns_check",
        nonce: swpmAdmin.nonce,
        domain: domain,
      },
      dataType: "json",
      success: function (response) {
        if (response.success) {
          swpmRenderDnsResults(response.data);
        } else {
          swpmNotice(response.data.message || "DNS check failed.");
        }
      },
      error: function () {
        swpmNotice("Request failed.");
      },
      complete: function () {
        $btn.prop("disabled", false).removeClass("swpm-btn--loading");
      },
    });
  });

  /**
   * Handle "Re-check My Domain" button click.
   */
  $(document).on("click", "#swpm-dns-auto-check-btn", function () {
    var $btn = $(this);
    $btn.prop("disabled", true).addClass("swpm-btn--loading");

    $.ajax({
      url: swpmAdmin.ajaxUrl,
      type: "POST",
      data: {
        action: "swpm_dns_auto_check",
        nonce: swpmAdmin.nonce,
      },
      dataType: "json",
      success: function (response) {
        if (response.success) {
          swpmRenderDnsResults(response.data);
        } else {
          swpmNotice(response.data.message || "DNS check failed.");
        }
      },
      error: function () {
        swpmNotice("Request failed.");
      },
      complete: function () {
        $btn.prop("disabled", false).removeClass("swpm-btn--loading");
      },
    });
  });

  /**
   * Allow Enter key in DNS domain input.
   */
  $(document).on("keypress", "#swpm-dns-domain", function (e) {
    if (e.which === 13) {
      e.preventDefault();
      $("#swpm-dns-check-btn").trigger("click");
    }
  });

  /**
   * Initialize CodeMirror if available.
   */
  /* ==========================================================================
     Smart Routing — Condition Builder UI
     ========================================================================== */

  var routingRules = window.swpmRoutingRules || [];
  var $rulesList = $("#swpm-rules-list");
  var $rulesEmpty = $("#swpm-rules-empty");

  function routingInit() {
    if (!$rulesList.length) return;
    renderAllRules();
    bindRoutingEvents();
  }

  function generateId() {
    return "r_" + Math.random().toString(36).substr(2, 8);
  }

  function renderAllRules() {
    $rulesList.empty();
    if (!routingRules.length) {
      $rulesEmpty.show();
      return;
    }
    $rulesEmpty.hide();
    $.each(routingRules, function (i, rule) {
      renderRule(rule);
    });
  }

  function renderRule(rule) {
    var tmpl = $("#tmpl-swpm-rule").html();
    var html = tmpl
      .replace(/\{\{data\.id\}\}/g, rule.id)
      .replace(/\{\{data\.name\}\}/g, escAttr(rule.name || ""))
      .replace(/\{\{data\.priority\}\}/g, rule.priority || 50)
      .replace(/\{\{data\.enabledAttr\}\}/g, rule.enabled ? "checked" : "");

    var $rule = $(html);

    // Set provider dropdown.
    $rule.find(".swpm-rule__provider").val(rule.provider || "");

    // Render conditions.
    var $condList = $rule.find(".swpm-rule__conditions-list");
    if (rule.conditions && rule.conditions.length) {
      $.each(rule.conditions, function (j, cond) {
        var $cond = renderCondition(cond);
        $condList.append($cond);
      });
    }

    $rulesList.append($rule);
  }

  function renderCondition(cond) {
    var tmpl = $("#tmpl-swpm-condition").html();
    var html = tmpl.replace(/\{\{data\.value\}\}/g, escAttr(cond.value || ""));
    var $el = $(html);
    $el.find(".swpm-condition__field").val(cond.field || "to");
    $el.find(".swpm-condition__operator").val(cond.operator || "contains");
    return $el;
  }

  function escAttr(s) {
    return $("<span>").text(s).html();
  }

  function collectRulesFromDOM() {
    var rules = [];
    $rulesList.find(".swpm-rule").each(function () {
      var $r = $(this);
      var conditions = [];
      $r.find(".swpm-condition").each(function () {
        var $c = $(this);
        conditions.push({
          field: $c.find(".swpm-condition__field").val(),
          operator: $c.find(".swpm-condition__operator").val(),
          value: $c.find(".swpm-condition__value").val(),
        });
      });
      rules.push({
        id: $r.data("rule-id"),
        name: $r.find(".swpm-rule__name").val(),
        enabled: $r.find(".swpm-rule__enabled").is(":checked"),
        priority: parseInt($r.find(".swpm-rule__priority").val(), 10) || 50,
        provider: $r.find(".swpm-rule__provider").val(),
        conditions: conditions,
      });
    });
    return rules;
  }

  function bindRoutingEvents() {
    // Add rule.
    $("#swpm-add-rule").on("click", function () {
      var newRule = {
        id: generateId(),
        name: "",
        enabled: true,
        priority: 50,
        provider: "",
        conditions: [{ field: "to", operator: "contains", value: "" }],
      };
      routingRules.push(newRule);
      $rulesEmpty.hide();
      renderRule(newRule);
      $rulesList.find(".swpm-rule:last .swpm-rule__name").focus();
    });

    // Add condition.
    $rulesList.on("click", ".swpm-rule__add-condition", function () {
      var $condList = $(this).siblings(".swpm-rule__conditions-list");
      var $cond = renderCondition({
        field: "to",
        operator: "contains",
        value: "",
      });
      $condList.append($cond);
      $cond.find(".swpm-condition__value").focus();
    });

    // Remove condition.
    $rulesList.on("click", ".swpm-condition__remove", function () {
      $(this).closest(".swpm-condition").remove();
    });

    // Delete rule.
    $rulesList.on("click", ".swpm-rule__delete", function () {
      if (!confirm(swpmAdmin.i18n.confirmDelete || "Delete this rule?")) return;
      $(this)
        .closest(".swpm-rule")
        .fadeOut(200, function () {
          $(this).remove();
          routingRules = collectRulesFromDOM();
          if (!routingRules.length) $rulesEmpty.show();
        });
    });

    // Collapse/expand rule conditions.
    $rulesList.on("click", ".swpm-rule__collapse", function () {
      var $conditions = $(this)
        .closest(".swpm-rule")
        .find(".swpm-rule__conditions");
      $conditions.slideToggle(150);
      $(this).toggleClass("dashicons-arrow-up-alt2 dashicons-arrow-down-alt2");
    });

    // Save rules.
    $("#swpm-save-rules").on("click", function () {
      var $btn = $(this);
      var rules = collectRulesFromDOM();
      var enabledVal = $("#swpm-routing-toggle").is(":checked") ? "1" : "0";

      $btn.prop("disabled", true).text(swpmAdmin.i18n.saving || "Saving…");

      $.post(swpmAdmin.ajaxUrl, {
        action: "swpm_save_routing_rules",
        nonce: swpmAdmin.nonce,
        rules: JSON.stringify(rules),
        enabled: enabledVal,
      })
        .done(function (res) {
          if (res.success) {
            routingRules = res.data.rules || rules;
            showRoutingNotice("success", res.data.message);
          } else {
            showRoutingNotice("error", res.data.message || "Save failed.");
          }
        })
        .fail(function () {
          showRoutingNotice("error", "Network error.");
        })
        .always(function () {
          $btn
            .prop("disabled", false)
            .text(swpmAdmin.i18n.routingSave || "Save Rules");
        });
    });

    // Toggle.
    $("#swpm-routing-toggle").on("change", function () {
      var val = $(this).is(":checked") ? "1" : "";
      $.post(swpmAdmin.ajaxUrl, {
        action: "swpm_save_routing_toggle",
        nonce: swpmAdmin.nonce,
        enabled: val,
      });
    });

    // Test routing.
    $("#swpm-test-route-btn").on("click", function () {
      var $btn = $(this);
      var $result = $("#swpm-route-test-result");

      $btn.prop("disabled", true);
      $result.hide();

      $.post(swpmAdmin.ajaxUrl, {
        action: "swpm_test_routing",
        nonce: swpmAdmin.nonce,
        to: $("#swpm-test-to").val(),
        subject: $("#swpm-test-subject").val(),
        from: $("#swpm-test-from").val(),
        source: $("#swpm-test-source").val(),
      })
        .done(function (res) {
          if (res.success) {
            var d = res.data;
            var cls = d.routed
              ? "swpm-route-result--routed"
              : "swpm-route-result--default";
            var icon = d.routed ? "yes-alt" : "minus";
            var esc = function (s) {
              return $("<span>").text(s).html();
            };
            var matchedText = d.matched_rules.length
              ? "<br><small>" +
                esc(swpmAdmin.i18n.routingMatchedRules || "Matched rules") +
                ": " +
                esc(d.matched_rules.join(", ")) +
                "</small>"
              : "";
            $result
              .html(
                '<div class="swpm-route-result ' +
                  cls +
                  '">' +
                  '<span class="dashicons dashicons-' +
                  icon +
                  '"></span> ' +
                  "<strong>" +
                  esc(d.provider_name) +
                  "</strong> (" +
                  esc(d.provider_key) +
                  ")" +
                  matchedText +
                  "</div>",
              )
              .slideDown(150);
          } else {
            $result
              .html(
                '<div class="swpm-route-result swpm-route-result--error">' +
                  $("<span>")
                    .text(res.data.message || "Test failed.")
                    .html() +
                  "</div>",
              )
              .slideDown(150);
          }
        })
        .fail(function () {
          $result
            .html(
              '<div class="swpm-route-result swpm-route-result--error">Network error.</div>',
            )
            .slideDown(150);
        })
        .always(function () {
          $btn.prop("disabled", false);
        });
    });
  }

  function showRoutingNotice(type, message) {
    var cls =
      type === "success"
        ? "swpm-routing-notice--success"
        : "swpm-routing-notice--error";
    var $notice = $(
      '<div class="swpm-routing-notice ' + cls + '">' + message + "</div>",
    );
    $rulesList.before($notice);
    setTimeout(function () {
      $notice.fadeOut(300, function () {
        $(this).remove();
      });
    }, 3000);
  }

  $(document).ready(function () {
    routingInit();
  });

  /* ========================================================================== */
  /* ALARM NOTIFICATIONS                                                        */
  /* ========================================================================== */

  function alarmInit() {
    var $wrap = $(".swpm-wrap");
    if (!$wrap.find("#swpm-alarm-save").length) return;

    // Save alarm settings.
    $wrap.on("click", "#swpm-alarm-save", function () {
      var $btn = $(this);
      var $spinner = $("#swpm-alarm-spinner");
      var $msg = $("#swpm-alarm-message");

      var enabledChannels = [];
      $wrap
        .find('input[name="swpm_alarm_channel[]"]:checked')
        .each(function () {
          enabledChannels.push($(this).val());
        });

      var enabledEvents = [];
      $wrap.find('input[name="swpm_alarm_event[]"]:checked').each(function () {
        enabledEvents.push($(this).val());
      });

      var data = {
        action: "swpm_save_alarm_channels",
        nonce: swpmAdmin.nonce,
        enabled_channels: enabledChannels,
        enabled_events: enabledEvents,
        cooldown: $("#swpm-alarm-cooldown").val(),
        slack_webhook: $wrap.find('[name="slack_webhook"]').val(),
        discord_webhook: $wrap.find('[name="discord_webhook"]').val(),
        teams_webhook: $wrap.find('[name="teams_webhook"]').val(),
        twilio_sid: $wrap.find('[name="twilio_sid"]').val(),
        twilio_token: $wrap.find('[name="twilio_token"]').val(),
        twilio_from: $wrap.find('[name="twilio_from"]').val(),
        twilio_to: $wrap.find('[name="twilio_to"]').val(),
        custom_webhook: $wrap.find('[name="custom_webhook"]').val(),
        custom_secret: $wrap.find('[name="custom_secret"]').val(),
      };

      $btn.prop("disabled", true);
      $spinner.addClass("is-active");
      $msg.hide();

      $.post(swpmAdmin.ajaxUrl, data, function (res) {
        $btn.prop("disabled", false);
        $spinner.removeClass("is-active");
        $msg
          .text(res.data)
          .removeClass("swpm-alert--success swpm-alert--error")
          .addClass(res.success ? "swpm-alert--success" : "swpm-alert--error")
          .show();
      }).fail(function () {
        $btn.prop("disabled", false);
        $spinner.removeClass("is-active");
        $msg
          .text("Request failed.")
          .removeClass("swpm-alert--success")
          .addClass("swpm-alert--error")
          .show();
      });
    });

    // Test alarm channel.
    $wrap.on("click", ".swpm-alarm-test-btn", function () {
      var $btn = $(this);
      var channel = $btn.data("channel");
      var $status = $wrap.find(
        '.swpm-alarm-test-status[data-channel="' + channel + '"]',
      );

      $btn.prop("disabled", true);
      $status
        .text(swpmAdmin.i18n.testing || "Testing...")
        .removeClass("swpm-alarm-test--success swpm-alarm-test--error");

      $.post(
        swpmAdmin.ajaxUrl,
        {
          action: "swpm_test_alarm_channel",
          nonce: swpmAdmin.nonce,
          channel: channel,
        },
        function (res) {
          $btn.prop("disabled", false);
          $status
            .text(res.data)
            .addClass(
              res.success
                ? "swpm-alarm-test--success"
                : "swpm-alarm-test--error",
            );
        },
      ).fail(function () {
        $btn.prop("disabled", false);
        $status.text("Request failed.").addClass("swpm-alarm-test--error");
      });
    });
  }

  $(document).ready(function () {
    alarmInit();
  });

  /* ========================================================================== */
  /* WP-CONFIG.PHP CONSTANTS — READONLY FIELDS                                  */
  /* ========================================================================== */

  function constantsInit() {
    var defined =
      typeof swpmAdmin !== "undefined" && swpmAdmin.definedConstants
        ? swpmAdmin.definedConstants
        : [];

    if (!defined.length) return;

    var badgeHtml =
      '<span class="swpm-const-badge">' +
      '<span class="dashicons dashicons-lock"></span> ' +
      (swpmAdmin.i18n.constDefined || "Defined in wp-config.php") +
      "</span>";

    $.each(defined, function (_, optionKey) {
      // Handle provider selection grid.
      if (optionKey === "swpm_mail_provider") {
        $(".swpm-provider-grid")
          .addClass("swpm-const-locked")
          .before(
            '<div class="swpm-const-notice">' +
              '<span class="dashicons dashicons-lock"></span> ' +
              (swpmAdmin.i18n.constProviderNotice ||
                "Mail provider is locked via wp-config.php.") +
              "</div>",
          );
        $(".swpm-provider-option").css("pointer-events", "none");
        $("#swpm-provider-select").prop("disabled", true);
        return;
      }

      // Handle backup provider select.
      if (optionKey === "swpm_backup_provider") {
        var $backupSelect = $('select[name="swpm_backup_provider"]');
        if ($backupSelect.length) {
          $backupSelect.prop("disabled", true);
          $backupSelect.closest("td").append(badgeHtml);
        }
        return;
      }

      // Find form elements by name.
      var $el = $('[name="' + optionKey + '"]');

      if (!$el.length) return;

      var tagName = $el.prop("tagName").toLowerCase();
      var type = ($el.attr("type") || "").toLowerCase();

      if (type === "checkbox") {
        $el.prop("disabled", true);
        $el.closest("label").after(" " + badgeHtml);
      } else if (tagName === "select") {
        $el.prop("disabled", true);
        $el.closest("td").append(badgeHtml);
      } else {
        // text, email, url, number, password, tel
        $el.prop("readonly", true).addClass("swpm-const-readonly");
        $el
          .closest("td")
          .find(".description")
          .first()
          .before(badgeHtml + " ");
        // If no description, append to td.
        if (!$el.closest("td").find(".swpm-const-badge").length) {
          $el.after(" " + badgeHtml);
        }
      }
    });
  }

  $(document).ready(function () {
    constantsInit();
  });

  /* ========================================================================== */

  $(document).ready(function () {
    if (
      typeof wp !== "undefined" &&
      wp.codeEditor &&
      typeof swpmCodeMirror !== "undefined"
    ) {
      var $textarea = $("#swpm-template-editor");
      if ($textarea.length) {
        var editor = wp.codeEditor.initialize($textarea, swpmCodeMirror);
        window.swpmCodeMirrorInstance = editor.codemirror;
      }
    }
  });

  /* ======================================================================
     EMAIL LOGS — Tracking Detail Modal
     ====================================================================== */

  $(document).ready(function () {
    var $modal = $("#swpm-log-detail-modal");
    if (!$modal.length) {
      return;
    }

    var esc = function (s) {
      return $("<span>")
        .text(s || "—")
        .html();
    };

    // Open modal.
    $(document).on("click", ".swpm-log-detail-link", function (e) {
      e.preventDefault();
      var queueId = $(this).data("id");
      if (!queueId) {
        return;
      }

      $modal.show();
      $("#swpm-log-detail-loading").show();
      $("#swpm-log-detail-content").hide();

      $.post(swpmAdmin.ajaxUrl, {
        action: "swpm_log_tracking_detail",
        nonce: swpmAdmin.nonce,
        queue_id: queueId,
      })
        .done(function (res) {
          if (!res.success) {
            $modal.hide();
            return;
          }

          var d = res.data;
          var q = d.queue;

          // Meta fields.
          $("#swpm-detail-to").html(esc(q.to_email));
          $("#swpm-detail-subject").html(esc(q.subject));
          $("#swpm-detail-provider").html(esc(q.provider_used));
          $("#swpm-detail-sent-at").html(esc(q.sent_at || q.created_at));

          // Status badge.
          var statusMap = {
            sent: "success",
            pending: "warning",
            sending: "info",
            failed: "danger",
          };
          var statusClass = statusMap[q.status] || "default";
          $("#swpm-detail-status").html(
            '<span class="swpm-log-badge swpm-log-badge--' +
              esc(statusClass) +
              '">' +
              esc(q.status) +
              "</span>",
          );

          // Error row.
          if (q.error_message) {
            $("#swpm-detail-error").html(esc(q.error_message));
            $("#swpm-detail-error-row").show();
          } else {
            $("#swpm-detail-error-row").hide();
          }

          // Tracking summary.
          $("#swpm-detail-opens").text(d.open_count);
          $("#swpm-detail-clicks").text(d.click_count);
          var linkCount = Object.keys(d.link_clicks).length;
          $("#swpm-detail-links").text(linkCount);

          // Link clicks table.
          var $linksBody = $("#swpm-detail-links-body").empty();
          if (linkCount > 0) {
            $.each(d.link_clicks, function (url, clicks) {
              $linksBody.append(
                "<tr><td>" +
                  '<a href="' +
                  esc(url) +
                  '" class="swpm-detail-link-url" target="_blank" rel="noopener noreferrer" title="' +
                  esc(url) +
                  '">' +
                  esc(url) +
                  "</a>" +
                  "</td><td>" +
                  esc(String(clicks)) +
                  "</td></tr>",
              );
            });
            $("#swpm-detail-links-section").show();
          } else {
            $("#swpm-detail-links-section").hide();
          }

          // Timeline events.
          var $timeline = $("#swpm-detail-events-list").empty();
          if (d.events.length > 0) {
            $.each(d.events, function (_, ev) {
              var isClick = ev.event_type === "click";
              var icon = isClick ? "admin-links" : "visibility";
              var typeClass = isClick ? "click" : "open";
              var title = isClick ? "Link Clicked" : "Email Opened";

              var urlHtml = "";
              if (isClick && ev.url) {
                urlHtml =
                  '<a href="' +
                  esc(ev.url) +
                  '" class="swpm-timeline-url" target="_blank" rel="noopener noreferrer" title="' +
                  esc(ev.url) +
                  '">' +
                  esc(ev.url) +
                  "</a>";
              }

              var meta = [];
              if (ev.ip_address) {
                meta.push(esc(ev.ip_address));
              }
              if (ev.user_agent) {
                meta.push(esc(ev.user_agent.substring(0, 60)));
              }

              $timeline.append(
                '<div class="swpm-timeline-event">' +
                  '<div class="swpm-timeline-icon swpm-timeline-icon--' +
                  typeClass +
                  '"><span class="dashicons dashicons-' +
                  icon +
                  '"></span></div>' +
                  '<div class="swpm-timeline-body">' +
                  '<span class="swpm-timeline-title">' +
                  esc(title) +
                  "</span>" +
                  urlHtml +
                  (meta.length
                    ? '<div class="swpm-timeline-meta">' +
                      meta.join(" &middot; ") +
                      "</div>"
                    : "") +
                  "</div>" +
                  '<div class="swpm-timeline-time">' +
                  esc(ev.created_at) +
                  "</div>" +
                  "</div>",
              );
            });
            $("#swpm-detail-events-section").show();
          } else {
            $("#swpm-detail-events-section").hide();
          }

          $("#swpm-log-detail-loading").hide();
          $("#swpm-log-detail-content").show();
        })
        .fail(function () {
          $modal.hide();
        });
    });

    // Close modal.
    $modal.on("click", ".swpm-modal-close, .swpm-modal-overlay", function () {
      $modal.hide();
    });

    // Close on Escape key.
    $(document).on("keydown", function (e) {
      if (e.key === "Escape" && $modal.is(":visible")) {
        $modal.hide();
      }
    });
  });

  /**
   * Export Subscribers CSV.
   */
  $(document).on("click", "#swpm-export-subscribers", function () {
    var $btn = $(this);
    $btn.prop("disabled", true);

    // Create a temporary form to do a POST download.
    var $form = $("<form>", {
      action: swpmAdmin.ajaxUrl,
      method: "POST",
    })
      .append(
        $("<input>", {
          type: "hidden",
          name: "action",
          value: "swpm_export_subscribers",
        }),
      )
      .append(
        $("<input>", { type: "hidden", name: "nonce", value: swpmAdmin.nonce }),
      )
      .appendTo("body");

    $form.submit().remove();
    setTimeout(function () {
      $btn.prop("disabled", false);
    }, 2000);
  });

  /**
   * Purge Old Logs.
   */
  $(document).on("click", "#swpm-purge-logs", function () {
    var days = parseInt($("#swpm-purge-age").val(), 10);
    if (
      !confirm(
        swpmAdmin.i18n.confirmPurge ||
          "Delete logs older than " + days + " days?",
      )
    ) {
      return;
    }

    var $btn = $(this);
    var $result = $("#swpm-purge-result");
    $btn.prop("disabled", true);
    $result.text("");

    $.post(swpmAdmin.ajaxUrl, {
      action: "swpm_purge_logs",
      nonce: swpmAdmin.nonce,
      days: days,
    })
      .done(function (resp) {
        if (resp.success) {
          $result.text(resp.data.message || "Done.").css("color", "#46b450");
          setTimeout(function () {
            location.reload();
          }, 1500);
        } else {
          $result.text(resp.data || "Failed.").css("color", "#dc3232");
        }
      })
      .fail(function () {
        $result.text("Request failed.").css("color", "#dc3232");
      })
      .always(function () {
        $btn.prop("disabled", false);
      });
  });
})(jQuery);
