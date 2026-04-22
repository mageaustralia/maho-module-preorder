<?php
class Mageaustralia_Preorder_Block_Button extends Mage_Catalog_Block_Product_View
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('mageaustralia/preorder/button.phtml');
    }

    public function isPreorder(): bool
    {
        $p = $this->getProduct();
        return $p ? Mage::helper('mageaustralia_preorder')->isPreorder($p) : false;
    }

    public function getButtonText(): string
    {
        $p = $this->getProduct();
        return $p ? Mage::helper('mageaustralia_preorder')->getButtonText($p) : 'Pre-order now';
    }

    public function getMessage(): string
    {
        $p = $this->getProduct();
        return $p ? Mage::helper('mageaustralia_preorder')->getMessage($p) : '';
    }
}
