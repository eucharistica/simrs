<?php
// vedika/admin.php
namespace Plugins\Vedika;

use Systems\AdminModule;
use Systems\Lib\BpjsService;
use LZCompressor\LZString;

class Admin extends AdminModule
{
  private $_uploads = WEBAPPS_PATH . '/berkasrawat/pages/upload';

  protected $consid;
  protected $secretkey;
  protected $user_key;
  protected $api_url;
  protected $assign;

  // Mode internal dipakai worker CLI agar method yang biasanya mengirim HTTP
  // dapat digunakan ulang tanpa echo/exit dan tanpa membuka browser.
  private $captureInacbgsHtml = false;
  private $captureJsonResponse = false;

  public function init()
  {
    $this->consid = $this->settings->get('settings.BpjsConsID');
    $this->secretkey = $this->settings->get('settings.BpjsSecretKey');
    $this->user_key = $this->settings->get('settings.BpjsUserKey');
    $this->api_url = $this->settings->get('settings.BpjsApiUrl');
  }

  public function navigation() 
  {
    return [
      'Manage' => 'manage',
      'Index' => 'index',
      'Indexpnj' => 'indexpnj',
      'Indexinap' => 'indexinap',
      'Lengkap' => 'lengkap',
      'Lengkapinap' => 'lengkapinap',
      'Pengajuan' => 'pengajuan',
      'Pengajuaninap' => 'pengajuaninap',
      'Perbaikan' => 'perbaikan',
      'Mapping Inacbgs' => 'mappinginacbgs',
      'Bridging Eklaim' => 'bridgingeklaim',
      'User Vedika' => 'uservedika',
      'Kronis' => 'kronis',
      'Pengaturan' => 'settings',
      'Indexcari' => 'indexcari',
    ];
  }

  public function getManage()
  {
    $this->_addHeaderFiles();
    $this->core->addJS(url(BASE_DIR.'/assets/jscripts/Chart.bundle.min.js'));
    $carabayar = str_replace(",","','", $this->settings->get('vedika.carabayar'));
    $stats['Chart'] = $this->Chart();
    $date = $this->settings->get('vedika.periode');
    if(isset($_GET['periode']) && $_GET['periode'] !=''){
      $date = $_GET['periode'];
    }

    $KlaimRalan = $this->db()->pdo()->prepare("SELECT reg_periksa.no_rawat FROM reg_periksa, penjab WHERE reg_periksa.kd_pj = penjab.kd_pj AND penjab.kd_pj IN ('$carabayar') AND reg_periksa.tgl_registrasi LIKE '{$date}%' AND reg_periksa.status_lanjut = 'Ralan'");
    $KlaimRalan->execute();
    $KlaimRalan = $KlaimRalan->fetchAll();
    $stats['KlaimRalan'] = 0;
    if(count($KlaimRalan) > 0) {
      $stats['KlaimRalan'] = count($KlaimRalan);
    }

    $KlaimRanap = $this->db()->pdo()->prepare("SELECT reg_periksa.no_rawat FROM reg_periksa, penjab, kamar_inap WHERE reg_periksa.no_rawat = kamar_inap.no_rawat AND reg_periksa.kd_pj = penjab.kd_pj AND penjab.kd_pj IN ('$carabayar') AND kamar_inap.tgl_keluar LIKE '{$date}%' AND reg_periksa.status_lanjut = 'Ranap'");
    $KlaimRanap->execute();
    $KlaimRanap = $KlaimRanap->fetchAll();
    $stats['KlaimRanap'] = 0;
    if(count($KlaimRanap) > 0) {
      $stats['KlaimRanap'] = count($KlaimRanap);
    }

    $stats['totalKlaim'] = $stats['KlaimRalan'] + $stats['KlaimRanap'];

    $LengkapRalan = $this->db()->pdo()->prepare("SELECT no_rawat FROM mlite_vedika WHERE status = 'Lengkap' AND jenis = '2' AND tgl_registrasi LIKE '{$date}%'");
    $LengkapRalan->execute();
    $LengkapRalan = $LengkapRalan->fetchAll();
    $stats['LengkapRalan'] = 0;
    if(count($LengkapRalan) > 0) {
      $stats['LengkapRalan'] = count($LengkapRalan);
    }

    $LengkapRanap = $this->db()->pdo()->prepare("SELECT no_rawat FROM mlite_vedika WHERE status = 'Lengkap' AND jenis = '1' AND no_rawat IN (SELECT no_rawat FROM kamar_inap WHERE tgl_keluar LIKE '{$date}%')");
    $LengkapRanap->execute();
    $LengkapRanap = $LengkapRanap->fetchAll();
    $stats['LengkapRanap'] = 0;
    if(count($LengkapRanap) > 0) {
      $stats['LengkapRanap'] = count($LengkapRanap);
    }

    $stats['totalLengkap'] = $stats['LengkapRalan'] + $stats['LengkapRanap'];

    $PengajuanRalan = $this->db()->pdo()->prepare("SELECT no_rawat FROM mlite_vedika WHERE status = 'Pengajuan' AND jenis = '2' AND tgl_registrasi LIKE '{$date}%'");
    $PengajuanRalan->execute();
    $PengajuanRalan = $PengajuanRalan->fetchAll();
    $stats['PengajuanRalan'] = count($PengajuanRalan);

    $PengajuanRanap = $this->db()->pdo()->prepare("SELECT no_rawat FROM mlite_vedika WHERE status = 'Pengajuan' AND jenis = '1' AND no_rawat IN (SELECT no_rawat FROM kamar_inap WHERE tgl_keluar LIKE '{$date}%')");
    $PengajuanRanap->execute();
    $PengajuanRanap = $PengajuanRanap->fetchAll();
    $stats['PengajuanRanap'] = count($PengajuanRanap);

    $stats['totalPengajuan'] = $stats['PengajuanRalan'] + $stats['PengajuanRanap'];

    $PerbaikanRalan = $this->db()->pdo()->prepare("SELECT no_rawat FROM mlite_vedika WHERE status = 'Perbaiki' AND jenis = '2' AND tgl_registrasi LIKE '{$date}%' AND username IN (SELECT username FROM mlite_users_vedika)");
    $PerbaikanRalan->execute();
    $PerbaikanRalan = $PerbaikanRalan->fetchAll();
    $stats['PerbaikanRalan'] = count($PerbaikanRalan);

    $PerbaikanRalan1 = $this->db()->pdo()->prepare("SELECT no_rawat FROM mlite_vedika WHERE status = 'Perbaiki' AND jenis = '2' AND tgl_registrasi LIKE '{$date}%' AND username NOT IN (SELECT username FROM mlite_users_vedika)");
    $PerbaikanRalan1->execute();
    $PerbaikanRalan1 = $PerbaikanRalan1->fetchAll();
    $stats['PerbaikanRalan1'] = count($PerbaikanRalan1);

    $PerbaikanRanap = $this->db()->pdo()->prepare("SELECT no_rawat FROM mlite_vedika WHERE status = 'Perbaiki' AND jenis = '1' AND tgl_registrasi LIKE '{$date}%' AND username IN (SELECT username FROM mlite_users_vedika)");
    $PerbaikanRanap->execute();
    $PerbaikanRanap = $PerbaikanRanap->fetchAll();
    $stats['PerbaikanRanap'] = count($PerbaikanRanap);

    $PerbaikanRanap1 = $this->db()->pdo()->prepare("SELECT no_rawat FROM mlite_vedika WHERE status = 'Perbaiki' AND jenis = '1' AND tgl_registrasi LIKE '{$date}%' AND username NOT IN (SELECT username FROM mlite_users_vedika)");
    $PerbaikanRanap1->execute();
    $PerbaikanRanap1 = $PerbaikanRanap1->fetchAll();
    $stats['PerbaikanRanap1'] = count($PerbaikanRanap1);

    $stats['totalPerbaikan'] = $stats['PerbaikanRalan'] + $stats['PerbaikanRanap'];

    //$stats['rencanaRalan'] = $stats['LengkapRalan'] + $stats['PengajuanRalan'];
    //$stats['rencanaRanap'] = $stats['LengkapRanap'] + $stats['PengajuanRanap'];
    $stats['rencanaRalan'] = $stats['KlaimRalan'];
    $stats['rencanaRanap'] = $stats['KlaimRanap'];

    $sub_modules = [
      ['name' => 'Index Rawat Jalan', 'url' => url([ADMIN, 'vedika', 'index']), 'icon' => 'calendar-minus-o', 'desc' => 'Index Vedika'],      
      ['name' => 'Index Rawat Inap', 'url' => url([ADMIN, 'vedika', 'indexinap']), 'icon' => 'calendar-minus-o', 'desc' => 'Index Rawat Inap'],
      ['name' => 'Index Penunjang', 'url' => url([ADMIN, 'vedika', 'indexpnj']), 'icon' => 'calendar-minus-o', 'desc' => 'Index Penunjang'],
      ['name' => 'Lengkap Rawat Jalan', 'url' => url([ADMIN, 'vedika', 'lengkap']), 'icon' => 'calendar-check-o', 'desc' => 'Index Lengkap Vedika'],
      ['name' => 'Lengkap Rawat Inap', 'url' => url([ADMIN, 'vedika', 'lengkapinap']), 'icon' => 'calendar-check-o', 'desc' => 'Index Lengkap Rawat Inap'],
      ['name' => 'Pengajuan Rawat Jalan', 'url' => url([ADMIN, 'vedika', 'pengajuan']), 'icon' => 'send', 'desc' => 'Index Pengajuan'],
      ['name' => 'Pengajuan Rawat Inap', 'url' => url([ADMIN, 'vedika', 'pengajuaninap']), 'icon' => 'send', 'desc' => 'Index Pengajuan Rawat Inap'],
      ['name' => 'Perbaikan', 'url' => url([ADMIN, 'vedika', 'perbaikan']), 'icon' => 'calendar-times-o', 'desc' => 'Index Perbaikan Vedika'],
      // ['name' => 'Mapping Inacbgs', 'url' => url([ADMIN, 'vedika', 'mappinginacbgs']), 'icon' => 'code', 'desc' => 'Pengaturan Mapping Inacbgs'],
      // ['name' => 'Bridging Eklaim', 'url' => url([ADMIN, 'vedika', 'bridgingeklaim']), 'icon' => 'code', 'desc' => 'Bridging Eklaim'],
      // ['name' => 'User Vedika', 'url' => url([ADMIN, 'vedika', 'users']), 'icon' => 'code', 'desc' => 'User Vedika'],
      // ['name' => 'Pengaturan', 'url' => url([ADMIN, 'vedika', 'settings']), 'icon' => 'gear', 'desc' => 'Pengaturan Vedika'],
      ['name' => 'Obat Kronis', 'url' => url([ADMIN, 'vedika', 'kronis']), 'icon' => 'medkit', 'desc' => 'Obat Kronis'],
      ['name' => 'Index by Filter', 'url' => url([ADMIN, 'vedika', 'indexcari']), 'icon' => 'search', 'desc' => 'Cari pakai filter poli'],  
    ];
    return $this->draw('manage.html', ['sub_modules' => $sub_modules, 'stats' => $stats, 'periode' => $date]);
  }

  public function Chart()
  {

      $query = $this->db('reg_periksa')
          ->select([
            'count'       => 'COUNT(DISTINCT kd_pj)',
            'tgl_registrasi'     => 'tgl_registrasi',
          ])
          //->join('poliklinik', 'poliklinik.kd_poli = reg_periksa.kd_poli')
          ->where('tgl_registrasi', '>=', date('Y-m'))
          //->group(['reg_periksa.kd_pj'])
          ->desc('kd_pj');


          $data = $query->toArray();

          $return = [
              'labels'  => [],
              'visits'  => [],
          ];

          foreach ($data as $value) {
              $return['labels'][] = $value['tgl_registrasi'];
              $return['visits'][] = $value['count'];
          }

      return $return;
  }

  public function anyIndex($type = 'ralan', $page = 1)
  {

    if (isset($_POST['submit'])) {
      $kd_poli = $this->core->getRegPeriksaInfo('kd_poli', $_POST['no_rawat']);
      $status_lanjut = $this->core->getRegPeriksaInfo('status_lanjut', $_POST['no_rawat']);
      $data_sep = $this->db('bridging_sep')->where('no_sep', $_POST['nosep'])->oneArray();
      $jenis_sep = isset($data_sep['jnspelayanan']) ? (string) $data_sep['jnspelayanan'] : '';
      $jenis_klaim = in_array($jenis_sep, ['1', '2'], true)
        ? $jenis_sep
        : (($status_lanjut === 'Ranap') ? '1' : '2');
      
      if (!$this->db('mlite_vedika')->where('nosep', $_POST['nosep'])->oneArray()) {
        $simpan_status = $this->db('mlite_vedika')->save([
          'id' => NULL,
          'tanggal' => date('Y-m-d'),
          'no_rkm_medis' => $_POST['no_rkm_medis'],
          'no_rawat' => $_POST['no_rawat'],
          'tgl_registrasi' => $_POST['tgl_registrasi'],
          'nosep' => $_POST['nosep'],
          'jenis' => $jenis_klaim,
          'status' => $_POST['status'],
          'kd_poli' => $kd_poli,
          'username' => $this->core->getUserInfo('username', null, true)
        ]);
      } else {
        $simpan_status = $this->db('mlite_vedika')
          ->where('nosep', $_POST['nosep'])
          ->save([
            'tanggal' => date('Y-m-d'),
            'jenis' => $jenis_klaim,
            'status' => $_POST['status'],
            'kd_poli'  => $kd_poli
          ]);
      }
      if ($simpan_status) {
        $this->db('mlite_vedika_feedback')->save([
          'id' => NULL,
          'nosep' => $_POST['nosep'],
          'tanggal' => date('Y-m-d'),
          'catatan' => $_POST['status'].' - '.$_POST['catatan'],
          'username' => $this->core->getUserInfo('username', null, true)
        ]);

        $this->_queueGroupingAfterStatusSaved(
          $_POST['no_rawat'],
          $_POST['nosep'],
          $jenis_klaim,
          $_POST['status']
        );

      }
    }

    $this->_addHeaderFiles();
    $start_date = date('Y-m-d');
    if (isset($_GET['start_date']) && $_GET['start_date'] != '')
      $start_date = $_GET['start_date'];
    $end_date = date('Y-m-d');
    if (isset($_GET['end_date']) && $_GET['end_date'] != '')
      $end_date = $_GET['end_date'];
    $perpage = '10';
    $phrase = '';
    
    
    $poli = '';
    if (isset($_GET['poli']) && $_GET['poli'] != '')
      $poli = $_GET['poli'];
      
    $poliklinik = $this->db('poliklinik')
          ->where('status', '1')
          ->notIn ('kd_poli',['U0015','U0016','U0033','U0035','U0036','U0041','U0047','U0031','U0052','U0058'])
          ->asc('nm_poli')
          ->toArray(); 
    $this->assign['poliklinik'] = $poliklinik; 
    
    if (isset($_GET['s']))
      $phrase = $_GET['s'];

    $carabayar = str_replace(",","','", $this->settings->get('vedika.carabayar'));

    // pagination

    $totalRecords = $this->db()->pdo()->prepare("
    SELECT DISTINCT rp.no_rawat
      FROM reg_periksa rp
        INNER JOIN pasien p
          ON rp.no_rkm_medis = p.no_rkm_medis
        INNER JOIN poliklinik pl
          ON pl.kd_poli = rp.kd_poli
        INNER JOIN dokter d
          ON d.kd_dokter = rp.kd_dokter
        INNER JOIN maping_poli_bpjs_real mp
          ON mp.kd_poli_rs = rp.kd_poli
        -- SEP rawat jalan untuk episode ini
        LEFT JOIN bridging_sep sep_ralan
          ON sep_ralan.no_rawat = rp.no_rawat
         AND sep_ralan.jnspelayanan = '2'
        -- belum pernah masuk vedika
        LEFT JOIN mlite_vedika mv
          ON mv.no_rawat = rp.no_rawat
        -- Sembunyikan Ralan bila menjadi Ranap dalam episode yang sama,
        -- atau SEP Ranap pasien terbit pada tanggal SEP Ralan yang sama.
        LEFT JOIN bridging_sep bs_ranap
          ON bs_ranap.nomr = rp.no_rkm_medis
         AND bs_ranap.jnspelayanan = '1'
         AND (
              bs_ranap.no_rawat = rp.no_rawat
              OR bs_ranap.tglsep = sep_ralan.tglsep
         )
      WHERE
        rp.kd_pj IN ('$carabayar')
        AND rp.kd_poli LIKE '%$poli%'
        AND (rp.no_rkm_medis LIKE ?
          OR rp.no_rawat   LIKE ?
          OR p.nm_pasien   LIKE ?
          OR pl.nm_poli    LIKE ?
          OR d.nm_dokter   LIKE ?)
        AND rp.tgl_registrasi BETWEEN '$start_date' AND '$end_date'
        AND rp.status_lanjut = 'Ralan'
        AND rp.stts != 'Batal'
        AND mv.no_rawat IS NULL
        AND bs_ranap.nomr IS NULL
        GROUP BY rp.no_rawat
    ");
    $totalRecords->execute(['%' . $phrase . '%', '%' . $phrase . '%', '%' . $phrase . '%', '%' . $phrase . '%', '%' . $phrase . '%']);
    $totalRecords = $totalRecords->fetchAll();

    $pagination = new \Systems\Lib\Pagination($page, count($totalRecords), $perpage, url([ADMIN, 'vedika', 'index', $type, '%d?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date . '&poli=' . $poli]));
    
    
    $this->assign['pagination'] = $pagination->nav('pagination', '5');
    $this->assign['totalRecords'] = $totalRecords; 
    $this->assign['total'] = 'tes'; 

    $offset = $pagination->offset();$nomor = $offset + 1;
    
    // --- QUERY DATA UTAMA ---
    $query = $this->db()->pdo()->prepare("
      SELECT
        rp.*,
        p.*,
        d.nm_dokter,
        pl.nm_poli,
        sep_ralan.no_sep,
        (
          SELECT COUNT(*)
          FROM reg_periksa rp2
          WHERE rp2.no_rkm_medis   = rp.no_rkm_medis   -- pasien yang sama
            AND rp2.kd_poli        = rp.kd_poli        -- poli yang sama
            AND YEAR(rp2.tgl_registrasi)  = YEAR(rp.tgl_registrasi)  -- tahun sama
            AND MONTH(rp2.tgl_registrasi) = MONTH(rp.tgl_registrasi) -- bulan sama
            AND rp2.status_lanjut  = 'Ralan'
            AND rp2.stts          != 'Batal'
        ) AS jumlah_kunjungan
      FROM reg_periksa rp
        INNER JOIN pasien p
          ON rp.no_rkm_medis = p.no_rkm_medis
        INNER JOIN dokter d
          ON rp.kd_dokter = d.kd_dokter
        INNER JOIN poliklinik pl
          ON rp.kd_poli = pl.kd_poli
        INNER JOIN maping_poli_bpjs_real mp
          ON mp.kd_poli_rs = rp.kd_poli

        -- SEP RALAN (untuk ORDER BY no_sep, kalau ada)
        LEFT JOIN bridging_sep sep_ralan
          ON sep_ralan.no_rawat = rp.no_rawat
         AND sep_ralan.jnspelayanan = '2'

        -- belum pernah masuk vedika
        LEFT JOIN mlite_vedika mv
          ON mv.no_rawat = rp.no_rawat

        -- Sembunyikan Ralan bila menjadi Ranap dalam episode yang sama,
        -- atau SEP Ranap pasien terbit pada tanggal SEP Ralan yang sama.
        LEFT JOIN bridging_sep bs_ranap
          ON bs_ranap.nomr = rp.no_rkm_medis
         AND bs_ranap.jnspelayanan = '1'
         AND (
              bs_ranap.no_rawat = rp.no_rawat
              OR bs_ranap.tglsep = sep_ralan.tglsep
         )

      WHERE
        rp.kd_pj IN ('$carabayar')
        AND rp.kd_poli LIKE '%$poli%'
        AND (rp.no_rkm_medis LIKE ?
          OR rp.no_rawat   LIKE ?
          OR p.nm_pasien   LIKE ?
          OR pl.nm_poli    LIKE ?
          OR d.nm_dokter   LIKE ?)
        AND rp.tgl_registrasi BETWEEN '$start_date' AND '$end_date'
        AND rp.status_lanjut = 'Ralan'
        AND rp.stts != 'Batal'
        AND mv.no_rawat IS NULL
        AND bs_ranap.nomr IS NULL
      GROUP BY rp.no_rawat
      ORDER BY
        CASE WHEN sep_ralan.no_sep IS NULL THEN 1 ELSE 0 END,
        sep_ralan.no_sep ASC
      LIMIT $perpage OFFSET $offset
    ");
    $query->execute(['%' . $phrase . '%', '%' . $phrase . '%', '%' . $phrase . '%', '%' . $phrase . '%', '%' . $phrase . '%']);
    $rows = $query->fetchAll();

    $this->assign['list'] = [];
    
    if (count($rows)) {
      foreach ($rows as $row) {
        $berkas_digital = $this->db('berkas_digital_perawatan')
          ->join('master_berkas_digital', 'master_berkas_digital.kode=berkas_digital_perawatan.kode')
          ->where('berkas_digital_perawatan.no_rawat', $row['no_rawat'])
          ->asc('master_berkas_digital.nama')
          ->toArray();
          
        $diagnosa_pasienx = $this->db('diagnosa_pasien')
          ->join('penyakit', 'penyakit.kd_penyakit = diagnosa_pasien.kd_penyakit')
          ->where('no_rawat', $row['no_rawat'])
          ->where('diagnosa_pasien.status', $row['status_lanjut'])
          ->asc('prioritas')
          ->toArray();
        $prosedur_pasienx = $this->db('prosedur_pasien')
          ->join('icd9', 'icd9.kode = prosedur_pasien.kode')
          ->where('no_rawat', $row['no_rawat'])
          ->where('status', $row['status_lanjut'])
          ->asc('prioritas')
          ->toArray();       

        $no_peserta = $this->core->getPasienInfo('no_peserta', $row['no_rkm_medis']);
        $onlyIMDiagnosis = $this->_diagnosisRowsOnlyIM($diagnosa_pasienx);

        $row = htmlspecialchars_array($row);    
        $row['formVclaimURL'] = url([ADMIN, 'vedika', 'formsep', '?no_asuransi=' . $no_peserta .'&no_rawat='.$row['no_rawat']]);         
        $row['diagnosa_pasienx'] = $diagnosa_pasienx;
        $row['only_im_diagnosis'] = $onlyIMDiagnosis;
        $row['prosedur_pasienx'] = $prosedur_pasienx;
        $row['nomor'] = $nomor++;
        $row['png_jawab'] = $this->core->getPenjabInfo('png_jawab', $this->core->getRegPeriksaInfo('kd_pj', $row['no_rawat']));
        $row['no_sitb'] = $this->_getSITB('no_sitb', $row['no_rkm_medis']);
        $row['no_sep'] = $this->_getSEPInfo('no_sep', $row['no_rawat']);
        $row['grouping_error'] = $this->_getLatestGroupingFailure($row['no_rawat'], $row['no_sep']);
        $row['no_peserta'] = $this->_getSEPInfo('no_kartu', $row['no_rawat']);
        $row['no_rujukan'] = $this->_getSEPInfo('no_rujukan', $row['no_rawat']);
        $row['kd_penyakit'] = $this->_getDiagnosa('kd_penyakit', $row['no_rawat'], $row['status_lanjut']);
        $row['nm_penyakit'] = $this->_getDiagnosa('nm_penyakit', $row['no_rawat'], $row['status_lanjut']);
        $row['kode'] = $this->_getProsedur('kode', $row['no_rawat'], $row['status_lanjut']);
        $row['deskripsi_panjang'] = $this->_getProsedur('deskripsi_panjang', $row['no_rawat'], $row['status_lanjut']);
        $row['berkas_digital'] = $berkas_digital;
        $row['formSepURL'] = url([ADMIN, 'vedika', 'formsepvclaim', '?no_rawat=' . $row['no_rawat']]);
        $row['pdfURL'] = url([ADMIN, 'vedika', 'pdf', $this->convertNorawat($row['no_rawat'])]);
        $row['setstatusURL']  = url([ADMIN, 'vedika', 'setstatus', $this->_getSEPInfo('no_sep', $row['no_rawat'])]);
        $row['status_pengajuan'] = $this->db('mlite_vedika')->where('nosep', $this->_getSEPInfo('no_sep', $row['no_rawat']))->desc('id')->limit(1)->toArray();
        $row['berkasPasien'] = url([ADMIN, 'vedika', 'berkaspasien', $this->getRegPeriksaInfo('no_rkm_medis', $row['no_rawat'])]);
        $row['berkasPerawatan'] = url([ADMIN, 'vedika', 'berkasperawatan', $this->convertNorawat($row['no_rawat'])]);
        if ($type == 'ranap') {
          $_get_kamar_inap = $this->db('kamar_inap')->where('no_rawat', $row['no_rawat'])->limit(1)->desc('tgl_keluar')->toArray();
          $row['tgl_registrasi'] = $_get_kamar_inap[0]['tgl_keluar'];
          $row['jam_reg'] = $_get_kamar_inap[0]['jam_keluar'];
          $get_kamar = $this->db('kamar')->where('kd_kamar', $_get_kamar_inap[0]['kd_kamar'])->oneArray();
          $get_bangsal = $this->db('bangsal')->where('kd_bangsal', $get_kamar['kd_bangsal'])->oneArray();
          $row['nm_poli'] = $get_bangsal['nm_bangsal'].'/'.$get_kamar['kd_kamar'];
          $row['nm_dokter'] = $this->db('dpjp_ranap')
            ->join('dokter', 'dokter.kd_dokter=dpjp_ranap.kd_dokter')
            ->where('no_rawat', $row['no_rawat'])
            ->toArray();
        }
        $this->assign['list'][] = $row;
      }
    }

    $this->core->addCSS(url('assets/jscripts/lightbox/lightbox.min.css'));
    $this->core->addJS(url('assets/jscripts/lightbox/lightbox.min.js'));
    
    return $this->draw('index.html', ['tab' => $type, 'vedika' => $this->assign]);
  }

  public function anyIndexpnj($type = 'ralan', $page = 1)
  {

    if (isset($_POST['submit'])) {
      if (!$this->db('mlite_vedika')->where('nosep', $_POST['nosep'])->oneArray()) {
        $simpan_status = $this->db('mlite_vedika')->save([
          'id' => NULL,
          'tanggal' => date('Y-m-d'),
          'no_rkm_medis' => $_POST['no_rkm_medis'],
          'no_rawat' => $_POST['no_rawat'],
          'tgl_registrasi' => $_POST['tgl_registrasi'],
          'nosep' => $_POST['nosep'],
          'jenis' => $_POST['jnspelayanan'],
          'status' => $_POST['status'],
          'username' => $this->core->getUserInfo('username', null, true)
        ]);
      } else {
        $simpan_status = $this->db('mlite_vedika')
          ->where('nosep', $_POST['nosep'])
          ->save([
            'tanggal' => date('Y-m-d'),
            'status' => $_POST['status']
          ]);
      }
      if ($simpan_status) {
        $this->db('mlite_vedika_feedback')->save([
          'id' => NULL,
          'nosep' => $_POST['nosep'],
          'tanggal' => date('Y-m-d'),
          'catatan' => $_POST['status'].' - '.$_POST['catatan'],
          'username' => $this->core->getUserInfo('username', null, true)
        ]);
      }
    }

    if (isset($_POST['simpanberkas'])) {
      if(MULTI_APP) {

        $curl = curl_init();
        $filePath = $_FILES['files']['tmp_name'];
        $file_type = $_FILES['files']['type'];
        if($file_type=='application/pdf'){
          $imagick = new \Imagick();
          $imagick->readImage($image);
          $imagick->writeImages($image.'.jpg', false);
          $filePath = $image.'.jpg';
        }

        curl_setopt_array($curl, array(
          CURLOPT_URL => str_replace('webapps','',WEBAPPS_URL).'api/berkasdigital',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS => array('file'=> new \CURLFILE($filePath),'token' => $this->settings->get('api.berkasdigital_key'), 'no_rawat' => $_POST['no_rawat'], 'kode' => $_POST['kode']),
          CURLOPT_HTTPHEADER => array(),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        $json = json_decode($response, true);
        if($json['status'] == 'Success') {
          echo '<br><img src="'.WEBAPPS_URL.'/berkasrawat/'.$json['msg'].'" width="150" />';
        } else {
          echo 'Gagal menambahkan gambar';
        }

      } else {
        $dir    = $this->_uploads;
        $cntr   = 0;

        $image = $_FILES['files']['tmp_name'];

        $file_type = $_FILES['files']['type'];
        if($file_type=='application/pdf'){
          $imagick = new \Imagick();
          $imagick->readImage($image);
          $imagick->writeImages($image.'.jpg', false);
          $image = $image.'.jpg';
        }

        $img = new \Systems\Lib\Image();
        $id = convertNorawat($_POST['no_rawat']);
        if ($img->load($image)) {
          $imgName = time() . $cntr++;
          $imgPath = $dir . '/' . $id . '_' . $imgName . '.' . $img->getInfos('type');
          $lokasi_file = 'pages/upload/' . $id . '_' . $imgName . '.' . $img->getInfos('type');
          $img->save($imgPath);
          $query = $this->db('berkas_digital_perawatan')->save(['no_rawat' => $_POST['no_rawat'], 'kode' => $_POST['kode'], 'lokasi_file' => $lokasi_file]);
          if ($query) {
            $this->notify('success', 'Simpan berkas digital perawatan sukses.');
          }
        }
      }
    }

    //DELETE BERKAS DIGITAL PERAWATAN
    if (isset($_POST['deleteberkas'])) {
      if ($berkasPerawatan = $this->db('berkas_digital_perawatan')
        ->where('no_rawat', $_POST['no_rawat'])
        ->where('lokasi_file', $_POST['lokasi_file'])
        ->oneArray()
      ) {

        $lokasi_file = $berkasPerawatan['lokasi_file'];
        $no_rawat_file = $berkasPerawatan['no_rawat'];

        chdir('../../'); //directory di mlite/admin/, harus dirubah terlebih dahulu ke /www
        $fileLoc = getcwd() . '/webapps/berkasrawat/' . $lokasi_file;
        if (file_exists($fileLoc)) {
          unlink($fileLoc);
          $query = $this->db('berkas_digital_perawatan')->where('no_rawat', $no_rawat_file)->where('lokasi_file', $lokasi_file)->delete();

          if ($query) {
            $this->notify('success', 'Hapus berkas sukses');
          } else {
            $this->notify('failure', 'Hapus berkas gagal');
          }
        } else {
          $this->notify('failure', 'Hapus berkas gagal, File tidak ada');
        }
        chdir('mlite/admin/'); //mengembalikan directory ke mlite/admin/
      }
    }

    $this->_addHeaderFiles();
    $start_date = date('Y-m-d');
    if (isset($_GET['start_date']) && $_GET['start_date'] != '')
      $start_date = $_GET['start_date'];
    $end_date = date('Y-m-d');
    if (isset($_GET['end_date']) && $_GET['end_date'] != '')
      $end_date = $_GET['end_date'];
    $perpage = '5';
    $phrase = '';
    
    if (isset($_GET['s']))
      $phrase = $_GET['s'];

    $carabayar = str_replace(",","','", $this->settings->get('vedika.carabayar'));

    // pagination

    $totalRecords = $this->db()->pdo()->prepare("SELECT reg_periksa.no_rawat FROM reg_periksa, pasien, penjab, poliklinik, dokter 
    WHERE reg_periksa.no_rkm_medis = pasien.no_rkm_medis 
    AND poliklinik.kd_poli IN ('U0015','U0016','U0035')
    AND reg_periksa.kd_pj = penjab.kd_pj AND poliklinik.kd_poli = reg_periksa.kd_poli AND dokter.kd_dokter = reg_periksa.kd_dokter AND penjab.kd_pj IN ('$carabayar') AND (reg_periksa.no_rkm_medis LIKE ? OR reg_periksa.no_rawat LIKE ? OR pasien.nm_pasien LIKE ? OR poliklinik.nm_poli LIKE ? OR dokter.nm_dokter LIKE ?) AND reg_periksa.tgl_registrasi BETWEEN '$start_date' AND '$end_date' AND reg_periksa.status_lanjut = 'Ralan' AND reg_periksa.stts != 'Batal' AND reg_periksa.no_rawat NOT IN (SELECT no_rawat FROM mlite_vedika)");
    $totalRecords->execute(['%' . $phrase . '%', '%' . $phrase . '%', '%' . $phrase . '%', '%' . $phrase . '%', '%' . $phrase . '%']);
    $totalRecords = $totalRecords->fetchAll();

    $pagination = new \Systems\Lib\Pagination($page, count($totalRecords), $perpage, url([ADMIN, 'vedika', 'indexpnj', $type, '%d?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date]));
    $this->assign['pagination'] = $pagination->nav('pagination', '5');
    $this->assign['totalRecords'] = $totalRecords; 

    $offset = $pagination->offset();$nomor = $offset + 1;
    $query = $this->db()->pdo()->prepare("SELECT reg_periksa.*, pasien.*, dokter.nm_dokter, poliklinik.nm_poli, penjab.png_jawab 
    FROM reg_periksa
    INNER JOIN pasien on reg_periksa.no_rkm_medis = pasien.no_rkm_medis
    INNER JOIN dokter on reg_periksa.kd_dokter = dokter.kd_dokter
    INNER JOIN poliklinik on reg_periksa.kd_poli = poliklinik.kd_poli
    INNER JOIN penjab on reg_periksa.kd_pj = penjab.kd_pj
    LEFT JOIN bridging_sep on bridging_sep.no_rawat = reg_periksa.no_rawat
    WHERE penjab.kd_pj IN ('$carabayar') 
    AND poliklinik.kd_poli IN ('U0015','U0016','U0035')
    AND (reg_periksa.no_rkm_medis LIKE ? OR reg_periksa.no_rawat LIKE ? OR pasien.nm_pasien LIKE ? OR poliklinik.nm_poli LIKE ? OR dokter.nm_dokter LIKE ?) AND reg_periksa.tgl_registrasi BETWEEN '$start_date' AND '$end_date' AND reg_periksa.status_lanjut = 'Ralan' AND reg_periksa.stts != 'Batal' AND reg_periksa.no_rawat NOT IN (SELECT no_rawat FROM mlite_vedika) ORDER BY bridging_sep.no_sep desc LIMIT $perpage OFFSET $offset");
    $query->execute(['%' . $phrase . '%', '%' . $phrase . '%', '%' . $phrase . '%', '%' . $phrase . '%', '%' . $phrase . '%']);
    $rows = $query->fetchAll();

    if (isset($_GET['debug']) && $_GET['debug'] == 'yes') {
      $totalRecords = $this->db()->pdo()->prepare("SELECT reg_periksa.no_rawat FROM reg_periksa, pasien, penjab WHERE reg_periksa.no_rkm_medis = pasien.no_rkm_medis AND reg_periksa.kd_pj = penjab.kd_pj AND penjab.kd_pj IN ('$carabayar') AND (reg_periksa.no_rkm_medis LIKE ? OR reg_periksa.no_rawat LIKE ? OR pasien.nm_pasien LIKE ?) AND reg_periksa.tgl_registrasi BETWEEN '$start_date' AND '$end_date' AND reg_periksa.status_lanjut = 'Ralan'");
      $totalRecords->execute(['%' . $phrase . '%', '%' . $phrase . '%', '%' . $phrase . '%']);
      $totalRecords = $totalRecords->fetchAll();

      $pagination = new \Systems\Lib\Pagination($page, count($totalRecords), $perpage, url([ADMIN, 'vedika', 'indexpnj', $type, '%d?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date]));
      $this->assign['pagination'] = $pagination->nav('pagination', '5');
      $this->assign['totalRecords'] = $totalRecords;

      $offset = $pagination->offset();$nomor = $offset + 1;
      $query = $this->db()->pdo()->prepare("SELECT reg_periksa.*, pasien.*, dokter.nm_dokter, poliklinik.nm_poli, penjab.png_jawab FROM reg_periksa, pasien, dokter, poliklinik, penjab WHERE reg_periksa.no_rkm_medis = pasien.no_rkm_medis AND reg_periksa.kd_dokter = dokter.kd_dokter AND reg_periksa.kd_poli = poliklinik.kd_poli AND reg_periksa.kd_pj = penjab.kd_pj AND penjab.kd_pj IN ('$carabayar') AND (reg_periksa.no_rkm_medis LIKE ? OR reg_periksa.no_rawat LIKE ? OR pasien.nm_pasien LIKE ?) AND reg_periksa.tgl_registrasi BETWEEN '$start_date' AND '$end_date' AND reg_periksa.status_lanjut = 'Ralan' LIMIT $perpage OFFSET $offset");
      $query->execute(['%' . $phrase . '%', '%' . $phrase . '%', '%' . $phrase . '%']);
      $rows = $query->fetchAll();
    }

    if ($type == 'ranap') {
      // pagination
      $totalRecords = $this->db()->pdo()->prepare("SELECT reg_periksa.no_rawat 
      FROM reg_periksa, pasien, penjab, kamar_inap 
      WHERE reg_periksa.no_rkm_medis = pasien.no_rkm_medis AND reg_periksa.no_rawat = kamar_inap.no_rawat AND reg_periksa.kd_pj = penjab.kd_pj AND penjab.kd_pj IN ('$carabayar') AND (reg_periksa.no_rkm_medis LIKE ? OR reg_periksa.no_rawat LIKE ? OR pasien.nm_pasien LIKE ?) AND kamar_inap.tgl_keluar BETWEEN '$start_date' AND '$end_date' AND reg_periksa.status_lanjut = 'Ranap'
      GROUP BY reg_periksa.no_rawat");
      $totalRecords->execute(['%' . $phrase . '%', '%' . $phrase . '%', '%' . $phrase . '%']);
      $totalRecords = $totalRecords->fetchAll();

      $pagination = new \Systems\Lib\Pagination($page, count($totalRecords), $perpage, url([ADMIN, 'vedika', 'indexpnj', $type, '%d?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date]));
      $this->assign['pagination'] = $pagination->nav('pagination', '5');
      $this->assign['totalRecords'] = $totalRecords;

      $offset = $pagination->offset();$nomor = $offset + 1;
      $query = $this->db()->pdo()->prepare("SELECT reg_periksa.*, pasien.*, dokter.nm_dokter, poliklinik.nm_poli, penjab.png_jawab, kamar_inap.tgl_keluar, kamar_inap.jam_keluar, kamar_inap.kd_kamar 
      FROM reg_periksa
      INNER JOIN pasien on reg_periksa.no_rkm_medis = pasien.no_rkm_medis
      INNER JOIN dokter on reg_periksa.kd_dokter = dokter.kd_dokter
      INNER JOIN poliklinik on reg_periksa.kd_poli = poliklinik.kd_poli
      INNER JOIN penjab on reg_periksa.kd_pj = penjab.kd_pj
      INNER JOIN kamar_inap on reg_periksa.no_rawat = kamar_inap.no_rawat
      LEFT JOIN bridging_sep on bridging_sep.no_rawat = reg_periksa.no_rawat
    WHERE
    penjab.kd_pj IN ('$carabayar') 
    AND (reg_periksa.no_rkm_medis LIKE ? OR reg_periksa.no_rawat LIKE ? OR pasien.nm_pasien LIKE ?) 
    AND kamar_inap.tgl_keluar BETWEEN '$start_date' AND '$end_date' 
    AND reg_periksa.status_lanjut = 'Ranap' 
    GROUP BY reg_periksa.no_rawat
    LIMIT $perpage OFFSET $offset");
      $query->execute(['%' . $phrase . '%', '%' . $phrase . '%', '%' . $phrase . '%']);
      $rows = $query->fetchAll();
    }
    $this->assign['list'] = [];
    if (count($rows)) {
      foreach ($rows as $row) {
        $berkas_digital = $this->db('berkas_digital_perawatan')
          ->join('master_berkas_digital', 'master_berkas_digital.kode=berkas_digital_perawatan.kode')
          ->where('berkas_digital_perawatan.no_rawat', $row['no_rawat'])
          ->asc('master_berkas_digital.nama')
          ->toArray();
          
        $diagnosa_pasienx = $this->db('diagnosa_pasien')
          ->join('penyakit', 'penyakit.kd_penyakit = diagnosa_pasien.kd_penyakit')
          ->where('no_rawat', $row['no_rawat'])
          ->where('diagnosa_pasien.status', $row['status_lanjut'])
          ->asc('prioritas')
          ->toArray();
        $prosedur_pasienx = $this->db('prosedur_pasien')
          ->join('icd9', 'icd9.kode = prosedur_pasien.kode')
          ->where('no_rawat', $row['no_rawat'])
          ->where('status', $row['status_lanjut'])
          ->asc('prioritas')
          ->toArray();       

        $row = htmlspecialchars_array($row);        
        $row['diagnosa_pasienx'] = $diagnosa_pasienx;
        $row['prosedur_pasienx'] = $prosedur_pasienx;
        $row['nomor'] = $nomor++;
        $row['no_sep'] = $this->_getSEPInfo('no_sep', $row['no_rawat']);
        $row['no_peserta'] = $this->_getSEPInfo('no_kartu', $row['no_rawat']);
        $row['no_rujukan'] = $this->_getSEPInfo('no_rujukan', $row['no_rawat']);
        $row['kd_penyakit'] = $this->_getDiagnosa('kd_penyakit', $row['no_rawat'], $row['status_lanjut']);
        $row['nm_penyakit'] = $this->_getDiagnosa('nm_penyakit', $row['no_rawat'], $row['status_lanjut']);
        $row['kode'] = $this->_getProsedur('kode', $row['no_rawat'], $row['status_lanjut']);
        $row['deskripsi_panjang'] = $this->_getProsedur('deskripsi_panjang', $row['no_rawat'], $row['status_lanjut']);
        $row['berkas_digital'] = $berkas_digital;
        $row['formSepURL'] = url([ADMIN, 'vedika', 'formsepvclaim', '?no_rawat=' . $row['no_rawat']]);
        $row['pdfURL'] = url([ADMIN, 'vedika', 'pdf', $this->convertNorawat($row['no_rawat'])]);
        $row['setstatusURL']  = url([ADMIN, 'vedika', 'setstatus', $this->_getSEPInfo('no_sep', $row['no_rawat'])]);
        $row['status_pengajuan'] = $this->db('mlite_vedika')->where('nosep', $this->_getSEPInfo('no_sep', $row['no_rawat']))->desc('id')->limit(1)->toArray();
        $row['berkasPasien'] = url([ADMIN, 'vedika', 'berkaspasien', $this->getRegPeriksaInfo('no_rkm_medis', $row['no_rawat'])]);
        $row['berkasPerawatan'] = url([ADMIN, 'vedika', 'berkasperawatan', $this->convertNorawat($row['no_rawat'])]);
        if ($type == 'ranap') {
          $_get_kamar_inap = $this->db('kamar_inap')->where('no_rawat', $row['no_rawat'])->limit(1)->desc('tgl_keluar')->toArray();
          $row['tgl_registrasi'] = $_get_kamar_inap[0]['tgl_keluar'];
          $row['jam_reg'] = $_get_kamar_inap[0]['jam_keluar'];
          $get_kamar = $this->db('kamar')->where('kd_kamar', $_get_kamar_inap[0]['kd_kamar'])->oneArray();
          $get_bangsal = $this->db('bangsal')->where('kd_bangsal', $get_kamar['kd_bangsal'])->oneArray();
          $row['nm_poli'] = $get_bangsal['nm_bangsal'].'/'.$get_kamar['kd_kamar'];
          $row['nm_dokter'] = $this->db('dpjp_ranap')
            ->join('dokter', 'dokter.kd_dokter=dpjp_ranap.kd_dokter')
            ->where('no_rawat', $row['no_rawat'])
            ->toArray();
        }
        $this->assign['list'][] = $row;
      }
    }

    $this->core->addCSS(url('assets/jscripts/lightbox/lightbox.min.css'));
    $this->core->addJS(url('assets/jscripts/lightbox/lightbox.min.js'));

    $this->assign['searchUrl'] =  url([ADMIN, 'vedika', 'indexpnj', $type, $page . '?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date]);
    $this->assign['ralanUrl'] =  url([ADMIN, 'vedika', 'indexpnj', 'ralan', $page . '?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date]);
    $this->assign['ranapUrl'] =  url([ADMIN, 'vedika', 'indexpnj', 'ranap', $page . '?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date]);
    return $this->draw('indexpnj.html', ['tab' => $type, 'vedika' => $this->assign]);
  }

  public function anyIndexinap($type = 'ranap', $page = 1)
  {

    if (isset($_POST['submit'])) {
      $kd_poli = $this->core->getRegPeriksaInfo('kd_poli', $_POST['no_rawat']);
      $status_lanjut = $this->core->getRegPeriksaInfo('status_lanjut', $_POST['no_rawat']);
      $data_sep = $this->db('bridging_sep')->where('no_sep', $_POST['nosep'])->oneArray();
      $jenis_sep = isset($data_sep['jnspelayanan']) ? (string) $data_sep['jnspelayanan'] : '';
      $jenis_klaim = in_array($jenis_sep, ['1', '2'], true)
        ? $jenis_sep
        : (($status_lanjut === 'Ranap') ? '1' : '2');
    
      if (!$this->db('mlite_vedika')->where('nosep', $_POST['nosep'])->oneArray()) {
        $simpan_status = $this->db('mlite_vedika')->save([
          'id' => NULL,
          'tanggal' => date('Y-m-d'),
          'no_rkm_medis' => $_POST['no_rkm_medis'],
          'no_rawat' => $_POST['no_rawat'],
          'tgl_registrasi' => $_POST['tgl_registrasi'],
          'nosep' => $_POST['nosep'],
          'jenis' => $jenis_klaim,
          'status' => $_POST['status'],
          'kd_poli' => $kd_poli,
          'username' => $this->core->getUserInfo('username', null, true)
        ]);
      } else {
        $simpan_status = $this->db('mlite_vedika')
          ->where('nosep', $_POST['nosep'])
          ->save([
            'tanggal' => date('Y-m-d'),
            'jenis' => $jenis_klaim,
            'status' => $_POST['status'],
            'kd_poli'  => $kd_poli
          ]);
      }
      if ($simpan_status) {
        $this->db('mlite_vedika_feedback')->save([
          'id' => NULL,
          'nosep' => $_POST['nosep'],
          'tanggal' => date('Y-m-d'),
          'catatan' => $_POST['status'].' - '.$_POST['catatan'],
          'username' => $this->core->getUserInfo('username', null, true)
        ]);

        $this->_queueGroupingAfterStatusSaved(
          $_POST['no_rawat'],
          $_POST['nosep'],
          $jenis_klaim,
          $_POST['status']
        );
      }
    }

    $this->_addHeaderFiles();
    $start_date = date('Y-m-d');
    if (isset($_GET['start_date']) && $_GET['start_date'] != '')
      $start_date = $_GET['start_date'];
    $end_date = date('Y-m-d');
    if (isset($_GET['end_date']) && $_GET['end_date'] != '')
      $end_date = $_GET['end_date'];
    $perpage = '10';
    $phrase = '';
    
    if (isset($_GET['s']))
      $phrase = $_GET['s'];

    $carabayar = str_replace(",","','", $this->settings->get('vedika.carabayar'));

    // pagination

    $totalRecords = $this->db()->pdo()->prepare("
    SELECT COUNT(DISTINCT rp.no_rawat) AS total
      FROM reg_periksa rp
        INNER JOIN pasien p
          ON rp.no_rkm_medis = p.no_rkm_medis
        INNER JOIN poliklinik pl
          ON rp.kd_poli = pl.kd_poli
        INNER JOIN penjab pj
          ON rp.kd_pj = pj.kd_pj
        INNER JOIN kamar_inap ki
          ON rp.no_rawat = ki.no_rawat
        LEFT JOIN dpjp_ranap drp
          ON drp.no_rawat = ki.no_rawat
        LEFT JOIN dokter d
          ON d.kd_dokter = drp.kd_dokter
        -- anti-join vedika
        LEFT JOIN mlite_vedika mv
          ON mv.no_rawat = rp.no_rawat
      WHERE
        pj.kd_pj IN ('$carabayar')
        AND (rp.no_rkm_medis LIKE ?
          OR rp.no_rawat   LIKE ?
          OR p.nm_pasien   LIKE ?
          OR d.nm_dokter   LIKE ?)
        AND ki.tgl_keluar BETWEEN '$start_date' AND '$end_date'
        AND ki.stts_pulang != 'Pindah Kamar'
        AND rp.status_lanjut = 'Ranap'
        AND mv.no_rawat IS NULL
      GROUP BY rp.no_rawat
      ");
      $totalRecords->execute(['%' . $phrase . '%', '%' . $phrase . '%', '%' . $phrase . '%', '%' . $phrase . '%']);
      $totalRecords = $totalRecords->fetchAll();

      $pagination = new \Systems\Lib\Pagination($page, count($totalRecords), $perpage, url([ADMIN, 'vedika', 'indexinap', $type, '%d?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date]));
      $this->assign['pagination'] = $pagination->nav('pagination', '5');
      $this->assign['totalRecords'] = $totalRecords;

      $offset = $pagination->offset();$nomor = $offset + 1;
      
      // DATA LIST RAWAT INAP
    $query = $this->db()->pdo()->prepare("
      SELECT
        rp.*,
        p.*,
        d.nm_dokter,
        pl.nm_poli,
        pj.png_jawab,
        ki.tgl_keluar,
        ki.jam_keluar,
        ki.kd_kamar,
        bs.no_sep
      FROM reg_periksa rp
        INNER JOIN pasien p
          ON rp.no_rkm_medis = p.no_rkm_medis
        INNER JOIN poliklinik pl
          ON rp.kd_poli = pl.kd_poli
        INNER JOIN penjab pj
          ON rp.kd_pj = pj.kd_pj
        INNER JOIN kamar_inap ki
          ON rp.no_rawat = ki.no_rawat
        LEFT JOIN dpjp_ranap drp
          ON drp.no_rawat = ki.no_rawat
        LEFT JOIN dokter d
          ON d.kd_dokter = drp.kd_dokter
        LEFT JOIN bridging_sep bs
          ON bs.no_rawat = rp.no_rawat
        LEFT JOIN mlite_vedika mv
          ON mv.no_rawat = rp.no_rawat
      WHERE
        pj.kd_pj IN ('$carabayar')
        AND (rp.no_rkm_medis LIKE ?
          OR rp.no_rawat   LIKE ?
          OR p.nm_pasien   LIKE ?
          OR d.nm_dokter   LIKE ?)
        AND ki.tgl_keluar BETWEEN '$start_date' AND '$end_date'
        AND ki.stts_pulang != 'Pindah Kamar'
        AND rp.status_lanjut = 'Ranap'
        AND mv.no_rawat IS NULL
      GROUP BY rp.no_rawat
      ORDER BY bs.no_sep DESC
      LIMIT $perpage OFFSET $offset
    ");

    $query->execute([
      '%' . $phrase . '%',
      '%' . $phrase . '%',
      '%' . $phrase . '%',
      '%' . $phrase . '%',
    ]);
    $rows = $query->fetchAll();

    $this->assign['list'] = [];
    if (count($rows)) {
      foreach ($rows as $row) {
        $berkas_digital = $this->db('berkas_digital_perawatan')
          ->join('master_berkas_digital', 'master_berkas_digital.kode=berkas_digital_perawatan.kode')
          ->where('berkas_digital_perawatan.no_rawat', $row['no_rawat'])
          ->asc('master_berkas_digital.nama')
          ->toArray();
          
        $diagnosa_pasienx = $this->db('diagnosa_pasien')
          ->join('penyakit', 'penyakit.kd_penyakit = diagnosa_pasien.kd_penyakit')
          ->where('no_rawat', $row['no_rawat'])
          ->where('diagnosa_pasien.status', $row['status_lanjut'])
          ->asc('prioritas')
          ->toArray();

        $prosedur_pasienx = $this->db('prosedur_pasien')
          ->join('icd9', 'icd9.kode = prosedur_pasien.kode')
          ->where('no_rawat', $row['no_rawat'])
          ->where('prosedur_pasien.status', $row['status_lanjut'])
          ->asc('prioritas')
          ->toArray();       

        $no_peserta = $this->core->getPasienInfo('no_peserta', $row['no_rkm_medis']);
        $onlyIMDiagnosis = $this->_diagnosisRowsOnlyIM($diagnosa_pasienx);

        $row = htmlspecialchars_array($row);    
        $row['formVclaimURL'] = url([ADMIN, 'vedika', 'formsep', '?no_asuransi=' . $no_peserta .'&no_rawat='.$row['no_rawat']]);    
        $row['diagnosa_pasienx'] = $diagnosa_pasienx;
        $row['only_im_diagnosis'] = $onlyIMDiagnosis;
        $row['prosedur_pasienx'] = $prosedur_pasienx;
        $row['nomor'] = $nomor++;
        $row['no_sitb'] = $this->_getSITB('no_sitb', $row['no_rkm_medis']);
        $row['no_sep'] = $this->_getSEPInfo('no_sep', $row['no_rawat']);
        $row['grouping_error'] = $this->_getLatestGroupingFailure($row['no_rawat'], $row['no_sep']);
        $row['no_peserta'] = $this->_getSEPInfo('no_kartu', $row['no_rawat']);
        $row['nik_bpjs'] = $no_peserta;
        $row['no_rujukan'] = $this->_getSEPInfo('no_rujukan', $row['no_rawat']);
        $row['kd_penyakit'] = $this->_getDiagnosa('kd_penyakit', $row['no_rawat'], $row['status_lanjut']);
        $row['nm_penyakit'] = $this->_getDiagnosa('nm_penyakit', $row['no_rawat'], $row['status_lanjut']);
        $row['kode'] = $this->_getProsedur('kode', $row['no_rawat'], $row['status_lanjut']);
        $row['deskripsi_panjang'] = $this->_getProsedur('deskripsi_panjang', $row['no_rawat'], $row['status_lanjut']);
        $row['berkas_digital'] = $berkas_digital;
        $row['resume'] = $this->_getResumeRanap('cara_keluar', $row['no_rawat']);
        $row['formSepURL'] = url([ADMIN, 'vedika', 'formsepvclaim', '?no_rawat=' . $row['no_rawat']]);
        $row['pdfURL'] = url([ADMIN, 'vedika', 'pdf', $this->convertNorawat($row['no_rawat'])]);
        $row['setstatusURL']  = url([ADMIN, 'vedika', 'setstatus', $this->_getSEPInfo('no_sep', $row['no_rawat'])]);
        $row['status_pengajuan'] = $this->db('mlite_vedika')->where('nosep', $this->_getSEPInfo('no_sep', $row['no_rawat']))->desc('id')->limit(1)->toArray();
        $row['berkasPasien'] = url([ADMIN, 'vedika', 'berkaspasien', $this->getRegPeriksaInfo('no_rkm_medis', $row['no_rawat'])]);
        $row['berkasPerawatan'] = url([ADMIN, 'vedika', 'berkasperawatan', $this->convertNorawat($row['no_rawat'])]);
        if ($type == 'ranap') {
          $_get_kamar_inap = $this->db('kamar_inap')->where('no_rawat', $row['no_rawat'])->limit(1)->desc('tgl_keluar')->toArray();
          $row['tgl_registrasi'] = $_get_kamar_inap[0]['tgl_keluar'];
          $row['jam_reg'] = $_get_kamar_inap[0]['jam_keluar'];
          $get_kamar = $this->db('kamar')->where('kd_kamar', $_get_kamar_inap[0]['kd_kamar'])->oneArray();
          $get_bangsal = $this->db('bangsal')->where('kd_bangsal', $get_kamar['kd_bangsal'])->oneArray();
          $row['nm_poli'] = $get_bangsal['nm_bangsal'].'/'.$get_kamar['kd_kamar'];
          $row['nm_dokter'] = $this->db('dpjp_ranap')
            ->join('dokter', 'dokter.kd_dokter=dpjp_ranap.kd_dokter')
            ->where('no_rawat', $row['no_rawat'])
            ->toArray();
        }
        $this->assign['list'][] = $row;
      }
    }

    $this->core->addCSS(url('assets/jscripts/lightbox/lightbox.min.css'));
    $this->core->addJS(url('assets/jscripts/lightbox/lightbox.min.js'));

    return $this->draw('indexinap.html', ['tab' => $type, 'vedika' => $this->assign]);
  }

  public function anyIndexcari($type = 'ralan', $page = 1)
  {

    if (isset($_POST['submit'])) {
    $kd_poli = $this->core->getRegPeriksaInfo('kd_poli', $_POST['no_rawat']);
    
      if (!$this->db('mlite_vedika')->where('nosep', $_POST['nosep'])->oneArray()) {
        $simpan_status = $this->db('mlite_vedika')->save([
          'id' => NULL,
          'tanggal' => date('Y-m-d'),
          'no_rkm_medis' => $_POST['no_rkm_medis'],
          'no_rawat' => $_POST['no_rawat'],
          'tgl_registrasi' => $_POST['tgl_registrasi'],
          'nosep' => $_POST['nosep'],
          'jenis' => $_POST['jnspelayanan'],
          'status' => $_POST['status'],
          'kd_poli' => $kd_poli,
          'username' => $this->core->getUserInfo('username', null, true)
        ]);
      } else {
        $simpan_status = $this->db('mlite_vedika')
          ->where('nosep', $_POST['nosep'])
          ->save([
            'tanggal' => date('Y-m-d'),
            'status' => $_POST['status'],
            'jenis' => $_POST['jenis'],
            'kd_poli' => $kd_poli
          ]);
      }
      if ($simpan_status) {
        $this->db('mlite_vedika_feedback')->save([
          'id' => NULL,
          'nosep' => $_POST['nosep'],
          'tanggal' => date('Y-m-d'),
          'catatan' => $_POST['status'].' - '.$_POST['catatan'],
          'username' => $this->core->getUserInfo('username', null, true)
        ]);
      }
    }

    $this->_addHeaderFiles();
    $start_date = date('Y-m-d');
    if (isset($_GET['start_date']) && $_GET['start_date'] != '')
      $start_date = $_GET['start_date'];
    $end_date = date('Y-m-d');
    if (isset($_GET['end_date']) && $_GET['end_date'] != '')
      $end_date = $_GET['end_date'];
    $poli = '';
    if (isset($_GET['poli']) && $_GET['poli'] != '')
      $poli = $_GET['poli'];
    $statusklaim = '';
    if (isset($_GET['statusklaim']) && $_GET['statusklaim'] != '')
      $statusklaim = $_GET['poli'];
    $perpage = '50';
    $phrase = '-';
    
    if (isset($_GET['s']))
      $phrase = $_GET['s'];

    // $carabayar = str_replace(",","','", $this->settings->get('vedika.carabayar'));
     $carabayar = '';
    if (isset($_GET['carabayar']) && $_GET['carabayar'] != '')
      $carabayar = $_GET['carabayar'];

    // pagination

    $totalRecords = $this->db()->pdo()->prepare("SELECT reg_periksa.no_rawat FROM reg_periksa, maping_poli_bpjs_real
    WHERE maping_poli_bpjs_real.kd_poli_rs=reg_periksa.kd_poli
    AND reg_periksa.kd_pj LIKE '%$carabayar%'
    AND reg_periksa.kd_poli LIKE '%$poli%'
    -- AND reg_periksa.kd_poli NOT IN ('U0015','U0016','U0033','U0035','U0041','U0047','U0050','U0031','U0058')
    AND (reg_periksa.no_rkm_medis LIKE ?)
    AND reg_periksa.tgl_registrasi BETWEEN '$start_date' AND '$end_date' 
    AND reg_periksa.stts != 'Batal'");
    $totalRecords->execute(['%' . $phrase . '%']);
    $totalRecords = $totalRecords->fetchAll();

    $pagination = new \Systems\Lib\Pagination($page, count($totalRecords), $perpage, url([ADMIN, 'vedika', 'indexcari', $type, '%d?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date]));
    $this->assign['pagination'] = $pagination->nav('pagination', '5');
    $this->assign['totalRecords'] = $totalRecords; 

    $offset = $pagination->offset();$nomor = $offset + 1;
    $query = $this->db()->pdo()->prepare("SELECT reg_periksa.*
    FROM reg_periksa
    INNER JOIN maping_poli_bpjs_real on maping_poli_bpjs_real.kd_poli_rs=reg_periksa.kd_poli
    LEFT JOIN bridging_sep on bridging_sep.no_rawat = reg_periksa.no_rawat
    WHERE reg_periksa.kd_pj LIKE '%$carabayar%'
    AND reg_periksa.kd_poli LIKE '%$poli%'
    AND (reg_periksa.no_rkm_medis LIKE ?) 
    AND reg_periksa.tgl_registrasi BETWEEN '$start_date' AND '$end_date' 
    AND reg_periksa.stts != 'Batal' 
    ORDER BY reg_periksa.status_lanjut desc 
    LIMIT $perpage OFFSET $offset");
    $query->execute(['%' . $phrase . '%']);
    $rows = $query->fetchAll();
    
    $poliklinik = $this->db('poliklinik')
          ->where('status', '1')
          ->join('maping_poli_bpjs_real','maping_poli_bpjs_real.kd_poli_rs=poliklinik.kd_poli')
        //   ->notIn ('kd_poli',['U0015','U0016','U0033','U0035','U0041','U0047','U0050','U0031','U0058'])
          ->asc('nm_poli')
          ->toArray(); 
    $this->assign['poliklinik'] = $poliklinik; 

    $this->assign['list'] = [];
    if (count($rows)) {
      foreach ($rows as $row) {
        $berkas_digital = $this->db('berkas_digital_perawatan')
          ->join('master_berkas_digital', 'master_berkas_digital.kode=berkas_digital_perawatan.kode')
          ->where('berkas_digital_perawatan.no_rawat', $row['no_rawat'])
          ->asc('master_berkas_digital.nama')
          ->toArray();
          
        $diagnosa_pasienx = $this->db('diagnosa_pasien')
          ->join('penyakit', 'penyakit.kd_penyakit = diagnosa_pasien.kd_penyakit')
          ->where('no_rawat', $row['no_rawat'])
          ->where('diagnosa_pasien.status', $row['status_lanjut'])
          ->asc('prioritas')
          ->toArray();

        $prosedur_pasienx = $this->db('prosedur_pasien')
          ->join('icd9', 'icd9.kode = prosedur_pasien.kode')
          ->where('no_rawat', $row['no_rawat'])
          ->where('status', $row['status_lanjut'])
          ->asc('prioritas')
          ->toArray();
          
        $no_peserta = $this->core->getPasienInfo('no_peserta', $row['no_rkm_medis']);

        $row = htmlspecialchars_array($row);    
        $row['formVclaimURL'] = url([ADMIN, 'vedika', 'formsep', '?no_asuransi=' . $no_peserta .'&no_rawat='.$row['no_rawat']]);        
        $row['diagnosa_pasienx'] = $diagnosa_pasienx;
        $row['prosedur_pasienx'] = $prosedur_pasienx;
        $row['nomor'] = $nomor++;
        $row['nm_pasien'] = $this->core->getRegPeriksaInfo('nm_pasien', $row['no_rawat']);
        $row['almt_pj'] = $this->core->getRegPeriksaInfo('alamat', $row['no_rawat']);
        $row['jk'] = $this->core->getPasienInfo('jk', $row['no_rkm_medis']);
        $row['umur'] = $this->core->getRegPeriksaInfo('umurdaftar', $row['no_rawat']);
        $row['sttsumur'] = $this->core->getRegPeriksaInfo('sttsumur', $row['no_rawat']);
        $row['nm_dokter'] = $this->core->getDokterInfo('nm_dokter', $this->core->getRegPeriksaInfo('kd_dokter', $row['no_rawat']));
        $row['nm_poli'] = $this->core->getPoliklinikInfo('nm_poli', $this->core->getRegPeriksaInfo('kd_poli', $row['no_rawat']));
        $row['no_sitb'] = $this->_getSITB('no_sitb', $row['no_rkm_medis']);
        $row['final'] = $this->_getFinalKlaim('nik', $this->_getSEPInfo('no_sep', $row['no_rawat']));
        $row['no_sep'] = $this->_getSEPInfo('no_sep', $row['no_rawat']);
        $row['jam_reg'] = $this->core->getRegPeriksaInfo('jam_reg', $row['no_rawat']);
        $row['png_jawab'] = $this->core->getPenjabInfo('png_jawab', $this->core->getRegPeriksaInfo('kd_pj', $row['no_rawat']));
        $row['no_peserta'] = $this->_getSEPInfo('no_kartu', $row['no_rawat']);
        $row['no_rujukan'] = $this->_getSEPInfo('no_rujukan', $row['no_rawat']);
        $row['kd_penyakit'] = $this->_getDiagnosa('kd_penyakit', $row['no_rawat'], $row['status_lanjut']);
        $row['nm_penyakit'] = $this->_getDiagnosa('nm_penyakit', $row['no_rawat'], $row['status_lanjut']);
        $row['kode'] = $this->_getProsedur('kode', $row['no_rawat'], $row['status_lanjut']);
        $row['deskripsi_panjang'] = $this->_getProsedur('deskripsi_panjang', $row['no_rawat'], $row['status_lanjut']);
        $row['berkas_digital'] = $berkas_digital;
        $row['formSepURL'] = url([ADMIN, 'vedika', 'formsepvclaim', '?no_rawat=' . $row['no_rawat']]);
        $row['resume'] = $this->_getResumeRanap('cara_keluar', $row['no_rawat']);
        $row['pdfURL'] = url([ADMIN, 'vedika', 'pdf', $this->convertNorawat($row['no_rawat'])]);
        $row['setstatusURL']  = url([ADMIN, 'vedika', 'setstatus', $this->_getSEPInfo('no_sep', $row['no_rawat'])]);
        $row['status_pengajuan'] = $this->db('mlite_vedika')->where('nosep', $this->_getSEPInfo('no_sep', $row['no_rawat']))->desc('id')->limit(1)->toArray();
        $row['berkasPasien'] = url([ADMIN, 'vedika', 'berkaspasien', $this->getRegPeriksaInfo('no_rkm_medis', $row['no_rawat'])]);
        $row['berkasPerawatan'] = url([ADMIN, 'vedika', 'berkasperawatan', $this->convertNorawat($row['no_rawat'])]);
        if ($this->core->getRegPeriksaInfo('status_lanjut', $row['no_rawat']) == 'Ranap') {
          $row['tgl_registrasi'] = $this->core->getKamarInapInfo('tgl_keluar', $row['no_rawat']);
          $row['jam_reg'] = $this->core->getKamarInapInfo('jam_keluar', $row['no_rawat']);
          $get_kamar = $this->db('kamar')->where('kd_kamar', $this->core->getKamarInapInfo('kd_kamar', $row['no_rawat']))->oneArray();
          $get_bangsal = $this->db('bangsal')->where('kd_bangsal', $get_kamar['kd_bangsal'])->oneArray();
          $row['nm_poli'] = $get_bangsal['nm_bangsal'].'/'.$get_kamar['kd_kamar'];
          $row['nm_dokter'] = $this->getDpjpRanap('nm_dokter', $row['no_rawat']);
        }
        $this->assign['list'][] = $row;
      }
    }

    $this->core->addCSS(url('assets/jscripts/lightbox/lightbox.min.css'));
    $this->core->addJS(url('assets/jscripts/lightbox/lightbox.min.js'));

    $this->assign['searchUrl'] =  url([ADMIN, 'vedika', 'indexcari', $type, $page . '?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date]);
    $this->assign['ralanUrl'] =  url([ADMIN, 'vedika', 'indexcari', 'ralan', $page . '?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date]);
    $this->assign['ranapUrl'] =  url([ADMIN, 'vedika', 'indexcari', 'ranap', $page . '?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date]);
    return $this->draw('indexcari.html', ['tab' => $type, 'vedika' => $this->assign]);
  }

  public function anyKronis($type = 'ralan', $page = 1)
  {

    if (isset($_POST['submit'])) {
      if (!$this->db('mlite_veronisa')->where('nosep', $_POST['nosep'])->oneArray()) {
        $simpan_status = $this->db('mlite_veronisa')->save([
          'id' => NULL,
          'tanggal' => date('Y-m-d'),
          'no_rkm_medis' => $_POST['no_rkm_medis'],
          'no_rawat' => $_POST['no_rawat'],
          'tgl_registrasi' => $_POST['tgl_registrasi'],
          'nosep' => $_POST['nosep'],
          'username' => $this->core->getUserInfo('username', null, true)
        ]);
      } else {
        $simpan_status = $this->db('mlite_veronisa')
          ->where('nosep', $_POST['nosep'])
          ->save([
            'tanggal' => date('Y-m-d'),
            'status' => $_POST['status']
          ]);
      }
      if ($simpan_status) {
        $this->db('mlite_veronisa_feedback')->save([
          'id' => NULL,
          'nosep' => $_POST['nosep'],
          'tanggal' => date('Y-m-d'),
          'catatan' => $_POST['catatan'],
          'username' => $this->core->getUserInfo('username', null, true)
        ]);
      }

    }

    $this->_addHeaderFiles();
    $start_date = date('Y-m-d');
    if (isset($_GET['start_date']) && $_GET['start_date'] != '')
      $start_date = $_GET['start_date'];
    $end_date = date('Y-m-d');
    if (isset($_GET['end_date']) && $_GET['end_date'] != '')
      $end_date = $_GET['end_date'];
    $perpage = '10';
    $phrase = '';
    
    if (isset($_GET['s']))
      $phrase = $_GET['s'];
      
    $poli = '';
    if (isset($_GET['poli']) && $_GET['poli'] != '')
      $poli = $_GET['poli'];
      
    $poliklinik = $this->db('poliklinik')
          ->where('status', '1')
          ->join('maping_poli_bpjs_real','maping_poli_bpjs_real.kd_poli_rs=poliklinik.kd_poli')
          ->asc('nm_poli')
          ->toArray(); 
    $this->assign['poliklinik'] = $poliklinik; 

    // pagination

    $totalRecords = $this->db()->pdo()->prepare("SELECT
      reg_periksa.no_rawat 
    FROM
      reg_periksa
      inner join pasien on pasien.no_rkm_medis = reg_periksa.no_rkm_medis
      inner join mlite_veronisa on mlite_veronisa.no_rawat = reg_periksa.no_rawat
    WHERE
      reg_periksa.status_lanjut = 'Ralan'
      AND reg_periksa.kd_poli LIKE '%$poli%'
      AND (
        reg_periksa.no_rkm_medis LIKE ? 
        OR reg_periksa.no_rawat LIKE ? 
      OR pasien.nm_pasien LIKE ?) 
      AND reg_periksa.tgl_registrasi BETWEEN '$start_date' 
      AND '$end_date' 
      GROUP BY reg_periksa.no_rawat");
    $totalRecords->execute(['%' . $phrase . '%', '%' . $phrase . '%', '%' . $phrase . '%']);
    $totalRecords = $totalRecords->fetchAll();

    $pagination = new \Systems\Lib\Pagination($page, count($totalRecords), $perpage, url([ADMIN, 'vedika', 'kronis', $type, '%d?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date]));
    $this->assign['pagination'] = $pagination->nav('pagination', '5');
    $this->assign['totalRecords'] = $totalRecords; 

    $offset = $pagination->offset();$nomor = $offset + 1;
    $query = $this->db()->pdo()->prepare("SELECT reg_periksa.*, pasien.*, dokter.nm_dokter, poliklinik.nm_poli, penjab.png_jawab 
    FROM reg_periksa
    INNER JOIN pasien on reg_periksa.no_rkm_medis = pasien.no_rkm_medis
    INNER JOIN dokter on reg_periksa.kd_dokter = dokter.kd_dokter
    INNER JOIN poliklinik on reg_periksa.kd_poli = poliklinik.kd_poli
    INNER JOIN penjab on reg_periksa.kd_pj = penjab.kd_pj
    LEFT JOIN bridging_sep on bridging_sep.no_rawat = reg_periksa.no_rawat
    WHERE (reg_periksa.no_rkm_medis LIKE ? OR reg_periksa.no_rawat LIKE ? OR pasien.nm_pasien LIKE ?) AND reg_periksa.kd_poli LIKE '%$poli%' AND reg_periksa.tgl_registrasi BETWEEN '$start_date' AND '$end_date' AND reg_periksa.status_lanjut = 'Ralan' AND reg_periksa.stts != 'Batal' AND reg_periksa.no_rawat IN (SELECT no_rawat FROM mlite_veronisa) GROUP BY reg_periksa.no_rawat ORDER BY bridging_sep.no_sep desc LIMIT $perpage OFFSET $offset");
    $query->execute(['%' . $phrase . '%', '%' . $phrase . '%', '%' . $phrase . '%']);
    $rows = $query->fetchAll();

    $this->assign['list'] = [];
    if (count($rows)) {
      foreach ($rows as $row) {
        $berkas_digital = $this->db('berkas_digital_perawatan')
          ->join('master_berkas_digital', 'master_berkas_digital.kode=berkas_digital_perawatan.kode')
          ->where('berkas_digital_perawatan.no_rawat', $row['no_rawat'])
          ->asc('master_berkas_digital.nama')
          ->toArray();
            

        $no_peserta = $this->core->getPasienInfo('no_peserta', $row['no_rkm_medis']);

        $row = htmlspecialchars_array($row);    
        $row['formVclaimURL'] = url([ADMIN, 'vedika', 'formsep', '?no_asuransi=' . $no_peserta .'&no_rawat='.$row['no_rawat']]);        
        $row['nomor'] = $nomor++;
        $row['no_sep'] = $this->_getSEPInfo('no_sep', $row['no_rawat']);
        $row['no_peserta'] = $this->_getSEPInfo('no_kartu', $row['no_rawat']);
        $row['no_rujukan'] = $this->_getSEPInfo('no_rujukan', $row['no_rawat']);
        $row['berkas_digital'] = $berkas_digital;
        $row['formSepURL'] = url([ADMIN, 'veronisa', 'formsepvclaim', '?no_rawat=' . $row['no_rawat']]);
        $row['pdfURL'] = url([ADMIN, 'veronisa', 'pdf', $this->convertNorawat($row['no_rawat'])]);
        $row['setstatusURL']  = url([ADMIN, 'veronisa', 'setstatus', $this->_getSEPInfo('no_sep', $row['no_rawat'])]);
        $row['status_pengajuan'] = $this->db('mlite_veronisa')->where('nosep', $this->_getSEPInfo('no_sep', $row['no_rawat']))->desc('id')->limit(1)->toArray();
        $row['berkasPasien'] = url([ADMIN, 'vedika', 'berkaspasien', $this->getRegPeriksaInfo('no_rkm_medis', $row['no_rawat'])]);
        $row['berkasPerawatan'] = url([ADMIN, 'vedika', 'berkasperawatan', $this->convertNorawat($row['no_rawat'])]);
        $this->assign['list'][] = $row;
      }
    }

    $this->core->addCSS(url('assets/jscripts/lightbox/lightbox.min.css'));
    $this->core->addJS(url('assets/jscripts/lightbox/lightbox.min.js'));

    $this->assign['searchUrl'] =  url([ADMIN, 'vedika', 'kronis', $type, $page . '?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date]);
    return $this->draw('kronis.html', ['tab' => $type, 'vedika' => $this->assign]);
  }

  public function anyLengkap($type = 'ralan', $page = 1)
  {
    if (isset($_POST['submit'])) {
      if (!$this->db('mlite_vedika')->where('nosep', $_POST['nosep'])->oneArray()) {
        $simpan_status = $this->db('mlite_vedika')->save([
          'id' => NULL,
          'tanggal' => date('Y-m-d'),
          'no_rkm_medis' => $_POST['no_rkm_medis'],
          'no_rawat' => $_POST['no_rawat'],
          'tgl_registrasi' => $_POST['tgl_registrasi'],
          'nosep' => $_POST['nosep'],
          'jenis' => '2',
          'status' => $_POST['status'],
          'username' => $this->core->getUserInfo('username', null, true)
        ]);
      } else {
        $simpan_status = $this->db('mlite_vedika')
          ->where('nosep', $_POST['nosep'])
          ->save([
            'tanggal' => date('Y-m-d'),
            'status' => $_POST['status'],
            'jenis' => $_POST['jenis']
          ]);
      }
      if ($simpan_status) {
        $this->db('mlite_vedika_feedback')->save([
          'id' => NULL,
          'nosep' => $_POST['nosep'],
          'tanggal' => date('Y-m-d'),
          'catatan' => $_POST['status'].' - '.$_POST['catatan'],
          'username' => $this->core->getUserInfo('username', null, true)
        ]);
      }
    }

    $this->_addHeaderFiles();
    $start_date = date('Y-m-d');
    if (isset($_GET['start_date']) && $_GET['start_date'] != '')
      $start_date = $_GET['start_date'];
    $end_date = date('Y-m-d');
    if (isset($_GET['end_date']) && $_GET['end_date'] != '')
      $end_date = $_GET['end_date'];
    $perpage = '50';
    $phrase = '';
    
    if (isset($_GET['s']))
      $phrase = $_GET['s'];
      
    $poli = '';
    if (isset($_GET['poli']) && $_GET['poli'] != '')
      $poli = $_GET['poli'];
      
    $poliklinik = $this->db('poliklinik')
          ->where('status', '1')
          ->notIn ('kd_poli',['U0015','U0016','U0033','U0035','U0036','U0041','U0047','U0031','U0052','U0058'])
          ->asc('nm_poli')
          ->toArray(); 
    $this->assign['poliklinik'] = $poliklinik; 

    // pagination
    $totalRecords = $this->db()->pdo()->prepare("SELECT
      mlite_vedika.*
      FROM
      mlite_vedika
      -- INNER JOIN bridging_sep on bridging_sep.no_rawat = mlite_vedika.no_rawat
      WHERE
      mlite_vedika.tgl_registrasi BETWEEN '$start_date' AND '$end_date'
      AND mlite_vedika.kd_poli LIKE '%$poli%'
      AND mlite_vedika.`status` = 'Lengkap'
      AND mlite_vedika.jenis = '2'
      AND (mlite_vedika.no_rkm_medis LIKE ? OR mlite_vedika.nosep LIKE ?)");
    $totalRecords->execute(['%' . $phrase . '%', '%' . $phrase . '%']);
    $totalRecords = $totalRecords->fetchAll();

    $pagination = new \Systems\Lib\Pagination($page, count($totalRecords), $perpage, url([ADMIN, 'vedika', 'lengkap', $type, '%d?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date . '&poli=' . $poli]));
    $this->assign['pagination'] = $pagination->nav('pagination', '5');
    $this->assign['totalRecords'] = $totalRecords;
    
    $offset = $pagination->offset();$nomor = $offset + 1;
    $query = $this->db()->pdo()->prepare("SELECT
        v.*,
    
        -- === berapa kali pasien ini ke poli ini pada bulan yg sama ===
        (
          SELECT COUNT(*)
          FROM mlite_vedika v2
          WHERE v2.no_rkm_medis      = v.no_rkm_medis   -- pasien yang sama
            AND v2.kd_poli           = v.kd_poli        -- poli yang sama
            AND YEAR(v2.tgl_registrasi)  = YEAR(v.tgl_registrasi)  -- tahun sama
            AND MONTH(v2.tgl_registrasi) = MONTH(v.tgl_registrasi) -- bulan sama
            AND v2.status            = 'Lengkap'        -- konsisten dengan filter luar
            AND v2.jenis             = '2'
        ) AS jumlah_kunjungan
    
    FROM mlite_vedika v
    WHERE
        v.tgl_registrasi BETWEEN '$start_date' AND '$end_date'
        AND v.kd_poli LIKE '%$poli%'
        AND v.status = 'Lengkap'
        AND v.jenis  = '2'
      AND (v.no_rkm_medis LIKE ? OR v.nosep LIKE ?) ORDER BY v.nosep ASC LIMIT $perpage OFFSET $offset");
    $query->execute(['%' . $phrase . '%', '%' . $phrase . '%']);
    $rows = $query->fetchAll();

    $this->assign['list'] = [];
    if (count($rows)) {
      foreach ($rows as $row) {
        $berkas_digital = $this->db('berkas_digital_perawatan')
          ->join('master_berkas_digital', 'master_berkas_digital.kode=berkas_digital_perawatan.kode')
          ->where('berkas_digital_perawatan.no_rawat', $row['no_rawat'])
          ->asc('master_berkas_digital.nama')
          ->toArray();
        $diagnosa_pasienx = $this->db('diagnosa_pasien')
          ->join('penyakit', 'penyakit.kd_penyakit = diagnosa_pasien.kd_penyakit')
          ->where('no_rawat', $row['no_rawat'])
          ->where('diagnosa_pasien.status', 'Ralan')
          ->asc('prioritas')
          ->toArray();
        $prosedur_pasienx = $this->db('prosedur_pasien')
          ->join('icd9', 'icd9.kode = prosedur_pasien.kode')
          ->where('no_rawat', $row['no_rawat'])
          ->where('status', 'Ralan')
          ->asc('prioritas')
          ->toArray();       

        $no_peserta = $this->core->getPasienInfo('no_peserta', $row['no_rkm_medis']);

        $row = htmlspecialchars_array($row);    
        $row['formVclaimURL'] = url([ADMIN, 'vedika', 'formsep', '?no_asuransi=' . $no_peserta .'&no_rawat='.$row['no_rawat']]);         
        $row['diagnosa_pasienx'] = $diagnosa_pasienx;
        $row['prosedur_pasienx'] = $prosedur_pasienx;
        $row['nomor'] = $nomor++;
        $row['rkm_medis'] = $this->core->getRegPeriksaInfo('no_rkm_medis', $row['no_rawat']);
        $row['nm_pasien'] = $this->core->getRegPeriksaInfo('nm_pasien', $row['no_rawat']);
        $row['almt_pj'] = $this->core->getRegPeriksaInfo('alamat', $row['no_rawat']);
        $row['jk'] = $this->core->getPasienInfo('jk', $row['no_rkm_medis']);
        $row['umur'] = $this->core->getRegPeriksaInfo('umurdaftar', $row['no_rawat']);
        $row['sttsumur'] = $this->core->getRegPeriksaInfo('sttsumur', $row['no_rawat']);
        $row['tgl_registrasi'] = $this->core->getRegPeriksaInfo('tgl_registrasi', $row['no_rawat']);
        $row['status_lanjut'] = $this->core->getRegPeriksaInfo('status_lanjut', $row['no_rawat']);
        $row['png_jawab'] = $this->core->getPenjabInfo('png_jawab', $this->core->getRegPeriksaInfo('kd_pj', $row['no_rawat']));
        $row['jam_reg'] = $this->core->getRegPeriksaInfo('jam_reg', $row['no_rawat']);
        $row['nm_dokter'] = $this->core->getDokterInfo('nm_dokter', $this->core->getRegPeriksaInfo('kd_dokter', $row['no_rawat']));
        $row['nm_poli'] = $this->core->getPoliklinikInfo('nm_poli', $this->core->getRegPeriksaInfo('kd_poli', $row['no_rawat']));
        $row['no_sitb'] = $this->_getSITB('no_sitb', $row['no_rkm_medis']);
        $row['final'] = $this->_getFinalKlaim('nik', $this->_getSEPInfo('no_sep', $row['no_rawat']));
        $row['no_sep'] = $this->_getSEPInfo('no_sep', $row['no_rawat']);
        $row['no_peserta'] = $this->_getSEPInfo('no_kartu', $row['no_rawat']);
        $row['no_rujukan'] = $this->_getSEPInfo('no_rujukan', $row['no_rawat']);
        $row['kd_penyakit'] = $this->_getDiagnosa('kd_penyakit', $row['no_rawat'], $row['status_lanjut']);
        $row['nm_penyakit'] = $this->_getDiagnosa('nm_penyakit', $row['no_rawat'], $row['status_lanjut']);
        $row['kode'] = $this->_getProsedur('kode', $row['no_rawat'], $row['status_lanjut']);
        $row['deskripsi_panjang'] = $this->_getProsedur('deskripsi_panjang', $row['no_rawat'], $row['status_lanjut']);
        $row['berkas_digital'] = $berkas_digital;
        $row['formSepURL'] = url([ADMIN, 'vedika', 'formsepvclaim', '?no_rawat=' . $row['no_rawat']]);
        $row['pdfURL'] = url([ADMIN, 'vedika', 'pdfklaim', $this->convertNorawat($row['no_rawat'])]);
        $row['createPdfKlaimURL'] = url([ADMIN, 'vedika', 'createpdfklaim', $this->convertNorawat($row['no_rawat'])]);
        $row['setstatusURL']  = url([ADMIN, 'vedika', 'setstatus', $this->_getSEPInfo('no_sep', $row['no_rawat'])]);
        $row['status_lengkap'] = $this->db('mlite_vedika')->where('nosep', $this->_getSEPInfo('no_sep', $row['no_rawat']))->desc('id')->limit(1)->toArray();
        $row['berkasPasien'] = url([ADMIN, 'vedika', 'berkaspasien', $this->getRegPeriksaInfo('no_rkm_medis', $row['no_rawat'])]);
        $row['berkasPerawatan'] = url([ADMIN, 'vedika', 'berkasperawatan', $this->convertNorawat($row['no_rawat'])]);
        $row['pegawai'] = $this->db('mlite_vedika')->join('pegawai','pegawai.nik=mlite_vedika.username')->where('nosep', $this->_getSEPInfo('no_sep', $row['no_rawat']))->desc('mlite_vedika.id')->limit(1)->toArray();
        //$row['pegawai'] = $this->core->getPegawaiInfo('nama', $row['username']);
        if ($type == 'ranap') {
          $_get_kamar_inap = $this->db('kamar_inap')->where('no_rawat', $row['no_rawat'])->limit(1)->desc('tgl_keluar')->toArray();
          $row['tgl_registrasi'] = $_get_kamar_inap[0]['tgl_keluar'];
          $row['jam_reg'] = $_get_kamar_inap[0]['jam_keluar'];
          $get_kamar = $this->db('kamar')->where('kd_kamar', $_get_kamar_inap[0]['kd_kamar'])->oneArray();
          $get_bangsal = $this->db('bangsal')->where('kd_bangsal', $get_kamar['kd_bangsal'])->oneArray();
          $row['nm_poli'] = $get_bangsal['nm_bangsal'].'/'.$get_kamar['kd_kamar'];
          $row['nm_dokter'] = $this->db('dpjp_ranap')
            ->join('dokter', 'dokter.kd_dokter=dpjp_ranap.kd_dokter')
            ->where('no_rawat', $row['no_rawat'])
            ->toArray();
        }
        //pdfklaim
        $kode_pdf_klaim = 'KLM';
        $pdf_klaim = $this->db('berkas_digital_perawatan')
          ->where('no_rawat', $row['no_rawat'])
          ->where('kode', $kode_pdf_klaim)
          ->oneArray();
        
        $row['pdf_klaim_created'] = '';
        $row['pdf_klaim_lokasi'] = '';
        $row['pdf_klaim_url'] = '';
        
        if ($pdf_klaim) {
          $pdf_klaim_path = WEBAPPS_PATHX . '/berkasrawat/' . $pdf_klaim['lokasi_file'];
        
          if (file_exists($pdf_klaim_path)) {
            $row['pdf_klaim_created'] = '1';
            $row['pdf_klaim_lokasi'] = $pdf_klaim['lokasi_file'];
            $row['pdf_klaim_url'] = url(WEBAPPS_URLX) . '/berkasrawat/' . $pdf_klaim['lokasi_file'];
          }
        }
        $this->assign['list'][] = $row;
      }
    }

    $this->core->addCSS(url('assets/jscripts/lightbox/lightbox.min.css'));
    $this->core->addJS(url('assets/jscripts/lightbox/lightbox.min.js'));

    $this->assign['searchUrl'] =  url([ADMIN, 'vedika', 'lengkap', $type, $page . '?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date]);
    return $this->draw('lengkap.html', ['tab' => $type, 'vedika' => $this->assign]);
  }

  public function anyLengkapinap($type = 'ranap', $page = 1)
  {
    if (isset($_POST['submit'])) {
      if (!$this->db('mlite_vedika')->where('nosep', $_POST['nosep'])->oneArray()) {
        $simpan_status = $this->db('mlite_vedika')->save([
          'id' => NULL,
          'tanggal' => date('Y-m-d'),
          'no_rkm_medis' => $_POST['no_rkm_medis'],
          'no_rawat' => $_POST['no_rawat'],
          'tgl_registrasi' => $_POST['tgl_registrasi'],
          'nosep' => $_POST['nosep'],
          'jenis' => '1',
          'status' => $_POST['status'],
          'username' => $this->core->getUserInfo('username', null, true)
        ]);
      } else {
        $simpan_status = $this->db('mlite_vedika')
          ->where('nosep', $_POST['nosep'])
          ->save([
            'tanggal' => date('Y-m-d'),
            'status' => $_POST['status'],
            'jenis' => $_POST['jenis']
          ]);
      }
      if ($simpan_status) {
        $this->db('mlite_vedika_feedback')->save([
          'id' => NULL,
          'nosep' => $_POST['nosep'],
          'tanggal' => date('Y-m-d'),
          'catatan' => $_POST['status'].' - '.$_POST['catatan'],
          'username' => $this->core->getUserInfo('username', null, true)
        ]);
      }
    }

    if (isset($_POST['simpanberkas'])) {

      if(MULTI_APP) {

        $curl = curl_init();
        $filePath = $_FILES['files']['tmp_name'];
        $file_type = $_FILES['files']['type'];
        if($file_type=='application/pdf'){
          $imagick = new \Imagick();
          $imagick->readImage($image);
          $imagick->writeImages($image.'.jpg', false);
          $filePath = $image.'.jpg';
        }

        curl_setopt_array($curl, array(
          CURLOPT_URL => str_replace('webapps','',WEBAPPS_URL).'api/berkasdigital',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS => array('file'=> new \CURLFILE($filePath),'token' => $this->settings->get('api.berkasdigital_key'), 'no_rawat' => $_POST['no_rawat'], 'kode' => $_POST['kode']),
          CURLOPT_HTTPHEADER => array(),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        $json = json_decode($response, true);
        if($json['status'] == 'Success') {
          echo '<br><img src="'.WEBAPPS_URL.'/berkasrawat/'.$json['msg'].'" width="150" />';
        } else {
          echo 'Gagal menambahkan gambar';
        }

      } else {      
        $dir    = $this->_uploads;
        $cntr   = 0;

        $image = $_FILES['files']['tmp_name'];

        $file_type = $_FILES['files']['type'];
        if($file_type=='application/pdf'){
          $imagick = new \Imagick();
          $imagick->readImage($image);
          $imagick->writeImages($image.'.jpg', false);
          $image = $image.'.jpg';
        }

        $img = new \Systems\Lib\Image();
        $id = convertNorawat($_POST['no_rawat']);
        if ($img->load($image)) {
          $imgName = time() . $cntr++;
          $imgPath = $dir . '/' . $id . '_' . $imgName . '.' . $img->getInfos('type');
          $lokasi_file = 'pages/upload/' . $id . '_' . $imgName . '.' . $img->getInfos('type');
          $img->save($imgPath);
          $query = $this->db('berkas_digital_perawatan')->save(['no_rawat' => $_POST['no_rawat'], 'kode' => $_POST['kode'], 'lokasi_file' => $lokasi_file]);
          if ($query) {
            $this->notify('success', 'Simpan berkas digital perawatan sukses.');
          }
        }
      }
    }

    //DELETE BERKAS DIGITAL PERAWATAN
    if (isset($_POST['deleteberkas'])) {
      if ($berkasPerawatan = $this->db('berkas_digital_perawatan')
        ->where('no_rawat', $_POST['no_rawat'])
        ->where('lokasi_file', $_POST['lokasi_file'])
        ->oneArray()
      ) {

        $lokasi_file = $berkasPerawatan['lokasi_file'];
        $no_rawat_file = $berkasPerawatan['no_rawat'];

        chdir('../../'); //directory di mlite/admin/, harus dirubah terlebih dahulu ke /www
        $fileLoc = getcwd() . '/webapps/berkasrawat/' . $lokasi_file;
        if (file_exists($fileLoc)) {
          unlink($fileLoc);
          $query = $this->db('berkas_digital_perawatan')->where('no_rawat', $no_rawat_file)->where('lokasi_file', $lokasi_file)->delete();

          if ($query) {
            $this->notify('success', 'Hapus berkas sukses');
          } else {
            $this->notify('failure', 'Hapus berkas gagal');
          }
        } else {
          $this->notify('failure', 'Hapus berkas gagal, File tidak ada');
        }
        chdir('mlite/admin/'); //mengembalikan directory ke mlite/admin/
      }
    }

    $this->_addHeaderFiles();
    $start_date = date('Y-m-d');
    if (isset($_GET['start_date']) && $_GET['start_date'] != '')
      $start_date = $_GET['start_date'];
    $end_date = date('Y-m-d');
    if (isset($_GET['end_date']) && $_GET['end_date'] != '')
      $end_date = $_GET['end_date'];
    $perpage = '50';
    $phrase = '';
    
    if (isset($_GET['s']))
      $phrase = $_GET['s'];

    // pagination
    $totalRecords = $this->db()->pdo()->prepare("SELECT no_rawat 
    FROM mlite_vedika 
    WHERE 
    status = 'Lengkap'
    AND (no_rkm_medis LIKE ? OR no_rawat LIKE ? OR nosep LIKE ? ) 
    AND no_rawat IN (SELECT no_rawat FROM kamar_inap WHERE tgl_keluar BETWEEN '$start_date' AND '$end_date' AND kamar_inap.stts_pulang != 'Pindah Kamar')");
    $totalRecords->execute(['%' . $phrase . '%', '%' . $phrase . '%', '%' . $phrase . '%']);
    $totalRecords = $totalRecords->fetchAll();

    $pagination = new \Systems\Lib\Pagination($page, count($totalRecords), $perpage, url([ADMIN, 'vedika', 'lengkapinap', $type, '%d?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date]));
    $this->assign['pagination'] = $pagination->nav('pagination', '5');
    $this->assign['totalRecords'] = $totalRecords;
    
    $offset = $pagination->offset();$nomor = $offset + 1;
    $query = $this->db()->pdo()->prepare("SELECT * 
    FROM mlite_vedika 
    WHERE status = 'Lengkap'
    AND (no_rkm_medis LIKE ? OR no_rawat LIKE ? OR nosep LIKE ?) 
    AND no_rawat IN (SELECT no_rawat FROM kamar_inap WHERE tgl_keluar BETWEEN '$start_date' AND '$end_date' AND kamar_inap.stts_pulang != 'Pindah Kamar') 
    order by mlite_vedika.nosep LIMIT $perpage OFFSET $offset");
      $query->execute(['%' . $phrase . '%', '%' . $phrase . '%', '%' . $phrase . '%']);
      $rows = $query->fetchAll();

    $this->assign['list'] = [];
    if (count($rows)) {
      foreach ($rows as $row) {
        $berkas_digital = $this->db('berkas_digital_perawatan')
          ->join('master_berkas_digital', 'master_berkas_digital.kode=berkas_digital_perawatan.kode')
          ->where('berkas_digital_perawatan.no_rawat', $row['no_rawat'])
          ->asc('master_berkas_digital.nama')
          ->toArray();
        $diagnosa_pasien = $this->db('diagnosa_pasien')
          ->join('penyakit', 'penyakit.kd_penyakit = diagnosa_pasien.kd_penyakit')
          ->where('no_rawat', $row['no_rawat'])
          ->where('diagnosa_pasien.status', 'Ranap')
          ->asc('prioritas')
          ->toArray();
        $prosedur_pasien = $this->db('prosedur_pasien')
          ->join('icd9', 'icd9.kode = prosedur_pasien.kode')
          ->where('no_rawat', $row['no_rawat'])
          ->where('status', 'Ranap')
          ->asc('prioritas')
          ->toArray();  

        $no_peserta = $this->core->getPasienInfo('no_peserta', $row['no_rkm_medis']);
    
        $row = htmlspecialchars_array($row);    
        $row['formVclaimURL'] = url([ADMIN, 'vedika', 'formsep', '?no_asuransi=' . $no_peserta .'&no_rawat='.$row['no_rawat']]);         
        $row['diagnosa_pasien'] = $diagnosa_pasien;
        $row['prosedur_pasien'] = $prosedur_pasien;
        $row['nomor'] = $nomor++;
        $row['rkm_medis'] = $this->core->getRegPeriksaInfo('no_rkm_medis', $row['no_rawat']);
        $row['nm_pasien'] = $this->core->getRegPeriksaInfo('nm_pasien', $row['no_rawat']);
        $row['almt_pj'] = $this->core->getRegPeriksaInfo('alamat', $row['no_rawat']);
        $row['jk'] = $this->core->getPasienInfo('jk', $row['no_rkm_medis']);
        $row['umur'] = $this->core->getRegPeriksaInfo('umurdaftar', $row['no_rawat']);
        $row['sttsumur'] = $this->core->getRegPeriksaInfo('sttsumur', $row['no_rawat']);
        $row['tgl_registrasi'] = $this->core->getRegPeriksaInfo('tgl_registrasi', $row['no_rawat']);
        $row['status_lanjut'] = $this->core->getRegPeriksaInfo('status_lanjut', $row['no_rawat']);
        $row['png_jawab'] = $this->core->getPenjabInfo('png_jawab', $this->core->getRegPeriksaInfo('kd_pj', $row['no_rawat']));
        $row['jam_reg'] = $this->core->getRegPeriksaInfo('jam_reg', $row['no_rawat']);
        $row['nm_dokter'] = $this->core->getDokterInfo('nm_dokter', $this->core->getRegPeriksaInfo('kd_dokter', $row['no_rawat']));
        $row['nm_poli'] = $this->core->getPoliklinikInfo('nm_poli', $this->core->getRegPeriksaInfo('kd_poli', $row['no_rawat']));
        $row['no_sitb'] = $this->_getSITB('no_sitb', $row['no_rkm_medis']);
        $row['final'] = $this->_getFinalKlaim('nik', $this->_getSEPInfo('no_sep', $row['no_rawat']));
        $row['resume'] = $this->_getResumeRanap('cara_keluar', $row['no_rawat']);
        $row['no_sep'] = $this->_getSEPInfo('no_sep', $row['no_rawat']);
        $row['no_peserta'] = $this->_getSEPInfo('no_kartu', $row['no_rawat']);
        $row['no_rujukan'] = $this->_getSEPInfo('no_rujukan', $row['no_rawat']);
        $row['kd_penyakit'] = $this->_getDiagnosa('kd_penyakit', $row['no_rawat'], $row['status_lanjut']);
        $row['nm_penyakit'] = $this->_getDiagnosa('nm_penyakit', $row['no_rawat'], $row['status_lanjut']);
        $row['kode'] = $this->_getProsedur('kode', $row['no_rawat'], $row['status_lanjut']);
        $row['deskripsi_panjang'] = $this->_getProsedur('deskripsi_panjang', $row['no_rawat'], $row['status_lanjut']);
        $row['berkas_digital'] = $berkas_digital;        
        $row['formSepURL'] = url([ADMIN, 'vedika', 'formsepvclaim', '?no_rawat=' . $row['no_rawat']]);
        $row['pdfURL'] = url([ADMIN, 'vedika', 'pdfklaim', $this->convertNorawat($row['no_rawat'])]);
        $row['createPdfKlaimURL'] = url([ADMIN, 'vedika', 'createpdfklaim', $this->convertNorawat($row['no_rawat'])]);
        $row['setstatusURL']  = url([ADMIN, 'vedika', 'setstatus', $this->_getSEPInfo('no_sep', $row['no_rawat'])]);
        $row['status_lengkap'] = $this->db('mlite_vedika')->where('nosep', $this->_getSEPInfo('no_sep', $row['no_rawat']))->desc('id')->limit(1)->toArray();
        $row['berkasPasien'] = url([ADMIN, 'vedika', 'berkaspasien', $this->getRegPeriksaInfo('no_rkm_medis', $row['no_rawat'])]);
        $row['berkasPerawatan'] = url([ADMIN, 'vedika', 'berkasperawatan', $this->convertNorawat($row['no_rawat'])]);
        $row['pegawai'] = $this->db('mlite_vedika')->join('pegawai','pegawai.nik=mlite_vedika.username')->where('nosep', $this->_getSEPInfo('no_sep', $row['no_rawat']))->desc('mlite_vedika.id')->limit(1)->toArray();
        //$row['pegawai'] = $this->core->getPegawaiInfo('nama', $row['username']);
        if ($type == 'ranap') {
          $_get_kamar_inap = $this->db('kamar_inap')->where('no_rawat', $row['no_rawat'])->limit(1)->desc('tgl_keluar')->toArray();
          $row['tgl_registrasi'] = $_get_kamar_inap[0]['tgl_keluar'];
          $row['jam_reg'] = $_get_kamar_inap[0]['jam_keluar'];
          $get_kamar = $this->db('kamar')->where('kd_kamar', $_get_kamar_inap[0]['kd_kamar'])->oneArray();
          $get_bangsal = $this->db('bangsal')->where('kd_bangsal', $get_kamar['kd_bangsal'])->oneArray();
          $row['nm_poli'] = $get_bangsal['nm_bangsal'].'/'.$get_kamar['kd_kamar'];
          $row['nm_dokter'] = $this->db('dpjp_ranap')
            ->join('dokter', 'dokter.kd_dokter=dpjp_ranap.kd_dokter')
            ->where('no_rawat', $row['no_rawat'])
            ->toArray();
        }
        
        //pdfklaim
        $kode_pdf_klaim = 'KLM';
        $pdf_klaim = $this->db('berkas_digital_perawatan')
          ->where('no_rawat', $row['no_rawat'])
          ->where('kode', $kode_pdf_klaim)
          ->oneArray();
        
        $row['pdf_klaim_created'] = '';
        $row['pdf_klaim_lokasi'] = '';
        $row['pdf_klaim_url'] = '';
        
        if ($pdf_klaim) {
          $pdf_klaim_path = WEBAPPS_PATHX . '/berkasrawat/' . $pdf_klaim['lokasi_file'];
        
          if (file_exists($pdf_klaim_path)) {
            $row['pdf_klaim_created'] = '1';
            $row['pdf_klaim_lokasi'] = $pdf_klaim['lokasi_file'];
            $row['pdf_klaim_url'] = url(WEBAPPS_URLX) . '/berkasrawat/' . $pdf_klaim['lokasi_file'];
          }
        }
        $this->assign['list'][] = $row;
      }
    }

    $this->core->addCSS(url('assets/jscripts/lightbox/lightbox.min.css'));
    $this->core->addJS(url('assets/jscripts/lightbox/lightbox.min.js'));

    $this->assign['searchUrl'] =  url([ADMIN, 'vedika', 'lengkapinap', $type, $page . '?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date]);
    $this->assign['ralanUrl'] =  url([ADMIN, 'vedika', 'lengkapinap', 'ralan', $page . '?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date]);
    $this->assign['ranapUrl'] =  url([ADMIN, 'vedika', 'lengkapinap', 'ranap', $page . '?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date]);
    return $this->draw('lengkapinap.html', ['tab' => $type, 'vedika' => $this->assign]);
  }

  public function anyPengajuan($type = 'ralan', $page = 1)
  {
    if (isset($_POST['submit'])) {
      if (!$this->db('mlite_vedika')->where('nosep', $_POST['nosep'])->oneArray()) {
        $simpan_status = $this->db('mlite_vedika')->save([
          'id' => NULL,
          'tanggal' => date('Y-m-d'),
          'no_rkm_medis' => $_POST['no_rkm_medis'],
          'no_rawat' => $_POST['no_rawat'],
          'tgl_registrasi' => $_POST['tgl_registrasi'],
          'nosep' => $_POST['nosep'],
          'jenis' => '2',
          'status' => $_POST['status'],
          'username' => $this->core->getUserInfo('username', null, true)
        ]);
      } else {
        $simpan_status = $this->db('mlite_vedika')
          ->where('nosep', $_POST['nosep'])
          ->save([
            'tanggal' => date('Y-m-d'),
            'status' => $_POST['status'],
            'jenis' => $_POST['jenis']
          ]);
      }
      if ($simpan_status) {
        $this->db('mlite_vedika_feedback')->save([
          'id' => NULL,
          'nosep' => $_POST['nosep'],
          'tanggal' => date('Y-m-d'),
          'catatan' => $_POST['status'].' - '.$_POST['catatan'],
          'username' => $this->core->getUserInfo('username', null, true)
        ]);
      }
    }

    if (isset($_POST['simpanberkas'])) {

      if(MULTI_APP) {

        $curl = curl_init();
        $filePath = $_FILES['files']['tmp_name'];
        $file_type = $_FILES['files']['type'];
        if($file_type=='application/pdf'){
          $imagick = new \Imagick();
          $imagick->readImage($image);
          $imagick->writeImages($image.'.jpg', false);
          $filePath = $image.'.jpg';
        }

        curl_setopt_array($curl, array(
          CURLOPT_URL => str_replace('webapps','',WEBAPPS_URL).'api/berkasdigital',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS => array('file'=> new \CURLFILE($filePath),'token' => $this->settings->get('api.berkasdigital_key'), 'no_rawat' => $_POST['no_rawat'], 'kode' => $_POST['kode']),
          CURLOPT_HTTPHEADER => array(),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        $json = json_decode($response, true);
        if($json['status'] == 'Success') {
          echo '<br><img src="'.WEBAPPS_URL.'/berkasrawat/'.$json['msg'].'" width="150" />';
        } else {
          echo 'Gagal menambahkan gambar';
        }

      } else {      
        $dir    = $this->_uploads;
        $cntr   = 0;

        $image = $_FILES['files']['tmp_name'];

        $file_type = $_FILES['files']['type'];
        if($file_type=='application/pdf'){
          $imagick = new \Imagick();
          $imagick->readImage($image);
          $imagick->writeImages($image.'.jpg', false);
          $image = $image.'.jpg';
        }

        $img = new \Systems\Lib\Image();
        $id = convertNorawat($_POST['no_rawat']);
        if ($img->load($image)) {
          $imgName = time() . $cntr++;
          $imgPath = $dir . '/' . $id . '_' . $imgName . '.' . $img->getInfos('type');
          $lokasi_file = 'pages/upload/' . $id . '_' . $imgName . '.' . $img->getInfos('type');
          $img->save($imgPath);
          $query = $this->db('berkas_digital_perawatan')->save(['no_rawat' => $_POST['no_rawat'], 'kode' => $_POST['kode'], 'lokasi_file' => $lokasi_file]);
          if ($query) {
            $this->notify('success', 'Simpan berkas digital perawatan sukses.');
          }
        }
      }
    }

    //DELETE BERKAS DIGITAL PERAWATAN
    if (isset($_POST['deleteberkas'])) {
      if ($berkasPerawatan = $this->db('berkas_digital_perawatan')
        ->where('no_rawat', $_POST['no_rawat'])
        ->where('lokasi_file', $_POST['lokasi_file'])
        ->oneArray()
      ) {

        $lokasi_file = $berkasPerawatan['lokasi_file'];
        $no_rawat_file = $berkasPerawatan['no_rawat'];

        chdir('../../'); //directory di mlite/admin/, harus dirubah terlebih dahulu ke /www
        $fileLoc = getcwd() . '/webapps/berkasrawat/' . $lokasi_file;
        if (file_exists($fileLoc)) {
          unlink($fileLoc);
          $query = $this->db('berkas_digital_perawatan')->where('no_rawat', $no_rawat_file)->where('lokasi_file', $lokasi_file)->delete();

          if ($query) {
            $this->notify('success', 'Hapus berkas sukses');
          } else {
            $this->notify('failure', 'Hapus berkas gagal');
          }
        } else {
          $this->notify('failure', 'Hapus berkas gagal, File tidak ada');
        }
        chdir('mlite/admin/'); //mengembalikan directory ke mlite/admin/
      }
    }

    $this->_addHeaderFiles();
    $start_date = date('Y-m-d');
    if (isset($_GET['start_date']) && $_GET['start_date'] != '')
      $start_date = $_GET['start_date'];
    $end_date = date('Y-m-d');
    if (isset($_GET['end_date']) && $_GET['end_date'] != '')
      $end_date = $_GET['end_date'];
    $perpage = '10';
    $phrase = '';
    
    if (isset($_GET['s']))
      $phrase = $_GET['s'];
      
    $poli = '';
    if (isset($_GET['poli']) && $_GET['poli'] != '')
      $poli = $_GET['poli'];
      
    $poliklinik = $this->db('poliklinik')
          ->where('status', '1')
          ->notIn ('kd_poli',['U0015','U0016','U0033','U0035','U0036','U0041','U0047','U0031','U0052','U0058'])
          ->asc('nm_poli')
          ->toArray(); 
    $this->assign['poliklinik'] = $poliklinik; 

    // pagination
    $totalRecords = $this->db()->pdo()->prepare("SELECT no_rawat FROM mlite_vedika WHERE status = 'Pengajuan' AND mlite_vedika.kd_poli LIKE '%$poli%' AND jenis ='2' AND (no_rkm_medis LIKE ? OR no_rawat LIKE ? OR nosep LIKE ?) AND tgl_registrasi BETWEEN '$start_date' AND '$end_date'");
    $totalRecords->execute(['%' . $phrase . '%', '%' . $phrase . '%', '%' . $phrase . '%']);
    $totalRecords = $totalRecords->fetchAll();

    $pagination = new \Systems\Lib\Pagination($page, count($totalRecords), $perpage, url([ADMIN, 'vedika', 'pengajuan', $type, '%d?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date]));
    $this->assign['pagination'] = $pagination->nav('pagination', '5');
    $this->assign['totalRecords'] = $totalRecords;

    $offset = $pagination->offset();$nomor = $offset + 1;
    $query = $this->db()->pdo()->prepare("SELECT * FROM mlite_vedika WHERE status = 'Pengajuan' AND mlite_vedika.kd_poli LIKE '%$poli%' AND jenis ='2' AND (no_rkm_medis LIKE ? OR no_rawat LIKE ? OR nosep LIKE ?) AND tgl_registrasi BETWEEN '$start_date' AND '$end_date' ORDER BY nosep LIMIT $perpage OFFSET $offset");
    $query->execute(['%' . $phrase . '%', '%' . $phrase . '%', '%' . $phrase . '%']);
    $rows = $query->fetchAll();
    
    $this->assign['list'] = [];
    if (count($rows)) {
      foreach ($rows as $row) {
        $berkas_digital = $this->db('berkas_digital_perawatan')
          ->join('master_berkas_digital', 'master_berkas_digital.kode=berkas_digital_perawatan.kode')
          ->where('berkas_digital_perawatan.no_rawat', $row['no_rawat'])
          ->asc('master_berkas_digital.nama')
          ->toArray();

        $diagnosa_pasienx = $this->db('diagnosa_pasien')
          ->join('penyakit', 'penyakit.kd_penyakit = diagnosa_pasien.kd_penyakit')
          ->where('no_rawat', $row['no_rawat'])
          ->where('diagnosa_pasien.status', 'Ralan')
          ->asc('prioritas')
          ->toArray();
        $prosedur_pasienx = $this->db('prosedur_pasien')
          ->join('icd9', 'icd9.kode = prosedur_pasien.kode')
          ->where('no_rawat', $row['no_rawat'])
          ->where('status', 'Ralan')
          ->asc('prioritas')
          ->toArray();       
          
        $no_peserta = $this->core->getPasienInfo('no_peserta', $row['no_rkm_medis']);

        $row = htmlspecialchars_array($row);   
        $row['formVclaimURL'] = url([ADMIN, 'vedika', 'formsep', '?no_asuransi=' . $no_peserta .'&no_rawat='.$row['no_rawat']]);
        $row['diagnosa_pasienx'] = $diagnosa_pasienx;
        $row['prosedur_pasienx'] = $prosedur_pasienx;
        $row['nomor'] = $nomor++;
        $row['nm_pasien'] = $this->core->getPasienInfo('nm_pasien', $row['no_rkm_medis']);
        $row['almt_pj'] = $this->core->getPasienInfo('alamat', $row['no_rkm_medis']);
        $row['jk'] = $this->core->getPasienInfo('jk', $row['no_rkm_medis']);
        $row['umur'] = $this->core->getRegPeriksaInfo('umurdaftar', $row['no_rawat']);
        $row['sttsumur'] = $this->core->getRegPeriksaInfo('sttsumur', $row['no_rawat']);
        $row['tgl_registrasi'] = $this->core->getRegPeriksaInfo('tgl_registrasi', $row['no_rawat']);
        $row['status_lanjut'] = $this->core->getRegPeriksaInfo('status_lanjut', $row['no_rawat']);
        $row['png_jawab'] = $this->core->getPenjabInfo('png_jawab', $this->core->getRegPeriksaInfo('kd_pj', $row['no_rawat']));
        $row['jam_reg'] = $this->core->getRegPeriksaInfo('jam_reg', $row['no_rawat']);
        $row['nm_dokter'] = $this->core->getDokterInfo('nm_dokter', $this->core->getRegPeriksaInfo('kd_dokter', $row['no_rawat']));
        $row['nm_poli'] = $this->core->getPoliklinikInfo('nm_poli', $this->core->getRegPeriksaInfo('kd_poli', $row['no_rawat']));
        $row['no_sitb'] = $this->_getSITB('no_sitb', $row['no_rkm_medis']);
        $row['final'] = $this->_getFinalKlaim('nik', $this->_getSEPInfo('no_sep', $row['no_rawat']));
        $row['no_sep'] = $this->_getSEPInfo('no_sep', $row['no_rawat']);
        $row['no_peserta'] = $this->_getSEPInfo('no_kartu', $row['no_rawat']);
        $row['no_rujukan'] = $this->_getSEPInfo('no_rujukan', $row['no_rawat']);
        $row['kd_penyakit'] = $this->_getDiagnosa('kd_penyakit', $row['no_rawat'], $row['status_lanjut']);
        $row['nm_penyakit'] = $this->_getDiagnosa('nm_penyakit', $row['no_rawat'], $row['status_lanjut']);
        $row['kode'] = $this->_getProsedur('kode', $row['no_rawat'], $row['status_lanjut']);
        $row['deskripsi_panjang'] = $this->_getProsedur('deskripsi_panjang', $row['no_rawat'], $row['status_lanjut']);
        $row['berkas_digital'] = $berkas_digital;
        $row['formSepURL'] = url([ADMIN, 'vedika', 'formsepvclaim', '?no_rawat=' . $row['no_rawat']]);
        $row['pdfURL'] = url([ADMIN, 'vedika', 'pdfklaim', $this->convertNorawat($row['no_rawat'])]);
        $row['createPdfKlaimURL'] = url([ADMIN, 'vedika', 'createpdfklaim', $this->convertNorawat($row['no_rawat'])]);
        $row['setstatusURL']  = url([ADMIN, 'vedika', 'setstatus', $this->_getSEPInfo('no_sep', $row['no_rawat'])]);
        $row['status_pengajuan'] = $this->db('mlite_vedika')->where('nosep', $this->_getSEPInfo('no_sep', $row['no_rawat']))->desc('id')->limit(1)->toArray();
        $row['berkasPasien'] = url([ADMIN, 'vedika', 'berkaspasien', $this->getRegPeriksaInfo('no_rkm_medis', $row['no_rawat'])]);
        $row['berkasPerawatan'] = url([ADMIN, 'vedika', 'berkasperawatan', $this->convertNorawat($row['no_rawat'])]);
        $row['pegawai'] = $this->db('mlite_vedika')->join('pegawai','pegawai.nik=mlite_vedika.username')->where('nosep', $this->_getSEPInfo('no_sep', $row['no_rawat']))->desc('mlite_vedika.id')->limit(1)->toArray();
        //$row['pegawai'] = $this->core->getPegawaiInfo('nama', $row['username']);
        if ($type == 'ranap') {
          $_get_kamar_inap = $this->db('kamar_inap')->where('no_rawat', $row['no_rawat'])->limit(1)->desc('tgl_keluar')->toArray();
          $row['tgl_registrasi'] = $_get_kamar_inap[0]['tgl_keluar'];
          $row['jam_reg'] = $_get_kamar_inap[0]['jam_keluar'];
          $get_kamar = $this->db('kamar')->where('kd_kamar', $_get_kamar_inap[0]['kd_kamar'])->oneArray();
          $get_bangsal = $this->db('bangsal')->where('kd_bangsal', $get_kamar['kd_bangsal'])->oneArray();
          $row['nm_poli'] = $get_bangsal['nm_bangsal'].'/'.$get_kamar['kd_kamar'];
          $row['nm_dokter'] = $this->db('dpjp_ranap')
            ->join('dokter', 'dokter.kd_dokter=dpjp_ranap.kd_dokter')
            ->where('no_rawat', $row['no_rawat'])
            ->toArray();
        }
        //pdfklaim
        $kode_pdf_klaim = 'KLM';
        $pdf_klaim = $this->db('berkas_digital_perawatan')
          ->where('no_rawat', $row['no_rawat'])
          ->where('kode', $kode_pdf_klaim)
          ->oneArray();
        
        $row['pdf_klaim_created'] = '';
        $row['pdf_klaim_lokasi'] = '';
        $row['pdf_klaim_url'] = '';
        
        if ($pdf_klaim) {
          $pdf_klaim_path = WEBAPPS_PATHX . '/berkasrawat/' . $pdf_klaim['lokasi_file'];
        
          if (file_exists($pdf_klaim_path)) {
            $row['pdf_klaim_created'] = '1';
            $row['pdf_klaim_lokasi'] = $pdf_klaim['lokasi_file'];
            $row['pdf_klaim_url'] = url(WEBAPPS_URLX) . '/berkasrawat/' . $pdf_klaim['lokasi_file'];
          }
        }
        $this->assign['list'][] = $row;
      }
    }

    $this->core->addCSS(url('assets/jscripts/lightbox/lightbox.min.css'));
    $this->core->addJS(url('assets/jscripts/lightbox/lightbox.min.js'));

    $this->assign['searchUrl'] =  url([ADMIN, 'vedika', 'pengajuan', $type, $page . '?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date]);
    $this->assign['ralanUrl'] =  url([ADMIN, 'vedika', 'pengajuan', 'ralan', $page . '?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date]);
    $this->assign['ranapUrl'] =  url([ADMIN, 'vedika', 'pengajuan', 'ranap', $page . '?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date]);
    return $this->draw('pengajuan.html', ['tab' => $type, 'vedika' => $this->assign]);
  }

  public function anyPengajuaninap($type = 'ralan', $page = 1)
  {
    if (isset($_POST['submit'])) {
      if (!$this->db('mlite_vedika')->where('nosep', $_POST['nosep'])->oneArray()) {
        $simpan_status = $this->db('mlite_vedika')->save([
          'id' => NULL,
          'tanggal' => date('Y-m-d'),
          'no_rkm_medis' => $_POST['no_rkm_medis'],
          'no_rawat' => $_POST['no_rawat'],
          'tgl_registrasi' => $_POST['tgl_registrasi'],
          'nosep' => $_POST['nosep'],
          'jenis' => '1',
          'status' => $_POST['status'],
          'username' => $this->core->getUserInfo('username', null, true)
        ]);
      } else {
        $simpan_status = $this->db('mlite_vedika')
          ->where('nosep', $_POST['nosep'])
          ->save([
            'tanggal' => date('Y-m-d'),
            'status' => $_POST['status'],
            'jenis' => $_POST['jenis']
          ]);
      }
      if ($simpan_status) {
        $this->db('mlite_vedika_feedback')->save([
          'id' => NULL,
          'nosep' => $_POST['nosep'],
          'tanggal' => date('Y-m-d'),
          'catatan' => $_POST['status'].' - '.$_POST['catatan'],
          'username' => $this->core->getUserInfo('username', null, true)
        ]);
      }
    }

    if (isset($_POST['simpanberkas'])) {

      if(MULTI_APP) {

        $curl = curl_init();
        $filePath = $_FILES['files']['tmp_name'];
        $file_type = $_FILES['files']['type'];
        if($file_type=='application/pdf'){
          $imagick = new \Imagick();
          $imagick->readImage($image);
          $imagick->writeImages($image.'.jpg', false);
          $filePath = $image.'.jpg';
        }

        curl_setopt_array($curl, array(
          CURLOPT_URL => str_replace('webapps','',WEBAPPS_URL).'api/berkasdigital',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS => array('file'=> new \CURLFILE($filePath),'token' => $this->settings->get('api.berkasdigital_key'), 'no_rawat' => $_POST['no_rawat'], 'kode' => $_POST['kode']),
          CURLOPT_HTTPHEADER => array(),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        $json = json_decode($response, true);
        if($json['status'] == 'Success') {
          echo '<br><img src="'.WEBAPPS_URL.'/berkasrawat/'.$json['msg'].'" width="150" />';
        } else {
          echo 'Gagal menambahkan gambar';
        }

      } else {      
        $dir    = $this->_uploads;
        $cntr   = 0;

        $image = $_FILES['files']['tmp_name'];

        $file_type = $_FILES['files']['type'];
        if($file_type=='application/pdf'){
          $imagick = new \Imagick();
          $imagick->readImage($image);
          $imagick->writeImages($image.'.jpg', false);
          $image = $image.'.jpg';
        }

        $img = new \Systems\Lib\Image();
        $id = convertNorawat($_POST['no_rawat']);
        if ($img->load($image)) {
          $imgName = time() . $cntr++;
          $imgPath = $dir . '/' . $id . '_' . $imgName . '.' . $img->getInfos('type');
          $lokasi_file = 'pages/upload/' . $id . '_' . $imgName . '.' . $img->getInfos('type');
          $img->save($imgPath);
          $query = $this->db('berkas_digital_perawatan')->save(['no_rawat' => $_POST['no_rawat'], 'kode' => $_POST['kode'], 'lokasi_file' => $lokasi_file]);
          if ($query) {
            $this->notify('success', 'Simpan berkas digital perawatan sukses.');
          }
        }
      }
    }

    //DELETE BERKAS DIGITAL PERAWATAN
    if (isset($_POST['deleteberkas'])) {
      if ($berkasPerawatan = $this->db('berkas_digital_perawatan')
        ->where('no_rawat', $_POST['no_rawat'])
        ->where('lokasi_file', $_POST['lokasi_file'])
        ->oneArray()
      ) {

        $lokasi_file = $berkasPerawatan['lokasi_file'];
        $no_rawat_file = $berkasPerawatan['no_rawat'];

        chdir('../../'); //directory di mlite/admin/, harus dirubah terlebih dahulu ke /www
        $fileLoc = getcwd() . '/webapps/berkasrawat/' . $lokasi_file;
        if (file_exists($fileLoc)) {
          unlink($fileLoc);
          $query = $this->db('berkas_digital_perawatan')->where('no_rawat', $no_rawat_file)->where('lokasi_file', $lokasi_file)->delete();

          if ($query) {
            $this->notify('success', 'Hapus berkas sukses');
          } else {
            $this->notify('failure', 'Hapus berkas gagal');
          }
        } else {
          $this->notify('failure', 'Hapus berkas gagal, File tidak ada');
        }
        chdir('mlite/admin/'); //mengembalikan directory ke mlite/admin/
      }
    }

    $this->_addHeaderFiles();
    $start_date = date('Y-m-d');
    if (isset($_GET['start_date']) && $_GET['start_date'] != '')
      $start_date = $_GET['start_date'];
    $end_date = date('Y-m-d');
    if (isset($_GET['end_date']) && $_GET['end_date'] != '')
      $end_date = $_GET['end_date'];
    $perpage = '10';
    $phrase = '';
    
    if (isset($_GET['s']))
      $phrase = $_GET['s'];

    // pagination
    $totalRecords = $this->db()->pdo()->prepare("SELECT no_rawat 
    FROM mlite_vedika 
    WHERE status = 'Pengajuan'     
    AND (no_rkm_medis LIKE ? OR no_rawat LIKE ? OR nosep LIKE ?) 
    AND no_rawat IN (SELECT no_rawat FROM kamar_inap WHERE tgl_keluar BETWEEN '$start_date' AND '$end_date' AND kamar_inap.stts_pulang != 'Pindah Kamar')");
      $totalRecords->execute(['%' . $phrase . '%', '%' . $phrase . '%', '%' . $phrase . '%']);
      $totalRecords = $totalRecords->fetchAll();

      $pagination = new \Systems\Lib\Pagination($page, count($totalRecords), $perpage, url([ADMIN, 'vedika', 'pengajuaninap', $type, '%d?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date]));
      $this->assign['pagination'] = $pagination->nav('pagination', '5');
      $this->assign['totalRecords'] = $totalRecords;

      $offset = $pagination->offset();$nomor = $offset + 1;
      $query = $this->db()->pdo()->prepare("SELECT * 
      FROM mlite_vedika 
      WHERE status = 'Pengajuan' 
      AND (no_rkm_medis LIKE ? OR no_rawat LIKE ? OR nosep LIKE ?) 
      AND no_rawat IN (SELECT no_rawat FROM kamar_inap WHERE tgl_keluar BETWEEN '$start_date' AND '$end_date' AND kamar_inap.stts_pulang != 'Pindah Kamar') 
      order by mlite_vedika.nosep LIMIT $perpage OFFSET $offset");
      $query->execute(['%' . $phrase . '%', '%' . $phrase . '%', '%' . $phrase . '%']);
      $rows = $query->fetchAll();
    
     $this->assign['list'] = [];
    if (count($rows)) {
      foreach ($rows as $row) {
        $berkas_digital = $this->db('berkas_digital_perawatan')
          ->join('master_berkas_digital', 'master_berkas_digital.kode=berkas_digital_perawatan.kode')
          ->where('berkas_digital_perawatan.no_rawat', $row['no_rawat'])
          ->asc('master_berkas_digital.nama')
          ->toArray();

        $diagnosa_pasienx = $this->db('diagnosa_pasien')
          ->join('penyakit', 'penyakit.kd_penyakit = diagnosa_pasien.kd_penyakit')
          ->where('no_rawat', $row['no_rawat'])
          ->where('diagnosa_pasien.status', 'Ranap')
          ->asc('prioritas')
          ->toArray();
        $prosedur_pasienx = $this->db('prosedur_pasien')
          ->join('icd9', 'icd9.kode = prosedur_pasien.kode')
          ->where('no_rawat', $row['no_rawat'])
          ->where('status', 'Ranap')
          ->asc('prioritas')
          ->toArray();       

        $row = htmlspecialchars_array($row);        
        $row['diagnosa_pasienx'] = $diagnosa_pasienx;
        $row['prosedur_pasienx'] = $prosedur_pasienx;
        $row['nomor'] = $nomor++;
        $row['nm_pasien'] = $this->core->getPasienInfo('nm_pasien', $row['no_rkm_medis']);
        $row['almt_pj'] = $this->core->getPasienInfo('alamat', $row['no_rkm_medis']);
        $row['jk'] = $this->core->getPasienInfo('jk', $row['no_rkm_medis']);
        $row['umur'] = $this->core->getRegPeriksaInfo('umurdaftar', $row['no_rawat']);
        $row['sttsumur'] = $this->core->getRegPeriksaInfo('sttsumur', $row['no_rawat']);
        $row['tgl_registrasi'] = $this->core->getRegPeriksaInfo('tgl_registrasi', $row['no_rawat']);
        $row['status_lanjut'] = $this->core->getRegPeriksaInfo('status_lanjut', $row['no_rawat']);
        $row['png_jawab'] = $this->core->getPenjabInfo('png_jawab', $this->core->getRegPeriksaInfo('kd_pj', $row['no_rawat']));
        $row['jam_reg'] = $this->core->getRegPeriksaInfo('jam_reg', $row['no_rawat']);
        $row['nm_dokter'] = $this->core->getDokterInfo('nm_dokter', $this->core->getRegPeriksaInfo('kd_dokter', $row['no_rawat']));
        $row['nm_poli'] = $this->core->getPoliklinikInfo('nm_poli', $this->core->getRegPeriksaInfo('kd_poli', $row['no_rawat']));
        $row['no_sitb'] = $this->_getSITB('no_sitb', $row['no_rkm_medis']);
        $row['final'] = $this->_getFinalKlaim('nik', $this->_getSEPInfo('no_sep', $row['no_rawat']));
        $row['no_sep'] = $this->_getSEPInfo('no_sep', $row['no_rawat']);
        $row['no_peserta'] = $this->_getSEPInfo('no_kartu', $row['no_rawat']);
        $row['no_rujukan'] = $this->_getSEPInfo('no_rujukan', $row['no_rawat']);
        $row['kd_penyakit'] = $this->_getDiagnosa('kd_penyakit', $row['no_rawat'], $row['status_lanjut']);
        $row['nm_penyakit'] = $this->_getDiagnosa('nm_penyakit', $row['no_rawat'], $row['status_lanjut']);
        $row['kode'] = $this->_getProsedur('kode', $row['no_rawat'], $row['status_lanjut']);
        $row['deskripsi_panjang'] = $this->_getProsedur('deskripsi_panjang', $row['no_rawat'], $row['status_lanjut']);
        $row['berkas_digital'] = $berkas_digital;
        $row['formSepURL'] = url([ADMIN, 'vedika', 'formsepvclaim', '?no_rawat=' . $row['no_rawat']]);
        $row['pdfURL'] = url([ADMIN, 'vedika', 'pdfklaim', $this->convertNorawat($row['no_rawat'])]);
        $row['createPdfKlaimURL'] = url([ADMIN, 'vedika', 'createpdfklaim', $this->convertNorawat($row['no_rawat'])]);
        $row['setstatusURL']  = url([ADMIN, 'vedika', 'setstatus', $this->_getSEPInfo('no_sep', $row['no_rawat'])]);
        $row['status_pengajuan'] = $this->db('mlite_vedika')->where('nosep', $this->_getSEPInfo('no_sep', $row['no_rawat']))->desc('id')->limit(1)->toArray();
        $row['berkasPasien'] = url([ADMIN, 'vedika', 'berkaspasien', $this->getRegPeriksaInfo('no_rkm_medis', $row['no_rawat'])]);
        $row['berkasPerawatan'] = url([ADMIN, 'vedika', 'berkasperawatan', $this->convertNorawat($row['no_rawat'])]);
        $row['pegawai'] = $this->db('mlite_vedika')->join('pegawai','pegawai.nik=mlite_vedika.username')->where('nosep', $this->_getSEPInfo('no_sep', $row['no_rawat']))->desc('mlite_vedika.id')->limit(1)->toArray();
        //$row['pegawai'] = $this->core->getPegawaiInfo('nama', $row['username']);
        if ($type == 'ranap') {
          $_get_kamar_inap = $this->db('kamar_inap')->where('no_rawat', $row['no_rawat'])->limit(1)->desc('tgl_keluar')->toArray();
          $row['tgl_registrasi'] = $_get_kamar_inap[0]['tgl_keluar'];
          $row['jam_reg'] = $_get_kamar_inap[0]['jam_keluar'];
          $get_kamar = $this->db('kamar')->where('kd_kamar', $_get_kamar_inap[0]['kd_kamar'])->oneArray();
          $get_bangsal = $this->db('bangsal')->where('kd_bangsal', $get_kamar['kd_bangsal'])->oneArray();
          $row['nm_poli'] = $get_bangsal['nm_bangsal'].'/'.$get_kamar['kd_kamar'];
          $row['nm_dokter'] = $this->db('dpjp_ranap')
            ->join('dokter', 'dokter.kd_dokter=dpjp_ranap.kd_dokter')
            ->where('no_rawat', $row['no_rawat'])
            ->toArray();
        }
        //pdfklaim
        $kode_pdf_klaim = 'KLM';
        $pdf_klaim = $this->db('berkas_digital_perawatan')
          ->where('no_rawat', $row['no_rawat'])
          ->where('kode', $kode_pdf_klaim)
          ->oneArray();
        
        $row['pdf_klaim_created'] = '';
        $row['pdf_klaim_lokasi'] = '';
        $row['pdf_klaim_url'] = '';
        
        if ($pdf_klaim) {
          $pdf_klaim_path = WEBAPPS_PATHX . '/berkasrawat/' . $pdf_klaim['lokasi_file'];
        
          if (file_exists($pdf_klaim_path)) {
            $row['pdf_klaim_created'] = '1';
            $row['pdf_klaim_lokasi'] = $pdf_klaim['lokasi_file'];
            $row['pdf_klaim_url'] = url(WEBAPPS_URLX) . '/berkasrawat/' . $pdf_klaim['lokasi_file'];
          }
        }
        $this->assign['list'][] = $row;
      }
    }

    $this->core->addCSS(url('assets/jscripts/lightbox/lightbox.min.css'));
    $this->core->addJS(url('assets/jscripts/lightbox/lightbox.min.js'));

    $this->assign['searchUrl'] =  url([ADMIN, 'vedika', 'pengajuaninap', $type, $page . '?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date]);
    $this->assign['ralanUrl'] =  url([ADMIN, 'vedika', 'pengajuaninap', 'ralan', $page . '?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date]);
    $this->assign['ranapUrl'] =  url([ADMIN, 'vedika', 'pengajuaninap', 'ranap', $page . '?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date]);
    return $this->draw('pengajuaninap.html', ['tab' => $type, 'vedika' => $this->assign]);
  }

  public function getIndexExcel()
  {
    $start_date = $_GET['start_date'];
    $end_date = $_GET['end_date'];
    $rows = $this->db('reg_periksa')
    ->select('mlite_vedika.status')
    ->select('reg_periksa.*')
    // ->select('reg_periksa.no_rawat')
    // ->select('reg_periksa.no_rkm_medis')
    // ->select('reg_periksa.tgl_registrasi')
    // ->select('bridging_sep.no_sep')
    // ->join('bridging_sep', 'briding_sep.no_rawat=reg_periksa.no_rawat')
    ->join('maping_poli_bpjs_real','maping_poli_bpjs_real.kd_poli_rs=reg_periksa.kd_poli')
    ->leftJoin('mlite_vedika','mlite_vedika.no_rawat=reg_periksa.no_rawat')
    ->where('reg_periksa.kd_pj','=','BPJ')
    ->where('reg_periksa.stts','!=','Batal')
    // ->where('reg_periksa.kd_poli','!=','U0015')
    // ->where('reg_periksa.kd_poli','!=','U0016')
    // ->where('reg_periksa.kd_poli','!=','U0035')
    // ->where('reg_periksa.kd_poli','!=','U0021')
    // ->where('reg_periksa.kd_poli','!=','U0045')
    ->where('reg_periksa.tgl_registrasi','>=',$start_date)
    ->where('reg_periksa.tgl_registrasi','<=', $end_date)
    ->where('status_lanjut','=','Ralan')
    ->desc('mlite_vedika.status')
    ->asc('reg_periksa.tgl_registrasi')
    ->toArray();
    $i = 1;
    foreach ($rows as $row) {
      $row['no'] = $i++;
      $row['tgl_masuk'] = $this->core->getRegPeriksaInfo('tgl_registrasi', $row['no_rawat']);
      // $row['tgl_keluar'] = $this->core->getRegPeriksaInfo('tgl_registrasi', $row['no_rawat']);
      $row['nm_pasien'] = $this->core->getPasienInfo('nm_pasien', $row['no_rkm_medis']);
      $row['no_peserta'] = $this->core->getPasienInfo('no_peserta', $row['no_rkm_medis']);
      $row['nosep'] = $this->_getSEPInfo('no_sep', $row['no_rawat']);
      $row['status'];
      // $row['kd_penyakit'] = $this->_getDiagnosa('kd_penyakit', $row['no_rawat'], $row['status_lanjut']);
      // $row['kd_prosedur'] = $this->_getProsedur('kode', $row['no_rawat'], $row['status_lanjut']);
      // $get_feedback_bpjs = $this->db('mlite_vedika_feedback')->where('nosep', $row['nosep'])->where('username', 'bpjs')->oneArray();
      // $row['konfirmasi_bpjs'] = $get_feedback_bpjs['catatan'];
      // $get_feedback_rs = $this->db('mlite_vedika_feedback')->where('nosep', $row['nosep'])->where('username','!=','bpjs')->oneArray();
      // $row['konfirmasi_rs'] = $get_feedback_rs['catatan'];
      $display[] = $row;
    }

    $this->tpl->set('display', $display);

    echo $this->tpl->draw(MODULES . '/vedika/view/admin/index_excel.html', true);
    exit();
  }

  public function getIndexInapExcel()
  {
    $start_date = $_GET['start_date'];
    $end_date = $_GET['end_date'];
    $rows = $this->db('kamar_inap')
    ->select('kamar_inap.*')
    ->select('mlite_vedika.status')
    ->select('reg_periksa.*')
    // ->select('bridging_sep.no_sep')
    ->join('reg_periksa', 'reg_periksa.no_rawat=kamar_inap.no_rawat')
    ->leftJoin('mlite_vedika','mlite_vedika.no_rawat=reg_periksa.no_rawat')
    ->where('reg_periksa.kd_pj','=','BPJ')
    ->where('kamar_inap.tgl_keluar','>=',$start_date)
    ->where('kamar_inap.tgl_keluar','<=', $end_date)
    ->group('kamar_inap.no_rawat')
    // ->where('status_lanjut','=','Ranap')
    ->asc('kamar_inap.tgl_keluar')
    ->toArray();
    $i = 1;
    foreach ($rows as $row) {
      $row['no'] = $i++;
      $row['tgl_masuk'];
      $row['tgl_keluar'];
      $row['kd_kamar'];
      $row['nm_pasien'] = $this->core->getPasienInfo('nm_pasien', $row['no_rkm_medis']);
      $row['no_peserta'] = $this->core->getPasienInfo('no_peserta', $row['no_rkm_medis']);
      $row['status'];
      $row['nosep'] = $this->_getSEPInfo('no_sep', $row['no_rawat']);
      // $row['kd_penyakit'] = $this->_getDiagnosa('kd_penyakit', $row['no_rawat'], $row['status_lanjut']);
      // $row['kd_prosedur'] = $this->_getProsedur('kode', $row['no_rawat'], $row['status_lanjut']);
      // $get_feedback_bpjs = $this->db('mlite_vedika_feedback')->where('nosep', $row['nosep'])->where('username', 'bpjs')->oneArray();
      // $row['konfirmasi_bpjs'] = $get_feedback_bpjs['catatan'];
      // $get_feedback_rs = $this->db('mlite_vedika_feedback')->where('nosep', $row['nosep'])->where('username','!=','bpjs')->oneArray();
      // $row['konfirmasi_rs'] = $get_feedback_rs['catatan'];
      $display[] = $row;
    }

    $this->tpl->set('display', $display);

    echo $this->tpl->draw(MODULES . '/vedika/view/admin/indexinap_excel.html', true);
    exit();
  }

  public function getLengkapExcel()
  {
    $start_date = $_GET['start_date'];
    $end_date = $_GET['end_date'];
    $rows = $this->db('mlite_vedika')
    ->where('status', 'Lengkap')
    ->where('tgl_registrasi','>=',$start_date)
    ->where('tgl_registrasi','<=', $end_date)
    ->asc('nosep')
    ->toArray();
    if(isset($_GET['jenis']) && $_GET['jenis'] == 1) {
      $rows = $this->db('mlite_vedika')->where('status', 'Lengkap')->where('tgl_registrasi','>=',$start_date)->where('tgl_registrasi','<=', $end_date)->where('jenis', 1)->asc('nosep')->toArray();
    }
    if(isset($_GET['jenis']) && $_GET['jenis'] == 2) {
      $rows = $this->db('mlite_vedika')->where('status', 'Lengkap')->where('tgl_registrasi','>=',$start_date)->where('tgl_registrasi','<=', $end_date)->where('jenis', 2)->asc('nosep')->toArray();
    }
    $i = 1;
    foreach ($rows as $row) {
      $row['status_lanjut'] = 'Ralan';
      if($row['jenis'] == 1) {
        $row['status_lanjut'] = 'Ranap';
      }
      $row['no'] = $i++;
      $row['tgl_masuk'] = $this->core->getRegPeriksaInfo('tgl_registrasi', $row['no_rawat']);
      $row['tgl_keluar'] = $this->core->getRegPeriksaInfo('tgl_registrasi', $row['no_rawat']);
      if($row['jenis'] == 1) {
        $row['tgl_masuk'] = $this->core->getKamarInapInfo('tgl_masuk', $row['no_rawat']);
        $row['tgl_keluar'] = $this->core->getKamarInapInfo('tgl_keluar', $row['no_rawat']);
      }
      $row['nm_pasien'] = $this->core->getPasienInfo('nm_pasien', $row['no_rkm_medis']);
      $row['no_peserta'] = $this->core->getPasienInfo('no_peserta', $row['no_rkm_medis']);
    //   $row['kd_penyakit'] = $this->_getDiagnosa('kd_penyakit', $row['no_rawat'], $row['status_lanjut']);
    //   $row['kd_prosedur'] = $this->_getProsedur('kode', $row['no_rawat'], $row['status_lanjut']);
    //   $get_feedback_bpjs = $this->db('mlite_vedika_feedback')->where('nosep', $row['nosep'])->where('username', 'bpjs')->oneArray();
    //   $row['konfirmasi_bpjs'] = $get_feedback_bpjs['catatan'];
    //   $get_feedback_rs = $this->db('mlite_vedika_feedback')->where('nosep', $row['nosep'])->where('username','!=','bpjs')->oneArray();
    //   $row['konfirmasi_rs'] = $get_feedback_rs['catatan'];
      $display[] = $row;
    }

    $this->tpl->set('display', $display);

    echo $this->tpl->draw(MODULES . '/vedika/view/admin/lengkap_excel.html', true);
    exit();
  }

  public function getPengajuanExcel()
  {
    $start_date = $_GET['start_date'];
    $end_date = $_GET['end_date'];
    $rows = $this->db('mlite_vedika')->where('status', 'Pengajuan')->where('tgl_registrasi','>=',$start_date)->where('tgl_registrasi','<=', $end_date)->toArray();
    if(isset($_GET['jenis']) && $_GET['jenis'] == 1) {
      $rows = $this->db('mlite_vedika')->where('status', 'Pengajuan')->where('tgl_registrasi','>=',$start_date)->where('tgl_registrasi','<=', $end_date)->where('jenis', 1)->toArray();
    }
    if(isset($_GET['jenis']) && $_GET['jenis'] == 2) {
      $rows = $this->db('mlite_vedika')->where('status', 'Pengajuan')->where('tgl_registrasi','>=',$start_date)->where('tgl_registrasi','<=', $end_date)->where('jenis', 2)->toArray();
    }
    $i = 1;
    foreach ($rows as $row) {
      $row['status_lanjut'] = 'Ralan';
      if($row['jenis'] == 1) {
        $row['status_lanjut'] = 'Ranap';
      }
      $row['no'] = $i++;
      $row['tgl_masuk'] = $this->core->getRegPeriksaInfo('tgl_registrasi', $row['no_rawat']);
      $row['tgl_keluar'] = $this->core->getRegPeriksaInfo('tgl_registrasi', $row['no_rawat']);
      if($row['jenis'] == 1) {
        $row['tgl_masuk'] = $this->core->getKamarInapInfo('tgl_masuk', $row['no_rawat']);
        $row['tgl_keluar'] = $this->core->getKamarInapInfo('tgl_keluar', $row['no_rawat']);
      }
      $row['nm_pasien'] = $this->core->getPasienInfo('nm_pasien', $row['no_rkm_medis']);
      $row['no_peserta'] = $this->core->getPasienInfo('no_peserta', $row['no_rkm_medis']);
      $row['kd_penyakit'] = $this->_getDiagnosa('kd_penyakit', $row['no_rawat'], $row['status_lanjut']);
      $row['kd_prosedur'] = $this->_getProsedur('kode', $row['no_rawat'], $row['status_lanjut']);
      $get_feedback_bpjs = $this->db('mlite_vedika_feedback')->where('nosep', $row['nosep'])->where('username', 'bpjs')->oneArray();
      $row['konfirmasi_bpjs'] = $get_feedback_bpjs['catatan'];
      $get_feedback_rs = $this->db('mlite_vedika_feedback')->where('nosep', $row['nosep'])->where('username','!=','bpjs')->oneArray();
      $row['konfirmasi_rs'] = $get_feedback_rs['catatan'];
      $display[] = $row;
    }

    $this->tpl->set('display', $display);

    echo $this->tpl->draw(MODULES . '/vedika/view/admin/pengajuan_excel.html', true);
    exit();
  }

  public function getPerbaikanExcel()
  {
    $start_date = $_GET['start_date'];
    $end_date = $_GET['end_date'];
    $rows = $this->db('mlite_vedika')->where('status', 'Perbaikan')->where('tgl_registrasi','>=',$start_date)->where('tgl_registrasi','<=', $end_date)->toArray();
    if(isset($_GET['jenis']) && $_GET['jenis'] == 1) {
      $rows = $this->db('mlite_vedika')->where('status', 'Perbaikan')->where('tgl_registrasi','>=',$start_date)->where('tgl_registrasi','<=', $end_date)->where('jenis', 1)->toArray();
    }
    if(isset($_GET['jenis']) && $_GET['jenis'] == 2) {
      $rows = $this->db('mlite_vedika')->where('status', 'Perbaikan')->where('tgl_registrasi','>=',$start_date)->where('tgl_registrasi','<=', $end_date)->where('jenis', 2)->toArray();
    }
    $i = 1;
    foreach ($rows as $row) {
      $row['status_lanjut'] = 'Ralan';
      if($row['jenis'] == 1) {
        $row['status_lanjut'] = 'Ranap';
      }
      $row['no'] = $i++;
      $row['tgl_masuk'] = $this->core->getRegPeriksaInfo('tgl_registrasi', $row['no_rawat']);
      $row['tgl_keluar'] = $this->core->getRegPeriksaInfo('tgl_registrasi', $row['no_rawat']);
      if($row['jenis'] == 1) {
        $row['tgl_masuk'] = $this->core->getKamarInapInfo('tgl_masuk', $row['no_rawat']);
        $row['tgl_keluar'] = $this->core->getKamarInapInfo('tgl_keluar', $row['no_rawat']);
      }
      $row['nm_pasien'] = $this->core->getPasienInfo('nm_pasien', $row['no_rkm_medis']);
      $row['no_peserta'] = $this->core->getPasienInfo('no_peserta', $row['no_rkm_medis']);
      $row['kd_penyakit'] = $this->_getDiagnosa('kd_penyakit', $row['no_rawat'], $row['status_lanjut']);
      $row['kd_prosedur'] = $this->_getProsedur('kode', $row['no_rawat'], $row['status_lanjut']);
      $get_feedback_bpjs = $this->db('mlite_vedika_feedback')->where('nosep', $row['nosep'])->where('username', 'bpjs')->oneArray();
      $row['konfirmasi_bpjs'] = $get_feedback_bpjs['catatan'];
      $get_feedback_rs = $this->db('mlite_vedika_feedback')->where('nosep', $row['nosep'])->where('username','!=','bpjs')->oneArray();
      $row['konfirmasi_rs'] = $get_feedback_rs['catatan'];
      $display[] = $row;
    }

    $this->tpl->set('display', $display);

    echo $this->tpl->draw(MODULES . '/vedika/view/admin/perbaikan_excel.html', true);
    exit();
  }

  public function anyPerbaikan($type = 'ralan', $page = 1)
  {
    if (isset($_POST['submit'])) {
      if (!$this->db('mlite_vedika')->where('nosep', $_POST['nosep'])->oneArray()) {
        $simpan_status = $this->db('mlite_vedika')->save([
          'id' => NULL,
          'tanggal' => date('Y-m-d'),
          'no_rkm_medis' => $_POST['no_rkm_medis'],
          'no_rawat' => $_POST['no_rawat'],
          'tgl_registrasi' => $_POST['tgl_registrasi'],
          'nosep' => $_POST['nosep'],
          'jenis' => $_POST['jnspelayanan'],
          'status' => $_POST['status'],
          'username' => $this->core->getUserInfo('username', null, true)
        ]);
      } else {
        $simpan_status = $this->db('mlite_vedika')
          ->where('nosep', $_POST['nosep'])
          ->save([
            'tanggal' => date('Y-m-d'),
            'status' => $_POST['status'],
            'jenis' => $_POST['jenis']
          ]);
      }
      if ($simpan_status) {
        $this->db('mlite_vedika_feedback')->save([
          'id' => NULL,
          'nosep' => $_POST['nosep'],
          'tanggal' => date('Y-m-d'),
          'catatan' => $_POST['status'].' - '.$_POST['catatan'],
          'username' => $this->core->getUserInfo('username', null, true)
        ]);
      }
    }

    if (isset($_POST['simpanberkas'])) {

      if(MULTI_APP) {

        $curl = curl_init();
        $filePath = $_FILES['files']['tmp_name'];
        $file_type = $_FILES['files']['type'];
        if($file_type=='application/pdf'){
          $imagick = new \Imagick();
          $imagick->readImage($image);
          $imagick->writeImages($image.'.jpg', false);
          $filePath = $image.'.jpg';
        }

        curl_setopt_array($curl, array(
          CURLOPT_URL => str_replace('webapps','',WEBAPPS_URL).'api/berkasdigital',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS => array('file'=> new \CURLFILE($filePath),'token' => $this->settings->get('api.berkasdigital_key'), 'no_rawat' => $_POST['no_rawat'], 'kode' => $_POST['kode']),
          CURLOPT_HTTPHEADER => array(),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        $json = json_decode($response, true);
        if($json['status'] == 'Success') {
          echo '<br><img src="'.WEBAPPS_URL.'/berkasrawat/'.$json['msg'].'" width="150" />';
        } else {
          echo 'Gagal menambahkan gambar';
        }

      } else {      
        $dir    = $this->_uploads;
        $cntr   = 0;

        $image = $_FILES['files']['tmp_name'];

        $file_type = $_FILES['files']['type'];
        if($file_type=='application/pdf'){
          $imagick = new \Imagick();
          $imagick->readImage($image);
          $imagick->writeImages($image.'.jpg', false);
          $image = $image.'.jpg';
        }

        $img = new \Systems\Lib\Image();
        $id = convertNorawat($_POST['no_rawat']);
        if ($img->load($image)) {
          $imgName = time() . $cntr++;
          $imgPath = $dir . '/' . $id . '_' . $imgName . '.' . $img->getInfos('type');
          $lokasi_file = 'pages/upload/' . $id . '_' . $imgName . '.' . $img->getInfos('type');
          $img->save($imgPath);
          $query = $this->db('berkas_digital_perawatan')->save(['no_rawat' => $_POST['no_rawat'], 'kode' => $_POST['kode'], 'lokasi_file' => $lokasi_file]);
          if ($query) {
            $this->notify('success', 'Simpan berkas digital perawatan sukses.');
          }
        }
      }
    }

    //DELETE BERKAS DIGITAL PERAWATAN
    if (isset($_POST['deleteberkas'])) {
      if ($berkasPerawatan = $this->db('berkas_digital_perawatan')
        ->where('no_rawat', $_POST['no_rawat'])
        ->where('lokasi_file', $_POST['lokasi_file'])
        ->oneArray()
      ) {

        $lokasi_file = $berkasPerawatan['lokasi_file'];
        $no_rawat_file = $berkasPerawatan['no_rawat'];

        chdir('../../'); //directory di mlite/admin/, harus dirubah terlebih dahulu ke /www
        $fileLoc = getcwd() . '/webapps/berkasrawat/' . $lokasi_file;
        if (file_exists($fileLoc)) {
          unlink($fileLoc);
          $query = $this->db('berkas_digital_perawatan')->where('no_rawat', $no_rawat_file)->where('lokasi_file', $lokasi_file)->delete();

          if ($query) {
            $this->notify('success', 'Hapus berkas sukses');
          } else {
            $this->notify('failure', 'Hapus berkas gagal');
          }
        } else {
          $this->notify('failure', 'Hapus berkas gagal, File tidak ada');
        }
        chdir('mlite/admin/'); //mengembalikan directory ke mlite/admin/
      }
    }

    $this->_addHeaderFiles();
    $start_date = date('Y-m-d');
    if (isset($_GET['start_date']) && $_GET['start_date'] != '')
      $start_date = $_GET['start_date'];
    $end_date = date('Y-m-d');
    if (isset($_GET['end_date']) && $_GET['end_date'] != '')
      $end_date = $_GET['end_date'];
    $perpage = '10';
    $phrase = '';
    
    if (isset($_GET['s']))
      $phrase = $_GET['s'];

    // pagination
    $totalRecords = $this->db()->pdo()->prepare("SELECT no_rawat FROM mlite_vedika WHERE status = 'Perbaiki' AND (no_rkm_medis LIKE ? OR no_rawat LIKE ? OR nosep LIKE ?) AND tgl_registrasi BETWEEN '$start_date' AND '$end_date'");
    $totalRecords->execute(['%' . $phrase . '%', '%' . $phrase . '%', '%' . $phrase . '%']);
    $totalRecords = $totalRecords->fetchAll();

    $pagination = new \Systems\Lib\Pagination($page, count($totalRecords), $perpage, url([ADMIN, 'vedika', 'perbaikan', $type, '%d?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date]));
    $this->assign['pagination'] = $pagination->nav('pagination', '5');
    $this->assign['totalRecords'] = $totalRecords;

    $offset = $pagination->offset();$nomor = $offset + 1;
    $query = $this->db()->pdo()->prepare("SELECT mlite_vedika.* FROM mlite_vedika WHERE status = 'Perbaiki' AND (no_rkm_medis LIKE ? OR no_rawat LIKE ? OR nosep LIKE ?) AND mlite_vedika.tgl_registrasi BETWEEN '$start_date' AND '$end_date' LIMIT $perpage OFFSET $offset");
    $query->execute(['%' . $phrase . '%', '%' . $phrase . '%', '%' . $phrase . '%']);
    $rows = $query->fetchAll();
    
    $this->assign['list'] = [];
    if (count($rows)) {
      foreach ($rows as $row) {
        $berkas_digital = $this->db('berkas_digital_perawatan')
          ->join('master_berkas_digital', 'master_berkas_digital.kode=berkas_digital_perawatan.kode')
          ->where('berkas_digital_perawatan.no_rawat', $row['no_rawat'])
          ->asc('master_berkas_digital.nama')
          ->toArray();

        $diagnosa_pasienx = $this->db('diagnosa_pasien')
          ->join('penyakit', 'penyakit.kd_penyakit = diagnosa_pasien.kd_penyakit')
          ->where('no_rawat', $row['no_rawat'])
          ->asc('prioritas')
          ->toArray();
        $prosedur_pasienx = $this->db('prosedur_pasien')
          ->join('icd9', 'icd9.kode = prosedur_pasien.kode')
          ->where('no_rawat', $row['no_rawat'])
          ->asc('prioritas')
          ->toArray(); 

        $row = htmlspecialchars_array($row);        
        $row['diagnosa_pasienx'] = $diagnosa_pasienx;
        $row['prosedur_pasienx'] = $prosedur_pasienx;
        $row['nomor'] = $nomor++;
        $row['rkm_medis'] = $this->core->getRegPeriksaInfo('no_rkm_medis', $row['no_rawat']);
        $row['nm_pasien'] = $this->core->getRegPeriksaInfo('nm_pasien', $row['no_rawat']);
        $row['almt_pj'] = $this->core->getRegPeriksaInfo('alamat', $row['no_rawat']);
        $row['jk'] = $this->core->getPasienInfo('jk', $row['no_rkm_medis']);
        $row['umur'] = $this->core->getRegPeriksaInfo('umurdaftar', $row['no_rawat']);
        $row['sttsumur'] = $this->core->getRegPeriksaInfo('sttsumur', $row['no_rawat']);
        $row['tgl_registrasi'] = $this->core->getRegPeriksaInfo('tgl_registrasi', $row['no_rawat']);
        $row['status_lanjut'] = $this->core->getRegPeriksaInfo('status_lanjut', $row['no_rawat']);
        $row['png_jawab'] = $this->core->getPenjabInfo('png_jawab', $this->core->getRegPeriksaInfo('kd_pj', $row['no_rawat']));
        $row['jam_reg'] = $this->core->getRegPeriksaInfo('jam_reg', $row['no_rawat']);
        $row['nm_dokter'] = $this->core->getDokterInfo('nm_dokter', $this->core->getRegPeriksaInfo('kd_dokter', $row['no_rawat']));
        $row['nm_poli'] = $this->core->getPoliklinikInfo('nm_poli', $this->core->getRegPeriksaInfo('kd_poli', $row['no_rawat']));
        $row['no_sitb'] = $this->_getSITB('no_sitb', $row['no_rkm_medis']);
        $row['final'] = $this->_getFinalKlaim('nik', $this->_getSEPInfo('no_sep', $row['no_rawat']));
        $row['no_sep'] = $this->_getSEPInfo('no_sep', $row['no_rawat']);
        $row['no_peserta'] = $this->_getSEPInfo('no_kartu', $row['no_rawat']);
        $row['no_rujukan'] = $this->_getSEPInfo('no_rujukan', $row['no_rawat']);
        $row['kd_penyakit'] = $this->_getDiagnosa('kd_penyakit', $row['no_rawat'], $row['status_lanjut']);
        $row['nm_penyakit'] = $this->_getDiagnosa('nm_penyakit', $row['no_rawat'], $row['status_lanjut']);
        $row['kode'] = $this->_getProsedur('kode', $row['no_rawat'], $row['status_lanjut']);
        $row['deskripsi_panjang'] = $this->_getProsedur('deskripsi_panjang', $row['no_rawat'], $row['status_lanjut']);
        $row['berkas_digital'] = $berkas_digital;
        $row['formSepURL'] = url([ADMIN, 'vedika', 'formsepvclaim', '?no_rawat=' . $row['no_rawat']]);
        $row['pdfURL'] = url([ADMIN, 'vedika', 'pdf', $this->convertNorawat($row['no_rawat'])]);
        $row['setstatusURL']  = url([ADMIN, 'vedika', 'setstatus', $this->_getSEPInfo('no_sep', $row['no_rawat'])]);
        $row['status_pengajuan'] = $this->db('mlite_vedika')->where('nosep', $this->_getSEPInfo('no_sep', $row['no_rawat']))->desc('id')->limit(1)->toArray();
        $row['berkasPasien'] = url([ADMIN, 'vedika', 'berkaspasien', $this->getRegPeriksaInfo('no_rkm_medis', $row['no_rawat'])]);
        $row['berkasPerawatan'] = url([ADMIN, 'vedika', 'berkasperawatan', $this->convertNorawat($row['no_rawat'])]);
        if ($this->core->getRegPeriksaInfo('status_lanjut', $row['no_rawat']) == 'Ranap') {
          $row['tgl_registrasi'] = $this->core->getKamarInapInfo('tgl_keluar', $row['no_rawat']);
          $row['jam_reg'] = $this->core->getKamarInapInfo('jam_keluar', $row['no_rawat']);
          $get_kamar = $this->db('kamar')->where('kd_kamar', $this->core->getKamarInapInfo('kd_kamar', $row['no_rawat']))->oneArray();
          $get_bangsal = $this->db('bangsal')->where('kd_bangsal', $get_kamar['kd_bangsal'])->oneArray();
          $row['nm_poli'] = $get_bangsal['nm_bangsal'].'/'.$get_kamar['kd_kamar'];
          $row['nm_dokter'] = $this->getDpjpRanap('nm_dokter', $row['no_rawat']);
        }
        $this->assign['list'][] = $row;
      }
    }

    $this->core->addCSS(url('assets/jscripts/lightbox/lightbox.min.css'));
    $this->core->addJS(url('assets/jscripts/lightbox/lightbox.min.js'));

    $this->assign['searchUrl'] =  url([ADMIN, 'vedika', 'perbaikan', $type, $page . '?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date]);
    $this->assign['ralanUrl'] =  url([ADMIN, 'vedika', 'perbaikan', 'ralan', $page . '?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date]);
    $this->assign['ranapUrl'] =  url([ADMIN, 'vedika', 'perbaikan', 'ranap', $page . '?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date]);
    return $this->draw('perbaikan.html', ['tab' => $type, 'vedika' => $this->assign]);
  }

  public function getFormSEPVClaim()
  {
    $this->tpl->set('poliklinik', $this->db('poliklinik')->where('status', '1')->toArray());
    $this->tpl->set('dokter', $this->db('dokter')->where('status', '1')->toArray());
    echo $this->tpl->draw(MODULES . '/vedika/view/admin/form.sepvclaim.html', true);
    exit();
  }

  public function getFormSEP()
  {
    // $this->tpl->set('no_asuransi', $this->db('poliklinik')->where('status', '1')->toArray());
    // $this->tpl->set('dokter', $this->db('dokter')->where('status', '1')->toArray());
    echo $this->tpl->draw(MODULES . '/vedika/view/admin/form.sep.html', true);
    exit();
  }

  public function getHapus($no_sep)
  {
    $query = $this->db('bridging_sep')->where('no_sep', $no_sep)->delete();
    if ($query) {
      $this->db('bpjs_prb')->where('no_sep', $no_sep)->delete();
    }
    echo 'No SEP ' . $no_sep . ' telah dihapus.!!';
    exit();
  }

  public function getHapusBerkas($no_rawat, $nama_file)
  {
    $berkasPerawatan = $this->db('berkas_digital_perawatan')->where('no_rawat', revertNorawat($no_rawat))->like('lokasi_file', '%'.$nama_file.'%')->oneArray();
    if ($berkasPerawatan) {
      $lokasi_file = $berkasPerawatan['lokasi_file'];
      $fileLoc = WEBAPPS_PATH . '/berkasrawat/' . $lokasi_file;
      if (file_exists($fileLoc)) {
        //unlink($fileLoc);
        $query = $this->db('berkas_digital_perawatan')->where('no_rawat', revertNorawat($no_rawat))->where('lokasi_file', $lokasi_file)->delete();
        if ($query) {
          echo 'Hapus berkas sukses';
        } else {
          echo 'Hapus berkas gagal';
        }
      } else {
        echo json_encode($berkasPerawatan);
        echo 'Hapus berkas gagal, berkas tidak ditemukan.';
      }
    } else {
      echo 'Hapus berkas gagal, tidak ada data perawatan.';
    }
    exit();
  }

  public function postSaveSEP()
  {
    $date = date('Y-m-d');
    date_default_timezone_set('UTC');
    $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
    $key = $this->consid . $this->secretkey . $tStamp;

    header('Content-type: text/html');
    $url = $this->settings->get('settings.BpjsApiUrl') . 'SEP/' . $_POST['no_sep'];
    $consid = $this->settings->get('settings.BpjsConsID');
    $secretkey = $this->settings->get('settings.BpjsSecretKey');
    $userkey = $this->settings->get('settings.BpjsUserKey');
    $output = BpjsService::get($url, NULL, $consid, $secretkey, $userkey, $tStamp);
    $data = json_decode($output, true);
    // print_r($output);
    $code = $data['metaData']['code'];
    $message = $data['metaData']['message'];
    $stringDecrypt = stringDecrypt($key, $data['response']);
    $decompress = '""';
    if (!empty($stringDecrypt)) {
      $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent(($stringDecrypt));
    }
    if ($data != null) {
      $data = '{
          "metaData": {
            "code": "' . $code . '",
            "message": "' . $message . '"
          },
          "response": ' . $decompress . '}';
      $data = json_decode($data, true);
    } else {
      $data = '{
          "metaData": {
            "code": "5000",
            "message": "ERROR"
          },
          "response": "ADA KESALAHAN ATAU SAMBUNGAN KE SERVER BPJS TERPUTUS."}';
      $data = json_decode($data, true);
    }

    $jenis_pelayanan = '2';
    if ($data['response']['jnsPelayanan'] == 'Rawat Inap') {
      $jenis_pelayanan = '1';
    }
    // get data sep
    echo json_encode($data);
    $data_rujukan = [];
    $no_telp = "00000000";

    // print_r($jenis_pelayanan);

    if ($jenis_pelayanan == '2'){  
      if ($data['response']['noRujukan'] == "") {
        $data_rujukan['response']['rujukan']['tglKunjungan'] = $_POST['tgl_kunjungan'];
        $data_rujukan['response']['rujukan']['provPerujuk']['kode'] = $this->settings->get('settings.ppk_bpjs');
        $data_rujukan['response']['rujukan']['provPerujuk']['nama'] = $this->settings->get('settings.nama_instansi');
        $data_rujukan['response']['rujukan']['diagnosa']['kode'] = $_POST['kd_diagnosa'];
        $data_rujukan['response']['rujukan']['diagnosa']['nama'] = $data['response']['diagnosa'];
        $data_rujukan['response']['rujukan']['pelayanan']['kode'] = $jenis_pelayanan;
      } else {
        $url_rujukan = $this->settings->get('settings.BpjsApiUrl') . 'Rujukan/' . $data['response']['noRujukan'];
        if ($_POST['asal_rujukan'] == 2) {
          $url_rujukan = $this->settings->get('settings.BpjsApiUrl') . 'Rujukan/RS/' . $data['response']['noRujukan'];
        }
        $rujukan = BpjsService::get($url_rujukan, NULL, $consid, $secretkey, $userkey, $tStamp);
        $data_rujukan = json_decode($rujukan, true);
        // rujukan
        // print_r($data_rujukan['response']['rujukan']['tglKunjungan']);

        $code = $data_rujukan['metaData']['code'];
        $message = $data_rujukan['metaData']['message'];
        $stringDecrypt = stringDecrypt($key, $data_rujukan['response']);
        $decompress = '""';
        if (!empty($stringDecrypt)) {
          $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent(($stringDecrypt));
        }
        if ($data_rujukan != null) {
          $data_rujukan = '{
              "metaData": {
                "code": "' . $code . '",
                "message": "' . $message . '"
              },
              "response": ' . $decompress . '}';
          $data_rujukan = json_decode($data_rujukan, true);
        } else {
          $data_rujukan = '{
              "metaData": {
                "code": "5000",
                "message": "ERROR"
              },
              "response": "ADA KESALAHAN ATAU SAMBUNGAN KE SERVER BPJS TERPUTUS."}';
          $data_rujukan = json_decode($data_rujukan, true);
        }

        // rujukan
        // echo json_encode($data_rujukan);
        $no_telp = $data_rujukan['response']['rujukan']['peserta']['mr']['noTelepon'];
        if (empty($data_rujukan['response']['rujukan']['peserta']['mr']['noTelepon'])) {
          $no_telp = '00000000';
        }

        if ($data_rujukan['metaData']['code'] == 201) {
          $data_rujukan['response']['rujukan']['tglKunjungan'] = $_POST['tgl_kunjungan'];
          $data_rujukan['response']['rujukan']['provPerujuk']['kode'] = $this->settings->get('settings.ppk_bpjs');
          $data_rujukan['response']['rujukan']['provPerujuk']['nama'] = $this->settings->get('settings.nama_instansi');
          $data_rujukan['response']['rujukan']['diagnosa']['kode'] = $_POST['kd_diagnosa'];
          $data_rujukan['response']['rujukan']['diagnosa']['nama'] = $data['response']['diagnosa'];
          $data_rujukan['response']['rujukan']['pelayanan']['kode'] = $jenis_pelayanan;
        } else if ($data_rujukan['metaData']['code'] == 202) {
          $data_rujukan['response']['rujukan']['tglKunjungan'] = $_POST['tgl_kunjungan'];
          $data_rujukan['response']['rujukan']['provPerujuk']['kode'] = $this->settings->get('settings.ppk_bpjs');
          $data_rujukan['response']['rujukan']['provPerujuk']['nama'] = $this->settings->get('settings.nama_instansi');
          $data_rujukan['response']['rujukan']['diagnosa']['kode'] = $_POST['kd_diagnosa'];
          $data_rujukan['response']['rujukan']['diagnosa']['nama'] = $data['response']['diagnosa'];
          $data_rujukan['response']['rujukan']['pelayanan']['kode'] = $jenis_pelayanan;
        }
      }

        if($data['response']['dpjp']['kdDPJP'] =='0')
          {
            $data['response']['dpjp']['kdDPJP'] = $this->db('maping_dokter_dpjpvclaim')->where('kd_dokter', $_POST['kd_dokter'])->oneArray()['kd_dokter_bpjs'];
            $data['response']['dpjp']['nmDPJP'] = $this->db('maping_dokter_dpjpvclaim')->where('kd_dokter', $_POST['kd_dokter'])->oneArray()['nm_dokter_bpjs'];
          }

          if ($data['metaData']['code'] == 200) {
            $insert = $this->db('bridging_sep')->save([
              'no_sep' => $data['response']['noSep'],
              'no_rawat' => $_POST['no_rawat'],
              'tglsep' => $data['response']['tglSep'],
              'tglrujukan' => $data_rujukan['response']['rujukan']['tglKunjungan'],
              'no_rujukan' => $data['response']['noRujukan'],
              'kdppkrujukan' => $data_rujukan['response']['rujukan']['provPerujuk']['kode'],
              'nmppkrujukan' => $data_rujukan['response']['rujukan']['provPerujuk']['nama'],
              'kdppkpelayanan' => $this->settings->get('settings.ppk_bpjs'),
              'nmppkpelayanan' => $this->settings->get('settings.nama_instansi'),
              'jnspelayanan' => $jenis_pelayanan,
              'catatan' => $data['response']['catatan'],
              'diagawal' => $data_rujukan['response']['rujukan']['diagnosa']['kode'],
              'nmdiagnosaawal' => $data_rujukan['response']['rujukan']['diagnosa']['nama'],
              'kdpolitujuan' => $this->db('maping_poli_bpjs')->where('kd_poli_rs', $_POST['kd_poli'])->oneArray()['kd_poli_bpjs'],
              // 'kdpolitujuan' => $this->db('maping_poli_bpjs')->where('nm_poli_bpjs', $data['response']['poli'] )->oneArray()['kd_poli_bpjs'],
              'nmpolitujuan' => $this->db('maping_poli_bpjs')->where('kd_poli_rs', $_POST['kd_poli'])->oneArray()['nm_poli_bpjs'],
              // 'nmpolitujuan' => $data['response']['poli'],
              'klsrawat' =>  $data['response']['klsRawat']['klsRawatHak'],
              'klsnaik' => $data['response']['klsRawat']['klsRawatNaik'] == null ? "" : $data['response']['klsRawat']['klsRawatNaik'],
              'pembiayaan' => $data['response']['klsRawat']['pembiayaan']  == null ? "" : $data['response']['klsRawat']['pembiayaan'],
              'pjnaikkelas' => $data['response']['klsRawat']['penanggungJawab']  == null ? "" : $data['response']['klsRawat']['penanggungJawab'],
              'lakalantas' => '0',
              'user' => $this->core->getUserInfo('username', null, true),
              'nomr' => $this->getRegPeriksaInfo('no_rkm_medis', $_POST['no_rawat']),
              'nama_pasien' => $data['response']['peserta']['nama'],
              'tanggal_lahir' => $data['response']['peserta']['tglLahir'],
              'peserta' => $data['response']['peserta']['jnsPeserta'],
              'jkel' => $data['response']['peserta']['kelamin'],
              'no_kartu' => $data['response']['peserta']['noKartu'],
              'tglpulang' => $data['response']['tglSep'],
              'asal_rujukan' => $data_rujukan['response']['asalFaskes'],
              'eksekutif' => '0. Tidak',
              'cob' => '0. Tidak',
              'notelep' => $no_telp,
              'katarak' => '0. Tidak',
              'tglkkl' => '0000-00-00',
              'keterangankkl' => '-',
              'suplesi' => '0. Tidak',
              'no_sep_suplesi' => '-',
              'kdprop' => '-',
              'nmprop' => '-',
              'kdkab' => '-',
              'nmkab' => '-',
              'kdkec' => '-',
              'nmkec' => '-',
              'noskdp' => '0',
              'kddpjp' => $this->db('maping_dokter_dpjpvclaim')->where('kd_dokter', $_POST['kd_dokter'])->oneArray()['kd_dokter_bpjs'],
              'nmdpdjp' => $this->db('maping_dokter_dpjpvclaim')->where('kd_dokter', $_POST['kd_dokter'])->oneArray()['nm_dokter_bpjs'],
              // 'kddpjp' => $data['response']['dpjp']['kdDPJP'],
              // 'nmdpdjp' => $data['response']['dpjp']['nmDPJP'],
              'tujuankunjungan' => $data['response']['tujuanKunj']['kode'],
              'flagprosedur' => $data['response']['flagProcedure']['kode'],
              'penunjang' => $data['response']['kdPenunjang']['kode'],
              'asesmenpelayanan' => $data['response']['assestmenPel']['kode'],
              'kddpjplayanan' => $data['response']['dpjp']['kdDPJP'],
              'nmdpjplayanan' => $data['response']['dpjp']['nmDPJP']
            ]);
          }
          print_r($insert);
          if ($insert) {
            $this->db('bpjs_prb')->save(['no_sep' => $data['response']['noSep'], 'prb' => $data_rujukan['response']['rujukan']['peserta']['informasi']['prolanisPRB']]);
            $this->notify('success', 'Simpan sukes');
            // window.history.back();
            redirect(url([ADMIN, 'vedika', 'index']));
          } else {
            $this->notify('failure', 'Simpan gagal');
            redirect(url([ADMIN, 'vedika', 'index']));
          }
    }
    else{
      // print_r($jenis_pelayanan);
      $url_rujukan = $this->settings->get('settings.BpjsApiUrl') . 'Rujukan/' . $data['response']['noRujukan'];
      if ($_POST['asal_rujukan'] == 2) {
        $url_rujukan = $this->settings->get('settings.BpjsApiUrl') . 'Rujukan/RS/' . $data['response']['noRujukan'];
      }
      $rujukan = BpjsService::get($url_rujukan, NULL, $consid, $secretkey, $userkey, $tStamp);
      $data_rujukan = json_decode($rujukan, true);
      echo json_encode($data_rujukan);
      // rujukan
      $code = $data_rujukan['metaData']['code'];
      $message = $data_rujukan['metaData']['message'];
      $stringDecrypt = stringDecrypt($key, $data_rujukan['response']);
      $decompress = '""';
      if (!empty($stringDecrypt)) {
        $decompress = \LZCompressor\LZString::decompressFromEncodedURIComponent(($stringDecrypt));
      }
      
          if($data['response']['dpjp']['kdDPJP'] =='0')
        {
          $data['response']['dpjp']['kdDPJP'] = $this->db('maping_dokter_dpjpvclaim')->where('kd_dokter', $_POST['kd_dokter'])->oneArray()['kd_dokter_bpjs'];
          $data['response']['dpjp']['nmDPJP'] = $this->db('maping_dokter_dpjpvclaim')->where('kd_dokter', $_POST['kd_dokter'])->oneArray()['nm_dokter_bpjs'];
        }

        if ($data['metaData']['code'] == 200) {
          $insert = $this->db('bridging_sep')->save([
            'no_sep' => $data['response']['noSep'],
            'no_rawat' => $_POST['no_rawat'],
            'tglsep' => $data['response']['tglSep'],
            'tglrujukan' => $_POST['tgl_kunjungan'],
            'no_rujukan' => $data['response']['noRujukan'],
            'kdppkrujukan' => $this->settings->get('settings.ppk_bpjs'),
            'nmppkrujukan' => $this->settings->get('settings.nama_instansi'),
            'kdppkpelayanan' => $this->settings->get('settings.ppk_bpjs'),
            'nmppkpelayanan' => $this->settings->get('settings.nama_instansi'),
            'jnspelayanan' => $jenis_pelayanan,
            'catatan' => $data['response']['catatan'],
            'diagawal' => $_POST['kd_diagnosa'],
            'nmdiagnosaawal' => $data['response']['diagnosa'],
            'kdpolitujuan' => '',
            'nmpolitujuan' => '',
            'klsrawat' =>  $data['response']['klsRawat']['klsRawatHak'],
            'klsnaik' => $data['response']['klsRawat']['klsRawatNaik'] == null ? "" : $data['response']['klsRawat']['klsRawatNaik'],
            'pembiayaan' => $data['response']['klsRawat']['pembiayaan']  == null ? "" : $data['response']['klsRawat']['pembiayaan'],
            'pjnaikkelas' => $data['response']['klsRawat']['penanggungJawab']  == null ? "" : $data['response']['klsRawat']['penanggungJawab'],
            'lakalantas' => '0',
            'user' => $this->core->getUserInfo('username', null, true),
            'nomr' => $this->getRegPeriksaInfo('no_rkm_medis', $_POST['no_rawat']),
            'nama_pasien' => $data['response']['peserta']['nama'],
            'tanggal_lahir' => $data['response']['peserta']['tglLahir'],
            'peserta' => $data['response']['peserta']['jnsPeserta'],
            'jkel' => $data['response']['peserta']['kelamin'],
            'no_kartu' => $data['response']['peserta']['noKartu'],
            'tglpulang' => '0000-00-00 00:00:00',
            'asal_rujukan' => '2. Faskes 2(RS)',
            'eksekutif' => '0. Tidak',
            'cob' => '0. Tidak',
            'notelep' => $no_telp,
            'katarak' => '0. Tidak',
            'tglkkl' => '0000-00-00',
            'keterangankkl' => '-',
            'suplesi' => '0. Tidak',
            'no_sep_suplesi' => '-',
            'kdprop' => '-',
            'nmprop' => '-',
            'kdkab' => '-',
            'nmkab' => '-',
            'kdkec' => '-',
            'nmkec' => '-',
            'noskdp' => $data['response']['noRujukan'],
            'kddpjp' => $this->db('maping_dokter_dpjpvclaim')->where('kd_dokter', $_POST['kd_dokter'])->oneArray()['kd_dokter_bpjs'],
            'nmdpdjp' => $this->db('maping_dokter_dpjpvclaim')->where('kd_dokter', $_POST['kd_dokter'])->oneArray()['nm_dokter_bpjs'],
            'tujuankunjungan' => $data['response']['tujuanKunj']['kode'],
            'flagprosedur' => $data['response']['flagProcedure']['kode'],
            'penunjang' => $data['response']['kdPenunjang']['kode'],
            'asesmenpelayanan' => $data['response']['assestmenPel']['kode'],
            'kddpjplayanan' => $data['response']['dpjp']['kdDPJP'],
            'nmdpjplayanan' => $data['response']['dpjp']['nmDPJP']
          ]);
        }
        print_r($insert);
        if ($insert) {
          $this->db('bpjs_prb')->save(['no_sep' => $data['response']['noSep'], 'prb' => $data_rujukan['response']['rujukan']['peserta']['informasi']['prolanisPRB']]);
          $this->notify('success', 'Simpan sukes');
          // window.history.back();
          redirect(url([ADMIN, 'vedika', 'indexinap']));
        } else {
          $this->notify('failure', 'Simpan gagal');
          redirect(url([ADMIN, 'vedika', 'indexinap']));
        }
    }     
  }

  public function getPDF($id)
  {
    $this->_addHeaderFiles();
    $orthanc = $this->settings->get('orthanc.server');
    $pacs['data'] = $this->core->getRegPeriksaInfo('no_rkm_medis', revertNoRawat($id));
    $pacs['tgl_periksa'] = str_replace('-', '', $this->core->getPeriksaRadiologiInfo('tgl_periksa', revertNoRawat($id)));

      $curl = curl_init();
      curl_setopt ($curl, CURLOPT_URL, $orthanc . '/tools/find');
      curl_setopt ($curl, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt ($curl, CURLOPT_USERPWD, $this->settings->get('orthanc.username').":".$this->settings->get('orthanc.password'));
      curl_setopt ($curl, CURLOPT_TIMEOUT, 30);
      curl_setopt ($curl, CURLOPT_POST, 1);
      curl_setopt ($curl, CURLOPT_POSTFIELDS, '{
          "Level": "Study",
          "Expand": true,
          "Query": {
              "StudyDate": "'.$pacs['tgl_periksa'].'-'.$pacs['tgl_periksa'].'",
              "PatientID": "' . $this->core->getRegPeriksaInfo('no_rkm_medis', revertNoRawat($id)) . '"
          }
      }');
      $resp = curl_exec($curl);
      curl_close($curl);

      $patient = json_decode($resp, TRUE);

      $pacs['Series'] = $patient[0]["Series"][0];   

      if($pacs['Series'] != "") {
        
          $curl = curl_init();
          curl_setopt ($curl, CURLOPT_URL, $orthanc . '/series/' . $pacs['Series']);
          curl_setopt ($curl, CURLOPT_RETURNTRANSFER, 1);
          curl_setopt ($curl, CURLOPT_USERPWD, $this->settings->get('orthanc.username').":".$this->settings->get('orthanc.password'));
          curl_setopt ($curl, CURLOPT_TIMEOUT, 30);
          $resp = curl_exec($curl);
          curl_close($curl);

          $Instances = json_decode($resp, TRUE);
          $pacs['Instances'][] = $Instances;
    }

    $berkas_digital = $this->db('berkas_digital_perawatan')
      ->join('master_berkas_digital', 'master_berkas_digital.kode=berkas_digital_perawatan.kode')
      ->where('berkas_digital_perawatan.no_rawat', $this->revertNorawat($id))
      ->notLike('lokasi_file','%pdf')
      ->asc('master_berkas_digital.nama')
      ->toArray();

    $berkas_digital_pdf = $this->db('berkas_digital_perawatan')
      ->join('master_berkas_digital', 'master_berkas_digital.kode=berkas_digital_perawatan.kode')
      ->where('berkas_digital_perawatan.no_rawat', $this->revertNorawat($id))
      ->where('berkas_digital_perawatan.kode','!=' ,'001')
      ->like('lokasi_file','%pdf')
      ->asc('master_berkas_digital.nama')
      ->toArray();

    $berkas_sep_pdf = $this->db('berkas_digital_perawatan')
      ->join('master_berkas_digital', 'master_berkas_digital.kode=berkas_digital_perawatan.kode')
      ->where('berkas_digital_perawatan.no_rawat', $this->revertNorawat($id))
      ->where('berkas_digital_perawatan.kode','=', '001')
      ->like('lokasi_file','%pdf')
      ->asc('master_berkas_digital.nama')
      ->toArray();

    $no_rawat = $this->revertNorawat($id);

    $check_billing = $this->db()->pdo()->query("SHOW TABLES LIKE 'billing'");
    $check_billing->execute();
    $check_billing = $check_billing->fetch();

    if($check_billing) {
      $query = $this->db()->pdo()->prepare("select no,nm_perawatan,pemisah,if(biaya=0,'',biaya),if(jumlah=0,'',jumlah),if(tambahan=0,'',tambahan),if(totalbiaya=0,'',totalbiaya),totalbiaya from billing where no_rawat='$no_rawat'");
      $query->execute();
      $rows = $query->fetchAll();
      $total = 0;
      foreach ($rows as $key => $value) {
        $total = $total + $value['7'];
      }
      $total = $total;
    } else {
      $rows = [];
      $total = '';
    }

    $this->tpl->set('total', $total);

    $lengkap = $this->db('mlite_vedika')
           ->where('mlite_vedika.no_rawat', $no_rawat)
           ->oneArray();
    $this->tpl->set('lengkap', $lengkap);
    
    $instansi['logo'] = $this->settings->get('settings.logo');
    $instansi['nama_instansi'] = $this->settings->get('settings.nama_instansi');
    $instansi['alamat'] = $this->settings->get('settings.alamat');
    $instansi['kota'] = $this->settings->get('settings.kota');
    $instansi['propinsi'] = $this->settings->get('settings.propinsi');
    $instansi['nomor_telepon'] = $this->settings->get('settings.nomor_telepon');
    $instansi['email'] = $this->settings->get('settings.email');

    $this->tpl->set('billing', $rows);

    /* Menggunakan billing bawaan mLITE */

    if($this->settings->get('vedika.billing') == 'mlite') {
        $settings = $this->settings('settings');
        $this->tpl->set('settings', $this->tpl->noParse_array(htmlspecialchars_array($settings)));

       $reg_periksa = $this->db('reg_periksa')->where('no_rawat', $no_rawat)->oneArray();
       if($reg_periksa['status_lanjut'] == 'Ralan') {
          $result_detail['billing'] = $this->db('mlite_billing')->where('no_rawat', $no_rawat)->like('kd_billing', 'RJ%')->desc('id_billing')->oneArray();
          $result_detail['fullname'] = $this->core->getUserInfo('fullname', $result_detail['billing']['id_user'], true);

          $result_detail['poliklinik'] = $this->db('poliklinik')
            ->join('reg_periksa', 'reg_periksa.kd_poli = poliklinik.kd_poli')
            ->where('reg_periksa.no_rawat', $no_rawat)
            ->oneArray();

          $poliklinik = $this->db('poliklinik')
            ->join('reg_periksa', 'reg_periksa.kd_poli=poliklinik.kd_poli')
            ->where('no_rawat', $no_rawat)
            ->oneArray();
          if($poliklinik['stts_daftar'] == 'Lama') {
            $poliklinik['registrasi'] = $poliklinik['registrasilama'];
          }


          $result_detail['rawat_jl_dr'] = $this->db('rawat_jl_dr')
            ->select('jns_perawatan.nm_perawatan')
            ->select(['biaya_rawat' => 'rawat_jl_dr.biaya_rawat'])
            ->select(['jml' => 'COUNT(rawat_jl_dr.kd_jenis_prw)'])
            ->select(['total_biaya_rawat_dr' => 'SUM(rawat_jl_dr.biaya_rawat)'])
            ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw = rawat_jl_dr.kd_jenis_prw')
            ->where('rawat_jl_dr.no_rawat', $no_rawat)
            ->group('jns_perawatan.nm_perawatan')
            ->toArray();

          $total_rawat_jl_dr = 0;
          foreach ($result_detail['rawat_jl_dr'] as $row) {
            $total_rawat_jl_dr += $row['total_biaya_rawat_dr'];
          }

          $result_detail['rawat_jl_pr'] = $this->db('rawat_jl_pr')
            ->select('jns_perawatan.nm_perawatan')
            ->select(['biaya_rawat' => 'rawat_jl_pr.biaya_rawat'])
            ->select(['jml' => 'COUNT(rawat_jl_pr.kd_jenis_prw)'])
            ->select(['total_biaya_rawat_pr' => 'SUM(rawat_jl_pr.biaya_rawat)'])
            ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw = rawat_jl_pr.kd_jenis_prw')
            ->where('rawat_jl_pr.no_rawat', $no_rawat)
            ->group('jns_perawatan.nm_perawatan')
            ->toArray();

          $total_rawat_jl_pr = 0;
          foreach ($result_detail['rawat_jl_pr'] as $row) {
            $total_rawat_jl_pr += $row['total_biaya_rawat_pr'];
          }

          $result_detail['rawat_jl_drpr'] = $this->db('rawat_jl_drpr')
            ->select('jns_perawatan.nm_perawatan')
            ->select(['biaya_rawat' => 'rawat_jl_drpr.biaya_rawat'])
            ->select(['jml' => 'COUNT(rawat_jl_drpr.kd_jenis_prw)'])
            ->select(['total_biaya_rawat_drpr' => 'SUM(rawat_jl_drpr.biaya_rawat)'])
            ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw = rawat_jl_drpr.kd_jenis_prw')
            ->where('rawat_jl_drpr.no_rawat', $no_rawat)
            ->group('jns_perawatan.nm_perawatan')
            ->toArray();

          $total_rawat_jl_drpr = 0;
          foreach ($result_detail['rawat_jl_drpr'] as $row) {
            $total_rawat_jl_drpr += $row['total_biaya_rawat_drpr'];
          }

          $result_detail['detail_pemberian_obat'] = $this->db('detail_pemberian_obat')
            ->join('databarang', 'databarang.kode_brng=detail_pemberian_obat.kode_brng')
            ->where('no_rawat', $no_rawat)
            ->where('detail_pemberian_obat.status', 'Ralan')
            ->toArray();

          $total_detail_pemberian_obat = 0;
          foreach ($result_detail['detail_pemberian_obat'] as $row) {
            $total_detail_pemberian_obat += $row['total'];
          }

          $result_detail['periksa_lab'] = $this->db('periksa_lab')
            ->select('jns_perawatan_lab.nm_perawatan') 
            ->select('periksa_lab.biaya')  
            ->select('periksa_lab.kd_jenis_prw')          
            ->join('jns_perawatan_lab', 'jns_perawatan_lab.kd_jenis_prw=periksa_lab.kd_jenis_prw')
            ->where('periksa_lab.no_rawat', $no_rawat)
            ->where('periksa_lab.status', 'Ralan')
            ->where('periksa_lab.biaya', '!=','0')
            ->toArray();

          $result_detail['detail_periksa_lab'] = $this->db('detail_periksa_lab')
            ->select('jns_perawatan_lab.nm_perawatan') 
            ->select(['biaya' => 'SUM(detail_periksa_lab.bagian_dokter)'])
            ->select('detail_periksa_lab.kd_jenis_prw') 
            ->join('jns_perawatan_lab', 'jns_perawatan_lab.kd_jenis_prw=detail_periksa_lab.kd_jenis_prw')
            ->where('detail_periksa_lab.no_rawat', $no_rawat)
            ->where('detail_periksa_lab.bagian_dokter', '!=','0')
            ->group('detail_periksa_lab.kd_jenis_prw')
            ->toArray();

          $total_periksa_lab = 0;
          foreach (array_merge($result_detail['periksa_lab'], $result_detail['detail_periksa_lab']) as $row) {
            $total_periksa_lab += $row['biaya'];
          }

          $result_detail['periksa_radiologi'] = $this->db('periksa_radiologi')
            ->join('jns_perawatan_radiologi', 'jns_perawatan_radiologi.kd_jenis_prw=periksa_radiologi.kd_jenis_prw')
            ->where('no_rawat', $no_rawat)
            // ->where('periksa_radiologi.status', 'Ralan')
            ->toArray();

          $total_periksa_radiologi = 0;
          foreach ($result_detail['periksa_radiologi'] as $row) {
            $total_periksa_radiologi += $row['biaya'];
          }

          $jumlah_total_operasi = 0;
          $operasis = $this->db('operasi')->join('paket_operasi', 'paket_operasi.kode_paket=operasi.kode_paket')->where('no_rawat', $no_rawat)->where('operasi.status', 'Ralan')->toArray();
          $result_detail['operasi'] = [];
          foreach ($operasis as $operasi) {
            $operasi['jumlah'] = $operasi['biayaoperator1']+$operasi['biayaoperator2']+$operasi['biayaoperator3']+$operasi['biayaasisten_operator1']+$operasi['biayaasisten_operator2']+$operasi['biayadokter_anak']+$operasi['biayaperawaat_resusitas']+$operasi['biayadokter_anestesi']+$operasi['biayaasisten_anestesi']+$operasi['biayabidan']+$operasi['biayaperawat_luar']+$operasi['sarpras'];
            $jumlah_total_operasi += $operasi['jumlah'];
            $result_detail['operasi'][] = $operasi;
          }
          $jumlah_total_obat_operasi = 0;
          $obat_operasis = $this->db('beri_obat_operasi')->join('obatbhp_ok', 'obatbhp_ok.kd_obat=beri_obat_operasi.kd_obat')->where('no_rawat', $no_rawat)->toArray();
          $result_detail['obat_operasi'] = [];
          foreach ($obat_operasis as $obat_operasi) {
            $obat_operasi['harga'] = $obat_operasi['hargasatuan'] * $obat_operasi['jumlah'];
            $jumlah_total_obat_operasi += $obat_operasi['harga'];
            $result_detail['obat_operasi'][] = $obat_operasi;
          }

       } else {

         $result_detail['billing'] = $this->db('mlite_billing')->where('no_rawat', $no_rawat)->like('kd_billing', 'RI%')->desc('id_billing')->oneArray();
         $result_detail['fullname'] = $this->core->getUserInfo('fullname', $result_detail['billing']['id_user'], true);

         $result_detail['kamar_inap'] = $this->db('kamar_inap')
           ->join('reg_periksa', 'reg_periksa.no_rawat = kamar_inap.no_rawat')
           ->where('reg_periksa.no_rawat', $no_rawat)
           ->oneArray();

         // $result_detail['ranap'] = $this->db('kamar_inap')
         // ->where('no_rawat', revertNoRawat($no_rawat))
         // ->limit(1)->desc('tgl_keluar')
         // ->toArray();
         $result_detail['rawat_jl_dr'] = $this->db('rawat_jl_dr')
            ->select('jns_perawatan.nm_perawatan')
            ->select(['biaya_rawat' => 'rawat_jl_dr.biaya_rawat'])
            ->select(['jml' => 'COUNT(rawat_jl_dr.kd_jenis_prw)'])
            ->select(['total_biaya_rawat_dr' => 'SUM(rawat_jl_dr.biaya_rawat)'])
            ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw = rawat_jl_dr.kd_jenis_prw')
            ->where('rawat_jl_dr.no_rawat', $no_rawat)
            ->group('jns_perawatan.nm_perawatan')
            ->toArray();

          $total_rawat_jl_dr = 0;
          foreach ($result_detail['rawat_jl_dr'] as $row) {
            $total_rawat_jl_dr += $row['total_biaya_rawat_dr'];
          }

          $result_detail['rawat_jl_pr'] = $this->db('rawat_jl_pr')
            ->select('jns_perawatan.nm_perawatan')
            ->select(['biaya_rawat' => 'rawat_jl_pr.biaya_rawat'])
            ->select(['jml' => 'COUNT(rawat_jl_pr.kd_jenis_prw)'])
            ->select(['total_biaya_rawat_pr' => 'SUM(rawat_jl_pr.biaya_rawat)'])
            ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw = rawat_jl_pr.kd_jenis_prw')
            ->where('rawat_jl_pr.no_rawat', $no_rawat)
            ->group('jns_perawatan.nm_perawatan')
            ->toArray();

          $total_rawat_jl_pr = 0;
          foreach ($result_detail['rawat_jl_pr'] as $row) {
            $total_rawat_jl_pr += $row['total_biaya_rawat_pr'];
          }

          $result_detail['rawat_jl_drpr'] = $this->db('rawat_jl_drpr')
            ->select('jns_perawatan.nm_perawatan')
            ->select(['biaya_rawat' => 'rawat_jl_drpr.biaya_rawat'])
            ->select(['jml' => 'COUNT(rawat_jl_drpr.kd_jenis_prw)'])
            ->select(['total_biaya_rawat_drpr' => 'SUM(rawat_jl_drpr.biaya_rawat)'])
            ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw = rawat_jl_drpr.kd_jenis_prw')
            ->where('rawat_jl_drpr.no_rawat', $no_rawat)
            ->group('jns_perawatan.nm_perawatan')
            ->toArray();

          $total_rawat_jl_drpr = 0;
          foreach ($result_detail['rawat_jl_drpr'] as $row) {
            $total_rawat_jl_drpr += $row['total_biaya_rawat_drpr'];
          }

          $ranap = $this->db('kamar_inap')
            ->join('reg_periksa', 'reg_periksa.no_rawat=kamar_inap.no_rawat')
            ->join('poliklinik','poliklinik.kd_poli=reg_periksa.kd_poli')
            ->where('reg_periksa.no_rawat', $no_rawat)
            // ->where('kamar_inap.stts_pulang', '!=','Pindah Kamar')
            ->oneArray();

           $result_detail['biaya_ranap'] = $this->db('kamar_inap')
             ->where('kamar_inap.no_rawat', $no_rawat)
             ->desc('tgl_keluar')
            //  ->limit('1')
             ->toArray();
 
             $total_biaya_kamarinap = 0;
            foreach ($result_detail['biaya_ranap'] as $row) {
             $total_biaya_kamarinap += $row['ttl_biaya'];
            }
         $result_detail['rawat_inap_dr'] = $this->db('rawat_inap_dr')
           ->select('jns_perawatan_inap.nm_perawatan')
           ->select(['biaya_rawat' => 'rawat_inap_dr.biaya_rawat'])
           ->select(['jml' => 'COUNT(rawat_inap_dr.kd_jenis_prw)'])
           ->select(['total_biaya_rawat_dr' => 'SUM(rawat_inap_dr.biaya_rawat)'])
           ->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw = rawat_inap_dr.kd_jenis_prw')
           ->where('rawat_inap_dr.no_rawat', $no_rawat)
           ->group('jns_perawatan_inap.nm_perawatan')
           ->toArray();

           $total_rawat_inap_dr = 0;
          foreach ($result_detail['rawat_inap_dr'] as $row) {
            $total_rawat_inap_dr += $row['total_biaya_rawat_dr'];
          }

         $result_detail['rawat_inap_pr'] = $this->db('rawat_inap_pr')
           ->select('jns_perawatan_inap.nm_perawatan')
           ->select(['biaya_rawat' => 'rawat_inap_pr.biaya_rawat'])
           ->select(['jml' => 'COUNT(rawat_inap_pr.kd_jenis_prw)'])
           ->select(['total_biaya_rawat_pr' => 'SUM(rawat_inap_pr.biaya_rawat)'])
           ->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw = rawat_inap_pr.kd_jenis_prw')
           ->where('rawat_inap_pr.no_rawat', $no_rawat)
           ->group('jns_perawatan_inap.nm_perawatan')
           ->toArray();

           $total_rawat_inap_pr = 0;
          foreach ($result_detail['rawat_inap_pr'] as $row) {
            $total_rawat_inap_pr += $row['total_biaya_rawat_pr'];
          }

         $result_detail['rawat_inap_drpr'] = $this->db('rawat_inap_drpr')
           ->select('jns_perawatan_inap.nm_perawatan')
           ->select(['biaya_rawat' => 'rawat_inap_drpr.biaya_rawat'])
           ->select(['jml' => 'COUNT(rawat_inap_drpr.kd_jenis_prw)'])
           ->select(['total_biaya_rawat_drpr' => 'SUM(rawat_inap_drpr.biaya_rawat)'])
           ->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw = rawat_inap_drpr.kd_jenis_prw')
           ->where('rawat_inap_drpr.no_rawat', $no_rawat)
           ->group('jns_perawatan_inap.nm_perawatan')
           ->toArray();

          $total_rawat_inap_drpr = 0;
          foreach ($result_detail['rawat_inap_drpr'] as $row) {
            $total_rawat_inap_drpr += $row['total_biaya_rawat_drpr'];
          }

         $result_detail['detail_pemberian_obat_ranap'] = $this->db('detail_pemberian_obat')
           ->join('databarang', 'databarang.kode_brng=detail_pemberian_obat.kode_brng')
           ->where('no_rawat', $no_rawat)
           // ->where('detail_pemberian_obat.status', 'Ranap')
           ->toArray();

          $total_detail_pemberian_obat_ranap = 0;
          foreach ($result_detail['detail_pemberian_obat_ranap'] as $row) {
            $total_detail_pemberian_obat_ranap += $row['total'];
          }

         $result_detail['periksa_lab_ranap'] = $this->db('periksa_lab')
            ->select('jns_perawatan_lab.nm_perawatan') 
            ->select('periksa_lab.biaya')  
            ->select('periksa_lab.kd_jenis_prw')          
            ->join('jns_perawatan_lab', 'jns_perawatan_lab.kd_jenis_prw=periksa_lab.kd_jenis_prw')
            ->where('periksa_lab.no_rawat', $no_rawat)
            // ->where('periksa_lab.status', 'Ranap')
            ->where('periksa_lab.biaya', '!=','0')
            ->toArray();

          $result_detail['detail_periksa_lab_ranap'] = $this->db('detail_periksa_lab')
            ->select('jns_perawatan_lab.nm_perawatan') 
            ->select(['biaya' => 'SUM(detail_periksa_lab.bagian_dokter)'])
            ->select('detail_periksa_lab.kd_jenis_prw') 
            ->join('jns_perawatan_lab', 'jns_perawatan_lab.kd_jenis_prw=detail_periksa_lab.kd_jenis_prw')
            ->where('detail_periksa_lab.no_rawat', $no_rawat)
            ->where('detail_periksa_lab.bagian_dokter', '!=','0')
            ->group('detail_periksa_lab.kd_jenis_prw')
            ->toArray();

          $total_periksa_lab_ranap = 0;
          foreach (array_merge($result_detail['periksa_lab_ranap'], $result_detail['detail_periksa_lab_ranap']) as $row) {
            $total_periksa_lab_ranap += $row['biaya'];
          }

         $result_detail['periksa_radiologi_ranap'] = $this->db('periksa_radiologi')
           ->join('jns_perawatan_radiologi', 'jns_perawatan_radiologi.kd_jenis_prw=periksa_radiologi.kd_jenis_prw')
           ->where('no_rawat', $no_rawat)
           // ->where('periksa_radiologi.status', 'Ranap')
           ->toArray();

          $total_periksa_radiologi_ranap = 0;
          foreach ($result_detail['periksa_radiologi_ranap'] as $row) {
            $total_periksa_radiologi_ranap += $row['biaya'];
          }
    
         $result_detail['tambahan_biaya'] = $this->db('tambahan_biaya')
           //->where('status', 'ranap')
           ->where('no_rawat', $no_rawat)
           ->toArray();

         $jumlah_total_operasi = 0;
         $operasis = $this->db('operasi')
         ->join('paket_operasi', 'paket_operasi.kode_paket=operasi.kode_paket')
         ->where('no_rawat', $no_rawat)
         // ->where('operasi.status', 'Ranap')
         ->toArray();
         $result_detail['operasi'] = [];
         foreach ($operasis as $operasi) {
           $operasi['jumlah'] = $operasi['biayaoperator1']+$operasi['biayaoperator2']+$operasi['biayaoperator3']+$operasi['biayaasisten_operator1']+$operasi['biayaasisten_operator2']+$operasi['biayadokter_anak']+$operasi['biayaperawaat_resusitas']+$operasi['biayadokter_anestesi']+$operasi['biayaasisten_anestesi']+$operasi['biayabidan']+$operasi['biayaperawat_luar']+$operasi['sarpras'];
           $jumlah_total_operasi += $operasi['jumlah'];
           $result_detail['operasi'][] = $operasi;
         }
         $jumlah_total_obat_operasi = 0;
         $obat_operasis = $this->db('beri_obat_operasi')->join('obatbhp_ok', 'obatbhp_ok.kd_obat=beri_obat_operasi.kd_obat')->where('no_rawat', $no_rawat)->toArray();
         $result_detail['obat_operasi'] = [];
         foreach ($obat_operasis as $obat_operasi) {
           $obat_operasi['harga'] = $obat_operasi['hargasatuan'] * $obat_operasi['jumlah'];
           $jumlah_total_obat_operasi += $obat_operasi['harga'];
           $result_detail['obat_operasi'][] = $obat_operasi;
         }

       }

       $this->tpl->set('billing', $result_detail);

    }

    /* End menggunakan billing bawaan mlITE */

    $this->tpl->set('instansi', $instansi);

    $print_sep = array();
    if (!empty($this->_getSEPInfo('no_sep', $no_rawat))) {
      $print_sep['bridging_sep'] = $this->db('bridging_sep')->where('no_sep', $this->_getSEPInfo('no_sep', $no_rawat))->oneArray();
      $print_sep['bpjs_prb'] = $this->db('bpjs_prb')->where('no_sep', $this->_getSEPInfo('no_sep', $no_rawat))->oneArray();
      $batas_rujukan = $this->db('bridging_sep')->select('DATE_ADD(tglrujukan , INTERVAL 85 DAY) AS batas_rujukan')->where('no_sep', $this->_getSEPInfo('no_sep', $no_rawat))->oneArray();
      $print_sep['batas_rujukan'] = $batas_rujukan['batas_rujukan'];
      switch ($print_sep['bridging_sep']['klsnaik']) {
        case '2':
          $print_sep['kelas_naik'] = 'Kelas VIP';
          break;
        case '3':
          $print_sep['kelas_naik'] = 'Kelas 1';
          break;
        case '4':
          $print_sep['kelas_naik'] = 'Kelas 2';
          break;

        default:
          $print_sep['kelas_naik'] = "";
          break;
      }
    }
    $print_sep['nama_instansi'] = $this->settings->get('settings.nama_instansi');
    $print_sep['logoURL'] = url(MODULES . '/vclaim/img/bpjslogo.png');
    $this->tpl->set('print_sep', $print_sep);

    $permintaan_ranap = $this->db('permintaan_ranap')
    ->where('no_rawat', $this->revertNorawat($id))
    ->join('dokter', 'dokter.kd_dokter=permintaan_ranap.kd_dpjp')
    ->oneArray();
    $this->tpl->set('permintaan_ranap', $permintaan_ranap);

    $rujukan_ranap = $this->db('rujuk')
    ->where('no_rawat', $this->revertNorawat($id))
    ->join('dokter', 'dokter.kd_dokter=rujuk.kd_dokter')
    ->oneArray();
    $this->tpl->set('rujukan_ranap', $rujukan_ranap);

    $cek_spri = $this->db('bridging_surat_pri_bpjs')
    ->where('no_rawat', $this->revertNorawat($id))
    ->oneArray();
    $this->tpl->set('cek_spri', $cek_spri);

    $print_spri = array();
    if (!empty($this->_getSPRIInfo('no_surat', $no_rawat))) {
      $print_spri['bridging_surat_pri_bpjs'] = $this->db('bridging_surat_pri_bpjs')->where('no_surat', $this->_getSPRIInfo('no_surat', $no_rawat))->oneArray();
    }
    $print_spri['nama_instansi'] = $this->settings->get('settings.nama_instansi');
    $print_spri['logoURL'] = url(MODULES . '/vclaim/img/bpjslogo.png');
    $this->tpl->set('print_spri', $print_spri);

    $resume_pasien = $this->db('resume_pasien_ranap')
      ->join('dokter', 'dokter.kd_dokter = resume_pasien_ranap.kd_dokter')
      ->where('no_rawat', $this->revertNorawat($id))
      ->oneArray();
      
    if(!$this
    ->db('resume_pasien_ranap')
    ->where('no_rawat', $this->revertNorawat($id))
    ->oneArray()) {
      $resume_pasien = $this->db('resume_pasien')
        ->join('dokter', 'dokter.kd_dokter = resume_pasien.kd_dokter')
        ->where('no_rawat', $this->revertNorawat($id))
        ->oneArray();
    }
    $this->tpl->set('resume_pasien', $resume_pasien);

    $asesmen_medis_igd = $this->db('asesmen_medis_igd')
      ->join('dokter', 'dokter.kd_dokter = asesmen_medis_igd.kd_dokter')
      ->where('no_rawat', $this->revertNorawat($id))
      ->oneArray();

    $this->tpl->set('asesmen_medis_igd', $asesmen_medis_igd);

    $triase_igd = $this->db('data_triase_igd')
      ->join('master_triase_macam_kasus', 'master_triase_macam_kasus.kode_kasus = data_triase_igd.kode_kasus')
      ->where('no_rawat', $this->revertNorawat($id))
      ->oneArray();

    $this->tpl->set('triase_igd', $triase_igd);

    $triaseprimer = $this->db('data_triase_igdprimer')
      ->where('no_rawat', $this->revertNorawat($id))
      ->oneArray();

    $this->tpl->set('triaseprimer', $triaseprimer);
  
    $triasesekunder = $this->db('data_triase_igdsekunder')
      ->where('no_rawat', $this->revertNorawat($id))
      ->oneArray();

    $this->tpl->set('triasesekunder', $triasesekunder);

    $skala1 = $this->db('data_triase_igddetail_skala1')
    ->where('no_rawat', $this->revertNorawat($id))
    ->oneArray();

    $this->tpl->set('skala1', $skala1);

    $skala2 = $this->db('data_triase_igddetail_skala2')
    ->where('no_rawat', $this->revertNorawat($id))
    ->oneArray();

    $this->tpl->set('skala2', $skala2);

    $skala3 = $this->db('data_triase_igddetail_skala3')
    ->where('no_rawat', $this->revertNorawat($id))
    ->oneArray();

    $this->tpl->set('skala3', $skala3);

    $skala4 = $this->db('data_triase_igddetail_skala4')
    ->where('no_rawat', $this->revertNorawat($id))
    ->oneArray();

    $this->tpl->set('skala4', $skala4);

    $skala5 = $this->db('data_triase_igddetail_skala5')
    ->where('no_rawat', $this->revertNorawat($id))
    ->oneArray();

    $this->tpl->set('skala5', $skala5);   

    $pasien = $this->db('pasien')
      ->join('kecamatan', 'kecamatan.kd_kec = pasien.kd_kec')
      ->join('kabupaten', 'kabupaten.kd_kab = pasien.kd_kab')
      ->where('no_rkm_medis', $this->getRegPeriksaInfo('no_rkm_medis', $this->revertNorawat($id)))
      ->oneArray();
    $reg_periksa = $this->db('reg_periksa')
      ->join('dokter', 'dokter.kd_dokter = reg_periksa.kd_dokter')
      ->join('poliklinik', 'poliklinik.kd_poli = reg_periksa.kd_poli')
      ->join('penjab', 'penjab.kd_pj = reg_periksa.kd_pj')
      ->where('stts', '<>', 'Batal')
      ->where('no_rawat', $this->revertNorawat($id))
      ->oneArray();
    $rows_dpjp_ranap = $this->db('dpjp_ranap')
      ->join('dokter', 'dokter.kd_dokter = dpjp_ranap.kd_dokter')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();
    $dpjp_i = 1;
    $dpjp_ranap = [];
    foreach ($rows_dpjp_ranap as $row) {
      $row['nomor'] = $dpjp_i++;
      $dpjp_ranap[] = $row;
    }
    /*
    $rujukan_internal = $this->db('rujukan_internal_poli')
      ->join('poliklinik', 'poliklinik.kd_poli = rujukan_internal_poli.kd_poli')
      ->join('dokter', 'dokter.kd_dokter = rujukan_internal_poli.kd_dokter')
      ->where('no_rawat', $this->revertNorawat($id))
      ->oneArray();
    */
    $diagnosa_pasien = $this->db('diagnosa_pasien')
      ->join('penyakit', 'penyakit.kd_penyakit = diagnosa_pasien.kd_penyakit')
      ->where('no_rawat', $this->revertNorawat($id))
      ->where('diagnosa_pasien.status', 'Ralan')
      ->asc('prioritas')
      ->toArray();
    if($reg_periksa['status_lanjut'] == 'Ranap'){
      $diagnosa_pasien = $this->db('diagnosa_pasien')
        ->join('penyakit', 'penyakit.kd_penyakit = diagnosa_pasien.kd_penyakit')
        ->where('no_rawat', $this->revertNorawat($id))
        ->where('diagnosa_pasien.status', 'Ranap')
        ->asc('prioritas')
        ->toArray();
    }

    $prosedur_pasien = $this->db('prosedur_pasien')
      ->join('icd9', 'icd9.kode = prosedur_pasien.kode')
      ->where('no_rawat', $this->revertNorawat($id))
      ->where('status', 'Ralan')
      ->asc('prioritas')
      ->toArray();
      if($reg_periksa['status_lanjut'] == 'Ranap'){
    $prosedur_pasien = $this->db('prosedur_pasien')
      ->join('icd9', 'icd9.kode = prosedur_pasien.kode')
      ->where('no_rawat', $this->revertNorawat($id))
      ->where('status', 'Ranap')
      ->asc('prioritas')
      ->toArray();
      }

    $pemeriksaan_ralan = $this->db('pemeriksaan_ralan')
      ->where('no_rawat', $this->revertNorawat($id))
      ->asc('tgl_perawatan')
      ->asc('jam_rawat')
      ->toArray();
    $pemeriksaan_rehab = $this->db('pemeriksaan_ralan_rehab')
      ->where('no_rawat', $this->revertNorawat($id))
      ->join('pegawai', 'pemeriksaan_ralan_rehab.nik=pegawai.nik')
      ->asc('tgl_perawatan')
      ->asc('jam_rawat')
      ->oneArray();
    $frekuensi_kunjungan = $this->db('kunjungan_fisio_rehab')
      ->where('no_rawat', $this->revertNorawat($id))
      ->oneArray();
    $uji_fungsi_kfr = $this->db('uji_fungsi_kfr')
      ->where('no_rawat', $this->revertNorawat($id))
      ->asc('tanggal')
      ->oneArray();    
    $pre_uji_fungsi_kfr = $this->db('uji_fungsi_kfr')
      ->select('uji_fungsi_kfr.*')
      ->join('reg_periksa', 'uji_fungsi_kfr.no_rawat=reg_periksa.no_rawat')
      ->where('no_rkm_medis', $this->getRegPeriksaInfo('no_rkm_medis', $this->revertNorawat($id)))
      ->desc('tanggal')
      ->limit('1')
      ->oneArray();
    $pemeriksaan_ranap = $this->db('pemeriksaan_ranap')
      ->where('no_rawat', $this->revertNorawat($id))
      ->asc('tgl_perawatan')
      ->asc('jam_rawat')
      ->toArray();
    
    foreach ($pemeriksaan_ranap as &$pr) {
      if (!isset($pr['pemeriksaan']) || $pr['pemeriksaan'] === '') continue;
    
      $s = $pr['pemeriksaan'];
    
      // NBSP (dua kemungkinan)
      $s = str_replace(["\xC2\xA0", "\xA0"], ' ', $s);
    
      // rapikan newline
      $s = str_replace(["\r\n", "\r"], "\n", $s);
    
      // buang control char aneh (kecuali tab/newline)
      $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $s);
    
      // paksa jadi UTF-8 valid
      if (function_exists('mb_convert_encoding')) {
        $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
      }
    
      // opsional: kalau memang ada char � tersimpan, ganti jadi spasi
      $s = str_replace("�", " ", $s);
    
      $pr['pemeriksaan'] = trim($s);
    }
    unset($pr);

    $resume_ranap = $this->db('resume_pasien_ranap')
      ->join('dokter', 'resume_pasien_ranap.kd_dokter=dokter.kd_dokter')
      ->where('no_rawat', $this->revertNorawat($id))
      ->oneArray();
    $rawat_jl_dr = $this->db('rawat_jl_dr')
      ->join('jns_perawatan', 'rawat_jl_dr.kd_jenis_prw=jns_perawatan.kd_jenis_prw')
      ->join('dokter', 'rawat_jl_dr.kd_dokter=dokter.kd_dokter')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();
    $rawat_jl_pr = $this->db('rawat_jl_pr')
      ->join('jns_perawatan', 'rawat_jl_pr.kd_jenis_prw=jns_perawatan.kd_jenis_prw')
      ->join('petugas', 'rawat_jl_pr.nip=petugas.nip')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();
    $rawat_jl_drpr = $this->db('rawat_jl_drpr')
      ->join('jns_perawatan', 'rawat_jl_drpr.kd_jenis_prw=jns_perawatan.kd_jenis_prw')
      ->join('dokter', 'rawat_jl_drpr.kd_dokter=dokter.kd_dokter')
      ->join('petugas', 'rawat_jl_drpr.nip=petugas.nip')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();
    $rawat_inap_dr = $this->db('rawat_inap_dr')
      ->join('jns_perawatan_inap', 'rawat_inap_dr.kd_jenis_prw=jns_perawatan_inap.kd_jenis_prw')
      ->join('dokter', 'rawat_inap_dr.kd_dokter=dokter.kd_dokter')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();
    $rawat_inap_pr = $this->db('rawat_inap_pr')
      ->join('jns_perawatan_inap', 'rawat_inap_pr.kd_jenis_prw=jns_perawatan_inap.kd_jenis_prw')
      ->join('petugas', 'rawat_inap_pr.nip=petugas.nip')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();
    $rawat_inap_drpr = $this->db('rawat_inap_drpr')
      ->join('jns_perawatan_inap', 'rawat_inap_drpr.kd_jenis_prw=jns_perawatan_inap.kd_jenis_prw')
      ->join('dokter', 'rawat_inap_drpr.kd_dokter=dokter.kd_dokter')
      ->join('petugas', 'rawat_inap_drpr.nip=petugas.nip')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();

    $kamar_inap = $this->db('kamar_inap')
      ->join('kamar', 'kamar_inap.kd_kamar=kamar.kd_kamar')
      ->join('bangsal', 'kamar.kd_bangsal=bangsal.kd_bangsal')
      ->where('no_rawat', $this->revertNorawat($id))
      ->asc('tgl_keluar')
    //   ->limit('1')
      ->toArray();

    $lama_inap = $this->db('kamar_inap')
      ->select(['lama' => 'SUM(kamar_inap.lama)'])
      ->where('no_rawat', $this->revertNorawat($id))
      ->desc('lama')
      ->limit('1')
      ->oneArray();

    $operasi = $this->db('operasi')
      ->join('paket_operasi', 'operasi.kode_paket=paket_operasi.kode_paket')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();
    $rujuk_igd = $this->db('rujuk_igd')
      ->join('dokter', 'dokter.kd_dokter=rujuk_igd.kd_dokter')
      ->where('no_rawat', $this->revertNorawat($id))
      ->oneArray(); 
    $rujuk_ralan = $this->db('rujuk')
      ->select('rujuk.*')
      ->select('a.nm_dokter')
      ->join('dokter a', 'a.kd_dokter=rujuk.kd_dokter')
      ->where('no_rawat', $this->revertNorawat($id))
      ->oneArray(); 
    $rujuk_ranap = $this->db('rujuk_rawat_inap')
      ->select('rujuk_rawat_inap.*')
      ->select('a.nm_dokter')
      ->join('dokter a', 'a.kd_dokter=rujuk_rawat_inap.kd_dokter')
      ->where('no_rawat', $this->revertNorawat($id))
      ->oneArray();   
    $tindakan_radiologi = $this->db('periksa_radiologi')
      ->join('jns_perawatan_radiologi', 'periksa_radiologi.kd_jenis_prw=jns_perawatan_radiologi.kd_jenis_prw')
      ->join('dokter', 'periksa_radiologi.kd_dokter=dokter.kd_dokter')
      ->join('petugas', 'periksa_radiologi.nip=petugas.nip')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();
    $hasil_radiologi = $this->db('hasil_radiologi')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();
    $pemeriksaan_laboratorium = [];
    $rows_pemeriksaan_laboratorium = $this->db('periksa_lab')
      ->join('jns_perawatan_lab', 'jns_perawatan_lab.kd_jenis_prw=periksa_lab.kd_jenis_prw')
      ->where('no_rawat', $this->revertNorawat($id))
      ->asc('tgl_periksa')
      ->toArray();
    
    foreach ($rows_pemeriksaan_laboratorium as $value) {
    
      $value['detail_periksa_lab'] = $this->db('detail_periksa_lab')
        ->join('template_laboratorium', 'template_laboratorium.id_template=detail_periksa_lab.id_template')
        ->where('detail_periksa_lab.no_rawat', $value['no_rawat'])
        ->where('detail_periksa_lab.kd_jenis_prw', $value['kd_jenis_prw'])
        ->where('detail_periksa_lab.tgl_periksa', $value['tgl_periksa'])
        ->where('detail_periksa_lab.jam', $value['jam'])
        ->toArray();
    
      // ✅ FIX: normalisasi satuan (µL dll) + bersihin karakter aneh
      foreach ($value['detail_periksa_lab'] as &$d) {
        if (!empty($d['satuan'])) {
          $s = $d['satuan'];
    
          // NBSP (dua kemungkinan)
          $s = str_replace(["\xC2\xA0", "\xA0"], ' ', $s);
    
          // perbaiki µL yang rusak (�L) dan variasinya
          $s = str_replace(
            ['�L', '/�L', 'uL', 'u/L', 'µL'], // variasi umum
            ['µL', '/µL', 'µL', 'µ/L', 'µL'],
            $s
          );
    
          // buang control chars (kecuali newline/tab kalau ada)
          $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $s);
    
          // pastikan UTF-8 valid
          if (function_exists('mb_convert_encoding')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
          }
    
          $d['satuan'] = trim($s);
        }
    
        // opsional: kalau nilai juga suka ada karakter aneh
        if (!empty($d['nilai'])) {
          $n = $d['nilai'];
          $n = str_replace(["\xC2\xA0", "\xA0"], ' ', $n);
          $n = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $n);
          if (function_exists('mb_convert_encoding')) {
            $n = mb_convert_encoding($n, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
          }
          $d['nilai'] = trim($n);
        }
      }
      unset($d);
    
      $pemeriksaan_laboratorium[] = $value;
    }

    $pemberian_obat = $this->db('detail_pemberian_obat')
      ->join('databarang', 'detail_pemberian_obat.kode_brng=databarang.kode_brng')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();
    $obat_operasi = $this->db('beri_obat_operasi')
      ->join('obatbhp_ok', 'beri_obat_operasi.kd_obat=obatbhp_ok.kd_obat')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();
    $resep_pulang = $this->db('resep_pulang')
      ->join('databarang', 'resep_pulang.kode_brng=databarang.kode_brng')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();
    $laporan_operasi = $this->db('laporan_operasi')
      ->select('laporan_operasi.*')
      ->select('operasi.*')
      ->select('a.nm_dokter')
      ->select(['operator1' => 'a.nm_dokter'])
      ->select(['dokter_anak' => 'b.nm_dokter'])
      ->select(['dokter_anestesi' => 'c.nm_dokter'])
      ->select(['dokter_umum' => 'd.nm_dokter'])
      ->join('operasi', 'operasi.no_rawat=laporan_operasi.no_rawat')
      ->join('dokter a', 'a.kd_dokter=operasi.operator1')
      ->join('dokter b', 'b.kd_dokter=operasi.dokter_anak')
      ->join('dokter c', 'c.kd_dokter=operasi.dokter_anestesi')
      ->join('dokter d', 'd.kd_dokter=operasi.dokter_umum')
      ->where('laporan_operasi.no_rawat', $this->revertNorawat($id))
      ->group('laporan_operasi.no_rawat')
      ->oneArray();
      
    $laporan_operasi_ralan = $this->db('laporan_bedah')
      ->select('laporan_bedah.*')
      ->select('a.nm_dokter')
      ->select(['operator' => 'a.nm_dokter'])
      ->join('dokter a', 'a.kd_dokter=laporan_bedah.operator')
      ->where('laporan_bedah.no_rawat', $this->revertNorawat($id))
      ->group('laporan_bedah.no_rawat')
      ->oneArray();

    $this->tpl->set('total_biaya', 
    $total_rawat_jl_dr
    +$total_rawat_jl_pr
    +$total_rawat_jl_drpr
    +$total_detail_pemberian_obat
    +$total_periksa_lab
    +$total_periksa_radiologi
    +$jumlah_total_operasi
    +$jumlah_total_obat_operasi
    +$poliklinik['registrasi']);
    $this->tpl->set('total_biaya_ranap', 
    $total_biaya_kamarinap
    +$total_rawat_jl_dr
    +$total_rawat_jl_pr
    +$total_rawat_jl_drpr
    +$total_rawat_inap_dr
    +$total_rawat_inap_pr
    +$total_rawat_inap_drpr
    +$total_detail_pemberian_obat_ranap
    +$total_periksa_lab_ranap
    +$total_periksa_radiologi_ranap
    +$jumlah_total_operasi
    +$jumlah_total_obat_operasi
    +$ranap['biaya_reg']);
    $this->tpl->set('total_detail_pemberian_obat', $total_detail_pemberian_obat);
    $this->tpl->set('total_detail_pemberian_obat_ranap', $total_detail_pemberian_obat_ranap);
    $this->tpl->set('total_rawat_jl_dr', $total_rawat_jl_dr);
    $this->tpl->set('total_rawat_jl_pr', $total_rawat_jl_pr);
    $this->tpl->set('total_rawat_jl_drpr', $total_rawat_jl_drpr);
    $this->tpl->set('total_rawat_inap_dr', $total_rawat_inap_dr);
    $this->tpl->set('total_rawat_inap_pr', $total_rawat_inap_pr);
    $this->tpl->set('total_rawat_inap_drpr', $total_rawat_inap_drpr);
    $this->tpl->set('total_biaya_kamarinap', $total_biaya_kamarinap+$ranap['biaya_reg']);
    $this->tpl->set('total_periksa_lab', $total_periksa_lab);
    $this->tpl->set('total_periksa_radiologi', $total_periksa_radiologi);
    $this->tpl->set('total_periksa_lab_ranap', $total_periksa_lab_ranap);
    $this->tpl->set('total_periksa_radiologi_ranap', $total_periksa_radiologi_ranap);
    $this->tpl->set('jumlah_total_operasi', $jumlah_total_operasi);
    $this->tpl->set('jumlah_total_obat_operasi', $jumlah_total_obat_operasi);
    $this->tpl->set('pasien', $pasien);
    $this->tpl->set('reg_periksa', $reg_periksa);
    //$this->tpl->set('rujukan_internal', $rujukan_internal);
    $this->tpl->set('dpjp_ranap', $dpjp_ranap);
    $this->tpl->set('diagnosa_pasien', $diagnosa_pasien);
    $this->tpl->set('prosedur_pasien', $prosedur_pasien);
    $this->tpl->set('pemeriksaan_ralan', $pemeriksaan_ralan);
    $this->tpl->set('pemeriksaan_ranap', $pemeriksaan_ranap);
    $this->tpl->set('resume_ranap', $resume_ranap);
    $this->tpl->set('rawat_jl_dr', $rawat_jl_dr);
    $this->tpl->set('rawat_jl_pr', $rawat_jl_pr);
    $this->tpl->set('rawat_jl_drpr', $rawat_jl_drpr);
    $this->tpl->set('rawat_inap_dr', $rawat_inap_dr);
    $this->tpl->set('rawat_inap_pr', $rawat_inap_pr);
    $this->tpl->set('rawat_inap_drpr', $rawat_inap_drpr);
    $this->tpl->set('ranap', $ranap);
    $this->tpl->set('kamar_inap', $kamar_inap);
    $this->tpl->set('lama_inap', $lama_inap['lama']);
    $this->tpl->set('operasi', $operasi);
    $this->tpl->set('rujuk_ralan', $rujuk_ralan);
    $this->tpl->set('rujuk_ranap', $rujuk_ranap);
    $this->tpl->set('rujuk_igd', $rujuk_igd);
    $this->tpl->set('tindakan_radiologi', $tindakan_radiologi);
    $this->tpl->set('pemeriksaan_laboratorium', $pemeriksaan_laboratorium);
    $this->tpl->set('pemberian_obat', $pemberian_obat);
    $this->tpl->set('obat_operasi', $obat_operasi);
    $this->tpl->set('resep_pulang', $resep_pulang);
    $this->tpl->set('laporan_operasi', $laporan_operasi);
    $this->tpl->set('laporan_operasi_ralan', $laporan_operasi_ralan);

    $this->tpl->set('berkas_digital', $berkas_digital);
    $this->tpl->set('berkas_digital_pdf', $berkas_digital_pdf);
    $this->tpl->set('berkas_sep_pdf', $berkas_sep_pdf);

    $this->tpl->set('pacs', $pacs);
    $this->tpl->set('orthanc', $orthanc);
    // $this->tpl->set(name: 'tgl_hasil', value: $tgl_hasil);
    $this->tpl->set('hasil_radiologi', $this->db('hasil_radiologi')->where('no_rawat', $this->revertNorawat($id))->toArray());
    $this->tpl->set('gambar_radiologi', $this->db('gambar_radiologi')->where('no_rawat', $this->revertNorawat($id))->toArray());
    $this->tpl->set('vedika', htmlspecialchars_array($this->settings('vedika')));
    $this->tpl->set('pengaturan_billing', $this->settings->get('vedika.billing'));
    $this->tpl->set('pemeriksaan_rehab', $pemeriksaan_rehab);
    $this->tpl->set('kunjungan', $frekuensi_kunjungan);
    $this->tpl->set('uji_fungsi_kfr', $uji_fungsi_kfr);
    $this->tpl->set('pre_uji_fungsi_kfr', $pre_uji_fungsi_kfr);
    echo $this->tpl->draw(MODULES . '/vedika/view/admin/pdf.html', true);
    exit();
  }
  
  public function _renderPDFKlaimHTML($id)
  {
    $this->_addHeaderFiles();

    $berkas_digital = $this->db('berkas_digital_perawatan')
      ->join('master_berkas_digital', 'master_berkas_digital.kode=berkas_digital_perawatan.kode')
      ->where('berkas_digital_perawatan.no_rawat', $this->revertNorawat($id))
      ->notLike('lokasi_file','%pdf')
      ->asc('master_berkas_digital.nama')
      ->toArray();

    $berkas_digital_pdf = $this->db('berkas_digital_perawatan')
      ->join('master_berkas_digital', 'master_berkas_digital.kode=berkas_digital_perawatan.kode')
      ->where('berkas_digital_perawatan.no_rawat', $this->revertNorawat($id))
      ->where('berkas_digital_perawatan.kode','!=' ,'001')
      ->like('lokasi_file','%pdf')
      ->asc('master_berkas_digital.nama')
      ->toArray();

    $berkas_sep_pdf = $this->db('berkas_digital_perawatan')
      ->join('master_berkas_digital', 'master_berkas_digital.kode=berkas_digital_perawatan.kode')
      ->where('berkas_digital_perawatan.no_rawat', $this->revertNorawat($id))
      ->where('berkas_digital_perawatan.kode','=', '001')
      ->like('lokasi_file','%pdf')
      ->asc('master_berkas_digital.nama')
      ->toArray();

    $no_rawat = $this->revertNorawat($id);

    $check_billing = $this->db()->pdo()->query("SHOW TABLES LIKE 'billing'");
    $check_billing->execute();
    $check_billing = $check_billing->fetch();

    if($check_billing) {
      $query = $this->db()->pdo()->prepare("select no,nm_perawatan,pemisah,if(biaya=0,'',biaya),if(jumlah=0,'',jumlah),if(tambahan=0,'',tambahan),if(totalbiaya=0,'',totalbiaya),totalbiaya from billing where no_rawat='$no_rawat'");
      $query->execute();
      $rows = $query->fetchAll();
      $total = 0;
      foreach ($rows as $key => $value) {
        $total = $total + $value['7'];
      }
      $total = $total;
    } else {
      $rows = [];
      $total = '';
    }

    $this->tpl->set('total', $total);

    $lengkap = $this->db('mlite_vedika')
           ->where('mlite_vedika.no_rawat', $no_rawat)
           ->oneArray();
    $this->tpl->set('lengkap', $lengkap);
    
    $instansi['logo'] = $this->settings->get('settings.logo');
    $instansi['nama_instansi'] = $this->settings->get('settings.nama_instansi');
    $instansi['alamat'] = $this->settings->get('settings.alamat');
    $instansi['kota'] = $this->settings->get('settings.kota');
    $instansi['propinsi'] = $this->settings->get('settings.propinsi');
    $instansi['nomor_telepon'] = $this->settings->get('settings.nomor_telepon');
    $instansi['email'] = $this->settings->get('settings.email');

    $this->tpl->set('billing', $rows);

    /* Menggunakan billing bawaan mLITE */

    if($this->settings->get('vedika.billing') == 'mlite') {
        $settings = $this->settings('settings');
        $this->tpl->set('settings', $this->tpl->noParse_array(htmlspecialchars_array($settings)));

       $reg_periksa = $this->db('reg_periksa')->where('no_rawat', $no_rawat)->oneArray();
       if($reg_periksa['status_lanjut'] == 'Ralan') {
          $result_detail['billing'] = $this->db('mlite_billing')->where('no_rawat', $no_rawat)->like('kd_billing', 'RJ%')->desc('id_billing')->oneArray();
          $result_detail['fullname'] = $this->core->getUserInfo('fullname', $result_detail['billing']['id_user'], true);

          $result_detail['poliklinik'] = $this->db('poliklinik')
            ->join('reg_periksa', 'reg_periksa.kd_poli = poliklinik.kd_poli')
            ->where('reg_periksa.no_rawat', $no_rawat)
            ->oneArray();

          $poliklinik = $this->db('poliklinik')
            ->join('reg_periksa', 'reg_periksa.kd_poli=poliklinik.kd_poli')
            ->where('no_rawat', $no_rawat)
            ->oneArray();
          if($poliklinik['stts_daftar'] == 'Lama') {
            $poliklinik['registrasi'] = $poliklinik['registrasilama'];
          }


          $result_detail['rawat_jl_dr'] = $this->db('rawat_jl_dr')
            ->select('jns_perawatan.nm_perawatan')
            ->select(['biaya_rawat' => 'rawat_jl_dr.biaya_rawat'])
            ->select(['jml' => 'COUNT(rawat_jl_dr.kd_jenis_prw)'])
            ->select(['total_biaya_rawat_dr' => 'SUM(rawat_jl_dr.biaya_rawat)'])
            ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw = rawat_jl_dr.kd_jenis_prw')
            ->where('rawat_jl_dr.no_rawat', $no_rawat)
            ->group('jns_perawatan.nm_perawatan')
            ->toArray();

          $total_rawat_jl_dr = 0;
          foreach ($result_detail['rawat_jl_dr'] as $row) {
            $total_rawat_jl_dr += $row['total_biaya_rawat_dr'];
          }

          $result_detail['rawat_jl_pr'] = $this->db('rawat_jl_pr')
            ->select('jns_perawatan.nm_perawatan')
            ->select(['biaya_rawat' => 'rawat_jl_pr.biaya_rawat'])
            ->select(['jml' => 'COUNT(rawat_jl_pr.kd_jenis_prw)'])
            ->select(['total_biaya_rawat_pr' => 'SUM(rawat_jl_pr.biaya_rawat)'])
            ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw = rawat_jl_pr.kd_jenis_prw')
            ->where('rawat_jl_pr.no_rawat', $no_rawat)
            ->group('jns_perawatan.nm_perawatan')
            ->toArray();

          $total_rawat_jl_pr = 0;
          foreach ($result_detail['rawat_jl_pr'] as $row) {
            $total_rawat_jl_pr += $row['total_biaya_rawat_pr'];
          }

          $result_detail['rawat_jl_drpr'] = $this->db('rawat_jl_drpr')
            ->select('jns_perawatan.nm_perawatan')
            ->select(['biaya_rawat' => 'rawat_jl_drpr.biaya_rawat'])
            ->select(['jml' => 'COUNT(rawat_jl_drpr.kd_jenis_prw)'])
            ->select(['total_biaya_rawat_drpr' => 'SUM(rawat_jl_drpr.biaya_rawat)'])
            ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw = rawat_jl_drpr.kd_jenis_prw')
            ->where('rawat_jl_drpr.no_rawat', $no_rawat)
            ->group('jns_perawatan.nm_perawatan')
            ->toArray();

          $total_rawat_jl_drpr = 0;
          foreach ($result_detail['rawat_jl_drpr'] as $row) {
            $total_rawat_jl_drpr += $row['total_biaya_rawat_drpr'];
          }

          $result_detail['detail_pemberian_obat'] = $this->db('detail_pemberian_obat')
            ->join('databarang', 'databarang.kode_brng=detail_pemberian_obat.kode_brng')
            ->where('no_rawat', $no_rawat)
            ->where('detail_pemberian_obat.status', 'Ralan')
            ->toArray();

          $total_detail_pemberian_obat = 0;
          foreach ($result_detail['detail_pemberian_obat'] as $row) {
            $total_detail_pemberian_obat += $row['total'];
          }

          $result_detail['periksa_lab'] = $this->db('periksa_lab')
            ->select('jns_perawatan_lab.nm_perawatan') 
            ->select('periksa_lab.biaya')  
            ->select('periksa_lab.kd_jenis_prw')          
            ->join('jns_perawatan_lab', 'jns_perawatan_lab.kd_jenis_prw=periksa_lab.kd_jenis_prw')
            ->where('periksa_lab.no_rawat', $no_rawat)
            ->where('periksa_lab.status', 'Ralan')
            ->where('periksa_lab.biaya', '!=','0')
            ->toArray();

          $result_detail['detail_periksa_lab'] = $this->db('detail_periksa_lab')
            ->select('jns_perawatan_lab.nm_perawatan') 
            ->select(['biaya' => 'SUM(detail_periksa_lab.bagian_dokter)'])
            ->select('detail_periksa_lab.kd_jenis_prw') 
            ->join('jns_perawatan_lab', 'jns_perawatan_lab.kd_jenis_prw=detail_periksa_lab.kd_jenis_prw')
            ->where('detail_periksa_lab.no_rawat', $no_rawat)
            ->where('detail_periksa_lab.bagian_dokter', '!=','0')
            ->group('detail_periksa_lab.kd_jenis_prw')
            ->toArray();

          $total_periksa_lab = 0;
          foreach (array_merge($result_detail['periksa_lab'], $result_detail['detail_periksa_lab']) as $row) {
            $total_periksa_lab += $row['biaya'];
          }

          $result_detail['periksa_radiologi'] = $this->db('periksa_radiologi')
            ->join('jns_perawatan_radiologi', 'jns_perawatan_radiologi.kd_jenis_prw=periksa_radiologi.kd_jenis_prw')
            ->where('no_rawat', $no_rawat)
            // ->where('periksa_radiologi.status', 'Ralan')
            ->toArray();

          $total_periksa_radiologi = 0;
          foreach ($result_detail['periksa_radiologi'] as $row) {
            $total_periksa_radiologi += $row['biaya'];
          }

          $jumlah_total_operasi = 0;
          $operasis = $this->db('operasi')->join('paket_operasi', 'paket_operasi.kode_paket=operasi.kode_paket')->where('no_rawat', $no_rawat)->where('operasi.status', 'Ralan')->toArray();
          $result_detail['operasi'] = [];
          foreach ($operasis as $operasi) {
            $operasi['jumlah'] = $operasi['biayaoperator1']+$operasi['biayaoperator2']+$operasi['biayaoperator3']+$operasi['biayaasisten_operator1']+$operasi['biayaasisten_operator2']+$operasi['biayadokter_anak']+$operasi['biayaperawaat_resusitas']+$operasi['biayadokter_anestesi']+$operasi['biayaasisten_anestesi']+$operasi['biayabidan']+$operasi['biayaperawat_luar']+$operasi['sarpras'];
            $jumlah_total_operasi += $operasi['jumlah'];
            $result_detail['operasi'][] = $operasi;
          }
          $jumlah_total_obat_operasi = 0;
          $obat_operasis = $this->db('beri_obat_operasi')->join('obatbhp_ok', 'obatbhp_ok.kd_obat=beri_obat_operasi.kd_obat')->where('no_rawat', $no_rawat)->toArray();
          $result_detail['obat_operasi'] = [];
          foreach ($obat_operasis as $obat_operasi) {
            $obat_operasi['harga'] = $obat_operasi['hargasatuan'] * $obat_operasi['jumlah'];
            $jumlah_total_obat_operasi += $obat_operasi['harga'];
            $result_detail['obat_operasi'][] = $obat_operasi;
          }

       } else {

         $result_detail['billing'] = $this->db('mlite_billing')->where('no_rawat', $no_rawat)->like('kd_billing', 'RI%')->desc('id_billing')->oneArray();
         $result_detail['fullname'] = $this->core->getUserInfo('fullname', $result_detail['billing']['id_user'], true);

         $result_detail['kamar_inap'] = $this->db('kamar_inap')
           ->join('reg_periksa', 'reg_periksa.no_rawat = kamar_inap.no_rawat')
           ->where('reg_periksa.no_rawat', $no_rawat)
           ->oneArray();

         // $result_detail['ranap'] = $this->db('kamar_inap')
         // ->where('no_rawat', revertNoRawat($no_rawat))
         // ->limit(1)->desc('tgl_keluar')
         // ->toArray();
         $result_detail['rawat_jl_dr'] = $this->db('rawat_jl_dr')
            ->select('jns_perawatan.nm_perawatan')
            ->select(['biaya_rawat' => 'rawat_jl_dr.biaya_rawat'])
            ->select(['jml' => 'COUNT(rawat_jl_dr.kd_jenis_prw)'])
            ->select(['total_biaya_rawat_dr' => 'SUM(rawat_jl_dr.biaya_rawat)'])
            ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw = rawat_jl_dr.kd_jenis_prw')
            ->where('rawat_jl_dr.no_rawat', $no_rawat)
            ->group('jns_perawatan.nm_perawatan')
            ->toArray();

          $total_rawat_jl_dr = 0;
          foreach ($result_detail['rawat_jl_dr'] as $row) {
            $total_rawat_jl_dr += $row['total_biaya_rawat_dr'];
          }

          $result_detail['rawat_jl_pr'] = $this->db('rawat_jl_pr')
            ->select('jns_perawatan.nm_perawatan')
            ->select(['biaya_rawat' => 'rawat_jl_pr.biaya_rawat'])
            ->select(['jml' => 'COUNT(rawat_jl_pr.kd_jenis_prw)'])
            ->select(['total_biaya_rawat_pr' => 'SUM(rawat_jl_pr.biaya_rawat)'])
            ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw = rawat_jl_pr.kd_jenis_prw')
            ->where('rawat_jl_pr.no_rawat', $no_rawat)
            ->group('jns_perawatan.nm_perawatan')
            ->toArray();

          $total_rawat_jl_pr = 0;
          foreach ($result_detail['rawat_jl_pr'] as $row) {
            $total_rawat_jl_pr += $row['total_biaya_rawat_pr'];
          }

          $result_detail['rawat_jl_drpr'] = $this->db('rawat_jl_drpr')
            ->select('jns_perawatan.nm_perawatan')
            ->select(['biaya_rawat' => 'rawat_jl_drpr.biaya_rawat'])
            ->select(['jml' => 'COUNT(rawat_jl_drpr.kd_jenis_prw)'])
            ->select(['total_biaya_rawat_drpr' => 'SUM(rawat_jl_drpr.biaya_rawat)'])
            ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw = rawat_jl_drpr.kd_jenis_prw')
            ->where('rawat_jl_drpr.no_rawat', $no_rawat)
            ->group('jns_perawatan.nm_perawatan')
            ->toArray();

          $total_rawat_jl_drpr = 0;
          foreach ($result_detail['rawat_jl_drpr'] as $row) {
            $total_rawat_jl_drpr += $row['total_biaya_rawat_drpr'];
          }

          $ranap = $this->db('kamar_inap')
            ->join('reg_periksa', 'reg_periksa.no_rawat=kamar_inap.no_rawat')
            ->join('poliklinik','poliklinik.kd_poli=reg_periksa.kd_poli')
            ->where('reg_periksa.no_rawat', $no_rawat)
            // ->where('kamar_inap.stts_pulang', '!=','Pindah Kamar')
            ->oneArray();

           $result_detail['biaya_ranap'] = $this->db('kamar_inap')
             ->where('kamar_inap.no_rawat', $no_rawat)
             ->desc('tgl_keluar')
            //  ->limit('1')
             ->toArray();
 
             $total_biaya_kamarinap = 0;
            foreach ($result_detail['biaya_ranap'] as $row) {
             $total_biaya_kamarinap += $row['ttl_biaya'];
            }
          
         $result_detail['rawat_inap_dr'] = $this->db('rawat_inap_dr')
           ->select('jns_perawatan_inap.nm_perawatan')
           ->select(['biaya_rawat' => 'rawat_inap_dr.biaya_rawat'])
           ->select(['jml' => 'COUNT(rawat_inap_dr.kd_jenis_prw)'])
           ->select(['total_biaya_rawat_dr' => 'SUM(rawat_inap_dr.biaya_rawat)'])
           ->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw = rawat_inap_dr.kd_jenis_prw')
           ->where('rawat_inap_dr.no_rawat', $no_rawat)
           ->group('jns_perawatan_inap.nm_perawatan')
           ->toArray();

           $total_rawat_inap_dr = 0;
          foreach ($result_detail['rawat_inap_dr'] as $row) {
            $total_rawat_inap_dr += $row['total_biaya_rawat_dr'];
          }

         $result_detail['rawat_inap_pr'] = $this->db('rawat_inap_pr')
           ->select('jns_perawatan_inap.nm_perawatan')
           ->select(['biaya_rawat' => 'rawat_inap_pr.biaya_rawat'])
           ->select(['jml' => 'COUNT(rawat_inap_pr.kd_jenis_prw)'])
           ->select(['total_biaya_rawat_pr' => 'SUM(rawat_inap_pr.biaya_rawat)'])
           ->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw = rawat_inap_pr.kd_jenis_prw')
           ->where('rawat_inap_pr.no_rawat', $no_rawat)
           ->group('jns_perawatan_inap.nm_perawatan')
           ->toArray();

           $total_rawat_inap_pr = 0;
          foreach ($result_detail['rawat_inap_pr'] as $row) {
            $total_rawat_inap_pr += $row['total_biaya_rawat_pr'];
          }

         $result_detail['rawat_inap_drpr'] = $this->db('rawat_inap_drpr')
           ->select('jns_perawatan_inap.nm_perawatan')
           ->select(['biaya_rawat' => 'rawat_inap_drpr.biaya_rawat'])
           ->select(['jml' => 'COUNT(rawat_inap_drpr.kd_jenis_prw)'])
           ->select(['total_biaya_rawat_drpr' => 'SUM(rawat_inap_drpr.biaya_rawat)'])
           ->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw = rawat_inap_drpr.kd_jenis_prw')
           ->where('rawat_inap_drpr.no_rawat', $no_rawat)
           ->group('jns_perawatan_inap.nm_perawatan')
           ->toArray();

          $total_rawat_inap_drpr = 0;
          foreach ($result_detail['rawat_inap_drpr'] as $row) {
            $total_rawat_inap_drpr += $row['total_biaya_rawat_drpr'];
          }

         $result_detail['detail_pemberian_obat_ranap'] = $this->db('detail_pemberian_obat')
           ->join('databarang', 'databarang.kode_brng=detail_pemberian_obat.kode_brng')
           ->where('no_rawat', $no_rawat)
           // ->where('detail_pemberian_obat.status', 'Ranap')
           ->toArray();

          $total_detail_pemberian_obat_ranap = 0;
          foreach ($result_detail['detail_pemberian_obat_ranap'] as $row) {
            $total_detail_pemberian_obat_ranap += $row['total'];
          }

         $result_detail['periksa_lab_ranap'] = $this->db('periksa_lab')
            ->select('jns_perawatan_lab.nm_perawatan') 
            ->select('periksa_lab.biaya')  
            ->select('periksa_lab.kd_jenis_prw')          
            ->join('jns_perawatan_lab', 'jns_perawatan_lab.kd_jenis_prw=periksa_lab.kd_jenis_prw')
            ->where('periksa_lab.no_rawat', $no_rawat)
            // ->where('periksa_lab.status', 'Ranap')
            ->where('periksa_lab.biaya', '!=','0')
            ->toArray();

          $result_detail['detail_periksa_lab_ranap'] = $this->db('detail_periksa_lab')
            ->select('jns_perawatan_lab.nm_perawatan') 
            ->select(['biaya' => 'SUM(detail_periksa_lab.bagian_dokter)'])
            ->select('detail_periksa_lab.kd_jenis_prw') 
            ->join('jns_perawatan_lab', 'jns_perawatan_lab.kd_jenis_prw=detail_periksa_lab.kd_jenis_prw')
            ->where('detail_periksa_lab.no_rawat', $no_rawat)
            ->where('detail_periksa_lab.bagian_dokter', '!=','0')
            ->group('detail_periksa_lab.kd_jenis_prw')
            ->toArray();

          $total_periksa_lab_ranap = 0;
          foreach (array_merge($result_detail['periksa_lab_ranap'], $result_detail['detail_periksa_lab_ranap']) as $row) {
            $total_periksa_lab_ranap += $row['biaya'];
          }

         $result_detail['periksa_radiologi_ranap'] = $this->db('periksa_radiologi')
           ->join('jns_perawatan_radiologi', 'jns_perawatan_radiologi.kd_jenis_prw=periksa_radiologi.kd_jenis_prw')
           ->where('no_rawat', $no_rawat)
           // ->where('periksa_radiologi.status', 'Ranap')
           ->toArray();

          $total_periksa_radiologi_ranap = 0;
          foreach ($result_detail['periksa_radiologi_ranap'] as $row) {
            $total_periksa_radiologi_ranap += $row['biaya'];
          }
    
         $result_detail['tambahan_biaya'] = $this->db('tambahan_biaya')
           //->where('status', 'ranap')
           ->where('no_rawat', $no_rawat)
           ->toArray();

         $jumlah_total_operasi = 0;
         $operasis = $this->db('operasi')
         ->join('paket_operasi', 'paket_operasi.kode_paket=operasi.kode_paket')
         ->where('no_rawat', $no_rawat)
         // ->where('operasi.status', 'Ranap')
         ->toArray();
         $result_detail['operasi'] = [];
         foreach ($operasis as $operasi) {
           $operasi['jumlah'] = $operasi['biayaoperator1']+$operasi['biayaoperator2']+$operasi['biayaoperator3']+$operasi['biayaasisten_operator1']+$operasi['biayaasisten_operator2']+$operasi['biayadokter_anak']+$operasi['biayaperawaat_resusitas']+$operasi['biayadokter_anestesi']+$operasi['biayaasisten_anestesi']+$operasi['biayabidan']+$operasi['biayaperawat_luar']+$operasi['sarpras'];
           $jumlah_total_operasi += $operasi['jumlah'];
           $result_detail['operasi'][] = $operasi;
         }
         $jumlah_total_obat_operasi = 0;
         $obat_operasis = $this->db('beri_obat_operasi')->join('obatbhp_ok', 'obatbhp_ok.kd_obat=beri_obat_operasi.kd_obat')->where('no_rawat', $no_rawat)->toArray();
         $result_detail['obat_operasi'] = [];
         foreach ($obat_operasis as $obat_operasi) {
           $obat_operasi['harga'] = $obat_operasi['hargasatuan'] * $obat_operasi['jumlah'];
           $jumlah_total_obat_operasi += $obat_operasi['harga'];
           $result_detail['obat_operasi'][] = $obat_operasi;
         }

       }

       $this->tpl->set('billing', $result_detail);

    }

    /* End menggunakan billing bawaan mlITE */

    $this->tpl->set('instansi', $instansi);

    $print_sep = array();
    if (!empty($this->_getSEPInfo('no_sep', $no_rawat))) {
      $print_sep['bridging_sep'] = $this->db('bridging_sep')->where('no_sep', $this->_getSEPInfo('no_sep', $no_rawat))->oneArray();
      $print_sep['bpjs_prb'] = $this->db('bpjs_prb')->where('no_sep', $this->_getSEPInfo('no_sep', $no_rawat))->oneArray();
      $batas_rujukan = $this->db('bridging_sep')->select('DATE_ADD(tglrujukan , INTERVAL 85 DAY) AS batas_rujukan')->where('no_sep', $this->_getSEPInfo('no_sep', $no_rawat))->oneArray();
      $print_sep['batas_rujukan'] = $batas_rujukan['batas_rujukan'];
      switch ($print_sep['bridging_sep']['klsnaik']) {
        case '2':
          $print_sep['kelas_naik'] = 'Kelas VIP';
          break;
        case '3':
          $print_sep['kelas_naik'] = 'Kelas 1';
          break;
        case '4':
          $print_sep['kelas_naik'] = 'Kelas 2';
          break;

        default:
          $print_sep['kelas_naik'] = "";
          break;
      }
    }
    $print_sep['nama_instansi'] = $this->settings->get('settings.nama_instansi');
    $print_sep['logoURL'] = url(MODULES . '/vclaim/img/bpjslogo.png');
    $this->tpl->set('print_sep', $print_sep);

    $permintaan_ranap = $this->db('permintaan_ranap')
    ->where('no_rawat', $this->revertNorawat($id))
    ->join('dokter', 'dokter.kd_dokter=permintaan_ranap.kd_dpjp')
    ->oneArray();
    $this->tpl->set('permintaan_ranap', $permintaan_ranap);

    $rujukan_ranap = $this->db('rujuk')
    ->where('no_rawat', $this->revertNorawat($id))
    ->join('dokter', 'dokter.kd_dokter=rujuk.kd_dokter')
    ->oneArray();
    $this->tpl->set('rujukan_ranap', $rujukan_ranap);

    $cek_spri = $this->db('bridging_surat_pri_bpjs')
    ->where('no_rawat', $this->revertNorawat($id))
    ->oneArray();
    $this->tpl->set('cek_spri', $cek_spri);

    $print_spri = array();
    if (!empty($this->_getSPRIInfo('no_surat', $no_rawat))) {
      $print_spri['bridging_surat_pri_bpjs'] = $this->db('bridging_surat_pri_bpjs')->where('no_surat', $this->_getSPRIInfo('no_surat', $no_rawat))->oneArray();
    }
    $print_spri['nama_instansi'] = $this->settings->get('settings.nama_instansi');
    $print_spri['logoURL'] = url(MODULES . '/vclaim/img/bpjslogo.png');
    $this->tpl->set('print_spri', $print_spri);

    $resume_pasien = $this->db('resume_pasien_ranap')
      ->join('dokter', 'dokter.kd_dokter = resume_pasien_ranap.kd_dokter')
      ->where('no_rawat', $this->revertNorawat($id))
      ->oneArray();
      
    if(!$this
    ->db('resume_pasien_ranap')
    ->where('no_rawat', $this->revertNorawat($id))
    ->oneArray()) {
      $resume_pasien = $this->db('resume_pasien')
        ->join('dokter', 'dokter.kd_dokter = resume_pasien.kd_dokter')
        ->where('no_rawat', $this->revertNorawat($id))
        ->oneArray();
    }
    $this->tpl->set('resume_pasien', $resume_pasien);

    $asesmen_medis_igd = $this->db('asesmen_medis_igd')
      ->join('dokter', 'dokter.kd_dokter = asesmen_medis_igd.kd_dokter')
      ->where('no_rawat', $this->revertNorawat($id))
      ->oneArray();

    $this->tpl->set('asesmen_medis_igd', $asesmen_medis_igd);

    $triase_igd = $this->db('data_triase_igd')
      ->join('master_triase_macam_kasus', 'master_triase_macam_kasus.kode_kasus = data_triase_igd.kode_kasus')
      ->where('no_rawat', $this->revertNorawat($id))
      ->oneArray();

    $this->tpl->set('triase_igd', $triase_igd);

    $triaseprimer = $this->db('data_triase_igdprimer')
      ->where('no_rawat', $this->revertNorawat($id))
      ->oneArray();

    $this->tpl->set('triaseprimer', $triaseprimer);
  
    $triasesekunder = $this->db('data_triase_igdsekunder')
      ->where('no_rawat', $this->revertNorawat($id))
      ->oneArray();

    $this->tpl->set('triasesekunder', $triasesekunder);

    $skala1 = $this->db('data_triase_igddetail_skala1')
    ->where('no_rawat', $this->revertNorawat($id))
    ->oneArray();

    $this->tpl->set('skala1', $skala1);

    $skala2 = $this->db('data_triase_igddetail_skala2')
    ->where('no_rawat', $this->revertNorawat($id))
    ->oneArray();

    $this->tpl->set('skala2', $skala2);

    $skala3 = $this->db('data_triase_igddetail_skala3')
    ->where('no_rawat', $this->revertNorawat($id))
    ->oneArray();

    $this->tpl->set('skala3', $skala3);

    $skala4 = $this->db('data_triase_igddetail_skala4')
    ->where('no_rawat', $this->revertNorawat($id))
    ->oneArray();

    $this->tpl->set('skala4', $skala4);

    $skala5 = $this->db('data_triase_igddetail_skala5')
    ->where('no_rawat', $this->revertNorawat($id))
    ->oneArray();

    $this->tpl->set('skala5', $skala5);   

    $pasien = $this->db('pasien')
      ->join('kecamatan', 'kecamatan.kd_kec = pasien.kd_kec')
      ->join('kabupaten', 'kabupaten.kd_kab = pasien.kd_kab')
      ->where('no_rkm_medis', $this->getRegPeriksaInfo('no_rkm_medis', $this->revertNorawat($id)))
      ->oneArray();
    $reg_periksa = $this->db('reg_periksa')
      ->join('dokter', 'dokter.kd_dokter = reg_periksa.kd_dokter')
      ->join('poliklinik', 'poliklinik.kd_poli = reg_periksa.kd_poli')
      ->join('penjab', 'penjab.kd_pj = reg_periksa.kd_pj')
      ->where('stts', '<>', 'Batal')
      ->where('no_rawat', $this->revertNorawat($id))
      ->oneArray();
    $rows_dpjp_ranap = $this->db('dpjp_ranap')
      ->join('dokter', 'dokter.kd_dokter = dpjp_ranap.kd_dokter')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();
    $dpjp_i = 1;
    $dpjp_ranap = [];
    foreach ($rows_dpjp_ranap as $row) {
      $row['nomor'] = $dpjp_i++;
      $dpjp_ranap[] = $row;
    }
    /*
    $rujukan_internal = $this->db('rujukan_internal_poli')
      ->join('poliklinik', 'poliklinik.kd_poli = rujukan_internal_poli.kd_poli')
      ->join('dokter', 'dokter.kd_dokter = rujukan_internal_poli.kd_dokter')
      ->where('no_rawat', $this->revertNorawat($id))
      ->oneArray();
    */
    $diagnosa_pasien = $this->db('diagnosa_pasien')
      ->join('penyakit', 'penyakit.kd_penyakit = diagnosa_pasien.kd_penyakit')
      ->where('no_rawat', $this->revertNorawat($id))
      ->where('diagnosa_pasien.status', 'Ralan')
      ->asc('prioritas')
      ->toArray();
    if($reg_periksa['status_lanjut'] == 'Ranap'){
      $diagnosa_pasien = $this->db('diagnosa_pasien')
        ->join('penyakit', 'penyakit.kd_penyakit = diagnosa_pasien.kd_penyakit')
        ->where('no_rawat', $this->revertNorawat($id))
        ->where('diagnosa_pasien.status', 'Ranap')
        ->asc('prioritas')
        ->toArray();
    }

    $prosedur_pasien = $this->db('prosedur_pasien')
      ->join('icd9', 'icd9.kode = prosedur_pasien.kode')
      ->where('no_rawat', $this->revertNorawat($id))
      ->where('status', 'Ralan')
      ->asc('prioritas')
      ->toArray();
      if($reg_periksa['status_lanjut'] == 'Ranap'){
    $prosedur_pasien = $this->db('prosedur_pasien')
      ->join('icd9', 'icd9.kode = prosedur_pasien.kode')
      ->where('no_rawat', $this->revertNorawat($id))
      ->where('status', 'Ranap')
      ->asc('prioritas')
      ->toArray();
      }

    $pemeriksaan_ralan = $this->db('pemeriksaan_ralan')
      ->where('no_rawat', $this->revertNorawat($id))
      ->asc('tgl_perawatan')
      ->asc('jam_rawat')
      ->toArray();
    $pemeriksaan_rehab = $this->db('pemeriksaan_ralan_rehab')
      ->select('pemeriksaan_ralan_rehab.*')
      ->select('pegawai.*')
      ->where('no_rawat', $this->revertNorawat($id))
      ->join('pegawai', 'pemeriksaan_ralan_rehab.nik=pegawai.nik')
      ->asc('tgl_perawatan')
      ->asc('jam_rawat')
      ->oneArray();
    $frekuensi_kunjungan = $this->db('kunjungan_fisio_rehab')
      ->where('no_rawat', $this->revertNorawat($id))
      ->oneArray();
    $uji_fungsi_kfr = $this->db('uji_fungsi_kfr')
      ->where('no_rawat', $this->revertNorawat($id))
      ->asc('tanggal')
      ->oneArray();    
    $pre_uji_fungsi_kfr = $this->db('uji_fungsi_kfr')
      ->select('uji_fungsi_kfr.*')
      ->join('reg_periksa', 'uji_fungsi_kfr.no_rawat=reg_periksa.no_rawat')
      ->where('no_rkm_medis', $this->getRegPeriksaInfo('no_rkm_medis', $this->revertNorawat($id)))
      ->desc('tanggal')
      ->limit('1')
      ->oneArray();
    $pemeriksaan_ranap = $this->db('pemeriksaan_ranap')
      ->where('no_rawat', $this->revertNorawat($id))
      ->asc('tgl_perawatan')
      ->asc('jam_rawat')
      ->toArray();
    
    foreach ($pemeriksaan_ranap as &$pr) {
      if (!isset($pr['pemeriksaan']) || $pr['pemeriksaan'] === '') continue;
    
      $s = $pr['pemeriksaan'];
    
      // NBSP (dua kemungkinan)
      $s = str_replace(["\xC2\xA0", "\xA0"], ' ', $s);
    
      // rapikan newline
      $s = str_replace(["\r\n", "\r"], "\n", $s);
    
      // buang control char aneh (kecuali tab/newline)
      $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $s);
    
      // paksa jadi UTF-8 valid
      if (function_exists('mb_convert_encoding')) {
        $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
      }
    
      // opsional: kalau memang ada char � tersimpan, ganti jadi spasi
      $s = str_replace("�", " ", $s);
    
      $pr['pemeriksaan'] = trim($s);
    }
    unset($pr);

    $resume_ranap = $this->db('resume_pasien_ranap')
      ->join('dokter', 'resume_pasien_ranap.kd_dokter=dokter.kd_dokter')
      ->where('no_rawat', $this->revertNorawat($id))
      ->oneArray();
    $rawat_jl_dr = $this->db('rawat_jl_dr')
      ->join('jns_perawatan', 'rawat_jl_dr.kd_jenis_prw=jns_perawatan.kd_jenis_prw')
      ->join('dokter', 'rawat_jl_dr.kd_dokter=dokter.kd_dokter')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();
    $rawat_jl_pr = $this->db('rawat_jl_pr')
      ->join('jns_perawatan', 'rawat_jl_pr.kd_jenis_prw=jns_perawatan.kd_jenis_prw')
      ->join('petugas', 'rawat_jl_pr.nip=petugas.nip')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();
    $rawat_jl_drpr = $this->db('rawat_jl_drpr')
      ->join('jns_perawatan', 'rawat_jl_drpr.kd_jenis_prw=jns_perawatan.kd_jenis_prw')
      ->join('dokter', 'rawat_jl_drpr.kd_dokter=dokter.kd_dokter')
      ->join('petugas', 'rawat_jl_drpr.nip=petugas.nip')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();
    $rawat_inap_dr = $this->db('rawat_inap_dr')
      ->join('jns_perawatan_inap', 'rawat_inap_dr.kd_jenis_prw=jns_perawatan_inap.kd_jenis_prw')
      ->join('dokter', 'rawat_inap_dr.kd_dokter=dokter.kd_dokter')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();
    $rawat_inap_pr = $this->db('rawat_inap_pr')
      ->join('jns_perawatan_inap', 'rawat_inap_pr.kd_jenis_prw=jns_perawatan_inap.kd_jenis_prw')
      ->join('petugas', 'rawat_inap_pr.nip=petugas.nip')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();
    $rawat_inap_drpr = $this->db('rawat_inap_drpr')
      ->join('jns_perawatan_inap', 'rawat_inap_drpr.kd_jenis_prw=jns_perawatan_inap.kd_jenis_prw')
      ->join('dokter', 'rawat_inap_drpr.kd_dokter=dokter.kd_dokter')
      ->join('petugas', 'rawat_inap_drpr.nip=petugas.nip')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();

    $kamar_inap = $this->db('kamar_inap')
      ->join('kamar', 'kamar_inap.kd_kamar=kamar.kd_kamar')
      ->join('bangsal', 'kamar.kd_bangsal=bangsal.kd_bangsal')
      ->where('no_rawat', $this->revertNorawat($id))
      ->asc('tgl_keluar')
    //   ->limit('1')
      ->toArray();

    $lama_inap = $this->db('kamar_inap')
      ->select(['lama' => 'SUM(kamar_inap.lama)'])
      ->where('no_rawat', $this->revertNorawat($id))
      ->desc('lama')
      ->limit('1')
      ->oneArray();

    $operasi = $this->db('operasi')
      ->join('paket_operasi', 'operasi.kode_paket=paket_operasi.kode_paket')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();
    $rujuk_igd = $this->db('rujuk_igd')
      ->join('dokter', 'dokter.kd_dokter=rujuk_igd.kd_dokter')
      ->where('no_rawat', $this->revertNorawat($id))
      ->oneArray(); 
    $rujuk_ralan = $this->db('rujuk')
      ->select('rujuk.*')
      ->select('a.nm_dokter')
      ->join('dokter a', 'a.kd_dokter=rujuk.kd_dokter')
      ->where('no_rawat', $this->revertNorawat($id))
      ->oneArray(); 
    $rujuk_ranap = $this->db('rujuk_rawat_inap')
      ->select('rujuk_rawat_inap.*')
      ->select('a.nm_dokter')
      ->join('dokter a', 'a.kd_dokter=rujuk_rawat_inap.kd_dokter')
      ->where('no_rawat', $this->revertNorawat($id))
      ->oneArray();     
    $tindakan_radiologi = $this->db('periksa_radiologi')
      ->join('jns_perawatan_radiologi', 'periksa_radiologi.kd_jenis_prw=jns_perawatan_radiologi.kd_jenis_prw')
      ->join('dokter', 'periksa_radiologi.kd_dokter=dokter.kd_dokter')
      ->join('petugas', 'periksa_radiologi.nip=petugas.nip')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();
    $hasil_radiologi = $this->db('hasil_radiologi')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();
    $pemeriksaan_laboratorium = [];
    $rows_pemeriksaan_laboratorium = $this->db('periksa_lab')
      ->join('jns_perawatan_lab', 'jns_perawatan_lab.kd_jenis_prw=periksa_lab.kd_jenis_prw')
      ->where('no_rawat', $this->revertNorawat($id))
      ->asc('tgl_periksa')
      ->toArray();
    
    foreach ($rows_pemeriksaan_laboratorium as $value) {
    
      $value['detail_periksa_lab'] = $this->db('detail_periksa_lab')
        ->join('template_laboratorium', 'template_laboratorium.id_template=detail_periksa_lab.id_template')
        ->where('detail_periksa_lab.no_rawat', $value['no_rawat'])
        ->where('detail_periksa_lab.kd_jenis_prw', $value['kd_jenis_prw'])
        ->where('detail_periksa_lab.tgl_periksa', $value['tgl_periksa'])
        ->where('detail_periksa_lab.jam', $value['jam'])
        ->toArray();
    
      // ✅ FIX: normalisasi satuan (µL dll) + bersihin karakter aneh
      foreach ($value['detail_periksa_lab'] as &$d) {
        if (!empty($d['satuan'])) {
          $s = $d['satuan'];
    
          // NBSP (dua kemungkinan)
          $s = str_replace(["\xC2\xA0", "\xA0"], ' ', $s);
    
          // perbaiki µL yang rusak (�L) dan variasinya
          $s = str_replace(
            ['�L', '/�L', 'uL', 'u/L', 'µL'], // variasi umum
            ['µL', '/µL', 'µL', 'µ/L', 'µL'],
            $s
          );
    
          // buang control chars (kecuali newline/tab kalau ada)
          $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $s);
    
          // pastikan UTF-8 valid
          if (function_exists('mb_convert_encoding')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
          }
    
          $d['satuan'] = trim($s);
        }
    
        // opsional: kalau nilai juga suka ada karakter aneh
        if (!empty($d['nilai'])) {
          $n = $d['nilai'];
          $n = str_replace(["\xC2\xA0", "\xA0"], ' ', $n);
          $n = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $n);
          if (function_exists('mb_convert_encoding')) {
            $n = mb_convert_encoding($n, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
          }
          $d['nilai'] = trim($n);
        }
      }
      unset($d);
    
      $pemeriksaan_laboratorium[] = $value;
    }

    $pemberian_obat = $this->db('detail_pemberian_obat')
      ->join('databarang', 'detail_pemberian_obat.kode_brng=databarang.kode_brng')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();
    $obat_operasi = $this->db('beri_obat_operasi')
      ->join('obatbhp_ok', 'beri_obat_operasi.kd_obat=obatbhp_ok.kd_obat')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();
    $resep_pulang = $this->db('resep_pulang')
      ->join('databarang', 'resep_pulang.kode_brng=databarang.kode_brng')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();
    $laporan_operasi = $this->db('laporan_operasi')
      ->select('laporan_operasi.*')
      ->select('operasi.*')
      ->select('a.nm_dokter')
      ->select(['operator1' => 'a.nm_dokter'])
      ->select(['dokter_anak' => 'b.nm_dokter'])
      ->select(['dokter_anestesi' => 'c.nm_dokter'])
      ->select(['dokter_umum' => 'd.nm_dokter'])
      ->join('operasi', 'operasi.no_rawat=laporan_operasi.no_rawat')
      ->join('dokter a', 'a.kd_dokter=operasi.operator1')
      ->join('dokter b', 'b.kd_dokter=operasi.dokter_anak')
      ->join('dokter c', 'c.kd_dokter=operasi.dokter_anestesi')
      ->join('dokter d', 'd.kd_dokter=operasi.dokter_umum')
      ->where('laporan_operasi.no_rawat', $this->revertNorawat($id))
      ->group('laporan_operasi.no_rawat')
      ->oneArray();
      
    $laporan_operasi_ralan = $this->db('laporan_bedah')
      ->select('laporan_bedah.*')
      ->select('a.nm_dokter')
      ->select(['operator' => 'a.nm_dokter'])
      ->join('dokter a', 'a.kd_dokter=laporan_bedah.operator')
      ->where('laporan_bedah.no_rawat', $this->revertNorawat($id))
      ->group('laporan_bedah.no_rawat')
      ->oneArray();

    $this->tpl->set('total_biaya', 
    $total_rawat_jl_dr
    +$total_rawat_jl_pr
    +$total_rawat_jl_drpr
    +$total_detail_pemberian_obat
    +$total_periksa_lab
    +$total_periksa_radiologi
    +$jumlah_total_operasi
    +$jumlah_total_obat_operasi
    +$poliklinik['registrasi']);
    $this->tpl->set('total_biaya_ranap', 
    $total_biaya_kamarinap
    +$total_rawat_jl_dr
    +$total_rawat_jl_pr
    +$total_rawat_jl_drpr
    +$total_rawat_inap_dr
    +$total_rawat_inap_pr
    +$total_rawat_inap_drpr
    +$total_detail_pemberian_obat_ranap
    +$total_periksa_lab_ranap
    +$total_periksa_radiologi_ranap
    +$jumlah_total_operasi
    +$jumlah_total_obat_operasi
    +$ranap['biaya_reg']);
    $this->tpl->set('total_detail_pemberian_obat', $total_detail_pemberian_obat);
    $this->tpl->set('total_detail_pemberian_obat_ranap', $total_detail_pemberian_obat_ranap);
    $this->tpl->set('total_rawat_jl_dr', $total_rawat_jl_dr);
    $this->tpl->set('total_rawat_jl_pr', $total_rawat_jl_pr);
    $this->tpl->set('total_rawat_jl_drpr', $total_rawat_jl_drpr);
    $this->tpl->set('total_rawat_inap_dr', $total_rawat_inap_dr);
    $this->tpl->set('total_rawat_inap_pr', $total_rawat_inap_pr);
    $this->tpl->set('total_rawat_inap_drpr', $total_rawat_inap_drpr);
    $this->tpl->set('total_biaya_kamarinap', $total_biaya_kamarinap+$ranap['biaya_reg']);
    $this->tpl->set('total_periksa_lab', $total_periksa_lab);
    $this->tpl->set('total_periksa_radiologi', $total_periksa_radiologi);
    $this->tpl->set('total_periksa_lab_ranap', $total_periksa_lab_ranap);
    $this->tpl->set('total_periksa_radiologi_ranap', $total_periksa_radiologi_ranap);
    $this->tpl->set('jumlah_total_operasi', $jumlah_total_operasi);
    $this->tpl->set('jumlah_total_obat_operasi', $jumlah_total_obat_operasi);
    $this->tpl->set('pasien', $pasien);
    $this->tpl->set('reg_periksa', $reg_periksa);
    //$this->tpl->set('rujukan_internal', $rujukan_internal);
    $this->tpl->set('dpjp_ranap', $dpjp_ranap);
    $this->tpl->set('diagnosa_pasien', $diagnosa_pasien);
    $this->tpl->set('prosedur_pasien', $prosedur_pasien);
    $this->tpl->set('pemeriksaan_ralan', $pemeriksaan_ralan);
    $this->tpl->set('pemeriksaan_ranap', $pemeriksaan_ranap);
    $this->tpl->set('resume_ranap', $resume_ranap);
    $this->tpl->set('rawat_jl_dr', $rawat_jl_dr);
    $this->tpl->set('rawat_jl_pr', $rawat_jl_pr);
    $this->tpl->set('rawat_jl_drpr', $rawat_jl_drpr);
    $this->tpl->set('rawat_inap_dr', $rawat_inap_dr);
    $this->tpl->set('rawat_inap_pr', $rawat_inap_pr);
    $this->tpl->set('rawat_inap_drpr', $rawat_inap_drpr);
    $this->tpl->set('ranap', $ranap);
    $this->tpl->set('kamar_inap', $kamar_inap);
    $this->tpl->set('lama_inap', $lama_inap['lama']);
    $this->tpl->set('operasi', $operasi);
    $this->tpl->set('rujuk_ralan', $rujuk_ralan);
    $this->tpl->set('rujuk_ranap', $rujuk_ranap);
    $this->tpl->set('rujuk_igd', $rujuk_igd);
    $this->tpl->set('tindakan_radiologi', $tindakan_radiologi);
    $this->tpl->set('pemeriksaan_laboratorium', $pemeriksaan_laboratorium);
    $this->tpl->set('pemberian_obat', $pemberian_obat);
    $this->tpl->set('obat_operasi', $obat_operasi);
    $this->tpl->set('resep_pulang', $resep_pulang);
    $this->tpl->set('laporan_operasi', $laporan_operasi);
    $this->tpl->set('laporan_operasi_ralan', $laporan_operasi_ralan);

    $this->tpl->set('berkas_digital', $berkas_digital);
    $this->tpl->set('berkas_digital_pdf', $berkas_digital_pdf);
    $this->tpl->set('berkas_sep_pdf', $berkas_sep_pdf);

    $this->tpl->set('pacs', $pacs);
    $this->tpl->set('orthanc', $orthanc);
    // $this->tpl->set(name: 'tgl_hasil', value: $tgl_hasil);
    $this->tpl->set('hasil_radiologi', $this->db('hasil_radiologi')->where('no_rawat', $this->revertNorawat($id))->toArray());
    $this->tpl->set('gambar_radiologi', $this->db('gambar_radiologi')->where('no_rawat', $this->revertNorawat($id))->toArray());
    $this->tpl->set('vedika', htmlspecialchars_array($this->settings('vedika')));
    $this->tpl->set('pengaturan_billing', $this->settings->get('vedika.billing'));
    $this->tpl->set('pemeriksaan_rehab', $pemeriksaan_rehab);
    $this->tpl->set('kunjungan', $frekuensi_kunjungan);
    $this->tpl->set('uji_fungsi_kfr', $uji_fungsi_kfr);
    $this->tpl->set('pre_uji_fungsi_kfr', $pre_uji_fungsi_kfr);
    return $this->tpl->draw(MODULES . '/vedika/view/admin/pdfklaim.html', true);
  }
  
  private function _renderPDFKlaimGenerateHTML($id)
  {
    $this->_addHeaderFiles();

    // Nilai awal wajib tersedia untuk Ralan maupun Ranap agar render massal
    // tidak menghasilkan warning ketika salah satu kelompok biaya kosong.
    $result_detail = [];
    $total_biaya_kamarinap = 0;
    $total_rawat_jl_dr = 0;
    $total_rawat_jl_pr = 0;
    $total_rawat_jl_drpr = 0;
    $total_rawat_inap_dr = 0;
    $total_rawat_inap_pr = 0;
    $total_rawat_inap_drpr = 0;
    $total_detail_pemberian_obat = 0;
    $total_detail_pemberian_obat_ranap = 0;
    $total_periksa_lab = 0;
    $total_periksa_lab_ranap = 0;
    $total_periksa_radiologi = 0;
    $total_periksa_radiologi_ranap = 0;
    $jumlah_total_operasi = 0;
    $jumlah_total_obat_operasi = 0;
    $poliklinik = ['registrasi' => 0, 'registrasilama' => 0, 'stts_daftar' => ''];
    $ranap = ['biaya_reg' => 0];
    $pacs = [];
    $orthanc = $this->settings->get('orthanc.server');

    $berkas_digital = $this->db('berkas_digital_perawatan')
      ->join('master_berkas_digital', 'master_berkas_digital.kode=berkas_digital_perawatan.kode')
      ->where('berkas_digital_perawatan.no_rawat', $this->revertNorawat($id))
      ->notLike('lokasi_file','%pdf')
      ->asc('master_berkas_digital.nama')
      ->toArray();

    $berkas_digital_pdf = $this->db('berkas_digital_perawatan')
      ->join('master_berkas_digital', 'master_berkas_digital.kode=berkas_digital_perawatan.kode')
      ->where('berkas_digital_perawatan.no_rawat', $this->revertNorawat($id))
      ->where('berkas_digital_perawatan.kode','!=' ,'001')
      ->like('lokasi_file','%pdf')
      ->asc('master_berkas_digital.nama')
      ->toArray();

    $berkas_sep_pdf = $this->db('berkas_digital_perawatan')
      ->join('master_berkas_digital', 'master_berkas_digital.kode=berkas_digital_perawatan.kode')
      ->where('berkas_digital_perawatan.no_rawat', $this->revertNorawat($id))
      ->where('berkas_digital_perawatan.kode','=', '001')
      ->like('lokasi_file','%pdf')
      ->asc('master_berkas_digital.nama')
      ->toArray();

    $no_rawat = $this->revertNorawat($id);

    $check_billing = $this->db()->pdo()->query("SHOW TABLES LIKE 'billing'");
    $check_billing->execute();
    $check_billing = $check_billing->fetch();

    if($check_billing) {
      $query = $this->db()->pdo()->prepare("select no,nm_perawatan,pemisah,if(biaya=0,'',biaya),if(jumlah=0,'',jumlah),if(tambahan=0,'',tambahan),if(totalbiaya=0,'',totalbiaya),totalbiaya from billing where no_rawat='$no_rawat'");
      $query->execute();
      $rows = $query->fetchAll();
      $total = 0;
      foreach ($rows as $key => $value) {
        $total = $total + $value['7'];
      }
      $total = $total;
    } else {
      $rows = [];
      $total = '';
    }

    $this->tpl->set('total', $total);

    $lengkap = $this->db('mlite_vedika')
           ->where('mlite_vedika.no_rawat', $no_rawat)
           ->oneArray();
    $this->tpl->set('lengkap', $lengkap);
    
    $instansi['logo'] = $this->settings->get('settings.logo');
    $instansi['nama_instansi'] = $this->settings->get('settings.nama_instansi');
    $instansi['alamat'] = $this->settings->get('settings.alamat');
    $instansi['kota'] = $this->settings->get('settings.kota');
    $instansi['propinsi'] = $this->settings->get('settings.propinsi');
    $instansi['nomor_telepon'] = $this->settings->get('settings.nomor_telepon');
    $instansi['email'] = $this->settings->get('settings.email');

    $this->tpl->set('billing', $rows);

    /* Menggunakan billing bawaan mLITE */

    if($this->settings->get('vedika.billing') == 'mlite') {
        $settings = $this->settings('settings');
        $this->tpl->set('settings', $this->tpl->noParse_array(htmlspecialchars_array($settings)));

       $reg_periksa = $this->db('reg_periksa')->where('no_rawat', $no_rawat)->oneArray();
       if($reg_periksa['status_lanjut'] == 'Ralan') {
          $result_detail['billing'] = $this->db('mlite_billing')->where('no_rawat', $no_rawat)->like('kd_billing', 'RJ%')->desc('id_billing')->oneArray();
          $billingUserId = isset($result_detail['billing']['id_user']) ? $result_detail['billing']['id_user'] : null;
          $result_detail['fullname'] = $billingUserId
            ? $this->core->getUserInfo('fullname', $billingUserId, true)
            : '';

          $result_detail['poliklinik'] = $this->db('poliklinik')
            ->join('reg_periksa', 'reg_periksa.kd_poli = poliklinik.kd_poli')
            ->where('reg_periksa.no_rawat', $no_rawat)
            ->oneArray();

          $poliklinik = $this->db('poliklinik')
            ->join('reg_periksa', 'reg_periksa.kd_poli=poliklinik.kd_poli')
            ->where('no_rawat', $no_rawat)
            ->oneArray();
          if($poliklinik['stts_daftar'] == 'Lama') {
            $poliklinik['registrasi'] = $poliklinik['registrasilama'];
          }


          $result_detail['rawat_jl_dr'] = $this->db('rawat_jl_dr')
            ->select('jns_perawatan.nm_perawatan')
            ->select(['biaya_rawat' => 'rawat_jl_dr.biaya_rawat'])
            ->select(['jml' => 'COUNT(rawat_jl_dr.kd_jenis_prw)'])
            ->select(['total_biaya_rawat_dr' => 'SUM(rawat_jl_dr.biaya_rawat)'])
            ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw = rawat_jl_dr.kd_jenis_prw')
            ->where('rawat_jl_dr.no_rawat', $no_rawat)
            ->group('jns_perawatan.nm_perawatan')
            ->toArray();

          $total_rawat_jl_dr = 0;
          foreach ($result_detail['rawat_jl_dr'] as $row) {
            $total_rawat_jl_dr += $row['total_biaya_rawat_dr'];
          }

          $result_detail['rawat_jl_pr'] = $this->db('rawat_jl_pr')
            ->select('jns_perawatan.nm_perawatan')
            ->select(['biaya_rawat' => 'rawat_jl_pr.biaya_rawat'])
            ->select(['jml' => 'COUNT(rawat_jl_pr.kd_jenis_prw)'])
            ->select(['total_biaya_rawat_pr' => 'SUM(rawat_jl_pr.biaya_rawat)'])
            ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw = rawat_jl_pr.kd_jenis_prw')
            ->where('rawat_jl_pr.no_rawat', $no_rawat)
            ->group('jns_perawatan.nm_perawatan')
            ->toArray();

          $total_rawat_jl_pr = 0;
          foreach ($result_detail['rawat_jl_pr'] as $row) {
            $total_rawat_jl_pr += $row['total_biaya_rawat_pr'];
          }

          $result_detail['rawat_jl_drpr'] = $this->db('rawat_jl_drpr')
            ->select('jns_perawatan.nm_perawatan')
            ->select(['biaya_rawat' => 'rawat_jl_drpr.biaya_rawat'])
            ->select(['jml' => 'COUNT(rawat_jl_drpr.kd_jenis_prw)'])
            ->select(['total_biaya_rawat_drpr' => 'SUM(rawat_jl_drpr.biaya_rawat)'])
            ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw = rawat_jl_drpr.kd_jenis_prw')
            ->where('rawat_jl_drpr.no_rawat', $no_rawat)
            ->group('jns_perawatan.nm_perawatan')
            ->toArray();

          $total_rawat_jl_drpr = 0;
          foreach ($result_detail['rawat_jl_drpr'] as $row) {
            $total_rawat_jl_drpr += $row['total_biaya_rawat_drpr'];
          }

          $result_detail['detail_pemberian_obat'] = $this->db('detail_pemberian_obat')
            ->join('databarang', 'databarang.kode_brng=detail_pemberian_obat.kode_brng')
            ->where('no_rawat', $no_rawat)
            ->where('detail_pemberian_obat.status', 'Ralan')
            ->toArray();

          $total_detail_pemberian_obat = 0;
          foreach ($result_detail['detail_pemberian_obat'] as $row) {
            $total_detail_pemberian_obat += $row['total'];
          }

          $result_detail['periksa_lab'] = $this->db('periksa_lab')
            ->select('jns_perawatan_lab.nm_perawatan') 
            ->select('periksa_lab.biaya')  
            ->select('periksa_lab.kd_jenis_prw')          
            ->join('jns_perawatan_lab', 'jns_perawatan_lab.kd_jenis_prw=periksa_lab.kd_jenis_prw')
            ->where('periksa_lab.no_rawat', $no_rawat)
            ->where('periksa_lab.status', 'Ralan')
            ->where('periksa_lab.biaya', '!=','0')
            ->toArray();

          $result_detail['detail_periksa_lab'] = $this->db('detail_periksa_lab')
            ->select('jns_perawatan_lab.nm_perawatan') 
            ->select(['biaya' => 'SUM(detail_periksa_lab.bagian_dokter)'])
            ->select('detail_periksa_lab.kd_jenis_prw') 
            ->join('jns_perawatan_lab', 'jns_perawatan_lab.kd_jenis_prw=detail_periksa_lab.kd_jenis_prw')
            ->where('detail_periksa_lab.no_rawat', $no_rawat)
            ->where('detail_periksa_lab.bagian_dokter', '!=','0')
            ->group('detail_periksa_lab.kd_jenis_prw')
            ->toArray();

          $total_periksa_lab = 0;
          foreach (array_merge($result_detail['periksa_lab'], $result_detail['detail_periksa_lab']) as $row) {
            $total_periksa_lab += $row['biaya'];
          }

          $result_detail['periksa_radiologi'] = $this->db('periksa_radiologi')
            ->join('jns_perawatan_radiologi', 'jns_perawatan_radiologi.kd_jenis_prw=periksa_radiologi.kd_jenis_prw')
            ->where('no_rawat', $no_rawat)
            // ->where('periksa_radiologi.status', 'Ralan')
            ->toArray();

          $total_periksa_radiologi = 0;
          foreach ($result_detail['periksa_radiologi'] as $row) {
            $total_periksa_radiologi += $row['biaya'];
          }

          $jumlah_total_operasi = 0;
          $operasis = $this->db('operasi')->join('paket_operasi', 'paket_operasi.kode_paket=operasi.kode_paket')->where('no_rawat', $no_rawat)->where('operasi.status', 'Ralan')->toArray();
          $result_detail['operasi'] = [];
          foreach ($operasis as $operasi) {
            $operasi['jumlah'] = $operasi['biayaoperator1']+$operasi['biayaoperator2']+$operasi['biayaoperator3']+$operasi['biayaasisten_operator1']+$operasi['biayaasisten_operator2']+$operasi['biayadokter_anak']+$operasi['biayaperawaat_resusitas']+$operasi['biayadokter_anestesi']+$operasi['biayaasisten_anestesi']+$operasi['biayabidan']+$operasi['biayaperawat_luar']+$operasi['sarpras'];
            $jumlah_total_operasi += $operasi['jumlah'];
            $result_detail['operasi'][] = $operasi;
          }
          $jumlah_total_obat_operasi = 0;
          $obat_operasis = $this->db('beri_obat_operasi')->join('obatbhp_ok', 'obatbhp_ok.kd_obat=beri_obat_operasi.kd_obat')->where('no_rawat', $no_rawat)->toArray();
          $result_detail['obat_operasi'] = [];
          foreach ($obat_operasis as $obat_operasi) {
            $obat_operasi['harga'] = $obat_operasi['hargasatuan'] * $obat_operasi['jumlah'];
            $jumlah_total_obat_operasi += $obat_operasi['harga'];
            $result_detail['obat_operasi'][] = $obat_operasi;
          }

       } else {

         $result_detail['billing'] = $this->db('mlite_billing')->where('no_rawat', $no_rawat)->like('kd_billing', 'RI%')->desc('id_billing')->oneArray();
         $billingUserId = isset($result_detail['billing']['id_user']) ? $result_detail['billing']['id_user'] : null;
         $result_detail['fullname'] = $billingUserId
           ? $this->core->getUserInfo('fullname', $billingUserId, true)
           : '';

         $result_detail['kamar_inap'] = $this->db('kamar_inap')
           ->join('reg_periksa', 'reg_periksa.no_rawat = kamar_inap.no_rawat')
           ->where('reg_periksa.no_rawat', $no_rawat)
           ->oneArray();

         $result_detail['rawat_jl_dr'] = $this->db('rawat_jl_dr')
            ->select('jns_perawatan.nm_perawatan')
            ->select(['biaya_rawat' => 'rawat_jl_dr.biaya_rawat'])
            ->select(['jml' => 'COUNT(rawat_jl_dr.kd_jenis_prw)'])
            ->select(['total_biaya_rawat_dr' => 'SUM(rawat_jl_dr.biaya_rawat)'])
            ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw = rawat_jl_dr.kd_jenis_prw')
            ->where('rawat_jl_dr.no_rawat', $no_rawat)
            ->group('jns_perawatan.nm_perawatan')
            ->toArray();

          $total_rawat_jl_dr = 0;
          foreach ($result_detail['rawat_jl_dr'] as $row) {
            $total_rawat_jl_dr += $row['total_biaya_rawat_dr'];
          }

          $result_detail['rawat_jl_pr'] = $this->db('rawat_jl_pr')
            ->select('jns_perawatan.nm_perawatan')
            ->select(['biaya_rawat' => 'rawat_jl_pr.biaya_rawat'])
            ->select(['jml' => 'COUNT(rawat_jl_pr.kd_jenis_prw)'])
            ->select(['total_biaya_rawat_pr' => 'SUM(rawat_jl_pr.biaya_rawat)'])
            ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw = rawat_jl_pr.kd_jenis_prw')
            ->where('rawat_jl_pr.no_rawat', $no_rawat)
            ->group('jns_perawatan.nm_perawatan')
            ->toArray();

          $total_rawat_jl_pr = 0;
          foreach ($result_detail['rawat_jl_pr'] as $row) {
            $total_rawat_jl_pr += $row['total_biaya_rawat_pr'];
          }

          $result_detail['rawat_jl_drpr'] = $this->db('rawat_jl_drpr')
            ->select('jns_perawatan.nm_perawatan')
            ->select(['biaya_rawat' => 'rawat_jl_drpr.biaya_rawat'])
            ->select(['jml' => 'COUNT(rawat_jl_drpr.kd_jenis_prw)'])
            ->select(['total_biaya_rawat_drpr' => 'SUM(rawat_jl_drpr.biaya_rawat)'])
            ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw = rawat_jl_drpr.kd_jenis_prw')
            ->where('rawat_jl_drpr.no_rawat', $no_rawat)
            ->group('jns_perawatan.nm_perawatan')
            ->toArray();

          $total_rawat_jl_drpr = 0;
          foreach ($result_detail['rawat_jl_drpr'] as $row) {
            $total_rawat_jl_drpr += $row['total_biaya_rawat_drpr'];
          }

          $ranap = $this->db('kamar_inap')
            ->join('reg_periksa', 'reg_periksa.no_rawat=kamar_inap.no_rawat')
            ->join('poliklinik','poliklinik.kd_poli=reg_periksa.kd_poli')
            ->where('reg_periksa.no_rawat', $no_rawat)
            // ->where('kamar_inap.stts_pulang', '!=','Pindah Kamar')
            ->oneArray();

           $result_detail['biaya_ranap'] = $this->db('kamar_inap')
             ->where('kamar_inap.no_rawat', $no_rawat)
             ->desc('tgl_keluar')
            //  ->limit('1')
             ->toArray();
 
             $total_biaya_kamarinap = 0;
            foreach ($result_detail['biaya_ranap'] as $row) {
             $total_biaya_kamarinap += $row['ttl_biaya'];
            }
          
         $result_detail['rawat_inap_dr'] = $this->db('rawat_inap_dr')
           ->select('jns_perawatan_inap.nm_perawatan')
           ->select(['biaya_rawat' => 'rawat_inap_dr.biaya_rawat'])
           ->select(['jml' => 'COUNT(rawat_inap_dr.kd_jenis_prw)'])
           ->select(['total_biaya_rawat_dr' => 'SUM(rawat_inap_dr.biaya_rawat)'])
           ->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw = rawat_inap_dr.kd_jenis_prw')
           ->where('rawat_inap_dr.no_rawat', $no_rawat)
           ->group('jns_perawatan_inap.nm_perawatan')
           ->toArray();

           $total_rawat_inap_dr = 0;
          foreach ($result_detail['rawat_inap_dr'] as $row) {
            $total_rawat_inap_dr += $row['total_biaya_rawat_dr'];
          }

         $result_detail['rawat_inap_pr'] = $this->db('rawat_inap_pr')
           ->select('jns_perawatan_inap.nm_perawatan')
           ->select(['biaya_rawat' => 'rawat_inap_pr.biaya_rawat'])
           ->select(['jml' => 'COUNT(rawat_inap_pr.kd_jenis_prw)'])
           ->select(['total_biaya_rawat_pr' => 'SUM(rawat_inap_pr.biaya_rawat)'])
           ->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw = rawat_inap_pr.kd_jenis_prw')
           ->where('rawat_inap_pr.no_rawat', $no_rawat)
           ->group('jns_perawatan_inap.nm_perawatan')
           ->toArray();

           $total_rawat_inap_pr = 0;
          foreach ($result_detail['rawat_inap_pr'] as $row) {
            $total_rawat_inap_pr += $row['total_biaya_rawat_pr'];
          }

         $result_detail['rawat_inap_drpr'] = $this->db('rawat_inap_drpr')
           ->select('jns_perawatan_inap.nm_perawatan')
           ->select(['biaya_rawat' => 'rawat_inap_drpr.biaya_rawat'])
           ->select(['jml' => 'COUNT(rawat_inap_drpr.kd_jenis_prw)'])
           ->select(['total_biaya_rawat_drpr' => 'SUM(rawat_inap_drpr.biaya_rawat)'])
           ->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw = rawat_inap_drpr.kd_jenis_prw')
           ->where('rawat_inap_drpr.no_rawat', $no_rawat)
           ->group('jns_perawatan_inap.nm_perawatan')
           ->toArray();

          $total_rawat_inap_drpr = 0;
          foreach ($result_detail['rawat_inap_drpr'] as $row) {
            $total_rawat_inap_drpr += $row['total_biaya_rawat_drpr'];
          }

         $result_detail['detail_pemberian_obat_ranap'] = $this->db('detail_pemberian_obat')
           ->join('databarang', 'databarang.kode_brng=detail_pemberian_obat.kode_brng')
           ->where('no_rawat', $no_rawat)
           // ->where('detail_pemberian_obat.status', 'Ranap')
           ->toArray();

          $total_detail_pemberian_obat_ranap = 0;
          foreach ($result_detail['detail_pemberian_obat_ranap'] as $row) {
            $total_detail_pemberian_obat_ranap += $row['total'];
          }

         $result_detail['periksa_lab_ranap'] = $this->db('periksa_lab')
            ->select('jns_perawatan_lab.nm_perawatan') 
            ->select('periksa_lab.biaya')  
            ->select('periksa_lab.kd_jenis_prw')          
            ->join('jns_perawatan_lab', 'jns_perawatan_lab.kd_jenis_prw=periksa_lab.kd_jenis_prw')
            ->where('periksa_lab.no_rawat', $no_rawat)
            // ->where('periksa_lab.status', 'Ranap')
            ->where('periksa_lab.biaya', '!=','0')
            ->toArray();

          $result_detail['detail_periksa_lab_ranap'] = $this->db('detail_periksa_lab')
            ->select('jns_perawatan_lab.nm_perawatan') 
            ->select(['biaya' => 'SUM(detail_periksa_lab.bagian_dokter)'])
            ->select('detail_periksa_lab.kd_jenis_prw') 
            ->join('jns_perawatan_lab', 'jns_perawatan_lab.kd_jenis_prw=detail_periksa_lab.kd_jenis_prw')
            ->where('detail_periksa_lab.no_rawat', $no_rawat)
            ->where('detail_periksa_lab.bagian_dokter', '!=','0')
            ->group('detail_periksa_lab.kd_jenis_prw')
            ->toArray();

          $total_periksa_lab_ranap = 0;
          foreach (array_merge($result_detail['periksa_lab_ranap'], $result_detail['detail_periksa_lab_ranap']) as $row) {
            $total_periksa_lab_ranap += $row['biaya'];
          }

         $result_detail['periksa_radiologi_ranap'] = $this->db('periksa_radiologi')
           ->join('jns_perawatan_radiologi', 'jns_perawatan_radiologi.kd_jenis_prw=periksa_radiologi.kd_jenis_prw')
           ->where('no_rawat', $no_rawat)
           // ->where('periksa_radiologi.status', 'Ranap')
           ->toArray();

          $total_periksa_radiologi_ranap = 0;
          foreach ($result_detail['periksa_radiologi_ranap'] as $row) {
            $total_periksa_radiologi_ranap += $row['biaya'];
          }
    
         $result_detail['tambahan_biaya'] = $this->db('tambahan_biaya')
           //->where('status', 'ranap')
           ->where('no_rawat', $no_rawat)
           ->toArray();

         $jumlah_total_operasi = 0;
         $operasis = $this->db('operasi')
         ->join('paket_operasi', 'paket_operasi.kode_paket=operasi.kode_paket')
         ->where('no_rawat', $no_rawat)
         // ->where('operasi.status', 'Ranap')
         ->toArray();
         $result_detail['operasi'] = [];
         foreach ($operasis as $operasi) {
           $operasi['jumlah'] = $operasi['biayaoperator1']+$operasi['biayaoperator2']+$operasi['biayaoperator3']+$operasi['biayaasisten_operator1']+$operasi['biayaasisten_operator2']+$operasi['biayadokter_anak']+$operasi['biayaperawaat_resusitas']+$operasi['biayadokter_anestesi']+$operasi['biayaasisten_anestesi']+$operasi['biayabidan']+$operasi['biayaperawat_luar']+$operasi['sarpras'];
           $jumlah_total_operasi += $operasi['jumlah'];
           $result_detail['operasi'][] = $operasi;
         }
         $jumlah_total_obat_operasi = 0;
         $obat_operasis = $this->db('beri_obat_operasi')->join('obatbhp_ok', 'obatbhp_ok.kd_obat=beri_obat_operasi.kd_obat')->where('no_rawat', $no_rawat)->toArray();
         $result_detail['obat_operasi'] = [];
         foreach ($obat_operasis as $obat_operasi) {
           $obat_operasi['harga'] = $obat_operasi['hargasatuan'] * $obat_operasi['jumlah'];
           $jumlah_total_obat_operasi += $obat_operasi['harga'];
           $result_detail['obat_operasi'][] = $obat_operasi;
         }

       }

       $this->tpl->set('billing', $result_detail);

    }

    /* End menggunakan billing bawaan mlITE */

    $this->tpl->set('instansi', $instansi);

    $print_sep = array();
    if (!empty($this->_getSEPInfo('no_sep', $no_rawat))) {
      $print_sep['bridging_sep'] = $this->db('bridging_sep')->where('no_sep', $this->_getSEPInfo('no_sep', $no_rawat))->oneArray();
      $print_sep['bpjs_prb'] = $this->db('bpjs_prb')->where('no_sep', $this->_getSEPInfo('no_sep', $no_rawat))->oneArray();
      $batas_rujukan = $this->db('bridging_sep')->select('DATE_ADD(tglrujukan , INTERVAL 85 DAY) AS batas_rujukan')->where('no_sep', $this->_getSEPInfo('no_sep', $no_rawat))->oneArray();
      $print_sep['batas_rujukan'] = $batas_rujukan['batas_rujukan'];
      switch ($print_sep['bridging_sep']['klsnaik']) {
        case '2':
          $print_sep['kelas_naik'] = 'Kelas VIP';
          break;
        case '3':
          $print_sep['kelas_naik'] = 'Kelas 1';
          break;
        case '4':
          $print_sep['kelas_naik'] = 'Kelas 2';
          break;

        default:
          $print_sep['kelas_naik'] = "";
          break;
      }
    }
    $print_sep['nama_instansi'] = $this->settings->get('settings.nama_instansi');
    $print_sep['logoURL'] = url(MODULES . '/vclaim/img/bpjslogo.png');
    $this->tpl->set('print_sep', $print_sep);
    
    $dpjp_sep_row = $this->db('bridging_sep')
      ->select('nmdpdjp')
      ->where('no_sep', $this->_getSEPInfo('no_sep', $no_rawat))
      ->oneArray();
    
    $dpjp_sep = '';
    
    if ($dpjp_sep_row && isset($dpjp_sep_row['nmdpdjp'])) {
      $dpjp_sep = $dpjp_sep_row['nmdpdjp'];
    }
    
    $this->tpl->set('dpjp_sep', $dpjp_sep);
    
    $permintaan_ranap = $this->db('permintaan_ranap')
    ->where('no_rawat', $this->revertNorawat($id))
    ->join('dokter', 'dokter.kd_dokter=permintaan_ranap.kd_dpjp')
    ->oneArray();
    $this->tpl->set('permintaan_ranap', $permintaan_ranap);

    $rujukan_ranap = $this->db('rujuk')
    ->where('no_rawat', $this->revertNorawat($id))
    ->join('dokter', 'dokter.kd_dokter=rujuk.kd_dokter')
    ->oneArray();
    $this->tpl->set('rujukan_ranap', $rujukan_ranap);

    $cek_spri = $this->db('bridging_surat_pri_bpjs')
    ->where('no_rawat', $this->revertNorawat($id))
    ->oneArray();
    $this->tpl->set('cek_spri', $cek_spri);

    $print_spri = array();
    if (!empty($this->_getSPRIInfo('no_surat', $no_rawat))) {
      $print_spri['bridging_surat_pri_bpjs'] = $this->db('bridging_surat_pri_bpjs')->where('no_surat', $this->_getSPRIInfo('no_surat', $no_rawat))->oneArray();
    }
    $print_spri['nama_instansi'] = $this->settings->get('settings.nama_instansi');
    $print_spri['logoURL'] = url(MODULES . '/vclaim/img/bpjslogo.png');
    $this->tpl->set('print_spri', $print_spri);

    $resume_pasien = $this->db('resume_pasien_ranap')
      ->join('dokter', 'dokter.kd_dokter = resume_pasien_ranap.kd_dokter')
      ->where('no_rawat', $this->revertNorawat($id))
      ->oneArray();
      
    if(!$this
    ->db('resume_pasien_ranap')
    ->where('no_rawat', $this->revertNorawat($id))
    ->oneArray()) {
      $resume_pasien = $this->db('resume_pasien')
        ->join('dokter', 'dokter.kd_dokter = resume_pasien.kd_dokter')
        ->where('no_rawat', $this->revertNorawat($id))
        ->oneArray();
    }
    $this->tpl->set('resume_pasien', $resume_pasien);

    $asesmen_medis_igd = $this->db('asesmen_medis_igd')
      ->join('dokter', 'dokter.kd_dokter = asesmen_medis_igd.kd_dokter')
      ->where('no_rawat', $this->revertNorawat($id))
      ->oneArray();

    $this->tpl->set('asesmen_medis_igd', $asesmen_medis_igd);

    $triase_igd = $this->db('data_triase_igd')
      ->join('master_triase_macam_kasus', 'master_triase_macam_kasus.kode_kasus = data_triase_igd.kode_kasus')
      ->where('no_rawat', $this->revertNorawat($id))
      ->oneArray();

    $this->tpl->set('triase_igd', $triase_igd);

    $triaseprimer = $this->db('data_triase_igdprimer')
      ->where('no_rawat', $this->revertNorawat($id))
      ->oneArray();

    $this->tpl->set('triaseprimer', $triaseprimer);
  
    $triasesekunder = $this->db('data_triase_igdsekunder')
      ->where('no_rawat', $this->revertNorawat($id))
      ->oneArray();

    $this->tpl->set('triasesekunder', $triasesekunder);

    $skala1 = $this->db('data_triase_igddetail_skala1')
    ->where('no_rawat', $this->revertNorawat($id))
    ->oneArray();

    $this->tpl->set('skala1', $skala1);

    $skala2 = $this->db('data_triase_igddetail_skala2')
    ->where('no_rawat', $this->revertNorawat($id))
    ->oneArray();

    $this->tpl->set('skala2', $skala2);

    $skala3 = $this->db('data_triase_igddetail_skala3')
    ->where('no_rawat', $this->revertNorawat($id))
    ->oneArray();

    $this->tpl->set('skala3', $skala3);

    $skala4 = $this->db('data_triase_igddetail_skala4')
    ->where('no_rawat', $this->revertNorawat($id))
    ->oneArray();

    $this->tpl->set('skala4', $skala4);

    $skala5 = $this->db('data_triase_igddetail_skala5')
    ->where('no_rawat', $this->revertNorawat($id))
    ->oneArray();

    $this->tpl->set('skala5', $skala5);   

    $pasien = $this->db('pasien')
      ->join('kecamatan', 'kecamatan.kd_kec = pasien.kd_kec')
      ->join('kabupaten', 'kabupaten.kd_kab = pasien.kd_kab')
      ->where('no_rkm_medis', $this->getRegPeriksaInfo('no_rkm_medis', $this->revertNorawat($id)))
      ->oneArray();
    $reg_periksa = $this->db('reg_periksa')
      ->join('dokter', 'dokter.kd_dokter = reg_periksa.kd_dokter')
      ->join('poliklinik', 'poliklinik.kd_poli = reg_periksa.kd_poli')
      ->join('penjab', 'penjab.kd_pj = reg_periksa.kd_pj')
      ->where('stts', '<>', 'Batal')
      ->where('no_rawat', $this->revertNorawat($id))
      ->oneArray();
    $rows_dpjp_ranap = $this->db('dpjp_ranap')
      ->join('dokter', 'dokter.kd_dokter = dpjp_ranap.kd_dokter')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();
    $dpjp_i = 1;
    $dpjp_ranap = [];
    foreach ($rows_dpjp_ranap as $row) {
      $row['nomor'] = $dpjp_i++;
      $dpjp_ranap[] = $row;
    }
    /*
    $rujukan_internal = $this->db('rujukan_internal_poli')
      ->join('poliklinik', 'poliklinik.kd_poli = rujukan_internal_poli.kd_poli')
      ->join('dokter', 'dokter.kd_dokter = rujukan_internal_poli.kd_dokter')
      ->where('no_rawat', $this->revertNorawat($id))
      ->oneArray();
    */
    $diagnosa_pasien = $this->db('diagnosa_pasien')
      ->join('penyakit', 'penyakit.kd_penyakit = diagnosa_pasien.kd_penyakit')
      ->where('no_rawat', $this->revertNorawat($id))
      ->where('diagnosa_pasien.status', 'Ralan')
      ->asc('prioritas')
      ->toArray();
    if($reg_periksa['status_lanjut'] == 'Ranap'){
      $diagnosa_pasien = $this->db('diagnosa_pasien')
        ->join('penyakit', 'penyakit.kd_penyakit = diagnosa_pasien.kd_penyakit')
        ->where('no_rawat', $this->revertNorawat($id))
        ->where('diagnosa_pasien.status', 'Ranap')
        ->asc('prioritas')
        ->toArray();
    }

    $prosedur_pasien = $this->db('prosedur_pasien')
      ->join('icd9', 'icd9.kode = prosedur_pasien.kode')
      ->where('no_rawat', $this->revertNorawat($id))
      ->where('status', 'Ralan')
      ->asc('prioritas')
      ->toArray();
      if($reg_periksa['status_lanjut'] == 'Ranap'){
    $prosedur_pasien = $this->db('prosedur_pasien')
      ->join('icd9', 'icd9.kode = prosedur_pasien.kode')
      ->where('no_rawat', $this->revertNorawat($id))
      ->where('status', 'Ranap')
      ->asc('prioritas')
      ->toArray();
      }

    $pemeriksaan_ralan = $this->db('pemeriksaan_ralan')
      ->where('no_rawat', $this->revertNorawat($id))
      ->asc('tgl_perawatan')
      ->asc('jam_rawat')
      ->toArray();
    $pemeriksaan_rehab = $this->db('pemeriksaan_ralan_rehab')
      ->select('pemeriksaan_ralan_rehab.*')
      ->select('pegawai.*')
      ->where('no_rawat', $this->revertNorawat($id))
      ->join('pegawai', 'pemeriksaan_ralan_rehab.nik=pegawai.nik')
      ->asc('tgl_perawatan')
      ->asc('jam_rawat')
      ->oneArray();
    $frekuensi_kunjungan = $this->db('kunjungan_fisio_rehab')
      ->where('no_rawat', $this->revertNorawat($id))
      ->oneArray();
    $uji_fungsi_kfr = $this->db('uji_fungsi_kfr')
      ->where('no_rawat', $this->revertNorawat($id))
      ->asc('tanggal')
      ->oneArray();    
    $pre_uji_fungsi_kfr = $this->db('uji_fungsi_kfr')
      ->select('uji_fungsi_kfr.*')
      ->join('reg_periksa', 'uji_fungsi_kfr.no_rawat=reg_periksa.no_rawat')
      ->where('no_rkm_medis', $this->getRegPeriksaInfo('no_rkm_medis', $this->revertNorawat($id)))
      ->desc('tanggal')
      ->limit('1')
      ->oneArray();
    $pemeriksaan_ranap = $this->db('pemeriksaan_ranap')
      ->where('no_rawat', $this->revertNorawat($id))
      ->asc('tgl_perawatan')
      ->asc('jam_rawat')
      ->toArray();
    
    foreach ($pemeriksaan_ranap as &$pr) {
      if (!isset($pr['pemeriksaan']) || $pr['pemeriksaan'] === '') continue;
    
      $s = $pr['pemeriksaan'];
    
      // NBSP (dua kemungkinan)
      $s = str_replace(["\xC2\xA0", "\xA0"], ' ', $s);
    
      // rapikan newline
      $s = str_replace(["\r\n", "\r"], "\n", $s);
    
      // buang control char aneh (kecuali tab/newline)
      $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $s);
    
      // paksa jadi UTF-8 valid
      if (function_exists('mb_convert_encoding')) {
        $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
      }
    
      // opsional: kalau memang ada char � tersimpan, ganti jadi spasi
      $s = str_replace("�", " ", $s);
    
      $pr['pemeriksaan'] = trim($s);
    }
    unset($pr);

    $resume_ranap = $this->db('resume_pasien_ranap')
      ->join('dokter', 'resume_pasien_ranap.kd_dokter=dokter.kd_dokter')
      ->where('no_rawat', $this->revertNorawat($id))
      ->oneArray();
    $rawat_jl_dr = $this->db('rawat_jl_dr')
      ->join('jns_perawatan', 'rawat_jl_dr.kd_jenis_prw=jns_perawatan.kd_jenis_prw')
      ->join('dokter', 'rawat_jl_dr.kd_dokter=dokter.kd_dokter')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();
    $rawat_jl_pr = $this->db('rawat_jl_pr')
      ->join('jns_perawatan', 'rawat_jl_pr.kd_jenis_prw=jns_perawatan.kd_jenis_prw')
      ->join('petugas', 'rawat_jl_pr.nip=petugas.nip')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();
    $rawat_jl_drpr = $this->db('rawat_jl_drpr')
      ->join('jns_perawatan', 'rawat_jl_drpr.kd_jenis_prw=jns_perawatan.kd_jenis_prw')
      ->join('dokter', 'rawat_jl_drpr.kd_dokter=dokter.kd_dokter')
      ->join('petugas', 'rawat_jl_drpr.nip=petugas.nip')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();
    $rawat_inap_dr = $this->db('rawat_inap_dr')
      ->join('jns_perawatan_inap', 'rawat_inap_dr.kd_jenis_prw=jns_perawatan_inap.kd_jenis_prw')
      ->join('dokter', 'rawat_inap_dr.kd_dokter=dokter.kd_dokter')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();
    $rawat_inap_pr = $this->db('rawat_inap_pr')
      ->join('jns_perawatan_inap', 'rawat_inap_pr.kd_jenis_prw=jns_perawatan_inap.kd_jenis_prw')
      ->join('petugas', 'rawat_inap_pr.nip=petugas.nip')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();
    $rawat_inap_drpr = $this->db('rawat_inap_drpr')
      ->join('jns_perawatan_inap', 'rawat_inap_drpr.kd_jenis_prw=jns_perawatan_inap.kd_jenis_prw')
      ->join('dokter', 'rawat_inap_drpr.kd_dokter=dokter.kd_dokter')
      ->join('petugas', 'rawat_inap_drpr.nip=petugas.nip')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();

    $kamar_inap = $this->db('kamar_inap')
      ->join('kamar', 'kamar_inap.kd_kamar=kamar.kd_kamar')
      ->join('bangsal', 'kamar.kd_bangsal=bangsal.kd_bangsal')
      ->where('no_rawat', $this->revertNorawat($id))
      ->asc('tgl_keluar')
    //   ->limit('1')
      ->toArray();

    $lama_inap = $this->db('kamar_inap')
      ->select(['lama' => 'SUM(kamar_inap.lama)'])
      ->where('no_rawat', $this->revertNorawat($id))
      ->desc('lama')
      ->limit('1')
      ->oneArray();

    $operasi = $this->db('operasi')
      ->join('paket_operasi', 'operasi.kode_paket=paket_operasi.kode_paket')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();
    $rujuk_igd = $this->db('rujuk_igd')
      ->join('dokter', 'dokter.kd_dokter=rujuk_igd.kd_dokter')
      ->where('no_rawat', $this->revertNorawat($id))
      ->oneArray();
    if ($rujuk_igd) {
      $rujuk_igd['anamnesa_pdf'] = $this->_cleanLongTextForPDF($this->_val($rujuk_igd, 'keluhan_utama'));
      $rujuk_igd['pemeriksaan_fisik_pdf'] = $this->_cleanLongTextForPDF($this->_val($rujuk_igd, 'jalannya_penyakit'));
    }
    $rujuk_ralan = $this->db('rujuk')
      ->select('rujuk.*')
      ->select('a.nm_dokter')
      ->join('dokter a', 'a.kd_dokter=rujuk.kd_dokter')
      ->where('no_rawat', $this->revertNorawat($id))
      ->oneArray(); 
    $rujuk_ranap = $this->db('rujuk_rawat_inap')
      ->select('rujuk_rawat_inap.*')
      ->select('a.nm_dokter')
      ->join('dokter a', 'a.kd_dokter=rujuk_rawat_inap.kd_dokter')
      ->where('no_rawat', $this->revertNorawat($id))
      ->oneArray();     
    $tindakan_radiologi = $this->db('periksa_radiologi')
      ->join('jns_perawatan_radiologi', 'periksa_radiologi.kd_jenis_prw=jns_perawatan_radiologi.kd_jenis_prw')
      ->join('dokter', 'periksa_radiologi.kd_dokter=dokter.kd_dokter')
      ->join('petugas', 'periksa_radiologi.nip=petugas.nip')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();
    $hasil_radiologi = $this->db('hasil_radiologi')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();
    $pemeriksaan_laboratorium = [];
    $rows_pemeriksaan_laboratorium = $this->db('periksa_lab')
      ->join('jns_perawatan_lab', 'jns_perawatan_lab.kd_jenis_prw=periksa_lab.kd_jenis_prw')
      ->where('no_rawat', $this->revertNorawat($id))
      ->asc('tgl_periksa')
      ->toArray();
    
    foreach ($rows_pemeriksaan_laboratorium as $value) {
    
      $value['detail_periksa_lab'] = $this->db('detail_periksa_lab')
        ->join('template_laboratorium', 'template_laboratorium.id_template=detail_periksa_lab.id_template')
        ->where('detail_periksa_lab.no_rawat', $value['no_rawat'])
        ->where('detail_periksa_lab.kd_jenis_prw', $value['kd_jenis_prw'])
        ->where('detail_periksa_lab.tgl_periksa', $value['tgl_periksa'])
        ->where('detail_periksa_lab.jam', $value['jam'])
        ->toArray();
    
      // ✅ FIX: normalisasi satuan (µL dll) + bersihin karakter aneh
      foreach ($value['detail_periksa_lab'] as &$d) {
        if (!empty($d['satuan'])) {
          $s = $d['satuan'];
    
          // NBSP (dua kemungkinan)
          $s = str_replace(["\xC2\xA0", "\xA0"], ' ', $s);
    
          // perbaiki µL yang rusak (�L) dan variasinya
          $s = str_replace(
            ['�L', '/�L', 'uL', 'u/L', 'µL'], // variasi umum
            ['µL', '/µL', 'µL', 'µ/L', 'µL'],
            $s
          );
    
          // buang control chars (kecuali newline/tab kalau ada)
          $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $s);
    
          // pastikan UTF-8 valid
          if (function_exists('mb_convert_encoding')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
          }
    
          $d['satuan'] = trim($s);
        }
    
        // opsional: kalau nilai juga suka ada karakter aneh
        if (!empty($d['nilai'])) {
          $n = $d['nilai'];
          $n = str_replace(["\xC2\xA0", "\xA0"], ' ', $n);
          $n = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $n);
          if (function_exists('mb_convert_encoding')) {
            $n = mb_convert_encoding($n, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
          }
          $d['nilai'] = trim($n);
        }
      }
      unset($d);
    
      $pemeriksaan_laboratorium[] = $value;
    }

    $pemberian_obat = $this->db('detail_pemberian_obat')
      ->join('databarang', 'detail_pemberian_obat.kode_brng=databarang.kode_brng')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();
    $obat_operasi = $this->db('beri_obat_operasi')
      ->join('obatbhp_ok', 'beri_obat_operasi.kd_obat=obatbhp_ok.kd_obat')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();
    $resep_pulang = $this->db('resep_pulang')
      ->join('databarang', 'resep_pulang.kode_brng=databarang.kode_brng')
      ->where('no_rawat', $this->revertNorawat($id))
      ->toArray();
    $laporan_operasi = $this->db('laporan_operasi')
      ->select('laporan_operasi.*')
      ->select('operasi.*')
      ->select('a.nm_dokter')
      ->select(['operator1' => 'a.nm_dokter'])
      ->select(['dokter_anak' => 'b.nm_dokter'])
      ->select(['dokter_anestesi' => 'c.nm_dokter'])
      ->select(['dokter_umum' => 'd.nm_dokter'])
      ->join('operasi', 'operasi.no_rawat=laporan_operasi.no_rawat')
      ->join('dokter a', 'a.kd_dokter=operasi.operator1')
      ->join('dokter b', 'b.kd_dokter=operasi.dokter_anak')
      ->join('dokter c', 'c.kd_dokter=operasi.dokter_anestesi')
      ->join('dokter d', 'd.kd_dokter=operasi.dokter_umum')
      ->where('laporan_operasi.no_rawat', $this->revertNorawat($id))
      ->group('laporan_operasi.no_rawat')
      ->oneArray();
      
    $laporan_operasi_ralan = $this->db('laporan_bedah')
      ->select('laporan_bedah.*')
      ->select('a.nm_dokter')
      ->select(['operator' => 'a.nm_dokter'])
      ->join('dokter a', 'a.kd_dokter=laporan_bedah.operator')
      ->where('laporan_bedah.no_rawat', $this->revertNorawat($id))
      ->group('laporan_bedah.no_rawat')
      ->oneArray();

    $this->tpl->set('total_biaya', 
    $total_rawat_jl_dr
    +$total_rawat_jl_pr
    +$total_rawat_jl_drpr
    +$total_detail_pemberian_obat
    +$total_periksa_lab
    +$total_periksa_radiologi
    +$jumlah_total_operasi
    +$jumlah_total_obat_operasi
    +$poliklinik['registrasi']);
    $this->tpl->set('total_biaya_ranap', 
    $total_biaya_kamarinap
    +$total_rawat_jl_dr
    +$total_rawat_jl_pr
    +$total_rawat_jl_drpr
    +$total_rawat_inap_dr
    +$total_rawat_inap_pr
    +$total_rawat_inap_drpr
    +$total_detail_pemberian_obat_ranap
    +$total_periksa_lab_ranap
    +$total_periksa_radiologi_ranap
    +$jumlah_total_operasi
    +$jumlah_total_obat_operasi
    +$ranap['biaya_reg']);
    $this->tpl->set('total_detail_pemberian_obat', $total_detail_pemberian_obat);
    $this->tpl->set('total_detail_pemberian_obat_ranap', $total_detail_pemberian_obat_ranap);
    $this->tpl->set('total_rawat_jl_dr', $total_rawat_jl_dr);
    $this->tpl->set('total_rawat_jl_pr', $total_rawat_jl_pr);
    $this->tpl->set('total_rawat_jl_drpr', $total_rawat_jl_drpr);
    $this->tpl->set('total_rawat_inap_dr', $total_rawat_inap_dr);
    $this->tpl->set('total_rawat_inap_pr', $total_rawat_inap_pr);
    $this->tpl->set('total_rawat_inap_drpr', $total_rawat_inap_drpr);
    $this->tpl->set('total_biaya_kamarinap', $total_biaya_kamarinap+$ranap['biaya_reg']);
    $this->tpl->set('total_periksa_lab', $total_periksa_lab);
    $this->tpl->set('total_periksa_radiologi', $total_periksa_radiologi);
    $this->tpl->set('total_periksa_lab_ranap', $total_periksa_lab_ranap);
    $this->tpl->set('total_periksa_radiologi_ranap', $total_periksa_radiologi_ranap);
    $this->tpl->set('jumlah_total_operasi', $jumlah_total_operasi);
    $this->tpl->set('jumlah_total_obat_operasi', $jumlah_total_obat_operasi);
    $this->tpl->set('pasien', $pasien);
    $this->tpl->set('reg_periksa', $reg_periksa);
    //$this->tpl->set('rujukan_internal', $rujukan_internal);
    $this->tpl->set('dpjp_ranap', $dpjp_ranap);
    $this->tpl->set('diagnosa_pasien', $diagnosa_pasien);
    $this->tpl->set('prosedur_pasien', $prosedur_pasien);
    $this->tpl->set('pemeriksaan_ralan', $pemeriksaan_ralan);
    $this->tpl->set('pemeriksaan_ranap', $pemeriksaan_ranap);
    $this->tpl->set('resume_ranap', $resume_ranap);
    $this->tpl->set('rawat_jl_dr', $rawat_jl_dr);
    $this->tpl->set('rawat_jl_pr', $rawat_jl_pr);
    $this->tpl->set('rawat_jl_drpr', $rawat_jl_drpr);
    $this->tpl->set('rawat_inap_dr', $rawat_inap_dr);
    $this->tpl->set('rawat_inap_pr', $rawat_inap_pr);
    $this->tpl->set('rawat_inap_drpr', $rawat_inap_drpr);
    $this->tpl->set('ranap', $ranap);
    $this->tpl->set('kamar_inap', $kamar_inap);
    $this->tpl->set('lama_inap', $lama_inap['lama']);
    $this->tpl->set('operasi', $operasi);
    $this->tpl->set('rujuk_ralan', $rujuk_ralan);
    $this->tpl->set('rujuk_ranap', $rujuk_ranap);
    $this->tpl->set('rujuk_igd', $rujuk_igd);
    $this->tpl->set('tindakan_radiologi', $tindakan_radiologi);
    $this->tpl->set('pemeriksaan_laboratorium', $pemeriksaan_laboratorium);
    $this->tpl->set('pemberian_obat', $pemberian_obat);
    $this->tpl->set('obat_operasi', $obat_operasi);
    $this->tpl->set('resep_pulang', $resep_pulang);
    $this->tpl->set('laporan_operasi', $laporan_operasi);
    $this->tpl->set('laporan_operasi_ralan', $laporan_operasi_ralan);

    $this->tpl->set('berkas_digital', $berkas_digital);
    $this->tpl->set('berkas_digital_pdf', $berkas_digital_pdf);
    $this->tpl->set('berkas_sep_pdf', $berkas_sep_pdf);

    $this->tpl->set('pacs', $pacs);
    $this->tpl->set('orthanc', $orthanc);
    // $this->tpl->set(name: 'tgl_hasil', value: $tgl_hasil);
    $this->tpl->set('hasil_radiologi', $this->db('hasil_radiologi')->where('no_rawat', $this->revertNorawat($id))->toArray());
    $this->tpl->set('gambar_radiologi', $this->db('gambar_radiologi')->where('no_rawat', $this->revertNorawat($id))->toArray());
    $this->tpl->set('vedika', htmlspecialchars_array($this->settings('vedika')));
    $this->tpl->set('pengaturan_billing', $this->settings->get('vedika.billing'));
    $this->tpl->set('pemeriksaan_rehab', $pemeriksaan_rehab);
    $this->tpl->set('kunjungan', $frekuensi_kunjungan);
    $this->tpl->set('uji_fungsi_kfr', $uji_fungsi_kfr);
    $this->tpl->set('pre_uji_fungsi_kfr', $pre_uji_fungsi_kfr);
    
    $no_sep_qr = $this->_bridgeVal($print_sep, 'no_sep', $this->_getSEPInfo('no_sep', $no_rawat));
    
    /*
     * 1. Dokter utama / DPJP sesuai kondisi template resume
     */
    $nama_dokter_ttd = $this->_getNamaDokterUtamaTTD($reg_periksa, $print_sep, $resume_ranap);
    
    $this->tpl->set('nama_dokter_ttd', $nama_dokter_ttd);
    $this->tpl->set(
      'qr_dokter_text',
      $this->_makeQRText(
        'Dokter Penanggung Jawab Pelayanan',
        $nama_dokter_ttd,
        $no_rawat,
        $no_sep_qr
      )
    );
    
    /*
     * 2. QR pasien untuk SEP / persetujuan pasien
     */
    $nama_pasien_qr = $this->_bridgeVal($print_sep, 'nama_pasien', $this->_val($pasien, 'nm_pasien'));
    $no_rm_qr       = $this->_bridgeVal($print_sep, 'nomr', $this->_val($pasien, 'no_rkm_medis'));
    
    $this->tpl->set('nama_pasien_qr', $nama_pasien_qr);
    $this->tpl->set(
      'qr_pasien_text',
      $this->_makeQRPasienText(
        $nama_pasien_qr,
        $no_rm_qr,
        $no_rawat,
        $no_sep_qr
      )
    );
    
    /*
     * 3. Rehab Medik / KFR
     */
    $nama_dokter_kfr = $this->_formatDokterKFR($this->_bridgeVal($print_sep, 'nmdpdjp'));
    $nama_tim_rehab  = $this->_val($pemeriksaan_rehab, 'nama');
    
    $this->tpl->set('nama_dokter_kfr', $nama_dokter_kfr);
    $this->tpl->set('nama_tim_rehab', $nama_tim_rehab);
    
    $this->tpl->set(
      'qr_dokter_kfr_text',
      $this->_makeQRText(
        'Dokter Penanggung Jawab Pelayanan Rehabilitasi Medik',
        $nama_dokter_kfr,
        $no_rawat,
        $no_sep_qr
      )
    );
    
    $this->tpl->set(
      'qr_tim_rehab_text',
      $this->_makeQRText(
        'Tim Rehabilitasi Medik / Fisioterapis',
        $nama_tim_rehab,
        $no_rawat,
        $no_sep_qr,
        'Tanggal Pemeriksaan: ' . $this->_val($pemeriksaan_rehab, 'tgl_perawatan')
      )
    );
    
    /*
     * 4. Laporan operasi ranap
     */
    $nama_laporan_operasi = $this->_val($laporan_operasi, 'nm_dokter');
    
    $this->tpl->set('nama_laporan_operasi', $nama_laporan_operasi);
    $this->tpl->set(
      'qr_laporan_operasi_text',
      $this->_makeQRText(
        'Dokter Penanggung Jawab Pelayanan / Operator',
        $nama_laporan_operasi,
        $no_rawat,
        $no_sep_qr
      )
    );
    
    /*
     * 5. Laporan operasi ralan
     */
    $nama_laporan_operasi_ralan = $this->_val($laporan_operasi_ralan, 'nm_dokter');
    
    $this->tpl->set('nama_laporan_operasi_ralan', $nama_laporan_operasi_ralan);
    $this->tpl->set(
      'qr_laporan_operasi_ralan_text',
      $this->_makeQRText(
        'Dokter Penanggung Jawab Pelayanan / Operator',
        $nama_laporan_operasi_ralan,
        $no_rawat,
        $no_sep_qr
      )
    );
    
    /*
     * 6. Rujuk ralan
     */
    $nama_rujuk_ralan = $this->_val($rujuk_ralan, 'nm_dokter');
    
    $this->tpl->set('nama_rujuk_ralan', $nama_rujuk_ralan);
    $this->tpl->set(
      'qr_rujuk_ralan_text',
      $this->_makeQRText(
        'Dokter Yang Merawat / Dokter Perujuk',
        $nama_rujuk_ralan,
        $no_rawat,
        $no_sep_qr
      )
    );
    
    /*
     * 7. Rujuk ranap
     */
    $nama_rujuk_ranap = $this->_val($rujuk_ranap, 'nm_dokter');
    
    $this->tpl->set('nama_rujuk_ranap', $nama_rujuk_ranap);
    $this->tpl->set(
      'qr_rujuk_ranap_text',
      $this->_makeQRText(
        'Dokter Yang Merawat / Dokter Perujuk',
        $nama_rujuk_ranap,
        $no_rawat,
        $no_sep_qr
      )
    );
    
    /*
     * 8. Permintaan ranap
     */
    $nama_permintaan_ranap = $this->_val($reg_periksa, 'nm_dokter');
    
    $this->tpl->set('nama_permintaan_ranap', $nama_permintaan_ranap);
    $this->tpl->set(
      'qr_permintaan_ranap_text',
      $this->_makeQRText(
        'Dokter Pengirim / Dokter Penanggung Jawab Pelayanan',
        $nama_permintaan_ranap,
        $no_rawat,
        $no_sep_qr
      )
    );
    
    /*
     * 9. Dokter SEP
     */
    $nama_dokter_sep = $this->_bridgeVal($print_sep, 'nmdpdjp');

    $this->tpl->set('nama_dokter_sep', $nama_dokter_sep);
    $this->tpl->set(
      'qr_dokter_sep_text',
      $this->_makeQRText(
        'Dokter Penanggung Jawab Pelayanan',
        $nama_dokter_sep,
        $no_rawat,
        $no_sep_qr
      )
    );
    
    /*
     * 10. Rujuk IGD
     */
    $nama_rujuk_igd = $this->_val($rujuk_igd, 'nm_dokter');
    
    $this->tpl->set('nama_rujuk_igd', $nama_rujuk_igd);
    $this->tpl->set(
      'qr_rujuk_igd_text',
      $this->_makeQRText(
        'Dokter Yang Merawat / Dokter Perujuk IGD',
        $nama_rujuk_igd,
        $no_rawat,
        $no_sep_qr
      )
    );

  return $this->tpl->draw(MODULES . '/vedika/view/admin/pdfklaim_generate.html', true);
 }
  
  public function getPDFKlaim($id)
  {
      echo $this->_renderPDFKlaimHTML($id);
      exit();
  }
  
  private function _registerPDFKlaim($no_rawat, $lokasi_file)
    {
      $kode = 'KLM'; // sesuaikan kalau pakai kode lain
    
      $master = $this->db('master_berkas_digital')
        ->where('kode', $kode)
        ->oneArray();
    
      if (!$master) {
        $this->db('master_berkas_digital')->save([
          'kode' => $kode,
          'nama' => 'PDF Klaim Vedika'
        ]);
      }
    
      $existing = $this->db('berkas_digital_perawatan')
        ->where('no_rawat', $no_rawat)
        ->where('kode', $kode)
        ->oneArray();
    
      if ($existing) {
    
        /*
         * Penting:
         * Jangan unlink kalau lokasi file lama sama dengan file baru,
         * karena file baru sudah overwrite file lama di path yang sama.
         */
        if (
          !empty($existing['lokasi_file']) &&
          $existing['lokasi_file'] != $lokasi_file
        ) {
          $oldFile = WEBAPPS_PATHX . '/berkasrawat/' . $existing['lokasi_file'];
    
          if (file_exists($oldFile)) {
            unlink($oldFile);
          }
        }
    
        return $this->db('berkas_digital_perawatan')
          ->where('no_rawat', $no_rawat)
          ->where('kode', $kode)
          ->save([
            'lokasi_file' => $lokasi_file
          ]);
      }
    
      return $this->db('berkas_digital_perawatan')->save([
        'no_rawat' => $no_rawat,
        'kode' => $kode,
        'lokasi_file' => $lokasi_file
      ]);
    }
  
    private function _createPDFKlaimFile($no_rawat)
    {
      $id = $this->convertNorawat($no_rawat);
    
      $vedika = $this->db('mlite_vedika')
        ->where('no_rawat', $no_rawat)
        ->oneArray();
    
      if (!$vedika) {
        return [
          'status' => false,
          'message' => 'Data Vedika tidak ditemukan',
          'no_rawat' => $no_rawat
        ];
      }
    
      if ($vedika['status'] != 'Pengajuan') {
        return [
          'status' => false,
          'message' => 'Status klaim belum Lengkap',
          'no_rawat' => $no_rawat
        ];
      }
    
      /*
       * Untuk awal, boleh tetap pakai _renderPDFKlaimHTML($id).
       * Tapi paling aman nanti arahkan ke template pdfklaim_generate.html
       * yang tidak memakai iframe.
       */
        $html = $this->_renderPDFKlaimGenerateHTML($id);
        
        // buang script
        $html = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $html);
        
        // buang link stylesheet eksternal
        $html = preg_replace('/<link\b[^>]*>/i', '', $html);
        
        // buang tombol cetak
        $html = preg_replace('/<a\b[^>]*id=["\']printPageButton["\'][^>]*>[\s\S]*?<\/a>/i', '', $html);
        
        // ganti semua iframe dengan keterangan
        $html = preg_replace(
          '/<iframe\b[^>]*><\/iframe>/i',
          '<div style="border:1px solid #999;padding:10px;margin:10px 0;">
             Berkas PDF eksternal tidak dirender di file ini.
           </div>',
          $html
        );
        
        // buang class container dari body
        $html = str_replace('class="container"', '', $html);
        
        // perbaiki page break pertama agar tidak langsung halaman kosong
        $html = preg_replace(
          '/<fieldset style="page-break-before:always;">/i',
          '<fieldset style="page-break-before:auto;">',
          $html,
          1
        );
        
        // WAJIB sebelum WriteHTML
        $html = $this->_cleanHTMLForMpdf($html);
    
      $dir = WEBAPPS_PATHX . '/berkasrawat/pages/upload/klaim';
    
      if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
      }
    
      $nosep = isset($vedika['nosep']) ? $vedika['nosep'] : $this->_getSEPInfo('no_sep', $no_rawat);
      $safeSep = preg_replace('/[^A-Za-z0-9_\-]/', '', $nosep);
    
      $filename = $safeSep . '.pdf';
      $fullPath = $dir . '/' . $filename;
      $lokasi_file = 'pages/upload/klaim/' . $filename;
    
      try {
          $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'margin_left' => 8,
            'margin_right' => 8,
            'margin_top' => 8,
            'margin_bottom' => 8,
            'tempDir' => sys_get_temp_dir(),
        
            // bantu kecilkan image
            'img_dpi' => 96,
            'jpeg_quality' => 70,
          ]);
        
          $mpdf->SetCompression(true);
          $mpdf->simpleTables = true;
          $mpdf->packTableData = true;
          $mpdf->shrink_tables_to_fit = 1;
          $mpdf->use_kwt = false;
          $mpdf->keep_table_proportions = true;
          $mpdf->tableMinSizePriority = true;
          $mpdf->setAutoTopMargin = 'stretch';
          $mpdf->setAutoBottomMargin = 'stretch';

          // Worker CLI tidak selalu dapat mengambil logo melalui URL aplikasi.
          // Daftarkan file lokal langsung ke mPDF agar stabil di Linux/Apache.
          $rsudLogoPath = WEBAPPS_PATHX . '/TTD/logo.png';
          if (is_readable($rsudLogoPath)) {
            $mpdf->imageVars['rsud_logo'] = file_get_contents($rsudLogoPath);
          }
          
        
          $basePdfPath = $dir . '/base_' . $filename;
          $inacbgPdfPath = $dir . '/inacbg_' . $filename;
          $newFinalPath = $dir . '/new_' . $filename;
        
          /*
           * Penting:
           * PDF utama hasil mPDF disimpan ke basePdfPath,
           * bukan langsung ke fullPath.
           */
          $mpdf->WriteHTML($html);
          $mpdf->Output($basePdfPath, \Mpdf\Output\Destination::FILE);
        
          if (!file_exists($basePdfPath) || filesize($basePdfPath) <= 0) {
            return [
              'status' => false,
              'message' => 'File PDF utama gagal dibuat',
              'no_rawat' => $no_rawat,
              'path' => $basePdfPath
            ];
          }
        
          $mergeFiles = [];
          $skippedFiles = [];
        
          /*
           * 1. Ambil PDF INACBG dari e-Klaim
           */
          $inacbgResult = $this->_saveKlaimInacbgPDF($nosep, $inacbgPdfPath);
        
          if ($inacbgResult['status']) {
            $mergeFiles[] = $inacbgPdfPath;
          } else {
            $skippedFiles[] = [
              'jenis' => 'INACBG',
              'message' => isset($inacbgResult['message']) ? $inacbgResult['message'] : 'PDF INACBG tidak tersedia'
            ];
          }
        
          /*
           * 2. PDF utama hasil mPDF
           */
          $mergeFiles[] = $basePdfPath;
        
          /*
           * 3. PDF SEP upload kode 001
           */
          $berkas_sep_pdf = $this->db('berkas_digital_perawatan')
            ->where('no_rawat', $no_rawat)
            ->where('kode', '001')
            ->like('lokasi_file', '%pdf')
            ->toArray();
        
          foreach ($berkas_sep_pdf as $berkas) {
            $path = WEBAPPS_PATHX . '/berkasrawat/' . $berkas['lokasi_file'];
        
            if (file_exists($path) && filesize($path) > 0) {
              $mergeFiles[] = $path;
            } else {
              $skippedFiles[] = [
                'jenis' => 'SEP PDF',
                'lokasi_file' => $berkas['lokasi_file'],
                'path' => $path,
                'message' => 'File tidak ditemukan di server mLITE'
              ];
            }
          }
        
          /*
           * 4. PDF upload lainnya.
           * Jangan ikutkan kode KLM supaya hasil generate tidak merge dirinya sendiri.
           */
          $tempDownloadedFiles = [];
          $berkas_digital_pdf = $this->db('berkas_digital_perawatan')
              ->where('no_rawat', $no_rawat)
              ->where('kode', '!=', '001')
              ->where('kode', '!=', 'KLM')
              ->like('lokasi_file', '%pdf')
              ->toArray();
            
            foreach ($berkas_digital_pdf as $berkas) {
              /*
               * 1. Coba cari dulu di server mLITE lokal
               */
              $localPath = WEBAPPS_PATHX . '/berkasrawat/' . $berkas['lokasi_file'];
            
              if (file_exists($localPath) && filesize($localPath) > 0) {
                $mergeFiles[] = $localPath;
                continue;
              }
            
              /*
               * 2. Kalau tidak ada lokal, download dari server utama WEBAPPS_URL
               */
              $remoteResult = $this->_downloadRemotePDFToLocal(
                $berkas['lokasi_file'],
                $dir,
                'remote_' . $berkas['kode']
              );
            
              if ($remoteResult['status']) {
                $mergeFiles[] = $remoteResult['path'];
                $tempDownloadedFiles[] = $remoteResult['path'];
              } else {
                $skippedFiles[] = [
                  'jenis' => 'PDF Upload Remote',
                  'kode' => $berkas['kode'],
                  'lokasi_file' => $berkas['lokasi_file'],
                  'local_path' => $localPath,
                  'remote' => $remoteResult,
                  'message' => 'File tidak ditemukan lokal dan gagal download dari server utama'
                ];
              }
            }
        
          /*
           * Merge + compress ke file sementara.
           */
          $mergeResult = $this->_mergeCompressPDFs($mergeFiles, $newFinalPath);
        
          if (!$mergeResult['status']) {
            return [
              'status' => false,
              'message' => 'Gagal merge PDF',
              'no_rawat' => $no_rawat,
              'merge' => $mergeResult,
              'skipped_files' => $skippedFiles
            ];
          }
        
          if (!file_exists($newFinalPath) || filesize($newFinalPath) <= 0) {
            return [
              'status' => false,
              'message' => 'File hasil merge tidak terbentuk',
              'no_rawat' => $no_rawat,
              'path' => $newFinalPath,
              'merge' => $mergeResult,
              'skipped_files' => $skippedFiles
            ];
          }
        
          /*
           * Replace file lama hanya setelah file baru berhasil dibuat.
           */
          if (file_exists($fullPath)) {
            unlink($fullPath);
          }
        
          rename($newFinalPath, $fullPath);
        
          /*
           * Bersihkan file sementara.
           */
          if (file_exists($basePdfPath)) {
            unlink($basePdfPath);
          }
        
          if (file_exists($inacbgPdfPath)) {
            unlink($inacbgPdfPath);
          }
          
          foreach ($tempDownloadedFiles as $tempFile) {
              if (file_exists($tempFile)) {
                unlink($tempFile);
              }
            }
        
          if (!file_exists($fullPath) || filesize($fullPath) <= 0) {
            return [
              'status' => false,
              'message' => 'File PDF final gagal dibuat di server mLITE',
              'no_rawat' => $no_rawat,
              'path' => $fullPath
            ];
          }
        
          /*
           * Tidak perlu panggil _compressPDF lagi,
           * karena _mergeCompressPDFs sudah merge + compress.
           */
          $register = $this->_registerPDFKlaim($no_rawat, $lokasi_file);
        
          if (!$register) {
            return [
              'status' => false,
              'message' => 'PDF dibuat, tetapi gagal register ke database',
              'no_rawat' => $no_rawat,
              'file' => $lokasi_file,
              'path' => $fullPath,
              'merge' => $mergeResult,
              'skipped_files' => $skippedFiles
            ];
          }
        
          return [
            'status' => true,
            'message' => 'PDF klaim berhasil dibuat dan digabung',
            'no_rawat' => $no_rawat,
            'nosep' => $nosep,
            'file' => $lokasi_file,
            'url' => url(WEBAPPS_URLX) . '/berkasrawat/' . $lokasi_file,
            'path' => $fullPath,
            'merge' => $mergeResult,
            'skipped_files' => $skippedFiles
          ];
        
        } catch (\Throwable $e) {
          return [
            'status' => false,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'no_rawat' => $no_rawat
          ];
        }
    }
    
    private function _makeQRCodeBase64($text)
    {
      if (
        !class_exists('\\chillerlan\\QRCode\\QROptions') ||
        !class_exists('\\chillerlan\\QRCode\\QRCode')
      ) {
        return '';
      }
    
      try {
        $options = new \chillerlan\QRCode\QROptions([
          'outputType' => \chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG,
          'eccLevel' => \chillerlan\QRCode\QRCode::ECC_L,
          'scale' => 3,
          'imageBase64' => true
        ]);
    
        return (new \chillerlan\QRCode\QRCode($options))->render($text);
    
      } catch (\Throwable $e) {
        return '';
      }
    }
  
    private function _compressPDF($sourcePath)
    {
      if (!file_exists($sourcePath)) {
        return [
          'status' => false,
          'message' => 'File sumber tidak ditemukan',
          'source' => $sourcePath
        ];
      }
    
      if (!function_exists('exec')) {
        return [
          'status' => false,
          'message' => 'Fungsi exec() tidak aktif di PHP. Cek disable_functions di php.ini.',
          'source' => $sourcePath
        ];
      }
    
      $gs = 'gs';
    
      // Coba cari path ghostscript
      exec('command -v gs 2>&1', $whichOutput, $whichCode);
    
      if ($whichCode === 0 && !empty($whichOutput[0])) {
        $gs = trim($whichOutput[0]);
      }
    
      $compressedPath = preg_replace('/\.pdf$/i', '_compressed.pdf', $sourcePath);
    
      $cmd = escapeshellcmd($gs) . ' ' .
        '-sDEVICE=pdfwrite ' .
        '-dCompatibilityLevel=1.4 ' .
        '-dPDFSETTINGS=/ebook ' .
        '-dNOPAUSE ' .
        '-dQUIET ' .
        '-dBATCH ' .
        '-sOutputFile=' . escapeshellarg($compressedPath) . ' ' .
        escapeshellarg($sourcePath) .
        ' 2>&1';
    
      $output = [];
      $returnCode = 0;
    
      exec($cmd, $output, $returnCode);
    
      if ($returnCode !== 0) {
        return [
          'status' => false,
          'message' => 'Ghostscript gagal menjalankan kompresi',
          'return_code' => $returnCode,
          'command' => $cmd,
          'output' => implode("\n", $output),
          'source' => $sourcePath,
          'target' => $compressedPath
        ];
      }
    
      if (!file_exists($compressedPath)) {
        return [
          'status' => false,
          'message' => 'File hasil kompresi tidak terbentuk',
          'command' => $cmd,
          'output' => implode("\n", $output),
          'source' => $sourcePath,
          'target' => $compressedPath
        ];
      }
    
      if (filesize($compressedPath) <= 0) {
        unlink($compressedPath);
    
        return [
          'status' => false,
          'message' => 'File hasil kompresi kosong',
          'source' => $sourcePath,
          'target' => $compressedPath
        ];
      }
    
      $originalSize = filesize($sourcePath);
      $compressedSize = filesize($compressedPath);
    
      if ($compressedSize < $originalSize) {
        unlink($sourcePath);
        rename($compressedPath, $sourcePath);
    
        return [
          'status' => true,
          'message' => 'PDF berhasil dikompres',
          'original_size' => $originalSize,
          'compressed_size' => $compressedSize,
          'saved_bytes' => $originalSize - $compressedSize
        ];
      }
    
      unlink($compressedPath);
    
      return [
        'status' => true,
        'message' => 'PDF tidak dikompres karena ukuran hasil tidak lebih kecil',
        'original_size' => $originalSize,
        'compressed_size' => $compressedSize
      ];
    }
    
    public function getCreatePDFKlaim($id)
    {
      header('Content-Type: application/json');
    
      try {
        $no_rawat = $this->revertNorawat($id);
        $result = $this->_createPDFKlaimFile($no_rawat);
    
        echo json_encode($result);
        exit();
    
      } catch (\Throwable $e) {
        echo json_encode([
          'status' => false,
          'message' => $e->getMessage(),
          'file' => $e->getFile(),
          'line' => $e->getLine()
        ]);
        exit();
      }
    }

    private function _acquirePDFQueueLock($name, $timeout = 5)
    {
      $stmt = $this->db()->pdo()->prepare('SELECT GET_LOCK(?, ?)');
      $stmt->execute([$name, (int) $timeout]);
      return (int) $stmt->fetchColumn() === 1;
    }

    private function _releasePDFQueueLock($name)
    {
      try {
        $stmt = $this->db()->pdo()->prepare('SELECT RELEASE_LOCK(?)');
        $stmt->execute([$name]);
      } catch (\Throwable $e) {
        // Koneksi database juga akan melepas named lock secara otomatis.
      }
    }

    private function _enqueuePDFKlaim($no_rawat, $requested_by = '')
    {
      $no_rawat = trim((string) $no_rawat);

      if ($no_rawat === '') {
        return [
          'status' => false,
          'message' => 'Nomor rawat kosong'
        ];
      }

      $vedika = $this->db('mlite_vedika')
        ->where('no_rawat', $no_rawat)
        ->oneArray();

      if (!$vedika) {
        return [
          'status' => false,
          'no_rawat' => $no_rawat,
          'message' => 'Data Vedika tidak ditemukan'
        ];
      }

      if ($vedika['status'] !== 'Pengajuan') {
        return [
          'status' => false,
          'no_rawat' => $no_rawat,
          'message' => 'Status klaim bukan Pengajuan'
        ];
      }

      $nosep = isset($vedika['nosep']) && $vedika['nosep'] !== ''
        ? trim((string) $vedika['nosep'])
        : trim((string) $this->_getSEPInfo('no_sep', $no_rawat));
      $queueIdentity = $nosep !== '' ? 'sep:' . $nosep : 'rawat:' . $no_rawat;
      $lockName = 'vedika_pdf_enqueue_' . sha1($queueIdentity);

      if (!$this->_acquirePDFQueueLock($lockName, 5)) {
        return [
          'status' => false,
          'no_rawat' => $no_rawat,
          'message' => 'Gagal memperoleh lock antrean'
        ];
      }

      try {
        $pdo = $this->db()->pdo();
        $active = $pdo->prepare("SELECT *
          FROM mlite_vedika_pdf_queue
          WHERE (no_rawat = ? OR (? <> '' AND nosep = ?))
            AND status IN ('queued', 'processing')
          ORDER BY id DESC
          LIMIT 1");
        $active->execute([$no_rawat, $nosep, $nosep]);
        $active = $active->fetch(\PDO::FETCH_ASSOC);

        if ($active) {
          return [
            'status' => true,
            'queued' => true,
            'reused' => true,
            'recycled' => false,
            'job_id' => (int) $active['id'],
            'job_status' => $active['status'],
            'no_rawat' => $no_rawat,
            'nosep' => $active['nosep'],
            'message' => 'PDF sudah berada dalam antrean'
          ];
        }

        $recyclable = $pdo->prepare("SELECT id
          FROM mlite_vedika_pdf_queue
          WHERE no_rawat = ? OR (? <> '' AND nosep = ?)
          ORDER BY id DESC
          LIMIT 1");
        $recyclable->execute([$no_rawat, $nosep, $nosep]);
        $recyclableId = $recyclable->fetchColumn();

        if ($recyclableId) {
          $recycle = $pdo->prepare("UPDATE mlite_vedika_pdf_queue
            SET no_rawat = ?,
                nosep = ?,
                requested_by = ?,
                status = 'queued',
                attempts = 0,
                message = ?,
                created_at = NOW(),
                started_at = NULL,
                finished_at = NULL,
                heartbeat_at = NULL
            WHERE id = ?");
          $recycle->execute([
            $no_rawat,
            $nosep,
            substr((string) $requested_by, 0, 50),
            'Antrean digunakan kembali untuk generate PDF terbaru',
            $recyclableId
          ]);

          return [
            'status' => true,
            'queued' => true,
            'reused' => true,
            'recycled' => true,
            'job_id' => (int) $recyclableId,
            'job_status' => 'queued',
            'no_rawat' => $no_rawat,
            'nosep' => $nosep,
            'message' => 'Antrean lama digunakan kembali untuk membuat PDF terbaru'
          ];
        }

        $insert = $pdo->prepare("INSERT INTO mlite_vedika_pdf_queue
          (no_rawat, nosep, requested_by, status, attempts, message, created_at)
          VALUES (?, ?, ?, 'queued', 0, ?, NOW())");
        $insert->execute([
          $no_rawat,
          $nosep,
          substr((string) $requested_by, 0, 50),
          'Menunggu diproses worker'
        ]);

        return [
          'status' => true,
          'queued' => true,
          'reused' => false,
          'recycled' => false,
          'job_id' => (int) $pdo->lastInsertId(),
          'job_status' => 'queued',
          'no_rawat' => $no_rawat,
          'nosep' => $nosep,
          'message' => 'PDF berhasil dimasukkan ke antrean'
        ];
      } finally {
        $this->_releasePDFQueueLock($lockName);
      }
    }

    public function postEnqueuePDFKlaim()
    {
      header('Content-Type: application/json; charset=utf-8');

      try {
        $no_rawat = isset($_POST['no_rawat']) ? $_POST['no_rawat'] : '';
        $username = $this->core->getUserInfo('username', null, true);
        echo json_encode(
          $this->_enqueuePDFKlaim($no_rawat, $username),
          JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
      } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode([
          'status' => false,
          'message' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
      }
      exit();
    }

    public function postBulkEnqueuePDFKlaim()
    {
      header('Content-Type: application/json; charset=utf-8');

      try {
        $noRawatList = isset($_POST['no_rawat']) ? $_POST['no_rawat'] : [];

        if (!empty($_POST['no_rawat_json'])) {
          $decoded = json_decode($_POST['no_rawat_json'], true);
          if (is_array($decoded)) {
            $noRawatList = $decoded;
          }
        }

        if (!is_array($noRawatList)) {
          $noRawatList = [$noRawatList];
        }

        $noRawatList = array_values(array_unique(array_filter(array_map('trim', $noRawatList))));

        if (!$noRawatList) {
          echo json_encode([
            'status' => false,
            'message' => 'Tidak ada data untuk dimasukkan ke antrean'
          ]);
          exit();
        }

        $username = $this->core->getUserInfo('username', null, true);
        $queued = 0;
        $reused = 0;
        $failed = 0;
        $jobIds = [];
        $results = [];

        foreach ($noRawatList as $no_rawat) {
          $result = $this->_enqueuePDFKlaim($no_rawat, $username);
          $results[] = $result;

          if (!empty($result['status'])) {
            $jobIds[] = (int) $result['job_id'];
            if (!empty($result['reused'])) {
              $reused++;
            } else {
              $queued++;
            }
          } else {
            $failed++;
          }
        }

        echo json_encode([
          'status' => true,
          'message' => 'Bulk enqueue selesai',
          'total' => count($noRawatList),
          'queued' => $queued,
          'reused' => $reused,
          'failed' => $failed,
          'job_ids' => array_values(array_unique($jobIds)),
          'results' => $results
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode([
          'status' => false,
          'message' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
      }
      exit();
    }

    private function _outputPDFQueueStatus($jobIds)
    {
      header('Content-Type: application/json; charset=utf-8');

      try {
        if (!is_array($jobIds)) {
          $jobIds = explode(',', (string) $jobIds);
        }

        $jobIds = array_values(array_unique(array_filter(array_map('intval', $jobIds))));

        if (!$jobIds) {
          echo json_encode([
            'status' => false,
            'message' => 'ID antrean kosong',
            'jobs' => []
          ]);
          exit();
        }

        $placeholders = implode(',', array_fill(0, count($jobIds), '?'));
        $query = $this->db()->pdo()->prepare("SELECT *
          FROM mlite_vedika_pdf_queue
          WHERE id IN ($placeholders)
          ORDER BY id");
        $query->execute($jobIds);
        $jobs = $query->fetchAll(\PDO::FETCH_ASSOC);
        $counts = [
          'queued' => 0,
          'processing' => 0,
          'done' => 0,
          'failed' => 0
        ];

        foreach ($jobs as &$job) {
          $job['id'] = (int) $job['id'];
          $job['attempts'] = (int) $job['attempts'];
          $job['url'] = '';

          if (isset($counts[$job['status']])) {
            $counts[$job['status']]++;
          }

          if ($job['status'] === 'done') {
            $pdf = $this->db('berkas_digital_perawatan')
              ->where('no_rawat', $job['no_rawat'])
              ->where('kode', 'KLM')
              ->oneArray();

            if ($pdf && !empty($pdf['lokasi_file'])) {
              $job['url'] = url(WEBAPPS_URLX) . '/berkasrawat/' . $pdf['lokasi_file'];
            }
          }
        }
        unset($job);

        echo json_encode([
          'status' => true,
          'total' => count($jobs),
          'counts' => $counts,
          'finished' => ($counts['done'] + $counts['failed']) === count($jobs),
          'jobs' => $jobs
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode([
          'status' => false,
          'message' => $e->getMessage(),
          'jobs' => []
        ], JSON_UNESCAPED_UNICODE);
      }
      exit();
    }

    public function getPDFQueueStatus()
    {
      $jobIds = isset($_GET['job_ids']) ? $_GET['job_ids'] : [];
      $this->_outputPDFQueueStatus($jobIds);
    }

    public function postPDFQueueStatus()
    {
      $jobIds = isset($_POST['job_ids']) ? $_POST['job_ids'] : [];

      if (!empty($_POST['job_ids_json'])) {
        $decoded = json_decode($_POST['job_ids_json'], true);
        if (is_array($decoded)) {
          $jobIds = $decoded;
        }
      }

      $this->_outputPDFQueueStatus($jobIds);
    }

    private function _recoverStalePDFQueueJobs()
    {
      $pdo = $this->db()->pdo();
      $pdo->exec("UPDATE mlite_vedika_pdf_queue
        SET status = CASE WHEN attempts >= 3 THEN 'failed' ELSE 'queued' END,
            message = CASE
              WHEN attempts >= 3 THEN 'Worker berhenti dan batas percobaan tercapai'
              ELSE 'Dikembalikan ke antrean karena heartbeat worker kedaluwarsa'
            END,
            started_at = NULL,
            heartbeat_at = NULL
        WHERE status = 'processing'
          AND COALESCE(heartbeat_at, started_at) < DATE_SUB(NOW(), INTERVAL 60 MINUTE)");
    }

    public function processPDFQueueOnce($workerId = '')
    {
      $pdo = $this->db()->pdo();
      $workerId = $workerId !== '' ? $workerId : php_uname('n') . ':' . getmypid();
      $claimLock = 'vedika_pdf_queue_claim';
      $job = null;

      $this->_recoverStalePDFQueueJobs();

      if (!$this->_acquirePDFQueueLock($claimLock, 5)) {
        return [
          'status' => true,
          'idle' => true,
          'message' => 'Worker lain sedang mengambil antrean'
        ];
      }

      try {
        $query = $pdo->query("SELECT *
          FROM mlite_vedika_pdf_queue
          WHERE status = 'queued'
            AND attempts < 3
          ORDER BY created_at, id
          LIMIT 1");
        $job = $query->fetch(\PDO::FETCH_ASSOC);

        if (!$job) {
          return [
            'status' => true,
            'idle' => true,
            'message' => 'Antrean kosong'
          ];
        }

        $claim = $pdo->prepare("UPDATE mlite_vedika_pdf_queue
          SET status = 'processing',
              attempts = attempts + 1,
              message = ?,
              started_at = NOW(),
              finished_at = NULL,
              heartbeat_at = NOW()
          WHERE id = ? AND status = 'queued'");
        $claim->execute([
          'Diproses oleh ' . substr($workerId, 0, 120),
          $job['id']
        ]);

        if ($claim->rowCount() !== 1) {
          return [
            'status' => true,
            'idle' => true,
            'message' => 'Antrean sudah diambil worker lain'
          ];
        }

        $job['attempts'] = (int) $job['attempts'] + 1;
      } finally {
        $this->_releasePDFQueueLock($claimLock);
      }

      $patientLock = 'vedika_pdf_patient_' . sha1($job['no_rawat']);

      if (!$this->_acquirePDFQueueLock($patientLock, 0)) {
        $reset = $pdo->prepare("UPDATE mlite_vedika_pdf_queue
          SET status = 'queued',
              message = 'Menunggu proses PDF pasien yang sama',
              started_at = NULL,
              heartbeat_at = NULL
          WHERE id = ?");
        $reset->execute([$job['id']]);

        return [
          'status' => true,
          'idle' => true,
          'job_id' => (int) $job['id'],
          'message' => 'Pasien yang sama sedang diproses worker lain'
        ];
      }

      try {
        $heartbeat = $pdo->prepare("UPDATE mlite_vedika_pdf_queue
          SET heartbeat_at = NOW()
          WHERE id = ?");
        $heartbeat->execute([$job['id']]);

        $result = $this->_createPDFKlaimFile($job['no_rawat']);
        $success = !empty($result['status']);
        $message = isset($result['message'])
          ? $result['message']
          : ($success ? 'PDF selesai dibuat' : 'Pembuatan PDF gagal');

        $finalStatus = $success ? 'done' : ($job['attempts'] >= 3 ? 'failed' : 'queued');
        if (!$success && $finalStatus === 'queued') {
          $message .= ' (akan dicoba lagi)';
        }

        $finish = $pdo->prepare("UPDATE mlite_vedika_pdf_queue
          SET status = ?,
              message = ?,
              finished_at = CASE WHEN ? IN ('done', 'failed') THEN NOW() ELSE NULL END,
              started_at = CASE WHEN ? = 'queued' THEN NULL ELSE started_at END,
              heartbeat_at = NOW()
          WHERE id = ?");
        $finish->execute([
          $finalStatus,
          substr((string) $message, 0, 65000),
          $finalStatus,
          $finalStatus,
          $job['id']
        ]);

        return [
          'status' => $success,
          'idle' => false,
          'job_id' => (int) $job['id'],
          'no_rawat' => $job['no_rawat'],
          'message' => $message,
          'result' => $result
        ];
      } catch (\Throwable $e) {
        $finalStatus = $job['attempts'] >= 3 ? 'failed' : 'queued';
        $errorMessage = $e->getMessage();
        if ($finalStatus === 'queued') {
          $errorMessage .= ' (akan dicoba lagi)';
        }

        $failed = $pdo->prepare("UPDATE mlite_vedika_pdf_queue
          SET status = ?,
              message = ?,
              finished_at = CASE WHEN ? = 'failed' THEN NOW() ELSE NULL END,
              started_at = CASE WHEN ? = 'queued' THEN NULL ELSE started_at END,
              heartbeat_at = NOW()
          WHERE id = ?");
        $failed->execute([
          $finalStatus,
          substr($errorMessage, 0, 65000),
          $finalStatus,
          $finalStatus,
          $job['id']
        ]);

        return [
          'status' => false,
          'idle' => false,
          'job_id' => (int) $job['id'],
          'no_rawat' => $job['no_rawat'],
          'message' => $e->getMessage()
        ];
      } finally {
        $this->_releasePDFQueueLock($patientLock);
      }
    }
    
    private function _saveKlaimInacbgPDF($nosep, $targetPath)
    {
      $request = '{
        "metadata": {
          "method":"claim_print"
        },
        "data": {
          "nomor_sep":"'.$nosep.'"
        }
      }';
    
      $msg = $this->Request($request);
    
      if (
        isset($msg['metadata']['message']) &&
        $msg['metadata']['message'] == "Ok" &&
        !empty($msg['data'])
      ) {
        $pdf = base64_decode($msg['data']);
        file_put_contents($targetPath, $pdf);
    
        if (file_exists($targetPath) && filesize($targetPath) > 0) {
          return [
            'status' => true,
            'file' => $targetPath
          ];
        }
      }
    
      return [
        'status' => false,
        'message' => isset($msg['metadata']['message']) ? $msg['metadata']['message'] : 'Gagal mengambil PDF INACBG'
      ];
    } 
    
    private function _mergeCompressPDFs($sourceFiles, $outputPath)
    {
      $validFiles = [];
    
      foreach ($sourceFiles as $file) {
        if (file_exists($file) && filesize($file) > 0) {
          $validFiles[] = $file;
        }
      }
    
      if (!count($validFiles)) {
        return [
          'status' => false,
          'message' => 'Tidak ada file PDF valid untuk digabung'
        ];
      }
    
      $gs = 'gs';
    
      exec('command -v gs 2>&1', $whichOutput, $whichCode);
    
      if ($whichCode === 0 && !empty($whichOutput[0])) {
        $gs = trim($whichOutput[0]);
      }
    
      $cmd = escapeshellcmd($gs) . ' ' .
        '-sDEVICE=pdfwrite ' .
        '-dCompatibilityLevel=1.4 ' .
        '-dPDFSETTINGS=/ebook ' .
        '-dNOPAUSE ' .
        '-dQUIET ' .
        '-dBATCH ' .
        '-sOutputFile=' . escapeshellarg($outputPath) . ' ';
    
      foreach ($validFiles as $file) {
        $cmd .= escapeshellarg($file) . ' ';
      }
    
      $cmd .= ' 2>&1';
    
      $output = [];
      $returnCode = 0;
    
      exec($cmd, $output, $returnCode);
    
      if ($returnCode !== 0) {
        return [
          'status' => false,
          'message' => 'Ghostscript gagal merge PDF',
          'return_code' => $returnCode,
          'output' => implode("\n", $output),
          'command' => $cmd
        ];
      }
    
      if (!file_exists($outputPath) || filesize($outputPath) <= 0) {
        return [
          'status' => false,
          'message' => 'File hasil merge tidak terbentuk',
          'output' => implode("\n", $output)
        ];
      }
    
      return [
        'status' => true,
        'message' => 'PDF berhasil digabung dan dikompres',
        'file' => $outputPath,
        'size' => filesize($outputPath),
        'total_source' => count($validFiles)
      ];
    }
    
    private function _buildRemoteBerkasURL($lokasi_file)
    {
      $lokasi_file = ltrim($lokasi_file, '/');
    
      // keamanan dasar, jangan izinkan path naik folder
      if (strpos($lokasi_file, '..') !== false) {
        return false;
      }
    
      $parts = explode('/', $lokasi_file);
      $parts = array_map('rawurlencode', $parts);
    
      return rtrim(WEBAPPS_URL, '/') . '/berkasrawat/' . implode('/', $parts);
    }    
    
    private function _downloadRemotePDFToLocal($lokasi_file, $tempDir, $prefix = 'remote')
    {
      if (!is_dir($tempDir)) {
        mkdir($tempDir, 0775, true);
      }
    
      $url = $this->_buildRemoteBerkasURL($lokasi_file);
    
      if (!$url) {
        return [
          'status' => false,
          'message' => 'Lokasi file tidak valid',
          'lokasi_file' => $lokasi_file
        ];
      }
    
      $targetPath = $tempDir . '/' . $prefix . '_' . md5($lokasi_file . microtime(true)) . '.pdf';
    
      if (!function_exists('curl_init')) {
        return [
          'status' => false,
          'message' => 'cURL belum aktif di PHP',
          'url' => $url
        ];
      }
    
      $fp = fopen($targetPath, 'w+');
    
      if (!$fp) {
        return [
          'status' => false,
          'message' => 'Gagal membuat file temporary lokal',
          'path' => $targetPath
        ];
      }
    
      $ch = curl_init($url);
    
      curl_setopt($ch, CURLOPT_FILE, $fp);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
      curl_setopt($ch, CURLOPT_TIMEOUT, 60);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
      curl_setopt($ch, CURLOPT_USERAGENT, 'mLITE Vedika PDF Merger');
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
      $ok = curl_exec($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $curlError = curl_error($ch);
    
      curl_close($ch);
      fclose($fp);
    
      if (!$ok || $httpCode < 200 || $httpCode >= 300) {
        if (file_exists($targetPath)) {
          unlink($targetPath);
        }
    
        return [
          'status' => false,
          'message' => 'Gagal download PDF dari server utama',
          'http_code' => $httpCode,
          'curl_error' => $curlError,
          'url' => $url
        ];
      }
    
      if (!file_exists($targetPath) || filesize($targetPath) <= 0) {
        return [
          'status' => false,
          'message' => 'File temporary kosong',
          'url' => $url,
          'path' => $targetPath
        ];
      }
    
      $header = file_get_contents($targetPath, false, null, 0, 4);
    
      if ($header !== '%PDF') {
        unlink($targetPath);
    
        return [
          'status' => false,
          'message' => 'File dari server utama bukan PDF valid',
          'url' => $url
        ];
      }
    
      return [
        'status' => true,
        'message' => 'PDF remote berhasil didownload',
        'url' => $url,
        'path' => $targetPath,
        'size' => filesize($targetPath)
      ];
    }    

  public function getSetStatus($id)
  {
    $set_status = $this->db('bridging_sep')->where('no_sep', $id)->oneArray();
    $jenis = $this->db('mlite_vedika')->where('nosep', $id)->oneArray();
    $vedika = $this->db('mlite_vedika')
    ->join('mlite_users','mlite_users.username=mlite_vedika.username')
    ->where('mlite_vedika.nosep', $id)
    ->asc('mlite_vedika.id')
    ->limit('1')
    ->toArray();
    $this->tpl->set('logo', $this->settings->get('settings.logo'));
    $this->tpl->set('nama_instansi', $this->settings->get('settings.nama_instansi'));
    $this->tpl->set('set_status', $set_status);
    $this->tpl->set('vedika', $vedika);
    $this->tpl->set('jenis', $jenis);
    echo $this->tpl->draw(MODULES . '/vedika/view/admin/setstatus.html', true);
    exit();
  }

  public function getBerkasPasien()
  {
    echo $this->tpl->draw(MODULES . '/vedika/view/admin/berkaspasien.html', true);
    exit();
  }

  public function anyBerkasPerawatan($no_rawat)
  {
    $row_berkasdig = $this->db('berkas_digital_perawatan')
      ->join('master_berkas_digital', 'master_berkas_digital.kode=berkas_digital_perawatan.kode')
      ->where('berkas_digital_perawatan.no_rawat', revertNorawat($no_rawat))
      ->toArray();

    $this->assign['master_berkas_digital'] = $this->db('master_berkas_digital')->toArray();
    $this->assign['berkas_digital'] = $row_berkasdig;

    $this->assign['no_rawat'] = revertNorawat($no_rawat);
    $this->assign['user_role'] = $this->core->getUserInfo('role');
    $this->tpl->set('berkasperawatan', $this->assign);

    echo $this->tpl->draw(MODULES . '/vedika/view/admin/berkasperawatan.html', true);
    exit();
  }

  public function postSaveBerkasDigital()
  {

    if(MULTI_APP) {

      $curl = curl_init();
      $filePath = $_FILES['files']['tmp_name'];

      curl_setopt_array($curl, array(
        CURLOPT_URL => str_replace('webapps','',WEBAPPS_URL).'api/berkasdigital',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array('file'=> new \CURLFILE($filePath),'token' => $this->settings->get('api.berkasdigital_key'), 'no_rawat' => $_POST['no_rawat'], 'kode' => $_POST['kode']),
        CURLOPT_HTTPHEADER => array(),
      ));

      $response = curl_exec($curl);

      curl_close($curl);
      $json = json_decode($response, true);
      if($json['status'] == 'Success') {
        echo '<br><img src="'.WEBAPPS_URL.'/berkasrawat/'.$json['msg'].'" width="150" />';
      } else {
        echo 'Gagal menambahkan gambar';
      }

    } else {    
      $dir    = $this->_uploads;
      $cntr   = 0;

      $image = $_FILES['files']['tmp_name'];
      $img = new \Systems\Lib\Image();
      $id = convertNorawat($_POST['no_rawat']);
      if ($img->load($image)) {
        $imgName = time() . $cntr++;
        $imgPath = $dir . '/' . $id . '_' . $imgName . '.' . $img->getInfos('type');
        $lokasi_file = 'pages/upload/' . $id . '_' . $imgName . '.' . $img->getInfos('type');
        $img->save($imgPath);
        $query = $this->db('berkas_digital_perawatan')->save(['no_rawat' => $_POST['no_rawat'], 'kode' => $_POST['kode'], 'lokasi_file' => $lokasi_file]);
        if ($query) {
          echo '<br><img src="' . WEBAPPS_URL . '/berkasrawat/' . $lokasi_file . '" width="150" />';
        }
      }
    }
    exit();
  }

  public function postSaveStatus()
  {
    redirect(url([ADMIN, 'vedika', 'index']));
  }

  private function _getSEPInfo($field, $no_rawat)
  {
    $row = $this->db('bridging_sep')
    ->where('no_rawat', $no_rawat)
    ->asc('jnspelayanan')
    ->oneArray();
    if(!$row) {
      $row[$field] = '';
    }
    return $row[$field];
  }
  
  private function _getSITB($field, $no_rkm_medis)
  {
    $row = $this->db('sitb_pasien_norm')
      ->where('sitb_pasien_norm.no_rkm_medis', $no_rkm_medis)
      ->oneArray();
    if(!$row) {
      $row[$field] = '';
    }
    return $row[$field];
  }
  
  private function _getFinalKlaim($field, $no_sep)
  {
    $row = $this->db('inacbg_data_terkirim')
      ->where('inacbg_data_terkirim.no_sep', $no_sep)
      ->oneArray();
    if(!$row) {
      $row[$field] = '';
    }
    return $row[$field];
  }

  private function _getSPRIInfo($field, $no_rawat)
  {
    $row = $this->db('bridging_surat_pri_bpjs')->where('no_rawat', $no_rawat)->oneArray();
    if(!$row) {
      $row[$field] = '';
    }
    return $row[$field];
  }

  private function _getDiagnosa($field, $no_rawat, $status_lanjut)
  {
    $row = $this->db('diagnosa_pasien')
    ->join('penyakit', 'penyakit.kd_penyakit = diagnosa_pasien.kd_penyakit')
    ->where('diagnosa_pasien.no_rawat', $no_rawat)
    ->where('diagnosa_pasien.status', $status_lanjut)->oneArray();
    if(!$row) {
      $row[$field] = '';
    }
    return $row[$field];
  }
  
  private function _getResumeRanap($field, $no_rawat)
  {
    $row = $this->db('resume_pasien_ranap')
      ->where('resume_pasien_ranap.no_rawat', $no_rawat)
      ->oneArray();
    if(!$row) {
      $row[$field] = 'Resume belum dibuat';
    }
    return $row[$field];
  }

  public function getSettings()
  {
    $this->_addHeaderFiles();
    $this->assign['title'] = 'Pengaturan Modul Vedika';
    $this->assign['vedika'] = htmlspecialchars_array($this->settings('vedika'));
    $this->assign['penjab'] = $this->_getPenjab($this->settings->get('vedika.carabayar'));
    $this->assign['master_berkas_digital'] = $this->db('master_berkas_digital')->toArray();
    return $this->draw('settings.html', ['settings' => $this->assign]);
  }

  public function postSaveSettings()
  {
    $_POST['vedika']['carabayar'] = implode(',', $_POST['vedika']['carabayar']);
    foreach ($_POST['vedika'] as $key => $val) {
      $this->settings('vedika', $key, $val);
    }
    $this->notify('success', 'Pengaturan telah disimpan');
    redirect(url([ADMIN, 'vedika', 'settings']));
  }

  public function getMappingInacbgs()
  {
    $this->_addHeaderFiles();
    $this->assign['title'] = 'Pengaturan Mapping Inacbgs';
    $this->assign['vedika'] = htmlspecialchars_array($this->settings('vedika'));
    $this->assign['penjab'] = $this->_getPenjab($this->settings->get('vedika.carabayar'));
    $this->assign['kategori_perawatan'] = $this->db('kategori_perawatan')->toArray();
    return $this->draw('mapping.inacbgs.html', ['settings' => $this->assign]);
  }

  public function postSaveMappingInacbgs()
  {
    foreach ($_POST['vedika'] as $key => $val) {
      $this->settings('vedika', $key, $val);
    }
    $this->notify('success', 'Pengaturan telah disimpan');
    redirect(url([ADMIN, 'vedika', 'mappinginacbgs']));
  }

  public function getBridgingEklaim()
  {
    $this->_addHeaderFiles();
    $this->assign['title'] = 'Pengaturan Modul Vedika';
    $this->assign['vedika'] = htmlspecialchars_array($this->settings('vedika'));
    return $this->draw('bridging.eklaim.html', ['settings' => $this->assign]);
  }

  public function postSaveBridgingEklaim()
  {
    foreach ($_POST['vedika'] as $key => $val) {
      $this->settings('vedika', $key, $val);
    }
    $this->notify('success', 'Pengaturan telah disimpan');
    redirect(url([ADMIN, 'vedika', 'bridgingeklaim']));
  }

  public function getUsers()
  {
    $rows = $this->db('mlite_users_vedika')->toArray();
    foreach ($rows as &$row) {
        $row['editURL'] = url([ADMIN, 'vedika', 'useredit', $row['id']]);
        $row['delURL']  = url([ADMIN, 'vedika', 'userdelete', $row['id']]);
    }
    return $this->draw('users.html', ['users' => $rows]);
  }

  public function getUserAdd()
  {
    $this->assign['form'] = ['username' => '', 'fullname' => '', 'password' => ''];
    return $this->draw('user.form.html', ['users' => $this->assign]);
  }

  public function getUserEdit($id)
  {
    $this->assign['form'] = $this->db('mlite_users_vedika')->where('id', $id)->oneArray();
    return $this->draw('user.form.html', ['users' => $this->assign]);
  }

  public function postUserSave($id = null)
  {
    if (!$id) {    // new
      $query = $this->db('mlite_users_vedika')
      ->save([
        'username' => $_POST['username'],
        'fullname' => $_POST['fullname'],
        'password' => $_POST['password']
      ]);
    } else {        // edit
      $query = $this->db('mlite_users_vedika')
      ->where('id', $id)
      ->save([
        'username' => $_POST['username'],
        'fullname' => $_POST['fullname'],
        'password' => $_POST['password']
      ]);
    }

    if ($query) {
        $this->notify('success', 'Pengguna berhasil disimpan.');
    } else {
        $this->notify('failure', 'Gagak menyimpan pengguna.');
    }

    redirect(url([ADMIN, 'vedika', 'users']));
  }

  public function getUserDelete($id)
  {
    if ($this->db('mlite_users_vedika')->delete($id)) {
        $this->notify('success', 'Pengguna berhasil dihapus.');
    } else {
        $this->notify('failure', 'Tak dapat menghapus pengguna.');
    }
    redirect(url([ADMIN, 'vedika', 'users']));
  }

  public function getPegawaiInfo($field, $nik)
  {
    $row = $this->db('pegawai')->where('nik', $nik)->oneArray();
    if(!$row) {
      $row[$field] = '';
    }
    return $row[$field];
  }

  public function getPasienInfo($field, $no_rkm_medis)
  {
    $row = $this->db('pasien')->where('no_rkm_medis', $no_rkm_medis)->oneArray();
    if(!$row) {
      $row[$field] = '';
    }
    return $row[$field];
  }

  private function _getProsedur($field, $no_rawat, $status_lanjut)
  {
      $row = $this->db('prosedur_pasien')->join('icd9', 'icd9.kode = prosedur_pasien.kode')->where('prosedur_pasien.no_rawat', $no_rawat)->where('prosedur_pasien.status', $status_lanjut)->oneArray();
      if(!$row) {
        $row[$field] = '';
      }
      return $row[$field];
  }

  private function _getPenjab($kd_pj = null)
  {
      $result = [];
      $rows = $this->db('penjab')->where('status', '1')->toArray();

      if (!$kd_pj) {
          $kd_pjArray = [];
      } else {
          $kd_pjArray = explode(',', $kd_pj);
      }

      foreach ($rows as $row) {
          if (empty($kd_pjArray)) {
              $attr = '';
          } else {
              if (in_array($row['kd_pj'], $kd_pjArray)) {
                  $attr = 'selected';
              } else {
                  $attr = '';
              }
          }
          $result[] = ['kd_pj' => $row['kd_pj'], 'png_jawab' => $row['png_jawab'], 'attr' => $attr];
      }
      return $result;
  }

  public function getRegPeriksaInfo($field, $no_rawat)
  {
    $row = $this->db('reg_periksa')->where('no_rawat', $no_rawat)->oneArray();
    return $row[$field];
  }
  
   public function getDpjpRanap($field, $no_rawat)
  {
    $row = $this->db('dpjp_ranap')
    ->select('dokter.nm_dokter')
    ->join ('dokter', 'dokter.kd_dokter=dpjp_ranap.kd_dokter')
    ->where('no_rawat', $no_rawat)
    ->oneArray();
    return $row[$field];
  }

  public function convertNorawat($text)
  {
    setlocale(LC_ALL, 'en_EN');
    $text = str_replace('/', '', trim($text));
    return $text;
  }

  public function revertNorawat($text)
  {
    setlocale(LC_ALL, 'en_EN');
    $tahun = substr($text, 0, 4);
    $bulan = substr($text, 4, 2);
    $tanggal = substr($text, 6, 2);
    $nomor = substr($text, 8, 6);
    $result = $tahun . '/' . $bulan . '/' . $tanggal . '/' . $nomor;
    return $result;
  }

  public function getResume($status_lanjut, $no_rawat)
  {
    if($status_lanjut == 'Ralan') {
      echo $this->draw('form.resume.html', [
        'status_lanjut' => $status_lanjut,
        'reg_periksa' => $this->db('reg_periksa')->where('no_rawat', revertNoRawat($no_rawat))->oneArray(),
        'diagnosa' => $this->db('diagnosa_pasien')->join('penyakit', 'penyakit.kd_penyakit=diagnosa_pasien.kd_penyakit')->where('no_rawat', revertNoRawat($no_rawat))->where('prioritas', 1)->where('diagnosa_pasien.status', 'Ralan')->oneArray(),
        'prosedur' => $this->db('prosedur_pasien')->join('icd9', 'icd9.kode=prosedur_pasien.kode')->where('no_rawat', revertNoRawat($no_rawat))->where('prioritas', 1)->where('status', 'Ralan')->oneArray(),
        'resume_pasien' => $this->db('resume_pasien')->where('no_rawat', revertNoRawat($no_rawat))->oneArray()
      ]);
    }
    if($status_lanjut == 'Ranap') {
      echo $this->draw('form.resume.ranap.html', [
        'status_lanjut' => $status_lanjut,
        'reg_periksa' => $this->db('reg_periksa')->where('no_rawat', revertNoRawat($no_rawat))->oneArray(),
        'kamar_inap' => $this->db('kamar_inap')->where('no_rawat', revertNoRawat($no_rawat))->oneArray(),
        'resume_pasien' => $this->db('resume_pasien_ranap')->where('no_rawat', revertNoRawat($no_rawat))->oneArray()
      ]);
    }
    exit();
  }
  
  public function getAsesmenIgd($status_lanjut, $no_rawat)
  {
    if($status_lanjut == 'Ralan') {
      echo $this->draw('form.asesmenigd.html', [
        'status_lanjut' => $status_lanjut,
        'reg_periksa' => $this->db('reg_periksa')->join('pasien', 'pasien.no_rkm_medis=reg_periksa.no_rkm_medis')->where('no_rawat', revertNoRawat($no_rawat))->oneArray(),
        'diagnosa' => $this->db('diagnosa_pasien')->join('penyakit', 'penyakit.kd_penyakit=diagnosa_pasien.kd_penyakit')->where('no_rawat', revertNoRawat($no_rawat))->where('prioritas', 1)->where('diagnosa_pasien.status', 'Ralan')->oneArray(),
        'prosedur' => $this->db('prosedur_pasien')->join('icd9', 'icd9.kode=prosedur_pasien.kode')->where('no_rawat', revertNoRawat($no_rawat))->where('prioritas', 1)->where('status', 'Ralan')->oneArray(),
        'asesmen' => $this->db('asesmen_medis_igd')->where('no_rawat', revertNoRawat($no_rawat))->oneArray(),
        'triase' => $this->db('data_triase_igd')->where('no_rawat', revertNoRawat($no_rawat))->oneArray()
      ]);
    }
    if($status_lanjut == 'Ranap') {
      echo $this->draw('form.asesmenigd.html', [
        'status_lanjut' => $status_lanjut,
        'reg_periksa' => $this->db('reg_periksa')->where('no_rawat', revertNoRawat($no_rawat))->oneArray(),
        'kamar_inap' => $this->db('kamar_inap')->where('no_rawat', revertNoRawat($no_rawat))->oneArray(),
        'diagnosa_utama' => $this->db('diagnosa_pasien')->join('penyakit', 'penyakit.kd_penyakit=diagnosa_pasien.kd_penyakit')->where('no_rawat', revertNoRawat($no_rawat))->where('prioritas', 1)->where('diagnosa_pasien.status', 'Ranap')->oneArray(),
        'prosedur_utama' => $this->db('prosedur_pasien')->join('icd9', 'icd9.kode=prosedur_pasien.kode')->where('no_rawat', revertNoRawat($no_rawat))->where('prioritas', 1)->where('status', 'Ranap')->oneArray(),
        'asesmen' => $this->db('asesmen_medis_igd')->where('no_rawat', revertNoRawat($no_rawat))->oneArray()
      ]);
    }
    exit();
  }
  
  public function getSitb($status_lanjut, $no_rkm_medis)
  {
    
      echo $this->draw('form.sitb.html', [
        'status_lanjut' => $status_lanjut,
        'no_rkm_medis' => $no_rkm_medis,
        'sitb_pasien' => $this->db('sitb_pasien_norm')->where('sitb_pasien_norm.no_rkm_medis', $no_rkm_medis)->oneArray()
      ]);
    
    exit();
  }
  
  public function postSaveSitb()
  {

    if($this->db('sitb_pasien_norm')->where('no_rkm_medis', $_POST['no_rkm_medis'])->oneArray()) {
      $this->db('sitb_pasien_norm')
        ->where('no_rkm_medis', $_POST['no_rkm_medis'])
        ->save([
        'no_sitb'  => $_POST['no_sitb']
      ]);
    } else {
      $this->db('sitb_pasien_norm')->save([
        'no_rkm_medis' => $_POST['no_rkm_medis'],
        'no_sitb'  => $_POST['no_sitb']
      ]);
    }
    exit();
  }
  
  public function postSaveAsesmenIgd()
  {

    if($this->db('asesmen_medis_igd')->where('no_rawat', $_POST['no_rawat'])->oneArray()) {
      $this->db('asesmen_medis_igd')
        ->where('no_rawat', $_POST['no_rawat'])
        ->save([
        'rps' => $_POST['rps'],
        'ket_fisik' => $_POST['ket_fisik'],
        'diagnosis' => $_POST['diagnosis'],
        'tata' => $_POST['tata'],
        'suhu' => $_POST['suhu'],
        'td' => $_POST['td'],
        'nadi' => $_POST['nadi'],
        'rr' => $_POST['rr'],
        'spo' => $_POST['spo'],
        'gcs' => $_POST['gcs']
      ]);
    } else {
      
    }
    
    if($this->db('data_triase_igd')->where('no_rawat', $_POST['no_rawat'])->oneArray()) {
      $this->db('data_triase_igd')
        ->where('no_rawat', $_POST['no_rawat'])
        ->save([
        'nyeri' => $_POST['nyeri']
      ]);
    }
    exit();
  }
  
  public function postSaveResume()
  {

    if($this->db('resume_pasien')->where('no_rawat', $_POST['no_rawat'])->oneArray()) {
      $this->db('resume_pasien')
        ->where('no_rawat', $_POST['no_rawat'])
        ->save([
        'kd_dokter'  => $this->getRegPeriksaInfo('kd_dokter', $_POST['no_rawat']),
        'keluhan_utama' => '-',
        'jalannya_penyakit' => '-',
        'pemeriksaan_penunjang' => '-',
        'hasil_laborat' => '-',
        'diagnosa_utama' => $_POST['diagnosa_utama'],
        'kd_diagnosa_utama' => '-',
        'diagnosa_sekunder' => '-',
        'kd_diagnosa_sekunder' => '-',
        'diagnosa_sekunder2' => '-',
        'kd_diagnosa_sekunder2' => '-',
        'diagnosa_sekunder3' => '-',
        'kd_diagnosa_sekunder3' => '-',
        'diagnosa_sekunder4' => '-',
        'kd_diagnosa_sekunder4' => '-',
        'prosedur_utama' => $_POST['prosedur_utama'],
        'kd_prosedur_utama' => '-',
        'prosedur_sekunder' => '-',
        'kd_prosedur_sekunder' => '-',
        'prosedur_sekunder2' => '-',
        'kd_prosedur_sekunder2' => '-',
        'prosedur_sekunder3' => '-',
        'kd_prosedur_sekunder3' => '-',
        'kondisi_pulang'  => $_POST['kondisi_pulang'],
        'obat_pulang' => '-'
      ]);
    } else {
      $this->db('resume_pasien')->save([
        'no_rawat' => $_POST['no_rawat'],
        'kd_dokter'  => $this->getRegPeriksaInfo('kd_dokter', $_POST['no_rawat']),
        'keluhan_utama' => '-',
        'jalannya_penyakit' => '-',
        'pemeriksaan_penunjang' => '-',
        'hasil_laborat' => '-',
        'diagnosa_utama' => $_POST['diagnosa_utama'],
        'kd_diagnosa_utama' => '-',
        'diagnosa_sekunder' => '-',
        'kd_diagnosa_sekunder' => '-',
        'diagnosa_sekunder2' => '-',
        'kd_diagnosa_sekunder2' => '-',
        'diagnosa_sekunder3' => '-',
        'kd_diagnosa_sekunder3' => '-',
        'diagnosa_sekunder4' => '-',
        'kd_diagnosa_sekunder4' => '-',
        'prosedur_utama' => $_POST['prosedur_utama'],
        'kd_prosedur_utama' => '-',
        'prosedur_sekunder' => '-',
        'kd_prosedur_sekunder' => '-',
        'prosedur_sekunder2' => '-',
        'kd_prosedur_sekunder2' => '-',
        'prosedur_sekunder3' => '-',
        'kd_prosedur_sekunder3' => '-',
        'kondisi_pulang'  => $_POST['kondisi_pulang'],
        'obat_pulang' => '-'
      ]);
    }
    exit();
  }

  public function postSaveResumeRanap()
  {

    if($this->db('resume_pasien_ranap')->where('no_rawat', $_POST['no_rawat'])->oneArray()) {
      $this->db('resume_pasien_ranap')
        ->where('no_rawat', $_POST['no_rawat'])
        ->save([
        'cara_keluar'  => $_POST['cara_keluar'],
        'diagnosa_awal'  => $_POST['diagnosa_awal'],
        'keluhan_utama'  => $_POST['rps'],
        'jalannya_penyakit'  => $_POST['ket_fisik'],
        'terapi'  => $_POST['terapi']
      ]);
    } 
    exit();
  }
  
  public function getDisplayAsesmenIgd($no_rawat)
  {
    $asesmen = $this->db('asesmen_medis_igd')->where('no_rawat', revertNoRawat($no_rawat))->oneArray();
    echo $this->draw('display.asesmenigd.html', ['asesmen' => $asesmen]);
    exit();
  }

  public function getDisplayResume($no_rawat)
  {
    $resume_pasien = $this->db('resume_pasien')->where('no_rawat', revertNoRawat($no_rawat))->oneArray();
    echo $this->draw('display.resume.html', ['resume_pasien' => $resume_pasien]);
    exit();
  }
  
  public function getDisplaySitb($no_rawat)
  {
    $sitb_pasien = $this->db('sitb_pasien')->where('no_rawat', revertNoRawat($no_rawat))->oneArray();
    echo $this->draw('display.sitb.html', ['sitb_pasien' => $sitb_pasien]);
    exit();
  }

  public function getUbahDiagnosa($status_lanjut, $no_rawat)
  {
    $rawNoRawat = revertNoRawat($no_rawat);
    if (in_array($status_lanjut, ['Ralan', 'Ranap'], true)) {
      $this->_normalizeDiagnosisPriorities($rawNoRawat, $status_lanjut);
    }
    $diagnosa_pasien = $this->db('diagnosa_pasien')->join('penyakit', 'penyakit.kd_penyakit = diagnosa_pasien.kd_penyakit')->where('diagnosa_pasien.no_rawat', $rawNoRawat)->where('diagnosa_pasien.status', $status_lanjut)->asc('prioritas')->toArray();
    foreach ($diagnosa_pasien as &$diagnosa) {
      $diagnosa['valid_grouping'] = isset($diagnosa['validcode']) && (string) $diagnosa['validcode'] === '1';
      $diagnosa['primary_allowed'] = !isset($diagnosa['accpdx']) || strtoupper((string) $diagnosa['accpdx']) !== 'N';
      $diagnosa['im_only'] = isset($diagnosa['im']) && (string) $diagnosa['im'] === '1';
    }
    unset($diagnosa);
    echo $this->draw('ubah.diagnosa.validasi.html', [
      'no_rawat' => revertNoRawat($no_rawat),
      'diagnosa_pasien' => $diagnosa_pasien,
      'has_diagnosis' => count($diagnosa_pasien) > 0,
      'only_im_diagnosis' => $this->_diagnosisRowsOnlyIM($diagnosa_pasien),
      'status_lanjut' => $status_lanjut,
      'reload_url' => url([ADMIN, 'vedika', 'ubahdiagnosa', $status_lanjut, $no_rawat])
    ]);
    exit();
  }

  public function postCariDiagnosaKlaim()
  {
    $query = isset($_POST['query']) ? trim((string) $_POST['query']) : '';
    if (strlen($query) < 2) {
      return $this->jsonResponse(['ok' => true, 'items' => []]);
    }
    $like = '%' . $query . '%';
    $stmt = $this->db()->pdo()->prepare(
      "SELECT kd_penyakit AS kode, nm_penyakit AS nama, validcode, accpdx,
              code_asterisk, asterisk, im
       FROM penyakit
       WHERE kd_penyakit LIKE ? OR nm_penyakit LIKE ?
       ORDER BY CASE WHEN kd_penyakit = ? THEN 0 ELSE 1 END, kd_penyakit ASC
       LIMIT 25"
    );
    $stmt->execute([$like, $like, $query]);
    return $this->jsonResponse(['ok' => true, 'items' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
  }

  public function postSimpanDiagnosaKlaim()
  {
    $noRawat = isset($_POST['no_rawat']) ? trim((string) $_POST['no_rawat']) : '';
    $status = isset($_POST['status']) ? trim((string) $_POST['status']) : '';
    $kode = isset($_POST['kode']) ? trim((string) $_POST['kode']) : '';
    $prioritas = isset($_POST['prioritas']) ? (int) $_POST['prioritas'] : 0;
    if ($noRawat === '' || !in_array($status, ['Ralan', 'Ranap'], true) || $kode === '' || !in_array($prioritas, [1, 2], true)) {
      return $this->jsonResponse(['ok' => false, 'message' => 'Data diagnosa tidak lengkap atau prioritas tidak valid']);
    }
    if (!$this->db('reg_periksa')->where('no_rawat', $noRawat)->oneArray()) {
      return $this->jsonResponse(['ok' => false, 'message' => 'Nomor rawat tidak ditemukan']);
    }

    $master = $this->db('penyakit')->where('kd_penyakit', $kode)->oneArray();
    if (!$master || !isset($master['validcode']) || (string) $master['validcode'] !== '1') {
      return $this->jsonResponse(['ok' => false, 'message' => 'Kode ICD-10 tidak valid untuk grouping']);
    }
    if ($this->db('diagnosa_pasien')->where('no_rawat', $noRawat)->where('status', $status)->where('kd_penyakit', $kode)->oneArray()) {
      return $this->jsonResponse(['ok' => false, 'message' => 'Diagnosis tersebut sudah ada pada episode ini']);
    }

    $existing = $this->db('diagnosa_pasien')->join('penyakit', 'penyakit.kd_penyakit = diagnosa_pasien.kd_penyakit')->where('diagnosa_pasien.no_rawat', $noRawat)->where('diagnosa_pasien.status', $status)->asc('diagnosa_pasien.prioritas')->toArray();
    if (count($existing) >= 9) {
      return $this->jsonResponse(['ok' => false, 'message' => 'Maksimal 9 diagnosis dalam satu layanan']);
    }
    $primaryAllowed = !isset($master['accpdx']) || strtoupper((string) $master['accpdx']) !== 'N';
    $hasPrimary = false;
    foreach ($existing as $existingDiagnosis) {
      if ((int) $existingDiagnosis['prioritas'] === 1
          && isset($existingDiagnosis['validcode']) && (string) $existingDiagnosis['validcode'] === '1'
          && (!isset($existingDiagnosis['accpdx']) || strtoupper((string) $existingDiagnosis['accpdx']) !== 'N')) {
        $hasPrimary = true;
        break;
      }
    }
    if (!$existing) {
      if (!$primaryAllowed) {
        return $this->jsonResponse(['ok' => false, 'message' => 'Diagnosis ini hanya boleh menjadi sekunder. Tambahkan diagnosis utama terlebih dahulu']);
      }
      $prioritas = 1;
    } elseif (!$primaryAllowed && ($prioritas === 1 || !$hasPrimary)) {
      return $this->jsonResponse(['ok' => false, 'message' => 'Diagnosis ini hanya boleh menjadi sekunder dan membutuhkan diagnosis utama berprioritas 1']);
    }
    if ($prioritas !== 1) {
      $prioritas = count($existing) + 1;
    }

    $pdo = $this->db()->pdo();
    $pdo->beginTransaction();
    try {
      if ($prioritas === 1 && $existing) {
        $shift = $pdo->prepare('UPDATE diagnosa_pasien SET prioritas = prioritas + 1 WHERE no_rawat = ? AND status = ?');
        $shift->execute([$noRawat, $status]);
      }
      $save = $pdo->prepare('INSERT INTO diagnosa_pasien (no_rawat, kd_penyakit, status, prioritas, status_penyakit) VALUES (?, ?, ?, ?, ?)');
      $save->execute([$noRawat, $kode, $status, $prioritas, 'Baru']);
      $this->_normalizeDiagnosisPriorities($noRawat, $status, $pdo);
      $pdo->commit();
    } catch (\Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      return $this->jsonResponse(['ok' => false, 'message' => 'Diagnosis gagal disimpan: ' . $e->getMessage()]);
    }
    return $this->jsonResponse(['ok' => true, 'message' => 'Diagnosis berhasil disimpan']);
  }

  public function getDisplayDiagnosa($status_lanjut, $no_rawat)
  {
    $diagnosa_pasien = $this->db('diagnosa_pasien')->join('penyakit', 'penyakit.kd_penyakit = diagnosa_pasien.kd_penyakit')->where('diagnosa_pasien.no_rawat', revertNoRawat($no_rawat))->where('diagnosa_pasien.status', $status_lanjut)->asc('prioritas')->toArray();
    echo $this->draw('display.diagnosa.html', ['no_rawat' => revertNoRawat($no_rawat), 'diagnosa_pasien' => $diagnosa_pasien, 'status_lanjut' => $status_lanjut]);
    exit();
  }
  
  

  public function postHapusDiagnosa()
  {
    $noRawat = isset($_POST['no_rawat']) ? trim((string) $_POST['no_rawat']) : '';
    $kode = isset($_POST['kd_penyakit']) ? trim((string) $_POST['kd_penyakit']) : '';
    $status = isset($_POST['status']) ? trim((string) $_POST['status']) : '';
    if ($noRawat === '' || $kode === '' || !in_array($status, ['Ralan', 'Ranap'], true)) {
      return $this->jsonResponse(['ok' => false, 'message' => 'Data diagnosis tidak lengkap']);
    }
    $target = $this->db('diagnosa_pasien')->where('no_rawat', $noRawat)->where('status', $status)->where('kd_penyakit', $kode)->oneArray();
    if (!$target) {
      return $this->jsonResponse(['ok' => false, 'message' => 'Diagnosis tidak ditemukan']);
    }
    if ((int) $target['prioritas'] === 1) {
      $stmt = $this->db()->pdo()->prepare(
        "SELECT COUNT(*) AS total,
                SUM(CASE WHEN COALESCE(p.accpdx, 'Y') <> 'N' AND p.validcode = '1' THEN 1 ELSE 0 END) AS primary_allowed
         FROM diagnosa_pasien d LEFT JOIN penyakit p ON p.kd_penyakit = d.kd_penyakit
         WHERE d.no_rawat = ? AND d.status = ? AND d.kd_penyakit <> ?"
      );
      $stmt->execute([$noRawat, $status, $kode]);
      $remaining = $stmt->fetch(\PDO::FETCH_ASSOC);
      if ((int) $remaining['total'] > 0 && (int) $remaining['primary_allowed'] === 0) {
        return $this->jsonResponse(['ok' => false, 'message' => 'Tambahkan diagnosis utama pengganti sebelum menghapus diagnosis utama ini']);
      }
    }
    $pdo = $this->db()->pdo();
    $pdo->beginTransaction();
    try {
      $delete = $pdo->prepare('DELETE FROM diagnosa_pasien WHERE no_rawat = ? AND status = ? AND kd_penyakit = ?');
      $delete->execute([$noRawat, $status, $kode]);
      $this->_normalizeDiagnosisPriorities($noRawat, $status, $pdo);
      $pdo->commit();
    } catch (\Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      return $this->jsonResponse(['ok' => false, 'message' => 'Diagnosis gagal dihapus: ' . $e->getMessage()]);
    }
    return $this->jsonResponse(['ok' => true, 'message' => 'Diagnosis berhasil dihapus']);
  }

  public function postJadikanDiagnosaUtama()
  {
    $noRawat = isset($_POST['no_rawat']) ? trim((string) $_POST['no_rawat']) : '';
    $status = isset($_POST['status']) ? trim((string) $_POST['status']) : '';
    $kode = isset($_POST['kode']) ? trim((string) $_POST['kode']) : '';
    if ($noRawat === '' || $kode === '' || !in_array($status, ['Ralan', 'Ranap'], true)) {
      return $this->jsonResponse(['ok' => false, 'message' => 'Data diagnosis tidak lengkap']);
    }
    $master = $this->db('penyakit')->where('kd_penyakit', $kode)->oneArray();
    if (!$master || (string) $master['validcode'] !== '1' || (isset($master['accpdx']) && strtoupper((string) $master['accpdx']) === 'N')) {
      return $this->jsonResponse(['ok' => false, 'message' => 'Diagnosis ini tidak dapat dijadikan diagnosis utama']);
    }
    if (!$this->db('diagnosa_pasien')->where('no_rawat', $noRawat)->where('status', $status)->where('kd_penyakit', $kode)->oneArray()) {
      return $this->jsonResponse(['ok' => false, 'message' => 'Diagnosis tidak ditemukan']);
    }
    $pdo = $this->db()->pdo();
    $pdo->beginTransaction();
    try {
      $move = $pdo->prepare('UPDATE diagnosa_pasien SET prioritas = 0 WHERE no_rawat = ? AND status = ? AND kd_penyakit = ?');
      $move->execute([$noRawat, $status, $kode]);
      $this->_normalizeDiagnosisPriorities($noRawat, $status, $pdo);
      $pdo->commit();
    } catch (\Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      return $this->jsonResponse(['ok' => false, 'message' => 'Diagnosis utama gagal diubah: ' . $e->getMessage()]);
    }
    return $this->jsonResponse(['ok' => true, 'message' => 'Diagnosis utama berhasil diubah']);
  }

  public function postSubstitusiDiagnosaKlaim()
  {
    $noRawat = isset($_POST['no_rawat']) ? trim((string) $_POST['no_rawat']) : '';
    $status = isset($_POST['status']) ? trim((string) $_POST['status']) : '';
    $kodeLama = isset($_POST['kode_lama']) ? trim((string) $_POST['kode_lama']) : '';
    $kodeBaru = isset($_POST['kode_baru']) ? trim((string) $_POST['kode_baru']) : '';
    if ($noRawat === '' || $kodeLama === '' || $kodeBaru === '' || !in_array($status, ['Ralan', 'Ranap'], true)) {
      return $this->jsonResponse(['ok' => false, 'message' => 'Data substitusi diagnosis tidak lengkap']);
    }
    $target = $this->db('diagnosa_pasien')->where('no_rawat', $noRawat)->where('status', $status)->where('kd_penyakit', $kodeLama)->oneArray();
    $master = $this->db('penyakit')->where('kd_penyakit', $kodeBaru)->oneArray();
    if (!$target) return $this->jsonResponse(['ok' => false, 'message' => 'Diagnosis yang akan diganti tidak ditemukan']);
    if (!$master || (string) $master['validcode'] !== '1') return $this->jsonResponse(['ok' => false, 'message' => 'Kode diagnosis pengganti tidak valid untuk grouping']);
    if ((int) $target['prioritas'] === 1 && isset($master['accpdx']) && strtoupper((string) $master['accpdx']) === 'N') {
      return $this->jsonResponse(['ok' => false, 'message' => 'Kode pengganti hanya boleh menjadi diagnosis sekunder']);
    }
    if ($kodeLama !== $kodeBaru && $this->db('diagnosa_pasien')->where('no_rawat', $noRawat)->where('status', $status)->where('kd_penyakit', $kodeBaru)->oneArray()) {
      return $this->jsonResponse(['ok' => false, 'message' => 'Diagnosis pengganti sudah ada pada episode ini']);
    }
    $pdo = $this->db()->pdo();
    $pdo->beginTransaction();
    try {
      $replace = $pdo->prepare('UPDATE diagnosa_pasien SET kd_penyakit = ? WHERE no_rawat = ? AND status = ? AND kd_penyakit = ?');
      $replace->execute([$kodeBaru, $noRawat, $status, $kodeLama]);
      $this->_normalizeDiagnosisPriorities($noRawat, $status, $pdo);
      $pdo->commit();
    } catch (\Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      return $this->jsonResponse(['ok' => false, 'message' => 'Diagnosis gagal disubstitusi: ' . $e->getMessage()]);
    }
    return $this->jsonResponse(['ok' => true, 'message' => 'Diagnosis berhasil disubstitusi']);
  }

  private function _normalizeDiagnosisPriorities($noRawat, $status, $pdo = null)
  {
    $pdo = $pdo ?: $this->db()->pdo();
    $stmt = $pdo->prepare(
      "SELECT d.kd_penyakit, d.prioritas, p.validcode, p.accpdx
       FROM diagnosa_pasien d LEFT JOIN penyakit p ON p.kd_penyakit = d.kd_penyakit
       WHERE d.no_rawat = ? AND d.status = ? ORDER BY d.prioritas ASC, d.kd_penyakit ASC"
    );
    $stmt->execute([$noRawat, $status]);
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    if (!$rows) return;

    foreach ($rows as $index => $row) {
      $allowed = (string) $row['validcode'] === '1' && strtoupper((string) $row['accpdx']) !== 'N';
      if ($allowed) {
        if ($index > 0) {
          unset($rows[$index]);
          array_unshift($rows, $row);
          $rows = array_values($rows);
        }
        break;
      }
    }
    $update = $pdo->prepare('UPDATE diagnosa_pasien SET prioritas = ? WHERE no_rawat = ? AND status = ? AND kd_penyakit = ?');
    foreach ($rows as $index => $row) {
      $update->execute([$index + 1, $noRawat, $status, $row['kd_penyakit']]);
    }
  }

  public function getUbahProsedur($status_lanjut, $no_rawat)
  {
    $rawNoRawat = revertNoRawat($no_rawat);
    if (in_array($status_lanjut, ['Ralan', 'Ranap'], true)) {
      $this->_normalizeProcedurePriorities($rawNoRawat, $status_lanjut);
    }
    $prosedur_pasien = $this->db('prosedur_pasien')->join('icd9', 'icd9.kode = prosedur_pasien.kode')->where('prosedur_pasien.no_rawat', $rawNoRawat)->where('prosedur_pasien.status', $status_lanjut)->asc('prioritas')->toArray();
    foreach ($prosedur_pasien as &$prosedur) {
      $prosedur['valid_grouping'] = isset($prosedur['validcode']) && (string) $prosedur['validcode'] === '1';
      $prosedur['volume'] = $this->_getProcedureVolume($prosedur['no_rawat'], $prosedur['kode'], $status_lanjut);
    }
    unset($prosedur);
    echo $this->draw('ubah.prosedur.validasi.html', [
      'no_rawat' => revertNoRawat($no_rawat),
      'prosedur_pasien' => $prosedur_pasien,
      'has_procedure' => count($prosedur_pasien) > 0,
      'status_lanjut' => $status_lanjut,
      'reload_url' => url([ADMIN, 'vedika', 'ubahprosedur', $status_lanjut, $no_rawat]),
      'volumes' => range(1, 9)
    ]);
    exit();
  }

  public function postCariProsedurKlaim()
  {
    $query = isset($_POST['query']) ? trim((string) $_POST['query']) : '';
    if (strlen($query) < 2) return $this->jsonResponse(['ok' => true, 'items' => []]);
    $like = '%' . $query . '%';
    $stmt = $this->db()->pdo()->prepare(
      "SELECT kode, deskripsi_panjang AS nama, validcode, im
       FROM icd9 WHERE kode LIKE ? OR deskripsi_panjang LIKE ?
       ORDER BY CASE WHEN kode = ? THEN 0 ELSE 1 END, kode ASC LIMIT 25"
    );
    $stmt->execute([$like, $like, $query]);
    return $this->jsonResponse(['ok' => true, 'items' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
  }

  public function postSimpanProsedurKlaim()
  {
    $noRawat = isset($_POST['no_rawat']) ? trim((string) $_POST['no_rawat']) : '';
    $status = isset($_POST['status']) ? trim((string) $_POST['status']) : '';
    $kode = isset($_POST['kode']) ? trim((string) $_POST['kode']) : '';
    $prioritas = isset($_POST['prioritas']) ? (int) $_POST['prioritas'] : 0;
    $volume = isset($_POST['volume']) ? (int) $_POST['volume'] : 1;
    if ($noRawat === '' || !in_array($status, ['Ralan', 'Ranap'], true) || $kode === '' || !in_array($prioritas, [1, 2], true) || $volume < 1 || $volume > 9) {
      return $this->jsonResponse(['ok' => false, 'message' => 'Data prosedur, prioritas, atau volume tidak valid']);
    }
    if (!$this->db('reg_periksa')->where('no_rawat', $noRawat)->oneArray()) {
      return $this->jsonResponse(['ok' => false, 'message' => 'Nomor rawat tidak ditemukan']);
    }
    $master = $this->db('icd9')->where('kode', $kode)->oneArray();
    if (!$master || !isset($master['validcode']) || (string) $master['validcode'] !== '1') {
      return $this->jsonResponse(['ok' => false, 'message' => 'Kode ICD-9 tidak valid untuk grouping']);
    }
    if ($this->db('prosedur_pasien')->where('no_rawat', $noRawat)->where('status', $status)->where('kode', $kode)->oneArray()) {
      return $this->jsonResponse(['ok' => false, 'message' => 'Prosedur tersebut sudah ada pada episode ini']);
    }
    $existing = $this->db('prosedur_pasien')->where('no_rawat', $noRawat)->where('status', $status)->asc('prioritas')->toArray();
    if (count($existing) >= 9) {
      return $this->jsonResponse(['ok' => false, 'message' => 'Maksimal 9 prosedur dalam satu layanan']);
    }
    if (!$existing) {
      $prioritas = 1;
    } elseif ($prioritas !== 1) {
      $prioritas = count($existing) + 1;
    }
    $pdo = $this->db()->pdo();
    $pdo->beginTransaction();
    try {
      if ($prioritas === 1 && $existing) {
        $shift = $pdo->prepare('UPDATE prosedur_pasien SET prioritas = prioritas + 1 WHERE no_rawat = ? AND status = ?');
        $shift->execute([$noRawat, $status]);
      }
      $save = $pdo->prepare('INSERT INTO prosedur_pasien (no_rawat, kode, status, prioritas) VALUES (?, ?, ?, ?)');
      $save->execute([$noRawat, $kode, $status, $prioritas]);
      $this->_saveProcedureVolume($noRawat, $kode, $status, $volume);
      $this->_normalizeProcedurePriorities($noRawat, $status, $pdo);
      $pdo->commit();
    } catch (\Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      return $this->jsonResponse(['ok' => false, 'message' => 'Prosedur gagal disimpan: ' . $e->getMessage()]);
    }
    return $this->jsonResponse(['ok' => true, 'message' => 'Prosedur berhasil disimpan']);
  }

  public function postSimpanVolumeProsedur()
  {
    $noRawat = isset($_POST['no_rawat']) ? trim((string) $_POST['no_rawat']) : '';
    $kode = isset($_POST['kode']) ? trim((string) $_POST['kode']) : '';
    $status = isset($_POST['status']) ? trim((string) $_POST['status']) : '';
    $volume = isset($_POST['volume']) ? (int) $_POST['volume'] : 0;
    if ($noRawat === '' || $kode === '' || !in_array($status, ['Ralan', 'Ranap'], true) || $volume < 1 || $volume > 9) {
      return $this->jsonResponse(['ok' => false, 'message' => 'Data prosedur atau volume tidak valid']);
    }
    if (!$this->db('prosedur_pasien')->where('no_rawat', $noRawat)->where('kode', $kode)->where('status', $status)->oneArray()) {
      return $this->jsonResponse(['ok' => false, 'message' => 'Prosedur tidak ditemukan pada episode ini']);
    }
    $master = $this->db('icd9')->where('kode', $kode)->oneArray();
    if (!$master || !isset($master['validcode']) || (string) $master['validcode'] !== '1') {
      return $this->jsonResponse(['ok' => false, 'message' => 'Volume tidak dapat disimpan karena kode tidak valid untuk grouping']);
    }
    $this->_saveProcedureVolume($noRawat, $kode, $status, $volume);
    return $this->jsonResponse(['ok' => true, 'message' => 'Volume prosedur diperbarui']);
  }

  private function _getProcedureVolume($noRawat, $kode, $status)
  {
    $row = $this->db('mlite_vedika_procedure_volume')->where('no_rawat', $noRawat)->where('kode', $kode)->where('status', $status)->oneArray();
    return $row && isset($row['volume']) ? max(1, min(9, (int) $row['volume'])) : 1;
  }

  private function _saveProcedureVolume($noRawat, $kode, $status, $volume)
  {
    $pdo = $this->db()->pdo();
    $stmt = $pdo->prepare(
      'INSERT INTO mlite_vedika_procedure_volume (no_rawat, kode, status, volume, updated_by, updated_at)
       VALUES (?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE volume = VALUES(volume), updated_by = VALUES(updated_by), updated_at = NOW()'
    );
    $stmt->execute([$noRawat, $kode, $status, $volume, (string) $this->core->getUserInfo('username', null, true)]);
  }

  private function _procedureCodeWithVolume($noRawat, $kode, $status)
  {
    $volume = $this->_getProcedureVolume($noRawat, $kode, $status);
    return $volume > 1 ? $kode . '+' . $volume : $kode;
  }

  public function getDisplayProsedur($status_lanjut, $no_rawat)
  {
    $prosedur_pasien = $this->db('prosedur_pasien')->join('icd9', 'icd9.kode = prosedur_pasien.kode')->where('prosedur_pasien.no_rawat', revertNoRawat($no_rawat))->where('prosedur_pasien.status', $status_lanjut)->asc('prioritas')->toArray();
    echo $this->draw('display.prosedur.html', ['no_rawat' => revertNoRawat($no_rawat), 'prosedur_pasien' => $prosedur_pasien, 'status_lanjut' => $status_lanjut]);
    exit();
  }

  public function postHapusProsedur()
  {
    $noRawat = isset($_POST['no_rawat']) ? trim((string) $_POST['no_rawat']) : '';
    $kode = isset($_POST['kode']) ? trim((string) $_POST['kode']) : '';
    $status = isset($_POST['status']) ? trim((string) $_POST['status']) : '';
    if ($noRawat === '' || $kode === '' || !in_array($status, ['Ralan', 'Ranap'], true)) {
      return $this->jsonResponse(['ok' => false, 'message' => 'Data prosedur tidak lengkap']);
    }
    $pdo = $this->db()->pdo();
    $pdo->beginTransaction();
    try {
      $delete = $pdo->prepare('DELETE FROM prosedur_pasien WHERE no_rawat = ? AND status = ? AND kode = ?');
      $delete->execute([$noRawat, $status, $kode]);
      $deleteVolume = $pdo->prepare('DELETE FROM mlite_vedika_procedure_volume WHERE no_rawat = ? AND status = ? AND kode = ?');
      $deleteVolume->execute([$noRawat, $status, $kode]);
      $this->_normalizeProcedurePriorities($noRawat, $status, $pdo);
      $pdo->commit();
    } catch (\Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      return $this->jsonResponse(['ok' => false, 'message' => 'Prosedur gagal dihapus: ' . $e->getMessage()]);
    }
    return $this->jsonResponse(['ok' => true, 'message' => 'Prosedur berhasil dihapus']);
  }

  public function postJadikanProsedurUtama()
  {
    $noRawat = isset($_POST['no_rawat']) ? trim((string) $_POST['no_rawat']) : '';
    $status = isset($_POST['status']) ? trim((string) $_POST['status']) : '';
    $kode = isset($_POST['kode']) ? trim((string) $_POST['kode']) : '';
    if ($noRawat === '' || $kode === '' || !in_array($status, ['Ralan', 'Ranap'], true)) {
      return $this->jsonResponse(['ok' => false, 'message' => 'Data prosedur tidak lengkap']);
    }
    $master = $this->db('icd9')->where('kode', $kode)->oneArray();
    if (!$master || (string) $master['validcode'] !== '1') {
      return $this->jsonResponse(['ok' => false, 'message' => 'Prosedur ini tidak valid untuk dijadikan prosedur utama']);
    }
    if (!$this->db('prosedur_pasien')->where('no_rawat', $noRawat)->where('status', $status)->where('kode', $kode)->oneArray()) {
      return $this->jsonResponse(['ok' => false, 'message' => 'Prosedur tidak ditemukan']);
    }
    $pdo = $this->db()->pdo();
    $pdo->beginTransaction();
    try {
      $move = $pdo->prepare('UPDATE prosedur_pasien SET prioritas = 0 WHERE no_rawat = ? AND status = ? AND kode = ?');
      $move->execute([$noRawat, $status, $kode]);
      $this->_normalizeProcedurePriorities($noRawat, $status, $pdo);
      $pdo->commit();
    } catch (\Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      return $this->jsonResponse(['ok' => false, 'message' => 'Prosedur utama gagal diubah: ' . $e->getMessage()]);
    }
    return $this->jsonResponse(['ok' => true, 'message' => 'Prosedur utama berhasil diubah']);
  }

  public function postSubstitusiProsedurKlaim()
  {
    $noRawat = isset($_POST['no_rawat']) ? trim((string) $_POST['no_rawat']) : '';
    $status = isset($_POST['status']) ? trim((string) $_POST['status']) : '';
    $kodeLama = isset($_POST['kode_lama']) ? trim((string) $_POST['kode_lama']) : '';
    $kodeBaru = isset($_POST['kode_baru']) ? trim((string) $_POST['kode_baru']) : '';
    if ($noRawat === '' || $kodeLama === '' || $kodeBaru === '' || !in_array($status, ['Ralan', 'Ranap'], true)) {
      return $this->jsonResponse(['ok' => false, 'message' => 'Data substitusi prosedur tidak lengkap']);
    }
    $target = $this->db('prosedur_pasien')->where('no_rawat', $noRawat)->where('status', $status)->where('kode', $kodeLama)->oneArray();
    $master = $this->db('icd9')->where('kode', $kodeBaru)->oneArray();
    if (!$target) return $this->jsonResponse(['ok' => false, 'message' => 'Prosedur yang akan diganti tidak ditemukan']);
    if (!$master || (string) $master['validcode'] !== '1') return $this->jsonResponse(['ok' => false, 'message' => 'Kode prosedur pengganti tidak valid untuk grouping']);
    if ($kodeLama !== $kodeBaru && $this->db('prosedur_pasien')->where('no_rawat', $noRawat)->where('status', $status)->where('kode', $kodeBaru)->oneArray()) {
      return $this->jsonResponse(['ok' => false, 'message' => 'Prosedur pengganti sudah ada pada episode ini']);
    }
    $volume = $this->_getProcedureVolume($noRawat, $kodeLama, $status);
    $pdo = $this->db()->pdo();
    $pdo->beginTransaction();
    try {
      $replace = $pdo->prepare('UPDATE prosedur_pasien SET kode = ? WHERE no_rawat = ? AND status = ? AND kode = ?');
      $replace->execute([$kodeBaru, $noRawat, $status, $kodeLama]);
      if ($kodeLama !== $kodeBaru) {
        $deleteVolume = $pdo->prepare('DELETE FROM mlite_vedika_procedure_volume WHERE no_rawat = ? AND status = ? AND kode = ?');
        $deleteVolume->execute([$noRawat, $status, $kodeLama]);
        $this->_saveProcedureVolume($noRawat, $kodeBaru, $status, $volume);
      }
      $this->_normalizeProcedurePriorities($noRawat, $status, $pdo);
      $pdo->commit();
    } catch (\Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      return $this->jsonResponse(['ok' => false, 'message' => 'Prosedur gagal disubstitusi: ' . $e->getMessage()]);
    }
    return $this->jsonResponse(['ok' => true, 'message' => 'Prosedur berhasil disubstitusi']);
  }

  private function _normalizeProcedurePriorities($noRawat, $status, $pdo = null)
  {
    $pdo = $pdo ?: $this->db()->pdo();
    $stmt = $pdo->prepare('SELECT kode FROM prosedur_pasien WHERE no_rawat = ? AND status = ? ORDER BY prioritas ASC, kode ASC');
    $stmt->execute([$noRawat, $status]);
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    $update = $pdo->prepare('UPDATE prosedur_pasien SET prioritas = ? WHERE no_rawat = ? AND status = ? AND kode = ?');
    foreach ($rows as $index => $row) {
      $update->execute([$index + 1, $noRawat, $status, $row['kode']]);
    }
  }

  public function getBridgingInacbgs($no_rawat)
  {
    $reg_periksa = $this->db('reg_periksa')
      ->join('pasien', 'pasien.no_rkm_medis=reg_periksa.no_rkm_medis')
      ->join('poliklinik', 'poliklinik.kd_poli=reg_periksa.kd_poli')
      ->join('dokter', 'dokter.kd_dokter=reg_periksa.kd_dokter')
      ->join('penjab', 'penjab.kd_pj=reg_periksa.kd_pj')
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->oneArray();
    $no_rkm_medis = $this->db('reg_periksa')->select('no_rkm_medis')->where('no_rawat', revertNoRawat($no_rawat))->oneArray();
    $sitb = $this->db('sitb_pasien_norm')->where('sitb_pasien_norm.no_rkm_medis', $no_rkm_medis)->oneArray();
    $jk = $this->db('pasien')->where('pasien.no_rkm_medis', $no_rkm_medis)->oneArray();
    $pemeriksaan = $this->db('pemeriksaan_ralan')->where('no_rawat', $reg_periksa['no_rawat'])->limit(1)->desc('tgl_perawatan')->desc('jam_rawat')->toArray();
    $reg_periksa['sistole'] = strtok($pemeriksaan[0]['tensi'], '/');
    $reg_periksa['diastole'] = substr($pemeriksaan[0]['tensi'], strpos($pemeriksaan[0]['tensi'], '/') + 1);
    if($reg_periksa['status_lanjut'] == 'Ranap') {
      $pemeriksaan = $this->db('pemeriksaan_ranap')->where('no_rawat', $reg_periksa['no_rawat'])->limit(1)->desc('tgl_perawatan')->desc('jam_rawat')->toArray();
      $reg_periksa['sistole'] = strtok($pemeriksaan[0]['tensi'], '/');
      $reg_periksa['diastole'] = substr($pemeriksaan[0]['tensi'], strpos($pemeriksaan[0]['tensi'], '/') + 1);
    }
    $reg_periksa['no_sep'] = $this->_getSEPInfo('no_sep', revertNoRawat($no_rawat));
    $reg_periksa['kelas_rawat'] = $this->_getSEPInfo('klsrawat', revertNoRawat($no_rawat));
    $reg_periksa['stts_pulang'] = '';
    $reg_periksa['tgl_keluar'] = $reg_periksa['tgl_registrasi'];
    if($reg_periksa['status_lanjut'] == 'Ranap') {
      $_get_kamar_inap = $this->db('kamar_inap')->where('no_rawat', revertNoRawat($no_rawat))->limit(1)->desc('tgl_keluar')->toArray();
      $_get_kamar_inap_in = $this->db('kamar_inap')->where('no_rawat', revertNoRawat($no_rawat))->limit(1)->asc('tgl_masuk')->toArray();
      $reg_periksa['tgl_registrasi'] = $_get_kamar_inap_in[0]['tgl_masuk'].' '.$_get_kamar_inap_in[0]['jam_masuk'];
      $reg_periksa['tgl_keluar'] = $_get_kamar_inap[0]['tgl_keluar'].' '.$_get_kamar_inap[0]['jam_keluar'];
      $reg_periksa['stts_pulang'] = $_get_kamar_inap[0]['stts_pulang'];
      $get_kamar = $this->db('kamar')->where('kd_kamar', $_get_kamar_inap[0]['kd_kamar'])->oneArray();
      $get_bangsal = $this->db('bangsal')->where('kd_bangsal', $get_kamar['kd_bangsal'])->oneArray();
      $reg_periksa['nm_poli'] = $get_bangsal['nm_bangsal'].'/'.$get_kamar['kd_kamar'];
      $reg_periksa['nm_dokter'] = $this->db('dpjp_ranap')
        ->join('dokter', 'dokter.kd_dokter=dpjp_ranap.kd_dokter')
        ->where('no_rawat', revertNoRawat($no_rawat))
        ->toArray();
    }

    if($reg_periksa['status_lanjut'] == 'Ranap') {
    $row_diagnosa = $this->db('diagnosa_pasien')
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->where('status', 'Ranap')
      ->asc('prioritas')
      ->toArray();
    $a_diagnosa=1;
    $penyakit = '';
    foreach ($row_diagnosa as $row) {
      if($a_diagnosa==1){
          $penyakit=$row["kd_penyakit"];
      }else{
          $penyakit=$penyakit."#".$row["kd_penyakit"];
      }
      $a_diagnosa++;
    }
    } else {
    $row_diagnosa = $this->db('diagnosa_pasien')
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->where('status', 'Ralan')
      ->asc('prioritas')
      ->toArray();
    $a_diagnosa=1;
    $penyakit = '';
    foreach ($row_diagnosa as $row) {
      if($a_diagnosa==1){
          $penyakit=$row["kd_penyakit"];
      }else{
          $penyakit=$penyakit."#".$row["kd_penyakit"];
      }
      $a_diagnosa++;
    }
    }

    if($reg_periksa['status_lanjut'] == 'Ranap') {
    $row_prosedur = $this->db('prosedur_pasien')
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->where('status', 'Ranap')
      ->asc('prioritas')
      ->toArray();
    $prosedur= '';
    $a_prosedur=1;
    foreach ($row_prosedur as $row) {
      $kodeKlaim = $this->_procedureCodeWithVolume(revertNoRawat($no_rawat), $row['kode'], 'Ranap');
      if($a_prosedur==1){
          $prosedur=$kodeKlaim;
      }else{
          $prosedur=$prosedur."#".$kodeKlaim;
      }
      $a_prosedur++;
    }
    }else {
      $row_prosedur = $this->db('prosedur_pasien')
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->where('status', 'Ralan')
      ->asc('prioritas')
      ->toArray();
    $prosedur= '';
    $a_prosedur=1;
    foreach ($row_prosedur as $row) {
      $kodeKlaim = $this->_procedureCodeWithVolume(revertNoRawat($no_rawat), $row['kode'], 'Ralan');
      if($a_prosedur==1){
          $prosedur=$kodeKlaim;
      }else{
          $prosedur=$prosedur."#".$kodeKlaim;
      }
      $a_prosedur++;
    }
      }

    /* Prosedur non bedah ralan */
    $biaya_non_bedah_dr = $this->db('rawat_jl_dr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_jl_dr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_prosedur_non_bedah'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_non_bedah_pr = $this->db('rawat_jl_pr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_jl_pr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_prosedur_non_bedah'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_non_bedah_drpr = $this->db('rawat_jl_drpr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_jl_drpr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_prosedur_non_bedah'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    /* End prosedur non bedah ralan */

    /* Prosedur non bedah ranap */
    $biaya_non_bedah_dr_ranap = $this->db('rawat_inap_dr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_inap_dr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_prosedur_non_bedah'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_non_bedah_pr_ranap = $this->db('rawat_inap_pr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_inap_pr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_prosedur_non_bedah'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_non_bedah_drpr_ranap = $this->db('rawat_inap_drpr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_inap_drpr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_prosedur_non_bedah'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    /* End prosedur non bedah ranap */

    $total_biaya_non_bedah = 0;
    foreach (array_merge($biaya_non_bedah_dr, $biaya_non_bedah_pr, $biaya_non_bedah_drpr, $biaya_non_bedah_dr_ranap, $biaya_non_bedah_pr_ranap, $biaya_non_bedah_drpr_ranap) as $row) {
      $total_biaya_non_bedah += $row['biaya_rawat'];
    }

    /* Prosedur bedah ralan */
    $biaya_bedah_dr = $this->db('rawat_jl_dr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_jl_dr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_prosedur_bedah'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_bedah_pr = $this->db('rawat_jl_pr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_jl_pr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_prosedur_bedah'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_bedah_drpr = $this->db('rawat_jl_drpr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_jl_drpr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_prosedur_bedah'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    /* End prosedur bedah ralan */

    /* Prosedur bedah ranap */
    $biaya_bedah_dr_ranap = $this->db('rawat_inap_dr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_inap_dr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_prosedur_bedah'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_bedah_pr_ranap = $this->db('rawat_inap_pr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_inap_pr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_prosedur_bedah'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_bedah_drpr_ranap = $this->db('rawat_inap_drpr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_inap_drpr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_prosedur_bedah'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    /* End prosedur bedah ranap */

    /* Start biaya operasi */
    $biaya_operasi = $this->db('operasi')
      ->select(['biaya_rawat' => 'SUM(biayaoperator1 + biayaoperator2 + biayaoperator3 + biayaasisten_operator1 + biayaasisten_operator2 + biayadokter_anak + biayaperawaat_resusitas + biayadokter_anestesi + biayaasisten_anestesi + biayabidan + biayaperawat_luar)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->where('status', 'Ralan')
      ->toArray();

    if($reg_periksa['status_lanjut'] == 'Ranap') {
      $biaya_operasi = $this->db('operasi')
        ->select(['biaya_rawat' => 'SUM(biayaoperator1 + biayaoperator2 + biayaoperator3 + biayaasisten_operator1 + biayaasisten_operator2 + biayadokter_anak + biayaperawaat_resusitas + biayadokter_anestesi + biayaasisten_anestesi + biayabidan + biayaperawat_luar)'])
        ->where('no_rawat', revertNoRawat($no_rawat))
        ->where('status', 'Ranap')
        ->toArray();
    }
    /* End biaya operasi */

    $total_biaya_bedah = 0;
    foreach (array_merge($biaya_bedah_dr, $biaya_bedah_pr, $biaya_bedah_drpr, $biaya_bedah_dr_ranap, $biaya_bedah_pr_ranap, $biaya_bedah_drpr_ranap, $biaya_operasi) as $row) {
      $total_biaya_bedah += $row['biaya_rawat'];
    }

    /* Biaya Konsultasi */
    $biaya_poliklinik = $this->db('reg_periksa')
      ->select(['biaya_rawat' => 'SUM(registrasi)'])
      ->join('poliklinik', 'poliklinik.kd_poli=reg_periksa.kd_poli')
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_konsultasi_dr = $this->db('rawat_jl_dr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_jl_dr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_konsultasi'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_konsultasi_pr = $this->db('rawat_jl_pr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_jl_pr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_konsultasi'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_konsultasi_drpr = $this->db('rawat_jl_drpr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_jl_drpr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_konsultasi'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_visit_dr = $this->db('rawat_inap_dr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw=rawat_inap_dr.kd_jenis_prw')
      ->where('jns_perawatan_inap.kd_kategori', $this->settings->get('vedika.inacbgs_konsultasi'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_visit_pr = $this->db('rawat_inap_pr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw=rawat_inap_pr.kd_jenis_prw')
      ->where('jns_perawatan_inap.kd_kategori', $this->settings->get('vedika.inacbgs_konsultasi'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_visit_drpr = $this->db('rawat_inap_drpr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw=rawat_inap_drpr.kd_jenis_prw')
      ->where('jns_perawatan_inap.kd_kategori', $this->settings->get('vedika.inacbgs_konsultasi'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    /* End Biaya Konsultasi */

    $total_biaya_konsultasi = 0;
    foreach (array_merge($biaya_poliklinik, $biaya_konsultasi_dr, $biaya_konsultasi_pr, $biaya_konsultasi_drpr, $biaya_visit_dr,$biaya_visit_pr, $biaya_visit_drpr) as $row) {
      $total_biaya_konsultasi += $row['biaya_rawat'];
    }

    /* Biaya Tenaga Ahli */
    $biaya_tenaga_ahli_dr = $this->db('rawat_inap_dr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_inap_dr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_tenaga_ahli'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_tenaga_ahli_pr = $this->db('rawat_inap_pr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_inap_pr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_tenaga_ahli'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_tenaga_ahli_drpr = $this->db('rawat_inap_drpr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_inap_drpr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_tenaga_ahli'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    /* End Biaya Tenaga Ahli */

    $total_biaya_tenaga_ahli = 0;
    foreach (array_merge($biaya_tenaga_ahli_dr, $biaya_tenaga_ahli_pr, $biaya_tenaga_ahli_drpr) as $row) {
      $total_biaya_tenaga_ahli += $row['biaya_rawat'];
    }

    /* Biaya Keperawatan */
    $biaya_keperawatan_jl_pr = $this->db('rawat_jl_pr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_jl_pr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_keperawatan_ralan'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_keperawatan_jl_dr = $this->db('rawat_jl_dr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_jl_dr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_keperawatan_ralan'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_keperawatan_jl_drpr = $this->db('rawat_jl_drpr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_jl_drpr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_keperawatan_ralan'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_keperawatan_inap_pr = $this->db('rawat_inap_pr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw=rawat_inap_pr.kd_jenis_prw')
      ->where('jns_perawatan_inap.kd_kategori', $this->settings->get('vedika.inacbgs_keperawatan_ranap'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_keperawatan_inap_dr = $this->db('rawat_inap_dr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw=rawat_inap_dr.kd_jenis_prw')
      ->where('jns_perawatan_inap.kd_kategori', $this->settings->get('vedika.inacbgs_keperawatan_ranap'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_keperawatan_inap_drpr = $this->db('rawat_inap_drpr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw=rawat_inap_drpr.kd_jenis_prw')
      ->where('jns_perawatan_inap.kd_kategori', $this->settings->get('vedika.inacbgs_keperawatan_ranap'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    /* End Biaya Keperawatan */

    $total_biaya_keperawatan = 0;
    foreach (array_merge($biaya_keperawatan_jl_pr,$biaya_keperawatan_jl_dr,$biaya_keperawatan_jl_drpr, $biaya_keperawatan_inap_pr,$biaya_keperawatan_inap_dr,$biaya_keperawatan_inap_drpr) as $row) {
      $total_biaya_keperawatan += $row['biaya_rawat'];
    }

    /* Biaya Penunjang */
    $biaya_penunjang_jl_dr = $this->db('rawat_jl_dr')
      ->select(['biaya_rawat' => 'SUM(menejemen)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_penunjang_jl_pr = $this->db('rawat_jl_pr')
      ->select(['biaya_rawat' => 'SUM(menejemen)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_penunjang_jl_drpr = $this->db('rawat_jl_drpr')
      ->select(['biaya_rawat' => 'SUM(menejemen)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_penunjang_inap_dr = $this->db('rawat_inap_dr')
      ->select(['biaya_rawat' => 'SUM(menejemen)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_penunjang_inap_pr = $this->db('rawat_inap_pr')
      ->select(['biaya_rawat' => 'SUM(menejemen)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_penunjang_inap_drpr = $this->db('rawat_inap_drpr')
      ->select(['biaya_rawat' => 'SUM(menejemen)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    /* End Biaya Penunjang */

    $total_biaya_penunjang = 0;
    foreach (array_merge($biaya_penunjang_jl_dr, $biaya_penunjang_jl_pr, $biaya_penunjang_jl_drpr, $biaya_penunjang_inap_dr, $biaya_penunjang_inap_pr, $biaya_penunjang_inap_drpr) as $row) {
      $total_biaya_penunjang += $row['biaya_rawat'];
    }

    $total_biaya_radiologi = 0;
    $rows_periksa_radiologi = $this->db('periksa_radiologi')
    ->join('jns_perawatan_radiologi', 'jns_perawatan_radiologi.kd_jenis_prw=periksa_radiologi.kd_jenis_prw')
    ->where('no_rawat', revertNoRawat($no_rawat))
    // ->where('periksa_radiologi.status', 'Ralan')
    ->toArray();

    foreach ($rows_periksa_radiologi as $row) {
      $total_biaya_radiologi += $row['biaya'];
    }

    // if($reg_periksa['status_lanjut'] == 'Ranap') {
    //   $rows_periksa_radiologi = $this->db('periksa_radiologi')
    //   ->join('jns_perawatan_radiologi', 'jns_perawatan_radiologi.kd_jenis_prw=periksa_radiologi.kd_jenis_prw')
    //   ->where('no_rawat', revertNoRawat($no_rawat))
    //   ->where('periksa_radiologi.status', 'Ranap')
    //   ->toArray();

    //   foreach ($rows_periksa_radiologi as $row) {
    //     $total_biaya_radiologi += $row['biaya'];
    //   }
    // }

    $total_biaya_laboratorium = 0;

    $result_detail['periksa_lab'] = $this->db('periksa_lab')
            ->select('jns_perawatan_lab.nm_perawatan') 
            ->select('periksa_lab.biaya')  
            ->select('periksa_lab.kd_jenis_prw')          
            ->join('jns_perawatan_lab', 'jns_perawatan_lab.kd_jenis_prw=periksa_lab.kd_jenis_prw')
            ->where('periksa_lab.no_rawat', revertNoRawat($no_rawat))
            ->where('periksa_lab.status', 'Ralan')
            ->where('periksa_lab.biaya', '!=','0')
            ->toArray();

    $result_detail['detail_periksa_lab'] = $this->db('detail_periksa_lab')
            ->select('jns_perawatan_lab.nm_perawatan') 
            ->select(['biaya' => 'SUM(detail_periksa_lab.bagian_dokter)'])
            ->select('detail_periksa_lab.kd_jenis_prw') 
            ->join('jns_perawatan_lab', 'jns_perawatan_lab.kd_jenis_prw=detail_periksa_lab.kd_jenis_prw')
            ->where('detail_periksa_lab.no_rawat', revertNoRawat($no_rawat))
            ->where('detail_periksa_lab.bagian_dokter', '!=','0')
            ->group('detail_periksa_lab.kd_jenis_prw')
            ->toArray();

          // $total_periksa_lab = 0;
    foreach (array_merge($result_detail['periksa_lab'], $result_detail['detail_periksa_lab']) as $row) {
            $total_biaya_laboratorium += $row['biaya'];
    }

    // $rows_periksa_lab = $this->db('periksa_lab')
    // ->join('jns_perawatan_lab', 'jns_perawatan_lab.kd_jenis_prw=periksa_lab.kd_jenis_prw')
    // ->where('no_rawat', revertNoRawat($no_rawat))
    // ->where('periksa_lab.status', 'Ralan')
    // ->toArray();

    // foreach ($rows_periksa_lab as $row) {
    //   $total_biaya_laboratorium += $row['biaya'];
    // }

    if($reg_periksa['status_lanjut'] == 'Ranap') {

      // $rows_periksa_lab = $this->db('periksa_lab')
      // ->join('jns_perawatan_lab', 'jns_perawatan_lab.kd_jenis_prw=periksa_lab.kd_jenis_prw')
      // ->where('no_rawat', revertNoRawat($no_rawat))
      // ->where('periksa_lab.status', 'Ranap')
      // ->toArray();
      // foreach ($rows_periksa_lab as $row) {
      //   $total_biaya_laboratorium += $row['biaya'];
      // }
      $result_detail['periksa_lab_ranap'] = $this->db('periksa_lab')
            ->select('jns_perawatan_lab.nm_perawatan') 
            ->select('periksa_lab.biaya')  
            ->select('periksa_lab.kd_jenis_prw')          
            ->join('jns_perawatan_lab', 'jns_perawatan_lab.kd_jenis_prw=periksa_lab.kd_jenis_prw')
            ->where('periksa_lab.no_rawat', revertNoRawat($no_rawat))
            // ->where('periksa_lab.status', 'Ranap')
            ->where('periksa_lab.biaya', '!=','0')
            ->toArray();

      $result_detail['detail_periksa_lab_ranap'] = $this->db('detail_periksa_lab')
            ->select('jns_perawatan_lab.nm_perawatan') 
            ->select(['biaya' => 'SUM(detail_periksa_lab.bagian_dokter)'])
            ->select('detail_periksa_lab.kd_jenis_prw') 
            ->join('jns_perawatan_lab', 'jns_perawatan_lab.kd_jenis_prw=detail_periksa_lab.kd_jenis_prw')
            ->where('detail_periksa_lab.no_rawat', revertNoRawat($no_rawat))
            ->where('detail_periksa_lab.bagian_dokter', '!=','0')
            ->group('detail_periksa_lab.kd_jenis_prw')
            ->toArray();

      $total_biaya_laboratorium = 0;
          foreach (array_merge($result_detail['periksa_lab_ranap'], $result_detail['detail_periksa_lab_ranap']) as $row) {
            $total_biaya_laboratorium += $row['biaya'];
          }

    }

    $total_biaya_pelayanan_darah = 0;

    /* Biaya Rehabilitasi */

    $biaya_rehabilitasi_jl_dr = $this->db('rawat_jl_dr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_jl_dr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_rehabilitasi'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_rehabilitasi_jl_pr = $this->db('rawat_jl_pr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_jl_pr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_rehabilitasi'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_rehabilitasi_jl_drpr = $this->db('rawat_jl_drpr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_jl_drpr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_rehabilitasi'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_rehabilitasi_dr = $this->db('rawat_inap_dr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw=rawat_inap_dr.kd_jenis_prw')
      ->where('jns_perawatan_inap.kd_kategori', $this->settings->get('vedika.inacbgs_rehabilitasi'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_rehabilitasi_pr = $this->db('rawat_inap_pr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw=rawat_inap_pr.kd_jenis_prw')
      ->where('jns_perawatan_inap.kd_kategori', $this->settings->get('vedika.inacbgs_rehabilitasi'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_rehabilitasi_drpr = $this->db('rawat_inap_drpr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw=rawat_inap_drpr.kd_jenis_prw')
      ->where('jns_perawatan_inap.kd_kategori', $this->settings->get('vedika.inacbgs_rehabilitasi'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    /* End Biaya Rehabilitasi */

    $total_biaya_rehabilitasi = 0;
    foreach (array_merge($biaya_rehabilitasi_jl_dr, $biaya_rehabilitasi_jl_pr, $biaya_rehabilitasi_jl_drpr,$biaya_rehabilitasi_dr, $biaya_rehabilitasi_pr, $biaya_rehabilitasi_drpr) as $row) {
      $total_biaya_rehabilitasi += $row['biaya_rawat'];
    }

    $total_biaya_kamar = 0;
    if($reg_periksa['status_lanjut'] == 'Ralan') {
      $total_biaya_kamar = 0;
    }
    if($reg_periksa['status_lanjut'] == 'Ranap') {
      $__get_kamar_inap = $this->db('kamar_inap')->where('no_rawat', revertNoRawat($no_rawat))->desc('tgl_keluar')->toArray();
      foreach ($__get_kamar_inap as $row) {
        $subtotal_biaya_kamar += $row['ttl_biaya'];
        $total_biaya_kamar = $subtotal_biaya_kamar;
      }

    }

    /* Biaya Rawat Intensif */
    $biaya_rawat_intensif_dr = $this->db('rawat_inap_dr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw=rawat_inap_dr.kd_jenis_prw')
      ->where('jns_perawatan_inap.kd_kategori', $this->settings->get('vedika.inacbgs_rawat_intensif'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_rawat_intensif_pr = $this->db('rawat_inap_pr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw=rawat_inap_pr.kd_jenis_prw')
      ->where('jns_perawatan_inap.kd_kategori', $this->settings->get('vedika.inacbgs_rawat_intensif'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_rawat_intensif_drpr = $this->db('rawat_inap_drpr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw=rawat_inap_drpr.kd_jenis_prw')
      ->where('jns_perawatan_inap.kd_kategori', $this->settings->get('vedika.inacbgs_rawat_intensif'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    /* End Biaya Rawat Intensif */

    $total_biaya_rawat_intensif = 0;
    foreach (array_merge($biaya_rawat_intensif_dr, $biaya_rawat_intensif_pr, $biaya_rawat_intensif_drpr) as $row) {
      $total_biaya_rawat_intensif += $row['biaya_rawat'];
    }

    $sub_total_biaya_obat = 0;

    $rows_pemberian_obat = $this->db('detail_pemberian_obat')
    ->join('databarang', 'databarang.kode_brng=detail_pemberian_obat.kode_brng')
    ->where('detail_pemberian_obat.no_rawat', revertNoRawat($no_rawat))
    ->where('detail_pemberian_obat.status', 'Ralan')
    ->toArray();

    foreach ($rows_pemberian_obat as $row) {
      $sub_total_biaya_obat += floatval($row['total']);
    }

    if($reg_periksa['status_lanjut'] == 'Ranap') {
      $rows_pemberian_obat = $this->db('detail_pemberian_obat')
      ->join('databarang', 'databarang.kode_brng=detail_pemberian_obat.kode_brng')
      ->where('detail_pemberian_obat.no_rawat', revertNoRawat($no_rawat))
      //->where('detail_pemberian_obat.status', 'Ranap')
      ->toArray();

      foreach ($rows_pemberian_obat as $row) {
        $sub_total_biaya_obat += floatval($row['total']);
      }
    }


    $jumlah_total_obat_operasi = 0;
    $obat_operasis = $this->db('beri_obat_operasi')->where('no_rawat', revertNoRawat($no_rawat))->toArray();
    foreach ($obat_operasis as $obat_operasi) {
      $obat_operasi['harga'] = $obat_operasi['hargasatuan'] * $obat_operasi['jumlah'];
      $jumlah_total_obat_operasi += $obat_operasi['harga'];
    }

    $total_biaya_obat = $sub_total_biaya_obat + $jumlah_total_obat_operasi;

    $total_biaya_obat_kronis = 0;
    $total_biaya_obat_kemoterapi = 0;

    /* Biaya Alkes */
    $biaya_alkes_jl_dr = $this->db('rawat_jl_dr')
      ->select(['biaya_rawat' => 'SUM(material)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_alkes_jl_pr = $this->db('rawat_jl_pr')
      ->select(['biaya_rawat' => 'SUM(material)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_alkes_jl_drpr = $this->db('rawat_jl_drpr')
      ->select(['biaya_rawat' => 'SUM(material)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_alkes_inap_dr = $this->db('rawat_inap_dr')
      ->select(['biaya_rawat' => 'SUM(material)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_alkes_inap_pr = $this->db('rawat_inap_pr')
      ->select(['biaya_rawat' => 'SUM(material)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_alkes_inap_drpr = $this->db('rawat_inap_drpr')
      ->select(['biaya_rawat' => 'SUM(material)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    /* End Biaya Alkes */

    $total_biaya_alkes = 0;
    foreach (array_merge($biaya_alkes_jl_dr, $biaya_alkes_jl_pr, $biaya_alkes_jl_drpr, $biaya_alkes_inap_dr, $biaya_alkes_inap_pr, $biaya_alkes_inap_drpr) as $row) {
      $total_biaya_alkes += $row['biaya_rawat'];
    }

    /* Biaya BMHP */
    $biaya_bmhp_jl_dr = $this->db('rawat_jl_dr')
      ->select(['biaya_rawat' => 'SUM(bhp)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_bmhp_jl_pr = $this->db('rawat_jl_pr')
      ->select(['biaya_rawat' => 'SUM(bhp)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_bmhp_jl_drpr = $this->db('rawat_jl_drpr')
      ->select(['biaya_rawat' => 'SUM(bhp)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_bmhp_inap_dr = $this->db('rawat_inap_dr')
      ->select(['biaya_rawat' => 'SUM(bhp)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_bmhp_inap_pr = $this->db('rawat_inap_pr')
      ->select(['biaya_rawat' => 'SUM(bhp)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_bmhp_inap_drpr = $this->db('rawat_inap_drpr')
      ->select(['biaya_rawat' => 'SUM(bhp)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    /* End Biaya BMHP */

    $total_biaya_bmhp = 0;
    foreach (array_merge($biaya_bmhp_jl_dr, $biaya_bmhp_jl_pr, $biaya_bmhp_jl_drpr, $biaya_bmhp_inap_dr, $biaya_bmhp_inap_pr, $biaya_bmhp_inap_drpr) as $row) {
      $total_biaya_bmhp += $row['biaya_rawat'];
    }

    /* Biaya KSO */
    $biaya_sewa_alat_jl_dr = $this->db('rawat_jl_dr')
      ->select(['biaya_rawat' => 'SUM(kso)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_sewa_alat_jl_pr = $this->db('rawat_jl_pr')
      ->select(['biaya_rawat' => 'SUM(kso)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_sewa_alat_jl_drpr = $this->db('rawat_jl_drpr')
      ->select(['biaya_rawat' => 'SUM(kso)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_sewa_alat_inap_dr = $this->db('rawat_inap_dr')
      ->select(['biaya_rawat' => 'SUM(kso)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_sewa_alat_inap_pr = $this->db('rawat_inap_pr')
      ->select(['biaya_rawat' => 'SUM(kso)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_sewa_alat_inap_drpr = $this->db('rawat_inap_drpr')
      ->select(['biaya_rawat' => 'SUM(kso)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    /* End Biaya KSO */

    $total_biaya_sewa_alat = 0;
    foreach (array_merge($biaya_sewa_alat_jl_dr, $biaya_sewa_alat_jl_pr, $biaya_sewa_alat_jl_drpr, $biaya_sewa_alat_inap_dr, $biaya_sewa_alat_inap_pr, $biaya_sewa_alat_inap_drpr) as $row) {
      $total_biaya_sewa_alat += $row['biaya_rawat'];
    }

    /* Yang belum
    ======================
    pelayanan_darah, --> UTD atau by kategori pelayanan darah

    obat_kronis, --> resep dokter by kategori obat
    obat_kemoterapi, --> resep dokter by kategori obat
    ======================
    */

    $total_biaya_tarif_poli_eks = 0;
    $total_biaya_add_payment_pct = 0;

    //$piutang_pasien = $this->db('piutang_pasien')->where('no_rawat', revertNoRawat($no_rawat))->oneArray();
    //$total_biaya_kamar = $piutang_pasien['totalpiutang'] - $total_biaya_non_bedah - $total_biaya_bedah - $total_biaya_konsultasi - $total_biaya_keperawatan - $total_biaya_penunjang - $total_biaya_radiologi - $total_biaya_laboratorium - $total_biaya_pelayanan_darah - $total_biaya_rehabilitasi - $total_biaya_rawat_intensif - $total_biaya_obat - $total_biaya_obat_kronis - $total_biaya_obat_kemoterapi - $total_biaya_alkes - $total_biaya_bmhp - $total_biaya_sewa_alat - $total_biaya_tarif_poli_eks - $total_biaya_add_payment_pct;

    $request ='{
                     "metadata": {
                         "method":"get_claim_data"
                     },
                     "data": {
                         "nomor_sep":"'.$this->_getSEPInfo('no_sep', revertNoRawat($no_rawat)).'"
                     }
                }';

    $msg = $this->Request($request);
    $get_claim_data = [];
    if($msg['metadata']['message']=="Ok"){
      $get_claim_data = $msg;
      //echo json_encode($msg, true);
    }

    $adl = [];
    for($i=12; $i<=60; $i++){
       $adl[] = $i;
    }
    //echo json_encode($adl, true);

    $html = $this->draw('inacbgs.html', [
      'sitb' => $sitb,
      'jk' => $jk,
      'reg_periksa' => $reg_periksa,
      'biaya_non_bedah' => $total_biaya_non_bedah,
      'biaya_bedah' => $total_biaya_bedah,
      'biaya_konsultasi' => $total_biaya_konsultasi,
      'biaya_tenaga_ahli' => $total_biaya_tenaga_ahli,
      'biaya_keperawatan' => $total_biaya_keperawatan,
      'biaya_penunjang' => $total_biaya_penunjang,
      'biaya_radiologi' => $total_biaya_radiologi,
      'biaya_laboratorium' => $total_biaya_laboratorium,
      'biaya_pelayanan_darah' => $total_biaya_pelayanan_darah,
      'biaya_rehabilitasi' => $total_biaya_rehabilitasi,
      'biaya_kamar' => $total_biaya_kamar,
      'biaya_rawat_intensif' => $total_biaya_rawat_intensif,
      'biaya_obat' => $total_biaya_obat,
      'biaya_obat_kronis' => $total_biaya_obat_kronis,
      'biaya_obat_kemoterapi' => $total_biaya_obat_kemoterapi,
      'biaya_alkes' => $total_biaya_alkes,
      'biaya_bmhp' => $total_biaya_bmhp,
      'biaya_sewa_alat' => $total_biaya_sewa_alat,
      'biaya_tarif_poli_eks' => $total_biaya_tarif_poli_eks,
      'biaya_add_payment_pct' => $total_biaya_add_payment_pct,
      'get_claim_data' => $get_claim_data,
      'penyakit' => $penyakit,
      'prosedur' => $prosedur,
      'adl' => $adl
    ]);

    if ($this->captureInacbgsHtml) {
      return $html;
    }

    echo $html;
    exit();
  }

  public function getBridgingGrouper($no_rawat)
  {
    $reg_periksa = $this->db('reg_periksa')
      ->join('pasien', 'pasien.no_rkm_medis=reg_periksa.no_rkm_medis')
      ->join('poliklinik', 'poliklinik.kd_poli=reg_periksa.kd_poli')
      ->join('dokter', 'dokter.kd_dokter=reg_periksa.kd_dokter')
      ->join('penjab', 'penjab.kd_pj=reg_periksa.kd_pj')
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->oneArray();
    $pemeriksaan = $this->db('pemeriksaan_ralan')->where('no_rawat', $reg_periksa['no_rawat'])->limit(1)->desc('tgl_perawatan')->desc('jam_rawat')->toArray();
    $reg_periksa['sistole'] = strtok($pemeriksaan[0]['tensi'], '/');
    $reg_periksa['diastole'] = substr($pemeriksaan[0]['tensi'], strpos($pemeriksaan[0]['tensi'], '/') + 1);
    if($reg_periksa['status_lanjut'] == 'Ranap') {
      $pemeriksaan = $this->db('pemeriksaan_ranap')->where('no_rawat', $reg_periksa['no_rawat'])->limit(1)->desc('tgl_perawatan')->desc('jam_rawat')->toArray();
      $reg_periksa['sistole'] = strtok($pemeriksaan[0]['tensi'], '/');
      $reg_periksa['diastole'] = substr($pemeriksaan[0]['tensi'], strpos($pemeriksaan[0]['tensi'], '/') + 1);
    }
    $reg_periksa['no_sep'] = $this->_getSEPInfo('no_sep', revertNoRawat($no_rawat));
    $reg_periksa['kelas_rawat'] = $this->_getSEPInfo('klsrawat', revertNoRawat($no_rawat));
    $reg_periksa['stts_pulang'] = '';
    $reg_periksa['tgl_keluar'] = $reg_periksa['tgl_registrasi'];
    if($reg_periksa['status_lanjut'] == 'Ranap') {
      $_get_kamar_inap = $this->db('kamar_inap')->where('no_rawat', revertNoRawat($no_rawat))->limit(1)->desc('tgl_keluar')->toArray();
      $_get_kamar_inap_in = $this->db('kamar_inap')->where('no_rawat', revertNoRawat($no_rawat))->limit(1)->asc('tgl_masuk')->toArray();
      $reg_periksa['tgl_registrasi'] = $_get_kamar_inap[0]['tgl_masuk'].' '.$_get_kamar_inap_in[0]['jam_masuk'];
      $reg_periksa['tgl_keluar'] = $_get_kamar_inap[0]['tgl_keluar'].' '.$_get_kamar_inap[0]['jam_keluar'];
      $reg_periksa['stts_pulang'] = $_get_kamar_inap[0]['stts_pulang'];
      $get_kamar = $this->db('kamar')->where('kd_kamar', $_get_kamar_inap[0]['kd_kamar'])->oneArray();
      $get_bangsal = $this->db('bangsal')->where('kd_bangsal', $get_kamar['kd_bangsal'])->oneArray();
      $reg_periksa['nm_poli'] = $get_bangsal['nm_bangsal'].'/'.$get_kamar['kd_kamar'];
      $reg_periksa['nm_dokter'] = $this->db('dpjp_ranap')
        ->join('dokter', 'dokter.kd_dokter=dpjp_ranap.kd_dokter')
        ->where('no_rawat', revertNoRawat($no_rawat))
        ->toArray();
    }

    $row_diagnosa = $this->db('diagnosa_pasien')
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->where('status', $reg_periksa['status_lanjut'])
      ->asc('prioritas')
      ->toArray();
    $a_diagnosa=1;
    $penyakit = '';
    foreach ($row_diagnosa as $row) {
      if($a_diagnosa==1){
          $penyakit=$row["kd_penyakit"];
      }else{
          $penyakit=$penyakit."#".$row["kd_penyakit"];
      }
      $a_diagnosa++;
    }

    $row_prosedur = $this->db('prosedur_pasien')
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->where('status', $reg_periksa['status_lanjut'])
      ->asc('prioritas')
      ->toArray();
    $prosedur= '';
    $a_prosedur=1;
    foreach ($row_prosedur as $row) {
      $kodeKlaim = $this->_procedureCodeWithVolume(
        revertNoRawat($no_rawat),
        $row['kode'],
        isset($row['status']) ? $row['status'] : $reg_periksa['status_lanjut']
      );
      if($a_prosedur==1){
          $prosedur=$kodeKlaim;
      }else{
          $prosedur=$prosedur."#".$kodeKlaim;
      }
      $a_prosedur++;
    }

    /* Prosedur non bedah ralan */
    $biaya_non_bedah_dr = $this->db('rawat_jl_dr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_jl_dr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_prosedur_non_bedah'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_non_bedah_pr = $this->db('rawat_jl_pr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_jl_pr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_prosedur_non_bedah'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_non_bedah_drpr = $this->db('rawat_jl_drpr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_jl_drpr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_prosedur_non_bedah'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    /* End prosedur non bedah ralan */

    /* Prosedur non bedah ranap */
    $biaya_non_bedah_dr_ranap = $this->db('rawat_inap_dr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_inap_dr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_prosedur_non_bedah'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_non_bedah_pr_ranap = $this->db('rawat_inap_pr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_inap_pr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_prosedur_non_bedah'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_non_bedah_drpr_ranap = $this->db('rawat_inap_drpr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_inap_drpr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_prosedur_non_bedah'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    /* End prosedur non bedah ranap */

    $total_biaya_non_bedah = 0;
    foreach (array_merge($biaya_non_bedah_dr, $biaya_non_bedah_pr, $biaya_non_bedah_drpr, $biaya_non_bedah_dr_ranap, $biaya_non_bedah_pr_ranap, $biaya_non_bedah_drpr_ranap) as $row) {
      $total_biaya_non_bedah += $row['biaya_rawat'];
    }

    /* Prosedur bedah ralan */
    $biaya_bedah_dr = $this->db('rawat_jl_dr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_jl_dr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_prosedur_bedah'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_bedah_pr = $this->db('rawat_jl_pr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_jl_pr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_prosedur_bedah'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_bedah_drpr = $this->db('rawat_jl_drpr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_jl_drpr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_prosedur_bedah'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    /* End prosedur bedah ralan */

    /* Prosedur bedah ranap */
    $biaya_bedah_dr_ranap = $this->db('rawat_inap_dr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_inap_dr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_prosedur_bedah'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_bedah_pr_ranap = $this->db('rawat_inap_pr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_inap_pr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_prosedur_bedah'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_bedah_drpr_ranap = $this->db('rawat_inap_drpr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_inap_drpr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_prosedur_bedah'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    /* End prosedur bedah ranap */

    /* Start biaya operasi */
    $biaya_operasi = $this->db('operasi')
      ->select(['biaya_rawat' => 'SUM(biayaoperator1 + biayaoperator2 + biayaoperator3 + biayaasisten_operator1 + biayaasisten_operator2 + biayadokter_anak + biayaperawaat_resusitas + biayadokter_anestesi + biayaasisten_anestesi + biayabidan + biayaperawat_luar)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->where('status', 'Ralan')
      ->toArray();

    if($reg_periksa['status_lanjut'] == 'Ranap') {
      $biaya_operasi = $this->db('operasi')
        ->select(['biaya_rawat' => 'SUM(biayaoperator1 + biayaoperator2 + biayaoperator3 + biayaasisten_operator1 + biayaasisten_operator2 + biayadokter_anak + biayaperawaat_resusitas + biayadokter_anestesi + biayaasisten_anestesi + biayabidan + biayaperawat_luar)'])
        ->where('no_rawat', revertNoRawat($no_rawat))
        ->where('status', 'Ranap')
        ->toArray();
    }
    /* End biaya operasi */

    $total_biaya_bedah = 0;
    foreach (array_merge($biaya_bedah_dr, $biaya_bedah_pr, $biaya_bedah_drpr, $biaya_bedah_dr_ranap, $biaya_bedah_pr_ranap, $biaya_bedah_drpr_ranap, $biaya_operasi) as $row) {
      $total_biaya_bedah += $row['biaya_rawat'];
    }

    /* Biaya Konsultasi */
    $biaya_poliklinik = $this->db('reg_periksa')
      ->select(['biaya_rawat' => 'SUM(registrasi)'])
      ->join('poliklinik', 'poliklinik.kd_poli=reg_periksa.kd_poli')
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_konsultasi_dr = $this->db('rawat_jl_dr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_jl_dr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_konsultasi'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_konsultasi_pr = $this->db('rawat_jl_pr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_jl_pr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_konsultasi'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_konsultasi_drpr = $this->db('rawat_jl_drpr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_jl_drpr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_konsultasi'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_visit_dr = $this->db('rawat_inap_dr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw=rawat_inap_dr.kd_jenis_prw')
      ->where('jns_perawatan_inap.kd_kategori', $this->settings->get('vedika.inacbgs_konsultasi'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_visit_pr = $this->db('rawat_inap_pr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw=rawat_inap_pr.kd_jenis_prw')
      ->where('jns_perawatan_inap.kd_kategori', $this->settings->get('vedika.inacbgs_konsultasi'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_visit_drpr = $this->db('rawat_inap_drpr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw=rawat_inap_drpr.kd_jenis_prw')
      ->where('jns_perawatan_inap.kd_kategori', $this->settings->get('vedika.inacbgs_konsultasi'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    /* End Biaya Konsultasi */

    $total_biaya_konsultasi = 0;
    foreach (array_merge($biaya_poliklinik, $biaya_konsultasi_dr, $biaya_konsultasi_pr, $biaya_konsultasi_drpr, $biaya_visit_dr,$biaya_visit_pr, $biaya_visit_drpr) as $row) {
      $total_biaya_konsultasi += $row['biaya_rawat'];
    }

    /* Biaya Tenaga Ahli */
    $biaya_tenaga_ahli_dr = $this->db('rawat_inap_dr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_inap_dr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_tenaga_ahli'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_tenaga_ahli_pr = $this->db('rawat_inap_pr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_inap_pr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_tenaga_ahli'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_tenaga_ahli_drpr = $this->db('rawat_inap_drpr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_inap_drpr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_tenaga_ahli'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    /* End Biaya Tenaga Ahli */

    $total_biaya_tenaga_ahli = 0;
    foreach (array_merge($biaya_tenaga_ahli_dr, $biaya_tenaga_ahli_pr, $biaya_tenaga_ahli_drpr) as $row) {
      $total_biaya_tenaga_ahli += $row['biaya_rawat'];
    }

    /* Biaya Keperawatan */
    $biaya_keperawatan_jl_pr = $this->db('rawat_jl_pr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_jl_pr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_keperawatan_ralan'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_keperawatan_jl_dr = $this->db('rawat_jl_dr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_jl_dr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_keperawatan_ralan'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_keperawatan_jl_drpr = $this->db('rawat_jl_drpr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_jl_drpr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_keperawatan_ralan'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_keperawatan_inap_pr = $this->db('rawat_inap_pr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw=rawat_inap_pr.kd_jenis_prw')
      ->where('jns_perawatan_inap.kd_kategori', $this->settings->get('vedika.inacbgs_keperawatan_ranap'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_keperawatan_inap_dr = $this->db('rawat_inap_dr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw=rawat_inap_dr.kd_jenis_prw')
      ->where('jns_perawatan_inap.kd_kategori', $this->settings->get('vedika.inacbgs_keperawatan_ranap'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_keperawatan_inap_drpr = $this->db('rawat_inap_drpr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw=rawat_inap_drpr.kd_jenis_prw')
      ->where('jns_perawatan_inap.kd_kategori', $this->settings->get('vedika.inacbgs_keperawatan_ranap'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    /* End Biaya Keperawatan */

    $total_biaya_keperawatan = 0;
    foreach (array_merge($biaya_keperawatan_jl_pr,$biaya_keperawatan_jl_dr,$biaya_keperawatan_jl_drpr, $biaya_keperawatan_inap_pr,$biaya_keperawatan_inap_dr,$biaya_keperawatan_inap_drpr) as $row) {
      $total_biaya_keperawatan += $row['biaya_rawat'];
    }

    /* Biaya Penunjang */
    $biaya_penunjang_jl_dr = $this->db('rawat_jl_dr')
      ->select(['biaya_rawat' => 'SUM(menejemen)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_penunjang_jl_pr = $this->db('rawat_jl_pr')
      ->select(['biaya_rawat' => 'SUM(menejemen)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_penunjang_jl_drpr = $this->db('rawat_jl_drpr')
      ->select(['biaya_rawat' => 'SUM(menejemen)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_penunjang_inap_dr = $this->db('rawat_inap_dr')
      ->select(['biaya_rawat' => 'SUM(menejemen)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_penunjang_inap_pr = $this->db('rawat_inap_pr')
      ->select(['biaya_rawat' => 'SUM(menejemen)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_penunjang_inap_drpr = $this->db('rawat_inap_drpr')
      ->select(['biaya_rawat' => 'SUM(menejemen)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    /* End Biaya Penunjang */

    $total_biaya_penunjang = 0;
    foreach (array_merge($biaya_penunjang_jl_dr, $biaya_penunjang_jl_pr, $biaya_penunjang_jl_drpr, $biaya_penunjang_inap_dr, $biaya_penunjang_inap_pr, $biaya_penunjang_inap_drpr) as $row) {
      $total_biaya_penunjang += $row['biaya_rawat'];
    }

    $total_biaya_radiologi = 0;
    $rows_periksa_radiologi = $this->db('periksa_radiologi')
    ->join('jns_perawatan_radiologi', 'jns_perawatan_radiologi.kd_jenis_prw=periksa_radiologi.kd_jenis_prw')
    ->where('no_rawat', revertNoRawat($no_rawat))
    ->where('periksa_radiologi.status', 'Ralan')
    ->toArray();

    foreach ($rows_periksa_radiologi as $row) {
      $total_biaya_radiologi += $row['biaya'];
    }

    if($reg_periksa['status_lanjut'] == 'Ranap') {
      $rows_periksa_radiologi = $this->db('periksa_radiologi')
      ->join('jns_perawatan_radiologi', 'jns_perawatan_radiologi.kd_jenis_prw=periksa_radiologi.kd_jenis_prw')
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->where('periksa_radiologi.status', 'Ranap')
      ->toArray();

      foreach ($rows_periksa_radiologi as $row) {
        $total_biaya_radiologi += $row['biaya'];
      }
    }

    $total_biaya_laboratorium = 0;

    $rows_periksa_lab = $this->db('periksa_lab')
    ->join('jns_perawatan_lab', 'jns_perawatan_lab.kd_jenis_prw=periksa_lab.kd_jenis_prw')
    ->where('no_rawat', revertNoRawat($no_rawat))
    ->where('periksa_lab.status', 'Ralan')
    ->toArray();

    foreach ($rows_periksa_lab as $row) {
      $total_biaya_laboratorium += $row['biaya'];
    }

    if($reg_periksa['status_lanjut'] == 'Ranap') {
      $rows_periksa_lab = $this->db('periksa_lab')
      ->join('jns_perawatan_lab', 'jns_perawatan_lab.kd_jenis_prw=periksa_lab.kd_jenis_prw')
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->where('periksa_lab.status', 'Ranap')
      ->toArray();
      foreach ($rows_periksa_lab as $row) {
        $total_biaya_laboratorium += $row['biaya'];
      }
    }

    $total_biaya_pelayanan_darah = 0;

    /* Biaya Rehabilitasi */

    $biaya_rehabilitasi_jl_dr = $this->db('rawat_jl_dr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_jl_dr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_rehabilitasi'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_rehabilitasi_jl_pr = $this->db('rawat_jl_pr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_jl_pr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_rehabilitasi'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_rehabilitasi_jl_drpr = $this->db('rawat_jl_drpr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan', 'jns_perawatan.kd_jenis_prw=rawat_jl_drpr.kd_jenis_prw')
      ->where('jns_perawatan.kd_kategori', $this->settings->get('vedika.inacbgs_rehabilitasi'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_rehabilitasi_dr = $this->db('rawat_inap_dr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw=rawat_inap_dr.kd_jenis_prw')
      ->where('jns_perawatan_inap.kd_kategori', $this->settings->get('vedika.inacbgs_rehabilitasi'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_rehabilitasi_pr = $this->db('rawat_inap_pr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw=rawat_inap_pr.kd_jenis_prw')
      ->where('jns_perawatan_inap.kd_kategori', $this->settings->get('vedika.inacbgs_rehabilitasi'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_rehabilitasi_drpr = $this->db('rawat_inap_drpr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw=rawat_inap_drpr.kd_jenis_prw')
      ->where('jns_perawatan_inap.kd_kategori', $this->settings->get('vedika.inacbgs_rehabilitasi'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    /* End Biaya Rehabilitasi */

    $total_biaya_rehabilitasi = 0;
    foreach (array_merge($biaya_rehabilitasi_jl_dr, $biaya_rehabilitasi_jl_pr, $biaya_rehabilitasi_jl_drpr,$biaya_rehabilitasi_dr, $biaya_rehabilitasi_pr, $biaya_rehabilitasi_drpr) as $row) {
      $total_biaya_rehabilitasi += $row['biaya_rawat'];
    }

    $total_biaya_kamar = 0;
    if($reg_periksa['status_lanjut'] == 'Ralan') {
      $total_biaya_kamar = 0;
    }
    if($reg_periksa['status_lanjut'] == 'Ranap') {
      $__get_kamar_inap = $this->db('kamar_inap')->where('no_rawat', revertNoRawat($no_rawat))->limit(1)->desc('tgl_keluar')->toArray();
      foreach ($__get_kamar_inap as $row) {
        $subtotal_biaya_kamar += $row['ttl_biaya'];
        $total_biaya_kamar = $subtotal_biaya_kamar;
      }

    }

    /* Biaya Rawat Intensif */
    $biaya_rawat_intensif_dr = $this->db('rawat_inap_dr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw=rawat_inap_dr.kd_jenis_prw')
      ->where('jns_perawatan_inap.kd_kategori', $this->settings->get('vedika.inacbgs_rawat_intensif'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_rawat_intensif_pr = $this->db('rawat_inap_pr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw=rawat_inap_pr.kd_jenis_prw')
      ->where('jns_perawatan_inap.kd_kategori', $this->settings->get('vedika.inacbgs_rawat_intensif'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();

    $biaya_rawat_intensif_drpr = $this->db('rawat_inap_drpr')
      ->select(['biaya_rawat' => 'SUM(biaya_rawat)'])
      ->join('jns_perawatan_inap', 'jns_perawatan_inap.kd_jenis_prw=rawat_inap_drpr.kd_jenis_prw')
      ->where('jns_perawatan_inap.kd_kategori', $this->settings->get('vedika.inacbgs_rawat_intensif'))
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    /* End Biaya Rawat Intensif */

    $total_biaya_rawat_intensif = 0;
    foreach (array_merge($biaya_rawat_intensif_dr, $biaya_rawat_intensif_pr, $biaya_rawat_intensif_drpr) as $row) {
      $total_biaya_rawat_intensif += $row['biaya_rawat'];
    }

    $sub_total_biaya_obat = 0;

    $rows_pemberian_obat = $this->db('detail_pemberian_obat')
    ->join('databarang', 'databarang.kode_brng=detail_pemberian_obat.kode_brng')
    ->where('detail_pemberian_obat.no_rawat', revertNoRawat($no_rawat))
    ->where('detail_pemberian_obat.status', 'Ralan')
    ->toArray();

    foreach ($rows_pemberian_obat as $row) {
      $sub_total_biaya_obat += floatval($row['total']);
    }

    if($reg_periksa['status_lanjut'] == 'Ranap') {
      $rows_pemberian_obat = $this->db('detail_pemberian_obat')
      ->join('databarang', 'databarang.kode_brng=detail_pemberian_obat.kode_brng')
      ->where('detail_pemberian_obat.no_rawat', revertNoRawat($no_rawat))
      //->where('detail_pemberian_obat.status', 'Ranap')
      ->toArray();

      foreach ($rows_pemberian_obat as $row) {
        $sub_total_biaya_obat += floatval($row['total']);
      }
    }


    $jumlah_total_obat_operasi = 0;
    $obat_operasis = $this->db('beri_obat_operasi')->where('no_rawat', revertNoRawat($no_rawat))->toArray();
    foreach ($obat_operasis as $obat_operasi) {
      $obat_operasi['harga'] = $obat_operasi['hargasatuan'] * $obat_operasi['jumlah'];
      $jumlah_total_obat_operasi += $obat_operasi['harga'];
    }

    $total_biaya_obat = $sub_total_biaya_obat + $jumlah_total_obat_operasi;

    $total_biaya_obat_kronis = 0;
    $total_biaya_obat_kemoterapi = 0;

    /* Biaya Alkes */
    $biaya_alkes_jl_dr = $this->db('rawat_jl_dr')
      ->select(['biaya_rawat' => 'SUM(material)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_alkes_jl_pr = $this->db('rawat_jl_pr')
      ->select(['biaya_rawat' => 'SUM(material)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_alkes_jl_drpr = $this->db('rawat_jl_drpr')
      ->select(['biaya_rawat' => 'SUM(material)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_alkes_inap_dr = $this->db('rawat_inap_dr')
      ->select(['biaya_rawat' => 'SUM(material)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_alkes_inap_pr = $this->db('rawat_inap_pr')
      ->select(['biaya_rawat' => 'SUM(material)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_alkes_inap_drpr = $this->db('rawat_inap_drpr')
      ->select(['biaya_rawat' => 'SUM(material)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    /* End Biaya Alkes */

    $total_biaya_alkes = 0;
    foreach (array_merge($biaya_alkes_jl_dr, $biaya_alkes_jl_pr, $biaya_alkes_jl_drpr, $biaya_alkes_inap_dr, $biaya_alkes_inap_pr, $biaya_alkes_inap_drpr) as $row) {
      $total_biaya_alkes += $row['biaya_rawat'];
    }

    /* Biaya BMHP */
    $biaya_bmhp_jl_dr = $this->db('rawat_jl_dr')
      ->select(['biaya_rawat' => 'SUM(bhp)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_bmhp_jl_pr = $this->db('rawat_jl_pr')
      ->select(['biaya_rawat' => 'SUM(bhp)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_bmhp_jl_drpr = $this->db('rawat_jl_drpr')
      ->select(['biaya_rawat' => 'SUM(bhp)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_bmhp_inap_dr = $this->db('rawat_inap_dr')
      ->select(['biaya_rawat' => 'SUM(bhp)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_bmhp_inap_pr = $this->db('rawat_inap_pr')
      ->select(['biaya_rawat' => 'SUM(bhp)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_bmhp_inap_drpr = $this->db('rawat_inap_drpr')
      ->select(['biaya_rawat' => 'SUM(bhp)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    /* End Biaya BMHP */

    $total_biaya_bmhp = 0;
    foreach (array_merge($biaya_bmhp_jl_dr, $biaya_bmhp_jl_pr, $biaya_bmhp_jl_drpr, $biaya_bmhp_inap_dr, $biaya_bmhp_inap_pr, $biaya_bmhp_inap_drpr) as $row) {
      $total_biaya_bmhp += $row['biaya_rawat'];
    }

    /* Biaya KSO */
    $biaya_sewa_alat_jl_dr = $this->db('rawat_jl_dr')
      ->select(['biaya_rawat' => 'SUM(kso)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_sewa_alat_jl_pr = $this->db('rawat_jl_pr')
      ->select(['biaya_rawat' => 'SUM(kso)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_sewa_alat_jl_drpr = $this->db('rawat_jl_drpr')
      ->select(['biaya_rawat' => 'SUM(kso)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_sewa_alat_inap_dr = $this->db('rawat_inap_dr')
      ->select(['biaya_rawat' => 'SUM(kso)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_sewa_alat_inap_pr = $this->db('rawat_inap_pr')
      ->select(['biaya_rawat' => 'SUM(kso)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    $biaya_sewa_alat_inap_drpr = $this->db('rawat_inap_drpr')
      ->select(['biaya_rawat' => 'SUM(kso)'])
      ->where('no_rawat', revertNoRawat($no_rawat))
      ->toArray();
    /* End Biaya KSO */

    $total_biaya_sewa_alat = 0;
    foreach (array_merge($biaya_sewa_alat_jl_dr, $biaya_sewa_alat_jl_pr, $biaya_sewa_alat_jl_drpr, $biaya_sewa_alat_inap_dr, $biaya_sewa_alat_inap_pr, $biaya_sewa_alat_inap_drpr) as $row) {
      $total_biaya_sewa_alat += $row['biaya_rawat'];
    }

    /* Yang belum
    ======================
    pelayanan_darah, --> UTD atau by kategori pelayanan darah

    obat_kronis, --> resep dokter by kategori obat
    obat_kemoterapi, --> resep dokter by kategori obat
    ======================
    */

    $total_biaya_tarif_poli_eks = 0;
    $total_biaya_add_payment_pct = 0;

    //$piutang_pasien = $this->db('piutang_pasien')->where('no_rawat', revertNoRawat($no_rawat))->oneArray();
    //$total_biaya_kamar = $piutang_pasien['totalpiutang'] - $total_biaya_non_bedah - $total_biaya_bedah - $total_biaya_konsultasi - $total_biaya_keperawatan - $total_biaya_penunjang - $total_biaya_radiologi - $total_biaya_laboratorium - $total_biaya_pelayanan_darah - $total_biaya_rehabilitasi - $total_biaya_rawat_intensif - $total_biaya_obat - $total_biaya_obat_kronis - $total_biaya_obat_kemoterapi - $total_biaya_alkes - $total_biaya_bmhp - $total_biaya_sewa_alat - $total_biaya_tarif_poli_eks - $total_biaya_add_payment_pct;

    $request ='{
                     "metadata": {
                         "method":"get_claim_data"
                     },
                     "data": {
                         "nomor_sep":"'.$this->_getSEPInfo('no_sep', revertNoRawat($no_rawat)).'"
                     }
                }';

    $msg = $this->Request($request);
    $get_claim_data = [];
    if($msg['metadata']['message']=="Ok"){
      $get_claim_data = $msg;
      //echo json_encode($msg, true);
    }

    $adl = [];
    for($i=12; $i<=60; $i++){
       $adl[] = $i;
    }
    //echo json_encode($adl, true);

    echo $this->draw('inacbgsgrouper.html', [
      'reg_periksa' => $reg_periksa,
      'biaya_non_bedah' => $total_biaya_non_bedah,
      'biaya_bedah' => $total_biaya_bedah,
      'biaya_konsultasi' => $total_biaya_konsultasi,
      'biaya_tenaga_ahli' => $total_biaya_tenaga_ahli,
      'biaya_keperawatan' => $total_biaya_keperawatan,
      'biaya_penunjang' => $total_biaya_penunjang,
      'biaya_radiologi' => $total_biaya_radiologi,
      'biaya_laboratorium' => $total_biaya_laboratorium,
      'biaya_pelayanan_darah' => $total_biaya_pelayanan_darah,
      'biaya_rehabilitasi' => $total_biaya_rehabilitasi,
      'biaya_kamar' => $total_biaya_kamar,
      'biaya_rawat_intensif' => $total_biaya_rawat_intensif,
      'biaya_obat' => $total_biaya_obat,
      'biaya_obat_kronis' => $total_biaya_obat_kronis,
      'biaya_obat_kemoterapi' => $total_biaya_obat_kemoterapi,
      'biaya_alkes' => $total_biaya_alkes,
      'biaya_bmhp' => $total_biaya_bmhp,
      'biaya_sewa_alat' => $total_biaya_sewa_alat,
      'biaya_tarif_poli_eks' => $total_biaya_tarif_poli_eks,
      'biaya_add_payment_pct' => $total_biaya_add_payment_pct,
      'get_claim_data' => $get_claim_data,
      'penyakit' => $penyakit,
      'prosedur' => $prosedur,
      'adl' => $adl
    ]);
    exit();
  }

  public function postKirimInacbgs()
  {
    // $_POST['jk'] = $this->core->getRegPeriksaInfo('jk', $_POST['no_rawat']);;
    $_POST['tgl_lahir'] = $this->core->getRegPeriksaInfo('tgl_lahir', $_POST['no_rawat']);;
    $no_rkm_medis      = $this->validTeks(trim($_POST['no_rkm_medis']));
    $norawat           = $this->validTeks(trim($_POST['no_rawat']));
    $tgl_registrasi    = $this->validTeks(trim($_POST['tgl_registrasi']));
    $nosep             = $this->validTeks(trim($_POST['nosep']));
    $nokartu           = $this->validTeks(trim($_POST['nokartu']));
    $nm_pasien         = $this->validTeks(trim($_POST['nm_pasien']));
    $keluar            = $this->validTeks(trim($_POST['keluar']));
    $cara_masuk        = $this->validTeks(trim($_POST['cara_masuk']));
    $kelas_rawat       = $this->validTeks(trim($_POST['kelas_rawat']));
    $adl_sub_acute     = $this->validTeks(trim($_POST['adl_sub_acute']));
    $adl_chronic       = $this->validTeks(trim($_POST['adl_chronic']));
    $icu_indikator     = $this->validTeks(trim($_POST['icu_indikator']));
    $icu_los           = $this->validTeks(trim($_POST['icu_los']));
    $ventilator_hour   = $this->validTeks(trim($_POST['ventilator_hour']));
    $use_ind           = $this->validTeks(trim($_POST['use_ind']));
    $start_dttm        = $this->validTeks(trim($_POST['start_dttm']));
    $stop_dttm         = $this->validTeks(trim($_POST['stop_dttm']));
    $ventilator_hour   = $this->validTeks(trim($_POST['ventilator_hour']));
    $upgrade_class_ind = $this->validTeks(trim($_POST['upgrade_class_ind']));
    $upgrade_class_class = $this->validTeks(trim($_POST['upgrade_class_class']));
    $upgrade_class_los = $this->validTeks(trim($_POST['upgrade_class_los']));
    $upgrade_class_payor = $this->validTeks(trim($_POST['upgrade_class_payor']));
    $add_payment_pct   = $this->validTeks(trim($_POST['add_payment_pct']));
    $birth_weight      = $this->validTeks(trim($_POST['birth_weight']));
    $discharge_status  = $this->validTeks(trim($_POST['discharge_status']));
    $diagnosa          = $this->validTeks(trim($_POST['diagnosa']));
    $procedure         = $this->validTeks(trim($_POST['procedure']));
    $prosedur_non_bedah = $this->validTeks(trim($_POST['prosedur_non_bedah']));
    $prosedur_bedah    = $this->validTeks(trim($_POST['prosedur_bedah']));
    $konsultasi        = $this->validTeks(trim($_POST['konsultasi']));
    $tenaga_ahli       = $this->validTeks(trim($_POST['tenaga_ahli']));
    $keperawatan       = $this->validTeks(trim($_POST['keperawatan']));
    $penunjang         = $this->validTeks(trim($_POST['penunjang']));
    $radiologi         = $this->validTeks(trim($_POST['radiologi']));
    $laboratorium      = $this->validTeks(trim($_POST['laboratorium']));
    $pelayanan_darah   = $this->validTeks(trim($_POST['pelayanan_darah']));
    $rehabilitasi      = $this->validTeks(trim($_POST['rehabilitasi']));
    $kamar             = $this->validTeks(trim($_POST['kamar']));
    $rawat_intensif    = $this->validTeks(trim($_POST['rawat_intensif']));
    $obat              = $this->validTeks(trim($_POST['obat']));
    $obat_kronis       = $this->validTeks(trim($_POST['obat_kronis']));
    $obat_kemoterapi   = $this->validTeks(trim($_POST['obat_kemoterapi']));
    $alkes             = $this->validTeks(trim($_POST['alkes']));
    $bmhp              = $this->validTeks(trim($_POST['bmhp']));
    $sewa_alat         = $this->validTeks(trim($_POST['sewa_alat']));
    $pemulasaraan_jenazah = $this->validTeks(trim($_POST['pemulasaraan_jenazah']));
    $kantong_jenazah   = $this->validTeks(trim($_POST['kantong_jenazah']));
    $peti_jenazah      = $this->validTeks(trim($_POST['peti_jenazah']));
    $plastik_erat      = $this->validTeks(trim($_POST['plastik_erat']));
    $desinfektan_jenazah = $this->validTeks(trim($_POST['desinfektan_jenazah']));
    $mobil_jenazah     = $this->validTeks(trim($_POST['mobil_jenazah']));
    $desinfektan_mobil_jenazah = $this->validTeks(trim($_POST['desinfektan_mobil_jenazah']));
    $covid19_status_cd = $this->validTeks(trim($_POST['covid19_status_cd']));
    $nomor_kartu_t     = $this->validTeks(trim($_POST['nomor_kartu_t']));
    $episodes          = $this->validTeks(trim($_POST['episodes']));
    $covid19_cc_ind    = $this->validTeks(trim($_POST['covid19_cc_ind']));
    $covid19_rs_darurat_ind = $this->validTeks(trim($_POST['covid19_rs_darurat_ind']));
    $covid19_co_insidense_ind = $this->validTeks(trim($_POST['covid19_co_insidense_ind']));
    $terapi_konvalesen = $this->validTeks(trim($_POST['terapi_konvalesen']));
    $akses_naat        = $this->validTeks(trim($_POST['akses_naat']));
    $isoman_ind        = $this->validTeks(trim($_POST['isoman_ind']));
    $sistole = $this->validTeks(trim($_POST['sistole']));
    $diastole = $this->validTeks(trim($_POST['diastole']));
    $dializer_single_use = $this->validTeks(trim($_POST['dializer_single_use']));
    $kantong_darah     = $this->validTeks(trim($_POST['kantong_darah']));
    $usia_kehamilan     = $this->validTeks(trim($_POST['usia_kehamilan']));
    $onset_kontraksi     = $this->validTeks(trim($_POST['onset_kontraksi']));
    $delivery_method     = $this->validTeks(trim($_POST['delivery_method']));
    $delivery_dttm     = $this->validTeks(trim($_POST['delivery_dttm']));
    $letak_janin     = $this->validTeks(trim($_POST['letak_janin']));
    $kondisi     = $this->validTeks(trim($_POST['kondisi']));
    $use_manual     = $this->validTeks(trim($_POST['use_manual']));
    $use_forcep     = $this->validTeks(trim($_POST['use_forcep']));
    $use_vacuum     = $this->validTeks(trim($_POST['use_vacuum']));
    $appearance_1     = $this->validTeks(trim($_POST['appearance_1']));
    $pulse_1     = $this->validTeks(trim($_POST['pulse_1']));
    $grimace_1     = $this->validTeks(trim($_POST['grimace_1']));
    $activity_1     = $this->validTeks(trim($_POST['activity_1']));
    $respiration_1     = $this->validTeks(trim($_POST['respiration_1']));
    $appearance_5     = $this->validTeks(trim($_POST['appearance_5']));
    $pulse_5     = $this->validTeks(trim($_POST['pulse_5']));
    $grimace_5     = $this->validTeks(trim($_POST['grimace_5']));
    $activity_5     = $this->validTeks(trim($_POST['activity_5']));
    $respiration_5     = $this->validTeks(trim($_POST['respiration_5']));
    $tarif_poli_eks    = $this->validTeks(trim($_POST['tarif_poli_eks']));
    $nama_dokter       = $this->validTeks(trim($_POST['nama_dokter']));
    $jk                = $this->validTeks(trim($_POST['jk']));
    $tgl_lahir         = $this->validTeks(trim($_POST['tgl_lahir']));
    $no_sitb         = $this->validTeks(trim($_POST['sitb']));

    $jnsrawat="2";
    if($_POST['kd_poli'] == "IGDK"){
        $jnsrawat="3";
    }
    if($this->getRegPeriksaInfo('status_lanjut', $_POST['no_rawat']) == "Ranap"){
        $jnsrawat="1";
    }

    $gender = "";
    if($jk=="L"){
        $gender="1";
    }else{
        $gender="2";
    }
    
    $cek_claim ='{
                     "metadata": {
                         "method":"get_claim_data"
                     },
                     "data": {
                         "nomor_sep":"'.$nosep.'"
                     }
                }';

    $data_claim = $this->Request($cek_claim);
    if($data_claim['metadata']['message']=="Ok"){
        $this->EditUlangKlaim($nosep);
    } else {
        $this->BuatKlaimBaru($nokartu,$nosep,$no_rkm_medis,$nm_pasien,$tgl_lahir." 00:00:00", $gender,$norawat);
    }
    
    if ($no_sitb!=""){
    $this->CekSITB($nosep,$no_sitb);
    }

      if($this->getRegPeriksaInfo('status_lanjut', $_POST['no_rawat']) == "Ranap"){
        $this->SetKlaimRanap($nosep,$nokartu,$tgl_registrasi,$keluar,$cara_masuk,$jnsrawat,$kelas_rawat,$adl_sub_acute,
          $adl_chronic,$icu_indikator,$icu_los,$ventilator_hour,$use_ind,$start_dttm,$stop_dttm,$upgrade_class_ind,$upgrade_class_class,
          $upgrade_class_los,$upgrade_class_payor,$add_payment_pct,$birth_weight,$discharge_status,$diagnosa,$procedure,
          $tarif_poli_eks,$nama_dokter,$this->settings->get('vedika.eklaim_kelasrs'),$this->settings->get('vedika.eklaim_payor_id'),$this->settings->get('vedika.eklaim_payor_cd'),$this->settings->get('vedika.eklaim_cob_cd'),$this->_resolveCoderNik(),
          $prosedur_non_bedah,$prosedur_bedah,$konsultasi,$tenaga_ahli,$keperawatan,$penunjang,
          $radiologi,$laboratorium,$pelayanan_darah,$rehabilitasi,$kamar,$rawat_intensif,$obat,
          $obat_kronis,$obat_kemoterapi,$alkes,$bmhp,$sewa_alat,
          $pemulasaraan_jenazah,$kantong_jenazah,$peti_jenazah,$plastik_erat,$desinfektan_jenazah,$mobil_jenazah,$desinfektan_mobil_jenazah,
          $covid19_status_cd,$nomor_kartu_t,$episodes,$covid19_cc_ind,$covid19_rs_darurat_ind,$covid19_co_insidense_ind,
          $terapi_konvalesen,$akses_naat,$isoman_ind,$sistole,$diastole,$dializer_single_use,$kantong_darah,$usia_kehamilan,$onset_kontraksi,$delivery_method,$delivery_dttm,$letak_janin,$kondisi,$use_manual,$use_forcep,$use_vacuum,
          $appearance_1,$pulse_1,$grimace_1,$activity_1,$respiration_1,$appearance_5,$pulse_5,$grimace_5,$activity_5,$respiration_5,$no_sitb);
      }
      else{
      $this->SetKlaimRalan($nosep,$nokartu,$tgl_registrasi,$keluar,$cara_masuk,$jnsrawat,$kelas_rawat,$adl_sub_acute,
          $adl_chronic,$icu_indikator,$icu_los,$ventilator_hour,$use_ind,$start_dttm,$stop_dttm,$upgrade_class_ind,$upgrade_class_class,
          $upgrade_class_los,$upgrade_class_payor,$add_payment_pct,$birth_weight,$discharge_status,$diagnosa,$procedure,
          $tarif_poli_eks,$nama_dokter,$this->settings->get('vedika.eklaim_kelasrs'),$this->settings->get('vedika.eklaim_payor_id'),$this->settings->get('vedika.eklaim_payor_cd'),$this->settings->get('vedika.eklaim_cob_cd'),$this->_resolveCoderNik(),
          $prosedur_non_bedah,$prosedur_bedah,$konsultasi,$tenaga_ahli,$keperawatan,$penunjang,
          $radiologi,$laboratorium,$pelayanan_darah,$rehabilitasi,$kamar,$rawat_intensif,$obat,
          $obat_kronis,$obat_kemoterapi,$alkes,$bmhp,$sewa_alat,
          $pemulasaraan_jenazah,$kantong_jenazah,$peti_jenazah,$plastik_erat,$desinfektan_jenazah,$mobil_jenazah,$desinfektan_mobil_jenazah,
          $covid19_status_cd,$nomor_kartu_t,$episodes,$covid19_cc_ind,$covid19_rs_darurat_ind,$covid19_co_insidense_ind,
          $terapi_konvalesen,$akses_naat,$isoman_ind,$sistole,$diastole,$dializer_single_use,$kantong_darah,$usia_kehamilan,$onset_kontraksi,$delivery_method,$delivery_dttm,$letak_janin,$kondisi,$use_manual,$use_forcep,$use_vacuum,
          $appearance_1,$pulse_1,$grimace_1,$activity_1,$respiration_1,$appearance_5,$pulse_5,$grimace_5,$activity_5,$respiration_5,$no_sitb);
      }
    

    exit();
  }
  
  public function postProsesKlaimFull()
  {
    $_POST['tgl_lahir'] = $this->core->getRegPeriksaInfo('tgl_lahir', $_POST['no_rawat']);;
    $no_rkm_medis      = $this->validTeks(trim($_POST['no_rkm_medis']));
    $norawat           = $this->validTeks(trim($_POST['no_rawat']));
    $tgl_registrasi    = $this->validTeks(trim($_POST['tgl_registrasi']));
    $nosep             = $this->validTeks(trim($_POST['nosep']));
    $nokartu           = $this->validTeks(trim($_POST['nokartu']));
    $nm_pasien         = $this->validTeks(trim($_POST['nm_pasien']));
    $keluar            = $this->validTeks(trim($_POST['keluar']));
    $cara_masuk        = $this->validTeks(trim($_POST['cara_masuk']));
    $kelas_rawat       = $this->validTeks(trim($_POST['kelas_rawat']));
    $adl_sub_acute     = $this->validTeks(trim($_POST['adl_sub_acute']));
    $adl_chronic       = $this->validTeks(trim($_POST['adl_chronic']));
    $icu_indikator     = $this->validTeks(trim($_POST['icu_indikator']));
    $icu_los           = $this->validTeks(trim($_POST['icu_los']));
    $ventilator_hour   = $this->validTeks(trim($_POST['ventilator_hour']));
    $use_ind           = $this->validTeks(trim($_POST['use_ind']));
    $start_dttm        = $this->validTeks(trim($_POST['start_dttm']));
    $stop_dttm         = $this->validTeks(trim($_POST['stop_dttm']));
    $ventilator_hour   = $this->validTeks(trim($_POST['ventilator_hour']));
    $upgrade_class_ind = $this->validTeks(trim($_POST['upgrade_class_ind']));
    $upgrade_class_class = $this->validTeks(trim($_POST['upgrade_class_class']));
    $upgrade_class_los = $this->validTeks(trim($_POST['upgrade_class_los']));
    $upgrade_class_payor = $this->validTeks(trim($_POST['upgrade_class_payor']));
    $add_payment_pct   = $this->validTeks(trim($_POST['add_payment_pct']));
    $birth_weight      = $this->validTeks(trim($_POST['birth_weight']));
    $discharge_status  = $this->validTeks(trim($_POST['discharge_status']));
    $diagnosa          = $this->validTeks(trim($_POST['diagnosa']));
    $procedure         = $this->validTeks(trim($_POST['procedure']));
    $prosedur_non_bedah = $this->validTeks(trim($_POST['prosedur_non_bedah']));
    $prosedur_bedah    = $this->validTeks(trim($_POST['prosedur_bedah']));
    $konsultasi        = $this->validTeks(trim($_POST['konsultasi']));
    $tenaga_ahli       = $this->validTeks(trim($_POST['tenaga_ahli']));
    $keperawatan       = $this->validTeks(trim($_POST['keperawatan']));
    $penunjang         = $this->validTeks(trim($_POST['penunjang']));
    $radiologi         = $this->validTeks(trim($_POST['radiologi']));
    $laboratorium      = $this->validTeks(trim($_POST['laboratorium']));
    $pelayanan_darah   = $this->validTeks(trim($_POST['pelayanan_darah']));
    $rehabilitasi      = $this->validTeks(trim($_POST['rehabilitasi']));
    $kamar             = $this->validTeks(trim($_POST['kamar']));
    $rawat_intensif    = $this->validTeks(trim($_POST['rawat_intensif']));
    $obat              = $this->validTeks(trim($_POST['obat']));
    $obat_kronis       = $this->validTeks(trim($_POST['obat_kronis']));
    $obat_kemoterapi   = $this->validTeks(trim($_POST['obat_kemoterapi']));
    $alkes             = $this->validTeks(trim($_POST['alkes']));
    $bmhp              = $this->validTeks(trim($_POST['bmhp']));
    $sewa_alat         = $this->validTeks(trim($_POST['sewa_alat']));
    $pemulasaraan_jenazah = $this->validTeks(trim($_POST['pemulasaraan_jenazah']));
    $kantong_jenazah   = $this->validTeks(trim($_POST['kantong_jenazah']));
    $peti_jenazah      = $this->validTeks(trim($_POST['peti_jenazah']));
    $plastik_erat      = $this->validTeks(trim($_POST['plastik_erat']));
    $desinfektan_jenazah = $this->validTeks(trim($_POST['desinfektan_jenazah']));
    $mobil_jenazah     = $this->validTeks(trim($_POST['mobil_jenazah']));
    $desinfektan_mobil_jenazah = $this->validTeks(trim($_POST['desinfektan_mobil_jenazah']));
    $covid19_status_cd = $this->validTeks(trim($_POST['covid19_status_cd']));
    $nomor_kartu_t     = $this->validTeks(trim($_POST['nomor_kartu_t']));
    $episodes          = $this->validTeks(trim($_POST['episodes']));
    $covid19_cc_ind    = $this->validTeks(trim($_POST['covid19_cc_ind']));
    $covid19_rs_darurat_ind = $this->validTeks(trim($_POST['covid19_rs_darurat_ind']));
    $covid19_co_insidense_ind = $this->validTeks(trim($_POST['covid19_co_insidense_ind']));
    $terapi_konvalesen = $this->validTeks(trim($_POST['terapi_konvalesen']));
    $akses_naat        = $this->validTeks(trim($_POST['akses_naat']));
    $isoman_ind        = $this->validTeks(trim($_POST['isoman_ind']));
    $sistole = $this->validTeks(trim($_POST['sistole']));
    $diastole = $this->validTeks(trim($_POST['diastole']));
    $dializer_single_use = $this->validTeks(trim($_POST['dializer_single_use']));
    $kantong_darah     = $this->validTeks(trim($_POST['kantong_darah']));
    $usia_kehamilan     = $this->validTeks(trim($_POST['usia_kehamilan']));
    $onset_kontraksi     = $this->validTeks(trim($_POST['onset_kontraksi']));
    $delivery_method     = $this->validTeks(trim($_POST['delivery_method']));
    $delivery_dttm     = $this->validTeks(trim($_POST['delivery_dttm']));
    $letak_janin     = $this->validTeks(trim($_POST['letak_janin']));
    $kondisi     = $this->validTeks(trim($_POST['kondisi']));
    $use_manual     = $this->validTeks(trim($_POST['use_manual']));
    $use_forcep     = $this->validTeks(trim($_POST['use_forcep']));
    $use_vacuum     = $this->validTeks(trim($_POST['use_vacuum']));
    $appearance_1     = $this->validTeks(trim($_POST['appearance_1']));
    $pulse_1     = $this->validTeks(trim($_POST['pulse_1']));
    $grimace_1     = $this->validTeks(trim($_POST['grimace_1']));
    $activity_1     = $this->validTeks(trim($_POST['activity_1']));
    $respiration_1     = $this->validTeks(trim($_POST['respiration_1']));
    $appearance_5     = $this->validTeks(trim($_POST['appearance_5']));
    $pulse_5     = $this->validTeks(trim($_POST['pulse_5']));
    $grimace_5     = $this->validTeks(trim($_POST['grimace_5']));
    $activity_5     = $this->validTeks(trim($_POST['activity_5']));
    $respiration_5     = $this->validTeks(trim($_POST['respiration_5']));
    $tarif_poli_eks    = $this->validTeks(trim($_POST['tarif_poli_eks']));
    $nama_dokter       = $this->validTeks(trim($_POST['nama_dokter']));
    $jk                = $this->validTeks(trim($_POST['jk']));
    $tgl_lahir         = $this->validTeks(trim($_POST['tgl_lahir']));
    $no_sitb         = $this->validTeks(trim($_POST['sitb']));
    
    $diagSplit = $this->splitDiagnosaIM($diagnosa);
    $procSplit = $this->splitProcedureIM($procedure);
    
    $diagnosaIDRG   = $diagSplit['idrg'];
    $diagnosaINACBG = $diagSplit['inacbg'];
    
    $procedureIDRG   = $procSplit['idrg'];
    $procedureINACBG = $procSplit['inacbg'];

    $jnsrawat="2";
    if($_POST['kd_poli'] == "IGDK"){
        $jnsrawat="3";
    }
    if($this->getRegPeriksaInfo('status_lanjut', $_POST['no_rawat']) == "Ranap"){
        $jnsrawat="1";
    }

    $gender = "";
    if($jk=="L"){
        $gender="1";
    }else{
        $gender="2";
    }
    
    $cek_claim ='{
                     "metadata": {
                         "method":"get_claim_data"
                     },
                     "data": {
                         "nomor_sep":"'.$nosep.'"
                     }
                }';

    $data_claim = $this->Request($cek_claim);
    
    $result = [
        'ok' => false,
        'last_step' => null,
        'steps' => [
            'validasi_diagnosa_inacbg' => null,
            'buat_klaim' => null,
            'edit_klaim' => null,
            'set_klaim' => null,
            'grouper_idrg' => null,
            'final_idrg' => null,
            'grouper_inacbg' => null,
            'final_inacbg' => null,
            'final_klaim' => null,
            'kirim_datacenter' => null,
        ]
    ];

    // INACBG wajib memiliki minimal satu diagnosis non-IM. Kode IM tetap
    // dipertahankan untuk IDRG, tetapi sengaja tidak dikirim ke INACBG.
    if (trim((string) $diagnosaIDRG) === '') {
        return $this->stop($result, 'validasi_diagnosa_inacbg', [
            'ok' => false,
            'message' => 'Diagnosis ICD-10 belum diisi. Tambahkan minimal satu diagnosis yang sesuai sebelum mengirim klaim.'
        ]);
    }
    if (trim((string) $diagnosaINACBG) === '') {
        $imCodes = isset($diagSplit['im_codes']) ? $diagSplit['im_codes'] : $diagnosaIDRG;
        return $this->stop($result, 'validasi_diagnosa_inacbg', [
            'ok' => false,
            'message' => $this->_onlyIMDiagnosisMessage($imCodes)
        ]);
    }
    $result['steps']['validasi_diagnosa_inacbg'] = true;
    
    $isReedit = false;

    // STEP 1
    if($data_claim['metadata']['message']=="Ok"){
        $r = $this->EditUlangKlaim($nosep);
        if (!$r['ok']) return $this->stop($result, 'edit_klaim', $r);
        $result['steps']['edit_klaim'] = true;
        $isReedit = true;
    } else {
        $r = $this->BuatKlaimBaru($nokartu,$nosep,$no_rkm_medis,$nm_pasien,$tgl_lahir." 00:00:00", $gender,$norawat);
        if (!$r['ok']) return $this->stop($result, 'buat_klaim', $r);
        $result['steps']['buat_klaim'] = true;
    }
    
    // + SITB
    if ($no_sitb!=""){
    $this->CekSITB($nosep,$no_sitb);
    }

    // STEP 2
    if($this->getRegPeriksaInfo('status_lanjut', $_POST['no_rawat']) == "Ranap"){
    $r =  $this->SetKlaimRanap($nosep,$nokartu,$tgl_registrasi,$keluar,$cara_masuk,$jnsrawat,$kelas_rawat,$adl_sub_acute,
          $adl_chronic,$icu_indikator,$icu_los,$ventilator_hour,$use_ind,$start_dttm,$stop_dttm,$upgrade_class_ind,$upgrade_class_class,
          $upgrade_class_los,$upgrade_class_payor,$add_payment_pct,$birth_weight,$discharge_status,$diagnosa,$procedure,
          $tarif_poli_eks,$nama_dokter,$this->settings->get('vedika.eklaim_kelasrs'),$this->settings->get('vedika.eklaim_payor_id'),$this->settings->get('vedika.eklaim_payor_cd'),$this->settings->get('vedika.eklaim_cob_cd'),$this->_resolveCoderNik(),
          $prosedur_non_bedah,$prosedur_bedah,$konsultasi,$tenaga_ahli,$keperawatan,$penunjang,
          $radiologi,$laboratorium,$pelayanan_darah,$rehabilitasi,$kamar,$rawat_intensif,$obat,
          $obat_kronis,$obat_kemoterapi,$alkes,$bmhp,$sewa_alat,
          $pemulasaraan_jenazah,$kantong_jenazah,$peti_jenazah,$plastik_erat,$desinfektan_jenazah,$mobil_jenazah,$desinfektan_mobil_jenazah,
          $covid19_status_cd,$nomor_kartu_t,$episodes,$covid19_cc_ind,$covid19_rs_darurat_ind,$covid19_co_insidense_ind,
          $terapi_konvalesen,$akses_naat,$isoman_ind,$sistole,$diastole,$dializer_single_use,$kantong_darah,$usia_kehamilan,$onset_kontraksi,$delivery_method,$delivery_dttm,$letak_janin,$kondisi,$use_manual,$use_forcep,$use_vacuum,
          $appearance_1,$pulse_1,$grimace_1,$activity_1,$respiration_1,$appearance_5,$pulse_5,$grimace_5,$activity_5,$respiration_5,$no_sitb);
    }
    else {
    $r =  $this->SetKlaimRalan($nosep,$nokartu,$tgl_registrasi,$keluar,$cara_masuk,$jnsrawat,$kelas_rawat,$adl_sub_acute,
          $adl_chronic,$icu_indikator,$icu_los,$ventilator_hour,$use_ind,$start_dttm,$stop_dttm,$upgrade_class_ind,$upgrade_class_class,
          $upgrade_class_los,$upgrade_class_payor,$add_payment_pct,$birth_weight,$discharge_status,$diagnosa,$procedure,
          $tarif_poli_eks,$nama_dokter,$this->settings->get('vedika.eklaim_kelasrs'),$this->settings->get('vedika.eklaim_payor_id'),$this->settings->get('vedika.eklaim_payor_cd'),$this->settings->get('vedika.eklaim_cob_cd'),$this->_resolveCoderNik(),
          $prosedur_non_bedah,$prosedur_bedah,$konsultasi,$tenaga_ahli,$keperawatan,$penunjang,
          $radiologi,$laboratorium,$pelayanan_darah,$rehabilitasi,$kamar,$rawat_intensif,$obat,
          $obat_kronis,$obat_kemoterapi,$alkes,$bmhp,$sewa_alat,
          $pemulasaraan_jenazah,$kantong_jenazah,$peti_jenazah,$plastik_erat,$desinfektan_jenazah,$mobil_jenazah,$desinfektan_mobil_jenazah,
          $covid19_status_cd,$nomor_kartu_t,$episodes,$covid19_cc_ind,$covid19_rs_darurat_ind,$covid19_co_insidense_ind,
          $terapi_konvalesen,$akses_naat,$isoman_ind,$sistole,$diastole,$dializer_single_use,$kantong_darah,$usia_kehamilan,$onset_kontraksi,$delivery_method,$delivery_dttm,$letak_janin,$kondisi,$use_manual,$use_forcep,$use_vacuum,
          $appearance_1,$pulse_1,$grimace_1,$activity_1,$respiration_1,$appearance_5,$pulse_5,$grimace_5,$activity_5,$respiration_5,$no_sitb);  
    }
    if (!$r['ok']) return $this->stop($result, 'set_klaim', $r);
    $result['steps']['set_klaim'] = true;

    // STEP 3 IDRG
    $r = $this->GroupingIDRG($nosep,$diagnosa,$procedure);
    if (!$r['ok']) return $this->stop($result, 'grouper_idrg', $r);
    $result['steps']['grouper_idrg'] = true;
    $result['steps']['final_idrg'] = true;

    // STEP 5 INACBG
    $r = $this->GroupingStage($nosep,$diagnosaINACBG,$procedureINACBG);
    if (!$r['ok']) return $this->stop($result, 'grouper_inacbg', $r);
    $result['steps']['grouper_inacbg'] = true;
    $result['steps']['final_inacbg'] = true;

    // FINAL KLAIM sudah diverifikasi di dalam GroupingStage().
    $result['steps']['final_klaim'] = true;

    // STEP TERAKHIR: KIRIM KE DATA CENTER
    $r = $this->KirimKlaimIndividualKeDC($nosep, $isReedit);
    if (!$r['ok']) return $this->stop($result, 'kirim_datacenter', $r);
    $result['steps']['kirim_datacenter'] = true;

    $result['ok'] = true;
    $result['last_step'] = 'kirim_datacenter';
    $result['datacenter'] = $r;

    return $this->jsonResponse($result);
  }
  
  public function postSetIDRG()
  {
    $nosep             = $this->validTeks(trim($_POST['nosep']));
    $diagnosa          = $this->validTeks(trim($_POST['diagnosa']));
    $procedure         = $this->validTeks(trim($_POST['procedure']));
    
    // $this->CekGroupingIDRG($nosep,$diagnosa,$procedure);
    $this->CekGroupingStage($nosep,$diagnosa,$procedure);

    exit();
  }
  
  private function CekGroupingIDRG($nomor_sep,$diagnosa,$procedure){
      $request ='{
                      "metadata": {
                          "method":"idrg_diagnosa_set",
                          "nomor_sep":"'.$nomor_sep.'"
                      },
                      "data": {
                          "diagnosa":"'.$diagnosa.'"
                      }
                 }';
      $msg= $this->Request($request);
      
          echo "\n Set Diagnosa IDRG\n";
          echo json_encode($msg);
          echo "\n\n";
      
      $request1 ='{
                      "metadata": {
                          "method":"idrg_procedure_set",
                          "nomor_sep":"'.$nomor_sep.'"
                      },
                      "data": {
                          "procedure":"'.$procedure.'"
                      }
                 }';
      $msg1= $this->Request($request1);

          echo "\n Set Prosedure IDRG\n";
          echo json_encode($msg1);
          echo "\n\n";
          
      $grouper ='{
                      "metadata": {
                          "method":"grouper",
                          "stage":"1",
                          "grouper": "idrg"
                      },
                      "data": {
                          "nomor_sep":"'.$nomor_sep.'"
                      }
                 }';
      $msgs= $this->Request($grouper);
      if($msgs['metadata']['message']=="Ok"){
        echo "\n Hasil Grouper IDRG\n";
        echo json_encode($msgs);  
        echo "\n\n";
        $this->CekFinalIDRG($nomor_sep,$diagnosa,$procedure);
      }
  }
  
  private function CekFinalIDRG($nomor_sep,$diagnosa,$procedure){
      $request ='{
                      "metadata": {
                          "method":"idrg_grouper_final"
                      },
                      "data": {
                          "nomor_sep":"'.$nomor_sep.'"
                      }
                 }';
      $msg= $this->Request($request);
      
      if($msg['metadata']['message']=="Ok"){
        echo "\n Hasil Final IDRG\n";  
        echo json_encode($msg);  
      }
  }
  
  private function CekGroupingStage($nomor_sep,$diagnosa,$procedure){
      $request0 ='{
                          "metadata": {
                              "method":"inacbg_diagnosa_set",
                              "nomor_sep":"'.$nomor_sep.'"
                          },
                          "data": {
                              "diagnosa":"'.$diagnosa.'"
                          }
                     }';
      $msg0= $this->Request($request0);
      echo "\n Set Diagnosa Inacbg\n";
          echo json_encode($msg0);
          echo "\n\n";
          
      $request1 ='{
                          "metadata": {
                              "method":"inacbg_procedure_set",
                              "nomor_sep":"'.$nomor_sep.'"
                          },
                          "data": {
                              "procedure":"'.$procedure.'"
                          }
                     }';
      $msg1= $this->Request($request1);
          echo "\n Set Procedure Inacbg\n";
          echo json_encode($msg1);
          echo "\n\n";

      $request ='{
                      "metadata": {
                          "method":"grouper",
                          "stage":"1",
                          "grouper": "inacbg"
                      },
                      "data": {
                          "nomor_sep":"'.$nomor_sep.'"
                      }
                 }';
      $msg= $this->Request($request);
      if($msg['metadata']['message']=="Ok"){
          echo "Group S1\n";
          echo json_encode($msg);
          echo "\n\n";
        $topup = $msg['special_cmg_option']?$msg['special_cmg_option']:'';
        if($topup!=''){
          $temp_grouper="";
          $i = 0;
          foreach ($topup as $data) {
            if($i==0){
              $temp_grouper.=$data['code'];
            }else{
              $temp_grouper.='#'.$data['code'];
            }
            $i+=1;
          }
          $request2 ='{
            "metadata": {
                "method":"grouper",
                "stage":"2",
                "grouper": "inacbg"
            },
            "data": {
                "nomor_sep":"'.$nomor_sep.'",
                "special_cmg":"'.$temp_grouper.'"
            }
          }';
          $msg2= $this->Request($request2);
          if($msg2['metadata']['message']=="Ok"){
              echo "Group S2\n";
              echo json_encode($msg);
              echo "\n\n";
              $this->CekGroupingStageFinal($nomor_sep);
          }
        }else if($topup==''){
          $this->CekGroupingStageFinal($nomor_sep);
        }
      }
  }
  
  private function CekGroupingStageFinal($nomor_sep){
      $request ='{
                      "metadata": {
                          "method":"inacbg_grouper_final"
                      },
                      "data": {
                          "nomor_sep":"'.$nomor_sep.'"
                      }
                 }';
      $msg= $this->Request($request);
      if($msg['metadata']['message']=="Ok"){
          echo "\n\n";
          echo "Inacbg Final\n";
          echo json_encode($msg);
          $this->CekFinalisasiKlaim($nomor_sep);
      }
  }

  private function CekFinalisasiKlaim($nomor_sep){
      $request ='{
                      "metadata": {
                          "method":"claim_final"
                      },
                      "data": {
                          "nomor_sep":"'.$nomor_sep.'",
                          "coder_nik": "123123123123"
                      }
                 }';
      $msg= $this->Request($request);
      if($msg['metadata']['message']=="Ok"){
          echo "\n\n";
          echo "Final Klaim\n";
          echo json_encode($msg);
      }
  }
  
  public function postEditKlaim()
{
    header('Content-Type: application/json');

    $nosep = $_POST['nosep'] ?? null;
    if (empty($nosep)) {
        echo json_encode([
            'metadata' => [
                'code' => 400,
                'message' => 'Nomor SEP tidak boleh kosong'
            ]
        ]);
        exit;
    }

    // 1. Reedit Claim
    $reqReedit = json_encode([
        'metadata' => ['method' => 'reedit_claim'],
        'data' => ['nomor_sep' => $nosep]
    ]);
    $resReedit = $this->Request($reqReedit);

    // 2. IDRG Grouper Reedit
    $reqIDRG = json_encode([
        'metadata' => ['method' => 'idrg_grouper_reedit'],
        'data' => ['nomor_sep' => $nosep]
    ]);
    $resIDRG = $this->Request($reqIDRG);

    echo json_encode([
        'metadata' => [
            'code' => 200,
            'message' => 'Edit klaim diproses'
        ],
        'data' => [
            'reedit_claim' => $resReedit,
            'idrg_grouper_reedit' => $resIDRG
        ]
    ]);
    exit;
}

  public function postKirimDataCenter()
  {
    $nosep = isset($_POST['nosep']) ? $this->validTeks(trim($_POST['nosep'])) : '';

    if ($nosep === '') {
      $this->jsonResponse([
        'metadata' => [
          'code' => 400,
          'message' => 'Nomor SEP tidak boleh kosong'
        ]
      ]);
    }

    $result = $this->KirimKlaimIndividualKeDC($nosep);
    $this->jsonResponse($result['response']);
  }


  public function getKlaimPDF($nosep)
  {
    $request ='{
                    "metadata": {
                        "method":"claim_print"
                    },
                    "data": {
                        "nomor_sep":"'.$nosep.'"
                    }
               }';

    $msg = $this->Request($request);
    if($msg['metadata']['message']=="Ok"){
        // variable data adalah base64 dari file pdf
        $pdf = base64_decode($msg['data']);
        // atau untuk ditampilkan dengan perintah:
        header("Content-type:application/pdf");
        ob_clean();
        flush();
        echo $pdf;
    }

    exit();
  }

  private function Request($request){
      $json = $this->mc_encrypt ($request, $this->settings->get('vedika.eklaim_key'));
      $header = array("Content-Type: application/x-www-form-urlencoded");
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $this->settings->get('vedika.eklaim_url'));
      curl_setopt($ch, CURLOPT_HEADER, 0);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
      $response = curl_exec($ch);
      $first = strpos($response, "\n")+1;
      $last = strrpos($response, "\n")-1;
      $hasilresponse = substr($response,$first,strlen($response) - $first - $last);
      $hasildecrypt = $this->mc_decrypt($hasilresponse, $this->settings->get('vedika.eklaim_key'));
      //echo $hasildecrypt;
      $msg = json_decode($hasildecrypt,true);
      return $msg;
  }

  private function mc_encrypt($data, $strkey) {
      $key = hex2bin($strkey);
      if (mb_strlen($key, "8bit") !== 32) {
              throw new Exception("Needs a 256-bit key!");
      }

      $iv_size = openssl_cipher_iv_length("aes-256-cbc");
      $iv = openssl_random_pseudo_bytes($iv_size);
      $encrypted = openssl_encrypt($data,"aes-256-cbc",$key,OPENSSL_RAW_DATA,$iv );
      $signature = mb_substr(hash_hmac("sha256",$encrypted,$key,true),0,10,"8bit");
      $encoded = chunk_split(base64_encode($signature.$iv.$encrypted));
      return $encoded;
  }

  private function mc_decrypt($str, $strkey){
      $key = hex2bin($strkey);
      if (mb_strlen($key, "8bit") !== 32) {
          throw new Exception("Needs a 256-bit key!");
      }

      $iv_size = openssl_cipher_iv_length("aes-256-cbc");
      $decoded = base64_decode($str);
      $signature = mb_substr($decoded,0,10,"8bit");
      $iv = mb_substr($decoded,10,$iv_size,"8bit");
      $encrypted = mb_substr($decoded,$iv_size+10,NULL,"8bit");
      $calc_signature = mb_substr(hash_hmac("sha256",$encrypted,$key,true),0,10,"8bit");
      if(!$this->mc_compare($signature,$calc_signature)) {
          return "SIGNATURE_NOT_MATCH";
      }

      $decrypted = openssl_decrypt($encrypted,"aes-256-cbc",$key,OPENSSL_RAW_DATA,$iv);
      return $decrypted;
  }

  private function mc_compare($a, $b) {
      if (strlen($a) !== strlen($b)) {
          return false;
      }

      $result = 0;

      for($i = 0; $i < strlen($a); $i ++) {
          $result |= ord($a[$i]) ^ ord($b[$i]);
      }

      return $result == 0;
  }

  private function validTeks($data){
      $save=str_replace("'","",$data);
      $save=str_replace("\\","",$save);
      $save=str_replace(";","",$save);
      $save=str_replace("`","",$save);
      $save=str_replace("--","",$save);
      $save=str_replace("/*","",$save);
      $save=str_replace("*/","",$save);
      //$save=str_replace("#","",$save);
      return $save;
  }

  private function Grouping($nomor_sep){
    $request ='{
                    "metadata": {
                        "method":"grouper",
                        "stage":"1"
                    },
                    "data": {
                        "nomor_sep":"'.$nomor_sep.'"
                    }
               }';
    $msg= $this->Request($request);
    if($msg['metadata']['message']=="Ok"){
      $topup = $msg['special_cmg_option']?$msg['special_cmg_option']:'';
      if($topup!=''){
        $temp_grouper="";
        $i = 0;
        foreach ($topup as $data) {
          if($i==0){
            $temp_grouper.=$data['code'];
          }else{
            $temp_grouper.='#'.$data['code'];
          }
          $i+=1;
        }
        $request2 ='{
          "metadata": {
              "method":"grouper",
              "stage":"2"
          },
          "data": {
              "nomor_sep":"'.$nomor_sep.'",
              "special_cmg":"'.$temp_grouper.'"
          }
        }';
        $msg2= $this->Request($request2);
        if($msg2['metadata']['message']=="Ok"){
        }
      }else if($topup==''){
      }
    }
}
  private function Grouper($nomor_sep,$coder_nik){
      $request ='{
                      "metadata": {
                          "method":"grouper",
                          "stage":"1"
                      },
                      "data": {
                          "nomor_sep":"'.$nomor_sep.'"
                      }
                 }';
      $msg= $this->Request($request);
      if($msg['metadata']['message']=="Ok"){
          if($msg['response']['cbg']['tariff'] == '') {
            $tarif = '0';
          } else {
            $tarif = $msg['response']['cbg']['tariff'];
          }
          echo '<dt>Grouper</dt> <dd>'.$msg['response']['cbg']['code'].'</dd><br>';
          echo '<dt>Deskripsi</dt> <dd>'.$msg['response']['cbg']['description'].'</dd><br>';
          echo '<dt>Tarif INACBG\'s</dt> <dd>Rp. '.number_format($tarif,0,",",".").'</dd><br><br>';
      }
  }

  private function BuatKlaimBaru($nomor_kartu, $nomor_sep, $nomor_rm, $nama_pasien, $tgl_lahir, $gender, $norawat)
{
    $request = [
        'metadata' => ['method' => 'new_claim'],
        'data' => [
            'nomor_kartu' => $nomor_kartu,
            'nomor_sep' => $nomor_sep,
            'nomor_rm' => $nomor_rm,
            'nama_pasien' => $nama_pasien,
            'tgl_lahir' => $tgl_lahir,
            'gender' => $gender
        ]
    ];

    $msg = $this->Request(json_encode($request));

    if (($msg['metadata']['message'] ?? '') === "Ok") {
        // simpan ke DB
        $this->db('inacbg_klaim_baru')->save([
            'no_sep'  => $nomor_sep,
            'patient_id' => $msg['response']['patient_id'],
            'admission_id' => $msg['response']['admission_id'],
            'hospital_admission_id' => $msg['response']['hospital_admission_id'],
        ]);

        return [
            'ok' => true,
            'response' => $msg
        ];
    }

    return [
        'ok' => false,
        'response' => $msg
    ];
}

private function CekSITB($nomor_sep,$sitb){
        // if ($sitb!=""){
            $request ='{
                        "metadata": {
                            "method":"sitb_validate"
                        },
                        "data": {
                            "nomor_sep":"'.$nomor_sep.'",
                            "nomor_register_sitb":"'.$sitb.'"
                        }
                   }';
        // echo "Data : ".$request;
        $msg= $this->Request($request);
        // }
    }

  private function EditUlangKlaim($nomor_sep){
      $request ='{
                      "metadata": {
                          "method":"reedit_claim"
                      },
                      "data": {
                          "nomor_sep":"'.$nomor_sep.'"
                      }
                 }';
      $msg = $this->Request($request);
      $reeditSuccess = $this->_isClaimReadyForEdit($msg);

      if (!$reeditSuccess) {
          return [
              'ok' => false,
              'error_at' => 'reedit_claim',
              'response' => $msg
          ];
      }

      // Klaim yang sudah final juga harus membuka kembali hasil grouper IDRG.
      // Tanpa langkah ini set_claim_data dapat ditolak: "coding sudah final".
      $idrgRequest = json_encode([
          'metadata' => ['method' => 'idrg_grouper_reedit'],
          'data' => ['nomor_sep' => $nomor_sep]
      ]);
      $idrgResponse = $this->Request($idrgRequest);
      $idrgSuccess = $this->_isClaimReadyForEdit($idrgResponse);

      return [
          'ok' => $idrgSuccess,
          'error_at' => $idrgSuccess ? null : 'idrg_grouper_reedit',
          'response' => $idrgResponse,
          'reedit_claim' => $msg,
          'idrg_grouper_reedit' => $idrgResponse
      ];
  }

  private function _isClaimReadyForEdit($response)
  {
      if (!is_array($response) || !isset($response['metadata']['message'])) {
          return false;
      }

      $message = strtolower(trim((string) $response['metadata']['message']));
      if ($message === 'ok') {
          return true;
      }

      // Retry-safe: pesan ini berarti final sudah terbuka dari percobaan
      // sebelumnya, sehingga set dan grouping ulang boleh dilanjutkan.
      return strpos($message, 'belum final') !== false
          || strpos($message, 'not final') !== false;
  }

  private function postDeleteKlaim($nomor_sep){
    $request ='{
                    "metadata": {
                        "method":"delete_claim"
                    },
                    "data": {
                        "nomor_sep":"'.$nomor_sep.'"
                    }
               }';
    $msg= $this->Request($request);
    echo $msg['metadata']['message']."";
  }

  private function SetKlaimRalan(
    $nomor_sep, $nomor_kartu, $tgl_masuk, $tgl_pulang, $cara_masuk, $jenis_rawat, $kelas_rawat, $adl_sub_acute,
    $adl_chronic, $icu_indikator, $icu_los, $ventilator_hour, $use_ind, $start_dttm, $stop_dttm, $upgrade_class_ind, $upgrade_class_class,
    $upgrade_class_los, $upgrade_class_payor, $add_payment_pct, $birth_weight, $discharge_status, $diagnosa, $procedure,
    $tarif_poli_eks, $nama_dokter, $kode_tarif, $payor_id, $payor_cd, $cob_cd, $coder_nik,
    $prosedur_non_bedah, $prosedur_bedah, $konsultasi, $tenaga_ahli, $keperawatan, $penunjang,
    $radiologi, $laboratorium, $pelayanan_darah, $rehabilitasi, $kamar, $rawat_intensif, $obat,
    $obat_kronis, $obat_kemoterapi, $alkes, $bmhp, $sewa_alat,
    $pemulasaraan_jenazah, $kantong_jenazah, $peti_jenazah, $plastik_erat, $desinfektan_jenazah, $mobil_jenazah, $desinfektan_mobil_jenazah,
    $covid19_status_cd, $nomor_kartu_t, $episodes, $covid19_cc_ind, $covid19_rs_darurat_ind, $covid19_co_insidense_ind,
    $terapi_konvalesen, $akses_naat, $isoman_ind, $sistole, $diastole, $dializer_single_use, $kantong_darah, $usia_kehamilan, $onset_kontraksi, $delivery_method, $delivery_dttm, $letak_janin, $kondisi, $use_manual, $use_forcep, $use_vacuum,
    $appearance_1, $pulse_1, $grimace_1, $activity_1, $respiration_1, $appearance_5, $pulse_5, $grimace_5, $activity_5, $respiration_5, $no_sitb
) {
    $request = [
        'metadata' => [
            'method' => 'set_claim_data',
            'nomor_sep' => $nomor_sep
        ],
        'data' => [
            'nomor_sep' => $nomor_sep,
            'nomor_kartu' => $nomor_kartu,
            'tgl_masuk' => $tgl_masuk.' 00:00:01',
            'tgl_pulang' => $tgl_pulang.' 23:59:59',
            'cara_masuk' => $cara_masuk,
            'jenis_rawat' => $jenis_rawat,
            'kelas_rawat' => $kelas_rawat,
            'adl_sub_acute' => $adl_sub_acute,
            'adl_chronic' => $adl_chronic,
            'icu_indikator' => $icu_indikator,
            'icu_los' => $icu_los,
            'ventilator_hour' => $ventilator_hour,
            'ventilator' => [
                'use_ind' => $use_ind,
                'start_dttm' => $start_dttm,
                'stop_dttm' => $stop_dttm
            ],
            'upgrade_class_ind' => $upgrade_class_ind,
            'upgrade_class_class' => $upgrade_class_class,
            'upgrade_class_los' => $upgrade_class_los,
            'upgrade_class_payor' => $upgrade_class_payor,
            'add_payment_pct' => $add_payment_pct,
            'birth_weight' => $birth_weight,
            'sistole' => intval($sistole),
            'diastole' => intval($diastole),
            'discharge_status' => $discharge_status,
            'tarif_rs' => [
                'prosedur_non_bedah' => $prosedur_non_bedah,
                'prosedur_bedah' => $prosedur_bedah,
                'konsultasi' => $konsultasi,
                'tenaga_ahli' => $tenaga_ahli,
                'keperawatan' => $keperawatan,
                'penunjang' => $penunjang,
                'radiologi' => $radiologi,
                'laboratorium' => $laboratorium,
                'pelayanan_darah' => $pelayanan_darah,
                'rehabilitasi' => $rehabilitasi,
                'kamar' => $kamar,
                'rawat_intensif' => $rawat_intensif,
                'obat' => $obat,
                'obat_kronis' => $obat_kronis,
                'obat_kemoterapi' => $obat_kemoterapi,
                'alkes' => $alkes,
                'bmhp' => $bmhp,
                'sewa_alat' => $sewa_alat
            ],
            'pemulasaraan_jenazah' => $pemulasaraan_jenazah,
            'kantong_jenazah' => $kantong_jenazah,
            'peti_jenazah' => $peti_jenazah,
            'plastik_erat' => $plastik_erat,
            'desinfektan_jenazah' => $desinfektan_jenazah,
            'mobil_jenazah' => $mobil_jenazah,
            'desinfektan_mobil_jenazah' => $desinfektan_mobil_jenazah,
            'covid19_status_cd' => $covid19_status_cd,
            'nomor_kartu_t' => $nomor_kartu_t,
            'episodes' => $episodes,
            'covid19_cc_ind' => $covid19_cc_ind,
            'covid19_rs_darurat_ind' => $covid19_rs_darurat_ind,
            'covid19_co_insidense_ind' => $covid19_co_insidense_ind,
            'terapi_konvalesen' => $terapi_konvalesen,
            'akses_naat' => $akses_naat,
            'isoman_ind' => $isoman_ind,
            'bayi_lahir_status_cd' => 1,
            'dializer_single_use' => $dializer_single_use,
            'kantong_darah' => intval($kantong_darah),
            'apgar' => [
                'menit_1' => [
                    'appearance' => intval($appearance_1),
                    'pulse' => intval($pulse_1),
                    'grimace' => intval($grimace_1),
                    'activity' => intval($activity_1),
                    'respiration' => intval($respiration_1)
                ],
                'menit_5' => [
                    'appearance' => intval($appearance_5),
                    'pulse' => intval($pulse_5),
                    'grimace' => intval($grimace_5),
                    'activity' => intval($activity_5),
                    'respiration' => intval($respiration_5)
                ]
            ],
            'persalinan' => [
                'usia_kehamilan' => $usia_kehamilan,
                'gravida' => 1,
                'partus' => 1,
                'abortus' => 0,
                'onset_kontraksi' => $onset_kontraksi,
                'delivery' => [
                    [
                        'delivery_sequence' => "1",
                        'delivery_method' => $delivery_method,
                        'delivery_dttm' => $delivery_dttm,
                        'letak_janin' => $letak_janin,
                        'kondisi' => $kondisi,
                        'use_manual' => $use_manual,
                        'use_forcep' => $use_forcep,
                        'use_vacuum' => $use_vacuum,
                        'shk_spesimen_ambil' => "tidak",
                        'shk_lokasi' => "",
                        'shk_alasan' => "tidak-dapat",
                        'shk_spesimen_dttm' => ""
                    ]
                ]
            ],
            'tarif_poli_eks' => $tarif_poli_eks,
            'nama_dokter' => $nama_dokter,
            'kode_tarif' => $kode_tarif,
            'payor_id' => $payor_id,
            'payor_cd' => $payor_cd,
            'cob_cd' => $cob_cd,
            'coder_nik' => "123123123123"
        ]
    ];

    $msg = $this->Request(json_encode($request));

    return [
        'ok' => ($msg['metadata']['message'] ?? '') === 'Ok',
        'response' => $msg
    ];
}


  private function SetKlaimRanap(
    $nomor_sep, $nomor_kartu, $tgl_masuk, $tgl_pulang, $cara_masuk, $jenis_rawat, $kelas_rawat, $adl_sub_acute,
    $adl_chronic, $icu_indikator, $icu_los, $ventilator_hour, $use_ind, $start_dttm, $stop_dttm, $upgrade_class_ind, $upgrade_class_class,
    $upgrade_class_los, $upgrade_class_payor, $add_payment_pct, $birth_weight, $discharge_status, $diagnosa, $procedure,
    $tarif_poli_eks, $nama_dokter, $kode_tarif, $payor_id, $payor_cd, $cob_cd, $coder_nik,
    $prosedur_non_bedah, $prosedur_bedah, $konsultasi, $tenaga_ahli, $keperawatan, $penunjang,
    $radiologi, $laboratorium, $pelayanan_darah, $rehabilitasi, $kamar, $rawat_intensif, $obat,
    $obat_kronis, $obat_kemoterapi, $alkes, $bmhp, $sewa_alat,
    $pemulasaraan_jenazah, $kantong_jenazah, $peti_jenazah, $plastik_erat, $desinfektan_jenazah, $mobil_jenazah, $desinfektan_mobil_jenazah,
    $covid19_status_cd, $nomor_kartu_t, $episodes, $covid19_cc_ind, $covid19_rs_darurat_ind, $covid19_co_insidense_ind,
    $terapi_konvalesen, $akses_naat, $isoman_ind, $sistole, $diastole, $dializer_single_use, $kantong_darah, $usia_kehamilan, $onset_kontraksi, $delivery_method, $delivery_dttm, $letak_janin, $kondisi, $use_manual, $use_forcep, $use_vacuum,
    $appearance_1, $pulse_1, $grimace_1, $activity_1, $respiration_1, $appearance_5, $pulse_5, $grimace_5, $activity_5, $respiration_5, $no_sitb
) {
    $request = [
        'metadata' => [
            'method' => 'set_claim_data',
            'nomor_sep' => $nomor_sep
        ],
        'data' => [
            'nomor_sep' => $nomor_sep,
            'nomor_kartu' => $nomor_kartu,
            'tgl_masuk' => $tgl_masuk,
            'tgl_pulang' => $tgl_pulang,
            'cara_masuk' => $cara_masuk,
            'jenis_rawat' => $jenis_rawat,
            'kelas_rawat' => $kelas_rawat,
            'adl_sub_acute' => $adl_sub_acute,
            'adl_chronic' => $adl_chronic,
            'icu_indikator' => $icu_indikator,
            'icu_los' => $icu_los,
            'ventilator_hour' => $ventilator_hour,
            'ventilator' => [
                'use_ind' => $use_ind,
                'start_dttm' => $start_dttm,
                'stop_dttm' => $stop_dttm
            ],
            'upgrade_class_ind' => $upgrade_class_ind,
            'upgrade_class_class' => $upgrade_class_class,
            'upgrade_class_los' => $upgrade_class_los,
            'upgrade_class_payor' => $upgrade_class_payor,
            'add_payment_pct' => $add_payment_pct,
            'birth_weight' => $birth_weight,
            'sistole' => intval($sistole),
            'diastole' => intval($diastole),
            'discharge_status' => $discharge_status,
            'tarif_rs' => [
                'prosedur_non_bedah' => $prosedur_non_bedah,
                'prosedur_bedah' => $prosedur_bedah,
                'konsultasi' => $konsultasi,
                'tenaga_ahli' => $tenaga_ahli,
                'keperawatan' => $keperawatan,
                'penunjang' => $penunjang,
                'radiologi' => $radiologi,
                'laboratorium' => $laboratorium,
                'pelayanan_darah' => $pelayanan_darah,
                'rehabilitasi' => $rehabilitasi,
                'kamar' => $kamar,
                'rawat_intensif' => $rawat_intensif,
                'obat' => $obat,
                'obat_kronis' => $obat_kronis,
                'obat_kemoterapi' => $obat_kemoterapi,
                'alkes' => $alkes,
                'bmhp' => $bmhp,
                'sewa_alat' => $sewa_alat
            ],
            'pemulasaraan_jenazah' => $pemulasaraan_jenazah,
            'kantong_jenazah' => $kantong_jenazah,
            'peti_jenazah' => $peti_jenazah,
            'plastik_erat' => $plastik_erat,
            'desinfektan_jenazah' => $desinfektan_jenazah,
            'mobil_jenazah' => $mobil_jenazah,
            'desinfektan_mobil_jenazah' => $desinfektan_mobil_jenazah,
            'covid19_status_cd' => $covid19_status_cd,
            'nomor_kartu_t' => $nomor_kartu_t,
            'episodes' => $episodes,
            'covid19_cc_ind' => $covid19_cc_ind,
            'covid19_rs_darurat_ind' => $covid19_rs_darurat_ind,
            'covid19_co_insidense_ind' => $covid19_co_insidense_ind,
            'terapi_konvalesen' => $terapi_konvalesen,
            'akses_naat' => $akses_naat,
            'isoman_ind' => $isoman_ind,
            'bayi_lahir_status_cd' => 1,
            'dializer_single_use' => $dializer_single_use,
            'kantong_darah' => intval($kantong_darah),
            'apgar' => [
                'menit_1' => [
                    'appearance' => intval($appearance_1),
                    'pulse' => intval($pulse_1),
                    'grimace' => intval($grimace_1),
                    'activity' => intval($activity_1),
                    'respiration' => intval($respiration_1)
                ],
                'menit_5' => [
                    'appearance' => intval($appearance_5),
                    'pulse' => intval($pulse_5),
                    'grimace' => intval($grimace_5),
                    'activity' => intval($activity_5),
                    'respiration' => intval($respiration_5)
                ]
            ],
            'persalinan' => [
                'usia_kehamilan' => $usia_kehamilan,
                'gravida' => 1,
                'partus' => 1,
                'abortus' => 0,
                'onset_kontraksi' => $onset_kontraksi,
                'delivery' => [
                    [
                        'delivery_sequence' => "1",
                        'delivery_method' => $delivery_method,
                        'delivery_dttm' => $delivery_dttm,
                        'letak_janin' => $letak_janin,
                        'kondisi' => $kondisi,
                        'use_manual' => $use_manual,
                        'use_forcep' => $use_forcep,
                        'use_vacuum' => $use_vacuum,
                        'shk_spesimen_ambil' => "tidak",
                        'shk_lokasi' => "",
                        'shk_alasan' => "tidak-dapat",
                        'shk_spesimen_dttm' => ""
                    ]
                ]
            ],
            'tarif_poli_eks' => $tarif_poli_eks,
            'nama_dokter' => $nama_dokter,
            'kode_tarif' => $kode_tarif,
            'payor_id' => $payor_id,
            'payor_cd' => $payor_cd,
            'cob_cd' => $cob_cd,
            'coder_nik' => "123123123123"
        ]
    ];

    $msg = $this->Request(json_encode($request));

    return [
        'ok' => ($msg['metadata']['message'] ?? '') === 'Ok',
        'response' => $msg
    ];
}


  private function GroupingStage($nomor_sep, $diagnosa, $procedure)
{
    $result = [];

    // 1. Set Diagnosa
    $msgDx = $this->Request(json_encode([
        'metadata' => ['method' => 'inacbg_diagnosa_set', 'nomor_sep' => $nomor_sep],
        'data' => ['diagnosa' => $diagnosa]
    ]));
    $result['inacbg_diagnosa_set'] = $msgDx;

    if ($msgDx !== null && ($msgDx['metadata']['message'] ?? '') !== 'Ok') {
        return ['ok' => false, 'error_at' => 'inacbg_diagnosa_set', 'response' => $msgDx];
    }

    // 2. Set Procedure
    $msgPx = $this->Request(json_encode([
        'metadata' => ['method' => 'inacbg_procedure_set', 'nomor_sep' => $nomor_sep],
        'data' => ['procedure' => $procedure]
    ]));
    $result['inacbg_procedure_set'] = $msgPx;

    // Jika response null, atau 400 dengan error E2070 (kosong), tetap lanjut
    if ($msgPx !== null) {
        $code = $msgPx['metadata']['code'] ?? 0;
        $error_no = $msgPx['metadata']['error_no'] ?? '';
        $msg = $msgPx['metadata']['message'] ?? '';
        if (!($code == 400 && $error_no === 'E2070' && str_contains($msg, 'parameter data.procedure kosong')) &&
            ($msgPx['metadata']['message'] ?? '') !== 'Ok') {
            return ['ok' => false, 'error_at' => 'inacbg_procedure_set', 'response' => $msgPx];
        }
    }

    // 3. Grouper Stage 1
    $msgG1 = $this->Request(json_encode([
        'metadata' => ['method' => 'grouper', 'stage' => '1', 'grouper' => 'inacbg'],
        'data' => ['nomor_sep' => $nomor_sep]
    ]));
    $result['grouper_inacbg_s1'] = $msgG1;

    if (($msgG1['metadata']['message'] ?? '') !== 'Ok') {
        return ['ok' => false, 'error_at' => 'grouper_inacbg_stage_1', 'response' => $msgG1];
    }

    // 4. Grouper Stage 2 (jika ada special CMG)
    $topup = $msgG1['special_cmg_option'] ?? [];
    if (!empty($topup)) {
        $tempGrouper = implode('#', array_column($topup, 'code'));
        $msgG2 = $this->Request(json_encode([
            'metadata' => ['method' => 'grouper', 'stage' => '2', 'grouper' => 'inacbg'],
            'data' => ['nomor_sep' => $nomor_sep, 'special_cmg' => $tempGrouper]
        ]));
        $result['grouper_inacbg_s2'] = $msgG2;

        if (($msgG2['metadata']['message'] ?? '') !== 'Ok') {
            return ['ok' => false, 'error_at' => 'grouper_inacbg_stage_2', 'response' => $msgG2];
        }
    }

    // 5. Final Grouper INACBG
    $finalStage = $this->GroupingStageFinal($nomor_sep);
    $result['inacbg_grouper_final'] = $finalStage;

    if (!$finalStage['ok']) {
        return ['ok' => false, 'error_at' => 'final_inacbg', 'response' => $finalStage];
    }

    return ['ok' => true, 'response' => $result];
}


private function GroupingStageFinal($nomor_sep) {
    $requestFinal = [
        'metadata' => [
            'method' => 'inacbg_grouper_final'
        ],
        'data' => [
            'nomor_sep' => $nomor_sep
        ]
    ];
    $msgFinal = $this->Request(json_encode($requestFinal));

    if (($msgFinal['metadata']['message'] ?? '') === "Ok") {
        $finalKlaim = $this->FinalisasiKlaim($nomor_sep);

        if (!$finalKlaim['ok']) {
            return [
                'ok' => false,
                'error_at' => 'final_klaim',
                'response' => $msgFinal,
                'final_klaim' => $finalKlaim
            ];
        }

        return [
            'ok' => true,
            'response' => $msgFinal,
            'final_klaim' => $finalKlaim
        ];
    }

    return [
        'ok' => false,
        'response' => $msgFinal
    ];
}

private function FinalisasiKlaim($nomor_sep) {
    $request = [
        'metadata' => [
            'method' => 'claim_final'
        ],
        'data' => [
            'nomor_sep' => $nomor_sep,
            'coder_nik' => '123123123123'
        ]
    ];
    $msg = $this->Request(json_encode($request));

    return [
        'ok' => ($msg['metadata']['message'] ?? '') === "Ok",
        'response' => $msg
    ];
}


  private function KirimKlaimIndividualKeDC($nomor_sep, $forceResend = false)
    {
        // Idempotent: jangan kirim ulang jika sebelumnya sudah sukses terkirim.
        $alreadySent = $this->db('inacbg_data_terkirim')
            ->where('no_sep', $nomor_sep)
            ->oneArray();

        if (!$forceResend && !empty($alreadySent)) {
            return [
                'ok' => true,
                'skipped' => true,
                'response' => [
                    'metadata' => [
                        'code' => 200,
                        'message' => 'Klaim sudah pernah dikirim ke Data Center'
                    ],
                    'local_save' => 'exists'
                ]
            ];
        }

        $request = json_encode([
            'metadata' => [
                'method' => 'send_claim_individual'
            ],
            'data' => [
                'nomor_sep' => $nomor_sep
            ]
        ]);
    
        $msg = $this->Request($request);
        $response = $msg;
    
        // pastikan struktur response valid
        if (
            is_array($msg) &&
            isset($msg['metadata']['code']) &&
            (int)$msg['metadata']['code'] === 200
        ) {
            // cek apakah no_sep sudah ada
            $exists = $this->db('inacbg_data_terkirim')
                ->where('no_sep', $nomor_sep)
                ->oneArray();
    
            // hanya insert jika belum ada
            if (empty($exists)) {
                try {
                    $this->db('inacbg_data_terkirim')->save([
                        'no_sep'  => $nomor_sep,
                        'nik' => 'Terkirim DC'
                    ]);
                    $response['local_save'] = 'inserted';
                } catch (\Throwable $e) {
                    // DB error tidak menggagalkan response DC
                    $response['local_save']  = 'failed';
                    $response['local_error'] = $e->getMessage();
                }
            } else {
                // sudah ada → biarkan
                $response['local_save'] = 'exists';
            }
        }
    
        if (!is_array($response)) {
            $response = [
                'metadata' => [
                    'code' => 500,
                    'message' => 'Respons Data Center tidak valid'
                ]
            ];
        }

        $success = isset($response['metadata']['code'])
            && (int)$response['metadata']['code'] === 200;

        return [
            'ok' => $success,
            'skipped' => false,
            'response' => $response
        ];
    }

  
  private function GroupingIDRG($nomor_sep, $diagnosa, $procedure)
{
    $result = [];

    // 1. Set Diagnosa
    $msgDiag = $this->Request(json_encode([
        'metadata' => ['method' => 'idrg_diagnosa_set', 'nomor_sep' => $nomor_sep],
        'data' => ['diagnosa' => $diagnosa]
    ]));
    $result['idrg_diagnosa_set'] = $msgDiag;

    if (($msgDiag['metadata']['message'] ?? '') !== "Ok") {
        return ['ok' => false, 'error_at' => 'idrg_diagnosa_set', 'response' => $msgDiag];
    }

    // 2. Set Procedure (boleh null)
    $msgProc = $this->Request(json_encode([
        'metadata' => ['method' => 'idrg_procedure_set', 'nomor_sep' => $nomor_sep],
        'data' => ['procedure' => $procedure]
    ]));
    $result['idrg_procedure_set'] = $msgProc;

    // Jangan stop jika response null
    if ($msgProc !== null && ($msgProc['metadata']['message'] ?? '') !== 'Ok') {
        return ['ok' => false, 'error_at' => 'idrg_procedure_set', 'response' => $msgProc];
    }

    // 3. Grouper Stage 1
    $msgGrouper = $this->Request(json_encode([
        'metadata' => ['method' => 'grouper', 'stage' => '1', 'grouper' => 'idrg'],
        'data' => ['nomor_sep' => $nomor_sep]
    ]));
    $result['grouper_idrg'] = $msgGrouper;

    if (($msgGrouper['metadata']['message'] ?? '') !== "Ok") {
        return ['ok' => false, 'error_at' => 'grouper_idrg', 'response' => $msgGrouper];
    }

    // 4. Final IDRG
    $final = $this->FinalIDRG($nomor_sep, $diagnosa, $procedure);
    $result['idrg_grouper_final'] = $final;

    if (!$final['ok']) {
        return ['ok' => false, 'error_at' => 'final_idrg', 'response' => $final];
    }

    return ['ok' => true, 'response' => $result];
}



private function FinalIDRG($nomor_sep, $diagnosa, $procedure) {
    $requestFinal = [
        'metadata' => [
            'method' => 'idrg_grouper_final'
        ],
        'data' => [
            'nomor_sep' => $nomor_sep
        ]
    ];
    $msgFinal = $this->Request(json_encode($requestFinal));

    // Jika sukses, bisa lanjut ke GroupingStage di luar
    if (($msgFinal['metadata']['message'] ?? '') === "Ok") {
        return [
            'ok' => true,
            'response' => $msgFinal
        ];
    }

    return [
        'ok' => false,
        'response' => $msgFinal
    ];
}


  public function anySavePrioritas()
  {
    $this->db('diagnosa_pasien')
      ->where('no_rawat', $_REQUEST['no_rawat'])
      ->where('kd_penyakit', $_REQUEST['kd_penyakit'])
      ->where('status', $_REQUEST['status'])
      ->save([
        'prioritas' => $_REQUEST['prioritas']
      ]);

    exit();
  }

  public function anySaveProsedur()
  {
    $this->db('prosedur_pasien')
      ->where('no_rawat', $_REQUEST['no_rawat'])
      ->where('kode', $_REQUEST['kode'])
      ->where('status', $_REQUEST['status'])
      ->save([
        'prioritas' => $_REQUEST['prioritas']
      ]);

    exit();
  }

  public function getJavascript()
  {
    header('Content-type: text/javascript');
    echo $this->draw(MODULES . '/vedika/js/admin/scripts.js');
    exit();
  }

  public function getCss()
  {
    header('Content-type: text/css');
    echo $this->draw(MODULES . '/vedika/css/admin/styles.css');
    exit();
  }

  private function _addHeaderFiles()
  {
    // CSS
    $this->core->addCSS(url('assets/css/dataTables.bootstrap.min.css'));
    $this->core->addCSS(url('assets/css/bootstrap-datetimepicker.css'));

    // JS
    $this->core->addJS(url('assets/jscripts/jquery.dataTables.min.js'), 'footer');
    $this->core->addJS(url('assets/jscripts/dataTables.bootstrap.min.js'), 'footer');
    $this->core->addJS(url('assets/jscripts/moment-with-locales.js'));
    $this->core->addJS(url('assets/jscripts/bootstrap-datetimepicker.js'));

    // MODULE SCRIPTS
    $this->core->addCSS(url([ADMIN, 'vedika', 'css']));
    $this->core->addJS(url([ADMIN, 'vedika', 'javascript']), 'footer');
  }
  
  public function anyRincian($no_rawat)
    {
      $rows_rawat_jl_dr = $this->db('rawat_jl_dr')->where('no_rawat', $no_rawat)->toArray();
      $rows_rawat_jl_pr = $this->db('rawat_jl_pr')->where('no_rawat', $no_rawat)->toArray();
      $rows_rawat_jl_drpr = $this->db('rawat_jl_drpr')->where('no_rawat', $no_rawat)->toArray();

      $jumlah_total = 0;
      $rawat_jl_dr = [];
      $rawat_jl_pr = [];
      $rawat_jl_drpr = [];
      $i = 1;

      if($rows_rawat_jl_dr) {
        foreach ($rows_rawat_jl_dr as $row) {
          $jns_perawatan = $this->db('jns_perawatan')->where('kd_jenis_prw', $row['kd_jenis_prw'])->oneArray();
          $row['nm_perawatan'] = $jns_perawatan['nm_perawatan'];
          $jumlah_total = $jumlah_total + $row['biaya_rawat'];
          $row['provider'] = 'rawat_jl_dr';
          $rawat_jl_dr[] = $row;
        }
      }

      if($rows_rawat_jl_pr) {
        foreach ($rows_rawat_jl_pr as $row) {
          $jns_perawatan = $this->db('jns_perawatan')->where('kd_jenis_prw', $row['kd_jenis_prw'])->oneArray();
          $row['nm_perawatan'] = $jns_perawatan['nm_perawatan'];
          $jumlah_total = $jumlah_total + $row['biaya_rawat'];
          $row['provider'] = 'rawat_jl_pr';
          $rawat_jl_pr[] = $row;
        }
      }

      if($rows_rawat_jl_drpr) {
        foreach ($rows_rawat_jl_drpr as $row) {
          $jns_perawatan = $this->db('jns_perawatan')->where('kd_jenis_prw', $row['kd_jenis_prw'])->oneArray();
          $row['nm_perawatan'] = $jns_perawatan['nm_perawatan'];
          $jumlah_total = $jumlah_total + $row['biaya_rawat'];
          $row['provider'] = 'rawat_jl_drpr';
          $rawat_jl_drpr[] = $row;
        }
      }

      $rows = $this->db('resep_obat')
        ->join('dokter', 'dokter.kd_dokter=resep_obat.kd_dokter')
        ->join('resep_dokter', 'resep_dokter.no_resep=resep_obat.no_resep')
        ->where('no_rawat', $no_rawat)
        ->group('resep_dokter.no_resep')
        ->toArray();
      $resep = [];
      $jumlah_total_resep = 0;
      foreach ($rows as $row) {
        $row['nomor'] = $i++;
        $row['resep_dokter'] = $this->db('resep_dokter')->join('databarang', 'databarang.kode_brng=resep_dokter.kode_brng')->where('no_resep', $row['no_resep'])->toArray();
        foreach ($row['resep_dokter'] as $value) {
          $value['ralan'] = $value['jml'] * $value['ralan'];
          $jumlah_total_resep += floatval($value['ralan']);
        }
        $resep[] = $row;
      }

      $rows_racikan = $this->db('resep_obat')
        ->join('dokter', 'dokter.kd_dokter=resep_obat.kd_dokter')
        ->join('resep_dokter_racikan', 'resep_dokter_racikan.no_resep=resep_obat.no_resep')
        ->where('no_rawat', $no_rawat)
        ->group('resep_dokter_racikan.no_resep')
        ->toArray();
      $resep_racikan = [];
      $jumlah_total_resep_racikan = 0;
      foreach ($rows_racikan as $row) {
        $row['nomor'] = $i++;
        $row['resep_dokter_racikan_detail'] = $this->db('resep_dokter_racikan_detail')->join('databarang', 'databarang.kode_brng=resep_dokter_racikan_detail.kode_brng')->where('no_resep', $row['no_resep'])->toArray();
        foreach ($row['resep_dokter_racikan_detail'] as $value) {
          $value['ralan'] = $value['jml'] * $value['ralan'];
          $jumlah_total_resep_racikan += floatval($value['ralan']);
        }
        $resep_racikan[] = $row;
      }

      $rows_laboratorium = $this->db('permintaan_lab')
        ->join('dokter', 'dokter.kd_dokter=permintaan_lab.dokter_perujuk')
        ->where('no_rawat', $no_rawat)
        ->where('permintaan_lab.status', 'ralan')
        ->toArray();
      $laboratorium = [];
      foreach ($rows_laboratorium as $row) {
        $rows2 = $this->db('permintaan_pemeriksaan_lab')
          ->join('jns_perawatan_lab', 'jns_perawatan_lab.kd_jenis_prw=permintaan_pemeriksaan_lab.kd_jenis_prw')
          //->join('permintaan_detail_permintaan_lab', 'permintaan_detail_permintaan_lab.noorder=permintaan_pemeriksaan_lab.noorder')
          ->where('permintaan_pemeriksaan_lab.noorder', $row['noorder'])
          ->toArray();
          $row['permintaan_pemeriksaan_lab'] = [];
          foreach ($rows2 as $row2) {
            $row2['noorder'] = $row2['noorder'];
            $row2['kd_jenis_prw'] = $row2['kd_jenis_prw'];
            $row2['stts_bayar'] = $row2['stts_bayar'];
            $row2['nm_perawatan'] = $row2['nm_perawatan'];
            $row2['kd_pj'] = $row2['kd_pj'];
            $row2['status'] = $row2['status'];
            $row2['kelas'] = $row2['kelas'];
            $row2['kategori'] = $row2['kategori'];
            $rows3 = $this->db('permintaan_detail_permintaan_lab')->where('noorder', $row2['noorder'])->where('kd_jenis_prw', $row2['kd_jenis_prw'])->toArray();
            $row2['permintaan_detail_permintaan_lab'] = [];
            foreach ($rows3 as $row3) {
              $row3['template_laboratorium'] = $this->db('template_laboratorium')->where('kd_jenis_prw', $row3['kd_jenis_prw'])->where('id_template', $row3['id_template'])->oneArray();
              $row2['permintaan_detail_permintaan_lab'][] = $row3;
            }
            $row['permintaan_pemeriksaan_lab'][] = $row2;
          }
        $laboratorium[] = $row;
      }

      $rows_radiologi = $this->db('permintaan_radiologi')
        ->join('permintaan_pemeriksaan_radiologi', 'permintaan_pemeriksaan_radiologi.noorder=permintaan_radiologi.noorder')
        ->where('no_rawat', $no_rawat)
        ->where('permintaan_radiologi.status', 'ralan')
        ->toArray();
      $jumlah_total_rad = 0;
      $radiologi = [];

      if($rows_radiologi) {
        foreach ($rows_radiologi as $row) {
          $jns_perawatan = $this->db('jns_perawatan_radiologi')->where('kd_jenis_prw', $row['kd_jenis_prw'])->oneArray();
          $row['nm_perawatan'] = $jns_perawatan['nm_perawatan'];
          $row['kelas'] = $jns_perawatan['kelas'];
          $row['total_byr'] = $jns_perawatan['total_byr'];
          $jumlah_total_rad += $jns_perawatan['total_byr'];
          $radiologi[] = $row;
        }
      }

      $reg_periksa = $this->db('reg_periksa')->where('no_rawat', $no_rawat)->oneArray();
      $rows_data_resep = $this->db('resep_obat')
      ->join('reg_periksa', 'reg_periksa.no_rawat=resep_obat.no_rawat')
      ->where('resep_obat.kd_dokter', $this->core->getUserInfo('username', null, true))
      ->where('reg_periksa.no_rkm_medis', $reg_periksa['no_rkm_medis'])
      ->toArray();

      $data_resep = [];
      foreach ($rows_data_resep as $row) {
        $row['resep_dokter'] = $this->db('resep_dokter')
          ->join('databarang', 'databarang.kode_brng=resep_dokter.kode_brng')
          ->where('no_resep', $row['no_resep'])
          ->toArray();
        $data_resep[] = $row;
      }

      echo $this->draw('rincian.html', [
        'rawat_jl_dr' => $rawat_jl_dr,
        'rawat_jl_pr' => $rawat_jl_pr,
        'rawat_jl_drpr' => $rawat_jl_drpr,
        'resep' => $resep,
        'resep_racikan' => $resep_racikan,
        'data_resep' => $data_resep,
        'laboratorium' => $laboratorium,
        'radiologi' => $radiologi,
        'jumlah_total' => $jumlah_total,
        'jumlah_total_resep' => $jumlah_total_resep,
        'jumlah_total_resep_racikan' => $jumlah_total_resep_racikan,
        //'jumlah_total_lab' => $jumlah_total_lab,
        'jumlah_total_rad' => $jumlah_total_rad,
        'no_rawat' => $no_rawat
      ]);
      exit();
    }
    
    private function _queueGroupingAfterStatusSaved($noRawat, $nosep, $jenis, $targetStatus)
    {
        if (!in_array((string) $targetStatus, ['Lengkap', 'Pengajuan'], true)) {
            return;
        }

        $username = (string) $this->core->getUserInfo('username', null, true);
        $coderNik = (string) $this->core->getPegawaiInfo('no_ktp', $username);
        $queued = $this->_enqueueBackgroundGrouping(
            $noRawat,
            $nosep,
            $jenis,
            $targetStatus,
            $username,
            $coderNik
        );

        if (empty($queued['status'])) {
            // Jangan pernah meloloskan berkas bila validasi background bahkan
            // tidak berhasil dimasukkan ke antrean.
            $stmt = $this->db()->pdo()->prepare(
                'DELETE FROM mlite_vedika WHERE no_rawat = ? AND nosep = ? AND status = ?'
            );
            $stmt->execute([$noRawat, $nosep, $targetStatus]);
            throw new \RuntimeException(isset($queued['message'])
                ? $queued['message']
                : 'Antrean grouping INACBG tidak tersedia');
        }
    }

    private function _enqueueBackgroundGrouping(
        $noRawat,
        $nosep,
        $jenis,
        $targetStatus,
        $requestedBy,
        $coderNik
    ) {
        $noRawat = trim((string) $noRawat);
        $nosep = trim((string) $nosep);

        if ($noRawat === '' || $nosep === '') {
            return ['status' => false, 'message' => 'Nomor rawat atau SEP kosong'];
        }

        $lockName = 'vedika_grouping_enqueue_' . sha1($nosep . '|' . $noRawat);
        if (!$this->_acquirePDFQueueLock($lockName, 5)) {
            return ['status' => false, 'message' => 'Gagal memperoleh lock antrean grouping'];
        }

        try {
            $pdo = $this->db()->pdo();
            $find = $pdo->prepare(
                'SELECT id, status FROM mlite_vedika_grouping_queue
                 WHERE no_rawat = ? OR nosep = ? ORDER BY id DESC LIMIT 1'
            );
            $find->execute([$noRawat, $nosep]);
            $existing = $find->fetch(\PDO::FETCH_ASSOC);

            if ($existing && in_array($existing['status'], ['queued', 'processing'], true)) {
                return [
                    'status' => true,
                    'job_id' => (int) $existing['id'],
                    'reused' => true
                ];
            }

            if ($existing) {
                $update = $pdo->prepare(
                    "UPDATE mlite_vedika_grouping_queue
                     SET no_rawat = ?, nosep = ?, jenis = ?, target_status = ?,
                         requested_by = ?, coder_nik = ?, status = 'queued', attempts = 0,
                         last_step = NULL, message = NULL, created_at = NOW(),
                         started_at = NULL, finished_at = NULL, heartbeat_at = NULL
                     WHERE id = ?"
                );
                $update->execute([
                    $noRawat, $nosep, $jenis, $targetStatus,
                    substr((string) $requestedBy, 0, 50),
                    substr((string) $coderNik, 0, 50),
                    $existing['id']
                ]);
                return ['status' => true, 'job_id' => (int) $existing['id'], 'reused' => true];
            }

            $insert = $pdo->prepare(
                "INSERT INTO mlite_vedika_grouping_queue
                 (no_rawat, nosep, jenis, target_status, requested_by, coder_nik,
                  status, attempts, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, 'queued', 0, NOW())"
            );
            $insert->execute([
                $noRawat, $nosep, $jenis, $targetStatus,
                substr((string) $requestedBy, 0, 50),
                substr((string) $coderNik, 0, 50)
            ]);

            return ['status' => true, 'job_id' => (int) $pdo->lastInsertId(), 'reused' => false];
        } catch (\Throwable $e) {
            return ['status' => false, 'message' => 'Antrean grouping gagal: ' . $e->getMessage()];
        } finally {
            $this->_releasePDFQueueLock($lockName);
        }
    }

    private function _getLatestGroupingFailure($noRawat, $nosep)
    {
        try {
            $stmt = $this->db()->pdo()->prepare(
                "SELECT last_step, message, finished_at
                 FROM mlite_vedika_grouping_queue
                 WHERE status = 'failed' AND (no_rawat = ? OR (? <> '' AND nosep = ?))
                 ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute([$noRawat, $nosep, $nosep]);
            $failure = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$failure) {
                return null;
            }

            $message = (string) $failure['message'];
            if (stripos($message, 'diagnosa kosong') !== false || stripos($message, 'diagnosis kosong') !== false) {
                $specificMessage = $this->_onlyIMDiagnosisMessageForEpisode($noRawat);
                if ($specificMessage !== null) {
                    $message = $specificMessage;
                }
            }

            return [
                'last_step' => htmlspecialchars((string) $failure['last_step'], ENT_QUOTES, 'UTF-8'),
                'message' => htmlspecialchars($message, ENT_QUOTES, 'UTF-8'),
                'finished_at' => htmlspecialchars((string) $failure['finished_at'], ENT_QUOTES, 'UTF-8')
            ];
        } catch (\Throwable $e) {
            // Halaman daftar tetap dapat dibuka saat tabel antrean belum dipasang.
            return null;
        }
    }

    public function processGroupingQueueOnce($workerId)
    {
        $pdo = $this->db()->pdo();
        $workerId = substr((string) $workerId, 0, 120);

        // Pulihkan pekerjaan yang ditinggalkan worker mati lebih dari 15 menit.
        $stale = $pdo->prepare(
            "UPDATE mlite_vedika_grouping_queue
             SET status = CASE WHEN attempts >= 3 THEN 'failed' ELSE 'queued' END,
                 last_step = CASE WHEN attempts >= 3 THEN 'worker' ELSE last_step END,
                 message = CASE WHEN attempts >= 3
                     THEN 'Worker grouping terhenti tiga kali'
                     ELSE 'Mengulang pekerjaan setelah worker terhenti' END,
                 started_at = CASE WHEN attempts >= 3 THEN started_at ELSE NULL END,
                 finished_at = CASE WHEN attempts >= 3 THEN NOW() ELSE NULL END,
                 heartbeat_at = NOW()
             WHERE status = 'processing'
               AND COALESCE(heartbeat_at, started_at) < DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
        );
        $stale->execute();

        $rollbackStale = $pdo->prepare(
            "DELETE v FROM mlite_vedika v
             INNER JOIN mlite_vedika_grouping_queue q
                ON q.no_rawat = v.no_rawat AND q.nosep = v.nosep
             WHERE q.status = 'failed'
               AND q.message = 'Worker grouping terhenti tiga kali'
               AND v.status = q.target_status"
        );
        $rollbackStale->execute();

        $dequeueLock = 'vedika_grouping_dequeue';
        if (!$this->_acquirePDFQueueLock($dequeueLock, 2)) {
            return ['status' => true, 'idle' => true, 'message' => 'Antrean sedang diperiksa worker lain'];
        }

        $job = null;
        try {
            $select = $pdo->query(
                "SELECT * FROM mlite_vedika_grouping_queue
                 WHERE status = 'queued' AND attempts < 3
                 ORDER BY created_at ASC, id ASC LIMIT 1"
            );
            $job = $select->fetch(\PDO::FETCH_ASSOC);
            if ($job) {
                $claim = $pdo->prepare(
                    "UPDATE mlite_vedika_grouping_queue
                     SET status = 'processing', attempts = attempts + 1,
                         message = ?, started_at = NOW(), heartbeat_at = NOW()
                     WHERE id = ? AND status = 'queued'"
                );
                $claim->execute(['Diproses oleh ' . $workerId, $job['id']]);
                if ($claim->rowCount() !== 1) {
                    $job = null;
                } else {
                    $job['attempts'] = (int) $job['attempts'] + 1;
                }
            }
        } finally {
            $this->_releasePDFQueueLock($dequeueLock);
        }

        if (!$job) {
            return ['status' => true, 'idle' => true, 'message' => 'Antrean grouping kosong'];
        }

        $patientLock = 'vedika_grouping_patient_' . sha1($job['nosep'] . '|' . $job['no_rawat']);
        if (!$this->_acquirePDFQueueLock($patientLock, 2)) {
            $retry = $pdo->prepare(
                "UPDATE mlite_vedika_grouping_queue
                 SET status = 'queued', message = 'Menunggu proses pasien yang sama',
                     started_at = NULL, heartbeat_at = NOW() WHERE id = ?"
            );
            $retry->execute([$job['id']]);
            return ['status' => true, 'idle' => false, 'job_id' => (int) $job['id'], 'message' => 'Ditunda'];
        }

        try {
            $payload = $this->_buildBackgroundGroupingPayload($job['no_rawat'], $job['nosep']);
            $payload['coder_nik'] = (string) $job['coder_nik'];

            $oldPost = $_POST;
            $_POST = $payload;
            $this->captureJsonResponse = true;
            try {
                $result = $this->postProsesKlaimFull();
            } finally {
                $this->captureJsonResponse = false;
                $_POST = $oldPost;
            }

            if (!is_array($result) || empty($result['ok'])) {
                $this->_failBackgroundGrouping($job, $this->_groupingFailureMessage($result),
                    is_array($result) && isset($result['last_step']) ? $result['last_step'] : 'response');
                return [
                    'status' => false,
                    'idle' => false,
                    'job_id' => (int) $job['id'],
                    'no_rawat' => $job['no_rawat'],
                    'message' => $this->_groupingFailureMessage($result)
                ];
            }

            $done = $pdo->prepare(
                "UPDATE mlite_vedika_grouping_queue
                 SET status = 'done', last_step = ?, message = 'Grouping dan Kirim DC berhasil',
                     finished_at = NOW(), heartbeat_at = NOW() WHERE id = ?"
            );
            $done->execute([isset($result['last_step']) ? $result['last_step'] : 'selesai', $job['id']]);

            return [
                'status' => true,
                'idle' => false,
                'job_id' => (int) $job['id'],
                'no_rawat' => $job['no_rawat'],
                'message' => 'Grouping background berhasil'
            ];
        } catch (\Throwable $e) {
            if ((int) $job['attempts'] >= 3) {
                $this->_failBackgroundGrouping($job, 'Worker gagal: ' . $e->getMessage(), 'worker');
            } else {
                $retry = $pdo->prepare(
                    "UPDATE mlite_vedika_grouping_queue
                     SET status = 'queued', last_step = 'worker', message = ?,
                         started_at = NULL, heartbeat_at = NOW() WHERE id = ?"
                );
                $retry->execute([substr('Akan dicoba lagi: ' . $e->getMessage(), 0, 65000), $job['id']]);
            }

            return [
                'status' => false,
                'idle' => false,
                'job_id' => (int) $job['id'],
                'no_rawat' => $job['no_rawat'],
                'message' => $e->getMessage()
            ];
        } finally {
            $this->_releasePDFQueueLock($patientLock);
        }
    }

    private function _buildBackgroundGroupingPayload($noRawat, $nosep)
    {
        $this->captureInacbgsHtml = true;
        try {
            $html = $this->getBridgingInacbgs($this->convertNorawat($noRawat));
        } finally {
            $this->captureInacbgsHtml = false;
        }

        if (!is_string($html) || trim($html) === '') {
            throw new \RuntimeException('Form INACBG tidak berhasil dibentuk');
        }
        if (!class_exists('DOMDocument')) {
            throw new \RuntimeException('Ekstensi PHP DOM belum aktif');
        }

        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            throw new \RuntimeException('Form INACBG tidak dapat dibaca worker');
        }

        $payload = [];
        $xpath = new \DOMXPath($dom);
        foreach ($xpath->query('//*[@name]') as $element) {
            $name = trim((string) $element->getAttribute('name'));
            if ($name === '') {
                continue;
            }

            $tag = strtolower($element->nodeName);
            if ($tag === 'select') {
                $value = '';
                $first = null;
                foreach ($element->getElementsByTagName('option') as $option) {
                    if ($first === null) {
                        $first = $option;
                    }
                    if ($option->hasAttribute('selected')) {
                        $first = $option;
                        break;
                    }
                }
                if ($first !== null) {
                    $value = $first->hasAttribute('value')
                        ? $first->getAttribute('value')
                        : $first->textContent;
                }
            } elseif ($tag === 'textarea') {
                $value = $element->textContent;
            } else {
                $value = $element->getAttribute('value');
            }

            // Sama seperti JS modal saat ini: nama ganda memakai nilai terakhir.
            $payload[$name] = (string) $value;
        }

        // Kompatibilitas dengan nama field lama pada template inacbgs.html.
        $payload['appearance_1'] = isset($payload['appearance_1'])
            ? $payload['appearance_1']
            : (isset($payload['appareance_1']) ? $payload['appareance_1'] : '0');
        $payload['appearance_5'] = isset($payload['appearance_5'])
            ? $payload['appearance_5']
            : (isset($payload['appareance_5']) ? $payload['appareance_5'] : '0');
        foreach (['mobil_jenazah', 'desinfektan_mobil_jenazah', 'upgrade_class_payor'] as $optional) {
            if (!isset($payload[$optional])) {
                $payload[$optional] = '';
            }
        }

        // Pada satu no_rawat dapat terbit lebih dari satu SEP. Job harus selalu
        // memakai SEP yang dipilih coder, bukan hasil lookup no_rawat yang ambigu.
        $payload['nosep'] = (string) $nosep;
        $sep = $this->db('bridging_sep')->where('no_sep', $nosep)->oneArray();
        if ($sep) {
            if (isset($sep['no_kartu']) && trim((string) $sep['no_kartu']) !== '') {
                $payload['nokartu'] = (string) $sep['no_kartu'];
            }
            if (isset($sep['klsrawat']) && trim((string) $sep['klsrawat']) !== '') {
                $payload['kelas_rawat'] = (string) $sep['klsrawat'];
            }
        }

        return $payload;
    }

    private function _failBackgroundGrouping(array $job, $message, $lastStep)
    {
        $pdo = $this->db()->pdo();
        $message = substr(trim((string) $message), 0, 65000);
        if ($message === '') {
            $message = 'Data koding tidak lolos grouping INACBG';
        }

        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        try {
            // Hanya tarik kembali record yang masih merupakan status yang diuji job ini.
            // Perubahan baru oleh user lain tidak ikut terhapus.
            $delete = $pdo->prepare(
                'DELETE FROM mlite_vedika
                 WHERE no_rawat = ? AND nosep = ? AND status = ?'
            );
            $delete->execute([$job['no_rawat'], $job['nosep'], $job['target_status']]);

            $failed = $pdo->prepare(
                "UPDATE mlite_vedika_grouping_queue
                 SET status = 'failed', last_step = ?, message = ?,
                     finished_at = NOW(), heartbeat_at = NOW() WHERE id = ?"
            );
            $failed->execute([substr((string) $lastStep, 0, 50), $message, $job['id']]);
            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private function _groupingFailureMessage($result)
    {
        if (!is_array($result)) {
            return 'Respons proses grouping tidak valid';
        }

        $candidates = [
            isset($result['error']['response']['metadata']['message']) ? $result['error']['response']['metadata']['message'] : null,
            isset($result['error']['metadata']['message']) ? $result['error']['metadata']['message'] : null,
            isset($result['error']['response']['message']) ? $result['error']['response']['message'] : null,
            isset($result['error']['message']) ? $result['error']['message'] : null,
            isset($result['message']) ? $result['message'] : null
        ];
        foreach ($candidates as $candidate) {
            if (is_scalar($candidate) && trim((string) $candidate) !== '') {
                return trim((string) $candidate);
            }
        }

        return 'Data koding tidak lolos pada tahap '
            . (isset($result['last_step']) ? $result['last_step'] : 'grouping');
    }

    private function _resolveCoderNik()
    {
        if (isset($_POST['coder_nik']) && trim((string) $_POST['coder_nik']) !== '') {
            return $this->validTeks(trim((string) $_POST['coder_nik']));
        }

        $username = $this->core->getUserInfo('username', null, true);
        return $this->core->getPegawaiInfo('no_ktp', $username);
    }

    private function jsonResponse($data)
    {
        if ($this->captureJsonResponse) {
            return $data;
        }

        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    private function stop($result, $step, $detail)
    {
        $result['steps'][$step] = false;
        $result['last_step'] = $step;
        $result['error'] = $detail;
    
        return $this->jsonResponse($result);
    }
    
    private function splitDiagnosaIM($diagnosa): array
    {
        // Guard: null / empty / non-string
        if (!is_string($diagnosa) || trim($diagnosa) === '') {
            return [
                'idrg'   => '',
                'inacbg' => '',
                'im_codes' => ''
            ];
        }
    
        // Pecah kode
        $codes = array_filter(
            array_map('trim', explode('#', $diagnosa))
        );
    
        if (empty($codes)) {
            return [
                'idrg'   => '',
                'inacbg' => '',
                'im_codes' => ''
            ];
        }
    
        // Ambil daftar IM dari DB
        $imCodes = array_column(
            $this->db('penyakit')
                 ->where('im', '1')
                 ->toArray(),
            'kd_penyakit'
        );
    
        $idrg   = [];
        $inacbg = [];
        $imOnly = [];
    
        foreach ($codes as $code) {
            if (in_array($code, $imCodes, true)) {
                // IM → hanya IDRG
                $idrg[] = $code;
                $imOnly[] = $code;
            } else {
                // Non IM → keduanya
                $idrg[]   = $code;
                $inacbg[] = $code;
            }
        }
    
        return [
            'idrg'   => implode('#', $idrg),
            'inacbg' => implode('#', $inacbg),
            'im_codes' => implode('#', $imOnly)
        ];
    }

    private function _onlyIMDiagnosisMessage($codes)
    {
        $list = array_values(array_filter(array_map('trim', explode('#', (string) $codes))));
        $label = count($list) ? ' (' . implode(', ', $list) . ')' : '';
        return 'Diagnosis INACBG kosong: Tambahkan minimal satu diagnosis non-IM yang sesuai dengan dokumentasi klinis, lalu set status ulang.';
    }

    private function _onlyIMDiagnosisMessageForEpisode($noRawat)
    {
        $reg = $this->db('reg_periksa')->where('no_rawat', $noRawat)->oneArray();
        if (!$reg || !isset($reg['status_lanjut'])) return null;
        $rows = $this->db('diagnosa_pasien')
            ->join('penyakit', 'penyakit.kd_penyakit = diagnosa_pasien.kd_penyakit')
            ->where('diagnosa_pasien.no_rawat', $noRawat)
            ->where('diagnosa_pasien.status', $reg['status_lanjut'])
            ->asc('diagnosa_pasien.prioritas')
            ->toArray();
        if (!$rows) return null;
        $codes = [];
        foreach ($rows as $row) {
            if (!isset($row['im']) || (string) $row['im'] !== '1') return null;
            $codes[] = $row['kd_penyakit'];
        }
        return $this->_onlyIMDiagnosisMessage(implode('#', $codes));
    }

    private function _diagnosisRowsOnlyIM(array $rows)
    {
        if (!$rows) return false;
        foreach ($rows as $row) {
            if (!isset($row['im']) || (string) $row['im'] !== '1') return false;
        }
        return true;
    }
    
    private function splitProcedureIM($procedure): array
    {
        // Guard: null / empty / non-string
        if (!is_string($procedure) || trim($procedure) === '') {
            return [
                'idrg'   => '',
                'inacbg' => ''
            ];
        }
    
        // Pecah procedure (dipisah #)
        $codes = array_filter(
            array_map('trim', explode('#', $procedure))
        );
    
        if (empty($codes)) {
            return [
                'idrg'   => '',
                'inacbg' => ''
            ];
        }
    
        // Ambil daftar procedure IM
        $imCodes = array_column(
            $this->db('icd9')
                 ->where('im', '1')
                 ->toArray(),
            'kode'
        );
    
        $idrg   = [];
        $inacbg = [];
    
        foreach ($codes as $codeWithVolume) {
            $code = $codeWithVolume;
            if (preg_match('/^(.+)\+([1-9])$/', $codeWithVolume, $match)) {
                $code = $match[1];
            }
            if (in_array($code, $imCodes, true)) {
                // IM → hanya IDRG
                $idrg[] = $codeWithVolume;
            } else {
                // Non IM → keduanya
                $idrg[]   = $codeWithVolume;
                $inacbg[] = $code;
            }
        }
    
        return [
            'idrg'   => implode('#', $idrg),
            'inacbg' => implode('#', $inacbg)
        ];
    }
    
    private function _val($array, $key, $default = '')
    {
      if (is_array($array) && isset($array[$key]) && $array[$key] !== '') {
        return $array[$key];
      }
    
      return $default;
    }
    
    private function _bridgeVal($print_sep, $key, $default = '')
    {
      if (
        is_array($print_sep) &&
        isset($print_sep['bridging_sep']) &&
        is_array($print_sep['bridging_sep']) &&
        isset($print_sep['bridging_sep'][$key]) &&
        $print_sep['bridging_sep'][$key] !== ''
      ) {
        return $print_sep['bridging_sep'][$key];
      }
    
      return $default;
    }
    
    private function _formatDokterKFR($nama)
    {
      $nama = trim($nama);
    
      if ($nama == '') {
        return '';
      }
    
      if (stripos($nama, 'dr.') !== 0 && stripos($nama, 'dr ') !== 0) {
        $nama = 'dr. ' . $nama;
      }
    
      if (!preg_match('/Sp\.?\s*KFR/i', $nama)) {
          $nama .= ', Sp.KFR';
      }
    
      return $nama;
    }
    
    private function _getNamaDokterUtamaTTD($reg_periksa, $print_sep, $resume_ranap)
    {
      $status_lanjut = $this->_val($reg_periksa, 'status_lanjut');
      $kd_poli       = $this->_val($reg_periksa, 'kd_poli');
    
      $nama_dokter_sep = $this->_bridgeVal($print_sep, 'nmdpdjp');
      $nama_dokter_reg = $this->_val($reg_periksa, 'nm_dokter');
    
      if ($status_lanjut == 'Ralan' && $kd_poli == 'U0050') {
        return $nama_dokter_sep;
      }
    
      if ($status_lanjut == 'Ralan' && $kd_poli == 'U0021') {
        return $this->_formatDokterKFR($nama_dokter_sep);
      }
    
      if ($status_lanjut == 'Ralan') {
        return $nama_dokter_reg;
      }
    
      if (
        $status_lanjut == 'Ranap' &&
        is_array($resume_ranap) &&
        isset($resume_ranap['kd_dokter']) &&
        $resume_ranap['kd_dokter'] == 'D0000031'
      ) {
        return 'dr. Fransisca Janne Siahaya, Sp.B';
      }
    
      if ($status_lanjut == 'Ranap' && $nama_dokter_sep != '') {
        return $nama_dokter_sep;
      }
    
      return $nama_dokter_reg;
    }
    
    private function _makeQRText($jenis, $nama, $no_rawat, $no_sep = '', $tambahan = '')
    {
      $nama = trim($nama);
    
      if ($nama == '') {
        return '';
      }
    
      $text = 'Ditandatangani secara elektronik oleh: ' . $nama;
      $text .= ' | Sebagai: ' . trim($jenis);
      $text .= ' | No Rawat: ' . trim($no_rawat);
    
      if ($no_sep != '') {
        $text .= ' | No SEP: ' . trim($no_sep);
      }
    
      if ($tambahan != '') {
        $text .= ' | ' . trim($tambahan);
      }
    
      $text .= ' | RSUD Matraman';
    
      return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
    
    private function _makeQRPasienText($nama_pasien, $no_rm, $no_rawat, $no_sep = '')
    {
      $nama_pasien = trim($nama_pasien);
    
      if ($nama_pasien == '') {
        return '';
      }
    
      $text = 'Persetujuan pasien/keluarga pasien: ' . $nama_pasien;
      $text .= ' | No RM: ' . trim($no_rm);
      $text .= ' | No Rawat: ' . trim($no_rawat);
    
      if ($no_sep != '') {
        $text .= ' | No SEP: ' . trim($no_sep);
      }
    
      $text .= ' | RSUD Matraman';
    
      return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
    
    private function _cleanHTMLForMpdf($html)
    {
      if ($html === null) {
        return '';
      }
    
      /*
       * Pastikan string jadi UTF-8 valid.
       */
      if (function_exists('mb_check_encoding') && !mb_check_encoding($html, 'UTF-8')) {
        $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
      }
    
      /*
       * Buang byte invalid yang masih tersisa.
       */
      if (function_exists('iconv')) {
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $html);
    
        if ($clean !== false) {
          $html = $clean;
        }
      }
    
      /*
       * Ganti replacement character � dengan strip.
       */
      $html = str_replace("\xEF\xBF\xBD", '-', $html);
      $html = str_replace('�', '-', $html);
    
      /*
       * Buang control character yang bisa bikin mPDF error.
       */
      $html = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', ' ', $html);
    
      /*
       * Rapikan whitespace berlebih.
       */
      $html = str_replace(["\r\n", "\r"], "\n", $html);
    
      return $html;
    }
    
    private function _cleanLongTextForPDF($text)
    {
      if ($text === null) {
        return '';
      }
    
      $text = (string) $text;
    
      if (function_exists('mb_check_encoding') && !mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
      }
    
      if (function_exists('iconv')) {
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
    
        if ($clean !== false) {
          $text = $clean;
        }
      }
    
      $text = str_replace(["\r\n", "\r"], "\n", $text);
    
      // replacement character jadi line break
      $text = preg_replace('/�+/', "\n", $text);
    
      // tanda tanya beruntun biasanya hasil karakter rusak
      $text = preg_replace('/\?{2,}/', "\n", $text);
    
      // tanda tanya setelah titik dua biasanya karakter rusak, bukan pertanyaan
      $text = preg_replace('/:\s*\?([A-Za-z])/', ': $1', $text);
    
      // rapikan spasi sebelum newline
      $text = preg_replace('/[ \t]+\n/', "\n", $text);
    
      // rapikan newline berlebih
      $text = preg_replace("/\n{3,}/", "\n\n", $text);
    
      $text = trim($text);
    
      $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    
      return nl2br($text);
    }   
    
    public function postBulkSetStatus()
    {
      header('Content-Type: application/json');
    
      try {
        $allowedStatus = ['Lengkap', 'Pengajuan', 'Perbaiki'];
    
        $status = isset($_POST['status']) ? trim($_POST['status']) : '';
        $catatan = isset($_POST['catatan']) ? trim($_POST['catatan']) : '';
    
        if (!in_array($status, $allowedStatus)) {
          echo json_encode([
            'status' => false,
            'message' => 'Status tidak valid'
          ]);
          exit();
        }
    
        $noRawatList = [];
    
        if (isset($_POST['no_rawat'])) {
          if (is_array($_POST['no_rawat'])) {
            $noRawatList = $_POST['no_rawat'];
          } else {
            $noRawatList = [$_POST['no_rawat']];
          }
        }
    
        if (!count($noRawatList)) {
          echo json_encode([
            'status' => false,
            'message' => 'Tidak ada data yang dipilih'
          ]);
          exit();
        }
    
        $success = 0;
        $failed = 0;
        $results = [];
    
        foreach ($noRawatList as $no_rawat) {
          $no_rawat = trim($no_rawat);
    
          if ($no_rawat == '') {
            $failed++;
            continue;
          }
    
          $vedika = $this->db('mlite_vedika')
            ->where('no_rawat', $no_rawat)
            ->oneArray();
    
          if (!$vedika) {
            $failed++;
            $results[] = [
              'no_rawat' => $no_rawat,
              'status' => false,
              'message' => 'Data Vedika tidak ditemukan'
            ];
            continue;
          }
    
          $update = $this->db('mlite_vedika')
            ->where('no_rawat', $no_rawat)
            ->save([
              'tanggal' => date('Y-m-d'),
              'status' => $status,
              'username' => $this->core->getUserInfo('username', null, true)
            ]);
    
          if ($update) {
            $success++;
    
            $nosep = isset($vedika['nosep']) ? $vedika['nosep'] : '';
    
            $this->db('mlite_vedika_feedback')->save([
              'id' => NULL,
              'nosep' => $nosep,
              'tanggal' => date('Y-m-d'),
              'catatan' => $status . ' - ' . $catatan,
              'username' => $this->core->getUserInfo('username', null, true)
            ]);
    
            $results[] = [
              'no_rawat' => $no_rawat,
              'nosep' => $nosep,
              'status' => true,
              'message' => 'Status berhasil diubah'
            ];
    
          } else {
            $failed++;
    
            $results[] = [
              'no_rawat' => $no_rawat,
              'status' => false,
              'message' => 'Gagal update status'
            ];
          }
        }
    
        echo json_encode([
          'status' => true,
          'message' => 'Bulk set status selesai',
          'success' => $success,
          'failed' => $failed,
          'total' => count($noRawatList),
          'results' => $results
        ]);
        exit();
    
      } catch (\Throwable $e) {
        echo json_encode([
          'status' => false,
          'message' => $e->getMessage(),
          'file' => $e->getFile(),
          'line' => $e->getLine()
        ]);
        exit();
      }
    }

    public function getBulkPDFKlaimList()
    {
      header('Content-Type: application/json; charset=utf-8');

      try {
        $jenis = isset($_GET['jenis']) ? (string) $_GET['jenis'] : '2';
        $start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : date('Y-m-d');
        $end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : date('Y-m-d');
        $poli = isset($_GET['poli']) ? trim($_GET['poli']) : '';
        $phrase = isset($_GET['s']) ? trim($_GET['s']) : '';

        if (!in_array($jenis, ['1', '2'], true)) {
          throw new \InvalidArgumentException('Jenis pelayanan tidak valid.');
        }

        $start = \DateTime::createFromFormat('Y-m-d', $start_date);
        $end = \DateTime::createFromFormat('Y-m-d', $end_date);

        if (!$start || $start->format('Y-m-d') !== $start_date ||
            !$end || $end->format('Y-m-d') !== $end_date) {
          throw new \InvalidArgumentException('Format tanggal harus YYYY-MM-DD.');
        }

        if ($start_date > $end_date) {
          throw new \InvalidArgumentException('Tanggal awal tidak boleh melewati tanggal akhir.');
        }

        $search = '%' . $phrase . '%';

        if ($jenis === '1') {
          // Samakan dengan filter halaman Pengajuan Rawat Inap:
          // tanggal memakai tanggal pulang dan tidak mengambil baris Pindah Kamar.
          $query = $this->db()->pdo()->prepare("SELECT mv.no_rawat, mv.nosep
            FROM mlite_vedika mv
            WHERE mv.status = 'Pengajuan'
              AND (mv.no_rkm_medis LIKE ? OR mv.no_rawat LIKE ? OR mv.nosep LIKE ?)
              AND EXISTS (
                SELECT 1
                FROM kamar_inap ki
                WHERE ki.no_rawat = mv.no_rawat
                  AND ki.tgl_keluar BETWEEN ? AND ?
                  AND ki.stts_pulang != 'Pindah Kamar'
              )
            ORDER BY mv.nosep");
          $query->execute([$search, $search, $search, $start_date, $end_date]);
        } else {
          // Samakan dengan filter halaman Pengajuan Rawat Jalan.
          $query = $this->db()->pdo()->prepare("SELECT mv.no_rawat, mv.nosep
            FROM mlite_vedika mv
            WHERE mv.status = 'Pengajuan'
              AND mv.jenis = '2'
              AND mv.kd_poli LIKE ?
              AND (mv.no_rkm_medis LIKE ? OR mv.no_rawat LIKE ? OR mv.nosep LIKE ?)
              AND mv.tgl_registrasi BETWEEN ? AND ?
            ORDER BY mv.nosep");
          $query->execute(['%' . $poli . '%', $search, $search, $search, $start_date, $end_date]);
        }

        $rows = [];

        foreach ($query->fetchAll() as $row) {
          $rows[] = [
            'no_rawat' => $row['no_rawat'],
            'nosep' => isset($row['nosep']) ? $row['nosep'] : '',
            'create_url' => url([
              ADMIN,
              'vedika',
              'createpdfklaim',
              $this->convertNorawat($row['no_rawat'])
            ])
          ];
        }

        echo json_encode([
          'status' => true,
          'total' => count($rows),
          'rows' => $rows
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
      } catch (\Throwable $e) {
        http_response_code(400);
        echo json_encode([
          'status' => false,
          'message' => $e->getMessage(),
          'rows' => []
        ], JSON_UNESCAPED_UNICODE);
        exit();
      }
    }

    public function getDownloadPDFKlaimZip()
    {
      $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
      $end_date   = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
      $jenis      = isset($_GET['jenis']) ? $_GET['jenis'] : '2';
      $poli       = isset($_GET['poli']) ? $_GET['poli'] : '';
      $status     = isset($_GET['status']) ? $_GET['status'] : 'Pengajuan';

      $startDateObject = \DateTime::createFromFormat('!Y-m-d', $start_date);
      $endDateObject = \DateTime::createFromFormat('!Y-m-d', $end_date);
      if (
        !$startDateObject || !$endDateObject
        || $startDateObject->format('Y-m-d') !== $start_date
        || $endDateObject->format('Y-m-d') !== $end_date
        || $startDateObject > $endDateObject
      ) {
        echo "Rentang tanggal download ZIP tidak valid.";
        exit();
      }
    
      $kode = 'KLM';
    
      if (!class_exists('ZipArchive')) {
        echo "ZipArchive belum aktif di server.";
        exit();
      }
    
      $whereStatus = "";
      $params = [];
    
      /*
       * jenis = 1 : Ranap
       * Filter tanggal pakai kamar_inap.tgl_keluar terakhir.
       *
       * jenis = 2 : Ralan
       * Filter tanggal pakai mlite_vedika.tgl_registrasi.
       */
      if ($jenis == '1') {
    
        $params = [
          $jenis,
          $start_date,
          $end_date,
          '%' . $poli . '%',
          $kode
        ];
    
        if ($status != '' && strtoupper($status) != 'ALL') {
          $whereStatus = " AND v.status = ? ";
          $params[] = $status;
        }
    
        $sql = "
          SELECT
            v.no_rawat,
            v.nosep,
            v.no_rkm_medis,
            v.tgl_registrasi,
            v.status,
            ki.tgl_keluar AS tanggal_zip,
            bdp.lokasi_file
          FROM mlite_vedika v
          INNER JOIN (
            SELECT 
              no_rawat,
              MAX(tgl_keluar) AS tgl_keluar
            FROM kamar_inap
            WHERE tgl_keluar IS NOT NULL
              AND tgl_keluar <> ''
              AND tgl_keluar <> '0000-00-00'
            GROUP BY no_rawat
          ) ki
            ON ki.no_rawat = v.no_rawat
          INNER JOIN berkas_digital_perawatan bdp
            ON bdp.no_rawat = v.no_rawat
          WHERE v.jenis = ?
            AND ki.tgl_keluar BETWEEN ? AND ?
            AND v.kd_poli LIKE ?
            AND bdp.kode = ?
            $whereStatus
          ORDER BY ki.tgl_keluar ASC, v.nosep ASC
        ";
    
      } else {
    
        $params = [
          $jenis,
          $start_date,
          $end_date,
          '%' . $poli . '%',
          $kode
        ];
    
        if ($status != '' && strtoupper($status) != 'ALL') {
          $whereStatus = " AND v.status = ? ";
          $params[] = $status;
        }
    
        $sql = "
          SELECT
            v.no_rawat,
            v.nosep,
            v.no_rkm_medis,
            v.tgl_registrasi,
            v.status,
            v.tgl_registrasi AS tanggal_zip,
            bdp.lokasi_file
          FROM mlite_vedika v
          INNER JOIN berkas_digital_perawatan bdp
            ON bdp.no_rawat = v.no_rawat
          WHERE v.jenis = ?
            AND v.tgl_registrasi BETWEEN ? AND ?
            AND v.kd_poli LIKE ?
            AND bdp.kode = ?
            $whereStatus
          ORDER BY v.tgl_registrasi ASC, v.nosep ASC
        ";
      }
    
      $query = $this->db()->pdo()->prepare($sql);
      $query->execute($params);
    
      $rows = $query->fetchAll();
    
      if (!count($rows)) {
        echo "Belum ada PDF klaim yang terdaftar pada filter ini.";
        exit();
      }
    
      $jenisLabel = ($jenis == '1') ? 'Ranap' : 'Ralan';
      $statusLabel = ($status == '' || strtoupper($status) == 'ALL') ? 'ALL' : $status;
    
      $zipName = 'PDF_Klaim_' . $jenisLabel . '_' . $statusLabel . '_' . $start_date . '_sd_' . $end_date . '_' . date('Ymd_His') . '.zip';
      $zipName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $zipName);
    
      $zipPath = sys_get_temp_dir() . '/' . $zipName;
    
      $zip = new \ZipArchive();
    
      if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== TRUE) {
        echo "Gagal membuat file ZIP.";
        exit();
      }

      $added = 0;
      $skipped = [];
    
      foreach ($rows as $row) {
        $filePath = WEBAPPS_PATHX . '/berkasrawat/' . $row['lokasi_file'];
    
        if (file_exists($filePath) && filesize($filePath) > 0) {
          $safeSep = isset($row['nosep']) ? preg_replace('/[^A-Za-z0-9_\-]/', '', $row['nosep']) : '';
          $safeRm  = isset($row['no_rkm_medis']) ? preg_replace('/[^A-Za-z0-9_\-]/', '', $row['no_rkm_medis']) : '';
          $tanggalZip = isset($row['tanggal_zip']) ? trim((string) $row['tanggal_zip']) : '';
          if ($tanggalZip === '' && isset($row['tgl_registrasi'])) {
            $tanggalZip = trim((string) $row['tgl_registrasi']);
          }
          $tanggalTimestamp = strtotime($tanggalZip);
          $safeTgl = $tanggalTimestamp !== false
            ? date('Y-m-d', $tanggalTimestamp)
            : $start_date;
    
          if ($safeSep == '') {
            $safeSep = str_replace('/', '', $row['no_rawat']);
          }
    
          if ($safeRm == '') {
            $safeRm = 'RM';
          }
    
          $zipFileName = $safeTgl . '/' . $safeSep . '.pdf';
    
          /*
           * Cegah nama file dobel di dalam ZIP.
           */
          $counter = 1;
          $baseZipFileName = $zipFileName;
    
          while ($zip->locateName($zipFileName) !== false) {
            $zipFileName = substr($baseZipFileName, 0, -4) . '_' . $counter . '.pdf';
            $counter++;
          }
    
          $zip->addFile($filePath, $zipFileName);
          $added++;
    
        } else {
          $skipped[] = [
            'no_rawat' => $row['no_rawat'],
            'file' => $row['lokasi_file'],
            'path' => $filePath
          ];
        }
      }
    
      $zip->close();
    
      if ($added == 0) {
        if (file_exists($zipPath)) {
          unlink($zipPath);
        }
    
        echo "Data ditemukan, tetapi file PDF fisik tidak ditemukan di server mLITE.";
        exit();
      }
    
      while (ob_get_level() > 0) {
        ob_end_clean();
      }
    
      header('Content-Type: application/zip');
      header('Content-Disposition: attachment; filename="' . $zipName . '"');
      header('Content-Length: ' . filesize($zipPath));
      header('Pragma: public');
      header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    
      readfile($zipPath);
      unlink($zipPath);
      exit();
    }

    public function getRingkasanMedis($status_lanjut, $no_rawat)
    {
      $no_rawat = revertNoRawat($no_rawat);

      $diagnosa = $this->db('diagnosa_pasien')
        ->join(
          'penyakit',
          'penyakit.kd_penyakit = diagnosa_pasien.kd_penyakit'
        )
        ->where('diagnosa_pasien.no_rawat', $no_rawat)
        ->where('diagnosa_pasien.status', $status_lanjut)
        ->asc('diagnosa_pasien.prioritas')
        ->toArray();

      $prosedur = $this->db('prosedur_pasien')
        ->join(
          'icd9',
          'icd9.kode = prosedur_pasien.kode'
        )
        ->where('prosedur_pasien.no_rawat', $no_rawat)
        ->where('prosedur_pasien.status', $status_lanjut)
        ->asc('prosedur_pasien.prioritas')
        ->toArray();

      $hasilDiagnosa = [];
      foreach ($diagnosa as $data) {
        $hasilDiagnosa[] = [
          'kode' => $data['kd_penyakit'],
          'nama' => $data['nm_penyakit']
        ];
      }

      $hasilProsedur = [];
      foreach ($prosedur as $data) {
        $hasilProsedur[] = [
          'kode' => $data['kode'],
          'nama' => $data['deskripsi_pendek']
        ];
      }

      header('Content-Type: application/json; charset=utf-8');
      echo json_encode([
        'diagnosa' => $hasilDiagnosa,
        'prosedur' => $hasilProsedur
      ], JSON_UNESCAPED_UNICODE);
      exit();
    }

}
