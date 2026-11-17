<?php require 'config.php'; $u=auth('admin');
function go($m,$to){$_SESSION['ok']=$m;header('Location: '.$to);exit;}
function opt($v,$t,$cur){return '<option value="'.e($v).'"'.((string)$v===(string)$cur?' selected':'').'>'.$t.'</option>';}
$RM=['magang'=>['🎓','Anak Magang','#3978d4'],'satpam'=>['💂','Satpam','#7c5cd6']];

// ---------- AKSI (POST) ----------
if($_SERVER['REQUEST_METHOD']==='POST'){
 cek();$a=$_POST['aksi']??'';
 try{
  if(in_array($a,['approved','rejected'])){
    db()->prepare("UPDATE absensi SET approval=? WHERE id=? AND approval='pending'")->execute([$a,(int)$_POST['id']]);
    go($a==='approved'?' Pengajuan disetujui':' Pengajuan ditolak',$_SERVER['REQUEST_URI']);}
  if($a==='magang_save'||$a==='magang_add'){   // simpan jam semua sesi / hapus satu sesi / tambah sesi baru (anak magang)
    $daftar=pengaturan()['magang_sesi'];$hapus=$_POST['hapus']??null;
    if($a==='magang_add'){
      $n=trim(preg_replace('/\s+/',' ',$_POST['nama']??''));$j=substr(trim($_POST['jam']??''),0,5);$t=$_POST['tipe']??'';
      if(!namaShiftOk($n))go('❌ Nama sesi 1–20 karakter (huruf, angka, spasi)','?page=pengaturan');
      if(preg_match('/^shift(\s|$)/i',$n))go('❌ Nama sesi tidak boleh diawali "Shift" (dipakai jadwal satpam)','?page=pengaturan');
      if(!jamOk($j))go('❌ Format jam tidak valid','?page=pengaturan');
      if(!in_array($t,['batas','min'],true))go('❌ Pilih aturan: terlambat atau lebih awal','?page=pengaturan');
      if(slugShift($n)==='pulang'&&$t!=='min')go('❌ Sesi "Pulang" harus berjenis "lebih awal bila sebelum jam ini"','?page=pengaturan');
      foreach($daftar as $d)if(slugShift($d[0])===slugShift($n))go('❌ Sesi "'.$n.'" sudah ada','?page=pengaturan');
      if(count($daftar)>=SESI_MAGANG_MAKS)go('❌ Maksimal '.SESI_MAGANG_MAKS.' sesi presensi','?page=pengaturan');
      $daftar[]=[$n,$j,$t];$msg='✅ Sesi '.$n.' ditambahkan';
    }elseif($hapus!==null){
      $idx=array_search($hapus,array_column($daftar,0),true);
      if($idx===false)go('❌ Sesi tidak ditemukan','?page=pengaturan');
      if(count($daftar)<=1)go('❌ Minimal harus ada 1 sesi presensi','?page=pengaturan');
      array_splice($daftar,$idx,1);$msg='🗑️ Sesi '.$hapus.' dihapus';   // catatan presensi lama tidak ikut dihapus
    }else{
      foreach($daftar as $i2=>$d){$j=substr(trim($_POST['jam_'.slugShift($d[0])]??''),0,5);
        if(!jamOk($j))go('❌ Format jam tidak valid','?page=pengaturan');
        $daftar[$i2]=[$d[0],$j,$d[2]];}
      $msg='✅ Jadwal presensi anak magang disimpan';}
    simpanPengaturan(['magang_sesi'=>json_encode(array_values(bersihSesiMagang($daftar)),JSON_UNESCAPED_UNICODE)]);go($msg,'?page=pengaturan');}
  if($a==='shift_save'||$a==='shift_add'){   // simpan jam semua shift / hapus satu shift / tambah shift baru
    $daftar=pengaturan()['shifts'];$hapus=$_POST['hapus']??null;
    if($a==='shift_add'){
      $n=trim(preg_replace('/\s+/',' ',$_POST['nama']??''));$m=substr(trim($_POST['masuk']??''),0,5);$p2=substr(trim($_POST['pulang']??''),0,5);
      if(!namaShiftOk($n))go('❌ Nama shift 1–20 karakter (huruf, angka, spasi)','?page=pengaturan');
      if(!jamOk($m)||!jamOk($p2))go('❌ Format jam tidak valid','?page=pengaturan');
      if($m===$p2)go('❌ Jam masuk dan jam pulang tidak boleh sama','?page=pengaturan');
      foreach($daftar as $d)if(slugShift($d[0])===slugShift($n))go('❌ Shift "'.$n.'" sudah ada','?page=pengaturan');
      if(count($daftar)>=SHIFT_MAKS)go('❌ Maksimal '.SHIFT_MAKS.' shift','?page=pengaturan');
      $daftar[]=[$n,$m,$p2];$msg='✅ Shift '.$n.' ditambahkan';
    }elseif($hapus!==null){
      $idx=array_search($hapus,array_column($daftar,0),true);
      if($idx===false)go('❌ Shift tidak ditemukan','?page=pengaturan');
      if(count($daftar)<=1)go('❌ Minimal harus ada 1 shift','?page=pengaturan');
      $q=db()->prepare('SELECT COUNT(*) FROM absensi WHERE sesi=?');$q->execute(['Shift '.$hapus]);$pakai=(int)$q->fetchColumn();
      if($pakai)go('❌ Shift '.$hapus.' tidak bisa dihapus: sudah dipakai di '.$pakai.' catatan presensi (data lama akan rusak)','?page=pengaturan');
      array_splice($daftar,$idx,1);$msg='🗑️ Shift '.$hapus.' dihapus';
    }else{
      foreach($daftar as $i2=>$d){$k2=slugShift($d[0]);$m=substr(trim($_POST['masuk_'.$k2]??''),0,5);$p2=substr(trim($_POST['pulang_'.$k2]??''),0,5);
        if(!jamOk($m)||!jamOk($p2))go('❌ Format jam tidak valid','?page=pengaturan');
        if($m===$p2)go('❌ Jam masuk dan jam pulang shift '.$d[0].' tidak boleh sama','?page=pengaturan');
        $daftar[$i2]=[$d[0],$m,$p2];}
      $msg='✅ Jadwal shift satpam disimpan';}
    simpanPengaturan(['shifts'=>json_encode($daftar,JSON_UNESCAPED_UNICODE)]);go($msg,'?page=pengaturan');}
  if($a==='set_radius'){
    $rad=trim($_POST['radius']??'');
    if(!ctype_digit($rad)||(int)$rad<10||(int)$rad>5000)go('❌ Radius harus berupa angka 10 – 5000 meter','?page=pengaturan');
    simpanPengaturan(['radius'=>(int)$rad]);go('✅ Radius absensi disimpan: '.(int)$rad.' m','?page=pengaturan');}
  if($a==='user_add'||$a==='user_save'){
    $id=(int)($_POST['id']??0);$nama=trim($_POST['nama']??'');$un=trim($_POST['username']??'');$pw=$_POST['password']??'';$role=$_POST['role']??'';$jab=trim($_POST['jabatan']??'');$jab=$role==='magang'&&$jab!==''?mb_substr($jab,0,60):null;   // jabatan hanya untuk anak magang
    $to=$a==='user_add'?'?page=pengguna':'?page=uedit&id='.$id;
    if($nama===''||$un===''||!in_array($role,['admin','magang','satpam']))go('❌ Nama, username, dan peran wajib diisi',$to);
    if($m=cekTeks($nama,'Nama'))go('❌ '.$m,$to);
    if($m=cekTeks($un,'Username'))go('❌ '.$m,$to);
    if($role==='magang'&&($m=cekTeks((string)$jab,'Jabatan',$a==='user_add')))go('❌ '.$m,$to);   // jabatan hanya untuk anak magang (wajib saat tambah); satpam & admin tidak perlu jabatan
    if(($a==='user_add'||$pw!=='')&&strlen($pw)<6)go('❌ Password minimal 6 karakter',$to);
    if($a==='user_add'){
      db()->prepare('INSERT INTO users(username,password,nama,role,jabatan) VALUES(?,?,?,?,?)')->execute([$un,password_hash($pw,PASSWORD_DEFAULT),$nama,$role,$jab]);
      go('✅ Pengguna ditambahkan','?page=pengguna');}
    if($id===(int)$u['id'])$role='admin'; // admin tidak bisa menurunkan perannya sendiri
    if($role!=='magang')$jab=null;db()->prepare('UPDATE users SET username=?,nama=?,role=?,jabatan=? WHERE id=?')->execute([$un,$nama,$role,$jab,$id]);
    if($pw!=='')db()->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash($pw,PASSWORD_DEFAULT),$id]);
    if($id===(int)$u['id']){$_SESSION['u']['nama']=$nama;$_SESSION['u']['username']=$un;}
    go('✅ Perubahan disimpan','?page=pengguna');}
  if($a==='user_del'){
    $id=(int)$_POST['id'];
    if($id===(int)$u['id'])go('❌ Tidak bisa menghapus akun sendiri','?page=pengguna');
    db()->prepare('DELETE FROM users WHERE id=?')->execute([$id]);go('🗑️ Pengguna dihapus','?page=pengguna');}
  if($a==='abs_del'){
    $id=(int)$_POST['id'];$s=db()->prepare('SELECT a.tanggal,u.role FROM absensi a JOIN users u ON u.id=a.user_id WHERE a.id=?');$s->execute([$id]);$r=$s->fetch();
    db()->prepare('DELETE FROM absensi WHERE id=?')->execute([$id]);
    go('🗑️ Presensi dihapus',$r?'?page=harian&r='.$r['role'].'&tgl='.$r['tanggal']:'?');}
  if($a==='abs_save'){
    $id=(int)$_POST['id'];$uid=(int)$_POST['user_id'];$tgl=$_POST['tanggal']??'';$jam=$_POST['jam']??'';$j=$_POST['jenis']??'';
    $to='?page=edit&id='.$id.($id?'':'&uid='.$uid.'&tgl='.urlencode($tgl));
    $s=db()->prepare('SELECT role FROM users WHERE id=?');$s->execute([$uid]);$ro=$s->fetchColumn();
    if(!$ro)go('❌ Pengguna tidak ditemukan',$to);
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$tgl)||!preg_match('/^\d{2}:\d{2}(:\d{2})?$/',$jam))go('❌ Tanggal / jam tidak valid',$to);
    if(strlen($jam)===5)$jam.=':00';
    if(!in_array($j,['Hadir','Tidak Hadir','Izin']))go('❌ Keterangan tidak valid',$to);
    $sesi=$_POST['sesi']??'';$iz=$_POST['jenis_izin']??'';$ket=trim($_POST['keterangan']??'');$tl=trim($_POST['izin_terlambat']??'');
    $lat=$_POST['lat']??'';$lng=$_POST['lng']??'';$ap=$_POST['approval']??'none';$telat=0;
    if(!in_array($ap,['none','pending','approved','rejected']))$ap='none';
    if($j==='Hadir'){
      if(!in_array($sesi,$ro==='satpam'?SESI_SATPAM:array_merge(SESI_MAGANG,SESI_LAPORAN),true))go('❌ Sesi tidak sesuai dengan peran pengguna',$to);
      if($m=cekTeks($tl,'Keterangan terlambat / di luar lokasi / izin keluar',false))go('❌ '.$m,$to);
      $iz=null;$ket='';$telat=in_array($sesi,SESI_LAPORAN,true)?0:hitungTelat($uid,$ro,$sesi,$tgl,$jam);
      if(!is_numeric($lat)||!is_numeric($lng))$lat=$lng=null;
      if($tl==='')$ap='none';
    }elseif($j==='Izin'){
      if(!in_array($iz,['Sakit','Tidak Masuk']))go('❌ Jenis izin tidak valid',$to);
      if($m=cekTeks($ket,'Keterangan izin'))go('❌ '.$m,$to);
      $sesi=($ro==='satpam'&&isset(SHIFT[$_POST['sesi_izin']??'']))?$_POST['sesi_izin']:null;$lat=$lng=null;$tl='';if($ap==='none')$ap='pending';
    }else{if($ket!==''&&($m=cekTeks($ket,'Keterangan',false)))go('❌ '.$m,$to);$sesi=null;$iz=null;$lat=$lng=null;$tl='';$ap='none';}
    $v=[$uid,$tgl,$jam,$j,$sesi,$iz,$ket,$tl,$lat,$lng,$telat,$ap];
    if($id){db()->prepare('UPDATE absensi SET user_id=?,tanggal=?,jam=?,jenis=?,sesi=?,jenis_izin=?,keterangan=?,izin_terlambat=?,lat=?,lng=?,telat=?,approval=? WHERE id=?')->execute(array_merge($v,[$id]));}
    else{db()->prepare('INSERT INTO absensi(user_id,tanggal,jam,jenis,sesi,jenis_izin,keterangan,izin_terlambat,lat,lng,telat,approval) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)')->execute($v);}
    go('✅ Presensi disimpan','?page=harian&r='.$ro.'&tgl='.$tgl);}
 }catch(PDOException $e){
  go($e->getCode()==='23000'?'❌ Username sudah dipakai / data terkait bermasalah':'❌ Gagal menyimpan data',$_SERVER['REQUEST_URI']);}
}

// ---------- fungsi tampilan ----------
function ac($href,$ic,$t,$sub,$c,$n=0){return '<a class="mc ac" style="--c:'.$c.'" href="'.e($href).'"><span class="ic">'.$ic.'</span><span class="tx"><b>'.e($t).'</b><span class="sub">'.$sub.'</span></span>'.($n?'<span class="cnt">'.$n.'</span>':'').'</a>';}
function tabRole($page,$r,$extra=[],$cnt=[]){
  global $RM;$h='<div class="row tabs">';$tabs=$RM;
  if($page==='pengajuan')$tabs['keluar']=['🚪','Izin Keluar Jam Kerja','#0e9aa7'];   // pengajuan izin keluar di jam kerja dipisah dari pengajuan magang & satpam
  foreach($tabs as $k=>[$i,$t,$c])$h.='<a class="'.($r===$k?'on':'').'" style="--c:'.$c.'" href="?'.e(http_build_query(array_merge($extra,['page'=>$page,'r'=>$k]))).'">'.$i.' '.$t.(!empty($cnt[$k])?'<span class="cnt">'.$cnt[$k].'</span>':'').'</a>';
  return $h.'</div>';}
function ada($rs,$f){foreach($rs as $r)if($r['jenis']==='Hadir'&&$f($r))return true;return false;}
// ---------- filter & tabel anak magang ----------
function jabatanList(){return db()->query("SELECT DISTINCT jabatan FROM users WHERE role='magang' AND jabatan IS NOT NULL AND jabatan<>'' ORDER BY jabatan")->fetchAll(PDO::FETCH_COLUMN);}
function dlJabatan(){return '<datalist id="jbl">'.implode('',array_map(fn($j)=>'<option value="'.e($j).'">',jabatanList())).'</datalist>';}
function jabOk($j,$f){return $f===''||($f==='-'?trim((string)$j)==='':(string)$j===$f);}
function chipJab($j){return trim((string)$j)!==''?'<span class="b jb">'.e($j).'</span>':'<span class="mu">–</span>';}
// penanda per orang-per-hari. Pengajuan yang DISETUJUI admin tidak dihitung terlambat / di luar lokasi.
function pf($rs){$f=['hadir'=>0,'izin'=>0,'telat'=>0,'luar'=>0];
  foreach($rs as $x){if($x['jenis']==='Hadir'){$f['hadir']=1;if($x['approval']!=='approved'){if($x['telat'])$f['telat']=1;if(luar($x))$f['luar']=1;}}elseif($x['jenis']==='Izin')$f['izin']=1;}
  $f['absen']=($f['hadir']||$f['izin'])?0:1;return $f;}
function pfRec($x){return['hadir'=>(int)($x['jenis']==='Hadir'),'izin'=>(int)($x['jenis']==='Izin'),'telat'=>(int)($x['jenis']==='Hadir'&&$x['telat']),'luar'=>(int)luar($x)];}
function barFilter($page,$extra,$jab,$f,$cnt,$absen=false){
  $base=array_merge(['page'=>$page,'r'=>'magang'],$extra);
  $h='<form method="get" class="fbar">';foreach(array_merge($base,['f'=>$f]) as $k=>$v)$h.='<input type="hidden" name="'.e($k).'" value="'.e($v).'">';
  $h.='<label>🏷️ Jabatan</label><select name="jab" onchange="this.form.submit()">'.opt('','Semua jabatan',$jab);
  foreach(jabatanList() as $j)$h.=opt($j,e($j),$jab);
  $h.=opt('-','(Tanpa jabatan)',$jab).'</select></form><div class="stats fchips">';
  $ch=['all'=>['👥','Semua'],'hadir'=>['✅','Kehadiran'],'izin'=>['📝','Ijin'],'telat'=>['⏰','Keterlambatan'],'luar'=>['📍','Di Luar Lokasi']];if($absen)$ch['absen']=['➖','Belum / Tidak Hadir'];
  foreach($ch as $k=>[$i,$l])$h.='<a class="'.($f===$k?'on':'').'" href="'.e('?'.http_build_query(array_merge($base,['jab'=>$jab,'f'=>$k]))).'"><i>'.$i.'</i><b>'.(int)($cnt[$k]??0).'</b>'.$l.'</a>';
  return $h.'</div>';}
// tabel pengajuan (anak magang): $aksi=true -> tombol setujui/tolak, false -> hasil keputusan
function tabelPengajuan($rows,$aksi){
  if(!$rows){echo '<div class="empty"><div class="mas">📭</div><p class="mu">Tidak ada data untuk filter ini.</p></div>';return;}
  echo '<div class="tw"><table class="rw tb"><tr><th>Tanggal</th><th>Jam</th><th>Nama</th><th>Jabatan</th><th>Pengajuan</th><th>Alasan</th><th>Lokasi</th><th>'.($aksi?'Aksi':'Hasil').'</th></tr>';
  foreach($rows as $x){
    $lk=$x['jenis']==='Hadir'&&$x['lat']?'<a target="_blank" href="https://maps.google.com/?q='.e($x['lat']).','.e($x['lng']).'">📍 '.round(jarakM((float)$x['lat'],(float)$x['lng'],KANTOR['lat'],KANTOR['lng'])).' m</a>':'<span class="mu">–</span>';
    echo '<tr><td><b>'.tglId($x['tanggal']).'</b></td><td>'.substr($x['jam'],0,5).'</td><td><b>'.e($x['nama']).'</b></td><td>'.chipJab($x['jabatan']).'</td><td><span class="b wa">'.e(labelPengajuan($x)).'</span></td><td class="al">'.e($x['jenis']==='Izin'?$x['keterangan']:$x['izin_terlambat']).'</td><td>'.$lk.'</td><td class="aks">';
    if($aksi)echo '<form method="post" class="row nowrap">'.csrf().'<input type="hidden" name="id" value="'.(int)$x['id'].'"><button class="ok sm" name="aksi" value="approved">✔ Setujui</button><button class="no sm" name="aksi" value="rejected">✖ Tolak</button></form>';
    else echo chipAp($x['approval']);
    echo ' <a class="act" href="?page=edit&id='.(int)$x['id'].'">✏️ Edit</a></td></tr>';}
  echo '</table></div>';}
function kartu($r){
  $m=$r['role']==='satpam'?'satpam':'magang';
  echo '<div class="req t-'.$m.'"><div class="rh"><b>'.e($r['nama']).'</b><span class="mu">'.tglId($r['tanggal']).' · '.substr($r['jam'],0,5).'</span><a class="act" href="?page=edit&id='.(int)$r['id'].'">✏️ Edit</a></div>';
  echo '<div class="row"><span class="b wa">'.e(labelPengajuan($r)).'</span>';
  if($r['jenis']==='Hadir')echo '<span class="b mu">'.e(lblSesi($r['sesi'])).' · '.substr($r['jam'],0,5).'</span>';
  if($r['jenis']==='Hadir'&&luar($r))echo '<span class="b no">📍 ±'.round(jarakM((float)$r['lat'],(float)$r['lng'],KANTOR['lat'],KANTOR['lng'])).' m dari kantor</span>';
  echo '</div><p>'.e($r['jenis']==='Izin'?$r['keterangan']:$r['izin_terlambat']).'</p>'.($r['lat']?'<p><a target="_blank" href="https://maps.google.com/?q='.e($r['lat']).','.e($r['lng']).'">📍 Lihat lokasi</a></p>':'');
  echo '<p class="mu">Jika ditolak, status menjadi <b>'.($r['jenis']==='Izin'?'Tidak Hadir':'Tertolak').'</b>.</p>';
  echo '<form method="post" class="row">'.csrf().'<input type="hidden" name="id" value="'.(int)$r['id'].'"><button class="ok" name="aksi" value="approved">✔ Setujui</button><button class="no" name="aksi" value="rejected">✖ Tolak</button></form></div>';}

// ---------- data umum ----------
$pend=db()->query("SELECT a.*,u.nama,u.role,u.jabatan FROM absensi a JOIN users u ON u.id=a.user_id WHERE a.approval='pending' ORDER BY a.id")->fetchAll();
$pc=['magang'=>0,'satpam'=>0,'keluar'=>0];foreach($pend as $x){if($x['sesi']==='Laporan Keluar')$pc['keluar']++;else $pc[$x['role']]=($pc[$x['role']]??0)+1;}
$page=$_GET['page']??'home';
if(!in_array($page,['home','pengajuan','harian','rekap','detail','lokasi','pengguna','edit','uedit','ekspor','pengaturan']))$page='home';
$r=in_array($_GET['r']??'',['magang','satpam','keluar'])?$_GET['r']:'magang';
if($r==='keluar'&&($_GET['page']??'')!=='pengajuan')$r='magang';   // tab izin keluar hanya ada di halaman pengajuan
$back=$page==='home'?null:(['detail'=>'?page=rekap&r='.($_GET['r']??'magang'),'uedit'=>'?page=pengguna','edit'=>'javascript:history.back()'][$page]??'?');
head('Admin',$u,$back);

// ===================== BERANDA =====================
if($page==='home'){
  $today=date('Y-m-d');
  $us=db()->query("SELECT id,role FROM users WHERE role<>'admin'")->fetchAll();$by=[];
  foreach(db()->query("SELECT * FROM absensi WHERE tanggal='$today'")->fetchAll() as $x)$by[$x['user_id']][]=$x;
  $cnt=['hadir'=>0,'telat'=>0,'luar'=>0,'izin'=>0,'tidak'=>0,'belum'=>0];$sdh=['magang'=>[0,0],'satpam'=>[0,0]];
  foreach($us as $x){$k=kategori(harian($by[$x['id']]??[]));$cnt[$k]++;$sdh[$x['role']][1]++;if($k!=='belum')$sdh[$x['role']][0]++;}
  if($pend)echo '<a class="banner" href="?page=pengajuan&r='.($pc['magang']?'magang':($pc['satpam']?'satpam':'keluar')).'">⏳ '.count($pend).' pengajuan menunggu persetujuan — klik untuk proses</a>';
  echo '<div class="card"><h2>📅 Ringkasan Hari Ini <span class="mu">'.tglId($today,true).'</span></h2><div class="stats">';
  foreach([['✅','Hadir','hadir'],['⏰','Terlambat','telat'],['📍','Di Luar Lokasi','luar'],['📝','Izin','izin'],['❌','Tidak Hadir','tidak'],['➖','Belum Presensi','belum']] as [$i,$l,$k])echo '<div><i>'.$i.'</i><b>'.$cnt[$k].'</b>'.$l.'</div>';
  echo '</div></div>';
  echo '<div class="secgrid"><div><h3 class="sect"><b>Approver</b></h3><div class="menu adm">'.ac('?page=pengajuan&r=magang','🎓','Anak Magang','Terlambat, di luar lokasi & ijin',$RM['magang'][2],$pc['magang']).ac('?page=pengajuan&r=satpam','💂','Satpam','Laporan terlambat & ijin',$RM['satpam'][2],$pc['satpam']).ac('?page=pengajuan&r=keluar','🚪','Izin Keluar di Jam Kerja','Khusus anak magang · terpisah','#0e9aa7',$pc['keluar']).'</div></div>';
  echo '<div><h3 class="sect"><b>Presensi Harian</b></h3><div class="menu adm">'.ac('?page=harian&r=magang','🗓️','Presensi Anak Magang',$sdh['magang'][0].' dari '.$sdh['magang'][1].' sudah presensi',$RM['magang'][2]).ac('?page=harian&r=satpam','🗓️','Presensi Satpam',$sdh['satpam'][0].' dari '.$sdh['satpam'][1].' sudah presensi',$RM['satpam'][2]).'</div></div>';
  echo '<div><h3 class="sect"><b>Rekap Bulanan</b></h3><div class="menu adm">'.ac('?page=rekap&r=magang','📊','Rekap Anak Magang',namaBulan(date('Y-m')),$RM['magang'][2]).ac('?page=rekap&r=satpam','📊','Rekap Satpam',namaBulan(date('Y-m')),$RM['satpam'][2]).ac('?page=ekspor','📥','Unduh Data Excel','4 jenis unduhan Excel','#16a34a').'</div></div>';
  echo '<div><h3 class="sect"><b>Kelola</b></h3><div class="menu adm">'.ac('?page=pengguna','👥','Pengguna',db()->query('SELECT COUNT(*) FROM users')->fetchColumn().' akun terdaftar','#0e9aa7').ac('?page=pengaturan','⚙️','Pengaturan Waktu Presensi','Jadwal magang, shift satpam & radius','#64748b').ac('?page=lokasi','🗺️','Monitoring Lokasi','Peta seluruh presensi','#e0892f').'</div></div></div>';

// ===================== PENGAJUAN =====================
}elseif($page==='pengajuan'){
  if($r==='magang'){
  $jab=$_GET['jab']??'';$f=$_GET['f']??'all';if(!in_array($f,['all','hadir','izin','telat','luar']))$f='all';
  $ls=array_values(array_filter($pend,fn($x)=>$x['role']==='magang'&&$x['sesi']!=='Laporan Keluar'&&jabOk($x['jabatan'],$jab)));
  $his=array_values(array_filter(db()->query("SELECT a.*,u.nama,u.role,u.jabatan FROM absensi a JOIN users u ON u.id=a.user_id WHERE u.role='magang' AND COALESCE(a.sesi,'')<>'Laporan Keluar' AND a.approval IN('approved','rejected') ORDER BY a.id DESC LIMIT 300")->fetchAll(),fn($x)=>jabOk($x['jabatan'],$jab)));
  $cnt=['all'=>0,'hadir'=>0,'izin'=>0,'telat'=>0,'luar'=>0];foreach($ls as $x){$cnt['all']++;foreach(pfRec($x) as $k=>$v)if($v)$cnt[$k]++;}
  $pass=fn($x)=>$f==='all'||pfRec($x)[$f];$ls=array_values(array_filter($ls,$pass));$his=array_slice(array_values(array_filter($his,$pass)),0,25);
  echo tabRole('pengajuan',$r,[],$pc);
  echo '<div class="card t-magang"><h2>🎓 Anak Magang</h2>'.barFilter('pengajuan',[],$jab,$f,$cnt).'</div>';
  echo '<div class="card t-magang"><h2>⏳ Menunggu Persetujuan<span class="cnt">'.count($ls).'</span></h2>';tabelPengajuan($ls,true);echo '</div>';
  echo '<div class="card"><h2>🕘 Sudah Diproses <span class="mu">(25 terakhir)</span></h2>';tabelPengajuan($his,false);echo '</div>';
  }elseif($r==='keluar'){
  $jab=$_GET['jab']??'';
  $ls=array_values(array_filter($pend,fn($x)=>$x['sesi']==='Laporan Keluar'&&jabOk($x['jabatan'],$jab)));
  $q=db()->query("SELECT a.*,u.nama,u.role,u.jabatan FROM absensi a JOIN users u ON u.id=a.user_id WHERE a.sesi='Laporan Keluar' AND a.approval IN('approved','rejected') ORDER BY a.id DESC LIMIT 300");
  $his=array_slice(array_values(array_filter($q->fetchAll(),fn($x)=>jabOk($x['jabatan'],$jab))),0,25);
  echo tabRole('pengajuan',$r,[],$pc);
  echo '<div class="card" style="border-left:5px solid #0e9aa7"><h2>🚪 Izin Keluar di Jam Kerja</h2><p class="mu" style="margin:0"></p>'
    .'<form method="get" class="fbar" style="margin-top:12px"><input type="hidden" name="page" value="pengajuan"><input type="hidden" name="r" value="keluar"><label>🏷️ Jabatan</label><select name="jab" onchange="this.form.submit()">'.opt('','Semua jabatan',$jab);
  foreach(jabatanList() as $j)echo opt($j,e($j),$jab);
  echo opt('-','(Tanpa jabatan)',$jab).'</select></form></div>';
  echo '<div class="card t-magang"><h2>⏳ Menunggu Persetujuan<span class="cnt">'.count($ls).'</span></h2>';tabelPengajuan($ls,true);echo '</div>';
  echo '<div class="card"><h2>🕘 Sudah Diproses <span class="mu">(25 terakhir)</span></h2>';tabelPengajuan($his,false);echo '</div>';
  }else{
  $ls=array_filter($pend,fn($x)=>$x['role']===$r);
  echo tabRole('pengajuan',$r,[],$pc);
  foreach([['⏰ Terlambat / Di Luar Lokasi / Keluar-Pulang Awal',fn($x)=>$x['jenis']==='Hadir'],['📝 Ijin Tidak Masuk',fn($x)=>$x['jenis']==='Izin']] as [$jd,$f]){
    $g=array_filter($ls,$f);
    echo '<div class="card t-'.$r.'"><h2>'.$jd.'<span class="cnt">'.count($g).'</span></h2>';
    if(!$g)echo '<p class="mu">Tidak ada pengajuan.</p>';
    foreach($g as $x)kartu($x);
    echo '</div>';}
  $s=db()->prepare("SELECT a.*,u.nama FROM absensi a JOIN users u ON u.id=a.user_id WHERE u.role=? AND a.approval IN('approved','rejected') ORDER BY a.id DESC LIMIT 15");$s->execute([$r]);
  echo '<div class="card"><h2>🕘 Sudah Diproses <span class="mu">(15 terakhir)</span></h2>';
  $his=$s->fetchAll();if(!$his)echo '<p class="mu">Belum ada.</p>';
  foreach($his as $x)echo '<div class="hrow"><b>'.e($x['nama']).'</b><span class="mu">'.tglId($x['tanggal']).' · '.e(labelPengajuan($x)).'</span>'.chipAp($x['approval']).'<a class="act" href="?page=edit&id='.(int)$x['id'].'">✏️ Edit</a></div>';
  echo '</div>';

  }
// ===================== PRESENSI HARIAN =====================
}elseif($page==='harian'){
  if($r==='magang'){
  $tgl=tglOk($_GET['tgl']??'');$jab=$_GET['jab']??'';$f=$_GET['f']??'all';if(!in_array($f,['all','hadir','izin','telat','luar','absen']))$f='all';
  $us=db()->query("SELECT id,nama,jabatan FROM users WHERE role='magang' ORDER BY nama")->fetchAll();
  $q=db()->prepare("SELECT a.* FROM absensi a JOIN users u ON u.id=a.user_id WHERE u.role='magang' AND a.tanggal=? ORDER BY a.jam");$q->execute([$tgl]);
  $by=[];foreach($q->fetchAll() as $x)$by[$x['user_id']][]=$x;
  $rows=[];$cnt=['all'=>0,'hadir'=>0,'izin'=>0,'telat'=>0,'luar'=>0,'absen'=>0];
  foreach($us as $x){if(!jabOk($x['jabatan'],$jab))continue;$rs=$by[$x['id']]??[];$fl=pf($rs);$cnt['all']++;foreach($fl as $k=>$v)if($v)$cnt[$k]++;if($f==='all'||$fl[$f])$rows[]=[$x,$rs];}
  $add=fn($uid,$sesi)=>'<a class="act" title="Tambah manual" href="?page=edit&id=0&uid='.(int)$uid.'&tgl='.e($tgl).'&sesi='.urlencode($sesi).'">＋ tambah</a>';
  echo tabRole('harian',$r,['tgl'=>$tgl]);
  echo '<div class="card t-magang"><h2>🗓️ Presensi Anak Magang</h2>'.navTanggal($tgl,'tgl',['page'=>'harian','r'=>'magang','jab'=>$jab,'f'=>$f]).barFilter('harian',['tgl'=>$tgl],$jab,$f,$cnt,true);
  if(!$rows)echo '<div class="empty"><div class="mas">📭</div><p class="mu">Tidak ada data untuk filter ini.</p></div>';
  else{
    echo '<div class="tw"><table class="rw tb"><tr><th>Nama</th><th>Jabatan</th><th>Status</th>'.implode('',array_map(fn($z)=>'<th>'.e(lblSesi($z)).'</th>',SESI_MAGANG)).'<th>Laporan Keluar / Masuk</th><th>Pengajuan</th><th></th></tr>';
    foreach($rows as [$x,$rs]){[$st,$c,$i]=harian($rs);
      echo '<tr><td><b>'.e($x['nama']).'</b></td><td>'.chipJab($x['jabatan']).'</td><td><span class="b '.$c.'">'.$i.' '.e($st).'</span>';
      foreach($rs as $z)if($z['jenis']!=='Hadir')echo ' <a class="act" href="?page=edit&id='.(int)$z['id'].'">✏️</a>';
      if(!$rs)echo '<div><a class="act" href="?page=edit&id=0&uid='.(int)$x['id'].'&tgl='.e($tgl).'&jenis=Izin">＋ catat ijin / tidak hadir</a></div>';
      echo '</td>';
      foreach(SESI_MAGANG as $s)echo '<td>'.(ada($rs,fn($z)=>$z['sesi']===$s)?selJam($rs,$s,true):$add($x['id'],$s)).'</td>';
      echo '<td>'.selLaporan($rs,true).'</td><td>'.selPengajuan($rs).'</td><td class="aks"><a class="btn sm" href="?page=detail&uid='.(int)$x['id'].'&r=magang&bln='.substr($tgl,0,7).'">Detail ›</a></td></tr>';}
    echo '</table></div>';}
  echo '</div>';
  }else{
  $tgl=tglOk($_GET['tgl']??'');$f=$_GET['f']??'all';
  $us=db()->prepare('SELECT id,nama FROM users WHERE role=? ORDER BY nama');$us->execute([$r]);$us=$us->fetchAll();
  $q=db()->prepare('SELECT a.* FROM absensi a JOIN users u ON u.id=a.user_id WHERE u.role=? AND a.tanggal=? ORDER BY a.jam');$q->execute([$r,$tgl]);
  $by=[];foreach($q->fetchAll() as $x)$by[$x['user_id']][]=$x;
  $rows=[];$cnt=['all'=>0,'hadir'=>0,'telat'=>0,'luar'=>0,'izin'=>0,'tidak'=>0,'belum'=>0];
  foreach($us as $x){$rs=$by[$x['id']]??[];$h=harian($rs);$k=kategori($h);$cnt['all']++;$cnt[$k]++;$rows[]=[$x,$rs,$h,$k];}
  $L=fn($a)=>'?'.http_build_query(array_merge(['page'=>'harian','r'=>$r,'tgl'=>$tgl,'f'=>$f],$a));
  echo tabRole('harian',$r,['tgl'=>$tgl]);
  echo '<div class="card">'.navTanggal($tgl,'tgl',['page'=>'harian','r'=>$r]).'<div class="stats">';
  foreach([['all','👥','Semua'],['hadir','✅','Hadir'],['telat','⏰','Terlambat'],['luar','📍','Di Luar Lokasi'],['izin','📝','Izin'],['tidak','❌','Tidak Hadir'],['belum','➖','Belum Presensi']] as [$k,$i,$l])
    echo '<a class="'.($f===$k?'on':'').'" href="'.e($L(['f'=>$k])).'"><i>'.$i.'</i><b>'.$cnt[$k].'</b>'.$l.'</a>';
  echo '</div></div><div class="pgrid">';$n=0;
  $add=fn($uid,$sesi)=>'<a class="act" title="Tambah manual" href="?page=edit&id=0&uid='.(int)$uid.'&tgl='.e($tgl).'&sesi='.urlencode($sesi).'">＋ tambah</a>';
  foreach($rows as [$x,$rs,$h,$k]){if($f!=='all'&&$f!==$k)continue;$n++;
    echo '<div class="card person t-'.$r.'"><div class="rh"><b>'.e($x['nama']).'</b><a class="act" href="?page=detail&uid='.(int)$x['id'].'&r='.$r.'&bln='.substr($tgl,0,7).'">Detail ›</a></div><span class="b '.$h[1].'">'.$h[2].' '.e($h[0]).'</span><div class="chips">';
    if($r==='satpam'){
      echo '<div class="chip"><small>Jam Masuk (Shift)</small>'.(ada($rs,fn($z)=>isset(SHIFT[$z['sesi']]))?selShift($rs,true):$add($x['id'],array_key_first(SHIFT))).'</div>';
      echo '<div class="chip"><small>Jam Pulang</small>'.(ada($rs,fn($z)=>$z['sesi']==='Pulang')?selJam($rs,'Pulang',true):$add($x['id'],'Pulang')).'</div>';
    }else{foreach(SESI_MAGANG as $s)echo '<div class="chip"><small>'.e(lblSesi($s)).'</small>'.(ada($rs,fn($z)=>$z['sesi']===$s)?selJam($rs,$s,true):$add($x['id'],$s)).'</div>';
      if(ada($rs,fn($z)=>in_array($z['sesi'],SESI_LAPORAN,true)))echo '<div class="chip"><small>Laporan Keluar / Masuk</small>'.selLaporan($rs,true).'</div>';}
    echo '</div>';
    foreach($rs as $z)if($z['jenis']!=='Hadir')echo '<div class="note">'.($z['jenis']==='Izin'?'Ijin '.e($z['jenis_izin']):'Tidak Hadir'.($z['keterangan']?': '.e($z['keterangan']):'')).' <a class="act" href="?page=edit&id='.(int)$z['id'].'">✏️ Edit</a></div>';
    if(!ada($rs,fn($z)=>true)&&!array_filter($rs,fn($z)=>$z['jenis']!=='Hadir'))echo '<div class="note"><a class="act" href="?page=edit&id=0&uid='.(int)$x['id'].'&tgl='.e($tgl).'&jenis=Izin">＋ catat ijin / tidak hadir</a></div>';
    $pj=selPengajuan($rs);if(strpos($pj,'class="pj"')!==false)echo '<div class="pjbox"><small>Pengajuan</small>'.$pj.'</div>';
    echo '</div>';}
  echo '</div>';
  if(!$n)echo '<div class="card empty"><div class="mas">📭</div><p class="mu">Tidak ada data untuk filter ini.</p></div>';

  }
// ===================== REKAP BULANAN =====================
}elseif($page==='rekap'){
  if($r==='magang'){
  $bln=bulanOk($_GET['bln']??'');$jab=$_GET['jab']??'';$f=$_GET['f']??'all';if(!in_array($f,['all','hadir','izin','telat','luar']))$f='all';
  $rb=array_values(array_filter(rekapBulan($bln,'magang'),fn($x)=>jabOk($x['jabatan'],$jab)));
  $fl=fn($x)=>['hadir'=>$x['hadir']>0,'izin'=>$x['izin']>0,'telat'=>array_sum($x['per'])>0,'luar'=>$x['lu']>0];
  $cnt=['all'=>count($rb),'hadir'=>0,'izin'=>0,'telat'=>0,'luar'=>0];foreach($rb as $x)foreach($fl($x) as $k=>$v)if($v)$cnt[$k]++;
  $rb=array_values(array_filter($rb,fn($x)=>$f==='all'||$fl($x)[$f]));
  $tt=totalRekap($rb);
  echo tabRole('rekap',$r,['bln'=>$bln]);
  echo '<div class="card t-magang"><h2>📊 Rekap Anak Magang — '.e(namaBulan($bln)).'</h2>'.navBulan($bln,['page'=>'rekap','r'=>'magang','jab'=>$jab,'f'=>$f]);kotakBulan($tt,'magang');
  echo '</div>';
  echo '<div class="card"><h2>👤 Rekap Per Orang</h2>'.barFilter('rekap',['bln'=>$bln],$jab,$f,$cnt);
  if(!$rb)echo '<div class="empty"><div class="mas">📭</div><p class="mu">Tidak ada data untuk filter ini.</p></div>';
  else{
    echo '<div class="tw"><table class="rw tb"><tr><th>Nama</th><th>Jabatan</th><th>Hadir</th><th>Izin</th><th>Tidak Hadir</th>'.implode('',array_map(fn($z)=>'<th>'.e(kolTelat($z)).'</th>',SESI_MAGANG)).'<th>Di Luar Lokasi</th><th></th></tr>';
    foreach($rb as $x)echo '<tr><td><b>'.e($x['nama']).'</b><div class="mu">@'.e($x['username']).'</div></td><td>'.chipJab($x['jabatan']).'</td><td><span class="b ok">'.$x['hadir'].' hari</span></td><td><span class="b '.($x['izin']?'wa':'ok').'">'.$x['izin'].' hari</span></td><td><span class="b '.($x['tidak']?'no':'ok').'">'.$x['tidak'].' hari</span></td>'.implode('',array_map(fn($z)=>'<td><span class="b '.(($x['per'][$z]??0)?'no':'ok').'">'.(int)($x['per'][$z]??0).'×</span></td>',SESI_MAGANG)).'<td><span class="b '.($x['lu']?'no':'ok').'">'.$x['lu'].'×</span></td>'
      .'<td class="aks"><a class="btn sm" href="?page=detail&uid='.(int)$x['id'].'&r=magang&bln='.$bln.'">Detail ›</a></td></tr>';
    echo '</table></div>';}
  echo '<p class="mu">Hadir / Izin / Tidak Hadir dihitung <b>per hari</b>. Terlambat dan Di Luar Lokasi dihitung <b>per kejadian</b>; yang sudah <b>disetujui admin tidak dihitung terlambat</b>.</p></div>';
  }else{
  $bln=bulanOk($_GET['bln']??'');$rb=rekapBulan($bln,$r);$tt=totalRekap($rb);
  echo tabRole('rekap',$r,['bln'=>$bln]);
  echo '<div class="card t-'.$r.'"><h2>📊 Rekap '.e($RM[$r][1]).' — '.e(namaBulan($bln)).'</h2>'.navBulan($bln,['page'=>'rekap','r'=>$r]);kotakBulan($tt,$r);echo '</div>';
  echo '<div class="card"><h2>👤 Rekap Per Orang</h2>';
  if(!$rb)echo '<div class="empty"><div class="mas">📭</div><p class="mu">Belum ada pengguna.</p></div>';
  else{
    echo '<div class="tw"><table class="rb stk"><tr><th>Nama</th><th>Hadir</th><th>Izin</th><th>Tidak Hadir</th><th>Terlambat Masuk</th><th>Di Luar Lokasi</th><th></th></tr>';
    foreach($rb as $x)echo '<tr><td data-l="Nama"><b>'.e($x['nama']).'</b><div class="mu">@'.e($x['username']).'</div></td><td data-l="Hadir"><span class="b ok">'.$x['hadir'].' hari</span></td><td data-l="Izin"><span class="b '.($x['izin']?'wa':'ok').'">'.$x['izin'].' hari</span></td><td data-l="Tidak Hadir"><span class="b '.($x['tidak']?'no':'ok').'">'.$x['tidak'].' hari</span></td>'
      .'<td data-l="Terlambat Masuk"><span class="b '.($x['tp']?'no':'ok').'">'.$x['tp'].'×</span></td><td data-l="Di Luar Lokasi"><span class="b '.($x['lu']?'no':'ok').'">'.$x['lu'].'×</span></td>'
      .'<td class="end"><a class="btn sm" href="?page=detail&uid='.(int)$x['id'].'&r='.$r.'&bln='.$bln.'">Detail ›</a></td></tr>';
    echo '</table></div>';}
  echo '<p class="mu">Hadir / Izin / Tidak Hadir dihitung <b>per hari</b> (izin yang ditolak masuk Tidak Hadir). Terlambat dan Di Luar Lokasi dihitung <b>per kejadian</b>, kecuali yang sudah disetujui admin.</p></div>';

  }
// ===================== DETAIL REKAP PER ORANG =====================
}elseif($page==='detail'){
  $uid=(int)($_GET['uid']??0);$bln=bulanOk($_GET['bln']??'');
  $s=db()->prepare("SELECT * FROM users WHERE id=? AND role<>'admin'");$s->execute([$uid]);$x=$s->fetch();
  if(!$x)echo '<div class="card"><p>Pengguna tidak ditemukan.</p></div>';
  else{
    $rb=rekapBulan($bln,null,$uid)[$uid];
    echo '<div class="card person t-'.$x['role'].'"><div class="row between nowrap"><div><h2 style="margin:0">'.$RM[$x['role']][0].' '.e($x['nama']).'</h2><span class="mu">@'.e($x['username']).' · '.$RM[$x['role']][1].($x['role']==='magang'&&$x['jabatan']?' · '.e($x['jabatan']):'').'</span></div><div class="row"><a class="btn g sm" href="?page=uedit&id='.$uid.'">✏️ Edit Pengguna</a></div></div></div>';
    echo '<div class="card"><h2>📊 Rekap '.e(namaBulan($bln)).'</h2>'.navBulan($bln,['page'=>'detail','uid'=>$uid,'r'=>$x['role']]);kotakBulan($rb,$x['role']);echo '</div>';
    $s=db()->prepare('SELECT * FROM absensi WHERE user_id=? AND tanggal BETWEEN ? AND ? ORDER BY tanggal DESC,jam');$s->execute([$uid,$bln.'-01',date('Y-m-t',strtotime($bln.'-01'))]);
    echo '<div class="card"><div class="row between"><h2 style="margin:0">🗓️ Riwayat Harian</h2><a class="btn sm" href="?page=edit&id=0&uid='.$uid.'&tgl='.date('Y-m-d').'">＋ Tambah Presensi</a></div>';riwayatHarian($s->fetchAll(),$x['role'],true);echo '</div>';}

// ===================== MONITORING LOKASI =====================
}elseif($page==='lokasi'){
  $tgl=tglOk($_GET['tgl']??'');$rr=in_array($_GET['r']??'',['satpam','magang'])?$_GET['r']:'';
  $L=fn($a)=>e('?'.http_build_query(array_merge(['page'=>'lokasi','tgl'=>$tgl,'r'=>$rr],$a)));
  $q=db()->prepare("SELECT a.*,u.nama,u.role FROM absensi a JOIN users u ON u.id=a.user_id WHERE a.tanggal=? AND a.lat IS NOT NULL AND u.role<>'admin'".($rr?' AND u.role=?':'')." ORDER BY a.jam");
  $q->execute($rr?[$tgl,$rr]:[$tgl]);$ls=$q->fetchAll();
  $uq=db()->prepare("SELECT id,nama,role FROM users WHERE role<>'admin'".($rr?' AND role=?':'')." ORDER BY role,nama");$uq->execute($rr?[$rr]:[]);$semua=$uq->fetchAll();
  $pal=['#7c3aed','#e11d48','#16a34a','#d97706','#0ea5e9','#db2777','#65a30d','#9333ea'];$wr=[];$pts=[];
  foreach($ls as $x){$id=(int)$x['user_id'];if(!isset($wr[$id]))$wr[$id]=$pal[count($wr)%count($pal)];
    $pts[]=['i'=>count($pts),'u'=>$id,'c'=>$wr[$id],'n'=>$x['nama'],'s'=>lblSesi($x['sesi']),'j'=>substr($x['jam'],0,5),'lat'=>(float)$x['lat'],'lng'=>(float)$x['lng']];}
  $belum=array_filter($semua,fn($x)=>!isset($wr[(int)$x['id']]));
  echo '<div class="card"><h2>🗺️ Monitoring Lokasi Presensi</h2>'.navTanggal($tgl,'tgl',['page'=>'lokasi','r'=>$rr]);
  echo '<div class="row tabs">';foreach([''=>'👥 Semua','magang'=>'🎓 Anak Magang','satpam'=>'💂 Satpam'] as $k=>$t)echo '<a class="'.($rr===(string)$k?'on':'').'" href="'.$L(['r'=>$k]).'">'.$t.'</a>';echo '</div>';
  echo '<div id="map" data-lat="'.KANTOR['lat'].'" data-lng="'.KANTOR['lng'].'" data-r="'.RADIUS_M.'" data-n="'.e(KANTOR['nama']).'"></div><script type="application/json" id="pts">'.json_encode($pts,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE).'</script>';
  echo '<p class="mu">Lingkaran = radius '.RADIUS_M.' m dari '.e(KANTOR['nama']).'. Titik besar = presensi terakhir tiap orang; garis putus-putus = urutan perpindahan. Klik baris di bawah untuk memusatkan peta.</p>';
  if($pts){
    echo '<div class="tw"><table class="lk stk"><tr><th>Nama</th><th>Sesi</th><th>Jam</th><th>Status</th><th>Lokasi</th></tr>';
    foreach($ls as $n=>$x){[$st,$c,$i]=status($x);
      echo '<tr data-pt="'.$n.'" style="cursor:pointer"><td data-l="Nama"><span class="dot" style="background:'.e($wr[(int)$x['user_id']]).'"></span><b>'.e($x['nama']).'</b> <span class="mu">'.($x['role']==='satpam'?'💂':'🎓').'</span></td><td data-l="Sesi">'.e(lblSesi($x['sesi'])).'</td><td data-l="Jam">'.substr($x['jam'],0,5).'</td>'
        .'<td data-l="Status"><span class="b '.$c.'">'.$i.' '.e($st).'</span></td><td data-l="Lokasi"><a target="_blank" href="https://maps.google.com/?q='.e($x['lat']).','.e($x['lng']).'">📍 '.e($x['lat']).', '.e($x['lng']).'</a> <span class="mu">· '.round(jarakM((float)$x['lat'],(float)$x['lng'],KANTOR['lat'],KANTOR['lng'])).' m</span></td></tr>';}
    echo '</table></div>';
  }else echo '<div class="empty"><div class="mas">🗺️</div><p class="mu">Belum ada lokasi presensi pada tanggal ini.</p></div>';
  if($belum&&$pts)echo '<p class="mu" style="margin-top:12px"><b>Belum ada lokasi:</b> '.e(implode(', ',array_map(fn($x)=>$x['nama'],$belum))).'</p>';
  echo '</div><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css"><script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>';

// ===================== UNDUH EXCEL + PENGATURAN =====================
}elseif($page==='ekspor'){
  $unduh=[
   ['rekap_magang','🎓','Rekap Bulanan Anak Magang','#3978d4',''],
   ['rekap_satpam','💂','Rekap Bulanan Satpam','#7c5cd6',''],
   ['harian_magang','🗓️','Rekap Harian Sebulan Anak Magang','#3978d4',''],
   ['harian_satpam','🗓️','Rekap Harian Sebulan Satpam','#7c5cd6','']];
  echo '<div class="card"><h2>📥 Unduh Data Excel</h2><p class="mu" style="margin-top:0">Pilih bulan pada tiap kartu, lalu unduh. Keterlambatan yang sudah disetujui admin tidak dihitung.</p></div>';
  echo '<div class="pgrid" style="grid-template-columns:repeat(auto-fit,minmax(340px,1fr));margin-bottom:16px">';
  foreach($unduh as [$jn,$ic,$jd,$c,$ds])
    echo '<div class="card" style="border-top:4px solid '.$c.'"><h2>'.$ic.' '.e($jd).'</h2><p class="mu" style="margin-top:0">'.e($ds).'</p>
     <form method="get" action="export.php"><input type="hidden" name="jenis" value="'.$jn.'"><label>Bulan</label><input type="month" name="bln" value="'.date('Y-m').'" max="'.date('Y-m').'" required><button class="big xl">📥 Unduh Excel (.xlsx)</button></form></div>';
  echo '</div>';



// ===================== PENGATURAN WAKTU PRESENSI =====================
}elseif($page==='pengaturan'){
  $P=pengaturan();
  echo '<div class="card"><h2>⚙️ Pengaturan Waktu Presensi</h2><p class="mu" style="margin:0">Perubahan berlaku untuk presensi berikutnya. Data presensi yang sudah tercatat tidak berubah.</p></div>';
  echo '<div class="pgrid" style="grid-template-columns:repeat(auto-fit,minmax(340px,1fr))">';
  // anak magang: daftar sesi presensi + tambah / hapus
  $pk=db()->query("SELECT a.sesi,COUNT(*) FROM absensi a JOIN users u ON u.id=a.user_id WHERE u.role='magang' AND a.sesi IS NOT NULL GROUP BY a.sesi")->fetchAll(PDO::FETCH_KEY_PAIR);   // jumlah catatan lama per sesi (untuk peringatan hapus)
  echo '<div class="card" style="border-top:4px solid #3978d4"><h2>🎓 Jadwal Presensi Anak Magang <span class="mu">('.count(MAGANG).' sesi)</span></h2><form method="post">'.csrf().'<input type="hidden" name="aksi" value="magang_save"><button style="display:none" tabindex="-1" aria-hidden="true">Simpan</button><div class="tw"><table class="pt"><tr><th>Sesi</th><th>Aturan</th><th>Jam</th><th></th></tr>';
  foreach(MAGANG as $n=>[$j,$t]){$k=slugShift($n);$pakai=(int)($pk[$n]??0);
    $cf='Hapus sesi '.lblSesi($n).'?'.($pakai?' '.$pakai.' catatan presensi lama pada sesi ini tetap tersimpan tetapi tidak lagi ditampilkan.':'');
    echo '<tr><td style="vertical-align:middle"><b>'.ikonSesi($n).' '.e(lblSesi($n)).'</b></td><td style="vertical-align:middle" class="mu">'.($t==='batas'?'Terlambat bila lewat jam ini':'Lebih awal bila sebelum jam ini').'</td>
     <td><input type="time" name="jam_'.$k.'" value="'.e($j).'" required></td>
     <td style="vertical-align:middle"><button class="no sm" name="hapus" value="'.e($n).'" formnovalidate'.(count(MAGANG)<=1?' disabled title="Minimal 1 sesi"':'').' onclick="return confirm(\''.e($cf).'\')">🗑️ Hapus</button></td></tr>';}
  echo '</table></div><p class="mu">Setiap sesi tampil sebagai tombol presensi untuk anak magang, berurutan menurut jam. <b>Terlambat bila lewat jam ini</b>: presensi setelah jam tersebut dianggap terlambat (mis. Jam Masuk). <b>Lebih awal bila sebelum jam ini</b>: presensi sebelum jam tersebut dianggap lebih awal (mis. Jam Pulang). Keterlambatan / lebih awal wajib diberi keterangan oleh anak magang. Sesi yang dihapus tidak menghapus catatan presensi lama; catatan itu tetap tersimpan tetapi tidak lagi tampil di tabel.</p>
   <button class="big">💾 Simpan Jadwal Anak Magang</button></form>
   <h3 class="sect" style="margin-top:22px">➕ Tambah Sesi Presensi</h3>
   <form method="post">'.csrf().'<input type="hidden" name="aksi" value="magang_add"><div class="fgrid"><div><label>Nama sesi</label><input name="nama" maxlength="20" required placeholder="mis. Apel Pagi"></div>
   <div><label>Jam</label><input type="time" name="jam" required></div>
   <div><label>Aturan</label><select name="tipe">'.opt('batas','⏰ Terlambat bila lewat jam ini','batas').opt('min','🏃 Lebih awal bila sebelum jam ini','batas').'</select></div></div>
   <button class="big">➕ Tambah Sesi</button></form></div>';
  // satpam: daftar shift + tambah / hapus
  echo '<div class="card" style="border-top:4px solid #7c5cd6"><h2>💂 Jadwal Shift Satpam <span class="mu">('.count(SHIFT).' shift)</span></h2><form method="post">'.csrf().'<input type="hidden" name="aksi" value="shift_save"><div class="tw"><table class="pt"><tr><th>Shift</th><th>Jam Masuk</th><th>Jam Pulang</th><th></th></tr>';
  foreach(SHIFT as $n=>$v){$nm=substr($n,6);$k=slugShift($nm);
    echo '<tr><td style="vertical-align:middle"><b>'.$v[2].' '.e($n).'</b>'.(shiftLewatMalam($n)?'<div class="mu">melewati tengah malam</div>':'').'</td><td><input type="time" name="masuk_'.$k.'" value="'.e($v[0]).'" required></td><td><input type="time" name="pulang_'.$k.'" value="'.e($v[1]).'" required></td>
     <td style="vertical-align:middle"><button class="no sm" name="hapus" value="'.e($nm).'" formnovalidate onclick="return confirm(\'Hapus aturan '.e($n).'?\')">🗑️ Hapus</button></td></tr>';}
  echo '</table></div><p class="mu">Jam Masuk = awal shift (datang setelahnya dianggap <b>terlambat</b>; boleh datang hingga '.(SHIFT_AWAL/60).' jam sebelumnya). Jam Pulang = akhir shift (pulang sebelumnya dianggap <b>pulang lebih awal</b>). Jam pulang lebih kecil dari jam masuk berarti shift melewati tengah malam. Shift yang sudah dipakai pada data presensi tidak bisa dihapus.</p>
   <button class="big">💾 Simpan Jadwal Shift</button></form>
   <h3 class="sect" style="margin-top:22px">➕ Tambah Aturan Shift</h3>
   <form method="post">'.csrf().'<input type="hidden" name="aksi" value="shift_add"><div class="fgrid"><div><label>Nama shift</label><input name="nama" maxlength="20" required placeholder="mis. Siang"></div>
   <div><label>Jam Masuk</label><input type="time" name="masuk" required></div><div><label>Jam Pulang</label><input type="time" name="pulang" required></div></div>
   <button class="big">➕ Tambah Shift</button></form></div>';
  // radius
  echo '<div class="card" style="border-top:4px solid #e0892f"><h2>📍 Radius Absensi</h2><form method="post">'.csrf().'<input type="hidden" name="aksi" value="set_radius">
   <label>Jarak maksimal dari '.e(KANTOR['nama']).' (meter)</label><input type="number" name="radius" value="'.RADIUS_M.'" min="10" max="5000" step="1" required>
   <p class="mu">Presensi di luar radius ini dicatat <b>Di Luar Lokasi</b> dan wajib diberi keterangan. Berlaku untuk anak magang dan satpam (10 – 5000 m).</p>
   <button class="big">💾 Simpan Radius</button></form></div>';
  echo '</div>';

// ===================== PENGGUNA =====================
}elseif($page==='pengguna'){
  $rk=rekapBulan(date('Y-m'));
  echo '<div class="card"><h2>➕ Tambah Pengguna</h2><form method="post" autocomplete="off">'.csrf().'<input type="hidden" name="aksi" value="user_add"><div class="fgrid">
   <div><label>Nama</label><input name="nama" required data-max="'.MAKS_HURUF.'"><div class="cnt"><span data-cc>0</span>/'.MAKS_HURUF.' karakter (spasi tidak dihitung)</div></div><div><label>Username</label><input name="username" required data-max="'.MAKS_HURUF.'"><div class="cnt"><span data-cc>0</span>/'.MAKS_HURUF.'</div></div>
   <div><label>Password (min. 6 karakter)</label><input name="password" required minlength="6"></div>
   <div><label>Status</label><select name="role" data-role-toggle>'.opt('magang','🎓 Anak Magang','').opt('satpam','💂 Satpam','').opt('admin','🛡️ Admin','').'</select></div>
   <div data-role="magang"><label>Jabatan Anak Magang</label><input name="jabatan" list="jbl" maxlength="'.MAKS_ALASAN.'" required data-max="'.MAKS_HURUF.'" placeholder="mis. Pengolahan Data"><div class="cnt"><span data-cc>0</span>/'.MAKS_HURUF.' karakter (spasi tidak dihitung)</div></div></div>'.dlJabatan().'
   <button class="big">Tambah Pengguna</button></form></div>';
  $all=db()->query("SELECT * FROM users ORDER BY FIELD(role,'admin','magang','satpam'),nama")->fetchAll();
  $RL=['admin'=>['🛡️','Admin','ok'],'magang'=>['🎓','Anak Magang','bl'],'satpam'=>['💂','Satpam','jb']];
  echo '<div class="card"><h2>👥 Daftar Pengguna <span class="mu">('.count($all).' akun)</span></h2>';
  echo '<div class="tw"><table class="rb stk"><tr><th>No</th><th>Nama</th><th>Username</th><th>Peran</th><th>Jabatan</th><th>Terlambat Bulan Ini</th><th></th></tr>';
  foreach($all as $n=>$x){$ro=$x['role'];$t=$rk[$x['id']]??null;$sendiri=(int)$x['id']===(int)$u['id'];
    echo '<tr><td data-l="No">'.($n+1).'</td><td data-l="Nama"><b>'.e($x['nama']).'</b>'.($sendiri?' <span class="mu">(Anda)</span>':'').'</td><td data-l="Username">@'.e($x['username']).'</td>'
      .'<td data-l="Peran"><span class="b '.$RL[$ro][2].'">'.$RL[$ro][0].' '.$RL[$ro][1].'</span></td>'
      .'<td data-l="Jabatan">'.($ro==='magang'?chipJab($x['jabatan']):'<span class="mu">–</span>').'</td>'
      .'<td data-l="Terlambat">'.($t?'<span class="b '.($t['tp']?'no':'ok').'">'.$t['tp'].'×</span>':'<span class="mu">–</span>').'</td>'
      .'<td class="end"><div class="row nowrap" style="justify-content:flex-end"><a class="btn g sm" href="?page=uedit&id='.(int)$x['id'].'">✏️ Edit</a>'
      .($ro!=='admin'?'<a class="btn g sm" href="?page=detail&uid='.(int)$x['id'].'&r='.$ro.'">📊 Rekap</a>':'')
      .(!$sendiri?'<form method="post" onsubmit="return confirm(\'Hapus pengguna ini beserta seluruh data presensinya?\')">'.csrf().'<input type="hidden" name="id" value="'.(int)$x['id'].'"><button class="no sm" name="aksi" value="user_del">🗑️ Hapus</button></form>':'')
      .'</div></td></tr>';}
  echo '</table></div><p class="mu">Terlambat bulan ini = jumlah terlambat masuk (belum / tidak disetujui admin) pada '.e(namaBulan(date('Y-m'))).'.</p></div>';

}elseif($page==='uedit'){
  $id=(int)($_GET['id']??0);$s=db()->prepare('SELECT * FROM users WHERE id=?');$s->execute([$id]);$x=$s->fetch();
  if(!$x)echo '<div class="card"><p>Pengguna tidak ditemukan. <a href="?page=pengguna">Kembali</a></p></div>';
  else{
    echo '<div class="card"><h2>✏️ Edit Pengguna</h2><form method="post" autocomplete="off">'.csrf().'<input type="hidden" name="id" value="'.$id.'"><div class="fgrid">
     <div><label>Nama</label><input name="nama" value="'.e($x['nama']).'" required data-max="'.MAKS_HURUF.'"><div class="cnt"><span data-cc>0</span>/'.MAKS_HURUF.' karakter (spasi tidak dihitung)</div></div><div><label>Username</label><input name="username" value="'.e($x['username']).'" required data-max="'.MAKS_HURUF.'"><div class="cnt"><span data-cc>0</span>/'.MAKS_HURUF.'</div></div>
     <div><label>Password baru (kosongkan jika tidak diganti)</label><input name="password" minlength="6"></div>
     <div><label>Status</label><select name="role" data-role-toggle>'.opt('magang','🎓 Anak Magang',$x['role']).opt('satpam','💂 Satpam',$x['role']).opt('admin','🛡️ Admin',$x['role']).'</select></div>
     <div data-role="magang"><label>Jabatan Anak Magang</label><input name="jabatan" list="jbl" maxlength="'.MAKS_ALASAN.'" value="'.e($x['jabatan']).'" data-max="'.MAKS_HURUF.'" placeholder="mis. Pengolahan Data"><div class="cnt"><span data-cc>0</span>/'.MAKS_HURUF.' karakter (spasi tidak dihitung)</div></div></div>'.dlJabatan().'
     <div class="row" style="margin-top:16px"><button name="aksi" value="user_save">💾 Simpan</button><a class="btn g" href="?page=pengguna">Batal</a></div></form></div>';}

// ===================== EDIT PRESENSI =====================
}else{
  $id=(int)($_GET['id']??0);$r=null;
  if($id){$s=db()->prepare('SELECT * FROM absensi WHERE id=?');$s->execute([$id]);$r=$s->fetch();}
  if(!$r){$id=0;$jn=$_GET['jenis']??'Hadir';
    $r=['user_id'=>(int)($_GET['uid']??0),'tanggal'=>tglOk($_GET['tgl']??''),'jam'=>date('H:i:s'),'jenis'=>in_array($jn,['Hadir','Tidak Hadir','Izin'])?$jn:'Hadir','sesi'=>in_array($_GET['sesi']??'',SESI)?$_GET['sesi']:'Masuk Pagi','jenis_izin'=>'Sakit','keterangan'=>'','izin_terlambat'=>'','lat'=>'','lng'=>'','approval'=>'none'];}
  $us=db()->query("SELECT id,nama,role FROM users WHERE role<>'admin' ORDER BY role,nama")->fetchAll();
  echo '<div class="card"><h2>'.($id?'✏️ Edit Presensi':'➕ Tambah Presensi Manual').'</h2><form method="post">'.csrf().'<input type="hidden" name="aksi" value="abs_save"><input type="hidden" name="id" value="'.$id.'">
   <label>Pengguna</label><select name="user_id">';
  foreach($us as $x)echo opt($x['id'],e($x['nama']).' ('.($x['role']==='satpam'?'Satpam':'Magang').')',$r['user_id']);
  echo '</select><div class="fgrid"><div><label>Tanggal</label><input type="date" name="tanggal" value="'.e($r['tanggal']).'" required></div><div><label>Jam (WIB)</label><input type="time" step="1" name="jam" value="'.e($r['jam']).'" required></div></div>
   <label>Keterangan</label><select name="jenis" id="jenis">'.opt('Hadir','✅ Hadir',$r['jenis']).opt('Tidak Hadir','❌ Tidak Hadir',$r['jenis']).opt('Izin','📝 Ijin',$r['jenis']).'</select>
   <div data-j="Hadir"><label>Sesi</label><select name="sesi"><optgroup label="Anak Magang">';
  foreach(SESI_MAGANG as $s)echo opt($s,lblSesi($s),$r['sesi']);
  foreach(SESI_LAPORAN as $s)echo opt($s,$s.' (laporan)',$r['sesi']);
  echo '</optgroup><optgroup label="Satpam">';
  foreach(SESI_SATPAM as $s)echo opt($s,$s==='Pulang'?'Jam Pulang (Satpam)':$s,$r['sesi']);
  echo '</optgroup></select><label>Keterangan Terlambat / Di Luar Lokasi</label><textarea name="izin_terlambat" rows="2" data-max="'.MAKS_HURUF.'">'.e($r['izin_terlambat']).'</textarea><div class="cnt"><span data-cc>0</span>/'.MAKS_HURUF.' karakter (spasi tidak dihitung)</div>
   <div class="fgrid"><div><label>Latitude</label><input name="lat" value="'.e($r['lat']).'"></div><div><label>Longitude</label><input name="lng" value="'.e($r['lng']).'"></div></div>
   <p class="mu">Status terlambat dihitung otomatis dari sesi dan jam.</p></div>
   <div data-j="Izin"><label>Shift yang tidak dihadiri (khusus satpam)</label><select name="sesi_izin">'.opt('','— Tidak ada —',$r['sesi']).implode('',array_map(fn($n)=>opt($n,SHIFT[$n][2].' '.$n.' ('.rentang($n).')',$r['sesi']),array_keys(SHIFT))).'</select></div>
   <div data-j="Izin"><label>Jenis Ijin</label><select name="jenis_izin">'.opt('Sakit','Sakit',$r['jenis_izin']).opt('Tidak Masuk','Tidak Masuk',$r['jenis_izin']).'</select></div>
   <div data-j="Izin,Tidak Hadir"><label>Keterangan / Alasan</label><textarea name="keterangan" rows="2" data-max="'.MAKS_HURUF.'">'.e($r['keterangan']).'</textarea><div class="cnt"><span data-cc>0</span>/'.MAKS_HURUF.' karakter (spasi tidak dihitung)</div></div>
   <div data-j="Hadir,Izin"><label>Status Persetujuan</label><select name="approval">'.opt('none','Tidak perlu',$r['approval']).opt('pending','⏳ Menunggu',$r['approval']).opt('approved','✔ Disetujui',$r['approval']).opt('rejected','✖ Ditolak',$r['approval']).'</select></div>
   <div class="row" style="margin-top:16px"><button>💾 Simpan</button><a class="btn g" href="javascript:history.back()">Batal</a></div></form>';
  if($id)echo '<form method="post" style="margin-top:12px" onsubmit="return confirm(\'Hapus data presensi ini?\')">'.csrf().'<input type="hidden" name="id" value="'.$id.'"><button class="no sm" name="aksi" value="abs_del">🗑️ Hapus presensi ini</button></form>';
  echo '</div>';
}
if(in_array($page,['home','pengajuan','harian','rekap','detail','lokasi']))$LIVE=sigData();   // muat ulang otomatis bila data berubah
foot();
