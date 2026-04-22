<?php
/**
 * Add the four preorder attributes to every existing catalog_product
 * attribute set. The 0.1.0 install only attached them to the Default set
 * (Mage_Eav_Model_Entity_Setup::addAttribute() default behaviour), which
 * left products on Clothing / Electronics / etc. without the Preorder tab.
 *
 * @var Mage_Eav_Model_Entity_Setup $installer
 */
$installer = $this;
$installer->startSetup();

$entityTypeId = (int) $installer->getEntityTypeId('catalog_product');

$attributeCodes = [
    'is_preorder',
    'preorder_available_date',
    'preorder_button_text',
    'preorder_message',
];

/** @var Mage_Eav_Model_Resource_Entity_Attribute_Set_Collection $sets */
$sets = Mage::getResourceModel('eav/entity_attribute_set_collection')
    ->setEntityTypeFilter($entityTypeId);

foreach ($sets as $set) {
    $setId = (int) $set->getAttributeSetId();

    // Ensure a "Preorder" group exists on this set; create if missing.
    $groupId = $installer->getAttributeGroupId($entityTypeId, $setId, 'Preorder');
    if (!$groupId) {
        $installer->addAttributeGroup($entityTypeId, $setId, 'Preorder', 100);
        $groupId = $installer->getAttributeGroupId($entityTypeId, $setId, 'Preorder');
    }

    foreach ($attributeCodes as $code) {
        $attributeId = (int) $installer->getAttributeId($entityTypeId, $code);
        if (!$attributeId) {
            continue;
        }
        // addAttributeToSet is idempotent (skips if already assigned).
        $installer->addAttributeToSet(
            $entityTypeId,
            $setId,
            $groupId,
            $attributeId,
        );
    }
}

$installer->endSetup();
