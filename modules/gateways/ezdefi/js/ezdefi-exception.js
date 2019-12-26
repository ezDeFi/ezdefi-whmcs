jQuery(function($) {

    var whmcs_ezdefi_exception = function() {
        this.ezdefiData = JSON.parse($('#ezdefi-data').text());
        this.configUrl = this.ezdefiData.config_url;
        this.adminUrl = this.ezdefiData.admin_url;

        var init = this.init.bind(this);

        init();
    };

    whmcs_ezdefi_exception.prototype.init = function() {
        this.addExceptionTable.call(this);
        this.$search = $('#ezdefi-exception-search');
        this.$table = $('#ezdefi-exception-table');
        this.$pagination = this.$table.find('.pagination');

        var data = {
            action: 'get_exceptions',
        };

        this.getException.call(this, data);

        var onAssign = this.onAssign.bind(this);
        var onRemove = this.onRemove.bind(this);
        var onReverse = this.onReverse.bind(this);
        var onShowInvoiceSelect = this.onShowInvoiceSelect.bind(this);
        var onHideInvoiceSelect = this.onHideInvoiceSelect.bind(this);
        var onNext = this.onNext.bind(this);
        var onPrev = this.onPrev.bind(this);
        var closeModal = this.closeModal.bind(this);

        $(this.$table)
            .on('click', '.next', onNext)
            .on('click', '.prev', onPrev)
            .on('click', '.showSelectBtn', onShowInvoiceSelect)
            .on('click', '.hideSelectBtn', onHideInvoiceSelect)
            .on('click', '.assignBtn', onAssign)
            .on('click', '.removeBtn', onRemove)
            .on('click', '.reverseBtn', onReverse)
            .on('click', '.closeModalBtn', closeModal);

        var onClearFilter = this.onClearFilter.bind(this);
        var onSearch = this.onSearch.bind(this);

        $(this.$search)
            .on('click', '.clearFilterBtn', onClearFilter)
            .on('keyup', 'input', onSearch)
            .on('change', 'select', onSearch);
    };

    whmcs_ezdefi_exception.prototype.closeModal = function(e) {
        e.preventDefault();
        $('body').removeClass('ezdefi-modal-open');
        $('#ezdefi-modal').hide();
    };

    whmcs_ezdefi_exception.prototype.addExceptionTable = function() {
        var self = this;

        var table = $("<table id='ezdefi-exception-table'></table>")

        var tableHead = $(
            "<thead>" +
            "<tr>" +
            "<th width='32px'>#</th>" +
            "<th width='200px'>Received Amount</th>" +
            "<th width='120px'>Currency</th>" +
            "<th>Invoice</th>" +
            "<th width='220px'>Action</th>" +
            "</tr>" +
            "</thead>"
        );
        tableHead.appendTo(table);

        var tableBody = $(
            "<tbody>" +
                "<tr class='spinner-row'>" +
                    "<td colspan='4'><div class='whmcs-ezdefi-loader'></div></td>" +
                "</tr>" +
            "</tbody>"
        );
        tableBody.appendTo(table);

        var tableFoot = $(
            "<tfoot>" +
            "<tr>" +
            "<td colspan='2'><ul class='pagination'><li><a class='prev' href=''><</a></li><li><a class='next' href=''>></a></li></ul></td>" +
            "<td colspan='2' class='text-right'><button class='btn btn-danger btn-sm closeModalBtn'>Close</button></td>" +
            "</tr>" +
            "</tfoot>"
        );
        tableFoot.appendTo(table);

        var modal = $("<div id='ezdefi-modal'><div id='ezdefi-modal__overlay'></div><div id='ezdefi-modal__content'></div></div>");
        var search = $("<div id='ezdefi-exception-search'><input type='number' name='amount_id' placeholder='Amount'><input type='text' name='currency' placeholder='Currency'><input type='number' name='order_id' placeholder='Invoice ID'><select name='clientid' id='client'></select><select name='payment_method' id=''><option value='' selected=''>Any Payment Method</option><option value='ezdefi_wallet'>Pay with ezDeFi wallet</option><option value='amount_id'>Pay with any crypto wallet</option></select><select name='status' id=''><option value='' selected=''>Any Status</option><option value='expired_done'>Paid after expired</option><option value='not_paid'>Not paid</option><option value='done'>Paid on time</option></select><a href='' class='clearFilterBtn btn btn-primary btn-sm'>Clear</a></div>");
        modal.find('#ezdefi-modal__content').append(search);
        $(search).find('#client').select2({
            width: '100%',
            ajax: {
                url: self.configUrl,
                type: 'POST',
                data: function (params) {
                    var query = {
                        action: 'get_clients',
                    };

                    return query;
                },
                processResults: function (data) {
                    return {
                        results: data.data
                    }
                },
                cache: true,
                dataType: 'json',
            },
            placeholder: 'Select Client',
            minimumResultsForSearch: Infinity,
            templateResult: self.formatClientOption,
            templateSelection: self.formatClientSelection,
        });
        modal.find('#ezdefi-modal__content').append(table);

        modal.appendTo('body');
    };

    whmcs_ezdefi_exception.prototype.renderHtml = function(data) {
        var self = this;
        var rows = data['data'];
        self.$table.find('tbody tr').not('.spinner-row').remove();
        if(rows.length === 0) {
            self.$table.append("<tr><td colspan='4'>Not found</td></tr>")
        }
        for(var i=0;i<rows.length;i++) {
            var number = i + 1 + (data['per_page'] * (data['current_page'] - 1));
            var row = rows[i];
            var status;
            var payment_method;
            switch (row['status']) {
                case 'not_paid':
                    status = 'Not paid';
                    break;
                case 'expired_done':
                    status = 'Paid after expired';
                    break;
                case 'done':
                    status = 'Paid on time';
                    break;
            }
            switch (row['payment_method']) {
                case 'amount_id':
                    payment_method = 'Pay with any crypto wallet';
                    break;
                case 'ezdefi_wallet':
                    payment_method = '<strong>Pay with ezDeFi wallet</strong>';
                    break;
            }
            var html = $(
                "<tr>" +
                "<td width='32px'>" + number + "</td>" +
                "<td width='200px' class='amount-id-column'>" +
                "<span>" + row['amount_id'] + "</span>" +
                "<input type='hidden' class='amount-id-input' value='" + row['amount_id'] + "' >" +
                "</td>" +
                "<td width='120px'>" +
                "<span class='symbol'>" + row['currency'] + "</span>" +
                "<input type='hidden' class='currency-input' value='" + row['currency'] + "' >" +
                "</td>" +
                "<td class='order-column'>" +
                "<input type='hidden' class='old-invoice-id-input' value='" + ( (row['order_id']) ? row['order_id'] : '' ) + "' >" +
                "<input type='hidden' class='invoice-id-input' value='" + ( (row['order_id']) ? row['order_id'] : '' ) + "' >" +
                "<div class='saved-order'>" +
                "<div>Invoice: <a href='" + self.adminUrl + "invoices.php?action=edit&id=" + row['order_id'] + "'>#<span id='saved-invoice-id'>" + row['order_id'] + "</span></a></div>" +
                "<div>Client: <a href='" + self.adminUrl + "clientssummary.php?userid=" + row['user_id'] + "'>" + row['firstname'] + " " + row['lastname'] + "</a></div>" +
                "<div>Status: " + status + "</div>" +
                "<div>Payment method: " + payment_method + "</div>" +
                "</div>" +
                "<div class='select-order' style='display: none'>" +
                "<select name='' id=''></select>" +
                "</div>" +
                "<div class='actions'>" +
                "<a href='' class='showSelectBtn button'>Assign to different order</a>" +
                "<a href='' class='hideSelectBtn button' style='display: none'>Cancel</a>" +
                "</div>" +
                "</td>" +
                "</tr>"
            );
            if(row['explorer_url'] && row['explorer_url'].length > 0) {
                var explore = $("<a class='explorer-url' href='" + row['explorer_url'] + "'>View Transaction Detail</a>");
                html.find('td.amount-id-column').append(explore);
            }

            if(row['order_id'] == null) {
                html.find('td.order-column .saved-order, td.order-column .actions').remove();
                html.find('td.order-column .select-order').show();
                self.initInvoiceSelect.call(self, html.find('td.order-column select'));
            }

            var last_td;

            if(row['status'] === 'done') {
                last_td = $(
                    "<td width='220px'>" +
                    "<button class='btn btn-primary btn-sm reverseBtn'>Reverse</button> " +
                    "<button class='btn btn-danger btn-sm removeBtn'>Remove</button>" +
                    "</td>"
                );
            } else {
                last_td = $(
                    "<td width='220px'>" +
                    "<button class='btn btn-primary btn-sm button-primary assignBtn'>Confirm Paid</button> " +
                    "<button class='btn btn-danger btn-sm removeBtn'>Remove</button>" +
                    "</td>"
                );
            }
            html.append(last_td);
            html.appendTo(self.$table.find('tbody'));
        }
        self.$table.attr('data-current-page', data['current_page']);
        self.$table.attr('data-last-page', data['last_page']);
    };

    whmcs_ezdefi_exception.prototype.onShowInvoiceSelect = function(e) {
        e.preventDefault();
        var column = $(e.target).closest('td');

        column.find('.showSelectBtn').hide();
        column.find('.hideSelectBtn').show();
        column.find('.saved-order').hide();
        column.find('.select-order').show();

        this.initInvoiceSelect.call(this, column.find('select'));
    };

    whmcs_ezdefi_exception.prototype.onHideInvoiceSelect = function(e) {
        e.preventDefault();
        var column = $(e.target).closest('td');

        column.find('.showSelectBtn').show();
        column.find('.hideSelectBtn').hide();
        column.find('.saved-order').show();
        column.find('.select-order').hide();

        column.find('select').select2('destroy');

        var savedOrderId = column.find('#saved-invoice-id').text();
        column.find('.invoice-id-input').val(savedOrderId);
    };

    whmcs_ezdefi_exception.prototype.initInvoiceSelect = function(element) {
        var self = this;
        element.select2({
            width: '280px',
            ajax: {
                url: self.configUrl,
                type: 'POST',
                data: function(params) {
                    var query = {
                        action: 'get_unpaid_invoices',
                        keyword: params.term,
                    };

                    return query;
                },
                processResults: function(data) {
                    return {
                        results: data.data
                    }
                },
                cache: true,
                dataType: 'json',
                delay: 250
            },
            placeholder: 'Select Invoice',
            templateResult: self.formatInvoiceOption,
            templateSelection: self.formatInvoiceSelection,
        });
        element.on('select2:select', this.onSelect2Select);
    };

    whmcs_ezdefi_exception.prototype.onSelect2Select = function(e) {
        var column = $(e.target).closest('td');
        var data = e.params.data;

        column.find('.invoice-id-input').val(data.id);
    };

    whmcs_ezdefi_exception.prototype.formatInvoiceOption = function(order) {
        if (order.loading) {
            return 'Loading';
        }

        var $container = $(
            "<div class='select2-order'>" +
            "<div class='select2-order__row'>" +
            "<div class='left'><strong>Invoice:</strong></div>" +
            "<div class='right'>#" + order['id'] + "</div>" +
            "</div>" +
            "<div class='select2-order__row'>" +
            "<div class='left'><strong>Client Name:</strong></div>" +
            "<div class='right'>" + order['firstname'] + " " + order['lastname'] + "</div>" +
            "</div>" +
            "<div class='select2-order__row'>" +
            "<div class='left'><strong>Invoice Date:</strong></div>" +
            "<div class='right'>" + order['date'] + "</div>" +
            "</div>" +
            "<div class='select2-order__row'>" +
            "<div class='left'><strong>Due Date:</strong></div>" +
            "<div class='right'>" + order['duedate'] + "</div>" +
            "</div>" +
            "<div class='select2-order__row'>" +
            "<div class='left'><strong>Total Due:</strong></div>" +
            "<div class='right'>" + order['prefix'] + order['total'] + " " + order['suffix'] + "</div>" +
            "</div>" +
            "</div>"
        );
        return $container;
    };

    whmcs_ezdefi_exception.prototype.formatInvoiceSelection = function(order) {
        return 'Invoice ID: #' + order['id'];
    };

    whmcs_ezdefi_exception.prototype.formatClientOption = function(client) {
        if (client.loading) {
            return 'Loading';
        }

        var $container = $(
            "<div class='select2-client'>" +
            "<div>" + client.firstname + " " + client.lastname + "</div>" +
            "<div>" + client.email + "</div>" +
            "</div>"
        );

        return $container;
    };

    whmcs_ezdefi_exception.prototype.formatClientSelection = function(client) {
        if(client.firstname && client.lastname) {
            return client.firstname + " " + client.lastname;
        }

        return client.text;
    };

    whmcs_ezdefi_exception.prototype.onSearch = function(e) {
        e.preventDefault();
        this.getException.call(this);
    };

    whmcs_ezdefi_exception.prototype.getAjaxData = function(page) {
        var self = this;
        var data = {
            'action': 'get_exceptions',
        };
        self.$search.find('input, select').each(function() {
            var val = '';
            if($(this).is('input')) {
                val = $(this).val();
            }

            if($(this).is('select')) {
                val = $(this).find('option:selected').val();
            }

            if(val && val.length > 0) {
                data[$(this).attr('name')] = $(this).val();
            }
        });
        if(page && page > 1) {
            data['page'] = page;
        } else {
            data['page'] = 1;
        }
        return data;
    };

    whmcs_ezdefi_exception.prototype.getException = function(page = 1) {
        var self = this;
        $.ajax({
            url: self.configUrl,
            method: 'post',
            data: self.getAjaxData(page),
            beforeSend: function() {
                self.$pagination.hide();
                self.$table.find('tbody tr').not('.spinner-row').remove();
                self.$table.find('tbody tr.spinner-row').show();
            },
            success: function(response) {
                self.$table.find('tbody tr.spinner-row').hide();
                self.renderHtml.call(self, response['data']);
                self.renderPagination.call(self, response['data']);
                self.$table.find('tr select').each(function() {
                    self.initInvoiceSelect.call(self, $(this));
                });
            }
        });
    };

    whmcs_ezdefi_exception.prototype.renderPagination = function(data) {
        var self = this;
        if(data['current_page'] == 1) {
            self.$pagination.find('li:first-child').addClass('disabled');
        } else {
            self.$pagination.find('li:first-child').removeClass('disabled');
        }
        if((data['current_page'] == data['last_page']) || data['last_page'] == 0) {
            self.$pagination.find('li:last-child').addClass('disabled');
        } else {
            self.$pagination.find('li:last-child').removeClass('disabled');
        }
        self.$pagination.show();
    };

    whmcs_ezdefi_exception.prototype.onNext = function(e) {
        e.preventDefault();
        var target = $(e.target);
        if(target.closest('li').hasClass('disabled')) {
            return false;
        }
        var current_page = parseInt(this.$table.attr('data-current-page'));
        var page = current_page + 1;
        this.getException(page);
    };

    whmcs_ezdefi_exception.prototype.onPrev = function(e) {
        e.preventDefault();
        var target = $(e.target);
        if(target.closest('li').hasClass('disabled')) {
            return false;
        }
        var current_page = parseInt(this.$table.attr('data-current-page'));
        var page = current_page -1;
        this.getException(page);
    };

    whmcs_ezdefi_exception.prototype.onClearFilter = function(e) {
        e.preventDefault();
        var self = this;
        self.$search.find('input, select').each(function() {
            if($(this).is('select#client')) {
                $(this).val(null).trigger('change');
            } else {
                $(this).val('');
            }
        });
    };

    whmcs_ezdefi_exception.prototype.onAssign = function(e) {
        e.preventDefault();
        var self = this;
        var row = $(e.target).closest('tr');
        var old_invoice_id = row.find('.old-invoice-id-input').val();
        var invoice_id = row.find('.invoice-id-input').val();
        var amount_id = row.find('.amount-id-input').val();
        var currency = row.find('.currency-input').val();
        var data = {
            action: 'assign_amount_id',
            old_invoice_id: old_invoice_id,
            invoice_id: invoice_id,
            amount_id: amount_id,
            currency: currency
        };
        this.callAjax.call(this, data).success(function() {
            self.getException();
        });
    };

    whmcs_ezdefi_exception.prototype.onRemove = function(e) {
        e.preventDefault();
        if(!confirm('Do you want to delete this exception')) {
            return false;
        }
        var self = this;
        var row = $(e.target).closest('tr');
        var invoice_id = row.find('.invoice-id-input').val();
        var amount_id = row.find('.amount-id-input').val();
        var currency = row.find('.currency-input').val();
        var data = {
            action: 'delete_exception',
            invoice_id: invoice_id,
            amount_id: amount_id,
            currency: currency
        };
        this.callAjax.call(this, data).success(function() {
            var page = self.$table.attr('data-current-page');
            self.getException(page);
        });
    };

    whmcs_ezdefi_exception.prototype.onReverse = function(e) {
        e.preventDefault();
        var self = this;
        var row = $(e.target).closest('tr');
        var invoice_id = row.find('.invoice-id-input').val();
        var amount_id = row.find('.amount-id-input').val();
        var currency = row.find('.currency-input').val();
        var data = {
            action: 'reverse_invoice',
            invoice_id: invoice_id,
            amount_id: amount_id,
            currency: currency
        };
        this.callAjax.call(this, data).success(function() {
            self.getException();
        });
    };

    whmcs_ezdefi_exception.prototype.callAjax = function(data) {
        var self = this;
        return $.ajax({
            url: self.configUrl,
            method: 'post',
            data: data,
            beforeSend: function() {
                self.$table.find('tbody tr').not('.spinner-row').remove();
                self.$table.find('tbody tr.spinner-row').show();
                self.$pagination.hide();
            }
        });
    };

    new whmcs_ezdefi_exception();
});