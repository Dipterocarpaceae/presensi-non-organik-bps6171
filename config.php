<?php
// ===== KONFIGURASI =====

// --- Production: matikan tampilan error, aktifkan logging ---
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Asia/Pontianak'); // jam otomatis Pontianak (WIB)

// --- Production: keamanan session ---
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);        // hanya kirim cookie via HTTPS
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
session_start();

// --- Kredensial database: GANTI sesuai hosting Anda ---
const DB_HOST='localhost';                   // host MySQL dari provider hosting
const DB_NAME='absensi';                     // nama database di hosting
const DB_USER='absensi_user';                // GANTI: user MySQL khusus (jangan root!)
const DB_PASS='GANTI_PASSWORD_KUAT_ANDA';    // GANTI: password kuat minimal 16 karakter
const KANTOR=['nama'=>'BPS Kota Pontianak','lat'=>-0.04891361545564986,'lng'=>109.33787634012586]; // titik pusat presensi
// Nilai awal pengaturan. Admin dapat mengubahnya di halaman "Unduh Data Excel" (tersimpan di tabel `pengaturan`).
const PENGATURAN_DEFAULT=[
  'magang_masuk'=>'07:30',            // anak magang: lewat jam ini = terlambat masuk
  'magang_keluar_istirahat'=>'12:00', // sebelum jam ini = keluar istirahat lebih awal
  'magang_masuk_istirahat'=>'13:00',  // lewat jam ini = terlambat masuk istirahat
  'magang_pulang'=>'16:00',           // sebelum jam ini = pulang lebih awal
  'magang_sesi'=>'[]',               // anak magang: daftar sesi presensi [nama, jam, jenis] (JSON) — bisa ditambah / dihapus admin. jenis: batas (terlambat bila lewat jam) | min (lebih awal bila sebelum jam)
  'shifts'=>'[]',                    // satpam: daftar shift [nama, jam masuk, jam pulang] (JSON) — bisa ditambah / dihapus admin
  'radius'=>'120'];                   // meter: di luar radius = Di Luar Lokasi (wajib laporan izin)
const SHIFT_DEFAULT=[['Pagi','07:00','15:00'],['Sore','15:00','23:00'],['Malam','23:00','07:00']];   // dipakai bila admin belum mengatur shift
const SHIFT_MAKS=8;   // jumlah shift maksimal
const SESI_MAGANG_MAKS=10;                                                      // jumlah sesi presensi anak magang maksimal
const SHIFT_AWAL=120;                                                           // menit: satpam boleh absen sebelum jam mulai shift tanpa dianggap terlambat
const SESI_LAPORAN=['Laporan Keluar','Laporan Masuk'];                            // izin keluar kantor di jam kerja (anak magang): boleh berkali-kali. Keluar = isi alasan + lokasi (perlu persetujuan admin); Masuk = cukup tekan tombol (tanpa form)
const MAKS_HURUF=20;                                                            // SEMUA isian teks: maksimal 20 karakter, spasi tidak dihitung
const MAKS_ALASAN=200;                                                          // batas pengaman panjang mentah (dengan spasi) agar database aman

if(!function_exists('mb_strlen')){function mb_strlen($s){return preg_match_all('/./su',(string)$s);}}   // cadangan jika ekstensi mbstring tidak aktif
if(!function_exists('mb_substr')){function mb_substr($s,$a,$l=null){preg_match_all('/./su',(string)$s,$m);return implode('',array_slice($m[0],$a,$l));}}
function db(){static $p;if(!$p){$p=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);skemaBaru($p);}return $p;}
// Migrasi otomatis: tambah kolom jabatan (khusus anak magang) pada database lama
function skemaBaru($p){try{if(!$p->query("SHOW COLUMNS FROM users LIKE 'jabatan'")->fetch())$p->exec('ALTER TABLE users ADD jabatan VARCHAR(60) NULL AFTER role');}catch(Throwable $x){}
  try{$p->exec('CREATE TABLE IF NOT EXISTS pengaturan(kunci VARCHAR(40) PRIMARY KEY, nilai TEXT NOT NULL)');
    if(stripos((string)$p->query("SHOW COLUMNS FROM pengaturan LIKE 'nilai'")->fetch()['Type'],'varchar')!==false)$p->exec('ALTER TABLE pengaturan MODIFY nilai TEXT NOT NULL');}catch(Throwable $x){}}
function hurufAlasan($s){return mb_strlen(preg_replace('/\s+/u','',(string)$s));}   // jumlah karakter tanpa spasi
// Validasi isian teks: wajib (bila $wajib) dan maksimal MAKS_HURUF karakter tanpa spasi. Mengembalikan pesan galat atau ''.
function cekTeks($s,$label,$wajib=true){$n=hurufAlasan($s);
  if($wajib&&$n<1)return $label.' wajib diisi.';
  if($n>MAKS_HURUF)return $label.' maksimal '.MAKS_HURUF.' karakter (spasi tidak dihitung). Saat ini '.$n.' karakter.';
  if(mb_strlen((string)$s)>MAKS_ALASAN)return $label.' terlalu panjang.';return '';}
function jamOk($t){return(bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',(string)$t);}
function namaShiftOk($n){return(bool)preg_match('/^(?=.*[A-Za-z0-9])[A-Za-z0-9 ]{1,20}$/',(string)$n);}
function slugShift($n){return strtolower(preg_replace('/[^A-Za-z0-9]/','',(string)$n));}
function ikonShift($n){$n=strtolower($n);return str_contains($n,'pagi')?'🌅':(str_contains($n,'sore')?'🌇':(str_contains($n,'malam')?'🌙':'🕐'));}
// Saring daftar shift: [[nama,masuk,pulang],...] -> hanya yang valid & tidak kembar
function bersihShift($a){$o=[];$slug=[];if(!is_array($a))return $o;
  foreach($a as $x){if(!is_array($x)||count($x)<3)continue;[$n,$m,$p]=array_values($x);$n=trim(preg_replace('/\s+/',' ',(string)$n));
    if(!namaShiftOk($n)||!jamOk($m)||!jamOk($p)||$m===$p||isset($slug[slugShift($n)]))continue;$slug[slugShift($n)]=1;$o[]=[$n,$m,$p];if(count($o)>=SHIFT_MAKS)break;}
  return $o;}
// Saring daftar sesi presensi anak magang: [[nama,jam,jenis],...] -> hanya yang valid & tidak kembar, diurutkan menurut jam
function bersihSesiMagang($a){$o=[];$slug=[];if(!is_array($a))return $o;
  foreach($a as $x){if(!is_array($x)||count($x)<3)continue;[$n,$j,$t]=array_values($x);$n=trim(preg_replace('/\s+/',' ',(string)$n));
    if(!namaShiftOk($n)||!jamOk($j)||!in_array($t,['batas','min'],true)||preg_match('/^shift(\s|$)/i',$n)||isset($slug[slugShift($n)]))continue;   // nama berawalan "Shift" dipakai satpam
    if(slugShift($n)==='pulang'&&$t!=='min')continue;
    if(in_array(slugShift($n),['laporankeluar','laporanmasuk'],true))continue;   // nama dipakai kartu laporan   // "Pulang" selalu berjenis lebih awal (dipakai juga oleh satpam)
    $slug[slugShift($n)]=1;$o[]=[$n,$j,$t];if(count($o)>=SESI_MAGANG_MAKS)break;}
  usort($o,fn($a,$b)=>strcmp($a[1],$b[1]));return $o;}
// Baca pengaturan dari database (jatuh ke nilai awal bila belum ada / belum valid)
function pengaturan(){static $c;if($c!==null)return $c;$c=PENGATURAN_DEFAULT;$c['shifts']=SHIFT_DEFAULT;
  try{$raw=db()->query('SELECT kunci,nilai FROM pengaturan')->fetchAll(PDO::FETCH_KEY_PAIR);}catch(Throwable $x){$raw=[];}   // database / tabel belum ada (mis. saat setup.php) => nilai awal
  foreach($raw as $k=>$v){
    if($k==='radius'){if(ctype_digit((string)$v)&&(int)$v>=10&&(int)$v<=5000)$c[$k]=(string)$v;}
    elseif($k!=='shifts'&&$k!=='magang_sesi'&&isset(PENGATURAN_DEFAULT[$k])&&jamOk($v))$c[$k]=$v;}
  $sm=[];   // sesi presensi anak magang; bila belum diatur, pakai 4 sesi bawaan dengan jam dari pengaturan lama
  if(isset($raw['magang_sesi']))$sm=bersihSesiMagang(json_decode($raw['magang_sesi'],true));
  if(!$sm)$sm=bersihSesiMagang([['Masuk Pagi',$c['magang_masuk'],'batas'],['Keluar Istirahat',$c['magang_keluar_istirahat'],'min'],['Masuk Siang',$c['magang_masuk_istirahat'],'batas'],['Pulang',$c['magang_pulang'],'min']]);
  $c['magang_sesi']=$sm;
  $sf=[];
  if(isset($raw['shifts']))$sf=bersihShift(json_decode($raw['shifts'],true));
  elseif(isset($raw['shift_pagi_mulai'])||isset($raw['shift_sore_mulai'])||isset($raw['shift_malam_mulai'])){   // pengaturan versi lama (3 shift tetap)
    $sf=[];foreach(SHIFT_DEFAULT as [$n,$m,$p]){$k=strtolower($n);$sf[]=[$n,jamOk($raw["shift_{$k}_mulai"]??'')?$raw["shift_{$k}_mulai"]:$m,jamOk($raw["shift_{$k}_selesai"]??'')?$raw["shift_{$k}_selesai"]:$p];}
    $sf=bersihShift($sf);}
  if($sf)$c['shifts']=$sf;
  return $c;}
function simpanPengaturan($a){$s=db()->prepare('INSERT INTO pengaturan(kunci,nilai) VALUES(?,?) ON DUPLICATE KEY UPDATE nilai=VALUES(nilai)');
  foreach($a as $k=>$v)if(array_key_exists($k,PENGATURAN_DEFAULT))$s->execute([$k,(string)$v]);}
// Rentang jam shift untuk tampilan, mis. 07.00–15.00
function rentang($n){return str_replace(':','.',SHIFT[$n][0]).'–'.str_replace(':','.',SHIFT[$n][1]);}
// Shift yang melewati tengah malam (jam pulang < jam masuk): presensi dini hari tercatat di tanggal shift dimulai
function shiftLewatMalam($n){return isset(SHIFT[$n])&&SHIFT[$n][1]<SHIFT[$n][0];}
$P=pengaturan();
$ms=[];foreach($P['magang_sesi'] as [$n,$j,$t])$ms[$n]=[$j,$t];
define('MAGANG',$ms);                       // 'Masuk Pagi'=>[jam,'batas'|'min']  batas: lewat jam ini = terlambat | min: sebelum jam ini = lebih awal
define('SESI_MAGANG',array_keys($ms));      // sesi presensi anak magang (urut jam)
$sh=[];foreach($P['shifts'] as [$n,$m,$p])$sh['Shift '.$n]=[$m,$p,ikonShift($n)];   // 'Shift Pagi'=>[masuk,pulang,ikon]
define('SHIFT',$sh);
define('SESI_SATPAM',array_merge(array_keys($sh),['Pulang']));
define('SESI',array_merge(SESI_MAGANG,array_keys($sh),SESI_LAPORAN));          // semua sesi yang valid
define('RADIUS_M',(int)$P['radius']);
function jarakM($a,$b,$c,$d){$r=6371000;$p=M_PI/180;$x=sin(($c-$a)*$p/2)**2+cos($a*$p)*cos($c*$p)*sin(($d-$b)*$p/2)**2;return 2*$r*asin(sqrt($x));}
function luar($r){return(($r['jenis']??'')==='Hadir'&&!in_array($r['sesi']??'',SESI_LAPORAN,true)&&($r['lat']??'')!==''&&$r['lat']!==null&&jarakM((float)$r['lat'],(float)$r['lng'],KANTOR['lat'],KANTOR['lng'])>RADIUS_M);}
function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function auth($need){
  if(empty($_SESSION['u'])){header('Location: index.php');exit;}
  $q=db()->prepare('SELECT id,nama,username,role,jabatan FROM users WHERE id=?');$q->execute([(int)$_SESSION['u']['id']]);$f=$q->fetch();
  if(!$f){unset($_SESSION['u']);header('Location: index.php');exit;}            // akun sudah dihapus admin
  $_SESSION['u']=array_merge($_SESSION['u'],$f);                                  // nama / username / peran terbaru dari admin
  $r=$_SESSION['u']['role'];
  if(($need==='admin'&&$r!=='admin')||($need==='user'&&$r==='admin')){header('Location: index.php');exit;}
  return $_SESSION['u'];
}
// Tanda perubahan data (dipakai untuk memuat ulang halaman otomatis saat admin / user memperbarui data)
function sigData($uid=null){
  db()->exec('SET SESSION group_concat_max_len=4194304');
  $q=db()->prepare("SELECT MD5(COALESCE(GROUP_CONCAT(CONCAT_WS('|',id,user_id,tanggal,jam,jenis,IFNULL(sesi,''),approval,telat,IFNULL(lat,''),keterangan,izin_terlambat) ORDER BY id SEPARATOR ';'),'')) FROM absensi WHERE (?=0 OR user_id=?) AND tanggal>=DATE_SUB(CURDATE(),INTERVAL 70 DAY)");
  $q->execute([(int)$uid,(int)$uid]);return (string)$q->fetchColumn();}
// Jumlah keterlambatan per sesi pada satu bulan. Yang SUDAH DISETUJUI admin tidak dihitung terlambat.
function telatUser($uid,$bln,$hanyaDitolak=false){
  $q=db()->prepare("SELECT sesi,COUNT(*) n FROM absensi WHERE user_id=? AND jenis='Hadir' AND telat=1 AND approval<>'approved' AND tanggal BETWEEN ? AND ? GROUP BY sesi");
  $q->execute([$uid,$bln.'-01',date('Y-m-t',strtotime($bln.'-01'))]);return $q->fetchAll(PDO::FETCH_KEY_PAIR);}
// Label sesi untuk tampilan
function lblSesi($s){return['Masuk Pagi'=>'Jam Masuk','Keluar Istirahat'=>'Keluar Istirahat','Masuk Siang'=>'Masuk Istirahat','Pulang'=>'Jam Pulang'][$s]??(string)$s;}
function ikonSesi($s){return['Masuk Pagi'=>'🏢','Keluar Istirahat'=>'🚶','Masuk Siang'=>'🍱','Pulang'=>'🏠','Laporan Keluar'=>'🚪','Laporan Masuk'=>'↩️'][$s]??((isset(MAGANG[$s])&&MAGANG[$s][1]==='min')?'🏃':'🕐');}
// Label pelanggaran sesuai sesi
function lblTelat($sesi){
  if(isset(SHIFT[$sesi]))return 'Terlambat '.$sesi;
  $b=['Masuk Pagi'=>['batas','Terlambat Masuk'],'Masuk Siang'=>['batas','Terlambat Masuk Istirahat'],'Keluar Istirahat'=>['min','Keluar Awal'],'Pulang'=>['min','Pulang Awal']];
  if(isset(MAGANG[$sesi])){$t=MAGANG[$sesi][1];   // anak magang: sesi bawaan memakai istilah lama, sesi tambahan mengikuti namanya
    if(isset($b[$sesi])&&$b[$sesi][0]===$t)return $b[$sesi][1];
    return $t==='batas'?'Terlambat '.$sesi:$sesi.' Lebih Awal';}
  return isset($b[$sesi])?$b[$sesi][1]:'Terlambat';}
// Judul kolom rekap per sesi (jumlah keterlambatan / lebih awal)
function kolTelat($s){$m=['Terlambat Masuk'=>'Terlambat Jam Masuk','Keluar Awal'=>'Keluar Istirahat Awal'];$l=lblTelat($s);return $m[$l]??$l;}
function mnt($hm){return (int)substr($hm,0,2)*60+(int)substr($hm,3,2);}
// Aturan waktu sebuah sesi => [jenis, nilai]. $sh = shift yang diambil satpam (untuk Pulang)
function aturan($sesi,$sh=null){
  if(isset(SHIFT[$sesi]))return['shiftin',SHIFT[$sesi][0]];
  if($sesi==='Pulang'&&$sh&&isset(SHIFT[$sh]))return['shiftout',SHIFT[$sh][0].'|'.SHIFT[$sh][1]];   // mulai|selesai shift
  if(isset(MAGANG[$sesi]))return[MAGANG[$sesi][1],MAGANG[$sesi][0]];   // anak magang: [jenis, jam]
  return['min','00:00'];}
function telatKind($k,$v,$hm){
  if($k==='batas')return $hm>$v; if($k==='min')return $hm<$v;
  // shiftin : absen di rentang jam shift (setelah jam mulai) = terlambat. Boleh datang maks. SHIFT_AWAL menit sebelum mulai.
  if($k==='shiftin'){$d=(mnt($hm)-mnt($v)+1440)%1440;return $d>0&&$d<1440-SHIFT_AWAL;}
  // shiftout: pulang di rentang jam shift (sebelum shift selesai) = pulang awal; pulang sebelum shift mulai juga awal.
  [$mulai,$selesai]=explode('|',$v);$dur=(mnt($selesai)-mnt($mulai)+1440)%1440?:1440;   // lama shift (menit), bisa melewati tengah malam
  $d=(mnt($hm)-mnt($mulai)+1440)%1440;return $d<$dur||$d>=1440-SHIFT_AWAL;}
// Hitung flag telat untuk data tersimpan (dipakai admin saat edit/tambah manual)
function hitungTelat($uid,$role,$sesi,$tgl,$jam){
  $sh=null;
  if($role==='satpam'&&$sesi==='Pulang'){$q=db()->prepare("SELECT sesi FROM absensi WHERE user_id=? AND tanggal=? AND sesi LIKE 'Shift %' ORDER BY jam LIMIT 1");$q->execute([$uid,$tgl]);$sh=$q->fetchColumn();if(!$sh)return 0;}
  [$k,$v]=aturan($sesi,$sh);return telatKind($k,$v,substr($jam,0,5))?1:0;}
// Tanggal kerja: satpam shift malam yang pulang dini hari masih tercatat di tanggal shift dimulai
function tglKerja($u){
  if(($u['role']??'')==='satpam'){$y=date('Y-m-d',strtotime('-1 day'));$now=date('H:i');
    foreach(SHIFT as $n=>$v)if($v[1]<$v[0]&&$now<$v[1]){   // dini hari pada shift yang melewati tengah malam
      $q=db()->prepare("SELECT COUNT(*) FROM absensi WHERE user_id=? AND tanggal=? AND sesi=?");$q->execute([$u['id'],$y,$n]);if($q->fetchColumn())return $y;}}
  return date('Y-m-d');}
// Status efektif: izin ditolak => Tidak Hadir, terlambat tanpa approval => Terlambat
function status($r){
  if($r['jenis']==='Tidak Hadir')return['Tidak Hadir','no','❌'];
  if($r['jenis']==='Izin'){
    if($r['approval']==='approved')return['Izin '.$r['jenis_izin'].' Disetujui','ok','✅'];
    if($r['approval']==='rejected')return['Tidak Hadir (Izin Ditolak)','no','❌'];
    return['Izin '.$r['jenis_izin'].' Menunggu Approval','wa','⏳'];
  }
  $lu=luar($r);
  if(!$r['telat']&&!$lu)return['Hadir Tepat Waktu','ok','✅'];
  $l=$r['telat']?lblTelat($r['sesi']):'';if($lu)$l=$l?$l.' + Di Luar Lokasi':'Di Luar Lokasi';
  if($r['approval']==='approved')return[$l.' (Izin Disetujui)','ok','✅'];
  if($r['approval']==='rejected')return['Tertolak ('.$l.')','no','🚫'];
  return[$r['approval']==='pending'?$l.' (Menunggu Approval)':$l,'no','⏰'];
}
// Status satu hari dari semua presensi hari itu
function harian($rs){
  $rs=array_values(array_filter($rs,fn($r)=>!in_array($r['sesi']??'',SESI_LAPORAN,true)));   // laporan keluar / masuk tidak mempengaruhi status kehadiran harian
  $hd=false;foreach($rs as $r)if($r['jenis']==='Hadir')$hd=true;
  foreach($rs as $r)if($r['jenis']==='Izin'&&!($r['sesi']&&$hd))return status($r);   // ijin 1 shift tapi masuk shift lain = tetap Hadir
  foreach($rs as $r)if($r['jenis']==='Tidak Hadir')return status($r);
  if(!$rs)return['Belum presensi','mu','➖'];
  foreach($rs as $r)if($r['jenis']==='Hadir'&&($r['telat']||luar($r))&&status($r)[1]==='no')return status($r);
  return['Hadir','ok','✅'];
}
function kategori($h){[$t,$c]=$h;
  if($t==='Belum presensi')return'belum'; if(str_starts_with($t,'Tidak'))return'tidak';
  if(str_starts_with($t,'Izin'))return'izin'; if($c==='no'&&str_contains($t,'Di Luar Lokasi'))return'luar'; if((str_contains($t,'Terlambat')||str_contains($t,'Pulang Awal')||str_contains($t,'Keluar Awal')||str_contains($t,'Lebih Awal'))&&$c==='no')return'telat'; return'hadir';}

// ===== Rekap bulanan =====
function bulanOk($b){return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',(string)$b)?$b:date('Y-m');}
function namaBulan($ym){$n=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];return $n[(int)substr($ym,5,2)-1].' '.substr($ym,0,4);}
function tglId($d,$full=false){$n=substr(namaBulan(substr($d,0,7)),0,-5);return date('d',strtotime($d)).' '.($full?$n:mb_substr($n,0,3)).' '.date('Y',strtotime($d));}
function geserBulan($ym,$d){return date('Y-m',strtotime($ym.'-01 '.($d>0?'+':'').$d.' month'));}
// Hadir/Izin/Tidak Hadir dihitung per HARI; Terlambat Pagi/Siang dihitung per kejadian (yang tidak disetujui admin)
function rekapBulan($ym,$role=null,$uid=null){
  $w="role<>'admin'";$p=[];
  if($role){$w.=' AND role=?';$p[]=$role;} if($uid){$w.=' AND id=?';$p[]=(int)$uid;}
  $s=db()->prepare("SELECT id,nama,username,role,jabatan FROM users WHERE $w ORDER BY FIELD(role,'satpam','magang'),nama");$s->execute($p);
  $o=[];foreach($s->fetchAll() as $u)$o[$u['id']]=$u+['hadir'=>0,'izin'=>0,'tidak'=>0,'tp'=>0,'lu'=>0,'per'=>[]];   // tp=terlambat masuk (shift satpam / sesi magang berjenis batas), lu=di luar lokasi, per[sesi]=jumlah terlambat / lebih awal pada tiap sesi
  if(!$o)return $o;
  $in=implode(',',array_map('intval',array_keys($o)));
  $a=db()->prepare("SELECT * FROM absensi WHERE user_id IN($in) AND tanggal BETWEEN ? AND ? ORDER BY jam");
  $a->execute([$ym.'-01',date('Y-m-t',strtotime($ym.'-01'))]);
  $g=[];foreach($a->fetchAll() as $r)$g[$r['user_id']][$r['tanggal']][]=$r;
  foreach($g as $id=>$hari)foreach($hari as $rs){
    $k=kategori(harian($rs));
    if($k==='hadir'||$k==='telat'||$k==='luar')$o[$id]['hadir']++;elseif($k==='izin')$o[$id]['izin']++;elseif($k==='tidak')$o[$id]['tidak']++;
    foreach($rs as $r)if($r['jenis']==='Hadir'&&$r['approval']!=='approved'){
      if($r['telat']){$sk=(string)$r['sesi'];$o[$id]['per'][$sk]=($o[$id]['per'][$sk]??0)+1;
        if(isset(SHIFT[$sk])||(isset(MAGANG[$sk])&&MAGANG[$sk][1]==='batas'))$o[$id]['tp']++;}
      if(luar($r))$o[$id]['lu']++;}
  }
  return $o;
}
// Jumlahkan hasil rekapBulan() beberapa orang (termasuk hitungan per sesi)
function totalRekap($rb){$t=['hadir'=>0,'izin'=>0,'tidak'=>0,'tp'=>0,'lu'=>0,'per'=>[]];
  foreach($rb as $x){foreach(['hadir','izin','tidak','tp','lu'] as $k)$t[$k]+=$x[$k];foreach($x['per'] as $sk=>$n)$t['per'][$sk]=($t['per'][$sk]??0)+$n;}
  return $t;}
function kotakBulan($s,$role=null){
  $k=[['✅',$s['hadir'],'Hadir'],['📝',$s['izin'],'Izin'],['❌',$s['tidak'],'Tidak Hadir']];
  if($role==='satpam')$k[]=['⏰',$s['tp'],'Terlambat Masuk'];
  else foreach(MAGANG as $n=>[$j,$t])$k[]=[$t==='batas'?'⏰':'🏃',(int)($s['per'][$n]??0),kolTelat($n)];   // satu kotak per sesi anak magang
  $k[]=['📍',$s['lu'],'Di Luar Lokasi'];
  echo '<div class="stats">';foreach($k as [$i,$n,$l])echo "<div><i>$i</i><b>$n</b>$l</div>";echo '</div>';}
function navBulan($bln,$extra=[]){
  $L=fn($b)=>e('?'.http_build_query(array_merge($extra,['bln'=>$b])));
  $h='<div class="datebar"><a class="btn g sm" href="'.$L(geserBulan($bln,-1)).'">◀</a><form method="get" class="row">';
  foreach($extra as $k=>$v)$h.='<input type="hidden" name="'.e($k).'" value="'.e($v).'">';
  return $h.'<input type="month" name="bln" value="'.e($bln).'" style="width:auto" onchange="this.form.submit()"></form><a class="btn g sm" href="'.$L(geserBulan($bln,1)).'">▶</a><a class="btn sm" href="'.$L(date('Y-m')).'">Bulan ini</a></div>';
}

function head($t,$u=null,$back=null){
  $adm=$u&&$u['role']==='admin';
  echo '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>'.e($t).'</title><script>try{var _t=localStorage.getItem("tema");if(_t!=="dark"&&_t!=="light")_t=matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light";document.documentElement.dataset.theme=_t}catch(e){}</script><link rel="stylesheet" href="assets/style.css?v='.@filemtime(__DIR__.'/assets/style.css').'"></head><body class="r-'.e($u['role']??'tamu').'"><div class="w'.($adm?' wide':'').'">';
  if($u){$av=['admin'=>'🛡️','satpam'=>'💂','magang'=>'🎓'][$u['role']];
    echo '<div class="top"><div class="row nowrap"><div class="av">'.$av.'</div><div><h1>Halo, '.e(explode(' ',$u['nama'])[0]).'! 👋</h1><span class="mu">'.['admin'=>'Administrator','satpam'=>'Satpam','magang'=>'Anak Magang'][$u['role']].(!empty($u['jabatan'])&&$u['role']==='magang'?' · '.e($u['jabatan']):'').'</span></div></div><div class="row nowrap"><button type="button" class="g sm tema" data-tema aria-label="Ganti tema terang / gelap" title="Ganti tema terang / gelap"><span class="ti">🌙</span><span class="tt">Gelap</span></button><a class="btn g sm" href="logout.php">Keluar</a></div></div>';}
  else echo '<div class="tema-float"><button type="button" class="g sm tema" data-tema aria-label="Ganti tema terang / gelap" title="Ganti tema terang / gelap"><span class="ti">🌙</span><span class="tt">Gelap</span></button></div>';
  if($back)echo '<a class="back" href="'.e($back).'">← Kembali</a>';
  if(!empty($_SESSION['ok'])){echo '<div class="toast">'.e($_SESSION['ok']).'</div>';unset($_SESSION['ok']);}
}
function foot(){echo '</div>'.(!empty($GLOBALS['LIVE'])?'<i id="live" data-sig="'.e($GLOBALS['LIVE']).'" hidden></i>':'').'<script src="assets/app.js?v='.@filemtime(__DIR__.'/assets/app.js').'"></script></body></html>';}   // ?v= agar browser selalu memuat app.js terbaru

// ===== Komponen tampilan bersama (user & admin) =====
function chipAp($ap){$m=['pending'=>['Menunggu','wa','⏳'],'approved'=>['Disetujui','ok','✅'],'rejected'=>['Ditolak','no','❌']];if(!isset($m[$ap]))return '';[$t,$c,$i]=$m[$ap];return '<span class="b '.$c.'">'.$i.' '.$t.'</span>';}
function labelPengajuan($r){
  if($r['jenis']==='Izin')return 'Ijin '.$r['jenis_izin'].(!empty($r['sesi'])?' ('.$r['sesi'].')':'');
  if(in_array($r['sesi'],SESI_LAPORAN,true))return $r['sesi'].' '.substr($r['jam'],0,5);   // laporan bisa berkali-kali: bedakan dengan jam
  $l=[];if($r['telat'])$l[]=lblTelat($r['sesi']);if(luar($r))$l[]='Di Luar Lokasi';
  return ($l?implode(' + ',$l):lblSesi($r['sesi']));}
function linkPeta($r){return $r['lat']?' <a class="act" target="_blank" title="Lihat lokasi" href="https://maps.google.com/?q='.e($r['lat']).','.e($r['lng']).'">📍</a>':'';}
// Satu sel jam pada tabel riwayat (satu sesi pada satu hari)
function selJam($rs,$sesi,$adm=false){
  foreach($rs as $r)if($r['jenis']==='Hadir'&&$r['sesi']===$sesi){
    $c=status($r)[1];$h='<b class="'.$c.'">'.substr($r['jam'],0,5).'</b>'.linkPeta($r);
    if($r['approval']!=='approved'&&($r['telat']||luar($r)))$h.=' <span class="mu">'.($r['telat']?($sesi==='Pulang'||(isset(MAGANG[$sesi])&&MAGANG[$sesi][1]==='min')?'awal':'telat'):'').($r['telat']&&luar($r)?' · ':'').(luar($r)?'luar':'').'</span>';
    if($adm)$h.=' <a class="act" title="Edit" href="?page=edit&id='.(int)$r['id'].'">✏️</a>';
    return $h;}
  return '<span class="mu">–</span>';}
function selShift($rs,$adm=false){
  foreach($rs as $r)if($r['jenis']==='Hadir'&&isset(SHIFT[$r['sesi']])){
    $c=status($r)[1];$h='<b class="'.$c.'">'.substr($r['jam'],0,5).'</b> <span class="mu">'.SHIFT[$r['sesi']][2].' '.e(substr($r['sesi'],6)).'</span>'.linkPeta($r);
    if($adm)$h.=' <a class="act" title="Edit" href="?page=edit&id='.(int)$r['id'].'">✏️</a>';
    return $h;}
  return '<span class="mu">–</span>';}
// Daftar laporan keluar / masuk (di luar jam istirahat) pada satu hari — bisa lebih dari satu
function selLaporan($rs,$adm=false){$o='';
  foreach($rs as $r)if($r['jenis']==='Hadir'&&in_array($r['sesi'],SESI_LAPORAN,true))
    $o.='<div class="pj"><b>'.($r['sesi']==='Laporan Keluar'?'🚪 Keluar ':'↩️ Masuk ').substr($r['jam'],0,5).'</b>'.linkPeta($r).($adm?' <a class="act" title="Edit" href="?page=edit&id='.(int)$r['id'].'">✏️</a>':'').'</div>';
  return $o?:'<span class="mu">–</span>';}
// Ringkasan pengajuan + hasilnya (disetujui / ditolak / menunggu)
function selPengajuan($rs){
  $o='';foreach($rs as $r){if($r['approval']==='none')continue;
    $o.='<div class="pj"><b>'.e(labelPengajuan($r)).'</b> '.chipAp($r['approval']).'<div class="mu">'.e($r['jenis']==='Izin'?$r['keterangan']:$r['izin_terlambat']).'</div></div>';}
  return $o?:'<span class="mu">–</span>';}
// Tabel riwayat: 1 baris = 1 hari
function riwayatHarian($rows,$role,$adm=false){
  if(!$rows){echo '<div class="empty"><div class="mas">📭</div><p class="mu">Belum ada riwayat pada bulan ini.</p></div>';return;}
  $g=[];foreach($rows as $r)$g[$r['tanggal']][]=$r;krsort($g);$sat=$role==='satpam';
  echo '<div class="tw"><table class="rw'.($adm&&$role!=='magang'?' stk':'').'"><tr><th>Tanggal</th><th>Status</th>'.($sat?'<th>Jam Masuk (Shift)</th><th>Jam Pulang</th>':implode('',array_map(fn($z)=>'<th>'.e(lblSesi($z)).'</th>',SESI_MAGANG)).'<th>Laporan Keluar / Masuk</th>').'<th>Hasil Pengajuan</th></tr>';
  foreach($g as $tg=>$rs){usort($rs,fn($a,$b)=>strcmp($a['jam'],$b['jam']));[$st,$c,$i]=harian($rs);
    $izn=array_values(array_filter($rs,fn($r)=>$r['jenis']!=='Hadir'));
    echo '<tr><td data-l="Tanggal"><b>'.tglId($tg).'</b></td><td data-l="Status"><span class="b '.$c.'">'.$i.' '.e($st).'</span>'.($adm&&$izn?' <a class="act" href="?page=edit&id='.(int)$izn[0]['id'].'">✏️</a>':'').'</td>';
    if($sat)echo '<td data-l="Jam Masuk">'.selShift($rs,$adm).'</td><td data-l="Jam Pulang">'.selJam($rs,'Pulang',$adm).'</td>';
    else{foreach(SESI_MAGANG as $z)echo '<td data-l="'.e(lblSesi($z)).'">'.selJam($rs,$z,$adm).'</td>';echo '<td data-l="Laporan">'.selLaporan($rs,$adm).'</td>';}
    echo '<td data-l="Pengajuan">'.selPengajuan($rs).'</td></tr>';}
  echo '</table></div>';}
function navTanggal($tg,$kunci,$extra=[]){   // navigasi per hari
  $L=fn($d)=>e('?'.http_build_query(array_merge($extra,[$kunci=>$d])));
  $h='<div class="datebar"><a class="btn g sm" href="'.$L(date('Y-m-d',strtotime("$tg -1 day"))).'">◀</a><form method="get" class="row">';
  foreach($extra as $k=>$v)$h.='<input type="hidden" name="'.e($k).'" value="'.e($v).'">';
  return $h.'<input type="date" name="'.e($kunci).'" value="'.e($tg).'" max="'.date('Y-m-d').'" onchange="this.form.submit()"></form><a class="btn g sm" href="'.$L(date('Y-m-d',strtotime("$tg +1 day"))).'">▶</a><a class="btn sm" href="'.$L(date('Y-m-d')).'">Hari ini</a></div>';}
function tglOk($t,$def=null){return preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)$t)&&strtotime($t)?$t:($def??date('Y-m-d'));}
// ===== Keamanan form (CSRF) =====
function csrf(){if(empty($_SESSION['t']))$_SESSION['t']=bin2hex(random_bytes(16));return '<input type="hidden" name="_t" value="'.$_SESSION['t'].'">';}
function cek(){if(($_POST['_t']??'')!==($_SESSION['t']??'x')){http_response_code(400);exit('Token tidak valid. Muat ulang halaman lalu coba lagi.');}}
