<?php

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email as SymfonyEmail;

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

    /**
     * Event: sales_order_place_after
     * Send a separate confirmation email with .ics attachment for preorder items.
     * Uses Symfony Mailer (Maho's native mail layer — Zend_Mail is not available).
     */
    public function onOrderPlaceAfter(Varien_Event_Observer $observer): void
    {
        $helper = Mage::helper('mageaustralia_preorder');
        if (!$helper->shouldAttachIcs()) {
            return;
        }

        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getEvent()->getOrder();
        if (!$order) {
            return;
        }

        // Collect preorder line items
        $preorderItems = [];
        foreach ($order->getAllVisibleItems() as $item) {
            if ($item->getIsPreorder() && $item->getPreorderAvailableDate()) {
                $preorderItems[] = $item;
            }
        }
        if (empty($preorderItems)) {
            return;
        }

        // Find the earliest dispatch date for the confirmation email
        $earliest = null;
        foreach ($preorderItems as $item) {
            $d = new \DateTimeImmutable($item->getPreorderAvailableDate());
            if ($earliest === null || $d < $earliest) {
                $earliest = $d;
            }
        }

        // Build .ics events per preorder item
        /** @var Mageaustralia_Preorder_Helper_Calendar $cal */
        $cal = Mage::helper('mageaustralia_preorder/calendar');
        $icsParts = [];
        foreach ($preorderItems as $item) {
            $host = parse_url(Mage::getBaseUrl(), PHP_URL_HOST) ?: 'mage.local';
            $icsParts[] = $cal->buildEvent(
                uid: sprintf('preorder-%s-%d@%s', $order->getIncrementId(), $item->getId(), $host),
                summary: 'Pre-order ships: ' . $item->getName(),
                date: new \DateTimeImmutable($item->getPreorderAvailableDate()),
                description: sprintf(
                    'Your pre-ordered %s will ship around this date. Order: %s.',
                    $item->getName(),
                    $order->getIncrementId(),
                ),
            );
        }
        $combinedIcs = $this->combineIcs($icsParts);

        // $earliest is guaranteed non-null here: $preorderItems is non-empty (checked above)
        // and all items have getPreorderAvailableDate() (filtered above).
        assert($earliest !== null);

        // Build email body from template (if configured), fall back to plain text
        $body = null;
        /** @var Mage_Core_Model_Email_Template $tpl */
        $tpl = Mage::getModel('core/email_template');
        $tpl->loadDefault(Mageaustralia_Preorder_Model_Email_Sender::TEMPLATE_CONFIRMATION);
        if ($tpl->getId()) {
            $body = $tpl->getProcessedTemplate([
                'order'                       => $order,
                'customer'                    => ['firstname' => $order->getCustomerFirstname()],
                'earliest_dispatch_formatted' => $earliest->format('M j, Y'),
            ]);
        }

        // Get Symfony Mailer transport via Maho core helper
        $mailTransport = Mage::helper('core')->getMailTransport();
        if (!$mailTransport) {
            // Email sending is disabled in store config — skip silently
            return;
        }

        $fromEmail = (string) Mage::getStoreConfig('trans_email/ident_general/email', $order->getStoreId());
        $fromName  = (string) Mage::getStoreConfig('trans_email/ident_general/name', $order->getStoreId());

        $symfonyEmail = (new SymfonyEmail())
            ->from(new Address($fromEmail, $fromName))
            ->to(new Address((string) $order->getCustomerEmail(), (string) $order->getCustomerName()))
            ->subject('Your pre-order — calendar event attached');

        if ($body !== null) {
            $symfonyEmail->html($body);
        } else {
            $symfonyEmail->text(sprintf(
                "Hi %s,\n\nYour pre-order %s ships around %s.\n",
                $order->getCustomerFirstname(),
                $order->getIncrementId(),
                $earliest->format('M j, Y'),
            ));
        }

        $symfonyEmail->attach($combinedIcs, 'preorder.ics', 'text/calendar; charset=utf-8; method=PUBLISH');

        try {
            $mailer = new Mailer($mailTransport);
            $mailer->send($symfonyEmail);
        } catch (\Exception $e) {
            Mage::logException($e);
        }
    }

    /**
     * Merge multiple VCALENDAR strings (each with one VEVENT) into one VCALENDAR
     * with multiple VEVENTs.
     *
     * @param string[] $parts
     */
    private function combineIcs(array $parts): string
    {
        if (empty($parts)) {
            return '';
        }
        $events = [];
        foreach ($parts as $ics) {
            if (preg_match('/BEGIN:VEVENT.*?END:VEVENT/s', $ics, $m)) {
                $events[] = $m[0];
            }
        }
        return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Mageaustralia//Preorder//EN\r\nCALSCALE:GREGORIAN\r\nMETHOD:PUBLISH\r\n" .
               implode("\r\n", $events) . "\r\n" .
               "END:VCALENDAR\r\n";
    }
}
