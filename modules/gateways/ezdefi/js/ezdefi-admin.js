jQuery(function($) {
    var selectors = {
        exceptionTable: '#ezdefi-exception-table',
        amountIdCheckbox: 'input[name="field[amountId]"]',
        ezdefiWalletCheckbox: 'input[name="field[ezdefiWallet]"]',
        assignBtn: '.assignBtn',
        removeBtn: '.removeBtn'
    };

    var whmcs_ezdefi_admin = function() {
        this.ezdefiData = JSON.parse($('#ezdefi-data').text());
        this.unpaid_invoices = this.ezdefiData.unpaid_invoices;
        this.configUrl = this.ezdefiData.config_url;

        var init = this.init.bind(this);

        init();
    };

    whmcs_ezdefi_admin.prototype.init = function() {
        var self = this;

        this.addCurrencyTable();
        this.addExceptionTable.call(this);

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
        var toggleAmountSetting = this.toggleAmountSetting.bind(this);
        var onAssign = this.onAssign.bind(this);
        var onRemove = this.onRemove.bind(this);

        self.toggleAmountSetting(this);

        $(self.form)
            .on('click', '.addBtn', addCurrency)
            .on('click', '.editBtn', toggleEdit)
            .on('click', '.cancelBtn', toggleEdit)
            .on('click', '.deleteBtn', removeCurrency)
            .on('click', '.saveBtn', saveCurrency)
            .on('change', selectors.amountIdCheckbox, toggleAmountSetting)
            .on('click', selectors.assignBtn, onAssign)
            .on('click', selectors.removeBtn, onRemove);
    };

    whmcs_ezdefi_admin.prototype.addCurrencyTable = function() {
        var settingsTable = $('#Payment-Gateway-Config-ezdefi');

        var currencySettingRow = $("<tr><td class='fieldlabel'>Accepted Currency</td><td class='fieldarea'></td></tr>");
        settingsTable.find('tr:last').before(currencySettingRow);

        var table = $("<table id='ezdefi-currency-table'></table>")
        table.appendTo(currencySettingRow.find('.fieldarea'));

        var tableHead = $(
            "<thead>" +
            "<tr><th></th><th></th><th>Currency</th><th>Discount</th><th>Expiration</th><th>Wallet</th><th>Block Confirmation</th><th>Decimal</th><th></th></tr>" +
            "</thead>"
        );
        tableHead.appendTo(table);

        var tableBody = $("<tbody></tbody>");
        tableBody.appendTo(table);

        var tableFoot = $(
            "<tfoot><tr><td colspan='6'><button class='saveBtn btn btn-primary btn-sm'>Save currency</button> <button class='addBtn btn btn-primary btn-sm'>Add currency</button></td></tr></tfoot>"
        );
        tableFoot.appendTo(table);
    };

    whmcs_ezdefi_admin.prototype.addExceptionTable = function() {
        var self = this;
        var settingsTable = $('#Payment-Gateway-Config-ezdefi');

        var exceptionRow = $("<tr><td class='fieldlabel'>Manage Exceptions</td><td class='fieldarea'></td></tr>");
        settingsTable.find('tr:last').before(exceptionRow);

        var table = $("<table id='ezdefi-exception-table'></table>")
        table.appendTo(exceptionRow.find('.fieldarea'));

        var tableHead = $(
            "<thead>" +
            "<tr><th>#</th><th>Received Amount</th><th>Currency</th><th>Received At</th><th>Assign To</th><th></th></tr>" +
            "</thead>"
        );
        tableHead.appendTo(table);

        var tableBody = $("<tbody></tbody>");
        tableBody.appendTo(table);

        $.ajax({
            url: self.configUrl,
            method: 'post',
            data: {
                action: 'get_exception',
            },
            success: function(response) {
                tableBody.append(response);
                table.find('tr select').each(function() {
                    self.initInvoiceSelect.call(self, $(this));
                });
            }
        });
    };

    whmcs_ezdefi_admin.prototype.initInvoiceSelect = function(element) {
        var self = this;
        element.select2({
            width: '100%',
            data: self.unpaid_invoices,
            placeholder: 'Select Invoice',
            templateResult: self.formatInvoiceOption,
            templateSelection: self.formatInvoiceSelection,
            minimumResultsForSearch: -1
        });
    };

    whmcs_ezdefi_admin.prototype.formatInvoiceOption = function(order) {
        var $container = $(
            "<div class='select2-order'>" +
            "<div class='select2-order__row'>" +
            "<div class='left'><strong>Invoice ID:</strong></div>" +
            "<div class='right'>" + order['id'] + "</div>" +
            "</div>" +
            "<div class='select2-order__row'>" +
            "<div class='left'><strong>Total:</strong></div>" +
            "<div class='right'>" + order['currency'] + " " + order['total'] + "</div>" +
            "</div>" +
            "<div class='select2-order__row'>" +
            "<div class='left'><strong>Email:</strong></div>" +
            "<div class='right'>" + order['email'] + "</div>" +
            "</div>" +
            "<div class='select2-order__row'>" +
            "<div class='left'><strong>Date:</strong></div>" +
            "<div class='right'>" + order['date'] + "</div>" +
            "</div>" +
            "</div>"
        );
        return $container;
    };

    whmcs_ezdefi_admin.prototype.formatInvoiceSelection = function(order) {
        return 'Order ID: ' + order['id'];
    };

    whmcs_ezdefi_admin.prototype.onAssign = function(e) {
        var self = this;
        e.preventDefault();
        var row = $(e.target).closest('tr');
        var invoice_id = row.find('select').val();
        var amount_id = row.find('#amount-id').val();
        var currency = row.find('#currency').val();
        var data = {
            action: 'assign_amount_id',
            invoice_id: invoice_id,
            amount_id: amount_id,
            currency: currency
        };
        this.callAjax.call(this, data, row).success(function() {
            $(selectors.exceptionTable).unblock();
            $(selectors.exceptionTable).find('tr select').each(function() {
                $(this).find('option[value="' + invoice_id + '"]').remove();
            });
            row.remove();
        });
    };

    whmcs_ezdefi_admin.prototype.onRemove = function(e) {
        e.preventDefault();
        if(!confirm('Do you want to delete this amount ID')) {
            return false;
        }
        var row = $(e.target).closest('tr');
        var amount_id = row.find('#amount-id').val();
        var currency = row.find('#currency').val();
        var data = {
            action: 'delete_amount_id',
            amount_id: amount_id,
            currency: currency
        };
        this.callAjax.call(this, data, row).success(function() {
            $(selectors.exceptionTable).unblock();
            row.remove();
        });
    };

    whmcs_ezdefi_admin.prototype.callAjax = function(data) {
        var self = this;
        return $.ajax({
            url: self.configUrl,
            method: 'post',
            data: data,
            beforeSend: function() {
                $(selectors.exceptionTable).block({message: 'Waiting...'});
            },
            error: function(e) {
                $(selectors.exceptionTable).block({message: 'Something wrong happend.'});
            }
        });
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
                "<td class='discount'><div class='view'></div><div class='edit'><input type='number' name='currency[0][discount]' /> %</div></td>" +
                "<td class='lifetime'><div class='view'></div><div class='edit'><input type='number' name='currency[0][lifetime]' /> s</div></td>" +
                "<td class='wallet'><div class='view'></div><div class='edit'><input type='text' name='currency[0][wallet]' /></div></td>" +
                "<td class='block_confirm'><div class='view'></div><div class='edit'><input type='number' name='currency[0][block_confirm]' /></div></td>" +
                "<td class='decimal'><div class='view'></div><div class='edit'><input type='number' name='currency[0][decimal]' /></div></td>" +
                "<td class='actions'>" +
                    "<div class='view'><a class='editBtn btn btn-primary btn-xs' href=''>Edit</a> <a class='deleteBtn btn btn-danger btn-xs' href=''>Delete</a></div>" +
                    "<div class='edit'><a class='cancelBtn btn btn-default btn-xs' href=''>Cancel</a></div>" +
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
                "<td class='discount'><div class='view'></div><div class='edit'><input type='number' name='currency[1][discount]' /> %</div></td>" +
                "<td class='lifetime'><div class='view'></div><div class='edit'><input type='number' name='currency[1][lifetime]' /> s</div></td>" +
                "<td class='wallet'><div class='view'></div><div class='edit'><input type='text' name='currency[1][wallet]' /></div></td>" +
                "<td class='block_confirm'><div class='view'></div><div class='edit'><input type='number' name='currency[1][block_confirm]' /></div></td>" +
                "<td class='decimal'><div class='view'></div><div class='edit'><input type='number' name='currency[1][decimal]' /></div></td>" +
                "<td class='actions'>" +
                    "<div class='view'><a class='editBtn btn btn-primary btn-xs' href=''>Edit</a> <a class='deleteBtn btn btn-danger btn-xs' href=''>Delete</a></div>" +
                    "<div class='edit'><a class='cancelBtn btn btn-default btn-xs' href=''>Cancel</a></div>" +
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
                    "<td class='discount'><div class='view'><span>"+((config['discount'].length > 0) ? config['discount'] : 0)+"%</span></div><div class='edit'><input type='number' name='currency["+i+"][discount]' value='"+config['discount']+"' /> %</div></td>" +
                    "<td class='lifetime'><div class='view'><span>"+((config['lifetime']).length > 0 ? config['lifetime'] + 's' : '')+"</span></div><div class='edit'><input type='number' name='currency["+i+"][lifetime]' value='"+config['lifetime']+"' /> s</div></td>" +
                    "<td class='wallet'><div class='view'><span>"+config['wallet']+"</span></div><div class='edit'><input type='text' name='currency["+i+"][wallet]' value='"+config['wallet']+"' /></div></td>" +
                    "<td class='block_confirm'><div class='view'><span>"+config['block_confirm']+"</span></div><div class='edit'><input type='number' name='currency["+i+"][block_confirm]' value='"+config['block_confirm']+"' /></div></td>" +
                    "<td class='decimal'><div class='view'><span>"+config['decimal']+"</span></div><div class='edit'><input type='number' name='currency["+i+"][decimal]' value='"+config['decimal']+"' /></div></td>" +
                    "<td class='actions'>" +
                    "<div class='view'><a class='editBtn btn btn-primary btn-xs' href=''>Edit</a> <a class='deleteBtn btn btn-danger btn-xs' href=''>Delete</a></div>" +
                    "<div class='edit'><a class='cancelBtn btn btn-default btn-xs' href=''>Cancel</a></div>" +
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
                    number: true,
                    min: 0,
                    max: 100
                },
                'field[amountId]': {
                    required: {
                        depends: function(element) {
                            return ! self.form.find(selectors.ezdefiWalletCheckbox).is(':checked');
                        }
                    }
                },
                'field[ezdefiWallet]': {
                    required: {
                        depends: function(element) {
                            return ! self.form.find(selectors.amountIdCheckbox).is(':checked');
                        }
                    }
                }
            },
            messages: {
                'field[amountId]': {
                    required: 'Please select at least one payment method'
                },
                'field[ezdefiWallet]': {
                    required: 'Please select at least one payment method'
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
                    min: 0,
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

            if(name.indexOf('block_confirm') > 0) {
                var $input = $('input[name="'+name+'"]');
                $input.rules('add', {
                    min: 0
                });
            }

            if(name.indexOf('decimal') > 0) {
                var $input = $('input[name="'+name+'"]');
                $input.rules('add', {
                    required: true,
                    min: 2,
                    max: 12,
                    messages: {
                        required: 'Please enter number of decimal',
                        min: 'Please enter number equal or greater than 2',
                    }
                });
            }

            if(name.indexOf('lifetime') > 0) {
                var $input = $('input[name="'+name+'"]');
                $input.rules('add', {
                    digits: {
                        depends: function(element) {
                            return ($input.val().length > 0);
                        }
                    },
                    min: 0
                });
            }
        });
    };

    whmcs_ezdefi_admin.prototype.toggleAmountSetting = function() {
        var checked = this.form.find(selectors.amountIdCheckbox).is(':checked');
        var amount_settings = this.form.find('input[name="field[variation]"]').closest('tr');
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

                if($(this).hasClass('editing')) {
                    var name = $(this).find('.currency-name').val();
                    $(this).find('.select2-selection__rendered').attr('title', name);
                    $(this).find('.select2-selection__rendered').text(name);
                }

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
            error: function() {
                self.table.closest('td').block({message: 'Can not save currency config. Please try again'});
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