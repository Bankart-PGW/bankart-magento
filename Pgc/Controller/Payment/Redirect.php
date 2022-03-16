<?php

namespace Pgc\Pgc\Controller\Payment;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;


class Redirect extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $session;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $session
    ) {
        parent::__construct($context);
        $this->session = $session;
    }

    public function execute()
    {
        $this->session->restoreQuote();
        if($this->getRequest()->getParam('reason') == 'error') {
            $this->messageManager->addErrorMessage("Payment failed.");
            $this->_redirect('checkout/cart');
        }
        else $this->_redirect('checkout', ['_fragment' => 'payment']);
    }

    public function createCsrfValidationException(
        RequestInterface $request
    ): ?InvalidRequestException {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
