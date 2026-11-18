<?php require 'config.php'; $u=auth('user'); $role=$u['role']; $err='';
$tgl=tglKerja($u);   // tanggal kerja (satpam shift malam yang pulang dini hari tetap ke tanggal shift dimulai)

// halaman -> sesi presensi
$SHP=[];foreach(SHIFT as $n=>$_)$SHP['s_'.slugShift(substr($n,6))]=$n;   // halaman presensi tiap shift satpam (dinamis sesuai pengaturan admin)
$SMG=[];foreach(SESI_MAGANG as $n)$SMG['m_'.slugShift($n)]=$n;           // halaman presensi tiap sesi anak magang (dinamis sesuai pengaturan admin)
$LAP=['l_keluar'=>'Laporan Keluar','l_masuk'=>'Laporan Masuk'];            // laporan keluar / masuk di luar jam istirahat (anak magang, boleh berkali-kali)
$PG=$role==='satpam'?$SHP+['pulang'=>'Pulang']:$SMG+$LAP;
$ALLOW=array_merge(['home'],array_keys($PG),['izin','rekap']);
$p=in_array($_GET['p']??'home',$ALLOW)?($_GET['p']??'home'):'home';

// data presensi hari ini
$q=db()->prepare('SELECT * FROM absensi WHERE user_id=? AND tanggal=? ORDER BY jam');$q->execute([$u['id'],$tgl]);
$ada=[];$izn=null;$izns=[];$izShift=[];
$hr=$q->fetchAll();
foreach($hr as $r){if($r['jenis']==='Hadir'&&!isset($ada[$r['sesi']]))$ada[$r['sesi']]=$r;elseif($r['jenis']==='Izin'){$izns[]=$r;$izn=$izn??$r;if($r['sesi'])$izShift[$r['sesi']]=$r;}}
$shift=null;foreach(SHIFT as $n=>$_)if(isset($ada[$n])&&!$shift)$shift=$n;
$sesiAwal=SESI_MAGANG[0]??null;$sesiAkhir=SESI_MAGANG?SESI_MAGANG[count(SESI_MAGANG)-1]:null;   // sesi pertama & terakhir anak magang (ditampilkan di kartu jam)
$jamMasuk=$role==='satpam'?($shift?$ada[$shift]:null):($sesiAwal?($ada[$sesiAwal]??null):null);
$jamPulang=$role==='satpam'?($ada['Pulang']??null):($sesiAkhir?($ada[$sesiAkhir]??null):null);
$hm=fn($r)=>$r?substr($r['jam'],0,5):null;

// ===== simpan =====
if($_SERVER['REQUEST_METHOD']==='POST'){
  cek();
  if($p==='izin'){
    $ket=trim($_POST['alasan']??'');$sat=$role==='satpam';$sh=$sat?($_POST['shift']??''):null;
    if($sat&&!isset(SHIFT[$sh]))$err='Pilih shift yang tidak Anda hadiri (pagi / sore / malam).';
    elseif($sat&&isset($izShift[$sh]))$err='Anda sudah mengajukan ijin untuk '.$sh.' hari ini.';
    elseif($sat&&$shift===$sh)$err='Anda sudah absen masuk di '.$sh.', tidak bisa mengajukan ijin untuk shift itu.';
    elseif($m=cekTeks($ket,'Alasan tidak masuk'))$err=$m;
    elseif(!$sat&&$izn)$err='Anda sudah mengajukan ijin tidak masuk hari ini.';
    else{
      db()->prepare("INSERT INTO absensi(user_id,tanggal,jam,jenis,sesi,jenis_izin,keterangan,izin_terlambat,telat,approval) VALUES(?,?,?,'Izin',?,'Tidak Masuk',?,'',0,'pending')")->execute([$u['id'],$tgl,date('H:i:s'),$sh,$ket]);
      $_SESSION['ok']='📨 Ijin tidak masuk'.($sh?' '.$sh:'').' dikirim ke admin.';header('Location: user.php');exit;}
  }elseif(isset($PG[$p])){
    $sesi=$PG[$p];$lat=$_POST['lat']??'';$lng=$_POST['lng']??'';$tl=trim($_POST['alasan']??'');
    $lapor=isset($LAP[$p]);   // laporan keluar / masuk: tidak dibatasi sekali sehari
    $lapMasuk=$p==='l_masuk'; // laporan masuk: TANPA form (tanpa alasan), cukup lokasi + tombol simpan
    if(!$lapor&&isset($ada[$sesi]))$err='Presensi ini sudah tercatat hari ini.';
    elseif(isset(SHIFT[$sesi])&&$shift)$err='Anda sudah masuk '.$shift.' hari ini.';
    elseif($sesi==='Pulang'&&$role==='satpam'&&!$shift)$err='Absen masuk shift terlebih dahulu.';
    elseif(!is_numeric($lat)||!is_numeric($lng))$err='Lokasi belum terdeteksi. Izinkan akses lokasi lalu coba lagi.';
    elseif($lapor&&!$lapMasuk&&($m=cekTeks($tl,'Alasan izin keluar')))$err=$m;
    elseif($lapMasuk){   // laporan masuk: tanpa form, tanpa persetujuan — hanya mencatat jam & lokasi kembali ke kantor
      db()->prepare("INSERT INTO absensi(user_id,tanggal,jam,jenis,sesi,jenis_izin,keterangan,izin_terlambat,lat,lng,telat,approval) VALUES(?,?,?,'Hadir',?,NULL,'','',?,?,0,'none')")
        ->execute([$u['id'],$tgl,date('H:i:s'),$sesi,$lat,$lng]);
      $_SESSION['ok']='✅ '.$sesi.' tercatat pukul '.date('H:i');header('Location: user.php');exit;}
    elseif($lapor){   // izin keluar di jam kerja: alasan + tagging lokasi, dikirim ke halaman pengajuan admin, status kehadiran tidak berubah
      db()->prepare("INSERT INTO absensi(user_id,tanggal,jam,jenis,sesi,jenis_izin,keterangan,izin_terlambat,lat,lng,telat,approval) VALUES(?,?,?,'Hadir',?,NULL,'',?,?,?,0,'pending')")
        ->execute([$u['id'],$tgl,date('H:i:s'),$sesi,$tl,$lat,$lng]);
      $_SESSION['ok']='✅ '.$sesi.' tersimpan pukul '.date('H:i').' · dikirim ke admin';header('Location: user.php');exit;}
    else{
      [$k,$v]=aturan($sesi,$shift,$tgl);$telat=telatKind($k,$v,date('H:i'))?1:0;
      $jr=jarakM((float)$lat,(float)$lng,KANTOR['lat'],KANTOR['lng']);$luar=$jr>RADIUS_M;
      if(($telat||$luar)&&($m=cekTeks($tl,'Keterangan')))$err=($luar?'📍 Anda di luar lokasi (±'.round($jr).' m dari '.KANTOR['nama'].'). ':'⏰ Anda tidak sesuai jadwal. ').$m;
      else{
        if(!$telat&&!$luar)$tl='';
        $ap=$tl!==''?'pending':'none';
        $tSimpan=(shiftLewatMalam($sesi)&&date('H:i')<SHIFT[$sesi][1])?date('Y-m-d',strtotime('-1 day')):$tgl;   // masuk dini hari pada shift yang melewati tengah malam = shift yang dimulai kemarin
        db()->prepare("INSERT INTO absensi(user_id,tanggal,jam,jenis,sesi,jenis_izin,keterangan,izin_terlambat,lat,lng,telat,approval) VALUES(?,?,?,'Hadir',?,NULL,'',?,?,?,?,?)")
          ->execute([$u['id'],$tSimpan,date('H:i:s'),$sesi,$tl,$lat,$lng,$telat,$ap]);
        $_SESSION['ok']='✅ '.(isset(SHIFT[$sesi])?'Masuk '.$sesi:lblSesi($sesi)).' tersimpan pukul '.date('H:i').($ap==='pending'?' · pengajuan dikirim ke admin':'');
        header('Location: user.php');exit;}
    }
  }
}

// ===== komponen =====
function mc($href,$ic,$t,$sub,$c,$done=false,$off=false){
  $in='<span class="ic">'.$ic.'</span><b>'.e($t).'</b><span class="sub">'.$sub.'</span>'.($done?'<span class="tick">✓</span>':'');
  return $off?'<div class="mc off" style="--c:'.$c.'">'.$in.'</div>':'<a class="mc" style="--c:'.$c.'" href="'.e($href).'">'.$in.'</a>';}
function tile($ic,$l,$v){return '<div class="tile"><span>'.$ic.'</span><small>'.$l.'</small><b class="'.($v?'':'mu').'">'.($v?:'--:--').'</b></div>';}

$judulSesi=[];foreach($PG as $k=>$n)$judulSesi[$k]=isset(SHIFT[$n])?$n:lblSesi($n);   // 'Shift Pagi' / 'Jam Masuk' / 'Apel Pagi' / ...
$judul=(['home'=>'Presensi','izin'=>'Ijin Tidak Masuk','rekap'=>'Rekap']+$judulSesi)[$p];
head($judul,$u,$p==='home'?null:'user.php');

// ===== DASHBOARD =====
if($p==='home'){
  echo '<div class="card hero"><div class="row between nowrap"><div><div class="clock" id="clk">'.date('H:i:s').' WIB</div><div class="mu">📅 '.tglId(date('Y-m-d'),true).' · Pontianak</div></div><div class="mas">'.($role==='satpam'?'💂':'🎓').'</div></div>
  <div class="tiles">'.($role==='satpam'?tile('🟢','Jam Masuk',$hm($jamMasuk)).tile('🔴','Jam Pulang',$hm($jamPulang)):($sesiAwal?tile('🟢',e(lblSesi($sesiAwal)),$hm($jamMasuk)):'').($sesiAkhir&&$sesiAkhir!==$sesiAwal?tile('🔴',e(lblSesi($sesiAkhir)),$hm($jamPulang)):'')).'</div>';
  if($role==='satpam'&&$shift)echo '<p class="mu c" style="margin:10px 0 0">'.SHIFT[$shift][2].' '.$shift.' · '.SHIFT[$shift][0].' – '.SHIFT[$shift][1].'</p>';
  echo '</div>';
  foreach($hr as $z){   // status pengajuan hari ini — selalu mengikuti keputusan admin
    if($z['jenis']==='Tidak Hadir'){echo '<div class="notice no">❌ Anda dicatat <b>Tidak Hadir</b> oleh admin'.($z['keterangan']?': '.e($z['keterangan']):'').'</div>';continue;}
    if($z['approval']==='none')continue;
    echo '<div class="notice '.['pending'=>'wa','approved'=>'ok','rejected'=>'no'][$z['approval']].'">📨 '.e(labelPengajuan($z)).(in_array($z['sesi'],SESI_LAPORAN,true)?'':' · '.substr($z['jam'],0,5)).': '.chipAp($z['approval']).'</div>';}
  echo '<h3 class="sect">Menu</h3><div class="menu">';
  if($role==='satpam'){
    $warna=['#2fa36b','#e0892f','#5b6bd6','#0e9aa7','#d6568a','#8a6d3b','#4f8a8b','#7c5cd6'];$wi=0;
    foreach($SHP as $k=>$n){
      $on=$shift===$n;$kunci=$warna[$wi++%count($warna)];
      $sub=str_replace(':','.',SHIFT[$n][0]).' – '.str_replace(':','.',SHIFT[$n][1]);
      if(isset($izShift[$n])&&!$on)$sub='📝 Ijin '.['pending'=>'menunggu','approved'=>'disetujui','rejected'=>'ditolak'][$izShift[$n]['approval']];
      echo mc('user.php?p='.$k,SHIFT[$n][2],$n,$sub,$kunci,$on,(bool)$shift&&!$on);}
    echo mc('user.php?p=pulang','🏠','Jam Pulang',$jamPulang?'Tercatat '.$hm($jamPulang):'Selesai shift','#e05260',(bool)$jamPulang,!$shift&&!$jamPulang);
  }else{
    // urutan menu: Jam Masuk, Jam Pulang, Keluar Istirahat, Masuk Istirahat, (sesi tambahan), Laporan Keluar, Laporan Masuk, Ijin, Rekap
    $dasar=array_values(array_filter(['Masuk Pagi','Pulang','Keluar Istirahat','Masuk Siang'],fn($n)=>isset(MAGANG[$n])));
    $urut=array_merge($dasar,array_values(array_diff(SESI_MAGANG,$dasar)));
    $warna=['#2fa36b','#e05260','#3978d4','#e5a13a','#0e9aa7','#d6568a','#8a6d3b','#4f8a8b'];$wi=0;
    foreach($urut as $n){[$j,$t]=MAGANG[$n];$cat=$ada[$n]??null;$k='m_'.slugShift($n);   // satu kartu per sesi presensi (sesuai pengaturan admin)
      echo mc('user.php?p='.$k,ikonSesi($n),lblSesi($n),$cat?'Tercatat '.$hm($cat):($t==='batas'?'Batas ':'Mulai ').jamSesi($n,$tgl).(hariJumat($tgl)&&isset(MAGANG_JUMAT[$n])?' · Jumat':''),$warna[$wi++%count($warna)],(bool)$cat);}
    foreach($LAP as $k=>$n){$jml=count(array_filter($hr,fn($z)=>$z['sesi']===$n));   // laporan bisa dipakai berkali-kali
      echo mc('user.php?p='.$k,ikonSesi($n),$n,$jml?$jml.'× hari ini · bisa lagi':($n==='Laporan Keluar'?'Izin keluar di jam kerja':'Kembali ke kantor · tanpa form'),$n==='Laporan Keluar'?'#0e9aa7':'#d6568a');}
  }
  echo mc('user.php?p=izin','📝','Ijin Tidak Masuk',$izns?count($izns).' pengajuan hari ini':($role==='satpam'?'Pilih shift & alasan':'Sakit / berhalangan'),'#e5a13a',(bool)$izns).mc('user.php?p=rekap','📊','Rekap','Riwayat & pengajuan','#7c5cd6').'</div>';

// ===== IJIN TIDAK MASUK =====
}elseif($p==='izin'){
  echo '<div class="card"><h2>📝 Ijin Tidak Masuk</h2>';
  if($err)echo '<p class="err">'.e($err).'</p>';
  foreach($izns as $z)echo '<div class="notice wa">Ijin'.($z['sesi']?' <b>'.e($z['sesi']).'</b>':'').' sudah diajukan: '.chipAp($z['approval']).'<br><span class="mu">'.e($z['keterangan']).'</span></div>';
  if($role!=='satpam'&&$izn)echo '</div>';
  else{
    echo '<form method="post">'.csrf();
    if($role==='satpam'){
      echo '<label>Anda tidak masuk di shift</label>';
      foreach(SHIFT as $n=>[$m,$s2,$ic]){
        $off=isset($izShift[$n])?'Sudah diajukan':($shift===$n?'Sudah absen masuk':'');
        echo '<label class="sopt"><input type="radio" name="shift" value="'.e($n).'" required'.($off?' disabled':'').'><span><i>'.$ic.'</i><b>'.e($n).'</b><small>'.str_replace(':','.',$m).' – '.str_replace(':','.',$s2).($off?' · '.$off:'').'</small></span></label>';}
    }
    echo '<label>Keterangan / alasan tidak masuk</label><textarea name="alasan" rows="3" maxlength="'.MAKS_ALASAN.'" required data-max="'.MAKS_IZIN.'" placeholder="Alasan singkat (maks. '.MAKS_IZIN.' karakter, spasi tidak dihitung)"></textarea><div class="cnt"><span data-cc>0</span>/'.MAKS_IZIN.' karakter (spasi tidak dihitung)</div><button class="big">💾 Simpan</button></form><p class="mu">Pengajuan akan dikirim ke admin untuk disetujui. Jika ditolak, status menjadi Tidak Hadir.</p></div>';}

// ===== REKAP =====
}elseif($p==='rekap'){
  $tg=tglOk($_GET['tgl']??'',$tgl);$bln=bulanOk($_GET['bln']??substr($tg,0,7));
  $s=db()->prepare("SELECT * FROM absensi WHERE user_id=? AND tanggal=? AND approval<>'none' ORDER BY jam");$s->execute([$u['id'],$tg]);$pj=$s->fetchAll();
  echo '<div class="card"><h2>📨 Monitoring Pengajuan</h2>'.navTanggal($tg,'tgl',['p'=>'rekap','bln'=>$bln]).'<p class="mu" style="margin-top:0">'.($tg===date('Y-m-d')?'Hari ini':tglId($tg,true)).' · pengajuan kepada admin</p>';
  if(!$pj)echo '<div class="empty"><div class="mas">📭</div><p class="mu">Tidak ada pengajuan pada tanggal ini.</p></div>';
  else{echo '<div class="tw"><table class="pt"><tr><th>Jam</th><th>Pengajuan</th><th>Keterangan</th><th>Hasil</th></tr>';
    foreach($pj as $r)echo '<tr><td>'.substr($r['jam'],0,5).'</td><td><b>'.e(labelPengajuan($r)).'</b></td><td>'.e($r['jenis']==='Izin'?$r['keterangan']:$r['izin_terlambat']).'</td><td>'.chipAp($r['approval']).'</td></tr>';
    echo '</table></div>';}
  echo '</div>';

  $tl=telatUser($u['id'],$bln);   // yang sudah disetujui admin tidak dihitung terlambat
  $kol=$role==='satpam'?array_merge(array_combine(array_keys(SHIFT),array_map(fn($n)=>'Terlambat '.$n,array_keys(SHIFT))),['Pulang'=>'Pulang Awal'])
     :array_combine(SESI_MAGANG,array_map('kolTelat',SESI_MAGANG));
  echo '<div class="card"><h2>⏰ Total Riwayat Keterlambatan</h2><div class="tw"><table class="pt tl"><tr><th>Bulan</th>';
  foreach($kol as $t)echo '<th>'.e($t).'</th>';
  echo '</tr><tr><td><b>'.e(namaBulan($bln)).'</b></td>';
  foreach($kol as $sk=>$t){$n=(int)($tl[$sk]??0);echo '<td><span class="b '.($n?'no':'ok').'">'.$n.'×</span></td>';}
  echo '</tr></table></div><p class="mu" style="margin:8px 0 0">Keterlambatan yang sudah disetujui admin tidak dihitung.</p></div>';

  $s=db()->prepare('SELECT * FROM absensi WHERE user_id=? AND tanggal BETWEEN ? AND ? ORDER BY tanggal DESC,jam');$s->execute([$u['id'],$bln.'-01',date('Y-m-t',strtotime($bln.'-01'))]);
  echo '<div class="card"><h2>🗓️ Rekap Bulanan — '.e(namaBulan($bln)).'</h2>'.navBulan($bln,['p'=>'rekap','tgl'=>$tg]);riwayatHarian($s->fetchAll(),$role);echo '<p class="mu" style="margin:8px 0 0">Geser tabel ke samping untuk melihat semua kolom.</p></div>';

// ===== HALAMAN LOKASI + SIMPAN (masuk / pulang / istirahat / shift) =====
}else{
  $sesi=$PG[$p];
  [$k,$v]=isset($LAP[$p])?[$p==='l_masuk'?'lapm':'lap','']:aturan($sesi,$shift,$tgl);   // lap = izin keluar (wajib alasan) | lapm = laporan masuk (tanpa form)
  $info=$k==='lapm'?'Kembali ke kantor · tanpa form · boleh berkali-kali':($k==='lap'?'Izin keluar di jam kerja · boleh berkali-kali':($k==='shiftin'?'Shift mulai '.$v.' · selesai '.SHIFT[$sesi][1]:($k==='shiftout'?$shift.' selesai '.SHIFT[$shift][1]:($k==='batas'?'Batas ':'Mulai ').$v.($role==='magang'&&hariJumat($tgl)&&isset(MAGANG_JUMAT[$sesi])?' (jadwal Jumat)':''))));
  $blok=isset($LAP[$p])?'':(isset($ada[$sesi])?'Sudah tercatat pukul '.$hm($ada[$sesi]).'.':(isset(SHIFT[$sesi])&&$shift?'Anda sudah masuk '.$shift.' ('.$hm($ada[$shift]).').':($sesi==='Pulang'&&$role==='satpam'&&!$shift?'Absen masuk shift terlebih dahulu.':'')));
  echo '<div class="card"><h2>'.(isset(SHIFT[$sesi])?SHIFT[$sesi][2]:ikonSesi($sesi)).' '.e($judul).'</h2><p class="mu">'.e($info).' · sekarang <b id="clk">'.date('H:i:s').' WIB</b></p>';
  if($err)echo '<p class="err">'.e($err).'</p>';
  if($blok)echo '<div class="notice ok">✅ '.e($blok).'</div></div>';
  else{
    echo '<form method="post" id="fm" data-k="'.e($k).'" data-v="'.e($v).'" data-aw="'.SHIFT_AWAL.'" data-clat="'.KANTOR['lat'].'" data-clng="'.KANTOR['lng'].'" data-r="'.RADIUS_M.'" data-n="'.e(KANTOR['nama']).'">'.csrf().'
    <div id="map"></div>
    <div class="geo wait" id="geoinfo">📡 Mengambil lokasi Anda…</div>
    <input type="hidden" name="lat" id="lat"><input type="hidden" name="lng" id="lng">
    <div id="lap" hidden><label id="lapl">Keterangan</label><textarea name="alasan" rows="3" maxlength="'.MAKS_ALASAN.'" data-max="'.MAKS_IZIN.'" placeholder="Alasan singkat (maks. '.MAKS_IZIN.' karakter, spasi tidak dihitung)"></textarea><div class="cnt"><span data-cc>0</span>/'.MAKS_IZIN.' karakter (spasi tidak dihitung)</div><p class="mu">Keterangan ini diajukan ke admin untuk disetujui atau ditolak.</p></div>
    <button class="big" id="sv" disabled>💾 Simpan</button></form></div>
';
    if(isset($LAP[$p])){   // riwayat laporan (keluar & masuk) hari ini tetap tampil, bisa lebih dari satu
      $rl=array_values(array_filter($hr,fn($z)=>in_array($z['sesi'],SESI_LAPORAN,true)));
      echo '<div class="card"><h2>🕘 Riwayat Laporan Hari Ini <span class="mu">('.count($rl).')</span></h2>';
      if(!$rl)echo '<p class="mu" style="margin:0">Belum ada laporan hari ini.</p>';
      foreach($rl as $z)echo '<div class="hrow"><b>'.($z['sesi']==='Laporan Keluar'?'🚪 Keluar ':'↩️ Masuk ').substr($z['jam'],0,5).'</b>'.($z['sesi']==='Laporan Keluar'?'<span class="mu">'.e($z['izin_terlambat']).'</span>'.chipAp($z['approval']):'<span class="mu">tercatat</span>').linkPeta($z).'</div>';
      echo '</div>';}
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css"><script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>';
  }
}
if(in_array($p,['home','rekap']))$LIVE=sigData($u['id']);   // muat ulang otomatis bila admin mengubah data
foot();
