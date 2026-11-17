<?php
// Unduh data presensi bulanan (Excel .xlsx). Hanya untuk admin. Setiap unduhan = 1 file dengan 1 sheet:
//   rekap_magang  : rekap bulanan anak magang          harian_magang : rekap harian sebulan anak magang
//   rekap_satpam  : rekap bulanan satpam (per shift)   harian_satpam : rekap harian sebulan satpam
require 'config.php'; require 'xlsx.php'; $u=auth('admin');

$JENIS=['rekap_magang'=>['magang','Rekap Magang','rekap-bulanan-magang'],'rekap_satpam'=>['satpam','Rekap Satpam','rekap-bulanan-satpam'],
        'harian_magang'=>['magang','Harian Magang','rekap-harian-magang'],'harian_satpam'=>['satpam','Harian Satpam','rekap-harian-satpam']];
$jenis=$_GET['jenis']??'';
if(!isset($JENIS[$jenis])){http_response_code(400);exit('Jenis unduhan tidak valid.');}
[$role,$namaSheet,$namaFile]=$JENIS[$jenis];

$bln=bulanOk($_GET['bln']??'');$aw=$bln.'-01';$ak=date('Y-m-t',strtotime($aw));
$us=db()->prepare('SELECT id,nama,username,role,jabatan FROM users WHERE role=? ORDER BY nama');$us->execute([$role]);
$user=[];foreach($us->fetchAll() as $x)$user[$x['id']]=$x;
$g=[];$all=[];                                              // $g[tanggal][user_id] = [record...]  |  $all[user_id] = [record...]
if($user){$in=implode(',',array_map('intval',array_keys($user)));
  $q=db()->prepare("SELECT * FROM absensi WHERE user_id IN($in) AND tanggal BETWEEN ? AND ? ORDER BY tanggal,jam");$q->execute([$aw,$ak]);
  foreach($q->fetchAll() as $x){$g[$x['tanggal']][$x['user_id']][]=$x;$all[$x['user_id']][]=$x;}}
ksort($g);
$HASIL=['pending'=>'Menunggu','approved'=>'Disetujui','rejected'=>'Ditolak','none'=>'Tanpa pengajuan'];
$T=fn($t)=>['v'=>$t,'st'=>'tengah'];
$JUDUL_KET='Keterangan Hasil Laporan (Ijin / Keterlambatan)';

// ---- helper
$jam=function($rs,$sesi){foreach($rs as $x)if($x['jenis']==='Hadir'&&$x['sesi']===$sesi)return substr($x['jam'],0,5);return '';};
$statusHari=function($rs){$k=kategori(harian($rs));return $k==='izin'?'Ijin':($k==='tidak'?'Tidak Hadir':($k==='belum'?'-':'Hadir'));};
// Satu baris laporan untuk satu catatan presensi: ijin / tidak hadir / terlambat / lebih awal / di luar lokasi + alasan + hasil keputusan admin.
// Presensi biasa (tepat waktu, di dalam lokasi, tanpa laporan) tidak menghasilkan baris. $tgl: awali dengan tanggal (untuk rekap sebulan).
$laporan=function($x,$tgl=false)use($HASIL){
  $isi=trim((string)($x['jenis']==='Izin'?$x['keterangan']:($x['jenis']==='Tidak Hadir'?$x['keterangan']:$x['izin_terlambat'])));
  if($x['jenis']==='Hadir'&&$x['approval']==='none'&&!$x['telat']&&!luar($x)&&$isi==='')return '';
  $judul=$x['jenis']==='Tidak Hadir'?'Tidak Hadir':labelPengajuan($x);
  $hasil=$x['jenis']==='Tidak Hadir'?'':' → '.($HASIL[$x['approval']]??'');
  return ($tgl?date('d/m',strtotime($x['tanggal'])).' ':'').'['.$judul.'] '.($isi!==''?$isi:'(tanpa keterangan)').$hasil;};
// Seluruh laporan dalam satu sel (satu laporan per baris di dalam sel)
$ketr=function($rs,$tgl=false)use($laporan){return implode("\n",array_values(array_filter(array_map(fn($x)=>$laporan($x,$tgl),$rs),fn($l)=>$l!=='')));};
$urut=function($per)use($user){uksort($per,fn($a,$b)=>strcasecmp($user[$a]['nama'],$user[$b]['nama']));return $per;};   // urut nama dalam satu tanggal
$K=fn($t)=>['v'=>$t,'st'=>'wrap'];

if($jenis==='rekap_magang'){
  // Hadir/Ijin/Tidak Hadir per HARI; keterlambatan per kejadian (yang sudah disetujui admin tidak dihitung). Kolom per sesi mengikuti Pengaturan.
  $judulTotal=function($n,$t){$b=['Masuk Pagi'=>['batas','Total Keterlambatan Jam Masuk'],'Keluar Istirahat'=>['min','Total Keluar Lebih Awal Jam Istirahat'],'Masuk Siang'=>['batas','Total Keterlambatan Masuk Jam Istirahat'],'Pulang'=>['min','Total Pulang Lebih Awal']];
    if(isset($b[$n])&&$b[$n][0]===$t)return $b[$n][1];return $t==='batas'?'Total Keterlambatan '.$n:'Total '.$n.' Lebih Awal';};
  $hd=['Nama','Jabatan','Hadir','Ijin','Tidak Hadir'];$lb=[28,24,9,9,13];
  foreach(MAGANG as $n=>[$j,$t]){$hd[]=$judulTotal($n,$t);$lb[]=22;}
  $hd[]='Total Di Luar Lokasi';$lb[]=16;
  $rows=[$hd];
  foreach(rekapBulan($bln,'magang') as $x){$r=[$x['nama'],(string)$x['jabatan'],$x['hadir'],$x['izin'],$x['tidak']];
    foreach(MAGANG as $n=>$_)$r[]=(int)($x['per'][$n]??0);
    $r[]=$x['lu'];$rows[]=$r;}
  $sheet=['nama'=>$namaSheet,'lebar'=>$lb,'baris'=>$rows];

}elseif($jenis==='rekap_satpam'){
  $rows=[['Nama','Shift','Total Keterlambatan Jam Masuk','Total Pulang Lebih Awal','Total Di Luar Lokasi']];
  foreach($user as $id=>$x){$per=[];$sh=[];                  // $sh[tanggal] = shift yang diambil hari itu
    foreach($all[$id]??[] as $r)if($r['jenis']==='Hadir'&&isset(SHIFT[$r['sesi']])){$sh[$r['tanggal']]=$r['sesi'];$per[$r['sesi']]=$per[$r['sesi']]??['t'=>0,'p'=>0,'l'=>0];}
    foreach($all[$id]??[] as $r){if($r['jenis']!=='Hadir'||$r['approval']==='approved')continue;
      $s=isset(SHIFT[$r['sesi']])?$r['sesi']:($r['sesi']==='Pulang'?($sh[$r['tanggal']]??null):null);   // jam pulang ikut shift pada hari itu
      if(!$s||!isset($per[$s]))continue;
      if(isset(SHIFT[$r['sesi']])&&$r['telat'])$per[$s]['t']++;
      if($r['sesi']==='Pulang'&&$r['telat'])$per[$s]['p']++;
      if(luar($r))$per[$s]['l']++;}
    $bar=[];
    if(!$per)$bar[]=[$x['nama'],$T('-'),0,0,0];
    foreach(array_keys(SHIFT) as $s)if(isset($per[$s]))$bar[]=[$x['nama'],$T($s),$per[$s]['t'],$per[$s]['p'],$per[$s]['l']];
    foreach($bar as $b)$rows[]=$b;}
  $sheet=['nama'=>$namaSheet,'lebar'=>[28,16,28,22,18],'baris'=>$rows];

}elseif($jenis==='harian_magang'){
  // Urutan kolom: Tanggal, Nama, Jabatan, Kehadiran, Jam Masuk, Jam Pulang, Jam Keluar Istirahat, Jam Masuk Istirahat, (sesi tambahan bila ada),
  //               Jam Laporan Keluar, Jam Laporan Masuk, Keterangan Hasil Laporan (Ijin / Keterlambatan), Laporan Keluar di Jam Kerja
  $jd=['Masuk Pagi'=>'Jam Masuk','Pulang'=>'Jam Pulang','Keluar Istirahat'=>'Jam Keluar Istirahat','Masuk Siang'=>'Jam Masuk Istirahat'];
  $dasar=array_values(array_filter(array_keys($jd),fn($n)=>isset(MAGANG[$n])));
  $sesiUrut=array_merge($dasar,array_values(array_diff(SESI_MAGANG,$dasar)));
  $hd=['Tanggal','Nama','Jabatan','Kehadiran'];$lb=[12,26,22,14];
  foreach($sesiUrut as $n){$hd[]=$jd[$n]??('Jam '.$n);$lb[]=16;}
  $hd[]='Jam Laporan Keluar';$lb[]=18;$hd[]='Jam Laporan Masuk';$lb[]=18;
  $hd[]=$JUDUL_KET;$lb[]=60;$hd[]='Laporan Keluar di Jam Kerja';$lb[]=60;$rows=[$hd];
  // semua jam laporan (bisa lebih dari satu per hari), satu jam per baris di dalam sel
  $jamLap=function($rs,$sesi){$o=[];foreach($rs as $x)if($x['jenis']==='Hadir'&&$x['sesi']===$sesi)$o[]=substr($x['jam'],0,5);return implode("\n",$o);};
  // Keterangan Hasil Laporan: hanya ijin / keterlambatan / di luar lokasi (laporan keluar-masuk punya kolom sendiri)
  $rsTanpaLap=fn($rs)=>array_values(array_filter($rs,fn($x)=>!in_array($x['sesi']??'',SESI_LAPORAN,true)));
  // Laporan Keluar di Jam Kerja: jam + alasan + hasil keputusan admin
  $lapKeluar=function($rs)use($HASIL){$o=[];foreach($rs as $x)if($x['jenis']==='Hadir'&&$x['sesi']==='Laporan Keluar'){
      $a=trim((string)$x['izin_terlambat']);$o[]=substr($x['jam'],0,5).' '.($a!==''?$a:'(tanpa keterangan)').' → '.($HASIL[$x['approval']]??'');}
    return implode("\n",$o);};
  foreach($g as $tg=>$per)foreach($urut($per) as $id=>$rs){
    $r=[['d'=>$tg,'st'=>'tgl'],$user[$id]['nama'],(string)$user[$id]['jabatan'],$T($statusHari($rs))];
    foreach($sesiUrut as $n)$r[]=$T($jam($rs,$n));
    $r[]=$K($jamLap($rs,'Laporan Keluar'));$r[]=$K($jamLap($rs,'Laporan Masuk'));
    $r[]=$K($ketr($rsTanpaLap($rs)));$r[]=$K($lapKeluar($rs));$rows[]=$r;}
  $sheet=['nama'=>$namaSheet,'lebar'=>$lb,'baris'=>$rows];

}else{ // harian_satpam
  $rows=[['Tanggal','Nama','Kehadiran','Shift','Jam Masuk','Jam Keluar',$JUDUL_KET]];
  foreach($g as $tg=>$per)foreach($urut($per) as $id=>$rs){
    $shf=null;foreach($rs as $x)if($x['jenis']==='Hadir'&&isset(SHIFT[$x['sesi']])){$shf=$x['sesi'];break;}
    $jenisShift=$shf?:implode(', ',array_unique(array_map(fn($x)=>(string)$x['sesi'],array_filter($rs,fn($x)=>$x['jenis']==='Izin'&&$x['sesi']))));   // ijin 1 shift: tampilkan shift yang diijinkan
    $rows[]=[['d'=>$tg,'st'=>'tgl'],$user[$id]['nama'],$T($statusHari($rs)),$T($jenisShift),$T($shf?$jam($rs,$shf):''),$T($jam($rs,'Pulang')),$K($ketr($rs))];}
  $sheet=['nama'=>$namaSheet,'lebar'=>[12,26,14,14,11,11,70],'baris'=>$rows];
}

$sheets=[$sheet];
$file=$namaFile.'-'.$bln;
$tmp=tempnam(sys_get_temp_dir(),'xl');
if(xlsxTulis($sheets,$tmp)){
  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment; filename="'.$file.'.xlsx"');header('Content-Length: '.filesize($tmp));header('Cache-Control: no-store');
  readfile($tmp);unlink($tmp);exit;}
@unlink($tmp);
// Cadangan bila ekstensi zip PHP tidak aktif: CSV
header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="'.$file.'.csv"');
echo "\xEF\xBB\xBF";$f=fopen('php://output','w');
foreach($sheet['baris'] as $row)fputcsv($f,array_map(function($c){$c=is_array($c)?($c['d']??$c['v']??''):$c;$c=str_replace(["\r\n","\n"],' | ',(string)$c);return $c!==''&&strpbrk($c[0],'=+-@')?"'".$c:$c;},$row),';');
