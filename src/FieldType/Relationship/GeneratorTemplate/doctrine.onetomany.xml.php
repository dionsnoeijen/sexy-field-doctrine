<one-to-many field="<?php echo $toPluralHandle; ?>" target-entity="<?php echo $toFullyQualifiedClassName; ?>" mapped-by="<?php echo $fromHandle; ?>"<?php if (!empty($fetch)) { echo " fetch=\"$fetch\""; } ?>>
<?php if ($cascade) { ?>
    <cascade>
        <cascade-<?php echo $cascade; ?> />
    </cascade>
<?php } ?>
</one-to-many>
