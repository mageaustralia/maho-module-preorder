<?php
/** @var Mage_Eav_Model_Entity_Setup $installer */
$installer = $this;
$installer->startSetup();

$catalogProductEntityTypeId = (int) Mage::getModel('eav/entity')
    ->setType('catalog_product')
    ->getTypeId();

// 1. is_preorder (yesno, store-scoped)
$installer->addAttribute('catalog_product', 'is_preorder', [
    'group'                   => 'Preorder',
    'type'                    => 'int',
    'backend'                 => '',
    'frontend'                => '',
    'label'                   => 'Is Preorder',
    'input'                   => 'boolean',
    'class'                   => '',
    'source'                  => 'eav/entity_attribute_source_boolean',
    'global'                  => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
    'visible'                 => true,
    'required'                => false,
    'user_defined'            => true,
    'default'                 => '0',
    'searchable'              => false,
    'filterable'              => false,
    'comparable'              => false,
    'visible_on_front'        => false,
    'used_in_product_listing' => true,
    'unique'                  => false,
    'apply_to'                => 'simple,configurable,virtual,bundle,downloadable',
]);

// 2. preorder_available_date (date, store-scoped)
$installer->addAttribute('catalog_product', 'preorder_available_date', [
    'group'                   => 'Preorder',
    'type'                    => 'datetime',
    'backend'                 => 'eav/entity_attribute_backend_datetime',
    'frontend'                => '',
    'label'                   => 'Preorder Available Date',
    'input'                   => 'date',
    'class'                   => '',
    'source'                  => '',
    'global'                  => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
    'visible'                 => true,
    'required'                => false,
    'user_defined'            => true,
    'searchable'              => false,
    'filterable'              => false,
    'comparable'              => false,
    'visible_on_front'        => false,
    'used_in_product_listing' => true,
    'apply_to'                => 'simple,configurable,virtual,bundle,downloadable',
]);

// 3. preorder_button_text (varchar 64, store-scoped)
$installer->addAttribute('catalog_product', 'preorder_button_text', [
    'group'                   => 'Preorder',
    'type'                    => 'varchar',
    'label'                   => 'Preorder Button Text',
    'input'                   => 'text',
    'global'                  => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
    'visible'                 => true,
    'required'                => false,
    'user_defined'            => true,
    'searchable'              => false,
    'filterable'              => false,
    'comparable'              => false,
    'visible_on_front'        => false,
    'used_in_product_listing' => false,
    'apply_to'                => 'simple,configurable,virtual,bundle,downloadable',
]);

// 4. preorder_message (text, store-scoped)
$installer->addAttribute('catalog_product', 'preorder_message', [
    'group'                   => 'Preorder',
    'type'                    => 'text',
    'label'                   => 'Preorder Message',
    'input'                   => 'textarea',
    'global'                  => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
    'visible'                 => true,
    'required'                => false,
    'user_defined'            => true,
    'searchable'              => false,
    'filterable'              => false,
    'comparable'              => false,
    'visible_on_front'        => false,
    'used_in_product_listing' => false,
    'apply_to'                => 'simple,configurable,virtual,bundle,downloadable',
]);

// Add quote/order item columns - portable across MySQL/SQLite/PG
$connection = $installer->getConnection();

foreach (['sales_flat_quote_item', 'sales_flat_order_item'] as $tableName) {
    $table = $installer->getTable($tableName);
    if (!$connection->tableColumnExists($table, 'is_preorder')) {
        $connection->addColumn($table, 'is_preorder', [
            'type'     => Varien_Db_Ddl_Table::TYPE_SMALLINT,
            'nullable' => false,
            'default'  => 0,
            'comment'  => 'Pre-order flag (1 = preorder, 0 = normal)',
        ]);
    }
    if (!$connection->tableColumnExists($table, 'preorder_available_date')) {
        $connection->addColumn($table, 'preorder_available_date', [
            'type'     => Varien_Db_Ddl_Table::TYPE_DATETIME,
            'nullable' => true,
            'comment'  => 'Pre-order expected dispatch date',
        ]);
    }
}

$installer->endSetup();
