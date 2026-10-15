<?php
require_once __DIR__ . '/php/layout.php';

if ($u = current_user()) {
    redirect(role_home($u['role']));
}

// Simple server-side maths CAPTCHA
$a = random_int(1, 9);
$b = random_int(1, 9);
$_SESSION['captcha'] = $a + $b;

page_start('Student Login');
?>
<div class="form-box">
<h2>Login to StudentHub</h2>
<?= flash_html() ?>
<form method="post" action="php/login_process.php" autocomplete="on">
<?= csrf_field() ?>
<label for="identifier">Email or Roll Number</label>
<input type="text" id="identifier" name="identifier" placeholder="Enter email or roll number" required maxlength="100">
<label for="password">Password</label>
<input type="password" id="password" name="password" placeholder="Enter Password" required>
<label for="captcha">CAPTCHA</label>
<div class="captcha-box"><span><?= $a ?> + <?= $b ?> = ?</span></div>
<input type="text" id="captcha" name="captcha" placeholder="Enter Answer" required inputmode="numeric" autocomplete="off">
<div class="checkbox-line">
<input type="checkbox" id="remember" name="remember" value="1">
<label for="remember" style="margin:0;">Remember me on this device (30 days)</label>
</div>
<input type="submit" value="Login">
<input type="reset" value="Reset">
</form>
<p style="font-size:14px;">New here? <a href="register.html">Create an account</a></p>
</div>
<?php page_end(); ?>
