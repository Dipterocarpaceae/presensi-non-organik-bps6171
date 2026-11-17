<?php
// Penulis .xlsx sederhana (tanpa library) — butuh ekstensi PHP "zip" (aktif di Laragon).
// $sheets = [ ['nama'=>'Ringkasan','lebar'=>[6,24,...],'baris'=>[ [sel,sel,...], ... ]], ... ]
// baris pertama = judul kolom. sel = teks/angka, atau ['v'=>nilai,'st'=>'tgl|tengah|wrap|tebal|angka'] ; tanggal: ['d'=>'2026-11-12','st'=>'tgl']
function xlsxKol($i){$s='';for($i++;$i>0;$i=intdiv($i-1,26))$s=chr(65+($i-1)%26).$s;return $s;}
function xlsxEsc($t){$t=preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u','',(string)$t);return htmlspecialchars($t,ENT_XML1|ENT_QUOTES,'UTF-8');}
function xlsxSheet($sh){
  $ST=['teks'=>2,'tgl'=>3,'tengah'=>4,'wrap'=>5,'tebal'=>6,'angka'=>7];$rows=$sh['baris'];$n=count($rows);$nc=0;foreach($rows as $r)$nc=max($nc,count($r));
  $x='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetFormatPr defaultRowHeight="15"/><cols>';
  foreach(($sh['lebar']??[]) as $i=>$w)$x.='<col min="'.($i+1).'" max="'.($i+1).'" width="'.(float)$w.'" customWidth="1"/>';
  $x.='</cols><sheetData>';
  foreach($rows as $ri=>$r){
    // tinggi baris: hitung jumlah baris tampilan sel "wrap" (pemisah baris + pembungkusan sesuai lebar kolom) agar teks tidak terpotong / meluber di aplikasi apa pun
    $ln=1;if($ri>0)foreach($r as $ci=>$c)if(is_array($c)&&($c['st']??'')==='wrap'){$w=max(8,(float)(($sh['lebar']??[])[$ci]??30));$n=0;
      foreach(explode("\n",(string)($c['v']??'')) as $b)$n+=max(1,(int)ceil(mb_strlen($b)*1.1/$w));$ln=max($ln,$n);}
    $x.='<row r="'.($ri+1).'"'.($ri===0?' ht="30" customHeight="1"':($ln>1?' ht="'.min(409,15*$ln+2).'" customHeight="1"':'')).'>';
    foreach($r as $ci=>$c){$ref=xlsxKol($ci).($ri+1);$st=$ri===0?1:2;
      if(is_array($c)){$st=$ST[$c['st']??'teks']??2;if($ri===0)$st=1;if(isset($c['d'])){$ts=strtotime($c['d'].' 12:00:00 UTC');$c=$ts===false?'':(int)floor($ts/86400)+25569;}else $c=$c['v']??'';}
      elseif(is_int($c)||is_float($c))$st=$ri===0?1:7;
      if($c===''||$c===null)$x.='<c r="'.$ref.'" s="'.$st.'"/>';
      elseif(is_int($c)||is_float($c))$x.='<c r="'.$ref.'" s="'.$st.'"><v>'.$c.'</v></c>';
      else $x.='<c r="'.$ref.'" s="'.$st.'" t="inlineStr"><is><t xml:space="preserve">'.xlsxEsc(mb_substr((string)$c,0,32000)).'</t></is></c>';}
    $x.='</row>';}
  $x.='</sheetData>'.($n>1&&$nc?'<autoFilter ref="A1:'.xlsxKol($nc-1).$n.'"/>':'').'<pageMargins left="0.5" right="0.5" top="0.75" bottom="0.75" header="0.3" footer="0.3"/><pageSetup orientation="landscape"/></worksheet>';
  return $x;}
function xlsxStyles(){
  $b='<border><left style="thin"><color rgb="FFD0D7E2"/></left><right style="thin"><color rgb="FFD0D7E2"/></right><top style="thin"><color rgb="FFD0D7E2"/></top><bottom style="thin"><color rgb="FFD0D7E2"/></bottom><diagonal/></border>';
  $xf=fn($num,$font,$fill,$al)=>'<xf numFmtId="'.$num.'" fontId="'.$font.'" fillId="'.$fill.'" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment '.$al.'/></xf>';
  return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="dd\-mm\-yyyy"/></numFmts>'
   .'<fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
   .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF3978D4"/><bgColor indexed="64"/></patternFill></fill></fills>'
   .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'.$b.'</borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
   .'<cellXfs count="8"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
   .$xf(0,1,2,'horizontal="center" vertical="center" wrapText="1"').$xf(0,0,0,'vertical="top"').$xf(164,0,0,'horizontal="center" vertical="top"').$xf(0,0,0,'horizontal="center" vertical="top"')
   .$xf(0,0,0,'vertical="top" wrapText="1"').$xf(0,2,0,'vertical="top"').$xf(1,0,0,'horizontal="center" vertical="top"').'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';}
// Penulis ZIP sederhana (tanpa ekstensi zip) dengan antarmuka mirip ZipArchive: open / addFromString / close
class XZip{
  private $f=[],$p;
  function open($p,$m=0){$this->p=$p;$this->f=[];return true;}
  function addFromString($n,$d){$this->f[$n]=(string)$d;return true;}
  function close(){
    $o='';$cd='';$n=0;$off=0;$t=getdate();$dt=($t['hours']<<11)|($t['minutes']<<5)|intdiv($t['seconds'],2);$dd=((max(1980,$t['year'])-1980)<<9)|($t['mon']<<5)|$t['mday'];
    foreach($this->f as $nm=>$d){$crc=crc32($d)&0xFFFFFFFF;$len=strlen($d);
      if(function_exists('gzdeflate')){$c=gzdeflate($d);$m=8;}else{$c=$d;$m=0;}
      $cl=strlen($c);$h=pack('VvvvvvVVVvv',0x04034b50,20,0x0800,$m,$dt,$dd,$crc,$cl,$len,strlen($nm),0).$nm;
      $o.=$h.$c;$cd.=pack('VvvvvvvVVVvvvvvVV',0x02014b50,20,20,0x0800,$m,$dt,$dd,$crc,$cl,$len,strlen($nm),0,0,0,0,0,$off).$nm;$off+=strlen($h)+$cl;$n++;}
    return file_put_contents($this->p,$o.$cd.pack('VvvvvVVv',0x06054b50,0,0,$n,$n,strlen($cd),$off,0))!==false;}}
// Tulis file .xlsx ke $path. Mengembalikan false jika ekstensi zip tidak tersedia.
function xlsxTulis($sheets,$path){
  $z=class_exists('ZipArchive')?new ZipArchive:new XZip;if($z->open($path,1|8)!==true)return false;   // ZipArchive::CREATE|OVERWRITE; bila ekstensi zip mati dipakai penulis ZIP bawaan (XZip)
  $ct='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
  $wb='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
  $rel='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
  foreach($sheets as $i=>$sh){$k=$i+1;$ct.='<Override PartName="/xl/worksheets/sheet'.$k.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
    $wb.='<sheet name="'.xlsxEsc(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/','-',$sh['nama']),0,31)).'" sheetId="'.$k.'" r:id="rId'.$k.'"/>';
    $rel.='<Relationship Id="rId'.$k.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$k.'.xml"/>';
    $z->addFromString('xl/worksheets/sheet'.$k.'.xml',xlsxSheet($sh));}
  $k=count($sheets)+1;$rel.='<Relationship Id="rId'.$k.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
  $z->addFromString('[Content_Types].xml',$ct.'</Types>');
  $z->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
  $z->addFromString('xl/workbook.xml',$wb.'</sheets></workbook>');$z->addFromString('xl/_rels/workbook.xml.rels',$rel);$z->addFromString('xl/styles.xml',xlsxStyles());
  return $z->close();}
