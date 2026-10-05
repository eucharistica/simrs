// sembunyikan form dan notif
$("#form_rincian").hide();
$("#form_soap").hide();
$("#form_sep").hide();
$("#form_berkasdigital").hide();
$("#histori_pelayanan").hide();
$("#notif").hide();
$('#provider').hide();
$('#aturan_pakai').hide();

// Buka tindakan dalam modal dari form SOAP pasien yang sedang aktif.
$(document).on('click', '#form_soap #buka_tindakan_soap', function(event){
  event.preventDefault();
  var form=$('#form_soap'), noRawat=$.trim(String(form.data('no_rawat')||form.find('input:text[name=no_rawat]').val()||''));
  if(!noRawat){ bootbox.alert('Nomor rawat belum tersedia.'); return false; }
  var modal=$('#tindakanSoapModal');
  modal.find('#tindakanNoRawat').val(noRawat); modal.find('#tindakanTanggal').val(form.find('input:text[name=tgl_perawatan]').val()); modal.find('#tindakanJam').val(form.find('input:text[name=jam_rawat]').val());
  modal.find('#tindakanNamaPasien').val(form.data('nm_pasien')||form.find('input:text[name=nm_pasien]').val()||'');
  modal.data('tindakanDipilih',[]); renderTindakanDipilih(modal); modal.find('#tindakanLayanan,#tindakanDokter,#tindakanNip,#tindakanKdDokter,#tindakanKdNip').val(''); modal.find('#tindakanProvider').val(''); modal.find('#tindakanLayananList,#tindakanDokterList,#tindakanNipList').empty(); modal.find('#tindakanProviderFields').hide(); modal.find('#tindakanStatus').empty(); modal.data('currentProvider',null);
  $.get(mlite.url+'/'+mlite.admin+'/igd/currentprovider?t='+mlite.token,function(data){modal.data('currentProvider',data||{});if(modal.find('#tindakanProvider').val()){modal.find('#tindakanProvider').trigger('change');}});
  modal.modal('show');
  return false;
});

$(document).on('keyup','#tindakanLayanan',function(){var q=$.trim($(this).val()),box=$('#tindakanLayananList');if(!q){box.empty();return;}$.post(mlite.url+'/'+mlite.admin+'/igd/layanan?t='+mlite.token,{layanan:q},function(html){box.html(html);});});
function renderTindakanDipilih(m){var list=m.data('tindakanDipilih')||[],box=m.find('#tindakanDipilih');if(!list.length){box.html('<div class="text-muted">Belum ada tindakan yang dipilih.</div>');return;}var html='';list.forEach(function(x,i){html+='<div class="well well-sm" style="margin-bottom:5px"><strong>'+x.nama+'</strong> <small>('+x.kode+' | Rp. '+x.biaya+')</small><button type="button" class="close hapus-tindakan-modal" data-index="'+i+'">&times;</button></div>';});box.html(html);}
$(document).on('click','#tindakanLayananList .pilih_layanan',function(e){e.preventDefault();var x=$(this),m=$('#tindakanSoapModal'),list=m.data('tindakanDipilih')||[],item={kode:x.data('kd_jenis_prw'),nama:x.data('nm_perawatan'),biaya:x.data('biaya'),kat:x.data('kat')||'tindakan'};if(!list.some(function(v){return v.kode===item.kode;}))list.push(item);m.data('tindakanDipilih',list);renderTindakanDipilih(m);m.find('#tindakanLayanan').val('').focus();m.find('#tindakanLayananList').empty();m.find('#tindakanProviderFields').show();});
$(document).on('click','#tindakanSoapModal .hapus-tindakan-modal',function(){var m=$('#tindakanSoapModal'),list=m.data('tindakanDipilih')||[];list.splice(parseInt($(this).data('index'),10),1);m.data('tindakanDipilih',list);renderTindakanDipilih(m);if(!list.length)m.find('#tindakanProviderFields').hide();});
$(document).on('change','#tindakanProvider',function(){var m=$('#tindakanSoapModal'),v=$(this).val(),u=m.data('currentProvider')||{};m.find('#tindakanDokterGroup').toggle(v==='rawat_jl_dr'||v==='rawat_jl_drpr');m.find('#tindakanPerawatGroup').toggle(v==='rawat_jl_pr'||v==='rawat_jl_drpr');m.find('#tindakanDokter,#tindakanKdDokter,#tindakanNip,#tindakanKdNip').val('').prop('readonly',false);if((v==='rawat_jl_dr'||v==='rawat_jl_drpr')&&u.role==='medis'&&u.dokter&&u.dokter.kd_dokter){m.find('#tindakanDokter').val(u.fullname||u.dokter.nm_dokter).prop('readonly',true);m.find('#tindakanKdDokter').val(u.dokter.kd_dokter);}if((v==='rawat_jl_pr'||v==='rawat_jl_drpr')&&u.role==='paramedis'&&u.petugas&&u.petugas.nip){m.find('#tindakanNip').val(u.fullname||u.petugas.nama).prop('readonly',true);m.find('#tindakanKdNip').val(u.petugas.nip);}});
$(document).on('keyup','#tindakanDokter',function(){var q=$.trim($(this).val());if(q)$.post(mlite.url+'/'+mlite.admin+'/igd/providerlist?t='+mlite.token,{query:q},function(html){$('#tindakanDokterList').html(html);});});
$(document).on('click','#tindakanDokterList li',function(){var p=$(this).text().split(': '),m=$('#tindakanSoapModal');m.find('#tindakanDokter').val(p[1]||'');m.find('#tindakanKdDokter').val(p[0]||'');m.find('#tindakanDokterList').empty();});
$(document).on('keyup','#tindakanNip',function(){var q=$.trim($(this).val());if(q)$.post(mlite.url+'/'+mlite.admin+'/igd/providerlist2?t='+mlite.token,{query:q},function(html){$('#tindakanNipList').html(html);});});
$(document).on('click','#tindakanNipList li',function(){var p=$(this).text().split(': '),m=$('#tindakanSoapModal');m.find('#tindakanNip').val(p[1]||'');m.find('#tindakanKdNip').val(p[0]||'');m.find('#tindakanNipList').empty();});
$(document).on('click','#tindakanSoapModal #simpanTindakanSoap',function(){var m=$('#tindakanSoapModal'),list=m.data('tindakanDipilih')||[],provider=m.find('#tindakanProvider').val(),b=$(this);if(!list.length||!provider){bootbox.alert('Pilih tindakan dan provider terlebih dahulu.');return;}var kdDokter=m.find('#tindakanKdDokter').val(),kdNip=m.find('#tindakanKdNip').val();if((provider==='rawat_jl_dr'||provider==='rawat_jl_drpr')&&!kdDokter){bootbox.alert('Pilih dokter terlebih dahulu.');return;}if((provider==='rawat_jl_pr'||provider==='rawat_jl_drpr')&&!kdNip){bootbox.alert('Pilih perawat terlebih dahulu.');return;}b.prop('disabled',true);var save=function(i){if(i>=list.length){m.find('#tindakanStatus').html('<div class="alert alert-success">'+list.length+' tindakan berhasil disimpan.</div>');setTimeout(function(){m.modal('hide');},600);b.prop('disabled',false);return;}var x=list[i],p={no_rawat:m.find('#tindakanNoRawat').val(),kd_jenis_prw:x.kode,provider:provider,kode_provider:kdDokter,kode_provider2:kdNip,tgl_perawatan:m.find('#tindakanTanggal').val(),jam_rawat:m.find('#tindakanJam').val(),biaya:x.biaya,kat:x.kat,jml:'10'};$.post(mlite.url+'/'+mlite.admin+'/igd/savedetail?t='+mlite.token,p).done(function(){save(i+1);}).fail(function(){m.find('#tindakanStatus').html('<div class="alert alert-danger">Gagal menyimpan tindakan ke-'+(i+1)+'.</div>');b.prop('disabled',false);});};save(0);});

// tombol buka form diklik
$("#index").on('click', '#bukaform', function(){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  $("#form").show().load(baseURL + '/igd/form?t=' + mlite.token);
  $("#bukaform").val("Tutup Form");
  $("#bukaform").attr("id", "tutupform");
});

// Triase IGD dan permintaan laboratorium/radiologi
(function(){
  var base=mlite.url+'/'+mlite.admin;
  function soapContext(){var f=$('#form_soap');return {form:f,no_rawat:$.trim(String(f.data('no_rawat')||f.find('input:text[name=no_rawat]').val()||'')),nm_pasien:f.data('nm_pasien')||f.find('input:text[name=nm_pasien]').val()||''};}
  function localDateTime(v){if(!v)return '';return String(v).replace(' ','T').substring(0,16);}
  function renderSelected(modal){var list=modal.data('pemeriksaanDipilih')||[],box=modal.find('#modalPemeriksaanDipilih');if(!list.length){box.html('<div class="text-muted">Belum ada pemeriksaan yang dipilih.</div>');return;}var html='';list.forEach(function(x,i){html+='<div class="well well-sm" style="margin-bottom:5px"><strong>'+x.nm_perawatan+'</strong> <small>('+x.kat+' | '+x.kd_jenis_prw+' | Rp. '+x.biaya+')</small><button type="button" class="close hapus-pemeriksaan-modal" data-index="'+i+'">&times;</button></div>';});box.html(html);}
  function searchService(input,kind,list){var q=$.trim(input.val());if(!q){list.empty();return;}var data={};data[kind]=q;$.post(base+'/'+(kind==='laboratorium'?'igd/laboratorium':'igd/radiologi')+'?t='+mlite.token,data,function(html){list.html(html);});}
  $(document).on('click','#form_soap #permintaan_lab_soap',function(e){e.preventDefault();var c=soapContext();if(!c.no_rawat){bootbox.alert('Nomor rawat belum tersedia.');return false;}var m=$('#permintaanLabRadModal');m.data({no_rawat:c.no_rawat,tgl_perawatan:m.find('#modalTanggal').val()||c.form.find('input:text[name=tgl_perawatan]').val(),jam_rawat:c.form.find('input:text[name=jam_rawat]').val(),pemeriksaanDipilih:[]});m.find('#permintaanLabRadPasien').text('Pasien: '+c.nm_pasien+' | No. Rawat: '+c.no_rawat);m.find('#modalLaboratorium,#modalRadiologi').val('');m.find('#modalLaboratoriumList,#modalRadiologiList').empty();m.find('#modalInformasi,#modalDiagnosa').val('');m.find('#modalPermintaanTersimpan').html('<div class="text-muted">Memuat...</div>');$.post(base+'/igd/rincian?t='+mlite.token,{no_rawat:c.no_rawat},function(data){m.find('#modalPermintaanTersimpan').html(data||'<div class="text-muted">Belum ada permintaan.</div>');}).fail(function(){m.find('#modalPermintaanTersimpan').html('<div class="alert alert-danger">Gagal memuat permintaan.</div>');});m.modal('show');return false;});
  $(document).on('keyup','#modalLaboratorium',function(){searchService($(this),'laboratorium',$('#modalLaboratoriumList'));});
  $(document).on('keyup','#modalRadiologi',function(){searchService($(this),'radiologi',$('#modalRadiologiList'));});
  $(document).on('click','#permintaanLabRadModal .pilih_laboratorium, #permintaanLabRadModal .pilih_radiologi',function(){var m=$('#permintaanLabRadModal'),x=$(this),list=m.data('pemeriksaanDipilih')||[];var item={kd_jenis_prw:x.data('kd_jenis_prw'),nm_perawatan:x.data('nm_perawatan'),biaya:x.data('biaya'),kat:x.data('kat')};if(!list.some(function(v){return v.kat===item.kat&&v.kd_jenis_prw===item.kd_jenis_prw;})){list.push(item);m.data('pemeriksaanDipilih',list);}renderSelected(m);});
  $(document).on('click','#permintaanLabRadModal .hapus-pemeriksaan-modal',function(){var m=$('#permintaanLabRadModal'),list=m.data('pemeriksaanDipilih')||[];list.splice(parseInt($(this).data('index'),10),1);m.data('pemeriksaanDipilih',list);renderSelected(m);});
  $(document).on('click','#permintaanLabRadModal #modalSimpanPermintaan',function(){var m=$('#permintaanLabRadModal'),c=soapContext(),list=m.data('pemeriksaanDipilih')||[],btn=$(this);if(!list.length){bootbox.alert('Pilih minimal satu pemeriksaan terlebih dahulu.');return;}btn.prop('disabled',true);var save=function(i){if(i>=list.length){m.find('#modalPermintaanTersimpan').load(base+'/igd/rincian?t='+mlite.token,{no_rawat:c.no_rawat});m.data('pemeriksaanDipilih',[]);renderSelected(m);btn.prop('disabled',false);return;}var x=list[i];$.post(base+'/igd/savedetail?t='+mlite.token,{no_rawat:c.no_rawat,kat:x.kat,kd_jenis_prw:x.kd_jenis_prw,tgl_perawatan:c.form.find('input:text[name=tgl_perawatan]').val(),jam_rawat:c.form.find('input:text[name=jam_rawat]').val(),informasi_tambahan:m.find('#modalInformasi').val()||'-',diagnosa_klinis:m.find('#modalDiagnosa').val()||'-'}).done(function(){save(i+1);}).fail(function(){btn.prop('disabled',false);bootbox.alert('Penyimpanan permintaan gagal.');});};save(0);});
  $(document).on('click','#form_soap #buka_eresep_soap',function(e){e.preventDefault();var c=soapContext(),m=$('#eresepSoapIgdModal');if(!c.no_rawat){bootbox.alert('Nomor rawat belum tersedia.');return false;}m.data({no_rawat:c.no_rawat,resepDipilih:[],currentProvider:null});m.find('#igdEresepPasien').text('Pasien: '+c.nm_pasien+' | No. Rawat: '+c.no_rawat);m.find('#igdEresepObat,#igdEresepAturan').val('');m.find('#igdEresepJumlah').val('10');m.find('#igdEresepObatList,#igdEresepStatus').empty();m.find('#igdEresepDipilih').html('<div class="text-muted">Belum ada obat yang dipilih.</div>');$.get(base+'/igd/currentprovider?t='+mlite.token,function(data){m.data('currentProvider',data||{});});m.modal('show');return false;});
  $(document).on('keyup','#igdEresepObat',function(){var q=$.trim($(this).val()),box=$('#igdEresepObatList');if(!q){box.empty();return;}$.post(base+'/igd/obat?t='+mlite.token,{obat:q},function(html){box.html(html);});});
  $(document).on('click','#igdEresepObatList .pilih_obat',function(e){e.preventDefault();var x=$(this),m=$('#eresepSoapIgdModal'),list=m.data('resepDipilih')||[],item={kode:x.data('kode_brng'),nama:x.data('nama_brng')};if(!list.some(function(v){return v.kode===item.kode;}))list.push(item);m.data('resepDipilih',list);var html='';list.forEach(function(v,i){html+='<div class="well well-sm" style="margin-bottom:5px"><strong>'+v.nama+'</strong> <small>('+v.kode+')</small><button type="button" class="close hapus-igd-obat" data-index="'+i+'">&times;</button></div>';});m.find('#igdEresepDipilih').html(html);m.find('#igdEresepObat').val('').focus();m.find('#igdEresepObatList').empty();});
  $(document).on('click','#eresepSoapIgdModal .hapus-igd-obat',function(){var m=$('#eresepSoapIgdModal'),list=m.data('resepDipilih')||[];list.splice(parseInt($(this).data('index'),10),1);m.data('resepDipilih',list);if(!list.length)m.find('#igdEresepDipilih').html('<div class="text-muted">Belum ada obat yang dipilih.</div>');});
  $(document).on('click','#simpanEresepSoapIgd',function(){var m=$('#eresepSoapIgdModal'),c=soapContext(),list=m.data('resepDipilih')||[],u=m.data('currentProvider')||{},b=$(this),kd=u.dokter&&u.dokter.kd_dokter?u.dokter.kd_dokter:'';if(!list.length){bootbox.alert('Pilih minimal satu obat.');return;}if(!kd){bootbox.alert('User login belum terhubung ke data dokter aktif.');return;}b.prop('disabled',true);var save=function(i){if(i>=list.length){m.find('#igdEresepStatus').html('<div class="alert alert-success">E-Resep berhasil disimpan.</div>');setTimeout(function(){m.modal('hide');},600);b.prop('disabled',false);return;}var x=list[i];$.post(base+'/igd/savedetail?t='+mlite.token,{no_rawat:c.no_rawat,kd_jenis_prw:x.kode,provider:'rawat_jl_dr',kode_provider:kd,kode_provider2:'',tgl_perawatan:c.form.find('input:text[name=tgl_perawatan]').val(),jam_rawat:c.form.find('input:text[name=jam_rawat]').val(),kat:'obat',jml:m.find('#igdEresepJumlah').val()||'1',aturan_pakai:m.find('#igdEresepAturan').val()||'-'}).done(function(){save(i+1);}).fail(function(){m.find('#igdEresepStatus').html('<div class="alert alert-danger">E-Resep gagal disimpan.</div>');b.prop('disabled',false);});};save(0);});
  function setField(m,id,key,obj){if(obj&&obj[key]!==undefined&&obj[key]!==null)m.find(id).val(obj[key]);}
  function renderScale(m,data,chosen,n){var rows=(data.master||{})['skala'+n]||[],sel=(chosen||{})['skala'+n]||[],selected={};sel.forEach(function(x){selected[x['kode_skala'+n]]=true;});var html='';rows.forEach(function(x){var code=x['kode_skala'+n],checked=selected[code]?' checked':'';html+='<label class="checkbox-inline" style="display:block;margin:4px 0"><input type="checkbox" class="triase-skala" data-skala="'+n+'" value="'+code+'"'+checked+'> '+(x['pengkajian_skala'+n]||'')+'</label>';});m.find('#triaseSkalaContainer').append('<div class="triase-skala-panel" data-skala-panel="'+n+'" style="padding:8px;border-left:4px solid '+({1:'#cc0000',2:'#e0b000',3:'#009900',4:'#0066cc',5:'#969696'}[n])+'">'+(html||'<div class="text-muted">Master belum tersedia.</div>')+'</div>');}
  function toggleTriase(m){var secondary=m.find('#triaseJenis').val()==='sekunder',tabs=secondary?[3,4,5]:[1,2],html='<ul class="nav nav-tabs" id="triaseSkalaTabs">';tabs.forEach(function(n){html+='<li data-skala="'+n+'"><a href="#">Skala '+n+'</a></li>';});html+='</ul>';m.find('#triaseSkalaTabsContainer').html(html);m.find('#triaseSkalaPanel').remove();m.find('#triasePrimerFields').toggle(!secondary);m.find('#triaseSekunderFields').toggleClass('hidden',!secondary);m.find('#triasePlanPrimer').toggle(!secondary);m.find('#triasePlanSekunder').toggleClass('hidden',!secondary);m.find('#triaseSkalaTabs li:first').addClass('active');m.find('.triase-skala-panel').hide();m.find('.triase-skala-panel[data-skala-panel="'+tabs[0]+'"]').show();}
  $(document).on('change','#triaseIgdModal #triaseJenis',function(){toggleTriase($('#triaseIgdModal'));});
  $(document).on('click','#triaseIgdModal #triaseSkalaTabs li',function(e){e.preventDefault();var m=$('#triaseIgdModal'),n=$(this).data('skala');m.find('#triaseSkalaTabs li').removeClass('active');$(this).addClass('active');m.find('.triase-skala-panel').hide();m.find('.triase-skala-panel[data-skala-panel="'+n+'"]').show();});
  $(document).on('click','#form_soap #buka_triase_igd',function(e){e.preventDefault();var c=soapContext();if(!c.no_rawat){bootbox.alert('Nomor rawat belum tersedia.');return false;}var m=$('#triaseIgdModal');m.find('input,textarea').not('#triaseNoRawat').val('');m.find('input[type=radio],input[type=checkbox]').prop('checked',false);m.find('#triaseCaraMasuk').val('Jalan');m.find('#triaseTransportasi').val('Sendiri');m.find('#triaseEmergency').val('True Emergency');m.find('#triaseKasus').val('002');m.find('#triaseJenis').val('primer');m.find('#triaseDatetime').val(localDateTime());m.find('#triaseNoRawat').val(c.no_rawat);m.find('#triaseIgdPasien').text('Pasien: '+c.nm_pasien+' | No. Rawat: '+c.no_rawat);m.find('#triaseStatus').empty();$.post(base+'/igd/triase?t='+mlite.token,{no_rawat:c.no_rawat},function(data){var b=data.igd||{},p=data.primer||{},s=data.sekunder||{};setField(m,'#triaseCaraMasuk','cara_masuk',b);setField(m,'#triaseTransportasi','alat_transportasi',b);setField(m,'#triaseEmergency','emergency',b);setField(m,'#triaseTensi','tekanan_darah',b);setField(m,'#triaseNadi','nadi',b);setField(m,'#triaseRespirasi','pernapasan',b);setField(m,'#triaseSuhu','suhu',b);setField(m,'#triaseSaturasi','saturasi_o2',b);setField(m,'#triaseBb','bb',b);setField(m,'#triaseNyeri','nyeri',b);setField(m,'#triaseKasus','kode_kasus',b);setField(m,'#triaseAlasan','alasan_kedatangan',b);setField(m,'#triaseKeterangan','keterangan_kedatangan',b);m.find('#triaseDatetime').val(localDateTime(b.tgl_kunjungan||p.tanggaltriase||s.tanggaltriase)||localDateTime());m.find('#triaseSkalaContainer').empty();[1,2,3,4,5].forEach(function(n){renderScale(m,data,data.terpilih,n);});if(Object.keys(s).length){m.find('#triaseJenis').val('sekunder');setField(m,'#triaseAnamnesa','anamnesa_singkat',s);setField(m,'#triaseCatatan','catatan',s);m.find('input[name=triase_plan][value="'+s.plan+'"]').prop('checked',true);}else{setField(m,'#triaseKeluhan','keluhan_utama',p);setField(m,'#triaseKebutuhan','kebutuhan_khusus',p);setField(m,'#triaseCatatan','catatan',p);m.find('input[name=triase_plan][value="'+p.plan+'"]').prop('checked',true);}toggleTriase(m);}).fail(function(){m.find('#triaseStatus').html('<div class="alert alert-warning">Data triase belum tersedia.</div>');});m.modal('show');return false;});
  $(document).on('click','#triaseIgdModal #simpanTriaseIgd',function(){var m=$('#triaseIgdModal'),payload={no_rawat:m.find('#triaseNoRawat').val(),triase_datetime:m.find('#triaseDatetime').val(),jenis_triase:m.find('#triaseJenis').val(),cara_masuk:m.find('#triaseCaraMasuk').val(),alat_transportasi:m.find('#triaseTransportasi').val(),emergency:m.find('#triaseEmergency').val(),tekanan_darah:m.find('#triaseTensi').val(),nadi:m.find('#triaseNadi').val(),pernapasan:m.find('#triaseRespirasi').val(),suhu:m.find('#triaseSuhu').val(),saturasi_o2:m.find('#triaseSaturasi').val(),bb:m.find('#triaseBb').val(),nyeri:m.find('#triaseNyeri').val(),kode_kasus:m.find('#triaseKasus').val(),alasan_kedatangan:m.find('#triaseAlasan').val(),keterangan_kedatangan:m.find('#triaseKeterangan').val(),catatan:m.find('#triaseCatatan').val(),plan:m.find('input[name=triase_plan]:checked').val()||'',keluhan_utama:m.find('#triaseKeluhan').val(),kebutuhan_khusus:m.find('#triaseKebutuhan').val(),anamnesa_singkat:m.find('#triaseAnamnesa').val()};[1,2,3,4,5].forEach(function(n){payload['skala'+n]=JSON.stringify(m.find('.triase-skala[data-skala="'+n+'"]:checked').map(function(){return this.value;}).get());});var b=$(this).prop('disabled',true);$.post(base+'/igd/savetriase?t='+mlite.token,payload,function(){m.find('#triaseStatus').html('<div class="alert alert-success">Triase berhasil disimpan.</div>');}).fail(function(){m.find('#triaseStatus').html('<div class="alert alert-danger">Triase gagal disimpan.</div>');}).always(function(){b.prop('disabled',false);});});
})();

// tombol tutup form diklik
$("#index").on('click', '#tutupform', function(){
  event.preventDefault();
  $("#form").hide();
  $("#tutupform").val("Buka Form");
  $("#tutupform").attr("id", "bukaform");
});

// tombol batal diklik
$("#form").on("click", "#batal", function(event){
  $("#pasien").hide();
  $('input:text[name=pasien]').val("");
  $('input:text[name=jk]').val("");
  $('input:text[name=stts_daftar]').val("");
  $('input:text[name=no_tlp]').val("");
  $('input:text[name=no_rawat]').removeAttr("disabled", true);
  $('input:text[name=no_reg]').removeAttr("disabled", true);
  bersih();
});

$("#form").on("click","#no_rawat", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  var url = baseURL + '/igd/maxid?t=' + mlite.token;
  $.post(url, {
  } ,function(data) {
    $("#no_rawat").val(data);
  });
});

$("#form").on("click","#no_reg", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  var url = baseURL + '/igd/maxantrian?t=' + mlite.token;
  var kd_poli = $('select[name=kd_poli]').val();

  $.post(url, {
    kd_poli: kd_poli
  } ,function(data) {
    $("#no_reg").val(data);
  });
});

$("#form").on("click", "#simpan", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  var no_rawat = $('input:text[name=no_rawat]').val();
  var no_reg = $('input:text[name=no_reg]').val();
  var tgl_registrasi = $('#tgl_registrasi').val();
  var jam_reg = $('#jam_reg').val();
  var no_rkm_medis = $('input:text[name=no_rkm_medis]').val();
  var kd_dokter = $('select[name=kd_dokter]').val();
  var kd_pj = $('select[name=kd_pj]').val();
  var stts_daftar = $('input:hidden[name=stts_daftar]').val();

  var url = baseURL + '/igd/save?t=' + mlite.token;

  if(no_rawat == '') {
    alert('Nomor rawat masih kosong!')
  }

  if(no_reg == '') {
    alert('Nomor antrian masih kosong!')
  }

  if(no_rkm_medis == '') {
    alert('Data pasien rawat masih kosong! Silahkan pilih pasien.')
  }
  if(!(stts_daftar == 'Baru' || stts_daftar == 'Lama' || stts_daftar == '-')) {
    bootbox.alert("Ada tagihan belum diselesaikan. Silahkan hubungi kasir atau admin!");
  } else {
    $.post(url,{
      no_rawat: no_rawat,
      no_reg: no_reg,
      tgl_registrasi: tgl_registrasi,
      jam_reg: jam_reg,
      no_rkm_medis: no_rkm_medis,
      kd_dokter: kd_dokter,
      kd_pj: kd_pj,
      stts_daftar: stts_daftar
    },function(data) {
      $("#display").show().load(baseURL + '/igd/display?t=' + mlite.token);
      bersih();
      $("#status_pendaftaran").hide();
      $('#notif').html("<div class=\"alert alert-success alert-dismissible fade in\" role=\"alert\" style=\"border-radius:0px;margin-top:-15px;\">"+
      "Data pendaftaran rawat jalan telah disimpan!"+
      "<button type=\"button\" class=\"close\" data-dismiss=\"alert\" aria-label=\"Close\">&times;</button>"+
      "</div>").show();
    }).error(function () {
      $('#notif').html("<div class=\"alert alert-danger alert-dismissible fade in\" role=\"alert\" style=\"border-radius:0px;margin-top:-15px;\">"+
      "Gagal menyimpan data pendaftaran rawat jalan!"+
      "<button type=\"button\" class=\"close\" data-dismiss=\"alert\" aria-label=\"Close\">&times;</button>"+
      "</div>").show();
    });
  }
  event.preventDefault();
});

$("#display").on("click",".antrian", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  var no_rawat = $(this).attr("data-no_rawat");
  window.open(baseURL + '/igd/antrian?no_rawat=' + no_rawat + '&t=' + mlite.token);
});

$("#display").on("click",".riwayat_perawatan", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  var no_rkm_medis = $(this).attr("data-no_rkm_medis");
  window.open(baseURL + '/pasien/riwayatperawatan/' + no_rkm_medis + '?t=' + mlite.token);
});

// ketika baris data diklik
$("#display").on("click", ".edit", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  var url = baseURL + '/igd/form?t=' + mlite.token;
  var no_rawat = $(this).attr("data-no_rawat");
  $.post(url, {no_rawat: no_rawat} ,function(data) {
    // tampilkan data
    $("#form").html(data).show();
    var url    				= baseURL + '/igd/statusdaftar?t=' + mlite.token;

    $.post(url, {no_rawat: no_rawat} ,function(data) {
      $("#stts_daftar").html(data).show();
    });
  });
});

// ketika tombol hapus ditekan
$("#form").on("click","#hapus", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  var url = baseURL + '/igd/hapus?t=' + mlite.token;
  //var no_rawat = $(this).attr("data-no_rawat");
  var no_rawat = $('input:text[name=no_rawat]').val();

  // tampilkan dialog konfirmasi
  bootbox.confirm("Apakah Anda yakin ingin menghapus data ini?", function(result){
    // ketika ditekan tombol ok
    if (result){
      // mengirimkan perintah penghapusan
      $.post(url, {
        no_rawat: no_rawat
      } ,function(data) {
        // sembunyikan form, tampilkan data yang sudah di perbaharui, tampilkan notif
        $("#display").load(baseURL + '/igd/display?t=' + mlite.token);
        bersih();
        $('#notif').html("<div class=\"alert alert-danger alert-dismissible fade in\" role=\"alert\" style=\"border-radius:0px;margin-top:-15px;\">"+
        "Data pasien telah dihapus!"+
        "<button type=\"button\" class=\"close\" data-dismiss=\"alert\" aria-label=\"Close\">&times;</button>"+
        "</div>").show();
      });
    }
  });
});

$("#display").on("click", ".sep", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();

  var no_rawat = $(this).attr("data-no_rawat");
  var no_rkm_medis = $(this).attr("data-no_rkm_medis");
  var nm_pasien = $(this).attr("data-nm_pasien");
  var tgl_registrasi = $(this).attr("data-tgl_registrasi");
  var no_peserta = $(this).attr("data-no_peserta");

  var url = baseURL + '/vclaim/bynokartu/' + no_peserta + '/{?=date('Y-m-d')?}?t=' + mlite.token;

  $.get(url,function(data) {
    var data = JSON.parse(data);
    var json_obj = [data];
    if(!json_obj[0]) {
      alert('Koneksi ke server BPJS terputus. Silahkan ulangi lagi!');
    } else if(json_obj[0].metaData.code == 200) {
      $('.nama_peserta').text(json_obj[0].response.peserta.nama);
      $('#no_kartu_peserta').text(json_obj[0].response.peserta.noKartu);
      $('#no_mr_peserta').text(no_rkm_medis);
      $('#nik_peserta').text(json_obj[0].response.peserta.nik);
      $('#tgl_lahir_peserta').text(json_obj[0].response.peserta.tglLahir);
      $('#status_peserta').text(json_obj[0].response.peserta.statusPeserta.keterangan);
      $('#jenis_peserta').text(json_obj[0].response.peserta.jenisPeserta.keterangan);
      $('.prolainis_peserta').text(json_obj[0].response.peserta.informasi.prolanisPRB);

      var jenis_kelamin = 'Laki-Laki';
      if(json_obj[0].response.peserta.sex == 'P') {
        var jenis_kelamin = 'Perempuan';
      }

      $('input:text[name=sep_jenis_kelamin_nama]').val(jenis_kelamin);
      $('input:text[name=sep_jenis_kelamin_kode]').val(json_obj[0].response.peserta.sex);
      $('input:text[name=sep_tanggal_lahir]').val(json_obj[0].response.peserta.tglLahir);
      $('input:text[name=sep_jenis_peserta]').val(json_obj[0].response.peserta.jenisPeserta.keterangan);
      $('input:text[name=sep_no_kartu]').val(json_obj[0].response.peserta.noKartu);
      $('input:text[name=sep_norm]').val(json_obj[0].response.peserta.mr.noMR);
      $('input:text[name=sep_eksekutif_kode]').val("0");
      $('input:text[name=sep_eksekutif_nama]').val("Tidak");
      $('input:text[name=sep_kunjungan_kode]').val("0");
      $('input:text[name=sep_kunjungan_nama]').val("Normal");
      $('input:text[name=sep_cob_kode]').val("0");
      $('input:text[name=sep_cob_nama]').val("Tidak");
      $('input:text[name=sep_katarak_kode]').val("0");
      $('input:text[name=sep_katarak_nama]').val("Tidak");
      $('input:text[name=sep_status_kecelakaan_kode]').val("0");
      $('input:text[name=sep_status_kecelakaan_nama]').val("Tidak");
      $('input:text[name=sep_penjamin_kecelakaan_kode]').val("0");
      $('input:text[name=sep_penjamin_kecelakaan_nama]').val("Tidak");
      $('input:text[name=sep_suplesi_kode]').val("0");
      $('input:text[name=sep_suplesi_nama]').val("Tidak");
      $('input:text[name=sep_kelas_kode]').val(json_obj[0].response.peserta.hakKelas.kode);
      $('input:text[name=sep_kelas_nama]').val(json_obj[0].response.peserta.hakKelas.keterangan);
      $('input:text[name=sep_nomor_telepon]').val(json_obj[0].response.peserta.mr.noTelepon);

    } else {
      alert(json_obj[0].metaData.message);
    }
  });

  $('input:text[name=sep_no_rawat]').val(no_rawat);
  $('input:text[name=no_rkm_medis]').val(no_rkm_medis);
  $('input:text[name=nm_pasien]').val(nm_pasien);
  $('input:text[name=tgl_registrasi]').val(tgl_registrasi);
  $('input:text[name=nomor_asuransi]').val(no_peserta);
  $('input:text[name=no_kartu_pcare]').val(no_peserta);
  $('input:text[name=no_kartu_rs]').val(no_peserta);
  $("#display").hide();
  $("#form_rincian").hide();
  $("#form").hide();
  $("#notif").hide();
  $("#form_soap").hide();
  $("#form_sep").show();
  $("#bukaform").hide();
});


$('#manage').on('click', '#submit_periode_rawat_jalan', function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  var url    = baseURL + '/igd/display?t=' + mlite.token;
  var periode_rawat_jalan  = $('input:text[name=periode_rawat_jalan]').val();
  var periode_rawat_jalan_akhir  = $('input:text[name=periode_rawat_jalan_akhir]').val();

  if(periode_rawat_jalan == '') {
    alert('Tanggal awal masih kosong!')
  }
  if(periode_rawat_jalan_akhir == '') {
    alert('Tanggal akhir masih kosong!')
  }

  $.post(url, {periode_rawat_jalan: periode_rawat_jalan, periode_rawat_jalan_akhir: periode_rawat_jalan_akhir} ,function(data) {
  // tampilkan data
    $("#form").show();
    $("#display").html(data).show();
    $("#form_rincian").hide();
    $("#form_soap").hide();
    $("#form_sep").hide();
    $("#notif").hide();
    $("#rincian").hide();
    $("#sep").hide();
    $("#soap").hide();
    $('.periode_rawat_jalan').datetimepicker('remove');
  });

});

$('#manage').on('click', '#belum_periode_rawat_jalan', function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  var url    = baseURL + '/igd/display?t=' + mlite.token;
  var periode_rawat_jalan  = $('input:text[name=periode_rawat_jalan]').val();
  var periode_rawat_jalan_akhir  = $('input:text[name=periode_rawat_jalan_akhir]').val();
  var status_periksa = 'belum';

  if(periode_rawat_jalan == '') {
    alert('Tanggal awal masih kosong!')
  }
  if(periode_rawat_jalan_akhir == '') {
    alert('Tanggal akhir masih kosong!')
  }

  $.post(url, {periode_rawat_jalan: periode_rawat_jalan, periode_rawat_jalan_akhir: periode_rawat_jalan_akhir, status_periksa: status_periksa} ,function(data) {
  // tampilkan data
    $("#form").show();
    $("#display").html(data).show();
    $("#form_rincian").hide();
    $("#form_soap").hide();
    $("#form_sep").hide();
    $("#notif").hide();
    $("#rincian").hide();
    $("#sep").hide();
    $("#soap").hide();
    $('.periode_rawat_jalan').datetimepicker('remove');
  });

});

$('#manage').on('click', '#selesai_periode_rawat_jalan', function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  var url    = baseURL + '/igd/display?t=' + mlite.token;
  var periode_rawat_jalan  = $('input:text[name=periode_rawat_jalan]').val();
  var periode_rawat_jalan_akhir  = $('input:text[name=periode_rawat_jalan_akhir]').val();
  var status_periksa = 'selesai';

  if(periode_rawat_jalan == '') {
    alert('Tanggal awal masih kosong!')
  }
  if(periode_rawat_jalan_akhir == '') {
    alert('Tanggal akhir masih kosong!')
  }

  $.post(url, {periode_rawat_jalan: periode_rawat_jalan, periode_rawat_jalan_akhir: periode_rawat_jalan_akhir, status_periksa: status_periksa} ,function(data) {
  // tampilkan data
    $("#form").show();
    $("#display").html(data).show();
    $("#form_rincian").hide();
    $("#form_soap").hide();
    $("#form_sep").hide();
    $("#notif").hide();
    $("#rincian").hide();
    $("#sep").hide();
    $("#soap").hide();
    $('.periode_rawat_jalan').datetimepicker('remove');
  });

});

$('#manage').on('click', '#lunas_periode_rawat_jalan', function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  var url    = baseURL + '/igd/display?t=' + mlite.token;
  var periode_rawat_jalan  = $('input:text[name=periode_rawat_jalan]').val();
  var periode_rawat_jalan_akhir  = $('input:text[name=periode_rawat_jalan_akhir]').val();
  var status_periksa = 'lunas';

  if(periode_rawat_jalan == '') {
    alert('Tanggal awal masih kosong!')
  }
  if(periode_rawat_jalan_akhir == '') {
    alert('Tanggal akhir masih kosong!')
  }

  $.post(url, {periode_rawat_jalan: periode_rawat_jalan, periode_rawat_jalan_akhir: periode_rawat_jalan_akhir, status_periksa: status_periksa} ,function(data) {
  // tampilkan data
    $("#form").show();
    $("#display").html(data).show();
    $("#form_rincian").hide();
    $("#form_soap").hide();
    $("#form_sep").hide();
    $("#notif").hide();
    $("#rincian").hide();
    $("#sep").hide();
    $("#soap").hide();
    $('.periode_rawat_jalan').datetimepicker('remove');
  });

});

//$("#display").on("click", ".soap", function(event){

// ketika tombol simpan diklik
$("#form_soap").on("click", "#simpan_soap", function(event){
  {if: !$cek_role}
    bootbox.alert({
        title: "Pemberitahuan penggunaan!",
        message: "Silahkan login dengan akun non administrator (akun yang berelasi dengan modul kepegawaian)!"
    });
  {else}
    var baseURL = mlite.url + '/' + mlite.admin;
    event.preventDefault();

    var no_rawat        = $('input:text[name=no_rawat]').val();
    var tgl_perawatan   = $('input:text[name=tgl_perawatan]').val();
    var jam_rawat       = $('input:text[name=jam_rawat]').val();
    var suhu_tubuh      = $('input:text[name=suhu_tubuh]').val();
    var tensi           = $('input:text[name=tensi]').val();
    var nadi            = $('input:text[name=nadi]').val();
    var respirasi       = $('input:text[name=respirasi]').val();
    var tinggi          = $('input:text[name=tinggi]').val();
    var berat           = $('input:text[name=berat]').val();
    var gcs             = $('input:text[name=gcs]').val();
    var kesadaran       = $('input:text[name=kesadaran]').val();
    var alergi          = $('input:text[name=alergi]').val();
    var alergi          = $('input:text[name=alergi]').val();
    var lingkar_perut   = $('input:text[name=lingkar_perut]').val();
    var keluhan         = $('textarea[name=keluhan]').val();
    var pemeriksaan     = $('textarea[name=pemeriksaan]').val();
    var penilaian       = $('textarea[name=penilaian]').val();
    var rtl             = $('textarea[name=rtl]').val();
    var instruksi       = $('textarea[name=instruksi]').val();
    var evaluasi        = $('textarea[name=evaluasi]').val();
    var spo2            = $('input:text[name=spo2]').val();

    var url = baseURL + '/igd/savesoap?t=' + mlite.token;
    $.post(url, {no_rawat : no_rawat,
    tgl_perawatan: tgl_perawatan,
    jam_rawat: jam_rawat,
    suhu_tubuh : suhu_tubuh,
    tensi : tensi,
    nadi : nadi,
    respirasi : respirasi,
    tinggi : tinggi,
    berat : berat,
    gcs : gcs,
    kesadaran : kesadaran,
    alergi : alergi,
    lingkar_perut: lingkar_perut,
    keluhan : keluhan,
    pemeriksaan : pemeriksaan,
    penilaian : penilaian,
    rtl : rtl,
    instruksi : instruksi,
    evaluasi : evaluasi,
    spo2 : spo2
    }, function(data) {
      // tampilkan data
      $("#display").hide();
      var url = baseURL + '/igd/soap?t=' + mlite.token;
      $.post(url, {no_rawat : no_rawat,
      }, function(data) {
        // tampilkan data
        $("#soap").html(data).show();
      });
      $('input:text[name=suhu_tubuh]').val("");
      $('input:text[name=tensi]').val("");
      $('input:text[name=nadi]').val("");
      $('input:text[name=respirasi]').val("");
      $('input:text[name=tinggi]').val("");
      $('input:text[name=berat]').val("");
      $('input:text[name=gcs]').val("");
      $('input:text[name=kesadaran]').val("");
      $('input:text[name=alergi]').val("");
      $('input:text[name=lingkar_perut]').val("");
      $('textarea[name=keluhan]').val("");
      $('textarea[name=pemeriksaan]').val("");
      $('textarea[name=penilaian]').val("");
      $('textarea[name=rtl]').val("");
      $('textarea[name=instruksi]').val("");
      $('textarea[name=evaluasi]').val("");
      $('input:text[name=spo2]').val("");
      $('input:text[name=tgl_perawatan]').val("{?=date('Y-m-d')?}");
      $('input:text[name=tgl_registrasi]').val("{?=date('Y-m-d')?}");
      $('input:text[name=jam_rawat]').val("{?=date('H:i:s')?}");
      $('#notif').html("<div class=\"alert alert-success alert-dismissible fade in\" role=\"alert\" style=\"border-radius:0px;margin-top:-15px;\">"+
      "Data soap telah disimpan!"+
      "<button type=\"button\" class=\"close\" data-dismiss=\"alert\" aria-label=\"Close\">&times;</button>"+
      "</div>").show();
    });
  {/if}
});

// ketika tombol hapus ditekan
$("#soap").on("click",".edit_soap", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  var no_rawat        = $(this).attr("data-no_rawat");
  var tgl_perawatan   = $(this).attr("data-tgl_perawatan");
  var jam_rawat       = $(this).attr("data-jam_rawat");
  var suhu_tubuh      = $(this).attr("data-suhu_tubuh");
  var tensi           = $(this).attr("data-tensi");
  var nadi            = $(this).attr("data-nadi");
  var respirasi       = $(this).attr("data-respirasi");
  var tinggi          = $(this).attr("data-tinggi");
  var berat           = $(this).attr("data-berat");
  var gcs             = $(this).attr("data-gcs");
  var kesadaran       = $(this).attr("data-kesadaran");
  var alergi          = $(this).attr("data-alergi");
  var lingkar_perut   = $(this).attr("data-lingkar_perut");
  var keluhan         = $(this).attr("data-keluhan");
  var pemeriksaan     = $(this).attr("data-pemeriksaan");
  var penilaian       = $(this).attr("data-penilaian");
  var rtl             = $(this).attr("data-rtl");
  var instruksi       = $(this).attr("data-instruksi");
  var evaluasi        = $(this).attr("data-evaluasi");
  var spo2            = $(this).attr("data-spo2");

  $('input:text[name=tgl_perawatan]').val(tgl_perawatan);
  $('input:text[name=jam_rawat]').val(jam_rawat);
  $('input:text[name=suhu_tubuh]').val(suhu_tubuh);
  $('input:text[name=tensi]').val(tensi);
  $('input:text[name=nadi]').val(nadi);
  $('input:text[name=respirasi]').val(respirasi);
  $('input:text[name=tinggi]').val(tinggi);
  $('input:text[name=berat]').val(berat);
  $('input:text[name=gcs]').val(gcs);
  $('input:text[name=kesadaran]').val(kesadaran);
  $('input:text[name=alergi]').val(alergi);
  $('input:text[name=lingkar_perut]').val(lingkar_perut);
  $('textarea[name=keluhan]').val(keluhan);
  $('textarea[name=pemeriksaan]').val(pemeriksaan);
  $('textarea[name=penilaian]').val(penilaian);
  $('textarea[name=rtl]').val(rtl);
  $('textarea[name=instruksi]').val(instruksi);
  $('textarea[name=evaluasi]').val(evaluasi);
  $('input:text[name=spo2]').val(spo2);

});

// ketika tombol hapus ditekan
$("#soap").on("click",".hapus_soap", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  var url = baseURL + '/igd/hapussoap?t=' + mlite.token;
  var no_rawat = $(this).attr("data-no_rawat");
  var tgl_perawatan = $(this).attr("data-tgl_perawatan");
  var jam_rawat = $(this).attr("data-jam_rawat");

  // tampilkan dialog konfirmasi
  bootbox.confirm("Apakah Anda yakin ingin menghapus data ini?", function(result){
    // ketika ditekan tombol ok
    if (result){
      // mengirimkan perintah penghapusan
      $.post(url, {
        no_rawat: no_rawat,
        tgl_perawatan: tgl_perawatan,
        jam_rawat: jam_rawat
      } ,function(data) {
        var url = baseURL + '/igd/soap?t=' + mlite.token;
        $.post(url, {no_rawat : no_rawat,
        }, function(data) {
          // tampilkan data
          $("#soap").html(data).show();
        });
        $('input:text[name=suhu_tubuh]').val("");
        $('input:text[name=tensi]').val("");
        $('input:text[name=nadi]').val("");
        $('input:text[name=respirasi]').val("");
        $('input:text[name=tinggi]').val("");
        $('input:text[name=berat]').val("");
        $('input:text[name=gcs]').val("");
        $('input:text[name=kesadaran]').val("");
        $('input:text[name=alergi]').val("");
        $('input:text[name=lingkar_perut]').val("");
        $('textarea[name=keluhan]').val("");
        $('textarea[name=pemeriksaan]').val("");
        $('textarea[name=penilaian]').val("");
        $('textarea[name=rtl]').val("");
        $('textarea[name=instruksi]').val("");
        $('textarea[name=evaluasi]').val("");
        $('input:text[name=spo2]').val("");
        $('input:text[name=tgl_perawatan]').val("{?=date('Y-m-d')?}");
        $('input:text[name=tgl_registrasi]').val("{?=date('Y-m-d')?}");
        $('input:text[name=jam_rawat]').val("{?=date('H:i:s')?}");
        $('#notif').html("<div class=\"alert alert-danger alert-dismissible fade in\" role=\"alert\" style=\"border-radius:0px;margin-top:-15px;\">"+
        "Data rincian riwayat telah dihapus!"+
        "<button type=\"button\" class=\"close\" data-dismiss=\"alert\" aria-label=\"Close\">&times;</button>"+
        "</div>").show();
      });
    }
  });
});

// tombol batal diklik
$("#form_rincian").on("click", "#selesai", function(event){
  bersih();
  $("#form_berkasdigital").hide();
  $("#form_rincian").hide();
  $("#form_soap").hide();
  $("#form").show();
  $("#display").show();
  $("#rincian").hide();
  $("#soap").hide();
  $("#berkasdigital").hide();
});

// tombol batal diklik
$("#form_soap").on("click", "#selesai_soap", function(event){
  bersih();
  $("#form_berkasdigital").hide();
  $("#form_rincian").hide();
  $("#form_soap").hide();
  $("#form").show();
  $("#display").show();
  $("#rincian").hide();
  $("#soap").hide();
  $("#berkasdigital").hide();
});

// ketika baris data diklik
//$("#display").on("click", ".layanan_obat", function(event){

// ketika inputbox pencarian diisi
$('input:text[name=layanan]').on('input',function(e){
  var baseURL = mlite.url + '/' + mlite.admin;
  var url    = baseURL + '/igd/layanan?t=' + mlite.token;
  var layanan = $('input:text[name=layanan]').val();

  if(layanan!="") {
      $.post(url, {layanan: layanan} ,function(data) {
      // tampilkan data yang sudah di perbaharui
        $("#layanan").html(data).show();
        $("#obat").hide();
      });
  }

});
// end pencarian

// ketika inputbox pencarian diisi
$('input:text[name=obat]').on('input',function(e){
  var baseURL = mlite.url + '/' + mlite.admin;
  var url    = baseURL + '/igd/obat?t=' + mlite.token;
  var obat = $('input:text[name=obat]').val();

  if(obat!="") {
      $.post(url, {obat: obat} ,function(data) {
      // tampilkan data yang sudah di perbaharui
        $("#obat").html(data).show();
        $("#layanan").hide();
      });
  }

});
// end pencarian

// ketika baris data diklik
$("#layanan").on("click", ".pilih_layanan", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();

  var kd_jenis_prw = $(this).attr("data-kd_jenis_prw");
  var nm_perawatan = $(this).attr("data-nm_perawatan");
  var biaya = $(this).attr("data-biaya");
  var kat = $(this).attr("data-kat");

  $('input:hidden[name=kd_jenis_prw]').val(kd_jenis_prw);
  $('input:text[name=nm_perawatan]').val(nm_perawatan);
  $('input:text[name=biaya]').val(biaya);
  $('input:hidden[name=kat]').val(kat);

  $("#layanan").hide();
  $('#provider').show();
  $('#aturan_pakai').hide();
});

// ketika baris data diklik
$("#obat").on("click", ".pilih_obat", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();

  var kode_brng = $(this).attr("data-kode_brng");
  var nama_brng = $(this).attr("data-nama_brng");
  var biaya = $(this).attr("data-ralan");
  var kat = $(this).attr("data-kat");

  $('input:hidden[name=kd_jenis_prw]').val(kode_brng);
  $('input:text[name=nm_perawatan]').val(nama_brng);
  $('input:text[name=biaya]').val(biaya);
  $('input:hidden[name=kat]').val(kat);

  /*$('#jumlah_jual').val(1);
  var jumlah_jual  = $('input:text[name=jumlah_jual]').val();

  $('#jumlah_jual').removeAttr("disabled");
  $('#potongan').removeAttr("disabled");
  $('#jumlah_jual').focus();

  var total = (Number(harga)) * (Number(jumlah_jual));
  $('input:text[name=total]').val(total);*/

  $('#obat').hide();
  $('#aturan_pakai').show();
  $('#rawat_jl_dr').show();
});

// ketika tombol simpan diklik
$("#form_rincian").on("click", "#simpan_rincian", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();

  var no_rawat        = $('input:text[name=no_rawat]').val();
  var kd_jenis_prw 	  = $('input:hidden[name=kd_jenis_prw]').val();
  var provider        = $('select[name=provider]').val();
  var kode_provider   = $('input:text[name=kode_provider]').val();
  var kode_provider2   = $('input:text[name=kode_provider2]').val();
  var tgl_perawatan   = $('input:text[name=tgl_perawatan]').val();
  var jam_rawat       = $('input:text[name=jam_rawat]').val();
  var biaya           = $('input:text[name=biaya]').val();
  var aturan_pakai    = $('input:text[name=aturan_pakai]').val();
  var kat             = $('input:hidden[name=kat]').val();
  var jml             = $('input:text[name=jml]').val();

  var url = baseURL + '/igd/savedetail?t=' + mlite.token;
  $.post(url, {no_rawat : no_rawat,
  kd_jenis_prw   : kd_jenis_prw,
  provider       : provider,
  kode_provider  : kode_provider,
  kode_provider2 : kode_provider2,
  tgl_perawatan  : tgl_perawatan,
  jam_rawat      : jam_rawat,
  biaya          : biaya,
  aturan_pakai   : aturan_pakai,
  kat            : kat,
  jml            : jml
  }, function(data) {

    // tampilkan data
    $("#display").hide();
    var url = baseURL + '/igd/rincian?t=' + mlite.token;
    $.post(url, {no_rawat : no_rawat,
    }, function(data) {
      // tampilkan data
      $("#rincian").html(data).show();
    });
    $('input:hidden[name=kd_jenis_prw]').val("");
    $('input:text[name=nm_perawatan]').val("");
    $('input:hidden[name=kat]').val("");
    $('input:text[name=biaya]').val("");
    $('input:text[name=nama_provider]').val("");
    $('input:text[name=nama_provider2]').val("");
    $('input:text[name=kode_provider]').val("");
    $('input:text[name=kode_provider2]').val("");
    $('#notif').html("<div class=\"alert alert-success alert-dismissible fade in\" role=\"alert\" style=\"border-radius:0px;margin-top:-15px;\">"+
    "Data pasien telah disimpan!"+
    "<button type=\"button\" class=\"close\" data-dismiss=\"alert\" aria-label=\"Close\">&times;</button>"+
    "</div>").show();
  });
});

// ketika tombol hapus ditekan
$("#rincian").on("click",".hapus_detail", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  var url = baseURL + '/igd/hapusdetail?t=' + mlite.token;
  var no_rawat = $(this).attr("data-no_rawat");
  var kd_jenis_prw = $(this).attr("data-kd_jenis_prw");
  var tgl_perawatan = $(this).attr("data-tgl_perawatan");
  var jam_rawat = $(this).attr("data-jam_rawat");
  var provider = $(this).attr("data-provider");

  // tampilkan dialog konfirmasi
  bootbox.confirm("Apakah Anda yakin ingin menghapus data ini?", function(result){
    // ketika ditekan tombol ok
    if (result){
      // mengirimkan perintah penghapusan
      $.post(url, {
        no_rawat: no_rawat,
        kd_jenis_prw: kd_jenis_prw,
        tgl_perawatan: tgl_perawatan,
        jam_rawat: jam_rawat,
        provider: provider
      } ,function(data) {
        var url = baseURL + '/igd/rincian?t=' + mlite.token;
        $.post(url, {no_rawat : no_rawat,
        }, function(data) {
          // tampilkan data
          $("#rincian").html(data).show();
        });
        $('#notif').html("<div class=\"alert alert-danger alert-dismissible fade in\" role=\"alert\" style=\"border-radius:0px;margin-top:-15px;\">"+
        "Data rincian rawat jalan telah dihapus!"+
        "<button type=\"button\" class=\"close\" data-dismiss=\"alert\" aria-label=\"Close\">&times;</button>"+
        "</div>").show();
      });
    }
  });
});

// ketika tombol hapus ditekan
$("#rincian").on("click",".hapus_resep_obat", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  var url = baseURL + '/igd/hapusresep?t=' + mlite.token;
  var no_resep = $(this).attr("data-no_resep");
  var no_rawat = $(this).attr("data-no_rawat");
  var tgl_peresepan = $(this).attr("data-tgl_peresepan");
  var jam_peresepan = $(this).attr("data-jam_peresepan");

  // tampilkan dialog konfirmasi
  bootbox.confirm("Apakah Anda yakin ingin menghapus data ini?", function(result){
    // ketika ditekan tombol ok
    if (result){
      // mengirimkan perintah penghapusan
      $.post(url, {
        no_resep: no_resep,
        no_rawat: no_rawat,
        tgl_peresepan: tgl_peresepan,
        jam_peresepan: jam_peresepan
      } ,function(data) {
        var url = baseURL + '/igd/rincian?t=' + mlite.token;
        $.post(url, {no_rawat : no_rawat,
        }, function(data) {
          // tampilkan data
          $("#rincian").html(data).show();
        });
        $('#notif').html("<div class=\"alert alert-danger alert-dismissible fade in\" role=\"alert\" style=\"border-radius:0px;margin-top:-15px;\">"+
        "Data rincian rawat jalan telah dihapus!"+
        "<button type=\"button\" class=\"close\" data-dismiss=\"alert\" aria-label=\"Close\">&times;</button>"+
        "</div>").show();
      });
    }
  });
});

// ketika tombol hapus ditekan
$("#rincian").on("click",".hapus_resep_dokter", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  var url = baseURL + '/igd/hapusresep?t=' + mlite.token;
  var no_resep = $(this).attr("data-no_resep");
  var no_rawat = $(this).attr("data-no_rawat");
  var kd_jenis_prw = $(this).attr("data-kd_jenis_prw");

  // tampilkan dialog konfirmasi
  bootbox.confirm("Apakah Anda yakin ingin menghapus data ini?", function(result){
    // ketika ditekan tombol ok
    if (result){
      // mengirimkan perintah penghapusan
      $.post(url, {
        no_resep: no_resep,
        no_rawat: no_rawat,
        kd_jenis_prw: kd_jenis_prw
      } ,function(data) {
        var url = baseURL + '/igd/rincian?t=' + mlite.token;
        $.post(url, {no_rawat : no_rawat,
        }, function(data) {
          // tampilkan data
          $("#rincian").html(data).show();
        });
        $('#notif').html("<div class=\"alert alert-danger alert-dismissible fade in\" role=\"alert\" style=\"border-radius:0px;margin-top:-15px;\">"+
        "Data rincian rawat jalan telah dihapus!"+
        "<button type=\"button\" class=\"close\" data-dismiss=\"alert\" aria-label=\"Close\">&times;</button>"+
        "</div>").show();
      });
    }
  });
});

function bersih(){
  $('input:text[name=no_rawat]').val("");
  $('input:text[name=no_rkm_medis]').val("");
  $('input:text[name=nm_pasien]').val("");
  $('input:text[name=tgl_perawatan]').val("{?=date('Y-m-d')?}");
  $('input:text[name=tgl_registrasi]').val("{?=date('Y-m-d')?}");
  $('input:text[name=tgl_lahir]').val("");
  $('input:text[name=jenis_kelamin]').val("");
  $('input:text[name=alamat]').val("");
  $('input:text[name=telepon]').val("");
  $('input:text[name=pekerjaan]').val("");
  $('input:text[name=layanan]').val("");
  $('input:text[name=obat]').val("");
  $('input:text[name=nama_jenis]').val("");
  $('input:text[name=jumlah_jual]').attr("disabled", true);
  $('input:text[name=potongan]').attr("disabled", true);
  $('input:text[name=harga_jual]').val("");
  $('input:text[name=total]').val("");
  $('input:text[name=no_reg]').val("");
}

$(document).click(function (event) {
    $('.dropdown-menu[data-parent]').hide();
});
$(document).on('click', '.table-responsive [data-toggle="dropdown"]', function () {
    if ($('body').hasClass('modal-open')) {
        throw new Error("This solution is not working inside a responsive table inside a modal, you need to find out a way to calculate the modal Z-index and add it to the element")
        return true;
    }

    $buttonGroup = $(this).parent();
    if (!$buttonGroup.attr('data-attachedUl')) {
        var ts = +new Date;
        $ul = $(this).siblings('ul');
        $ul.attr('data-parent', ts);
        $buttonGroup.attr('data-attachedUl', ts);
        $(window).resize(function () {
            $ul.css('display', 'none').data('top');
        });
    } else {
        $ul = $('[data-parent=' + $buttonGroup.attr('data-attachedUl') + ']');
    }
    if (!$buttonGroup.hasClass('open')) {
        $ul.css('display', 'none');
        return;
    }
    dropDownFixPosition($(this).parent(), $ul);
    function dropDownFixPosition(button, dropdown) {
        var dropDownTop = button.offset().top + button.outerHeight();
        dropdown.css('top', dropDownTop-60 + "px");
        dropdown.css('left', button.offset().left+7 + "px");
        dropdown.css('position', "absolute");

        dropdown.css('width', dropdown.width());
        dropdown.css('heigt', dropdown.height());
        dropdown.css('display', 'block');
        dropdown.appendTo('body');
    }
});

$('body').on('hidden.bs.modal', '.modal', function () {
    $(this).removeData('bs.modal');
});

$("#form").on("click","#jam_reg", function(event){
    var baseURL = mlite.url + '/' + mlite.admin;
    var url = baseURL + '/igd/cekwaktu?t=' + mlite.token;
    $.post(url, {
    } ,function(data) {
      $("#form #jam_reg").val(data);
    });
});

$("#form_rincian").on("click","#jam_reg", function(event){
    var baseURL = mlite.url + '/' + mlite.admin;
    var url = baseURL + '/igd/cekwaktu?t=' + mlite.token;
    $.post(url, {
    } ,function(data) {
      $("#form_rincian #jam_reg").val(data);
    });
});

$("#form_berkasdigital").on("click","#jam_reg", function(event){
    var baseURL = mlite.url + '/' + mlite.admin;
    var url = baseURL + '/igd/cekwaktu?t=' + mlite.token;
    $.post(url, {
    } ,function(data) {
      $("#form_berkasdigital #jam_reg").val(data);
    });
});

$("#form_soap").on("click","#jam_rawat", function(event){
    var baseURL = mlite.url + '/' + mlite.admin;
    var url = baseURL + '/igd/cekwaktu?t=' + mlite.token;
    $.post(url, {
    } ,function(data) {
      $("#jam_rawat").val(data);
    });
});
