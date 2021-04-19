
<?php echo 'aabbba'; ?>
<h2>Example Plugin Setasdasdasdtings</h2>
<form action="options.php" method="post">
    <?php 
    settings_fields( 'erp_sync_tool_settings' );
    do_settings_sections( 'erp_sync_tool_settings' ); ?>
    <input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e( 'Save' ); ?>" />
</form>