jQuery(function($) {
    const selectors = {
        container: '#whmcs_ezdefi_qrcode',
        changeBtn: '.changeBtn',
        select: '.currency-select',
        itemWrap: '.currency-item__wrap',
        item: '.currency-item',
        selected: '.selected-currency',
        paymentData: '#order-data',
        urlData: '#url-data',
        submitBtn: '.submitBtn',
        ezdefiPayment: '.ezdefi-payment',
        tabs: '.ezdefi-payment-tabs',
        tabsNavLink: '.ezdefi-tabs-nav a',
        panel: '.ezdefi-payment-panel',
        ezdefiEnableBtn: '.ezdefiEnableBtn',
        loader: '.whmcs-ezdefi-loader',
        copy: '.copy-to-clipboard',
        qrcode: '.qrcode',
        changeQrcodeBtn: '.changeQrcodeBtn'
    };

    var whmcs_ezdefi_qrcode = function() {
        this.$container = $(selectors.container);
        this.$loader = this.$container.find(selectors.loader);
        this.$tabs = this.$container.find(selectors.tabs);
        this.$currencySelect = this.$container.find(selectors.select);
        this.paymentData = JSON.parse(this.$container.find(selectors.paymentData).text());
        this.xhrPool = [];
        this.urlData = JSON.parse(this.$container.find(selectors.urlData).text());
        this.checkOrderLoop;

        var init = this.init.bind(this);
        var onClickTabsNavLink = this.onClickTabsNavLink.bind(this);
        var onSelectItem = this.onSelectItem.bind(this);
        var onClickEzdefiLink = this.onClickEzdefiLink.bind(this);
        var onUseAltQrcode = this.onUseAltQrcode.bind(this);
        var onClickQrcode = this.onClickQrcode.bind(this);

        init();

        $(document.body)
            .on('click', selectors.tabsNavLink, onClickTabsNavLink)
            .on('click', selectors.item, onSelectItem)
            .on('click', selectors.ezdefiEnableBtn, onClickEzdefiLink)
            .on('click', selectors.qrcode, onClickQrcode)
            .on('click', selectors.changeQrcodeBtn, onUseAltQrcode);
    };

    whmcs_ezdefi_qrcode.prototype.init = function() {
        var self = this;

        var li = this.$tabs.find('.ezdefi-tabs-nav li:first-child');
        var id = li.find('a').attr('href');
        var panel = this.$tabs.find('div' + id);

        li.addClass('active');
        panel.addClass('active');

        this.onActivateTab.call(this, panel.attr('id'), panel);

        var clipboard = new ClipboardJS(selectors.copy);
        clipboard.on('success', function(e) {
            var trigger = $(e.trigger)[0];
            console.log(trigger);
            trigger.classList.add('copied');
            setTimeout(function () {
                trigger.classList.remove('copied');
            }, 2000);
        });
    };

    whmcs_ezdefi_qrcode.prototype.onClickTabsNavLink = function(e) {
        e.preventDefault();
        this.$tabs.find('ul li').removeClass('active');
        this.$tabs.find(selectors.panel).removeClass('active');

        var target = $(e.target);

        if(!target.is('li')) {
            target = target.closest('li');
        }

        var id = target.find('a').attr('href');
        var panel = this.$tabs.find(id);

        target.addClass('active');
        panel.addClass('active');

        if(panel.html() === '') {
            this.onActivateTab.call(this, panel.attr('id'), panel);
        }
    };

    whmcs_ezdefi_qrcode.prototype.onClickQrcode = function(e) {
        var self = this;
        var target = $(e.target);
        if(!target.hasClass('expired')) {
            return false;
        } else {
            e.preventDefault();
            self.$currencySelect.find('.selected').click();
        }
    };

    whmcs_ezdefi_qrcode.prototype.onActivateTab = function(method, panel) {
        var self = this;
        self.createEzpayPayment.call(self, method).success(function(response) {
            panel.html(response.data);
            self.setTimeRemaining.call(self, panel);
            self.$loader.hide();
            self.$tabs.show();
            self.checkOrderStatus.call(self);
        });
    };

    whmcs_ezdefi_qrcode.prototype.createEzpayPayment = function(method) {
        var self = this;
        var symbol = this.$currencySelect.find(selectors.item + '.selected').attr('data-symbol');
        if(!symbol) {
            return false;
        }
        return $.ajax({
            url: self.urlData.ajaxUrl,
            method: 'post',
            data: {
                action: 'create_payment',
                uoid: self.paymentData.uoid,
                symbol: symbol,
                method: method
            },
            beforeSend: function() {
                clearInterval(self.checkOrderLoop);
                $.each(self.xhrPool, function(index, jqXHR) {
                    jqXHR.abort();
                });
                self.$loader.show();
                self.$tabs.hide();
            }
        });
    };

    whmcs_ezdefi_qrcode.prototype.onSelectItem = function(e) {
        var self = this;
        this.$currencySelect.find(selectors.item).removeClass('selected');
        var target = $(e.target);
        if(target.is(selectors.itemWrap)) {
            target.find(selectors.item).addClass('selected');
        } else {
            target.closest(selectors.itemWrap).find(selectors.item).addClass('selected');
        }
        var active = self.$tabs.find('.ezdefi-payment-panel.active');
        var method = active.attr('id');
        self.createEzpayPayment.call(self, method).success(function(response) {
            self.$tabs.find(selectors.panel).empty();
            active.html(response.data);
            self.setTimeRemaining.call(self, active);
            self.$loader.hide();
            self.$tabs.show();
            self.checkOrderStatus.call(self);
        });
    };

    whmcs_ezdefi_qrcode.prototype.onClickEzdefiLink = function(e) {
        var self = this;
        e.preventDefault();
        self.$tabs.find('ul li').removeClass('active');
        self.$tabs.find(selectors.panel).removeClass('active');
        self.$tabs.find('a#tab-ezdefi_wallet').trigger('click');
    };

    whmcs_ezdefi_qrcode.prototype.onUseAltQrcode = function(e) {
        e.preventDefault();
        this.$tabs.find('#amount_id .qrcode img.main').toggle();
        this.$tabs.find('#amount_id .qrcode__info--main').toggle();
        this.$tabs.find('#amount_id .qrcode img.alt').toggle();
        this.$tabs.find('#amount_id .qrcode__info--alt').toggle();
    };

    whmcs_ezdefi_qrcode.prototype.checkOrderStatus = function() {
        var self = this;
        self.checkOrderLoop = setInterval(function() {
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

    whmcs_ezdefi_qrcode.prototype.setTimeRemaining = function(panel) {
        var self = this;
        var timeLoop = setInterval(function() {
            var endTime = panel.find('.count-down').attr('data-endtime');
            var t = self.getTimeRemaining(endTime);
            var countDown = panel.find(selectors.ezdefiPayment).find('.count-down');

            if(t.total < 0) {
                clearInterval(timeLoop);
                countDown.text('0:0');
                self.timeout(panel);
            } else {
                countDown.text(t.text);
            }
        }, 1000);
    };

    whmcs_ezdefi_qrcode.prototype.getTimeRemaining = function(endTime) {
        var t = new Date(endTime).getTime() - new Date().getTime();
        var minutes = Math.floor((t / 60000));
        var seconds = ((t % 60000) / 1000).toFixed(0);
        return {
            'total': t,
            'text': (seconds == 60 ? (minutes +1) + ":00" : minutes + ":" + (seconds < 10 ? "0" : "") + seconds)
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

    whmcs_ezdefi_qrcode.prototype.timeout = function(panel) {
        panel.find('.qrcode').addClass('expired');
    };

    new whmcs_ezdefi_qrcode();
});