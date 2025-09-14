<?php if (!empty($event_id)) : ?>
<form class="ae-registration-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <input type="hidden" name="action" value="ae_register">
    <input type="hidden" name="event_id" value="<?php echo esc_attr($event_id); ?>">
    <?php wp_nonce_field('ae_register_nonce'); ?>

    <div class="ae-form-field">
        <label for="ae-name"><?php _e('Name', 'arbe-events'); ?></label>
        <input type="text" id="ae-name" name="name" required>
    </div>
    <div class="ae-form-field">
        <label for="ae-email"><?php _e('Email', 'arbe-events'); ?></label>
        <input type="email" id="ae-email" name="email" required>
    </div>
    <button type="submit" class="ae-submit-button"><?php _e('Register', 'arbe-events'); ?></button>
</form>
<?php endif; ?>