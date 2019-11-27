jQuery(function($) {
    const selectors = {
        container: '#whmcs_ezdefi_qrcode',
        changeBtn: '.changeBtn',
        select: '.currency-select',
        item: '.currency-item',
        selected: '.selected-currency',
        paymentData: '#order-data',
        urlData: '#url-data',
        submitBtn: '.submitBtn',
        ezdefiPayment: '.ezdefi-payment',
        tabs: '.ezdefi-payment-tabs',
        panel: '.ezdefi-payment-panel'
    };

    var whmcs_ezdefi_qrcode = function() {
        this.$container = $(selectors.container);
        this.$tabs = this.$container.find(selectors.tabs);
        this.$currencySelect = this.$container.find(selectors.select);
        this.$submitBtn = this.$container.find(selectors.submitBtn);
        this.paymentData = JSON.parse(this.$container.find(selectors.paymentData).text());
        this.xhrPool = [];
        this.urlData = JSON.parse(this.$container.find(selectors.urlData).text());

        var init = this.init.bind(this);
        var onChange = this.onChange.bind(this);
        var onSelectItem = this.onSelectItem.bind(this);
        var onSubmit = this.onSubmit.bind(this);

        init();

        $(document.body)
            .on('click', selectors.changeBtn, onChange)
            .on('click', selectors.item, onSelectItem)
            .on('click', selectors.submitBtn, onSubmit);
    };

    whmcs_ezdefi_qrcode.prototype.init = function() {
        var self = this;

        self.$tabs.tabs({
            activate: function(event, ui) {
                if(!ui.newPanel || ui.newPanel.is(':empty')) {
                    var method = ui.newPanel.attr('id');
                    self.onActivateTab.call(self, method, ui.newPanel);
                }
            }
        });

        var index = self.$tabs.tabs('option', 'active');
        var active = self.$tabs.find(selectors.panel + ':eq('+index+')');
        var method = active.attr('id');

        self.onActivateTab.call(self, method, active);
    };

    whmcs_ezdefi_qrcode.prototype.onChange = function(e) {
        e.preventDefault();
        this.$currencySelect.toggle();
        this.$submitBtn.prop('disabled', false).text('Confirm').show();
        this.$tabs.hide();
    };

    whmcs_ezdefi_qrcode.prototype.onSelectItem = function(e) {
        var $item = $(e.target).closest(selectors.item);
        var $selected = this.$container.find(selectors.selected);

        $selected.find('.logo').attr('src', $item.find('.logo').attr('src'));
        $selected.find('.symbol').text($item.find('.symbol').text());
        $selected.find('.name').text($item.find('.name').text());

        var desc = $item.find('.desc');

        if(desc) {
            $selected.find('.desc').text($item.find('.desc').text());
        }
    };

    whmcs_ezdefi_qrcode.prototype.onSubmit = function(e) {
        var self = this;
        var index = self.$tabs.tabs( "option", "active" );
        var active = self.$tabs.find(selectors.panel + ':eq('+index+')');
        var method = active.attr('id');
        self.$currencySelect.hide();
        self.$tabs.hide();
        self.$submitBtn.prop('disabled', true).text('Loading...');
        $.blockUI({message: null});
        var paymentid = self.$tabs.find('#amount_id .ezdefi-payment').attr('data-paymentid');
        self.setAmountIdValid.call(self, paymentid).done(function() {
            self.createEzpayPayment.call(self, method).success(function(response) {
                self.$tabs.find(selectors.panel).empty();
                active.html(response.data);
                var endTime = active.find('.count-down').attr('data-endtime');
                self.setTimeRemaining.call(self, endTime);
                $.unblockUI();
                self.$tabs.show();
                self.$submitBtn.prop('disabled', false).text('Confirm').hide();
                self.checkOrderStatus.call(self);
            });
        });
    };

    whmcs_ezdefi_qrcode.prototype.onActivateTab = function(method, panel) {
        var self = this;
        self.createEzpayPayment.call(self, method).success(function(response) {
            panel.html(response.data);
            var endTime = panel.find('.count-down').attr('data-endtime');
            self.setTimeRemaining.call(self, endTime);
            $.unblockUI();
            self.$tabs.show();
            self.$submitBtn.prop('disabled', false).text('Confirm').hide();
            self.checkOrderStatus.call(self);
        });
    };

    whmcs_ezdefi_qrcode.prototype.createEzpayPayment = function(method) {
        var self = this;
        var symbol = self.$container.find(selectors.selected).find('.symbol').text();
        if(!symbol) {
            return false;
        }
        $.each(self.xhrPool, function(index, jqXHR) {
            jqXHR.abort();
        });
        $.blockUI({message: null});
        return $.ajax({
            url: self.urlData.ajaxUrl,
            method: 'post',
            data: {
                action: 'create_payment',
                uoid: self.paymentData.uoid,
                symbol: symbol,
                method: method
            },
            error: function() {
                $.blockUI({message: 'Something wrong happend. Please contact admin.'});
            }
        });
    };

    whmcs_ezdefi_qrcode.prototype.checkOrderStatus = function() {
        var self = this;
        setInterval(function() {
            $.ajax({
                url: self.urlData.ajaxUrl,
                method: 'post',
                data: {
                    action: 'check_invoice',
                    invoice_id: self.paymentData.uoid
                },
                beforeSend: function(jqXHR) {
                    self.xhrPool.push(jqXHR);
                },
                success: function(response) {
                    if(response.toLowerCase() === 'paid') {
                        self.success();
                    }
                }
            });
        }, 600);
    };

    whmcs_ezdefi_qrcode.prototype.setTimeRemaining = function(endTime) {
        var self = this;
        clearInterval(self.timeLoop);
        self.timeLoop = setInterval(function() {
            var t = self.getTimeRemaining(endTime);
            var countDown = self.$container.find(selectors.ezdefiPayment).find('.count-down');

            if(t.total < 0) {
                clearInterval(self.timeLoop);
                self.timeout.call(self);
            }

            countDown.text(
                t.days + ' d ' +
                t.hours + ' h ' +
                t.minutes + ' m ' +
                t.seconds + ' s'
            );
        }, 1000);
    };

    whmcs_ezdefi_qrcode.prototype.getTimeRemaining = function(endTime) {
        var t = new Date(endTime).getTime() - new Date().getTime();
        var days = Math.floor(t / (1000 * 60 * 60 * 24));
        var hours = Math.floor((t % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        var minutes = Math.floor((t % (1000 * 60 * 60)) / (1000 * 60));
        var seconds = Math.floor((t % (1000 * 60)) / 1000);
        return {
            'total': t,
            'days': days,
            'hours': hours,
            'minutes': minutes,
            'seconds': seconds
        };
    };

    whmcs_ezdefi_qrcode.prototype.success = function() {
        var self = this;

        var $content = $(
            "<p>Success. You will be redirect to clientarea in 3 seconds. If it does not, click " +
            "<a href='" + self.urlData.clientArea + "'>here</a>" +
            "</p>"
        );

        self.$container.empty();
        self.$container.append($content);

        $.each(self.xhrPool, function(index, jqXHR) {
            jqXHR.abort();
        });

        setTimeout(function(){ window.location = self.urlData.clientArea; }, 3000);
    };

    whmcs_ezdefi_qrcode.prototype.timeout = function() {
        var self = this;

        var $content = $("<p>Timeout. You will be redirect to cart page</p>");

        self.$container.empty();
        self.$container.append($content);

        $.each(self.xhrPool, function(index, jqXHR) {
            jqXHR.abort();
        });

        var paymentid = self.$tabs.find('#amount_id .ezdefi-payment').attr('data-paymentid');

        if(paymentid) {
            self.setAmountIdValid.call(self, paymentid).success(function() {
                window.location = self.urlData.cart
            });
        }
    };

    whmcs_ezdefi_qrcode.prototype.setAmountIdValid = function(paymentid) {
        var self = this;
        return $.ajax({
            url: self.urlData.ajaxUrl,
            method: 'post',
            data: {
                action: 'payment_timeout',
                paymentid: paymentid
            }
        });
    };

    new whmcs_ezdefi_qrcode();
});