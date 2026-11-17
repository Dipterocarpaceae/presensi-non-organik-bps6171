<?php require 'config.php';
if(!empty($_SESSION['u'])){header('Location: '.($_SESSION['u']['role']==='admin'?'admin.php':'user.php'));exit;}
$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $s=db()->prepare('SELECT * FROM users WHERE username=?');$s->execute([trim($_POST['username']??'')]);$u=$s->fetch();
  if($u&&password_verify($_POST['password']??'',$u['password'])){unset($u['password']);session_regenerate_id(true);$_SESSION['u']=$u;header('Location: index.php');exit;}
  $err='Username atau password salah';
}
head('Login Absensi'); ?>
<div class="card login"><div class="mas">📋</div><h1 class="c">Absensi</h1><p class="mu c">Masuk dulu yuk!</p>
<form method="post"><label>Username</label><input name="username" required autofocus><label>Password</label><input type="password" name="password" required>
<?php if($err)echo '<p class="err">'.e($err).'</p>'; ?><button class="big">Masuk</button></form></div>
<?php foot();
