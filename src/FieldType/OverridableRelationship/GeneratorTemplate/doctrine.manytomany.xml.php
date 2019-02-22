<many-to-many field="<?php echo $toPluralHandle; ?>" target-entity="<?php echo $toFullyQualifiedClassName; ?>"<?php
    if ($type === 'bidirectional') {
        echo ' ' . ($owner ? 'inversed' : 'mapped') . '-by="' . $fromPluralHandle . '"';
    }?>>
<?php if ($cascade) { ?>
    <cascade>
        <cascade-<?php echo $cascade; ?> />
    </cascade>
<?php } ?>
<?php if (!($type === 'bidirectional' && !$owner)) { ?>
    <join-table name="<?php echo $fromPluralHandle . '_' . $toPluralHandle; ?>">
        <join-columns>
            <join-column name="<?php echo $fromHandle; ?>_id" referenced-column-name="id" />
        </join-columns>
        <inverse-join-columns>
            <join-column name="<?php echo $toHandle; ?>_id" referenced-column-name="id" />
        </inverse-join-columns>
    </join-table>
<?php } ?>
</many-to-many>
