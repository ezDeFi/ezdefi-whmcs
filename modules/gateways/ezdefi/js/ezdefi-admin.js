jQuery(function($) {
    var selectors = {
        amountIdCheckbox: 'input[name="field[simpleMethod]"]',
    };

    var whmcs_ezdefi_admin = function() {
        this.ezdefiData = JSON.parse($('#ezdefi-data').text());
        this.configUrl = this.ezdefiData.config_url;

        var init = this.init.bind(this);

        init();
    };

    whmcs_ezdefi_admin.prototype.init = function() {
        var self = this;

        this.addCurrencyTable();

        self.table = $('#ezdefi-currency-table');
        self.form = self.table.closest('form');

        var currency = self.ezdefiData.gateway_params.token;

        if(currency === '') {
            self.addDefaultCurrency();
        } else {
            self.renderCurrency.call(this, currency)
        }

        self.initValidation();
        self.initSort.call(this);

        self.table.find('select').each(function() {
           self.initCurrencySelect($(this));
        });

        var addCurrency = this.addCurrency.bind(this);
        var removeCurrency = this.removeCurrency.bind(this);
        var toggleEdit = this.toggleEdit.bind(this);
        var saveCurrency = self.saveCurrency.bind(this);
        var checkWalletAddress = this.checkWalletAddress.bind(this);
        var toggleAmountSetting = this.toggleAmountSetting.bind(this);

        self.toggleAmountSetting(this);

        $(self.form)
            .on('click', '.addBtn', addCurrency)
            .on('click', '.editBtn', toggleEdit)
            .on('click', '.cancelBtn', toggleEdit)
            .on('click', '.deleteBtn', removeCurrency)
            .on('click', '.saveBtn', saveCurrency)
            .on('keyup', '.wallet input', checkWalletAddress)
            .on('change', selectors.amountIdCheckbox, toggleAmountSetting);
    };

    whmcs_ezdefi_admin.prototype.addCurrencyTable = function() {
        var settingsTable = $('#Payment-Gateway-Config-ezdefi');

        var currencySettingRow = $("<tr><td class='fieldlabel'>Accepted Currency</td><td class='fieldarea'></td></tr>");
        settingsTable.find('tr:last').before(currencySettingRow);

        var table = $("<table id='ezdefi-currency-table'></table>")
        table.appendTo(currencySettingRow.find('.fieldarea'));

        var tableHead = $(
            "<thead>" +
            "<tr><th></th><th></th><th>Currency</th><th>Discount</th><th>Lifetime</th><th>Wallet</th><th>BC</th><th></th></tr>" +
            "</thead>"
        );
        tableHead.appendTo(table);

        var tableBody = $("<tbody></tbody>");
        tableBody.appendTo(table);

        var tableFoot = $(
            "<tfoot><tr><td colspan='6'><button class='saveBtn'>Save currency</button> <button class='addBtn'>Add currency</button></td></tr></tfoot>"
        );
        tableFoot.appendTo(table);
    };

    whmcs_ezdefi_admin.prototype.addDefaultCurrency = function() {
        var rows = $(
            "<tr class='editing'>" +
                "<td class='sortable-handle'><span><i class='fas fa-align-justify'></i></span></td>" +
                "<td class='logo'>" +
                    "<img src='https://s2.coinmarketcap.com/static/img/coins/64x64/2714.png' class='ezdefi-currency-logo'>" +
                "</td>" +
                "<td class='name'>" +
                    "<input class='currency-symbol' type='hidden' name='currency[0][symbol]' value='nusd'>" +
                    "<input class='currency-name' type='hidden' name='currency[0][name]' value='nusd'>" +
                    "<input class='currency-desc' type='hidden' name='currency[0][desc]' value='nusd desc'>" +
                    "<input class='currency-logo' type='hidden' name='currency[0][logo]' value='https://s2.coinmarketcap.com/static/img/coins/64x64/2714.png'>" +
                    "<div class='view'><span>Nusd</span></div>" +
                    "<div class='edit'><select name='currency[0][id]' class='select-select2'>" +
                        "<option value='nusd' selected='selected'>nusd</option>" +
                    "</select></div>" +
                "</td>" +
                "<td class='discount'><div class='view'></div><div class='edit'><input type='number' name='currency[0][discount]' /></div></td>" +
                "<td class='lifetime'><div class='view'></div><div class='edit'><input type='number' name='currency[0][lifetime]' /></div></td>" +
                "<td class='wallet'><div class='view'></div><div class='edit'><input type='text' name='currency[0][wallet]' /></div></td>" +
                "<td class='block_comfirm'><div class='view'></div><div class='edit'><input type='number' name='currency[0][block_confirm]' /></div></td>" +
                "<td>" +
                    "<div class='view'><a class='editBtn' href=''>Edit</a> <a class='deleteBtn' href=''>Delete</a></div>" +
                    "<div class='edit'><a class='cancelBtn' href=''>Cancel</a></div>" +
                "</td>" +
            "</tr>" +
            "<tr class='editing'>" +
                "<td class='sortable-handle'><span><i class='fas fa-align-justify'></i></span></td>" +
                "<td class='logo'>" +
                    "<img src='https://s2.coinmarketcap.com/static/img/coins/64x64/2714.png' class='ezdefi-currency-logo'>" +
                "</td>" +
                "<td class='name'>" +
                    "<input class='currency-symbol' type='hidden' name='currency[1][symbol]' value='ntf'>" +
                    "<input class='currency-name' type='hidden' name='currency[1][name]' value='ntf'>" +
                    "<input class='currency-desc' type='hidden' name='currency[1][desc]' value='ntf desc'>" +
                    "<input class='currency-logo' type='hidden' name='currency[0][logo]' value='https://s2.coinmarketcap.com/static/img/coins/64x64/2714.png'>" +
                    "<div class='view'><span>ntf</span></div>" +
                    "<div class='edit'><select name='currency[1][id]' class='select-select2'>" +
                    "<option value='ntf' selected='selected'>ntf</option>" +
                    "</select></div>" +
                "</td>" +
                "<td class='discount'><div class='view'></div><div class='edit'><input type='number' name='currency[1][discount]' /></div></td>" +
                "<td class='lifetime'><div class='view'></div><div class='edit'><input type='number' name='currency[1][lifetime]' /></div></td>" +
                "<td class='wallet'><div class='view'></div><div class='edit'><input type='text' name='currency[1][wallet]' /></div></td>" +
                "<td class='block_comfirm'><div class='view'></div><div class='edit'><input type='number' name='currency[1][block_confirm]' /></div></td>" +
                "<td>" +
                    "<div class='view'><a class='editBtn' href=''>Edit</a> <a class='deleteBtn' href=''>Delete</a></div>" +
                    "<div class='edit'><a class='cancelBtn' href=''>Cancel</a></div>" +
                "</td>" +
            "</tr>"
        );
        rows.appendTo(this.table.find('tbody'));
    };

    whmcs_ezdefi_admin.prototype.renderCurrency = function(currency) {
        var rows = '';
        for (var i = 0; i < currency.length; i++) {
            var config = currency[i];
            var row =
                "<tr>" +
                    "<td class='sortable-handle'><span><i class='fas fa-align-justify'></i></span></td>" +
                    "<td class='logo'>" +
                        "<img src='"+config['logo']+"' class='ezdefi-currency-logo'>" +
                    "</td>" +
                    "<td class='name'>" +
                        "<input class='currency-symbol' type='hidden' name='currency["+i+"][symbol]' value='"+config['symbol']+"'>" +
                        "<input class='currency-name' type='hidden' name='currency["+i+"][name]' value='"+config['name']+"'>" +
                        "<input class='currency-desc' type='hidden' name='currency["+i+"][desc]' value='"+config['desc']+"'>" +
                        "<input class='currency-logo' type='hidden' name='currency["+i+"][logo]' value='"+config['logo']+"'>" +
                        "<div class='view'><span>"+config['name']+"</span></div>" +
                        "<div class='edit'><select name='currency["+i+"][id]' class='select-select2'>" +
                            "<option value='"+config['symbol']+"' selected='selected'>"+config['symbol']+"</option>" +
                        "</select></div>" +
                    "</td>" +
                    "<td class='discount'><div class='view'><span>"+config['discount']+"</span></div><div class='edit'><input type='number' name='currency["+i+"][discount]' value='"+config['discount']+"' /></div></td>" +
                    "<td class='lifetime'><div class='view'><span>"+config['lifetime']+"</span></div><div class='edit'><input type='number' name='currency["+i+"][lifetime]' value='"+config['lifetime']+"' /></div></td>" +
                    "<td class='wallet'><div class='view'><span>"+config['wallet']+"</span></div><div class='edit'><input type='text' name='currency["+i+"][wallet]' value='"+config['wallet']+"' /></div></td>" +
                    "<td class='block_comfirm'><div class='view'><span>"+config['block_confirm']+"</span></div><div class='edit'><input type='number' name='currency["+i+"][block_confirm]' value='"+config['block_confirm']+"' /></div></td>" +
                    "<td>" +
                    "<div class='view'><a class='editBtn' href=''>Edit</a> <a class='deleteBtn' href=''>Delete</a></div>" +
                    "<div class='edit'><a class='cancelBtn' href=''>Cancel</a></div>" +
                    "</td>" +
                "</tr>";
            rows += row;
        }
        $(rows).appendTo(this.table.find('tbody'));
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
            rules: {
                'field[apiUrl]': {
                    required: true,
                    url: true
                },
                'field[apiKey]': {
                    required: true
                },
                'field[variation]': {
                    required: {
                        depends: function(element) {
                            return self.form.find(selectors.amountIdCheckbox).is(':checked');
                        }
                    },
                    number: true
                },
                'field[decimal]': {
                    required: {
                        depends: function(element) {
                            return self.form.find(selectors.amountIdCheckbox).is(':checked');
                        }
                    },
                    digits: true
                }

            }
        });

        this.table.find('tbody tr').each(function() {
            var row = $(this);
            self.addValidationRule(row);
        });
    };

    whmcs_ezdefi_admin.prototype.initSort = function() {
        var self = this;
        this.table.find('tbody').sortable({
            handle: '.sortable-handle span',
            stop: function() {
                $(this).find('tr').each(function (rowIndex) {
                    var row = $(this);
                    self.updateAttr(row, rowIndex)
                });
                $(this).find('tr .saveBtn').trigger('click');
            }
        }).disableSelection();
    };

    whmcs_ezdefi_admin.prototype.addValidationRule = function(row) {
        var self = this;
        row.find('input, select').each(function() {
            var name = $(this).attr('name');

            if(name.indexOf('discount') > 0) {
                $('input[name="'+name+'"]').rules('add', {
                    max: 100
                });
            }

            if(name.indexOf('name') > 0) {
                var $select = $('select[name="'+name+'"]');
                $select.rules('add', {
                    required: {
                        depends: function(element) {
                            return self.form.find('input[name="field[apiUrl]"]').val() !== '';
                        },
                    },
                    messages: {
                        required: 'Please select currency'
                    }
                });
                $select.on('select2:close', function () {
                    $(this).valid();
                });
            }

            if(name.indexOf('wallet') > 0) {
                var $input = $('input[name="'+name+'"]');
                $input.rules('add', {
                    required: true,
                    messages: {
                        required: 'Please enter wallet address'
                    }
                });
            }
        });
    };

    whmcs_ezdefi_admin.prototype.toggleAmountSetting = function() {
        var checked = this.form.find(selectors.amountIdCheckbox).is(':checked');
        var amount_settings = this.form.find(
            'input[name="field[variation]"], input[name="field[decimal]"], select[name="field[cronRecurrence]"]'
        ).closest('tr');
        if(checked) {
            amount_settings.each(function() {
                $(this).show();
            });
        } else {
            amount_settings.each(function() {
                $(this).hide();
            });
        }
    };

    whmcs_ezdefi_admin.prototype.checkWalletAddress = function(e) {
        var self = this;
        var apiUrl = self.form.find('input[name="field[apiUrl]"]').val();
        var apiKey = self.form.find('input[name="field[apiKey]"]').val();
        var $input = $(e.target);
        var $checking = $(
            "<div class='checking'><span class='text'>Checking wallet address</span>" +
            "<div class='dots'>" +
            "<div class='dot'></div>" +
            "<div class='dot'></div>" +
            "<div class='dot'></div>" +
            "</div>" +
            "</div>"
        );
        $input.rules('add', {
            remote: {
                depends: function(element) {
                    return apiUrl !== '' && apiKey !== '';
                },
                param: {
                    url: self.configUrl,
                    type: 'POST',
                    data: {
                        action: 'check_wallet',
                        address: function () {
                            return $input.val();
                        },
                        apiUrl: function() {
                            return apiUrl;
                        },
                        apiKey: function() {
                            return apiKey;
                        },
                    },
                    beforeSend: function() {
                        $input.closest('td').find('span.error').hide();
                        $input.closest('.edit').append($checking);
                    },
                    complete: function (data) {
                        var response = data.responseText;
                        var $inputWrapper = $input.closest('td');
                        if (response === 'true') {
                            $inputWrapper.find('.checking').empty().append('<span class="correct">Correct</span>');
                            window.setTimeout(function () {
                                $inputWrapper.find('.checking').remove();
                            }, 1000);
                        } else {
                            $inputWrapper.find('.checking').remove();
                        }
                    }
                }
            },
            messages: {
                remote: "This address is not active. Please check again in <a href='http://163.172.170.35/profile/info'>your profile</a>."
            }
        });
    };

    whmcs_ezdefi_admin.prototype.initCurrencySelect = function(element) {
        var self = this;
        element.select2({
            width: '100%',
            ajax: {
                url: self.configUrl,
                type: 'POST',
                data: function(params) {
                    var query = {
                        action: 'get_token',
                        api_url: self.form.find('input[name="field[apiUrl]"]').val(),
                        keyword: params.term
                    };

                    return query;
                },
                processResults: function(data) {
                    data = JSON.parse(data);
                    return {
                        results: data.data
                    }
                },
                cache: true,
                dataType: 'json',
                delay: 250
            },
            placeholder: 'Select currency',
            minimumInputLength: 1,
            templateResult: self.formatCurrencyOption,
            templateSelection: self.formatCurrencySelection
        });
        element.on('select2:select', self.onSelect2Select);
    };

    whmcs_ezdefi_admin.prototype.formatCurrencyOption = function(currency) {
        if(currency.loading) {
            return currency.text;
        }

        var $container = $(
            "<div class='select2-currency'>" +
            "<div class='select2-currency__icon'><img src='" + currency.logo + "' /></div>" +
            "<div class='select2-currency__name'>" + currency.name + "</div>" +
            "</div>"
        );

        return $container;
    };

    whmcs_ezdefi_admin.prototype.formatCurrencySelection = function(currency) {
        return currency.name || currency.text ;
    };

    whmcs_ezdefi_admin.prototype.addCurrency = function(e) {
        e.preventDefault();

        var $row = this.table.find('tbody tr:last');
        var $clone = $row.clone();
        var count = this.table.find('tbody tr').length;
        var selectName = $clone.find('select').attr('name')
        var $select = $('<select name="'+selectName+'" class="select-select2"></select>');

        $clone.find('select, .select2-container').remove();
        $clone.find('.logo img').attr('src', '');
        $clone.find('.name .view span').empty();
        $clone.find('.name .edit').prepend($select);
        $clone.find('input').val('');
        $clone.find('td').each(function() {
            $(this).find('span.error').remove();
        });
        this.updateAttr($clone, count);
        this.removeAttr($clone);
        $clone.insertAfter($row);
        this.initCurrencySelect($select);
        this.addValidationRule($clone);
        $clone.addClass('editing');
        return false;
    };

    whmcs_ezdefi_admin.prototype.removeCurrency = function(e) {
        e.preventDefault();

        var self = this;

        if(confirm('Do you want to delete this row')) {
            $(e.target).closest('tr').remove();
            self.table.find('tr').each(function (rowIndex) {
                $(this).find('.select2-container').remove();
                var $select = $(this).find('.select-select2');
                self.initCurrencySelect($select);

                var row = $(this);
                var number = rowIndex - 1;
                self.updateAttr(row, number);
            });
            self.table.find('tbody tr .saveBtn').trigger('click');
        }
        return false;
    };

    whmcs_ezdefi_admin.prototype.toggleEdit = function(e) {
        e.preventDefault();

        var self = this;
        var $row = $(e.target).closest('tr');

        if($row.find('.currency-symbol').val() === '') {
            self.removeCurrency(e);
        }

        $row.toggleClass('editing');
    };

    whmcs_ezdefi_admin.prototype.saveCurrency = function(e) {
        e.preventDefault();

        var self = this;
        var data = {};

        if(!self.form.valid()) {
            return;
        }

        self.table.find('tbody tr').each(function() {
            $(this).closest('tr').find('input, select').each(function () {
                var name = $(this).attr('name');
                var value = $(this).val();
                data[name] = value;
            });
        });

        data['action'] = 'save_currency';

        $.ajax({
            url: this.configUrl,
            method: 'post',
            data: data,
            beforeSend: function() {
                self.table.closest('td').block({ message: null });
            },
            success:function(response) {
                self.table.closest('td').unblock();
                self.table.find('tbody input').each(function() {
                    var value = $(this).val();
                    var td = $(this).closest('td');
                    if(!td.hasClass('name')) {
                        td.find('.view span').text(value);
                    }
                });
            }
        })
    };

    whmcs_ezdefi_admin.prototype.onSelect2Select = function(e) {
        var td = $(e.target).closest('td');
        var tr = $(e.target).closest('tr');
        var data = e.params.data;
        td.find('.currency-symbol').val(data.symbol);
        td.find('.currency-name').val(data.name);
        td.find('.currency-logo').val(data.logo);
        if(data.description) {
            td.find('.currency-desc').val(data.description);
        } else {
            td.find('.currency-desc').val('');
        }
        tr.find('.logo img').attr('src', data.logo);
        td.find('.view span').text(data.name);
    };

    whmcs_ezdefi_admin.prototype.updateAttr = function(row, number) {
        row.find('input, select').each(function () {
            var name = $(this).attr('name');
            name = name.replace(/\[(\d+)\]/, '[' + parseInt(number) + ']');
            $(this).attr('name', name).attr('id', name);
        });
    };

    whmcs_ezdefi_admin.prototype.removeAttr = function(row) {
        row.find('input, select').each(function () {
            $(this).removeAttr('aria-describedby').removeAttr('aria-invalid');
        });
    };

    new whmcs_ezdefi_admin();
});