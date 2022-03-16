<?php

namespace Pgc\Pgc\Observer;

use Magento\Framework\Event\ObserverInterface;

class SalesEventQuoteSubmitBeforeObserver implements ObserverInterface
{
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if($observer->getEvent()->getQuote()->getPayment()->getMethod() == 'pgc_creditcard') {
            $observer->getEvent()->getOrder()->setCanSendNewEmailFlag(false);
        }
        return $this;
    }
}