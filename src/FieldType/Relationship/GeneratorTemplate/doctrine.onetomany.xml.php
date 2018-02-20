<one-to-many field="<?php echo $toPluralHandle; ?>" target-entity="<?php echo $toFullyQualifiedClassName; ?>" mapped-by="<?php echo $fromHandle; ?>">
    <cascade>
        <cascade-all/>
    </cascade>
</one-to-many>
