define(
    [
        'Magento_Checkout/js/view/payment/default',
        "jquery",
        "underscore",
        "mage/url",
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/quote',
    ],
    function (
        Component, 
        $,
        _,
        url,
        fullScreenLoader,
        quote,
    ) {
        'use strict';
    
        return Component.extend({

            defaults: {
                template: "Pgc_Pgc/payment/creditcard",
                bankartInstalmentsSelected: 1,
                bankartInstalmentsMaxNum: 1,
                bankartInstalmentsMinAmt: 1,
                bankartInstalments: [1]
            },

            initialize: function() {
                this._super();
                this.config = window.checkoutConfig.payment[this.getCode()];

                this.calculateInstalments();
                quote.totals.subscribe(this.calculateInstalments, this);
            },

            calculateInstalments: function() {
                var calculatedMax = Math.floor(quote.getTotals()()['grand_total']/this.config.instalments_amount);
                if(calculatedMax > 1) {
                    var instalmentsMax = (this.config.instalments < calculatedMax) ? this.config.instalments : calculatedMax;
                    this.bankartInstalments = _.range(1, ++instalmentsMax);
                }
            },

            getData: function () {
                var data = {
                    'method': this.getCode(),
                    'additional_data': {
                        'instalments': this.bankartInstalmentsSelected
                    }
                };

                return data;
            },

            afterPlaceOrder: function () {

                this.redirectAfterPlaceOrder = false;

                const data = {
                    instalments: this.bankartInstalmentsSelected
                };

                $.ajax({
                    url: url.build("pgc/payment/frontend"),
                    type: 'post',
                    data: data,
                    success: (result) => {
                        console.log(result);

                        if (result.type === 'finished') {
                            this.redirectAfterPlaceOrder = true;
                        } else if (result.type === 'redirect') {
                            fullScreenLoader.startLoader();
                            window.location.replace(result.url);
                        }
                    },
                    error: (err) => {
                        console.error("Error : " + JSON.stringify(err));
                    }
                });

            },

        });
    }
);
