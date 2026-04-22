<?php
class Mageaustralia_Preorder_Model_Email_Sender
{
    /** @phpstan-ignore classConstant.unused */
    public const TEMPLATE_CONFIRMATION = 'mageaustralia_preorder_confirmation';
    private const TEMPLATE_REMINDER_7D  = 'mageaustralia_preorder_reminder_7d';
    private const TEMPLATE_REMINDER_1D  = 'mageaustralia_preorder_reminder_1d';

    public function sendReminder(Mage_Sales_Model_Order $order, Mage_Sales_Model_Order_Item $item, string $variant): bool
    {
        $template = match ($variant) {
            '7d'    => self::TEMPLATE_REMINDER_7D,
            '1d'    => self::TEMPLATE_REMINDER_1D,
            default => null,
        };
        if (!$template) {
            return false;
        }

        $dispatchDate = $item->getPreorderAvailableDate();
        $formatted    = $dispatchDate ? date('M j, Y', strtotime($dispatchDate)) : '';

        /** @var Mage_Core_Model_Email_Template $email */
        $email = Mage::getModel('core/email_template');
        $email->loadDefault($template);
        if (!$email->getId()) {
            return false; // template not configured, skip silently
        }

        $email->setSenderName(Mage::getStoreConfig('trans_email/ident_general/name', $order->getStoreId()));
        $email->setSenderEmail(Mage::getStoreConfig('trans_email/ident_general/email', $order->getStoreId()));
        $email->setTemplateSubject($email->getTemplateSubject());

        return (bool) $email->send(
            $order->getCustomerEmail(),
            $order->getCustomerName(),
            [
                'order'                   => $order,
                'product'                 => ['name' => $item->getName()],
                'customer'                => ['firstname' => $order->getCustomerFirstname()],
                'dispatch_date_formatted' => $formatted,
            ],
        );
    }
}
