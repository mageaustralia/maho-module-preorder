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
     * Inject preorder label into rendered cart item HTML AND inject a badge
     * onto each preorder product card in category / search list views.
     *
     * Hooked to core_block_abstract_to_html_after so the module works against
     * the unmodified core list.phtml — no template override needed.
     */
    public function onBlockHtmlAfter(Varien_Event_Observer $observer): void
    {
        $block = $observer->getEvent()->getBlock();
        $transport = $observer->getEvent()->getTransport();
        if (!$block || !$transport) {
            return;
        }

        // Cart line label — inject INSIDE the product-cart-info <td> so it sits
        // with the product/SKU/options, not appended after the row.
        // Inline the HTML (no createBlock) to avoid registering an orphan block in
        // the layout that other parents may render unexpectedly.
        if ($block instanceof Mage_Checkout_Block_Cart_Item_Renderer) {
            $item = $block->getItem();
            if (!$item || !$item->getIsPreorder()) {
                return;
            }
            $rawDate = $item->getPreorderAvailableDate();
            $dateText = $rawDate ? date('M j, Y', strtotime((string) $rawDate)) : '';
            $labelHtml = $dateText !== ''
                ? sprintf(
                    '<span class="mageaustralia-preorder-cart-label">%s &middot; ships ~%s</span>',
                    Mage::helper('mageaustralia_preorder')->__('Pre-order'),
                    htmlspecialchars($dateText, ENT_QUOTES),
                )
                : sprintf(
                    '<span class="mageaustralia-preorder-cart-label">%s</span>',
                    Mage::helper('mageaustralia_preorder')->__('Pre-order'),
                );

            $html = $transport->getHtml();
            // Try product-cart-info td (default Maho cart theme).
            $injected = preg_replace(
                '/(<td[^>]*class="[^"]*product-cart-info[^"]*"[^>]*>[\s\S]*?)(<\/td>)/',
                '$1' . $labelHtml . '$2',
                $html,
                1,
            );
            // Mini-cart fallback: insert before </a> of the product link inside <li>.
            if ($injected === null || $injected === $html) {
                $injected = preg_replace(
                    '/(<li[^>]*class="[^"]*item[^"]*"[^>]*>[\s\S]*?<a[^>]*>[\s\S]*?<\/a>)/',
                    '$1' . $labelHtml,
                    $html,
                    1,
                );
            }
            if ($injected !== null && $injected !== $html) {
                $transport->setHtml($injected);
            } else {
                // Theme doesn't match either pattern — append (legacy behaviour).
                $transport->setHtml($html . $labelHtml);
            }
            return;
        }

        // Category / search product list — append a badge to each preorder card.
        // Skip the landing-page block (Mageaustralia_Preorder_Block_Landing_ProductList)
        // because list.phtml already renders the badge inline per item.
        if ($block instanceof Mage_Catalog_Block_Product_List
            && !($block instanceof Mageaustralia_Preorder_Block_Landing_ProductList)
        ) {
            $collection = $block->getLoadedProductCollection();
            if (!$collection) {
                return;
            }
            $html = $transport->getHtml();
            $changed = false;
            // Look up preorder flags in one query (avoid N+1) — list collections
            // don't always eager-load custom attributes added post-install.
            $productIds = [];
            foreach ($collection as $product) {
                $productIds[] = (int) $product->getId();
            }
            if (empty($productIds)) {
                return;
            }
            /** @var Mage_Catalog_Model_Resource_Product_Collection $flagColl */
            $flagColl = Mage::getResourceModel('catalog/product_collection');
            $flagColl
                ->addAttributeToSelect(['is_preorder', 'preorder_available_date', 'preorder_button_text', 'preorder_message'])
                ->addFieldToFilter('entity_id', ['in' => $productIds])
                ->setStoreId(Mage::app()->getStore()->getId());
            $byId = [];
            foreach ($flagColl as $p) {
                $byId[(int) $p->getId()] = $p;
            }

            foreach ($collection as $product) {
                $id = (int) $product->getId();
                $hydrated = $byId[$id] ?? null;
                if (!$hydrated || !Mage::helper('mageaustralia_preorder')->isPreorder($hydrated)) {
                    continue;
                }
                $product = $hydrated;
                $badge = Mage::app()->getLayout()
                    ->createBlock('mageaustralia_preorder/badge')
                    ->setData('product', $product)
                    ->toHtml();
                if ($badge === '') {
                    continue;
                }
                // Attach the badge after the product name link for this product.
                // Match the catalog/product/list.phtml link pattern (catalog/product/view URL).
                $needle = sprintf('product_id=%d', (int) $product->getId());
                if (str_contains($html, $needle)) {
                    // Maho list.phtml emits a <button …>Add to Cart</button> per item.
                    // Replace the FIRST add-to-cart for this product with the badge.
                    // Robust enough for default + sample-data themes.
                    $pattern = sprintf(
                        '/(<button[^>]*onclick="[^"]*product\\/%d[^"]*"[^>]*>.*?<\\/button>)/s',
                        (int) $product->getId(),
                    );
                    if (preg_match($pattern, $html)) {
                        $html = preg_replace($pattern, $badge . '$1', $html, 1) ?? $html;
                        $changed = true;
                        continue;
                    }
                }
                // Fallback: append to product name anchor for this product URL.
                $url = $product->getProductUrl();
                if ($url) {
                    $escUrl = htmlspecialchars($url, ENT_QUOTES);
                    $idx = strpos($html, $escUrl);
                    if ($idx !== false) {
                        // Insert badge right after the closing </a> following the URL match.
                        $closeIdx = strpos($html, '</a>', $idx);
                        if ($closeIdx !== false) {
                            $html = substr($html, 0, $closeIdx + 4) . $badge . substr($html, $closeIdx + 4);
                            $changed = true;
                        }
                    }
                }
            }
            if ($changed) {
                $transport->setHtml($html);
            }
        }
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

        // Build email body from template (if configured), fall back to plain text.
        // NOTE: loadDefault() reads from config.xml-registered template files and
        // does NOT set an entity_id, so checking getId() always returns 0.
        // Use getTemplateText() to detect a successfully-loaded default template.
        $body = null;
        $subject = null;
        /** @var Mage_Core_Model_Email_Template $tpl */
        $tpl = Mage::getModel('core/email_template');
        $tpl->loadDefault(Mageaustralia_Preorder_Model_Email_Sender::TEMPLATE_CONFIRMATION);
        if ($tpl->getTemplateText()) {
            // Maho's email-template filter doesn't support {{foreach}} — pre-render
            // the items rows in PHP and pass as a single HTML var.
            $itemsHtml = '';
            foreach ($preorderItems as $i) {
                $d = $i->getPreorderAvailableDate();
                $dateText = $d ? date('M j, Y', strtotime((string) $d)) : '';
                $itemsHtml .= sprintf(
                    '<tr><td style="padding:12px 0;border-bottom:1px solid #ececec;">'
                    . '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%%">'
                    . '<tr>'
                    . '<td style="font-size:14px;font-weight:600;color:#1a1a2e;">%s</td>'
                    . '<td align="right" style="font-size:13px;color:#1a1a2e;white-space:nowrap;">%s &times; %d</td>'
                    . '</tr>'
                    . '<tr><td colspan="2" style="font-size:12px;color:#6b6b6b;padding-top:4px;">SKU: %s &nbsp;&middot;&nbsp; Ships ~%s</td></tr>'
                    . '</table></td></tr>',
                    htmlspecialchars((string) $i->getName(), ENT_QUOTES),
                    htmlspecialchars(Mage::helper('core')->currency($i->getPrice(), true, false), ENT_QUOTES),
                    (int) $i->getQtyOrdered(),
                    htmlspecialchars((string) $i->getSku(), ENT_QUOTES),
                    htmlspecialchars($dateText, ENT_QUOTES),
                );
            }
            // Maho's template filter doesn't traverse nested arrays; pass scalars
            // and Varien_Object for any structured access.
            $vars = [
                'order'                       => $order,
                'customer'                    => new Varien_Object(['firstname' => $order->getCustomerFirstname() ?: 'there']),
                'preorder_items_html'         => $itemsHtml,
                'preorder_items_count'        => count($preorderItems),
                'earliest_dispatch_formatted' => $earliest->format('M j, Y'),
                'store_name'                  => Mage::getStoreConfig('general/store_information/name', $order->getStoreId()) ?: Mage::app()->getStore($order->getStoreId())->getName(),
                'store_url'                   => Mage::app()->getStore($order->getStoreId())->getBaseUrl(),
                'preorder_landing_url'        => Mage::app()->getStore($order->getStoreId())->getBaseUrl() . 'preorder',
            ];
            $tpl->setSenderName(Mage::getStoreConfig('trans_email/ident_general/name', $order->getStoreId()));
            $tpl->setSenderEmail(Mage::getStoreConfig('trans_email/ident_general/email', $order->getStoreId()));
            $body = $tpl->getProcessedTemplate($vars);
            $subject = $tpl->getProcessedTemplateSubject($vars);
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
            ->subject($subject ?: 'Your pre-order is confirmed');

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
