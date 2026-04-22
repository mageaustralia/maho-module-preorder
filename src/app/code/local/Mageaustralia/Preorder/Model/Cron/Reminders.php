<?php
class Mageaustralia_Preorder_Model_Cron_Reminders
{
    public function run(): void
    {
        $helper = Mage::helper('mageaustralia_preorder');
        $sender = new Mageaustralia_Preorder_Model_Email_Sender();

        // Compute target date windows (UTC, day-precision)
        $plus7 = (new \DateTimeImmutable('today +7 days', new \DateTimeZone('UTC')))->format('Y-m-d');
        $plus8 = (new \DateTimeImmutable('today +8 days', new \DateTimeZone('UTC')))->format('Y-m-d');
        $plus1 = (new \DateTimeImmutable('today +1 day',  new \DateTimeZone('UTC')))->format('Y-m-d');
        $plus2 = (new \DateTimeImmutable('today +2 days', new \DateTimeZone('UTC')))->format('Y-m-d');

        if ($helper->shouldSendReminder7d()) {
            $this->sendForWindow($sender, $plus7, $plus8, '7d');
        }
        if ($helper->shouldSendReminder1d()) {
            $this->sendForWindow($sender, $plus1, $plus2, '1d');
        }
    }

    private function sendForWindow(Mageaustralia_Preorder_Model_Email_Sender $sender, string $from, string $to, string $variant): void
    {
        /** @var Mage_Sales_Model_Resource_Order_Item_Collection $items */
        $items = Mage::getResourceModel('sales/order_item_collection');
        $items->addFieldToFilter('is_preorder', 1)
              ->addFieldToFilter('preorder_available_date', ['gteq' => $from])
              ->addFieldToFilter('preorder_available_date', ['lt'   => $to]);

        foreach ($items as $item) {
            /** @var Mage_Sales_Model_Order_Item $item */
            $order = $item->getOrder() ?: Mage::getModel('sales/order')->load($item->getOrderId());
            if ($order->getId()) {
                $sender->sendReminder($order, $item, $variant);
            }
        }
    }
}
