<?php
class Mageaustralia_Preorder_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const XML_PATH_DEFAULT_BUTTON_TEXT = 'mageaustralia_preorder/general/default_button_text';
    public const XML_PATH_LANDING_ENABLED     = 'mageaustralia_preorder/general/landing_page_enabled';
    public const XML_PATH_REMINDER_7D         = 'mageaustralia_preorder/general/send_reminder_7d';
    public const XML_PATH_REMINDER_1D         = 'mageaustralia_preorder/general/send_reminder_1d';
    public const XML_PATH_ICS_ATTACH          = 'mageaustralia_preorder/general/ics_attach';

    public function isPreorder(mixed $product): bool
    {
        return $this->getProductData($product, 'is_preorder') ? true : false;
    }

    public function getAvailableDate(mixed $product): ?\DateTimeImmutable
    {
        $raw = $this->getProductData($product, 'preorder_available_date');
        if (!$raw || !is_string($raw)) {
            return null;
        }
        try {
            return new \DateTimeImmutable($raw);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getButtonText(mixed $product): string
    {
        $override = $this->getProductData($product, 'preorder_button_text');
        if (is_string($override) && $override !== '') {
            return $override;
        }
        $default = Mage::getStoreConfig(self::XML_PATH_DEFAULT_BUTTON_TEXT);
        return is_string($default) && $default !== '' ? $default : 'Pre-order now';
    }

    public function getMessage(mixed $product): string
    {
        $msg = $this->getProductData($product, 'preorder_message');
        return is_string($msg) ? $msg : '';
    }

    public function isLandingEnabled(): bool
    {
        return (bool) Mage::getStoreConfigFlag(self::XML_PATH_LANDING_ENABLED);
    }

    public function shouldSendReminder7d(): bool
    {
        return (bool) Mage::getStoreConfigFlag(self::XML_PATH_REMINDER_7D);
    }

    public function shouldSendReminder1d(): bool
    {
        return (bool) Mage::getStoreConfigFlag(self::XML_PATH_REMINDER_1D);
    }

    public function shouldAttachIcs(): bool
    {
        return (bool) Mage::getStoreConfigFlag(self::XML_PATH_ICS_ATTACH);
    }

    private function getProductData(mixed $product, string $key): mixed
    {
        if (is_object($product) && method_exists($product, 'getData')) {
            return $product->getData($key);
        }
        if (is_array($product)) {
            return $product[$key] ?? null;
        }
        return null;
    }
}
