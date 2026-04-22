<?php
class Mageaustralia_Preorder_Model_Observer
{
    /**
     * Event: checkout_cart_product_add_after
     * Copy is_preorder + preorder_available_date from product onto quote item.
     */
    public function onCartItemAdd(Varien_Event_Observer $observer): void
    {
        /** @var Mage_Sales_Model_Quote_Item $item */
        $item = $observer->getEvent()->getQuoteItem();
        $product = $observer->getEvent()->getProduct();
        if (!$item || !$product) {
            return;
        }
        $helper = Mage::helper('mageaustralia_preorder');
        if (!$helper->isPreorder($product)) {
            return;
        }
        $item->setIsPreorder(1);
        $date = $helper->getAvailableDate($product);
        if ($date) {
            $item->setPreorderAvailableDate($date->format('Y-m-d H:i:s'));
        }
    }

    /**
     * Event: sales_convert_quote_item_to_order_item
     * Carry the flag from quote item onto order item.
     */
    public function onQuoteItemToOrderItem(Varien_Event_Observer $observer): void
    {
        /** @var Mage_Sales_Model_Order_Item $orderItem */
        $orderItem = $observer->getEvent()->getOrderItem();
        $quoteItem = $observer->getEvent()->getItem();
        if (!$orderItem || !$quoteItem) {
            return;
        }
        if ($quoteItem->getIsPreorder()) {
            $orderItem->setIsPreorder(1);
            $orderItem->setPreorderAvailableDate($quoteItem->getPreorderAvailableDate());
        }
    }

    /**
     * Inject preorder label into rendered cart item HTML.
     * Hooked to core_block_abstract_to_html_after so we don't need a template override.
     */
    public function onBlockHtmlAfter(Varien_Event_Observer $observer): void
    {
        $block = $observer->getEvent()->getBlock();
        $transport = $observer->getEvent()->getTransport();
        if (!$block || !$transport) {
            return;
        }
        if (!$block instanceof Mage_Checkout_Block_Cart_Item_Renderer) {
            return;
        }
        $item = $block->getItem();
        if (!$item || !$item->getIsPreorder()) {
            return;
        }
        $labelBlock = Mage::app()->getLayout()
            ->createBlock('mageaustralia_preorder/cart_item')
            ->setData('item', $item);
        $transport->setHtml($transport->getHtml() . $labelBlock->toHtml());
    }
}
