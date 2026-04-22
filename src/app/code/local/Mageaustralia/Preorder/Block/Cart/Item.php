<?php
class Mageaustralia_Preorder_Block_Cart_Item extends Mage_Core_Block_Template
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('mageaustralia/preorder/cart/item.phtml');
    }

    public function getItem(): ?Mage_Sales_Model_Quote_Item
    {
        $item = $this->getData('item');
        return $item instanceof Mage_Sales_Model_Quote_Item ? $item : null;
    }

    public function isPreorder(): bool
    {
        $i = $this->getItem();
        return $i ? (bool) $i->getIsPreorder() : false;
    }

    public function getDateFormatted(): ?string
    {
        $i = $this->getItem();
        if (!$i || !$i->getPreorderAvailableDate()) {
            return null;
        }
        try {
            return (new \DateTimeImmutable($i->getPreorderAvailableDate()))->format('M j, Y');
        } catch (\Exception $e) {
            return null;
        }
    }
}
