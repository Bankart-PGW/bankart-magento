<?php

namespace Pgc\Pgc\Model\Payment;

use Pgc\Pgc\Model\Ui\ConfigProvider;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;


class CreditCard extends AbstractMethod
{
    protected $_code = ConfigProvider::CREDITCARD_CODE;
    protected $_infoBlockType = \Magento\Payment\Block\Info\Cc::class;

    protected $_isGateway = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canVoid = true;
    protected $_canRefund = false;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = [],        
        \Pgc\Pgc\Helper\Data $pgcHelper,
        \Magento\Framework\Message\ManagerInterface $messageManager     
    ) {
        $this->pgcHelper = $pgcHelper;
        $this->messageManager = $messageManager;

        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            null,
            null,
            $data
        );
    }

    public function capture(InfoInterface $payment, $amount)
    {
        if ($amount > 0) {
            $paymentMethod = 'pgc_creditcard';

            try {
                \Pgc\Client\Client::setApiUrl($this->pgcHelper->getGeneralConfigData('host'));
                $client = new \Pgc\Client\Client(
                    $this->pgcHelper->getGeneralConfigData('username'),
                    $this->pgcHelper->getGeneralConfigData('password'),
                    $this->pgcHelper->getPaymentConfigData('api_key', $paymentMethod, null),
                    $this->pgcHelper->getPaymentConfigData('shared_secret', $paymentMethod, null)
                );
        
                $capture = new \Pgc\Client\Transaction\Capture();
                // we override the already set Transaction ID generated from the authorization UUID
                // match the format generated by the gateway web interface to avoid duplicate captures
                $capture->setTransactionId('capture-' . $payment->getOrder()->getIncrementId());
                $capture->setReferenceTransactionId($payment->getLastTransId());

                $capture->setAmount(\number_format($amount, 2, '.', ''));
                $capture->setCurrency($payment->getOrder()->getOrderCurrency()->getCode());;

                $captureResult = $client->capture($capture);
                if (!$captureResult->isSuccess()) { 
                    $error = $captureResult->getFirstError();
                    throw new \Exception($error->getMessage());
                }
            } catch(\Exception $e) {
                throw new LocalizedException(__('Could not capture payment: ' . $e->getMessage()));
            }
    
        }
        // close parent authorization so that any order cancel will trigger an offline void
        $payment->setShouldCloseParentTransaction(true);
        // we then set the gateway UUID for the capture transaction ID
        $payment->setTransactionId($captureResult->getReferenceId());

        return $this;
    }

    public function void(InfoInterface $payment, $amount = null)
    {
        $paymentMethod = 'pgc_creditcard';

        try {
            \Pgc\Client\Client::setApiUrl($this->pgcHelper->getGeneralConfigData('host'));
            $client = new \Pgc\Client\Client(
                $this->pgcHelper->getGeneralConfigData('username'),
                $this->pgcHelper->getGeneralConfigData('password'),
                $this->pgcHelper->getPaymentConfigData('api_key', $paymentMethod, null),
                $this->pgcHelper->getPaymentConfigData('shared_secret', $paymentMethod, null)
            );

            $void = new \Pgc\Client\Transaction\VoidTransaction();
            // we override the already set Transaction ID generated from the authorization UUID
            $void->setTransactionId($payment->getOrder()->getIncrementId() . '-void');
            $void->setReferenceTransactionId($payment->getLastTransId());

            $voidResult = $client->void($void);
            if (!$voidResult->isSuccess()) { 
                $error = $voidResult->getFirstError();
                throw new \Exception($error->getMessage());
            }
        } catch(\Exception $e) {
            throw new LocalizedException(__('Could not void payment: ' . $e->getMessage()));
        }

        // we set the UUID returned from the gateway as the Transaction ID
        $payment->setTransactionId($voidResult->getReferenceId());

        return $this;
    }

    public function cancel(InfoInterface $payment, $amount = null)
    {
        // catch exceptions and let the rest of the order cancelation code work and note the void fail
        try {
            $this->void($payment);
        } catch (\Exception $e) {
            $payment->setTransactionId('offline void');
            $payment->setMessage('Online void failed. ' . $e->getMessage());
            $this->messageManager->addWarning(__("Online void failed!"));
            return $this;
        }

        return $this;
    }

}
