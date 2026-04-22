<?php
class Mageaustralia_Preorder_Block_JsonLd extends Mage_Core_Block_Template
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getProductsJsonLd(): array
    {
        $list = $this->getLayout()->getBlock('mageaustralia.preorder.product_list');
        if (!$list) {
            return [];
        }
        /** @var Mageaustralia_Preorder_Block_Landing_ProductList $list */
        $collection = $list->getLoadedProductCollection();
        $items = [];
        foreach ($collection as $product) {
            /** @var Mage_Catalog_Model_Product $product */
            $availableDate = Mage::helper('mageaustralia_preorder')->getAvailableDate($product);
            $items[] = [
                '@context'   => 'https://schema.org',
                '@type'      => 'Product',
                'name'       => $product->getName(),
                'image'      => (string) Mage::helper('catalog/image')->init($product, 'image'),
                'description'=> strip_tags((string) $product->getShortDescription()),
                'sku'        => $product->getSku(),
                'url'        => $product->getProductUrl(),
                'offers'     => [
                    '@type'         => 'Offer',
                    'price'         => number_format((float) $product->getFinalPrice(), 2, '.', ''),
                    'priceCurrency' => Mage::app()->getStore()->getCurrentCurrencyCode(),
                    'availability'  => 'https://schema.org/PreOrder',
                    'availabilityStarts' => $availableDate ? $availableDate->format(DATE_ATOM) : null,
                ],
            ];
        }
        return $items;
    }
}
