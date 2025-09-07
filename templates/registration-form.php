<?php if (!empty($event_id)) : ?>
<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
  <input type="hidden" name="action" value="ae_register">
  <input type="hidden" name="event_id" value="<?php echo esc_attr($event_id); ?>">
  <?php wp_nonce_field('ae_register_nonce'); ?>

  <p>
    <label><?php _e('Name', 'arbe-events'); ?></label>
    <input type="text" name="name" required>
  </p>
  <p>
    <label><?php _e('Email', 'arbe-events'); ?></label>
    <input type="email" name="email" required>
  </p>
  <button type="submit"><?php _e('Register', 'arbe-events'); ?></button>
</form>
<?php endif; ?>