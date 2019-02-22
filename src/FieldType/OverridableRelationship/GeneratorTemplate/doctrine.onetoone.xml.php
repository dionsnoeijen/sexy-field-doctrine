<one-to-one field="<?php echo $toHandle; ?>" target-entity="<?php echo $toFullyQualifiedClassName; ?>"<?php
if ($type === 'bidirectional') {
    echo ' ' . ($owner ? 'inversed' : 'mapped') . '-by="' . $fromHandle . '"';
} ?>>
<?php if ($cascade) { ?>
    <cascade>
        <cascade-<?php echo $cascade; ?> />
    </cascade>
<?php } ?>
<?php if (!($type === 'bidirectional' && !$owner)) { ?>
    <join-column name="<?php echo $toHandle; ?>_id" referenced-column-name="id" />
<?php } ?>
</one-to-one>
