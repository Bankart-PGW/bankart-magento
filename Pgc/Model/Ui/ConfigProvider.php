<?php

namespace Pgc\Pgc\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;

final class ConfigProvider implements ConfigProviderInterface
{
    const CREDITCARD_CODE = 'pgc_creditcard';

    /**
     * @var \Pgc\Pgc\Helper\Data
     */
    private $pgcHelper;

    public function __construct(\Pgc\Pgc\Helper\Data $pgcHelper)
    {
        $this->pgcHelper = $pgcHelper;
    }

    public function getConfig()
    {
        return [
            'payment' => [
                static::CREDITCARD_CODE => [
                    'seamless' => $this->pgcHelper->getPaymentConfigData('seamless', static::CREDITCARD_CODE),
                    'integration_key' => $this->pgcHelper->getPaymentConfigData('integration_key', static::CREDITCARD_CODE),
                    'instalments' => $this->pgcHelper->getPaymentConfigData('instalments', static::CREDITCARD_CODE),
                    'instalments_amount' => $this->pgcHelper->getPaymentConfigData('instalments_amount', static::CREDITCARD_CODE)
                ]
            ],
        ];
    }
}
