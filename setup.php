<?php
// Jalankan SEKALI: http://localhost/absensi/setup.php  (lalu hapus file ini)
require 'config.php';
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
 $s=$p->prepare('INSERT INTO users(username,password,nama,role) VALUES(?,?,?,?)');
 foreach([['admin','admin123','Administrator','admin'],['magang1','123456','Budi Magang','magang'],['magang2','123456','Sari Magang','magang'],['satpam1','123456','Joko Satpam','satpam'],['satpam2','123456','Andi Satpam','satpam']] as $u)
  $s->execute([$u[0],password_hash($u[1],PASSWORD_DEFAULT),$u[2],$u[3]]);
}
echo '✅ Database & tabel siap. <a href="index.php">Ke halaman login</a> (hapus setup.php setelah ini)';
