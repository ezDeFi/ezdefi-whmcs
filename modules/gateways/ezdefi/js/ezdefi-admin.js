jQuery(function($) {
  var selectors = {
    apiUrlInput: 'input[name="field[apiUrl]"]',
    apiKeyInput: 'input[name="field[apiKey]"]',
    publicKeyInput: 'input[name="field[publicKey]"]'
  };

  var whmcs_ezdefi_admin = function() {
    this.ezdefiData = JSON.parse($('#ezdefi-data').text());
    this.unpaid_invoices = this.ezdefiData.unpaid_invoices;
    this.configUrl = this.ezdefiData.config_url;
    this.adminUrl = this.ezdefiData.admin_url;
    this.systemUrl = this.ezdefiData.system_url;

    var init = this.init.bind(this);

    init();
  };

  whmcs_ezdefi_admin.prototype.init = function() {
    var self = this;

    this.addManageExceptionBtn.call(this);

    self.form = $('#Payment-Gateway-Config-ezdefi').closest('form');

    self.form.find(selectors.apiKeyInput).attr('autocomplete', 'off');

    self.initValidation();

    var onChangeApiUrl = this.onChangeApiUrl.bind(this);
    var onChangeApiKey = this.onChangeApiKey.bind(this);
    var onChangePublicKey = this.onChangePublicKey.bind(this);
    var beforeSubmitForm = this.beforeSubmitForm.bind(this);

    $(self.form)
      .on('submit', null, beforeSubmitForm)
      .on('keyup', selectors.apiUrlInput, onChangeApiUrl)
      .on('change', selectors.apiKeyInput, onChangeApiKey)
      .on('change', selectors.publicKeyInput, onChangePublicKey);
  };

  whmcs_ezdefi_admin.prototype.addManageExceptionBtn = function() {
    var settingsTable = $('#Payment-Gateway-Config-ezdefi');

    var exceptionRow = $(
      "<tr><td class='fieldlabel'>Manage Exceptions</td><td class='fieldarea'></td></tr>"
    );
    settingsTable.find('tr:last').before(exceptionRow);

    var btn = $(
      "<a href='' class='openModalBtn'><img src='" +
        this.adminUrl +
        "images/icons/browser.png' alt=''> Open Exception Table</a>"
    );
    btn.appendTo(exceptionRow.find('.fieldarea'));
  };

  whmcs_ezdefi_admin.prototype.initValidation = function() {
    var self = this;

    this.form.validate({
      ignore: [],
      errorElement: 'span',
      errorClass: 'error',
      errorPlacement: function(error, element) {
        error.appendTo(element.closest('td'));
      },
      highlight: function(element) {
        $(element)
          .closest('td')
          .addClass('form-invalid');
      },
      unhighlight: function(element) {
        $(element)
          .closest('td')
          .removeClass('form-invalid');
      },
      rules: {
        'field[name]': {
          required: true,
        },
        'field[apiUrl]': {
          required: true,
          url: true
        },
        'field[apiKey]': {
          required: true
        },
        'field[publicKey]': {
          required: true
        }
      }
    });
  };

  whmcs_ezdefi_admin.prototype.onChangeApiUrl = function(e) {
    var input = $(e.currentTarget);
    if(input.val().length === 0) {
      input.attr('placeholder', 'https://merchant-api.ezdefi.com/api/');
    }
  };

  whmcs_ezdefi_admin.prototype.onChangeApiKey = function(e) {
    var self = this;
    var $input = $(e.target);
    $input.rules('add', {
      remote: {
        url: self.configUrl,
        type: 'POST',
        data: {
          action: 'check_api_key',
          api_url: function() {
            return self.form.find('input[name="field[apiUrl]"]').val();
          },
          api_key: function() {
            return self.form.find('input[name="field[apiKey]"]').val();
          }
        },
        beforeSend: function() {
          self.form.find(selectors.apiKeyInput).addClass('pending');
        },
        complete: function(data) {
          self.form.find(selectors.apiKeyInput).removeClass('pending');
          var response = data.responseText;
          var $inputWrapper = self.form.find(selectors.apiKeyInput).closest('td');
          if (response === 'true') {
            $inputWrapper.append('<span class="correct">Correct</span>');
            window.setTimeout(function() {
              $inputWrapper.find('.correct').remove();
            }, 1000);
          }
        }
      },
      messages: {
        remote: 'API Key is not correct. Please check again'
      }
    });
  };

  whmcs_ezdefi_admin.prototype.onChangePublicKey = function(e) {
    var self = this;
    var $input = $(e.target);
    $input.rules('add', {
      remote: {
        url: self.configUrl,
        type: 'POST',
        data: {
          action: 'check_public_key',
          api_url: function() {
            return self.form.find('input[name="field[apiUrl]"]').val();
          },
          api_key: function() {
            return self.form.find('input[name="field[apiKey]"]').val();
          },
          public_key: function() {
            return self.form.find('input[name="field[publicKey]"]').val();
          }
        },
        beforeSend: function() {
          self.form.find(selectors.publicKeyInput).addClass('pending');
        },
        complete: function(data) {
          self.form.find(selectors.publicKeyInput).removeClass('pending');
          var response = data.responseText;
          var $inputWrapper = self.form.find(selectors.publicKeyInput).closest('td');
          if (response === 'true') {
            $inputWrapper.append('<span class="correct">Correct</span>');
            window.setTimeout(function() {
              $inputWrapper.find('.correct').remove();
            }, 1000);
          }
        }
      },
      messages: {
        remote: "Website' ID is not correct. Please check again"
      }
    });
  };

  whmcs_ezdefi_admin.prototype.beforeSubmitForm = function(e) {
    var self = this;
    if(this.form.valid()) {
      $.ajax({
        url: self.configUrl,
        type: 'POST',
        data: {
          action: 'save_callback_url',
          api_key: self.form.find(selectors.apiKeyInput).val(),
          website_id: self.form.find(selectors.publicKeyInput).val(),
          callback_url: self.systemUrl + 'modules/gateways/callback/ezdefi.php'
        },
        beforeSend: function() {
          self.form.find('input[type="submit"]').prop('disabled', true);
        }
      }).done(function() {
        return true
      })
    }
  };

  new whmcs_ezdefi_admin();
});
