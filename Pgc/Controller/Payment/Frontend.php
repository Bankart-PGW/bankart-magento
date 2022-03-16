<?php

namespace Pgc\Pgc\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\UrlInterface;
use Magento\Payment\Helper\Data;
use Magento\Sales\Model\Order;

class Frontend extends Action
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $session;

    /**
     * @var \Magento\Checkout\Api\PaymentInformationManagementInterface
     */
    private $paymentInformation;

    /**
     * @var Data
     */
    private $paymentHelper;

    /**
     * @var \Pgc\Pgc\Helper\Data
     */
    private $pgcHelper;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * Frontend constructor.
     * @param Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     */
    public function __construct(
        Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Checkout\Api\PaymentInformationManagementInterface $paymentInformation,
        Data $paymentHelper,
        \Pgc\Pgc\Helper\Data $pgcHelper,
        UrlInterface $urlBuilder,
        JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
        $this->session = $checkoutSession;
        $this->paymentInformation = $paymentInformation;
        $this->paymentHelper = $paymentHelper;
        $this->urlBuilder = $urlBuilder;
        $this->pgcHelper = $pgcHelper;
        $this->resultJsonFactory = $resultJsonFactory;
    }

    public function execute()
    {
        $request = $this->getRequest()->getPost()->toArray();
        $response = $this->resultJsonFactory->create();

        $paymentMethod = 'pgc_creditcard';

        //TODO: SELECT CORRECT PAYMENT SETTINGS
        \Pgc\Client\Client::setApiUrl($this->pgcHelper->getGeneralConfigData('host'));
        $client = new \Pgc\Client\Client(
            $this->pgcHelper->getGeneralConfigData('username'),
            $this->pgcHelper->getGeneralConfigData('password'),
            $this->pgcHelper->getPaymentConfigData('api_key', $paymentMethod, null),
            $this->pgcHelper->getPaymentConfigData('shared_secret', $paymentMethod, null)
        );
        $transactionType = $this->pgcHelper->getPaymentConfigData('transaction_type', $paymentMethod, null);
        $order = $this->session->getLastRealOrder();

        $transaction = null; 
        switch ($transactionType) {
            case 'debit':
                $transaction = new \Pgc\Client\Transaction\Debit();
                break;
            case 'preauth':
            default:
                $transaction = new \Pgc\Client\Transaction\Preauthorize();
                break;
        }

        $amount = \number_format($order->getGrandTotal(), 2, '.', '');
        $instalments = $this->pgcHelper->getPaymentConfigData('instalments', $paymentMethod);

        if ($instalments > 1) {
            $instalmentsSelected = (int) $request['instalments'];
            $instalmentsAmount = $this->pgcHelper->getPaymentConfigData('instalments_amount', $paymentMethod);
            $calculateInstalments = floor($amount/$instalmentsAmount);

            //  improve error handling
            if (($instalmentsSelected > $calculateInstalments) || ($instalmentsSelected > $instalments)) {
                die('Bad instalment data!');
            }
            $transaction->addExtraData('userField1', $instalments);
        }

        $transaction->setTransactionId($order->getIncrementId());
        $transaction->setAmount($amount);
        $transaction->setCurrency($order->getOrderCurrency()->getCode());

        $customer = new \Pgc\Client\Data\Customer();
        $customer->setFirstName($order->getCustomerFirstname());
        $customer->setLastName($order->getCustomerLastname());
        $customer->setEmail($order->getCustomerEmail());

        $billingAddress = $order->getBillingAddress();
        if ($billingAddress !== null) {
            $customer->setBillingAddress1($billingAddress->getStreet()[0]);
            $customer->setBillingPostcode($billingAddress->getPostcode());
            $customer->setBillingCity($billingAddress->getCity());
            $customer->setBillingCountry($billingAddress->getCountryId());
            $customer->setBillingPhone($billingAddress->getTelephone());
        }
        $shippingAddress = $order->getShippingAddress();
        if ($shippingAddress !== null) {
            $customer->setShippingCompany($shippingAddress->getCompany());
            $customer->setShippingFirstName($shippingAddress->getFirstname());
            $customer->setShippingLastName($shippingAddress->getLastname());
            $customer->setShippingAddress1($shippingAddress->getStreet()[0]);
            $customer->setShippingPostcode($shippingAddress->getPostcode());
            $customer->setShippingCity($shippingAddress->getCity());
            $customer->setShippingCountry($shippingAddress->getCountryId());
        }

        $transaction->setCustomer($customer);

        $baseUrl = $this->urlBuilder->getRouteUrl('pgc');

        $transaction->setSuccessUrl($this->urlBuilder->getUrl('checkout/onepage/success'));
        $transaction->setCancelUrl($baseUrl . 'payment/redirect?reason=cancel');
        $transaction->setErrorUrl($baseUrl . 'payment/redirect?reason=error');

        $transaction->setCallbackUrl($baseUrl . 'payment/callback');

        switch ($transactionType) {
            case 'debit':
                $paymentResult = $client->debit($transaction);
                break;
            case 'preauth':
            default:
                $paymentResult = $client->preauthorize($transaction);
                break;
        }

        if (!$paymentResult->isSuccess()) {
            $response->setData([
                'type' => 'error',
                'errors' => $paymentResult->getFirstError()->getMessage()
            ]);
            return $response;
        }

        if ($paymentResult->getReturnType() == \Pgc\Client\Transaction\Result::RETURN_TYPE_ERROR) {

            // redundant? Type error should be covered by is success? Will it also have to restore quote in case of payment.js?

            $response->setData([
                'type' => 'error',
                'errors' => $paymentResult->getFirstError()->getMessage()
            ]);
            return $response;

        } elseif ($paymentResult->getReturnType() == \Pgc\Client\Transaction\Result::RETURN_TYPE_REDIRECT) {

            // case for HPP redirect or payment.js 3DS redirect

            $response->setData([
                'type' => 'redirect',
                'url' => $paymentResult->getRedirectUrl()
            ]);

            return $response;

        } elseif ($paymentResult->getReturnType() == \Pgc\Client\Transaction\Result::RETURN_TYPE_PENDING) {
            //payment is pending, wait for callback to complete

            //setCartToPending();

        } elseif ($paymentResult->getReturnType() == \Pgc\Client\Transaction\Result::RETURN_TYPE_FINISHED) {

            // missing result type handling success/error?
            // to be used for payment.js without 3D Secure redirect

            $response->setData([
                'type' => 'finished',
            ]);
        }

        return $response;
    }
}
