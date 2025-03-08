<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <form action="options.php" method="post">
    <?php
        settings_fields('ovsi_options');
        do_settings_sections('ovsi_settings');
        submit_button('Save Settings');
    ?>
    </form>
</div>