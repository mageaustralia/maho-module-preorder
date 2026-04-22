<?php
/**
 * Move the four preorder attributes into a dedicated "Preorder" group on
 * every catalog_product attribute set.
 *
 * The 0.1.0 → 0.1.1 upgrade tried this but used the wrong group lookup —
 * Mage_Eav_Model_Entity_Setup::getAttributeGroupId() falls back to the
 * default ("General") group when the named one doesn't exist, so the
 * upgrade silently dumped attributes into General instead of creating a
 * new Preorder tab. This version uses getAttributeGroup() directly,
 * which returns false on miss.
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

    // getAttributeGroup() returns false when the named group doesn't exist
    // on this set (unlike getAttributeGroupId() which silently falls back
    // to the default group).
    $groupRow = $installer->getAttributeGroup($entityTypeId, $setId, 'Preorder');
    if (!$groupRow) {
        $installer->addAttributeGroup($entityTypeId, $setId, 'Preorder', 100);
        $groupRow = $installer->getAttributeGroup($entityTypeId, $setId, 'Preorder');
    }
    $groupId = (int) $groupRow['attribute_group_id'];

    foreach ($attributeCodes as $code) {
        $attributeId = (int) $installer->getAttributeId($entityTypeId, $code);
        if (!$attributeId) {
            continue;
        }
        // addAttributeToSet moves the attribute if already assigned to a
        // different group on this set; harmless if already in target.
        $installer->addAttributeToSet(
            $entityTypeId,
            $setId,
            $groupId,
            $attributeId,
        );
    }
}

$installer->endSetup();
