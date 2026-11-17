<?php
// Jalankan SEKALI di server: https://domain.com/setup.php
// ⚠️  HAPUS FILE INI SEGERA SETELAH SELESAI!
require 'config.php';

// Cegah akses berulang: jika tabel users sudah ada dan berisi data, tolak
try {
  $cek = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  if((int)$cek->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0){
    echo '⚠️ Database sudah terisi. Setup tidak perlu dijalankan lagi. <b>Hapus file setup.php dari server!</b>';exit;}
} catch(Throwable $e) { /* tabel belum ada, lanjut setup */ }

$p=new PDO('mysql:host='.DB_HOST.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$p->exec('CREATE DATABASE IF NOT EXISTS `'.DB_NAME.'` CHARACTER SET utf8mb4');
$p->exec('USE `'.DB_NAME.'`');
$p->exec("CREATE TABLE IF NOT EXISTS users(
 id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) UNIQUE NOT NULL, password VARCHAR(255) NOT NULL,
 nama VARCHAR(100) NOT NULL, role ENUM('admin','magang','satpam') NOT NULL, jabatan VARCHAR(60) NULL)");
$p->exec("CREATE TABLE IF NOT EXISTS absensi(
 id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, tanggal DATE NOT NULL, jam TIME NOT NULL,
 jenis ENUM('Hadir','Tidak Hadir','Izin') NOT NULL, sesi VARCHAR(30) NULL, jenis_izin VARCHAR(20) NULL,
 keterangan TEXT NULL, izin_terlambat TEXT NULL, lat DECIMAL(10,7) NULL, lng DECIMAL(10,7) NULL,
 telat TINYINT(1) NOT NULL DEFAULT 0, approval ENUM('none','pending','approved','rejected') NOT NULL DEFAULT 'none',
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE, INDEX(tanggal))");
$p->exec('CREATE TABLE IF NOT EXISTS pengaturan(kunci VARCHAR(40) PRIMARY KEY, nilai TEXT NOT NULL)');   // jadwal & radius presensi (diatur admin)
if(!$p->query('SELECT COUNT(*) FROM users')->fetchColumn()){
 // ⚠️  Hanya 1 akun admin yang dibuat. Tambah pengguna lain melalui halaman admin.
 // ⚠️  SEGERA ganti password ini setelah login pertama!
 $adminPass = bin2hex(random_bytes(6)); // password acak 12 karakter
 $s=$p->prepare('INSERT INTO users(username,password,nama,role) VALUES(?,?,?,?)');
 $s->execute(['admin',password_hash($adminPass,PASSWORD_DEFAULT),'Administrator','admin']);
}
echo '✅ Database & tabel siap. <a href="index.php">Ke halaman login</a><br><br>';
if(isset($adminPass))echo '🔑 <b>Akun admin:</b> username = <code>admin</code>, password = <code>'.htmlspecialchars($adminPass).'</code><br><b>Catat password ini! Tidak bisa dilihat lagi.</b><br><br>';
echo '⚠️ <b style="color:red">HAPUS FILE setup.php DARI SERVER SEKARANG!</b>';
