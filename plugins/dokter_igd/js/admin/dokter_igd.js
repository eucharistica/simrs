// sembunyikan form dan notif
$("#form_rincian").hide();
$("#form_soap").hide();
$("#form_sep").hide();
$("#histori_pelayanan").hide();
$("#notif").hide();
$('#provider').hide();
$('#aturan_pakai').hide();
$('#daftar_racikan').hide();
$("#info_tambahan").hide();
$("#form_kontrol").hide();

$("#display").on("click",".riwayat_perawatan", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  var no_rkm_medis = $(this).attr("data-no_rkm_medis");
  window.open(baseURL + '/pasien/riwayatperawatan/' + no_rkm_medis + '?t=' + mlite.token);
});

$('#manage').on('click', '#submit_periode_rawat_jalan', function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  var url    = baseURL + '/dokter_igd/display?t=' + mlite.token;
  var periode_rawat_jalan  = $('input:text[name=periode_rawat_jalan]').val();
  var periode_rawat_jalan_akhir  = $('input:text[name=periode_rawat_jalan_akhir]').val();
  var status_periksa = 'semua';

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
  var url    = baseURL + '/dokter_igd/display?t=' + mlite.token;
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
  var url    = baseURL + '/dokter_igd/display?t=' + mlite.token;
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
  var url    = baseURL + '/dokter_igd/display?t=' + mlite.token;
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

    var url = baseURL + '/dokter_igd/savesoap?t=' + mlite.token;
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
      var url = baseURL + '/dokter_igd/soap?t=' + mlite.token;
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
  var url = baseURL + '/dokter_igd/hapussoap?t=' + mlite.token;
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
        var url = baseURL + '/dokter_igd/soap?t=' + mlite.token;
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

// ketika tombol simpan diklik
$("#form_kontrol").on("click", "#simpan_kontrol", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();

  var no_rkm_medis    = $('input:text[name=no_rkm_medis]').val();
  var no_rawat        = $('input:text[name=no_rawat]').val();
  var tanggal_rujukan = $('input:text[name=tanggal_rujukan]').val();
  var tanggal_datang  = $('input:text[name=tanggal_datang]').val();
  var diagnosa        = $('input:text[name=diagnosa]').val();
  var terapi          = $('input:text[name=terapi]').val();
  var alasan1         = $('textarea[name=alasan1]').val();
  var rtl1            = $('textarea[name=rtl1]').val();

  var url = baseURL + '/dokter_igd/savekontrol?t=' + mlite.token;
  $.post(url, {no_rawat : no_rawat,
  no_rkm_medis   : no_rkm_medis,
  tanggal_rujukan       : tanggal_rujukan,
  tanggal_datang  : tanggal_datang,
  diagnosa : diagnosa,
  terapi  : terapi,
  alasan1      : alasan1,
  rtl1          : rtl1
  }, function(data) {
    // tampilkan data
    $("#display").hide();
    var url = baseURL + '/dokter_igd/kontrol?t=' + mlite.token;
    $.post(url, {no_rkm_medis : no_rkm_medis,
    }, function(data) {
      // tampilkan data
      $("#kontrol").html(data).show();
    });
    $('input:text[name=nm_perawatan]').val("");
    $('input:text[name=biaya]').val("");
    $('input:text[name=diagnosa_klinis]').val("");
    $('input:text[name=nama_provider]').val("");
    $('input:text[name=nama_provider2]').val("");
    $('input:text[name=kode_provider]').val("");
    $('input:text[name=kode_provider2]').val("");
    $('input:text[name=racikan]').val("");
    $('input:text[name=nama_racik]').val("");
    $('#notif').html("<div class=\"alert alert-success alert-dismissible fade in\" role=\"alert\" style=\"border-radius:0px;margin-top:-15px;\">"+
    "Data surat kontrol telah disimpan!"+
    "<button type=\"button\" class=\"close\" data-dismiss=\"alert\" aria-label=\"Close\">&times;</button>"+
    "</div>").show();
  });
});

// tombol batal diklik
$("#form_rincian").on("click", "#selesai", function(event){
  bersih();
  $("#form_rincian").hide();
  $("#form_soap").hide();
  $("#form").show();
  $("#display").show();
  $("#rincian").hide();
  $("#soap").hide();
  $('#aturan_pakai').hide();
  $('#daftar_racikan').hide();
  $("#info_tambahan").hide();
  $("#form_kontrol").hide();
  $("#kontrol").hide();
  $("#surat_kontrol").hide();
});

// tombol batal diklik
$("#form_soap").on("click", "#selesai_soap", function(event){
  bersih();
  $("#form_rincian").hide();
  $("#form_soap").hide();
  $("#form").show();
  $("#display").show();
  $("#rincian").hide();
  $("#soap").hide();
  $('#aturan_pakai').hide();
  $('#daftar_racikan').hide();
  $("#info_tambahan").hide();
  $("#form_kontrol").hide();
  $("#kontrol").hide();
  $("#surat_kontrol").hide();
});

// tombol batal diklik
$("#form_kontrol").on("click", "#selesai_kontrol", function(event){
  bersih();
  $("#form_rincian").hide();
  $("#form_soap").hide();
  $("#form").show();
  $("#display").show();
  $("#rincian").hide();
  $("#soap").hide();
  $('#aturan_pakai').hide();
  $('#daftar_racikan').hide();
  $("#info_tambahan").hide();
  $("#form_kontrol").hide();
  $("#kontrol").hide();
  $("#surat_kontrol").hide();
});

// ketika inputbox pencarian diisi
$('input:text[name=layanan]').on('input',function(e){
  var baseURL = mlite.url + '/' + mlite.admin;
  var url    = baseURL + '/dokter_igd/layanan?t=' + mlite.token;
  var layanan = $('input:text[name=layanan]').val();

  if(layanan!="") {
      $.post(url, {layanan: layanan} ,function(data) {
      // tampilkan data yang sudah di perbaharui
        $("#layanan").html(data).show();
        $("#obat").hide();
        $("#racikan").hide();
      });
  }

});
// end pencarian

// ketika inputbox pencarian diisi
$('input:text[name=obat]').on('input',function(e){
  var baseURL = mlite.url + '/' + mlite.admin;
  var url    = baseURL + '/dokter_igd/obat?t=' + mlite.token;
  var obat = $('input:text[name=obat]').val();

  if(obat!="") {
      $.post(url, {obat: obat} ,function(data) {
      // tampilkan data yang sudah di perbaharui
        $("#obat").html(data).show();
        $("#layanan").hide();
        $("#racikan").hide();
        $("#radiologi").hide();
        $("#laboratorium").hide();
      });
  }

});
// end pencarian

// ketika inputbox pencarian diisi
$('input:text[name=racikan]').on('input',function(e){
  var baseURL = mlite.url + '/' + mlite.admin;
  var url    = baseURL + '/dokter_igd/racikan?t=' + mlite.token;
  var racikan = $('input:text[name=racikan]').val();

  if(racikan!="") {
      $.post(url, {racikan: racikan} ,function(data) {
      // tampilkan data yang sudah di perbaharui
        $("#racikan").html(data).show();
        $("#layanan").hide();
        $("#obat").hide();
        $("#radiologi").hide();
        $("#laboratorium").hide();
      });
  }

});
// end pencarian

// ketika inputbox pencarian diisi
$('.nama_brng').on('input',function(e){
  var baseURL = mlite.url + '/' + mlite.admin;
  var url    = baseURL + '/dokter_igd/obatracikan?t=' + mlite.token;
  var obat = $('.nama_brng').val();

  if(obat!="") {
      $.post(url, {obat: obat} ,function(data) {
      // tampilkan data yang sudah di perbaharui
        $("#obat_racikan").html(data).show();
        $("#layanan").hide();
        $("#racikan").hide();
        $("#radiologi").hide();
        $("#laboratorium").hide();
      });
  }

});
// end pencarian

// ketika inputbox pencarian diisi
$('input:text[name=laboratorium]').on('input',function(e){
  var baseURL = mlite.url + '/' + mlite.admin;
  var url    = baseURL + '/dokter_igd/laboratorium?t=' + mlite.token;
  var laboratorium = $('input:text[name=laboratorium]').val();

  if(laboratorium!="") {
      $.post(url, {laboratorium: laboratorium} ,function(data) {
      // tampilkan data yang sudah di perbaharui
        $("#laboratorium").html(data).show();
        $("#layanan").hide();
        $("#obat").hide();
        $("#racikan").hide();
        $("#radiologi").hide();
      });
  }

});
// end pencarian

// ketika inputbox pencarian diisi
$('input:text[name=radiologi]').on('input',function(e){
  var baseURL = mlite.url + '/' + mlite.admin;
  var url    = baseURL + '/dokter_igd/radiologi?t=' + mlite.token;
  var radiologi = $('input:text[name=radiologi]').val();

  if(radiologi!="") {
      $.post(url, {radiologi: radiologi} ,function(data) {
      // tampilkan data yang sudah di perbaharui
        $("#radiologi").html(data).show();
        $("#layanan").hide();
        $("#obat").hide();
        $("#laboratorium").hide();
        $("#racikan").hide();
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
  $('#racikan').hide();
  $("#laboratorium").hide();
  $("#radiologi").hide();
  $('#daftar_racikan').hide();
  $("#info_tambahan").hide();
});

// ketika baris data diklik
$("#obat").on("click", ".pilih_obat", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();

  var kode_brng = $(this).attr("data-kode_brng");
  var nama_brng = $(this).attr("data-nama_brng");
  var biaya = $(this).attr("data-ralan");
  var stok = $(this).attr("data-stok");
  var stokminimal = $(this).attr("data-stokminimal");
  var kat = $(this).attr("data-kat");

  if(stok < stokminimal) {
    alert('Stok obat ' + nama_brng + ' tidak mencukupi.');
    $('input:hidden[name=kd_jenis_prw]').val();
    $('input:text[name=nm_perawatan]').val();
    $('input:text[name=biaya]').val();
    $('input:hidden[name=kat]').val();
  } else {
    $('input:hidden[name=kd_jenis_prw]').val(kode_brng);
    $('input:text[name=nm_perawatan]').val(nama_brng);
    $('input:text[name=biaya]').val(biaya);
    $('input:hidden[name=kat]').val(kat);
  }

  $('#obat').hide();
  $('#racikan').hide();
  $("#laboratorium").hide();
  $("#radiologi").hide();
  $('#daftar_racikan').hide();
  $('#aturan_pakai').show();
  $("#info_tambahan").hide();
});

// ketika baris data diklik
$("#racikan").on("click", ".pilih_racikan", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();

  var kd_racik = $(this).attr("data-kd_racik");
  var nm_racik = $(this).attr("data-nm_racik");
  var kat = $(this).attr("data-kat");

  $('input:hidden[name=kd_jenis_prw]').val(kd_racik);
  $('input:text[name=nm_perawatan]').val(nm_racik);
  $('input:text[name=biaya]').val('');
  $('input:hidden[name=kat]').val(kat);

  $('#racikan').hide();
  $("#laboratorium").hide();
  $("#radiologi").hide();
  $('#aturan_pakai').show();
  $('#daftar_racikan').show();
  $("#info_tambahan").hide();
});

// ketika baris data diklik
$("#obat_racikan").on("click", ".pilih_obat_racikan", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();

  var kode_brng = $(this).attr("data-kode_brng");
  var nama_brng = $(this).attr("data-nama_brng");
  var biaya = $(this).attr("data-ralan");
  var stok = $(this).attr("data-stok");

  if(stok < 10) {
    alert('Stok obat ' + nama_brng + ' tidak mencukupi.');
    $('input:hidden[name=kode_brng]').val();
    $('input:text[name=nama_brng]').val();
    $('input:text[name=biaya]').val();
  } else {
    $('input:hidden[name=kode_brng]').val(kode_brng);
    $('input:text[name=nama_brng]').val(nama_brng);
    $('input:text[name=biaya]').val(biaya);
  }

  $('#obat').hide();
  $('#racikan').hide();
  $("#laboratorium").hide();
  $("#radiologi").hide();
  //$('#daftar_racikan').hide();
  $('#aturan_pakai').show();
  $("#info_tambahan").hide();
});

// ketika baris data diklik
$("#laboratorium").on("click", ".pilih_laboratorium", function(event){
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
  $('#racikan').hide();
  $("#laboratorium").hide();
  $("#radiologi").hide();
  $('#daftar_racikan').hide();
  $("#info_tambahan").show();
});

// ketika baris data diklik
$("#radiologi").on("click", ".pilih_radiologi", function(event){
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
  $('#racikan').hide();
  $("#laboratorium").hide();
  $("#radiologi").hide();
  $('#daftar_racikan').hide();
  $("#info_tambahan").show();
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
  var nama_racik      = $('input:text[name=nama_racik]').val();
  var keterangan      = $('textarea[name=keterangan]').val();
  var kode_brng       = JSON.stringify($('select[name=kode_brng]').serializeArray());
  var kandungan       = JSON.stringify($('input:text[name=kandungan]').serializeArray());
  var diagnosa_klinis = $('input:text[name=diagnosa_klinis]').val();
  var informasi_tambahan = $('textarea[name=informasi_tambahan]').val();

  var url = baseURL + '/dokter_igd/savedetail?t=' + mlite.token;
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
  jml            : jml,
  nama_racik     : nama_racik,
  keterangan     : keterangan,
  kode_brng      : kode_brng,
  kandungan      : kandungan,
  informasi_tambahan : informasi_tambahan,
  diagnosa_klinis      : diagnosa_klinis
  }, function(data) {
    // tampilkan data
    $("#display").hide();
    var url = baseURL + '/dokter_igd/rincian?t=' + mlite.token;
    $.post(url, {no_rawat : no_rawat,
    }, function(data) {
      // tampilkan data
      $("#rincian").html(data).show();
    });
    $('input:hidden[name=kd_jenis_prw]').val("");
    $('input:text[name=nm_perawatan]').val("");
    $('input:hidden[name=kat]').val("");
    $('input:text[name=biaya]').val("");
    $('input:text[name=diagnosa_klinis]').val("");
    $('#informasi_tambahan').val("");
    $('input:text[name=nama_provider]').val("");
    $('input:text[name=nama_provider2]').val("");
    $('input:text[name=kode_provider]').val("");
    $('input:text[name=kode_provider2]').val("");
    $('input:text[name=racikan]').val("");
    $('input:text[name=nama_racik]').val("");
    $('#kode_brng').val("");
    $('#keterangan').val("");
    $('input:text[name=kandungan]').val("");
    $('.row_racikan').remove();
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
  var url = baseURL + '/dokter_igd/hapusdetail?t=' + mlite.token;
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
        var url = baseURL + '/dokter_igd/rincian?t=' + mlite.token;
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
  var url = baseURL + '/dokter_igd/hapusresep?t=' + mlite.token;
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
        var url = baseURL + '/dokter_igd/rincian?t=' + mlite.token;
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
  var url = baseURL + '/dokter_igd/hapusresep?t=' + mlite.token;
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
        var url = baseURL + '/dokter_igd/rincian?t=' + mlite.token;
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
$("#rincian").on("click",".copy_resep", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  var url = baseURL + '/dokter_igd/copyresep?t=' + mlite.token;
  var no_resep  = $(this).attr("data-no_resep");

  $.post(url, {no_resep: no_resep} ,function(data) {
    // tampilkan data
    $("#display_copy_resep").html(data).show();
  });

});

// ketika tombol hapus ditekan
$("#rincian").on("click","#simpan_copy_resep", function(event){
//$('form').on('submit', function(event){
  //alert('submit copy');
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  var url_save = baseURL + '/dokter_igd/savecopyresep?t=' + mlite.token;
  var url = baseURL + '/dokter_igd/rincian?t=' + mlite.token;
  var no_rawat = $('input:text[name=no_rawat]').val();
  var tgl_perawatan   = $('input:text[name=tgl_perawatan]').val();
  var jam_rawat       = $('input:text[name=jam_reg]').val();
  var kode_brng       = JSON.stringify($('input:hidden[name=kode_brng_copyresep]').serializeArray());
  var jml       = JSON.stringify($('input:text[name=jml_copyresep]').serializeArray());
  var aturan_pakai       = JSON.stringify($('input:hidden[name=aturan_copyresep]').serializeArray());

  $.post(url_save, {no_rawat : no_rawat,
    tgl_perawatan : tgl_perawatan,
    jam_rawat : jam_rawat,
    kode_brng : kode_brng,
    jml : jml,
    aturan_pakai : aturan_pakai
  }, function(data) {
    //alert(data);
    //if(data == 'ErrorError') {
    //  alert('Stok tidak mencukupi pada satu atau lebih obat.');
    //} else {
      $.post(url, {no_rawat : no_rawat,
      }, function(data) {
        // tampilkan data
        $("#rincian").html(data).show();
      });
      $('#notif').html("<div class=\"alert alert-success alert-dismissible fade in\" role=\"alert\" style=\"border-radius:0px;margin-top:-15px;\">"+
      "Data pasien telah disimpan!"+
      "<button type=\"button\" class=\"close\" data-dismiss=\"alert\" aria-label=\"Close\">&times;</button>"+
      "</div>").show();

    //}
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
  $('input:text[name=racikan]').val("");
  $('input:text[name=nama_racik]').val("");
  $('#kode_brng').val("");
  $('#keterangan').val("");
  $('input:text[name=kandungan]').val("");
  $('input:text[name=nm_perawatan]').val("");
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

$(document).ready(function () {
  var strip_tags = function(str) {
    return (str + '').replace(/<\/?[^>]+(>|$)/g, '')
  };
  var truncate_string = function(str, chars) {
    if ($.trim(str).length <= chars) {
      return str;
    } else {
      return $.trim(str.substr(0, chars)) + '...';
    }
  };
  $('select').selectator('destroy');
  $('.databarang_ajax').selectator({
    labels: {
      search: 'Cari obat...'
    },
    load: function (search, callback) {
      if (search.length < this.minSearchLength) return callback();
      $.ajax({
        url: '{?=url()?}/{?=ADMIN?}/dokter_igd/ajax?show=databarang&nama_brng=' + encodeURIComponent(search) + '&t={?=$_SESSION['token']?}',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
          callback(data.slice(0, 100));
          console.log(data);
        },
        error: function() {
          callback();
        }
      });
    },
    delay: 300,
    minSearchLength: 1,
    valueField: 'kode_brng',
    textField: 'nama_brng'
  });
  $('.master_aturan_pakai').selectator({
    labels: {
      search: 'Cari aturan pakai...'
    },
    load: function (search, callback) {
      if (search.length < this.minSearchLength) return callback();
      $.ajax({
        url: '{?=url()?}/{?=ADMIN?}/dokter_ralan/ajax?show=aturan_pakai&aturan=' + encodeURIComponent(search) + '&t={?=$_SESSION['token']?}',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
          callback(data.slice(0, 100));
          console.log(data);
        },
        error: function() {
          callback();
        }
      });
    },
    delay: 300,
    minSearchLength: 1,
    valueField: 'aturan',
    textField: 'aturan'
  });
  $('.jns_perawatan').selectator({
    labels: {
      search: 'Cari perawatan...'
    },
    load: function (search, callback) {
      if (search.length < this.minSearchLength) return callback();
      $.ajax({
        url: '{?=url()?}/{?=ADMIN?}/dokter_ralan/ajax?show=jns_perawatan&nm_perawatan=' + encodeURIComponent(search) + '&t={?=$_SESSION['token']?}',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
          callback(data.slice(0, 100));
          console.log(data);
        },
        error: function() {
          callback();
        }
      });
    },
    delay: 300,
    minSearchLength: 1,
    valueField: 'kd_jenis_prw',
    textField: 'nm_perawatan'
  });
  $('.jns_perawatan_lab_ajax').selectator({
    labels: {
      search: 'Cari perawatan lab...'
    },
    load: function (search, callback) {
      if (search.length < this.minSearchLength) return callback();
      $.ajax({
        url: '{?=url()?}/{?=ADMIN?}/dokter_ralan/ajax?show=jns_perawatan_lab&nm_perawatan=' + encodeURIComponent(search) + '&t={?=$_SESSION['token']?}',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
          callback(data.slice(0, 100));
          console.log(data);
        },
        error: function() {
          callback();
        }
      });
    },
    delay: 300,
    minSearchLength: 1,
    valueField: 'kd_jenis_prw',
    textField: 'nm_perawatan'
  });
  $('.jns_perawatan_rad_ajax').selectator({
    labels: {
      search: 'Cari perawatan radiologi...'
    },
    load: function (search, callback) {
      if (search.length < this.minSearchLength) return callback();
      $.ajax({
        url: '{?=url()?}/{?=ADMIN?}/dokter_ralan/ajax?show=jns_perawatan_radiologi&nm_perawatan=' + encodeURIComponent(search) + '&t={?=$_SESSION['token']?}',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
          callback(data.slice(0, 100));
          console.log(data);
        },
        error: function() {
          callback();
        }
      });
    },
    delay: 300,
    minSearchLength: 1,
    valueField: 'kd_jenis_prw',
    textField: 'nm_perawatan'
  });
  $('select').selectator();
});

$("#form_soap").on("click","#jam_rawat", function(event){
    var baseURL = mlite.url + '/' + mlite.admin;
    var url = baseURL + '/dokter_igd/cekwaktu?t=' + mlite.token;
    $.post(url, {
    } ,function(data) {
      $("#jam_rawat").val(data);
    });
});

$("#form_rincian").on("click","#jam_reg", function(event){
    var baseURL = mlite.url + '/' + mlite.admin;
    var url = baseURL + '/dokter_igd/cekwaktu?t=' + mlite.token;
    $.post(url, {
    } ,function(data) {
      $("#form_rincian #jam_reg").val(data);
    });
});
// Modal permintaan laboratorium/radiologi dari SOAP IGD
$(document).on('click', '#form_soap #permintaan_lab_soap', function(event){
  event.preventDefault();
  var baseURL = mlite.url + '/' + mlite.admin;
  var no_rawat = $('#form_soap input:text[name=no_rawat]').val();
  if (!no_rawat) { bootbox.alert('Nomor rawat belum tersedia.'); return false; }
  var modal = $('#permintaanLabRadModal');
  modal.data({no_rawat:no_rawat, tgl_perawatan:$('#form_soap input:text[name=tgl_perawatan]').val(), jam_rawat:$('#form_soap input:text[name=jam_rawat]').val(), pemeriksaanDipilih:[]});
  modal.find('#permintaanLabRadPasien').text('Pasien: ' + $('#form_soap input:text[name=nm_pasien]').val() + ' | No. RM: ' + $('#form_soap input:text[name=no_rkm_medis]').val() + ' | No. Rawat: ' + no_rawat);
  modal.find('#modalLaboratorium, #modalRadiologi, #modalInformasi, #modalDiagnosa').val('');
  modal.find('#modalLaboratoriumList, #modalRadiologiList').empty();
  renderIgdPemeriksaanDipilih(modal);
  modal.modal('show');
  $.post(baseURL + '/dokter_igd/rincian?t=' + mlite.token, {no_rawat:no_rawat}, function(data){
    var wrapper = $('<div>').html(data), output = $('<div>');
    var lab = wrapper.find('#lab').first().clone(), rad = wrapper.find('#rad').first().clone();
    if (lab.length) output.append(lab.removeAttr('id').show());
    if (rad.length) output.append(rad.removeAttr('id').show());
    modal.find('#modalPermintaanTersimpan').html(output.html() || '<div class="text-muted">Belum ada permintaan.</div>');
  }).fail(function(){ modal.find('#modalPermintaanTersimpan').html('<div class="alert alert-danger">Gagal memuat permintaan.</div>'); });
  return false;
});

$(document).on('input', '#permintaanLabRadModal #modalLaboratorium, #permintaanLabRadModal #modalRadiologi', function(){
  var input=$(this), query=input.val(), isLab=input.attr('id') === 'modalLaboratorium';
  var target=$(isLab ? '#modalLaboratoriumList' : '#modalRadiologiList');
  if(query.length < 2){ target.empty(); return; }
  $.post(mlite.url+'/'+mlite.admin+'/dokter_igd/'+(isLab?'laboratorium':'radiologi')+'?t='+mlite.token, isLab?{laboratorium:query}:{radiologi:query}, function(data){ target.html(data); });
});

function renderIgdPemeriksaanDipilih(modal){
  var selected=modal.data('pemeriksaanDipilih')||[], html='';
  selected.forEach(function(item,index){ html+='<div class="well well-sm" style="margin-bottom:5px;"><button type="button" class="close hapus-igd-pemeriksaan" data-index="'+index+'">&times;</button><strong>'+ $('<div>').text(item.nm_perawatan).html() +'</strong> <small>('+item.kat+' | '+item.kd_jenis_prw+' | Rp. '+item.biaya+')</small></div>'; });
  modal.find('#modalPemeriksaanDipilih').html(html||'<div class="text-muted">Belum ada pemeriksaan yang dipilih.</div>');
}

$(document).on('click', '#permintaanLabRadModal .pilih_laboratorium, #permintaanLabRadModal .pilih_radiologi', function(event){
  event.preventDefault(); var modal=$('#permintaanLabRadModal'), row=$(this), selected=modal.data('pemeriksaanDipilih')||[];
  var item={kd_jenis_prw:row.attr('data-kd_jenis_prw')||'',nm_perawatan:row.attr('data-nm_perawatan')||'',biaya:row.attr('data-biaya')||'',kat:row.attr('data-kat')||''};
  if(item.kd_jenis_prw && !selected.some(function(x){return x.kat===item.kat&&x.kd_jenis_prw===item.kd_jenis_prw;})) selected.push(item);
  modal.data('pemeriksaanDipilih',selected); renderIgdPemeriksaanDipilih(modal);
  modal.find('#modalLaboratoriumList, #modalRadiologiList, #modalLaboratorium, #modalRadiologi').empty().val('');
});

$(document).on('click', '#permintaanLabRadModal .hapus-igd-pemeriksaan', function(){
  var modal=$('#permintaanLabRadModal'), selected=modal.data('pemeriksaanDipilih')||[];
  selected.splice(parseInt($(this).attr('data-index'),10),1); modal.data('pemeriksaanDipilih',selected); renderIgdPemeriksaanDipilih(modal);
});

$(document).on('click', '#permintaanLabRadModal #modalSimpanPermintaan', function(event){
  event.preventDefault(); var modal=$('#permintaanLabRadModal'), selected=modal.data('pemeriksaanDipilih')||[], no_rawat=modal.data('no_rawat'), button=$(this);
  if(!selected.length || !no_rawat){ bootbox.alert('Pilih minimal satu pemeriksaan terlebih dahulu.'); return false; }
  button.prop('disabled',true);
  var save=function(index){
    if(index>=selected.length){ button.prop('disabled',false); modal.data('pemeriksaanDipilih',[]); renderIgdPemeriksaanDipilih(modal); bootbox.alert('Sebanyak '+selected.length+' pemeriksaan berhasil disimpan.'); return; }
    $.post(mlite.url+'/'+mlite.admin+'/dokter_igd/savedetail?t='+mlite.token,{no_rawat:no_rawat,kd_jenis_prw:selected[index].kd_jenis_prw,kat:selected[index].kat,tgl_perawatan:modal.data('tgl_perawatan'),jam_rawat:modal.data('jam_rawat'),informasi_tambahan:modal.find('#modalInformasi').val()||'-',diagnosa_klinis:modal.find('#modalDiagnosa').val()||'-'}).done(function(){save(index+1);}).fail(function(){button.prop('disabled',false);bootbox.alert('Penyimpanan berhenti pada pemeriksaan ke-'+(index+1)+'.');});
  };
  save(0); return false;
});

$(document).on('click', '#rincian .hapus_permintaan_rad', function(event){
  event.preventDefault();
  var button=$(this), no_rawat=button.attr('data-no_rawat'), noorder=button.attr('data-noorder');
  bootbox.confirm('Apakah Anda yakin ingin menghapus data radiologi ini?', function(result){
    if(!result) return;
    $.post(mlite.url+'/'+mlite.admin+'/dokter_igd/hapuspermintaanradiologi?t='+mlite.token,{no_rawat:no_rawat,noorder:noorder}).done(function(){
      $.post(mlite.url+'/'+mlite.admin+'/dokter_igd/rincian?t='+mlite.token,{no_rawat:no_rawat},function(data){$('#rincian').html(data).show();});
    }).fail(function(){bootbox.alert('Data radiologi bukan dibuat oleh user ini atau sudah tidak dapat dihapus.');});
  });
});

// Triase IGD V1
$(document).on('change', '#triaseIgdModal #triaseJenis', function(){
  var sekunder = $(this).val() === 'sekunder';
  $('#triasePrimerFields').toggle(!sekunder);
  $('#triaseSekunderFields').toggleClass('hidden', !sekunder);
});

function renderIgdSkala(modal, master, terpilih, skala) {
  var rows = master['skala'+skala] || [], selected = {};
  (terpilih['skala'+skala] || []).forEach(function(row){ selected[row['kode_skala'+skala]] = true; });
  var grouped = {};
  rows.forEach(function(row){ var key=row.kode_pemeriksaan; if(!grouped[key]) grouped[key]={nama:row.nama_pemeriksaan, items:[]}; grouped[key].items.push(row); });
  var html='';
  Object.keys(grouped).forEach(function(key){
    html += '<div class="panel panel-default"><div class="panel-heading"><strong>'+grouped[key].nama+'</strong></div><div class="panel-body">';
    grouped[key].items.forEach(function(row){
      var checked=selected[row['kode_skala'+skala]]?' checked':'';
      html += '<label class="checkbox-inline" style="display:block;margin:4px 0;"><input type="checkbox" class="triase-skala" data-skala="'+skala+'" value="'+row['kode_skala'+skala]+'"'+checked+'> '+row['pengkajian_skala'+skala]+'</label>';
    });
    html += '</div></div>';
  });
  modal.find('#triaseSkalaContainer').append('<div class="triase-skala-panel" data-skala-panel="'+skala+'" style="border-left:4px solid '+({1:'#cc0000',2:'#e0b000',3:'#009900',4:'#0066cc',5:'#969696'}[skala])+';padding-left:10px;"><h5>Skala '+skala+'</h5>'+ (html || '<div class="text-muted">Master belum tersedia.</div>') +'</div>');
}

function activateIgdSkalaTab(modal, skala) {
  modal.find('.triase-skala-panel').hide();
  modal.find('[data-skala-panel="'+skala+'"]').show();
  modal.find('#triaseSkalaTabs li').removeClass('active');
  modal.find('#triaseSkalaTabs li[data-skala="'+skala+'"]').addClass('active');
}

function toggleIgdSkala(modal) {
  var sekunder=modal.find('#triaseJenis').val()==='sekunder';
  var scales=sekunder ? [3,4,5] : [1,2];
  var labels={1:'Merah (ATS 1)',2:'Kuning (ATS 2)',3:'Hijau (ATS 3)',4:'Biru (ATS 4)',5:'Putih (ATS 5)'};
  var tabs='<ul class="nav nav-tabs" id="triaseSkalaTabs" style="margin-bottom:15px;">';
  scales.forEach(function(skala){ tabs+='<li role="presentation" data-skala="'+skala+'"><a href="#">'+labels[skala]+'</a></li>'; });
  tabs+='</ul>';
  modal.find('#triaseSkalaTabsContainer').html(tabs);
  modal.find('#triasePlanPrimer').toggle(!sekunder);
  modal.find('#triasePlanSekunder').toggleClass('hidden',!sekunder);
  activateIgdSkalaTab(modal, scales[0]);
}

function localTriaseDatetime(value) {
  if (value) return String(value).replace(' ', 'T').slice(0,16);
  var now=new Date(), pad=function(n){return String(n).padStart(2,'0');};
  return now.getFullYear()+'-'+pad(now.getMonth()+1)+'-'+pad(now.getDate())+'T'+pad(now.getHours())+':'+pad(now.getMinutes());
}

$(document).on('click', '#form_soap #buka_triase_igd', function(event){
  event.preventDefault();
  var soapForm=$('#form_soap'), no_rawat=$.trim(String(soapForm.data('no_rawat') || soapForm.find('input:text[name=no_rawat]').val() || ''));
  if(!no_rawat){ bootbox.alert('Nomor rawat belum tersedia.'); return false; }
  var modal=$('#triaseIgdModal');
  modal.find('input, textarea').not('#triaseNoRawat').val('');
  modal.find('input[type=radio], input[type=checkbox]').prop('checked',false);
  modal.find('#triaseCaraMasuk').val('Jalan');
  modal.find('#triaseTransportasi').val('Sendiri');
  modal.find('#triaseEmergency').val('True Emergency');
  modal.find('#triaseKasus').val('002');
  modal.find('#triaseJenis').val('primer').trigger('change');
  modal.find('#triaseDatetime').val(localTriaseDatetime());
  modal.find('#triaseNoRawat').val(no_rawat);
  modal.find('#triaseIgdPasien').text('Pasien: '+(soapForm.data('nm_pasien') || soapForm.find('input:text[name=nm_pasien]').val())+' | No. Rawat: '+no_rawat);
  modal.find('#triaseStatus').empty();
  var kasusSelect=modal.find('#triaseKasus');
  kasusSelect.val('002').trigger('change');
  $.post(mlite.url+'/'+mlite.admin+'/dokter_igd/triase?t='+mlite.token,{no_rawat:no_rawat},function(data){
    var base=data.igd||{}, asesmenFallback=data.asesmen||{}, primer=data.primer||{}, sekunder=data.sekunder||{};
    var set=function(id,key,obj){ if(obj[key] !== undefined && obj[key] !== null) modal.find(id).val(obj[key]); };
    var setVitalIfEmpty=function(id,key){ var el=modal.find(id); if(!el.val() && asesmenFallback[key] !== undefined && asesmenFallback[key] !== null && String(asesmenFallback[key]).trim() !== '') el.val(asesmenFallback[key]); };
    set('#triaseCaraMasuk','cara_masuk',base); set('#triaseTransportasi','alat_transportasi',base); set('#triaseEmergency','emergency',base);
    if (!base.cara_masuk) modal.find('#triaseCaraMasuk').val('Jalan');
    if (!base.alat_transportasi) modal.find('#triaseTransportasi').val('Sendiri');
    if (!base.emergency) modal.find('#triaseEmergency').val('True Emergency');
    set('#triaseTensi','tekanan_darah',base); set('#triaseNadi','nadi',base); set('#triaseRespirasi','pernapasan',base); set('#triaseSuhu','suhu',base); set('#triaseSaturasi','saturasi_o2',base); set('#triaseBb','bb',base);
    // Pertahankan data triase bila ada; jika belum ada, ambil tanda vital dari asesmen medis.
    setVitalIfEmpty('#triaseTensi','td'); setVitalIfEmpty('#triaseNadi','nadi'); setVitalIfEmpty('#triaseRespirasi','rr'); setVitalIfEmpty('#triaseSuhu','suhu'); setVitalIfEmpty('#triaseSaturasi','spo'); setVitalIfEmpty('#triaseBb','bb');
    set('#triaseNyeri','nyeri',base); if (base.kode_kasus && kasusSelect.find('option[value="'+base.kode_kasus+'"]').length) kasusSelect.val(base.kode_kasus).trigger('change'); else kasusSelect.val('002').trigger('change'); set('#triaseAlasan','alasan_kedatangan',base); set('#triaseKeterangan','keterangan_kedatangan',base);
    modal.find('#triaseDatetime').val(localTriaseDatetime(base.tgl_kunjungan || primer.tanggaltriase || sekunder.tanggaltriase));
    modal.find('#triaseSkalaContainer').empty();
    [1,2,3,4,5].forEach(function(skala){ renderIgdSkala(modal,data.master||{},data.terpilih||{},skala); });
    if(Object.keys(sekunder).length){ modal.find('#triaseJenis').val('sekunder'); set('#triaseAnamnesa','anamnesa_singkat',sekunder); set('#triaseCatatan','catatan',sekunder); $('input[name=triase_plan][value="'+sekunder.plan+'"]').prop('checked',true); }
    else { set('#triaseKeluhan','keluhan_utama',primer); set('#triaseKebutuhan','kebutuhan_khusus',primer); set('#triaseCatatan','catatan',primer); $('input[name=triase_plan][value="'+primer.plan+'"]').prop('checked',true); }
    toggleIgdSkala(modal);
  },'json').fail(function(){ modal.find('#triaseStatus').html('<div class="alert alert-warning">Data triase belum tersedia.</div>'); });
  modal.modal('show');
  return false;
});

$(document).on('change', '#triaseIgdModal #triaseJenis', function(){ toggleIgdSkala($('#triaseIgdModal')); });

$(document).on('click', '#triaseIgdModal #triaseSkalaTabs li', function(event){
  event.preventDefault();
  activateIgdSkalaTab($('#triaseIgdModal'), $(this).data('skala'));
});

$(document).on('click', '#triaseIgdModal #simpanTriaseIgd', function(){
  var modal=$('#triaseIgdModal'), jenis=modal.find('#triaseJenis').val(), payload={
    no_rawat:modal.find('#triaseNoRawat').val(), triase_datetime:modal.find('#triaseDatetime').val(), jenis_triase:jenis, cara_masuk:modal.find('#triaseCaraMasuk').val(), alat_transportasi:modal.find('#triaseTransportasi').val(), emergency:modal.find('#triaseEmergency').val(), tekanan_darah:modal.find('#triaseTensi').val(), nadi:modal.find('#triaseNadi').val(), pernapasan:modal.find('#triaseRespirasi').val(), suhu:modal.find('#triaseSuhu').val(), saturasi_o2:modal.find('#triaseSaturasi').val(), bb:modal.find('#triaseBb').val(), nyeri:modal.find('#triaseNyeri').val(), kode_kasus:modal.find('#triaseKasus').val(), alasan_kedatangan:modal.find('#triaseAlasan').val(), keterangan_kedatangan:modal.find('#triaseKeterangan').val(), catatan:modal.find('#triaseCatatan').val(), plan:modal.find('input[name=triase_plan]:checked').val() || '', keluhan_utama:modal.find('#triaseKeluhan').val(), kebutuhan_khusus:modal.find('#triaseKebutuhan').val(), anamnesa_singkat:modal.find('#triaseAnamnesa').val()
  };
  [1,2,3,4,5].forEach(function(skala){ payload['skala'+skala]=JSON.stringify(modal.find('.triase-skala[data-skala="'+skala+'"]:checked').map(function(){return this.value;}).get()); });
  var button=$(this).prop('disabled',true);
  $.post(mlite.url+'/'+mlite.admin+'/dokter_igd/savetriase?t='+mlite.token,payload,function(data){ modal.find('#triaseStatus').html('<div class="alert alert-success">Triase berhasil disimpan.</div>'); }).fail(function(xhr){ modal.find('#triaseStatus').html('<div class="alert alert-danger">Triase gagal disimpan.</div>'); }).always(function(){button.prop('disabled',false);});
});

// Asesmen Awal Medis IGD
(function(){
  var fisikFields=[['kepala','Kepala'],['mata','Mata'],['gigi','Gigi & Mulut'],['tht','THT'],['thoraks','Thoraks'],['jantung','Jantung'],['paru','Paru'],['abdomen','Abdomen'],['genital','Genital & Anus'],['ekstremitas','Ekstremitas'],['kulit','Kulit']];
  var fisikOptions='<option>Tidak Diperiksa</option><option>Normal</option><option>Abnormal</option>';
  function ensureFisikFields(modal){
    var box=modal.find('#asesmenFisikFields');
    if(box.children().length) return;
    fisikFields.forEach(function(item){ box.append('<div class="col-md-4 form-group"><label>'+item[1]+'</label><select class="form-control asesmen-fisik" data-field="'+item[0]+'">'+fisikOptions+'</select></div>'); });
  }
  function setField(modal,id,value){ if(value !== undefined && value !== null) modal.find(id).val(value); }
  function drawMaster(canvas){
    var ctx=canvas.getContext('2d'), img=new Image();
    img.onload=function(){ canvas.width=img.naturalWidth; canvas.height=img.naturalHeight; ctx.clearRect(0,0,canvas.width,canvas.height); ctx.drawImage(img,0,0); $(canvas).data('ready',true).data('dirty',false); };
    img.src=canvas.getAttribute('data-master-src');
  }
  function drawExisting(canvas,url){
    if(!url) return;
    var ctx=canvas.getContext('2d'), img=new Image(); img.crossOrigin='anonymous';
    img.onload=function(){ canvas.width=img.naturalWidth; canvas.height=img.naturalHeight; ctx.clearRect(0,0,canvas.width,canvas.height); ctx.drawImage(img,0,0); $(canvas).data('ready',true).data('dirty',false); };
    var imagePath=String(url).trim(), imageUrl;
    if(/^https?:\/\//i.test(imagePath)) imageUrl=imagePath;
    else {
      imagePath=imagePath.replace(/^\/+/, '').replace(/^webapps\/imagefreehand\/?/i, '');
      imageUrl='https://rsudmatraman.my.id/webapps/imagefreehand/'+imagePath;
    }
    img.src=imageUrl;
  }
  $(document).on('click','#form_soap #buka_asesmen_medis_igd',function(event){
    event.preventDefault();
    var soapForm=$('#form_soap'), noRawat=soapForm.data('no_rawat') || soapForm.find('input:text[name=no_rawat]').val();
    noRawat=$.trim(String(noRawat||'')); if(!noRawat){bootbox.alert('Nomor rawat belum tersedia.');return false;}
    var modal=$('#asesmenMedisIgdModal'); ensureFisikFields(modal);
    // Bersihkan seluruh isian pasien sebelumnya sebelum mengambil data pasien baru.
    modal.find('input, textarea').not('#asesmenIgdNoRawat').val('');
    modal.find('select').not('#asesmenRingSize, #asesmenBrushSize').prop('selectedIndex',0);
    modal.find('input[type=radio], input[type=checkbox]').prop('checked',false);
    modal.find('.asesmen-fisik').val('Tidak Diperiksa');
    modal.find('#asesmenAnamnesis').val('Autoanamnesis'); modal.find('#asesmenKeadaan').val('Sehat'); modal.find('#asesmenKesadaran').val('CM');
    modal.find('#asesmenIgdNoRawat').val(noRawat); modal.find('#asesmenIgdPasien').text('Pasien: '+(soapForm.data('nm_pasien') || soapForm.find('input:text[name=nm_pasien]').val())+' | No. Rawat: '+noRawat); modal.find('#asesmenIgdStatus').empty(); modal.find('#asesmenIgdCanvasWrap').hide(); modal.find('#toggleMarkingIgd').html('<i class="fa fa-eye"></i> Tampilkan Marking Lokalis'); drawMaster(modal.find('#asesmenIgdCanvas')[0]);
    $.post(mlite.url+'/'+mlite.admin+'/dokter_igd/asesmenmedisigd?t='+mlite.token,{no_rawat:noRawat},function(data){
      var a=data.asesmen||{}, triase=data.triase||{}; var firstValue=function(primary,fallback){ return primary !== undefined && primary !== null && String(primary).trim() !== '' ? primary : fallback; };
      setField(modal,'#asesmenAnamnesis',a.anamnesis||'Autoanamnesis'); setField(modal,'#asesmenHubungan',a.hubungan); setField(modal,'#asesmenKeadaan',a.keadaan||'Sehat'); setField(modal,'#asesmenKesadaran',a.kesadaran||'CM');
      setField(modal,'#asesmenGcs',a.gcs); setField(modal,'#asesmenTd',firstValue(a.td,triase.tekanan_darah)); setField(modal,'#asesmenNadi',firstValue(a.nadi,triase.nadi)); setField(modal,'#asesmenRr',firstValue(a.rr,triase.pernapasan)); setField(modal,'#asesmenSuhu',firstValue(a.suhu,triase.suhu)); setField(modal,'#asesmenSpo',firstValue(a.spo,triase.saturasi_o2)); setField(modal,'#asesmenBb',firstValue(a.bb,triase.bb)); setField(modal,'#asesmenTb',a.tb);
      setField(modal,'#asesmenKeluhan',a.keluhan_utama); setField(modal,'#asesmenRps',a.rps); setField(modal,'#asesmenRpd',a.rpd); setField(modal,'#asesmenRpk',a.rpk); setField(modal,'#asesmenRpoAlergi',(a.rpo||'')+(a.alergi?'\nAlergi: '+a.alergi:'')); setField(modal,'#asesmenKetFisik',a.ket_fisik); setField(modal,'#asesmenKetLokalis',a.ket_lokalis); setField(modal,'#asesmenLab',a.lab); setField(modal,'#asesmenRad',a.rad); setField(modal,'#asesmenPenunjang',a.penunjang); setField(modal,'#asesmenDiagnosis',a.diagnosis); setField(modal,'#asesmenTata',a.tata); setField(modal,'#asesmenEdukasi',a.edukasi);
      modal.find('.asesmen-fisik').each(function(){var key=$(this).data('field');$(this).val(a[key]||'Tidak Diperiksa');}); if(data.image&&data.image.url_image) drawExisting(modal.find('#asesmenIgdCanvas')[0],data.image.url_image);
    },'json').fail(function(){modal.find('#asesmenIgdStatus').html('<div class="alert alert-warning">Data asesmen belum tersedia.</div>');});
    modal.modal('show'); return false;
  });
  function canvasPoint(canvas,event){
    var rect=canvas.getBoundingClientRect();
    return {x:(event.clientX-rect.left)*(canvas.width/rect.width),y:(event.clientY-rect.top)*(canvas.height/rect.height)};
  }
  function drawMarkingRing(canvas,point){
    var ctx=canvas.getContext('2d');
    var radius=parseFloat($('#asesmenRingSize').val())||15;
    ctx.save(); ctx.strokeStyle='#ff0000'; ctx.lineWidth=3; ctx.beginPath();
    ctx.arc(point.x,point.y,radius,0,Math.PI*2); ctx.stroke(); ctx.restore();
  }
  $(document).on('click','#toggleMarkingIgd',function(){var button=$(this),wrap=$('#asesmenIgdCanvasWrap'); if(wrap.is(':visible')){wrap.hide();button.html('<i class="fa fa-eye"></i> Tampilkan Marking Lokalis');}else{wrap.show();button.html('<i class="fa fa-eye-slash"></i> Sembunyikan Marking Lokalis');}});
  $(document).on('pointerdown','#asesmenIgdCanvas',function(event){
    var canvas=this, point=canvasPoint(canvas,event), ctx=canvas.getContext('2d');
    canvas._markingPointer={start:point,last:point,moved:false,brushSize:parseFloat($('#asesmenBrushSize').val())||6};
    $(canvas).data('dirty',true);
    canvas.setPointerCapture && canvas.setPointerCapture(event.pointerId);
    ctx.strokeStyle='#ff0000'; ctx.lineWidth=canvas._markingPointer.brushSize; ctx.lineCap='round'; ctx.lineJoin='round';
    event.preventDefault();
  });
  $(document).on('pointermove','#asesmenIgdCanvas',function(event){
    var canvas=this,state=canvas._markingPointer; if(!state) return;
    var point=canvasPoint(canvas,event),ctx=canvas.getContext('2d');
    if(Math.abs(point.x-state.start.x)>3 || Math.abs(point.y-state.start.y)>3) state.moved=true;
    if(state.moved){
      // Mulai path baru untuk setiap segmen agar stroke berikutnya tidak
      // tersambung otomatis ke titik akhir stroke sebelumnya.
      ctx.strokeStyle='#ff0000'; ctx.lineWidth=state.brushSize; ctx.lineCap='round'; ctx.lineJoin='round';
      ctx.beginPath(); ctx.moveTo(state.last.x,state.last.y); ctx.lineTo(point.x,point.y); ctx.stroke(); state.last=point;
    }
    event.preventDefault();
  });
  $(document).on('pointerup pointercancel','#asesmenIgdCanvas',function(event){
    var canvas=this,state=canvas._markingPointer; if(!state) return;
    if(!state.moved) drawMarkingRing(canvas,state.start);
    canvas._markingPointer=null; event.preventDefault();
  });
  $(document).on('click','#hapusMarkingIgd',function(){var canvas=$('#asesmenIgdCanvas')[0]; drawMaster(canvas); $(canvas).data('dirty',true);});
  $(document).on('click','#simpanAsesmenMedisIgd',function(){
    var modal=$('#asesmenMedisIgdModal'), canvas=modal.find('#asesmenIgdCanvas')[0], payload={no_rawat:modal.find('#asesmenIgdNoRawat').val(),anamnesis:modal.find('#asesmenAnamnesis').val(),hubungan:modal.find('#asesmenHubungan').val(),keadaan:modal.find('#asesmenKeadaan').val(),kesadaran:modal.find('#asesmenKesadaran').val(),gcs:modal.find('#asesmenGcs').val(),td:modal.find('#asesmenTd').val(),nadi:modal.find('#asesmenNadi').val(),rr:modal.find('#asesmenRr').val(),suhu:modal.find('#asesmenSuhu').val(),spo:modal.find('#asesmenSpo').val(),bb:modal.find('#asesmenBb').val(),tb:modal.find('#asesmenTb').val(),keluhan_utama:modal.find('#asesmenKeluhan').val(),rps:modal.find('#asesmenRps').val(),rpd:modal.find('#asesmenRpd').val(),rpk:modal.find('#asesmenRpk').val(),rpo:modal.find('#asesmenRpoAlergi').val(),alergi:'',ket_fisik:modal.find('#asesmenKetFisik').val(),ket_lokalis:modal.find('#asesmenKetLokalis').val(),lab:modal.find('#asesmenLab').val(),rad:modal.find('#asesmenRad').val(),penunjang:modal.find('#asesmenPenunjang').val(),diagnosis:modal.find('#asesmenDiagnosis').val(),tata:modal.find('#asesmenTata').val(),edukasi:modal.find('#asesmenEdukasi').val(),image_data:$(canvas).data('dirty') ? canvas.toDataURL('image/png') : ''}; modal.find('.asesmen-fisik').each(function(){payload[$(this).data('field')]=$(this).val();}); var button=$(this).prop('disabled',true); $.post(mlite.url+'/'+mlite.admin+'/dokter_igd/saveasesmenmedisigd?t='+mlite.token,payload,function(){modal.find('#asesmenIgdStatus').html('<div class="alert alert-success">Asesmen medis IGD berhasil disimpan.</div>');}).fail(function(xhr){var msg='Asesmen gagal disimpan.';if(xhr.responseJSON&&xhr.responseJSON.message)msg=xhr.responseJSON.message;modal.find('#asesmenIgdStatus').html('<div class="alert alert-danger">'+msg+'</div>');}).always(function(){button.prop('disabled',false);});
  });
})();
