<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php bloginfo('name'); ?></title>
    <link rel="stylesheet" href="<?php bloginfo('stylesheet_url'); ?>">
    <?php wp_head(); ?>
</head>
<body>
    <header>
        <h1><?php bloginfo('name'); ?></h1>
        <nav>
            <ul>
                <?php if (is_user_logged_in()): ?>
                    <li><a href="#" class="btn" id="create-group-btn">Create Group</a></li>
                    <li><a href="<?php echo site_url('/my-groups'); ?>" class="btn">My Groups</a></li>
                    <li><a href="<?php echo wp_logout_url(); ?>" class="btn">Logout</a></li>
                <?php else: ?>
                    <li><a href="<?php echo site_url('/login'); ?>" class="btn">Login</a></li>
                    <li><a href="<?php echo site_url('/register'); ?>" class="btn">Register</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <div id="create-group-form" style="display: none;">
        <?php echo gem_create_group_shortcode(); ?>
    </div>

    <script>
        document.getElementById('create-group-btn').addEventListener('click', function() {
            document.getElementById('create-group-form').style.display = 'block';
        });
    </script>
