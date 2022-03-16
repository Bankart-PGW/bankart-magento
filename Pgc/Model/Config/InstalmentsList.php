<?php
namespace Pgc\Pgc\Model\Config;
 
class InstalmentsList implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => '1', 'label' => __('No instalments')],
            ['value' => '6', 'label' => __('6 instalments')],
            ['value' => '12', 'label' => __('12 instalments')],
            ['value' => '24', 'label' => __('24 instalments')],
            ['value' => '36', 'label' => __('36 instalments')],
        ];
    }
}