<?php
class Mageaustralia_Preorder_Block_Landing_ProductList extends Mage_Catalog_Block_Product_List
{
    protected function _getProductCollection()
    {
        if ($this->_productCollection === null) {
            /** @var Mage_Catalog_Model_Resource_Product_Collection $collection */
            $collection = Mage::getResourceModel('catalog/product_collection');
            $collection->addAttributeToSelect(['name', 'small_image', 'price', 'preorder_available_date', 'preorder_message'])
                       ->addAttributeToFilter('is_preorder', 1)
                       ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED);

            $today = date('Y-m-d 00:00:00');
            $collection->addAttributeToFilter([
                ['attribute' => 'preorder_available_date', 'null'    => true],
                ['attribute' => 'preorder_available_date', 'gteq'    => $today],
            ]);

            // Sort: nulls last, then by date asc — use portable expression not MySQL ISNULL()
            $collection->getSelect()->order(['(at_preorder_available_date.value IS NULL) ASC', 'at_preorder_available_date.value ASC']);

            /** @var Mage_Catalog_Model_Product_Visibility $visibility */
            $visibility = Mage::getSingleton('catalog/product_visibility');
            $visibility->addVisibleInCatalogFilterToCollection($collection);

            $this->_productCollection = $collection;
        }
        return $this->_productCollection;
    }
}
