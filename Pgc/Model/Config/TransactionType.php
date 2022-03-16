<?php
namespace Pgc\Pgc\Model\Config;
 
class TransactionType implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'debit', 'label' => __('Debit')],
            ['value' => 'preauth', 'label' => __('Preuthorization')],
        ];
    }
}