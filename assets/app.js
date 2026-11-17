const $=id=>document.getElementById(id);
const clk=$('clk');
if(clk){const f=()=>clk.textContent=new Intl.DateTimeFormat('id-ID',{timeZone:'Asia/Pontianak',hour:'2-digit',minute:'2-digit',second:'2-digit',hourCycle:'h23'}).format(new Date()).replace(/\./g,':')+' WIB';f();setInterval(f,1000)}
const j=$('jenis');
if(j){const s=()=>document.querySelectorAll('[data-j]').forEach(x=>x.hidden=!x.dataset.j.split(',').includes(j.value));j.onchange=s;s()}
// ===== Halaman presensi user: lokasi otomatis + peta + keterangan =====
const fm=$('fm');
if(fm&&fm.dataset.k){
  const d=fm.dataset,clat=+d.clat,clng=+d.clng,R=+d.r,info=$('geoinfo'),lap=$('lap'),ta=fm.elements.alasan,sv=$('sv');
  const jam=()=>new Intl.DateTimeFormat('en-GB',{timeZone:'Asia/Pontianak',hour:'2-digit',minute:'2-digit',hourCycle:'h23'}).format(new Date());
  const mn=h=>+h.slice(0,2)*60+ +h.slice(3,5);
  const lapm=d.k==='lapm',lapor=d.k==='lap'||lapm;   // lap = izin keluar (alasan wajib) | lapm = laporan masuk (TANPA form)
  const telat=()=>{if(lapor)return false;const h=jam(),v=d.v;if(d.k==='batas')return h>v;if(d.k==='min')return h<v;const aw=+d.aw||0;
    if(d.k==='shiftin'){const x=(mn(h)-mn(v)+1440)%1440;return x>0&&x<1440-aw}
    const [s,e]=v.split('|'),L=(mn(e)-mn(s)+1440)%1440||1440,x=(mn(h)-mn(s)+1440)%1440;return x<L||x>=1440-aw};
  const awal=d.k==='min'||d.k==='shiftout';
  const jarak=(a,b)=>{const r=Math.PI/180;return 2*6371000*Math.asin(Math.sqrt(Math.sin((a-clat)*r/2)**2+Math.cos(clat*r)*Math.cos(a*r)*Math.sin((b-clng)*r/2)**2))};
  let pos=null,map=null,me=null,acc=null,fit=false;
  if(window.L){
    map=L.map('map',{zoomControl:true}).setView([clat,clng],17);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OpenStreetMap'}).addTo(map);
    L.circle([clat,clng],{radius:R,color:'#3978D4',weight:2,fillOpacity:.08}).addTo(map).bindPopup('<b>'+d.n+'</b><br>Radius '+R+' m');
    L.circleMarker([clat,clng],{radius:5,color:'#3978D4',fillColor:'#3978D4',fillOpacity:1}).addTo(map);
  }else $('map').innerHTML='<p class="mu" style="padding:16px">Peta tidak bisa dimuat (butuh internet). Koordinat tetap diambil otomatis.</p>';
  function ui(){
    const tel=telat(),m=pos?Math.round(jarak(pos.lat,pos.lng)):null,luar=pos&&m>R,why=[];
    const wkt=lapor?'📝 Laporan':(tel?(awal?'⏰ Lebih awal dari jadwal':'⏰ Terlambat'):'⏰ Tepat waktu');
    if(luar&&!lapor)why.push('di luar lokasi');
    if(d.k==='lap')why.push('izin keluar');
    if(tel)why.push(awal?'lebih awal dari jadwal':'terlambat');
    if(pos){
      info.className='geo '+(why.length&&!lapm?'bad':'good');
      info.innerHTML='<b>📍 '+pos.lat.toFixed(6)+', '+pos.lng.toFixed(6)+'</b><br>'+(lapor?'📍 Lokasi Anda — '+m+' m dari kantor (tercatat sebagai tagging laporan)':(luar?'⚠️ Di luar lokasi — '+m+' m dari kantor (batas '+R+' m)':'✅ Di sekitar lokasi — '+m+' m dari kantor'))+'<br>'+(lapm?'↩️ Tidak perlu mengisi form — langsung tekan Simpan':wkt+(why.length?' — <b>wajib isi keterangan</b>':' — tidak perlu keterangan, langsung tekan Simpan'));}
    const perlu=!!why.length&&!lapm;lap.hidden=!perlu;ta.required=perlu;   // form keterangan HANYA muncul bila terlambat / pulang awal / di luar lokasi
    if(perlu)$('lapl').textContent=d.k==='lap'?'Alasan izin keluar — wajib diisi':'Keterangan ijin ('+why.join(' & ')+') — wajib diisi';
    sv.disabled=!pos;
  }
  const mulai=()=>{
    if(!navigator.geolocation){info.className='geo bad';info.textContent='Perangkat tidak mendukung GPS.';return}
    info.className='geo wait';info.textContent='📡 Mengambil lokasi Anda…';
    navigator.geolocation.watchPosition(p=>{
      pos={lat:p.coords.latitude,lng:p.coords.longitude};$('lat').value=pos.lat.toFixed(7);$('lng').value=pos.lng.toFixed(7);
      if(map){if(me){me.setLatLng([pos.lat,pos.lng]);acc.setLatLng([pos.lat,pos.lng]).setRadius(p.coords.accuracy)}
        else{acc=L.circle([pos.lat,pos.lng],{radius:p.coords.accuracy,color:'#20A477',weight:1,fillOpacity:.1}).addTo(map);me=L.circleMarker([pos.lat,pos.lng],{radius:9,color:'#fff',weight:3,fillColor:'#20A477',fillOpacity:1}).addTo(map).bindPopup('Lokasi Anda saat ini')}
        if(!fit){fit=true;map.fitBounds([[pos.lat,pos.lng],[clat,clng]],{padding:[40,40],maxZoom:18})}}
      ui()},
     ()=>{info.className='geo bad';info.innerHTML='Gagal mengambil lokasi. Izinkan akses lokasi (butuh HTTPS atau localhost). <button type="button" class="g sm" id="retry">Coba lagi</button>';$('retry').onclick=mulai},
     {enableHighAccuracy:true,timeout:20000,maximumAge:5000})};
  fm.onsubmit=e=>{if(!pos){e.preventDefault();return}if(lapm){ta.value='';return}if(ta.required&&!ta.value.trim()){e.preventDefault();ta.focus();alert('Keterangan wajib diisi.')}};
  mulai();   // minta lokasi DULU, agar tidak terhalang error perhitungan tampilan
  try{ui()}catch(e){console.error(e)}setInterval(()=>{try{ui()}catch(e){console.error(e)}},15000);
}
// Peta lokasi (admin)
const mp=$('map');
if(mp&&$('pts')){
  if(!window.L)mp.innerHTML='<p class="mu" style="padding:16px">Peta tidak bisa dimuat (butuh internet). Gunakan tautan 📍 pada daftar di bawah.</p>';
  else{
    const esc=x=>String(x).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const pts=JSON.parse($('pts').textContent||'[]'),map=L.map(mp).setView([+mp.dataset.lat,+mp.dataset.lng],16),lay=[],by={};
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OpenStreetMap'}).addTo(map);
    const k=[+mp.dataset.lat,+mp.dataset.lng];L.circle(k,{radius:+mp.dataset.r,color:'#2563eb',weight:2,fillOpacity:.08}).addTo(map).bindPopup('<b>'+esc(mp.dataset.n)+'</b><br>Radius '+mp.dataset.r+' m');
    L.circleMarker(k,{radius:5,color:'#2563eb',fillColor:'#2563eb',fillOpacity:1}).addTo(map);
    pts.forEach(p=>(by[p.u]=by[p.u]||[]).push(p));
    Object.values(by).forEach(a=>{const c=a[0].c;
      if(a.length>1)L.polyline(a.map(p=>[p.lat,p.lng]),{color:c,weight:2,dashArray:'4 6',opacity:.6}).addTo(map);
      a.forEach((p,i)=>{lay[p.i]=L.circleMarker([p.lat,p.lng],{radius:i===a.length-1?10:6,color:'#fff',weight:2,fillColor:c,fillOpacity:.9}).addTo(map).bindPopup('<b>'+esc(p.n)+'</b><br>'+esc(p.s)+' · '+esc(p.j))})});
    if(pts.length)map.fitBounds(pts.map(p=>[p.lat,p.lng]).concat([k]),{padding:[30,30],maxZoom:17});
    document.querySelectorAll('[data-pt]').forEach(r=>r.onclick=e=>{if(e.target.closest('a'))return;const m=lay[r.dataset.pt];
      if(m){map.setView(m.getLatLng(),17);m.openPopup();mp.scrollIntoView({behavior:'smooth',block:'center'})}});
  }}
document.querySelectorAll('.toast').forEach(t=>setTimeout(()=>t.remove(),2500));

// ===== Form pengguna: tampilkan isian khusus sesuai status (mis. Jabatan untuk anak magang) =====
document.querySelectorAll('select[data-role-toggle]').forEach(sel=>{
  const f=()=>document.querySelectorAll('[data-role]').forEach(x=>{const on=x.dataset.role.split(',').includes(sel.value);x.hidden=!on;x.querySelectorAll('input,select').forEach(i=>i.disabled=!on)});
  sel.onchange=f;f();
});
// ===== Sinkron otomatis: bila data berubah (admin setujui/tolak/ubah/hapus, atau user menyimpan) halaman dimuat ulang =====
const lv=$('live');
if(lv){
  const poll=()=>{
    if(document.hidden)return;const a=document.activeElement;if(a&&/INPUT|TEXTAREA|SELECT/.test(a.tagName))return;
    fetch(location.href,{cache:'no-store',credentials:'same-origin'}).then(r=>{if(r.redirected&&/index\.php/.test(r.url)){location.reload();return ''}return r.ok?r.text():''}).then(t=>{
      const m=t.match(/id="live" data-sig="(\w+)"/);if(m&&m[1]!==lv.dataset.sig)location.reload()}).catch(()=>{});
  };
  setInterval(poll,10000);document.addEventListener('visibilitychange',()=>{if(!document.hidden)poll()});
}

// ===== Semua isian teks: maksimal 20 karakter, spasi tidak dihitung =====
document.querySelectorAll('[data-max]').forEach(el=>{
  const max=+el.dataset.max,cc=el.parentElement.querySelector('[data-cc]'),n=()=>el.value.replace(/\s+/g,'').length;
  // potong otomatis bila melebihi batas (spasi tidak dihitung)
  const potong=()=>{if(n()<=max)return;let c=0,o='';for(const ch of el.value){if(!/\s/.test(ch)){if(c>=max)continue;c++}o+=ch}el.value=o};
  const upd=()=>{potong();if(cc){cc.textContent=n();cc.style.color=n()>=max?'#E05260':''}};
  el.addEventListener('input',upd);upd();
  if(el.form)el.form.addEventListener('submit',e=>{
    if(e.defaultPrevented||el.closest('[hidden]'))return;
    if(el.required&&n()<1){e.preventDefault();alert('Kolom ini wajib diisi.');el.focus()}});
});

// ===== Responsif: tabel data dipecah jadi daftar kartu di HP (label kolom diambil dari judul kolom) =====
document.querySelectorAll('table.rw,table.tb,table.rb,table.lk').forEach(t=>{
  const th=[...(t.querySelector('tr')?.children||[])].map(x=>x.textContent.trim());
  t.querySelectorAll('tr').forEach((tr,i)=>{if(!i)return;[...tr.children].forEach((td,k)=>{if(!td.hasAttribute('data-l'))td.setAttribute('data-l',th[k]||'')})});
  t.classList.add('stk');
});

// ===== Tema terang / gelap: ikuti pengaturan perangkat, bisa diganti manual (tersimpan di browser) =====
(()=>{
  const root=document.documentElement,btns=document.querySelectorAll('[data-tema]');
  const tampil=()=>{const g=root.dataset.theme==='dark';btns.forEach(b=>{b.querySelector('.ti').textContent=g?'☀️':'🌙';b.querySelector('.tt').textContent=g?'Terang':'Gelap'})};
  btns.forEach(b=>b.addEventListener('click',()=>{const t=root.dataset.theme==='dark'?'light':'dark';root.dataset.theme=t;try{localStorage.setItem('tema',t)}catch(e){}tampil()}));
  tampil();
})();
