<many-to-one field="<?php echo $toHandle; ?>" target-entity="<?php echo $toFullyQualifiedClassName; ?>"<?php
if ($type === 'bidirectional') {
    echo " inversed-by=\"$fromPluralHandle\"";
} ?>>
<?php if ($cascade) { ?>
    <cascade>
        <cascade-<?php echo $cascade; ?> />
    </cascade>
<?php } ?>
    <join-column name="<?php echo $toHandle; ?>_id" referenced-column-name="id" />
</many-to-one>
