<?php
declare(strict_types=1);

namespace Mageaustralia\Preorder\Tests\Unit;

use PHPUnit\Framework\TestCase;

class HelperDataTest extends TestCase
{
    public function test_isPreorder_returns_false_when_attribute_missing(): void
    {
        $product = new \stdClass();
        $helper = new \Mageaustralia_Preorder_Helper_Data();
        $this->assertFalse($helper->isPreorder($product));
    }

    public function test_isPreorder_returns_true_when_flag_set(): void
    {
        $product = new class {
            public function getData($key) { return $key === 'is_preorder' ? 1 : null; }
        };
        $helper = new \Mageaustralia_Preorder_Helper_Data();
        $this->assertTrue($helper->isPreorder($product));
    }

    public function test_getAvailableDate_returns_null_when_unset(): void
    {
        $product = new \stdClass();
        $helper = new \Mageaustralia_Preorder_Helper_Data();
        $this->assertNull($helper->getAvailableDate($product));
    }

    public function test_getAvailableDate_returns_DateTimeImmutable_when_set(): void
    {
        $product = new class {
            public function getData($key) { return $key === 'preorder_available_date' ? '2026-05-15 00:00:00' : null; }
        };
        $helper = new \Mageaustralia_Preorder_Helper_Data();
        $date = $helper->getAvailableDate($product);
        $this->assertInstanceOf(\DateTimeImmutable::class, $date);
        $this->assertSame('2026-05-15', $date->format('Y-m-d'));
    }

    public function test_getButtonText_returns_product_override_when_set(): void
    {
        $product = new class {
            public function getData($key) { return $key === 'preorder_button_text' ? 'Reserve yours' : null; }
        };
        $helper = new \Mageaustralia_Preorder_Helper_Data();
        $this->assertSame('Reserve yours', $helper->getButtonText($product));
    }
}
