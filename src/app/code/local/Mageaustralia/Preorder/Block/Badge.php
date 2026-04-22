<?php
class Mageaustralia_Preorder_Block_Badge extends Mage_Core_Block_Template
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('mageaustralia/preorder/badge.phtml');
    }

    public function getProduct(): ?Mage_Catalog_Model_Product
    {
        $product = $this->getData('product');
        if ($product instanceof Mage_Catalog_Model_Product) {
            return $product;
        }
        return Mage::registry('current_product');
    }

    public function isPreorder(): bool
    {
        $p = $this->getProduct();
        return $p ? Mage::helper('mageaustralia_preorder')->isPreorder($p) : false;
    }

    public function getAvailableDateFormatted(): ?string
    {
        $p = $this->getProduct();
        if (!$p) {
            return null;
        }
        $date = Mage::helper('mageaustralia_preorder')->getAvailableDate($p);
        return $date ? $date->format('M j, Y') : null;
    }
}
