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
  private $activeGroupingJobId = null;
  private $activeGroupingWorkerId = null;
  private $groupingJobDeadline = null;
  private $lastGroupingRequestMethod = null;

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
        $codingValidation = $this->_validateCodingRows($diagnosa_pasienx, $prosedur_pasienx);

        $row = htmlspecialchars_array($row);    
        $row['formVclaimURL'] = url([ADMIN, 'vedika', 'formsep', '?no_asuransi=' . $no_peserta .'&no_rawat='.$row['no_rawat']]);         
        $row['diagnosa_pasienx'] = $diagnosa_pasienx;
        $row['only_im_diagnosis'] = $onlyIMDiagnosis;
        $row['coding_blocked'] = !$codingValidation['ok'];
        $row['diagnosis_validation_message'] = htmlspecialchars($codingValidation['diagnosis_message'], ENT_QUOTES, 'UTF-8');
        $row['procedure_validation_message'] = htmlspecialchars($codingValidation['procedure_message'], ENT_QUOTES, 'UTF-8');
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
        $row['required_document_alerts'] = $this->_getRequiredDocumentAlerts($row['no_rawat'], $row['no_rkm_medis'], $diagnosa_pasienx, $prosedur_pasienx, $berkas_digital);
        $currentDiagnosisRows = isset($diagnosa_pasienx) ? $diagnosa_pasienx : (isset($diagnosa_pasien) ? $diagnosa_pasien : []);
        $currentProcedureRows = isset($prosedur_pasienx) ? $prosedur_pasienx : (isset($prosedur_pasien) ? $prosedur_pasien : []);
        $currentCodingValidation = $this->_validateCodingRows($currentDiagnosisRows, $currentProcedureRows);
        $row['coding_blocked'] = !$currentCodingValidation['ok'];
        $row['diagnosis_validation_message'] = htmlspecialchars($currentCodingValidation['diagnosis_message'], ENT_QUOTES, 'UTF-8');
        $row['procedure_validation_message'] = htmlspecialchars($currentCodingValidation['procedure_message'], ENT_QUOTES, 'UTF-8');
        $row['coding_blocked'] = !empty($row['coding_blocked']) || !empty($row['required_document_alerts']);
        $row['radiology_expertise_missing'] = $this->_hasMissingRadiologyExpertise($row['no_rawat']);
        $row['formSepURL'] = url([ADMIN, 'vedika', 'formsepvclaim', '?no_rawat=' . $row['no_rawat']]);
        $row['pdfURL'] = url([ADMIN, 'vedika', 'pdf', $this->convertNorawat($row['no_rawat'])]);
        $row['createPdfKlaimURL'] = url([ADMIN, 'vedika', 'createpdfklaim', $this->convertNorawat($row['no_rawat'])]);
        $pdfKlaimState = $this->db('berkas_digital_perawatan')->where('no_rawat', $row['no_rawat'])->where('kode', 'KLM')->oneArray();
        $row['pdf_klaim_created'] = ($pdfKlaimState && file_exists(WEBAPPS_PATHX . '/berkasrawat/' . $pdfKlaimState['lokasi_file'])) ? '1' : '';
        $row['pdf_klaim_url'] = $row['pdf_klaim_created'] === '1' ? url(WEBAPPS_URLX) . '/berkasrawat/' . $pdfKlaimState['lokasi_file'] : '';
        $row['setstatusURL']  = url([ADMIN, 'vedika', 'setstatus', $this->_getSEPInfo('no_sep', $row['no_rawat'])]);
        $row['inacbgsURL'] = url([ADMIN, 'vedika', 'bridginginacbgs', $this->convertNorawat($row['no_rawat']), '?nosep=' . rawurlencode($row['no_sep'])]);
        $row['status_pengajuan'] = $this->db('mlite_vedika')->where('nosep', $this->_getSEPInfo('no_sep', $row['no_rawat']))->desc('id')->limit(1)->toArray();
        $row['berkasPasien'] = url([ADMIN, 'vedika', 'berkaspasien', $this->getRegPeriksaInfo('no_rkm_medis', $row['no_rawat'])]);
        $row['berkasPerawatan'] = url([ADMIN, 'vedika', 'berkasperawatan', $this->convertNorawat($row['no_rawat'])]);
        $pdfKlaim = $this->db('berkas_digital_perawatan')->where('no_rawat', $row['no_rawat'])->where('kode', 'KLM')->oneArray();
        $row['pdf_klaim_created'] = ($pdfKlaim && file_exists(WEBAPPS_PATHX . '/berkasrawat/' . $pdfKlaim['lokasi_file'])) ? '1' : '';
        $row['pdf_klaim_url'] = $row['pdf_klaim_created'] === '1' ? url(WEBAPPS_URLX) . '/berkasrawat/' . $pdfKlaim['lokasi_file'] : '';
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
        $row['required_document_alerts'] = $this->_getRequiredDocumentAlerts($row['no_rawat'], $row['no_rkm_medis'], $diagnosa_pasienx, $prosedur_pasienx, $berkas_digital);
        $currentDiagnosisRows = isset($diagnosa_pasienx) ? $diagnosa_pasienx : (isset($diagnosa_pasien) ? $diagnosa_pasien : []);
        $currentProcedureRows = isset($prosedur_pasienx) ? $prosedur_pasienx : (isset($prosedur_pasien) ? $prosedur_pasien : []);
        $currentCodingValidation = $this->_validateCodingRows($currentDiagnosisRows, $currentProcedureRows);
        $row['coding_blocked'] = !$currentCodingValidation['ok'];
        $row['diagnosis_validation_message'] = htmlspecialchars($currentCodingValidation['diagnosis_message'], ENT_QUOTES, 'UTF-8');
        $row['procedure_validation_message'] = htmlspecialchars($currentCodingValidation['procedure_message'], ENT_QUOTES, 'UTF-8');
        $row['coding_blocked'] = !empty($row['coding_blocked']) || !empty($row['required_document_alerts']);
        $row['radiology_expertise_missing'] = $this->_hasMissingRadiologyExpertise($row['no_rawat']);
        $row['formSepURL'] = url([ADMIN, 'vedika', 'formsepvclaim', '?no_rawat=' . $row['no_rawat']]);
        $row['pdfURL'] = url([ADMIN, 'vedika', 'pdf', $this->convertNorawat($row['no_rawat'])]);
        $row['createPdfKlaimURL'] = url([ADMIN, 'vedika', 'createpdfklaim', $this->convertNorawat($row['no_rawat'])]);
        $pdfKlaim = $this->db('berkas_digital_perawatan')->where('no_rawat', $row['no_rawat'])->where('kode', 'KLM')->oneArray();
        $row['pdf_klaim_created'] = ($pdfKlaim && file_exists(WEBAPPS_PATHX . '/berkasrawat/' . $pdfKlaim['lokasi_file'])) ? '1' : '';
        $row['pdf_klaim_url'] = $row['pdf_klaim_created'] === '1' ? url(WEBAPPS_URLX) . '/berkasrawat/' . $pdfKlaim['lokasi_file'] : '';
        $row['setstatusURL']  = url([ADMIN, 'vedika', 'setstatus', $this->_getSEPInfo('no_sep', $row['no_rawat'])]);
        $row['inacbgsURL'] = url([ADMIN, 'vedika', 'bridginginacbgs', $this->convertNorawat($row['no_rawat']), '?nosep=' . rawurlencode($row['no_sep'])]);
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
        bs.no_sep,
        bs.klsrawat
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
         AND bs.jnspelayanan = '1'
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
        $codingValidation = $this->_validateCodingRows($diagnosa_pasienx, $prosedur_pasienx);

        $row = htmlspecialchars_array($row);    
        $row['formVclaimURL'] = url([ADMIN, 'vedika', 'formsep', '?no_asuransi=' . $no_peserta .'&no_rawat='.$row['no_rawat']]);    
        $row['diagnosa_pasienx'] = $diagnosa_pasienx;
        $row['only_im_diagnosis'] = $onlyIMDiagnosis;
        $row['coding_blocked'] = !$codingValidation['ok'];
        $row['diagnosis_validation_message'] = htmlspecialchars($codingValidation['diagnosis_message'], ENT_QUOTES, 'UTF-8');
        $row['procedure_validation_message'] = htmlspecialchars($codingValidation['procedure_message'], ENT_QUOTES, 'UTF-8');
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
        $row['required_document_alerts'] = $this->_getRequiredDocumentAlerts($row['no_rawat'], $row['no_rkm_medis'], $diagnosa_pasienx, $prosedur_pasienx, $berkas_digital);
        $currentDiagnosisRows = isset($diagnosa_pasienx) ? $diagnosa_pasienx : (isset($diagnosa_pasien) ? $diagnosa_pasien : []);
        $currentProcedureRows = isset($prosedur_pasienx) ? $prosedur_pasienx : (isset($prosedur_pasien) ? $prosedur_pasien : []);
        $currentCodingValidation = $this->_validateCodingRows($currentDiagnosisRows, $currentProcedureRows);
        $row['coding_blocked'] = !$currentCodingValidation['ok'];
        $row['diagnosis_validation_message'] = htmlspecialchars($currentCodingValidation['diagnosis_message'], ENT_QUOTES, 'UTF-8');
        $row['procedure_validation_message'] = htmlspecialchars($currentCodingValidation['procedure_message'], ENT_QUOTES, 'UTF-8');
        $row['coding_blocked'] = !empty($row['coding_blocked']) || !empty($row['required_document_alerts']);
        $row['radiology_expertise_missing'] = $this->_hasMissingRadiologyExpertise($row['no_rawat']);
        $row['resume'] = $this->_getResumeRanap('cara_keluar', $row['no_rawat']);
        $row['formSepURL'] = url([ADMIN, 'vedika', 'formsepvclaim', '?no_rawat=' . $row['no_rawat']]);
        $row['createPdfKlaimURL'] = url([ADMIN, 'vedika', 'createpdfklaim', $this->convertNorawat($row['no_rawat'])]);
        $row['pdfURL'] = url([ADMIN, 'vedika', 'pdf', $this->convertNorawat($row['no_rawat'])]);
        $pdfKlaim = $this->db('berkas_digital_perawatan')->where('no_rawat', $row['no_rawat'])->where('kode', 'KLM')->oneArray();
        $row['pdf_klaim_created'] = ($pdfKlaim && file_exists(WEBAPPS_PATHX . '/berkasrawat/' . $pdfKlaim['lokasi_file'])) ? '1' : '';
        $row['pdf_klaim_url'] = $row['pdf_klaim_created'] === '1' ? url(WEBAPPS_URLX) . '/berkasrawat/' . $pdfKlaim['lokasi_file'] : '';
        $row['setstatusURL']  = url([ADMIN, 'vedika', 'setstatus', $this->_getSEPInfo('no_sep', $row['no_rawat'])]);
        $row['inacbgsURL'] = url([ADMIN, 'vedika', 'bridginginacbgs', $this->convertNorawat($row['no_rawat']), '?nosep=' . rawurlencode($row['no_sep'])]);
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
          $kelasValidation = $this->_validateKelasRawat($row['klsrawat'] ?? '', $get_kamar['kelas'] ?? '');
          $row['kelas_rawat_mismatch'] = $kelasValidation['mismatch'];
          $row['kelas_rawat_alert'] = $kelasValidation['alert'];
          if ($kelasValidation['mismatch']) {
            $row['coding_blocked'] = true;
          }
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
    $missingRemoteBerkasAlerts = $this->_getMissingRemoteBerkasAlertsForRows($rows);
    
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
        $row['required_document_alerts'] = $this->_getRequiredDocumentAlerts($row['no_rawat'], $row['no_rkm_medis'], $diagnosa_pasienx, $prosedur_pasienx, $berkas_digital);
        $currentDiagnosisRows = isset($diagnosa_pasienx) ? $diagnosa_pasienx : (isset($diagnosa_pasien) ? $diagnosa_pasien : []);
        $currentProcedureRows = isset($prosedur_pasienx) ? $prosedur_pasienx : (isset($prosedur_pasien) ? $prosedur_pasien : []);
        $currentCodingValidation = $this->_validateCodingRows($currentDiagnosisRows, $currentProcedureRows);
        $row['coding_blocked'] = !$currentCodingValidation['ok'];
        $row['diagnosis_validation_message'] = htmlspecialchars($currentCodingValidation['diagnosis_message'], ENT_QUOTES, 'UTF-8');
        $row['procedure_validation_message'] = htmlspecialchars($currentCodingValidation['procedure_message'], ENT_QUOTES, 'UTF-8');
        $row['coding_blocked'] = !empty($row['coding_blocked']) || !empty($row['required_document_alerts']);
        // Notifikasi fisik berkas remote hanya bersifat peringatan.
        // Jangan ikut mengunci status/koding karena berkas mungkin baru saja
        // diunggah ulang dan akan tervalidasi saat halaman direfresh.
        $remoteFileAlerts = isset($missingRemoteBerkasAlerts[$row['no_rawat']])
          ? $missingRemoteBerkasAlerts[$row['no_rawat']]
          : [];
        if (!empty($remoteFileAlerts)) {
          $row['required_document_alerts'] = array_values(array_unique(array_merge(
            $row['required_document_alerts'],
            $remoteFileAlerts
          )));
        }
        $row['radiology_expertise_missing'] = $this->_hasMissingRadiologyExpertise($row['no_rawat']);
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
        $pdfKlaim = $this->db('berkas_digital_perawatan')->where('no_rawat', $row['no_rawat'])->where('kode', 'KLM')->oneArray();
        $row['pdf_klaim_created'] = '';
        $row['pdf_klaim_url'] = '';
        if ($pdfKlaim && !empty($pdfKlaim['lokasi_file']) && is_file(WEBAPPS_PATHX . '/berkasrawat/' . $pdfKlaim['lokasi_file'])) {
          $row['pdf_klaim_created'] = '1';
          $row['pdf_klaim_url'] = url(WEBAPPS_URLX) . '/berkasrawat/' . $pdfKlaim['lokasi_file'];
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

    $dc_filter = isset($_GET['dc']) ? strtolower(trim((string) $_GET['dc'])) : 'all';
    if (!in_array($dc_filter, ['all', 'sent', 'unsent'], true)) $dc_filter = 'all';
    $dcWhere = $this->_groupingDcFilterSql('mlite_vedika', $dc_filter);
      
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
      INNER JOIN reg_periksa rp ON rp.no_rawat = mlite_vedika.no_rawat
      -- INNER JOIN bridging_sep on bridging_sep.no_rawat = mlite_vedika.no_rawat
      WHERE
      mlite_vedika.tgl_registrasi BETWEEN '$start_date' AND '$end_date'
      AND mlite_vedika.kd_poli LIKE '%$poli%'
      AND mlite_vedika.`status` = 'Lengkap'
      AND mlite_vedika.jenis = '2'
      AND rp.stts <> 'Batal'
      $dcWhere
      AND (mlite_vedika.no_rkm_medis LIKE ? OR mlite_vedika.nosep LIKE ?)");
    $totalRecords->execute(['%' . $phrase . '%', '%' . $phrase . '%']);
    $totalRecords = $totalRecords->fetchAll();

    $pagination = new \Systems\Lib\Pagination($page, count($totalRecords), $perpage, url([ADMIN, 'vedika', 'lengkap', $type, '%d?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date . '&poli=' . $poli . '&dc=' . $dc_filter]));
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
    INNER JOIN reg_periksa rp ON rp.no_rawat = v.no_rawat
    WHERE
        v.tgl_registrasi BETWEEN '$start_date' AND '$end_date'
        AND v.kd_poli LIKE '%$poli%'
        AND v.status = 'Lengkap'
        AND v.jenis  = '2'
        AND rp.stts <> 'Batal'
      " . $this->_groupingDcFilterSql('v', $dc_filter) . "
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

        $codingValidation = $this->_validateCodingRows($diagnosa_pasienx, $prosedur_pasienx);
        $row['coding_blocked'] = !$codingValidation['ok'];
        $row['diagnosis_validation_message'] = htmlspecialchars($codingValidation['diagnosis_message'], ENT_QUOTES, 'UTF-8');
        $row['procedure_validation_message'] = htmlspecialchars($codingValidation['procedure_message'], ENT_QUOTES, 'UTF-8');
        $row['grouping_error'] = $this->_getLatestGroupingFailure($row['no_rawat'], $row['nosep']);
        $row['dc_delivery'] = $this->_getLatestGroupingDelivery($row['no_rawat'], $row['nosep']);

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
        $row['createPdfKlaimURL'] = url([ADMIN, 'vedika', 'createpdfklaim', $this->convertNorawat($row['no_rawat'])]);
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
        $row['required_document_alerts'] = $this->_getRequiredDocumentAlerts($row['no_rawat'], $row['no_rkm_medis'], $diagnosa_pasienx, $prosedur_pasienx, $berkas_digital);
        $row['coding_blocked'] = !empty($row['coding_blocked']) || !empty($row['required_document_alerts']);
        $row['radiology_expertise_missing'] = $this->_hasMissingRadiologyExpertise($row['no_rawat']);
        $row['formSepURL'] = url([ADMIN, 'vedika', 'formsepvclaim', '?no_rawat=' . $row['no_rawat']]);
        $row['pdfURL'] = url([ADMIN, 'vedika', 'pdfklaim', $this->convertNorawat($row['no_rawat'])]);
        $row['createPdfKlaimURL'] = url([ADMIN, 'vedika', 'createpdfklaim', $this->convertNorawat($row['no_rawat'])]);
        $pdfKlaim = $this->db('berkas_digital_perawatan')->where('no_rawat', $row['no_rawat'])->where('kode', 'KLM')->oneArray();
        $row['pdf_klaim_created'] = ($pdfKlaim && file_exists(WEBAPPS_PATHX . '/berkasrawat/' . $pdfKlaim['lokasi_file'])) ? '1' : '';
        $row['pdf_klaim_url'] = $row['pdf_klaim_created'] === '1' ? url(WEBAPPS_URLX) . '/berkasrawat/' . $pdfKlaim['lokasi_file'] : '';
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

    $this->assign['searchUrl'] =  url([ADMIN, 'vedika', 'lengkap', $type, $page . '?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date . '&poli=' . $poli . '&dc=' . $dc_filter]);
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

    $dc_filter = isset($_GET['dc']) ? strtolower(trim((string) $_GET['dc'])) : 'all';
    if (!in_array($dc_filter, ['all', 'sent', 'unsent'], true)) $dc_filter = 'all';
    $dcWhere = $this->_groupingDcFilterSql('mlite_vedika', $dc_filter);

    // pagination
    $totalRecords = $this->db()->pdo()->prepare("SELECT mlite_vedika.no_rawat
    FROM mlite_vedika
    INNER JOIN reg_periksa rp ON rp.no_rawat = mlite_vedika.no_rawat
    WHERE 
    mlite_vedika.status = 'Lengkap'
    AND mlite_vedika.jenis = '1'
    AND rp.stts <> 'Batal'
    $dcWhere
    AND (mlite_vedika.no_rkm_medis LIKE ? OR mlite_vedika.no_rawat LIKE ? OR mlite_vedika.nosep LIKE ? )
    AND mlite_vedika.no_rawat IN (SELECT no_rawat FROM kamar_inap WHERE tgl_keluar BETWEEN '$start_date' AND '$end_date' AND kamar_inap.stts_pulang != 'Pindah Kamar')");
    $totalRecords->execute(['%' . $phrase . '%', '%' . $phrase . '%', '%' . $phrase . '%']);
    $totalRecords = $totalRecords->fetchAll();

    $pagination = new \Systems\Lib\Pagination($page, count($totalRecords), $perpage, url([ADMIN, 'vedika', 'lengkapinap', $type, '%d?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date . '&dc=' . $dc_filter]));
    $this->assign['pagination'] = $pagination->nav('pagination', '5');
    $this->assign['totalRecords'] = $totalRecords;
    
    $offset = $pagination->offset();$nomor = $offset + 1;
    $query = $this->db()->pdo()->prepare("SELECT mlite_vedika.*
    FROM mlite_vedika
    INNER JOIN reg_periksa rp ON rp.no_rawat = mlite_vedika.no_rawat
    WHERE mlite_vedika.status = 'Lengkap'
    AND mlite_vedika.jenis = '1'
    AND rp.stts <> 'Batal'
    $dcWhere
    AND (mlite_vedika.no_rkm_medis LIKE ? OR mlite_vedika.no_rawat LIKE ? OR mlite_vedika.nosep LIKE ?)
    AND mlite_vedika.no_rawat IN (SELECT no_rawat FROM kamar_inap WHERE tgl_keluar BETWEEN '$start_date' AND '$end_date' AND kamar_inap.stts_pulang != 'Pindah Kamar')
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

        $codingValidation = $this->_validateCodingRows($diagnosa_pasien, $prosedur_pasien);
        $row['coding_blocked'] = !$codingValidation['ok'];
        $row['diagnosis_validation_message'] = htmlspecialchars($codingValidation['diagnosis_message'], ENT_QUOTES, 'UTF-8');
        $row['procedure_validation_message'] = htmlspecialchars($codingValidation['procedure_message'], ENT_QUOTES, 'UTF-8');
        $row['grouping_error'] = $this->_getLatestGroupingFailure($row['no_rawat'], $row['nosep']);
        $row['dc_delivery'] = $this->_getLatestGroupingDelivery($row['no_rawat'], $row['nosep']);

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
        $row['required_document_alerts'] = $this->_getRequiredDocumentAlerts($row['no_rawat'], $row['no_rkm_medis'], $diagnosa_pasien, $prosedur_pasien, $berkas_digital);
        $row['coding_blocked'] = !empty($row['coding_blocked']) || !empty($row['required_document_alerts']);
        $row['radiology_expertise_missing'] = $this->_hasMissingRadiologyExpertise($row['no_rawat']);
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

    $this->assign['searchUrl'] =  url([ADMIN, 'vedika', 'lengkapinap', $type, $page . '?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date . '&dc=' . $dc_filter]);
    $this->assign['ralanUrl'] =  url([ADMIN, 'vedika', 'lengkapinap', 'ralan', $page . '?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date . '&dc=' . $dc_filter]);
    $this->assign['ranapUrl'] =  url([ADMIN, 'vedika', 'lengkapinap', 'ranap', $page . '?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date . '&dc=' . $dc_filter]);
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
    $totalRecords = $this->db()->pdo()->prepare("SELECT no_rawat FROM mlite_vedika WHERE status = 'Pengajuan' AND mlite_vedika.kd_poli LIKE '%$poli%' AND jenis ='2' AND EXISTS (SELECT 1 FROM reg_periksa rp WHERE rp.no_rawat = mlite_vedika.no_rawat AND rp.stts <> 'Batal') AND (no_rkm_medis LIKE ? OR no_rawat LIKE ? OR nosep LIKE ?) AND tgl_registrasi BETWEEN '$start_date' AND '$end_date'");
    $totalRecords->execute(['%' . $phrase . '%', '%' . $phrase . '%', '%' . $phrase . '%']);
    $totalRecords = $totalRecords->fetchAll();

    // Bawa seluruh filter aktif ke URL pagination agar pindah halaman tidak
    // menghilangkan filter poli (selain tanggal dan pencarian).
    $pagination = new \Systems\Lib\Pagination($page, count($totalRecords), $perpage, url([ADMIN, 'vedika', 'pengajuan', $type, '%d?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date . '&poli=' . $poli]));
    $this->assign['pagination'] = $pagination->nav('pagination', '5');
    $this->assign['totalRecords'] = $totalRecords;

    $offset = $pagination->offset();$nomor = $offset + 1;
    $query = $this->db()->pdo()->prepare("SELECT * FROM mlite_vedika WHERE status = 'Pengajuan' AND mlite_vedika.kd_poli LIKE '%$poli%' AND jenis ='2' AND EXISTS (SELECT 1 FROM reg_periksa rp WHERE rp.no_rawat = mlite_vedika.no_rawat AND rp.stts <> 'Batal') AND (no_rkm_medis LIKE ? OR no_rawat LIKE ? OR nosep LIKE ?) AND tgl_registrasi BETWEEN '$start_date' AND '$end_date' ORDER BY nosep LIMIT $perpage OFFSET $offset");
    $query->execute(['%' . $phrase . '%', '%' . $phrase . '%', '%' . $phrase . '%']);
    $rows = $query->fetchAll();
    $missingRemoteBerkasAlerts = $this->_getMissingRemoteBerkasAlertsForRows($rows);
    
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
        $row['required_document_alerts'] = $this->_getRequiredDocumentAlerts($row['no_rawat'], $row['no_rkm_medis'], $diagnosa_pasienx, $prosedur_pasienx, $berkas_digital);
        $currentDiagnosisRows = isset($diagnosa_pasienx) ? $diagnosa_pasienx : (isset($diagnosa_pasien) ? $diagnosa_pasien : []);
        $currentProcedureRows = isset($prosedur_pasienx) ? $prosedur_pasienx : (isset($prosedur_pasien) ? $prosedur_pasien : []);
        $currentCodingValidation = $this->_validateCodingRows($currentDiagnosisRows, $currentProcedureRows);
        $row['coding_blocked'] = !$currentCodingValidation['ok'];
        $row['diagnosis_validation_message'] = htmlspecialchars($currentCodingValidation['diagnosis_message'], ENT_QUOTES, 'UTF-8');
        $row['procedure_validation_message'] = htmlspecialchars($currentCodingValidation['procedure_message'], ENT_QUOTES, 'UTF-8');
        $row['coding_blocked'] = !empty($row['coding_blocked']) || !empty($row['required_document_alerts']);
        // Notifikasi fisik berkas remote hanya bersifat peringatan.
        // Jangan ikut mengunci status/koding karena berkas mungkin baru saja
        // diunggah ulang dan akan tervalidasi saat halaman direfresh.
        $remoteFileAlerts = isset($missingRemoteBerkasAlerts[$row['no_rawat']])
          ? $missingRemoteBerkasAlerts[$row['no_rawat']]
          : [];
        if (!empty($remoteFileAlerts)) {
          $row['required_document_alerts'] = array_values(array_unique(array_merge(
            $row['required_document_alerts'],
            $remoteFileAlerts
          )));
        }
        $row['radiology_expertise_missing'] = $this->_hasMissingRadiologyExpertise($row['no_rawat']);
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

    $this->assign['searchUrl'] =  url([ADMIN, 'vedika', 'pengajuan', $type, $page . '?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date . '&poli=' . $poli]);
    $this->assign['ralanUrl'] =  url([ADMIN, 'vedika', 'pengajuan', 'ralan', $page . '?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date . '&poli=' . $poli]);
    $this->assign['ranapUrl'] =  url([ADMIN, 'vedika', 'pengajuan', 'ranap', $page . '?s=' . $phrase . '&start_date=' . $start_date . '&end_date=' . $end_date . '&poli=' . $poli]);
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
    AND EXISTS (SELECT 1 FROM reg_periksa rp WHERE rp.no_rawat = mlite_vedika.no_rawat AND rp.stts <> 'Batal')
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
      AND EXISTS (SELECT 1 FROM reg_periksa rp WHERE rp.no_rawat = mlite_vedika.no_rawat AND rp.stts <> 'Batal')
      AND (no_rkm_medis LIKE ? OR no_rawat LIKE ? OR nosep LIKE ?) 
      AND no_rawat IN (SELECT no_rawat FROM kamar_inap WHERE tgl_keluar BETWEEN '$start_date' AND '$end_date' AND kamar_inap.stts_pulang != 'Pindah Kamar') 
      order by mlite_vedika.nosep LIMIT $perpage OFFSET $offset");
      $query->execute(['%' . $phrase . '%', '%' . $phrase . '%', '%' . $phrase . '%']);
      $rows = $query->fetchAll();
    $missingRemoteBerkasAlerts = $this->_getMissingRemoteBerkasAlertsForRows($rows);
    
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
        $row['required_document_alerts'] = $this->_getRequiredDocumentAlerts($row['no_rawat'], $row['no_rkm_medis'], $diagnosa_pasienx, $prosedur_pasienx, $berkas_digital);
        $currentDiagnosisRows = isset($diagnosa_pasienx) ? $diagnosa_pasienx : (isset($diagnosa_pasien) ? $diagnosa_pasien : []);
        $currentProcedureRows = isset($prosedur_pasienx) ? $prosedur_pasienx : (isset($prosedur_pasien) ? $prosedur_pasien : []);
        $currentCodingValidation = $this->_validateCodingRows($currentDiagnosisRows, $currentProcedureRows);
        $row['coding_blocked'] = !$currentCodingValidation['ok'];
        $row['diagnosis_validation_message'] = htmlspecialchars($currentCodingValidation['diagnosis_message'], ENT_QUOTES, 'UTF-8');
        $row['procedure_validation_message'] = htmlspecialchars($currentCodingValidation['procedure_message'], ENT_QUOTES, 'UTF-8');
        $row['coding_blocked'] = !empty($row['coding_blocked']) || !empty($row['required_document_alerts']);
        // Notifikasi fisik berkas remote hanya bersifat peringatan.
        // Jangan ikut mengunci status/koding karena berkas mungkin baru saja
        // diunggah ulang dan akan tervalidasi saat halaman direfresh.
        $remoteFileAlerts = isset($missingRemoteBerkasAlerts[$row['no_rawat']])
          ? $missingRemoteBerkasAlerts[$row['no_rawat']]
          : [];
        if (!empty($remoteFileAlerts)) {
          $row['required_document_alerts'] = array_values(array_unique(array_merge(
            $row['required_document_alerts'],
            $remoteFileAlerts
          )));
        }
        $row['radiology_expertise_missing'] = $this->_hasMissingRadiologyExpertise($row['no_rawat']);
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
        $row['required_document_alerts'] = $this->_getRequiredDocumentAlerts($row['no_rawat'], $row['no_rkm_medis'], $diagnosa_pasienx, $prosedur_pasienx, $berkas_digital);
        $currentDiagnosisRows = isset($diagnosa_pasienx) ? $diagnosa_pasienx : (isset($diagnosa_pasien) ? $diagnosa_pasien : []);
        $currentProcedureRows = isset($prosedur_pasienx) ? $prosedur_pasienx : (isset($prosedur_pasien) ? $prosedur_pasien : []);
        $currentCodingValidation = $this->_validateCodingRows($currentDiagnosisRows, $currentProcedureRows);
        $row['coding_blocked'] = !$currentCodingValidation['ok'];
        $row['diagnosis_validation_message'] = htmlspecialchars($currentCodingValidation['diagnosis_message'], ENT_QUOTES, 'UTF-8');
        $row['procedure_validation_message'] = htmlspecialchars($currentCodingValidation['procedure_message'], ENT_QUOTES, 'UTF-8');
        $row['coding_blocked'] = !empty($row['coding_blocked']) || !empty($row['required_document_alerts']);
        $row['radiology_expertise_missing'] = $this->_hasMissingRadiologyExpertise($row['no_rawat']);
        $row['formSepURL'] = url([ADMIN, 'vedika', 'formsepvclaim', '?no_rawat=' . $row['no_rawat']]);
        $row['pdfURL'] = url([ADMIN, 'vedika', 'pdf', $this->convertNorawat($row['no_rawat'])]);
        $row['createPdfKlaimURL'] = url([ADMIN, 'vedika', 'createpdfklaim', $this->convertNorawat($row['no_rawat'])]);
        $row['setstatusURL']  = url([ADMIN, 'vedika', 'setstatus', $this->_getSEPInfo('no_sep', $row['no_rawat'])]);
        $row['status_pengajuan'] = $this->db('mlite_vedika')->where('nosep', $this->_getSEPInfo('no_sep', $row['no_rawat']))->desc('id')->limit(1)->toArray();
        $pdfKlaim = $this->db('berkas_digital_perawatan')->where('no_rawat', $row['no_rawat'])->where('kode', 'KLM')->oneArray();
        $row['pdf_klaim_created'] = ($pdfKlaim && file_exists(WEBAPPS_PATHX . '/berkasrawat/' . $pdfKlaim['lokasi_file'])) ? '1' : '';
        $row['pdf_klaim_url'] = $row['pdf_klaim_created'] === '1' ? url(WEBAPPS_URLX) . '/berkasrawat/' . $pdfKlaim['lokasi_file'] : '';
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

      $patient = json_decode($resp, true);
      $pacs['Series'] = [];
      $pacs['Instances'] = [];
      if (is_array($patient)) {
        foreach ($patient as $study) {
          if (!isset($study['Series']) || !is_array($study['Series'])) continue;
          foreach ($study['Series'] as $seriesId) {
            $seriesId = trim((string) $seriesId);
            if ($seriesId !== '') $pacs['Series'][$seriesId] = $seriesId;
          }
        }
      }
      foreach ($pacs['Series'] as $seriesId) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $orthanc . '/series/' . rawurlencode($seriesId));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_USERPWD, $this->settings->get('orthanc.username').":".$this->settings->get('orthanc.password'));
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        $seriesResponse = curl_exec($curl);
        $seriesHttpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($seriesResponse === false || $seriesHttpCode < 200 || $seriesHttpCode >= 300) continue;
        $series = json_decode($seriesResponse, true);
        if (!is_array($series) || empty($series['ID']) || empty($series['Instances']) || !is_array($series['Instances'])) continue;
        $pacs['Instances'][] = $series;
      }

    $berkas_digital = $this->db('berkas_digital_perawatan')
      ->join('master_berkas_digital', 'master_berkas_digital.kode=berkas_digital_perawatan.kode')
      ->where('berkas_digital_perawatan.no_rawat', $this->revertNorawat($id))
      ->where('berkas_digital_perawatan.kode', '!=', 'KLM')
      ->notLike('lokasi_file','%pdf')
      ->asc('master_berkas_digital.nama')
      ->toArray();

    $berkas_digital_pdf = $this->db('berkas_digital_perawatan')
      ->join('master_berkas_digital', 'master_berkas_digital.kode=berkas_digital_perawatan.kode')
      ->where('berkas_digital_perawatan.no_rawat', $this->revertNorawat($id))
      ->where('berkas_digital_perawatan.kode', '!=', 'KLM')
      ->where('berkas_digital_perawatan.kode','!=' ,'001')
      ->like('lokasi_file','%pdf')
      ->asc('master_berkas_digital.nama')
      ->toArray();

    $berkas_sep_pdf = $this->db('berkas_digital_perawatan')
      ->join('master_berkas_digital', 'master_berkas_digital.kode=berkas_digital_perawatan.kode')
      ->where('berkas_digital_perawatan.no_rawat', $this->revertNorawat($id))
      ->where('berkas_digital_perawatan.kode', '!=', 'KLM')
      ->where('berkas_digital_perawatan.kode','=', '001')
      ->like('lokasi_file','%pdf')
      ->asc('master_berkas_digital.nama')
      ->toArray();

    $no_rawat = $this->revertNorawat($id);
    $catatan_observasi_igd = $this->db('catatan_observasi_igd')
      ->where('no_rawat', $no_rawat)
      ->asc('tgl_perawatan')
      ->asc('jam_rawat')
      ->toArray();
    $catatan_observasi_ranap = $this->db('catatan_observasi')
      ->where('no_rawat', $no_rawat)
      ->asc('tgl_perawatan')
      ->asc('jam_rawat')
      ->toArray();

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
    $hasilRadiologiPDF = $this->db('hasil_radiologi')->where('no_rawat', $this->revertNorawat($id))->toArray();
    $hasExpertiseRadiologi = false;
    foreach ($hasilRadiologiPDF as $hasilRadiologiRow) {
      $expertiseText = isset($hasilRadiologiRow['hasil'])
        ? html_entity_decode(strip_tags((string) $hasilRadiologiRow['hasil']), ENT_QUOTES, 'UTF-8')
        : '';
      $expertiseText = str_replace("\xC2\xA0", ' ', $expertiseText);
      if (trim($expertiseText) !== '') {
        $hasExpertiseRadiologi = true;
        break;
      }
    }
    $this->tpl->set('hasil_radiologi', $hasilRadiologiPDF);
    $this->tpl->set('has_expertise_radiologi', $hasExpertiseRadiologi);
    $this->tpl->set('gambar_radiologi', $this->db('gambar_radiologi')->where('no_rawat', $this->revertNorawat($id))->toArray());
    $this->tpl->set('vedika', htmlspecialchars_array($this->settings('vedika')));
    $this->tpl->set('pengaturan_billing', $this->settings->get('vedika.billing'));
    $this->tpl->set('pemeriksaan_rehab', $pemeriksaan_rehab);
    $this->tpl->set('kunjungan', $frekuensi_kunjungan);
    $this->tpl->set('uji_fungsi_kfr', $uji_fungsi_kfr);
    $this->tpl->set('pre_uji_fungsi_kfr', $pre_uji_fungsi_kfr);
    $this->tpl->set('catatan_observasi_igd', $catatan_observasi_igd);
    $this->tpl->set('catatan_observasi_ranap', $catatan_observasi_ranap);
    echo $this->tpl->draw(MODULES . '/vedika/view/admin/pdf.html', true);
    exit();
  }
  
  public function _renderPDFKlaimHTML($id)
  {
    $this->_addHeaderFiles();

    $berkas_digital = $this->db('berkas_digital_perawatan')
      ->join('master_berkas_digital', 'master_berkas_digital.kode=berkas_digital_perawatan.kode')
      ->where('berkas_digital_perawatan.no_rawat', $this->revertNorawat($id))
      ->where('berkas_digital_perawatan.kode', '!=', 'KLM')
      ->notLike('lokasi_file','%pdf')
      ->asc('master_berkas_digital.nama')
      ->toArray();

    $berkas_digital_pdf = $this->db('berkas_digital_perawatan')
      ->join('master_berkas_digital', 'master_berkas_digital.kode=berkas_digital_perawatan.kode')
      ->where('berkas_digital_perawatan.no_rawat', $this->revertNorawat($id))
      ->where('berkas_digital_perawatan.kode', '!=', 'KLM')
      ->where('berkas_digital_perawatan.kode','!=' ,'001')
      ->like('lokasi_file','%pdf')
      ->asc('master_berkas_digital.nama')
      ->toArray();

    $berkas_sep_pdf = $this->db('berkas_digital_perawatan')
      ->join('master_berkas_digital', 'master_berkas_digital.kode=berkas_digital_perawatan.kode')
      ->where('berkas_digital_perawatan.no_rawat', $this->revertNorawat($id))
      ->where('berkas_digital_perawatan.kode', '!=', 'KLM')
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
    $catatan_observasi_igd = $this->db('catatan_observasi_igd')
      ->where('no_rawat', $this->revertNorawat($id))
      ->asc('tgl_perawatan')
      ->asc('jam_rawat')
      ->toArray();
    $catatan_observasi_ranap = $this->db('catatan_observasi')
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
    $this->tpl->set('catatan_observasi_igd', $catatan_observasi_igd);
    $this->tpl->set('catatan_observasi_ranap', $catatan_observasi_ranap);
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
      ->where('berkas_digital_perawatan.kode', '!=', 'KLM')
      ->notLike('lokasi_file','%pdf')
      ->asc('master_berkas_digital.nama')
      ->toArray();

    $berkas_digital_pdf = $this->db('berkas_digital_perawatan')
      ->join('master_berkas_digital', 'master_berkas_digital.kode=berkas_digital_perawatan.kode')
      ->where('berkas_digital_perawatan.no_rawat', $this->revertNorawat($id))
      ->where('berkas_digital_perawatan.kode', '!=', 'KLM')
      ->where('berkas_digital_perawatan.kode','!=' ,'001')
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
    $catatan_observasi_igd = $this->db('catatan_observasi_igd')
      ->where('no_rawat', $this->revertNorawat($id))
      ->asc('tgl_perawatan')
      ->asc('jam_rawat')
      ->toArray();
    $catatan_observasi_ranap = $this->db('catatan_observasi')
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
    
    $this->tpl->set('catatan_observasi_igd', $catatan_observasi_igd);
    $this->tpl->set('catatan_observasi_ranap', $catatan_observasi_ranap);
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

  return $this->_drawPDFKlaimTemplateSafely(MODULES . '/vedika/view/admin/pdfklaim_generate.html');
 }

  /**
   * Render template PDF Vedika tanpa membiarkan warning/notice/deprecation PHP
   * tercetak ke HTML/PDF. Semua warning tetap masuk error_log server.
   *
   * Ini menjadi lapisan global untuk field NULL / key array yang tidak ada,
   * termasuk dokumen kondisional yang belum diberi isset() satu per satu.
   * Exception dan fatal error tidak ditelan agar job tetap gagal dengan benar.
   */
  private function _drawPDFKlaimTemplateSafely($templatePath)
  {
    $previousDisplayErrors = ini_get('display_errors');
    $previousHtmlErrors = ini_get('html_errors');

    ini_set('display_errors', '0');
    ini_set('html_errors', '0');

    $mask = E_WARNING | E_NOTICE | E_DEPRECATED | E_USER_WARNING | E_USER_NOTICE | E_USER_DEPRECATED;
    if (defined('E_STRICT')) {
      $mask |= E_STRICT;
    }

    set_error_handler(function ($severity, $message, $file, $line) use ($mask) {
      if (($severity & $mask) === 0) {
        return false;
      }

      // Jangan memasukkan warning ke output PDF, tetapi tetap simpan untuk audit/debug.
      error_log('[VEDIKA PDF TEMPLATE] ' . $message . ' @ ' . $file . ':' . $line);
      return true;
    }, $mask);

    try {
      return $this->tpl->draw($templatePath, true);
    } finally {
      restore_error_handler();
      ini_set('display_errors', (string) $previousDisplayErrors);
      ini_set('html_errors', (string) $previousHtmlErrors);
    }
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
  
    private function _resetPDFManifest($jobId, $no_rawat, $nosep)
    {
      $pdo = $this->db()->pdo();

      if ($jobId === null) {
        $stmt = $pdo->prepare("DELETE FROM mlite_vedika_pdf_manifest
          WHERE job_id IS NULL AND no_rawat = ? AND nosep = ?");
        $stmt->execute([$no_rawat, $nosep]);
      } else {
        $stmt = $pdo->prepare("DELETE FROM mlite_vedika_pdf_manifest WHERE job_id = ?");
        $stmt->execute([(int) $jobId]);
      }
    }

    private function _insertPDFManifestRow($jobId, $no_rawat, $nosep, $urutan, $kode, $nama, $sifat, $expected, $sourceCount, $message)
    {
      $stmt = $this->db()->pdo()->prepare("INSERT INTO mlite_vedika_pdf_manifest
        (job_id, no_rawat, nosep, urutan, kode_dokumen, nama_dokumen, sifat, expected, generated, source_count, message, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, NOW())");
      $stmt->execute([
        $jobId === null ? null : (int) $jobId,
        $no_rawat,
        $nosep,
        (int) $urutan,
        $kode,
        $nama,
        $sifat,
        $expected ? 1 : 0,
        (int) $sourceCount,
        substr((string) $message, 0, 500)
      ]);
    }

    private function _updatePDFManifestRow($jobId, $no_rawat, $nosep, $kode, $generated, $message, $sourceCount = null)
    {
      $pdo = $this->db()->pdo();
      $params = [(int) ($generated ? 1 : 0)];
      $setSource = '';

      if ($sourceCount !== null) {
        $setSource = ', source_count = ?';
        $params[] = (int) $sourceCount;
      }

      $params[] = substr((string) $message, 0, 500);

      if ($jobId === null) {
        $sql = "UPDATE mlite_vedika_pdf_manifest
          SET generated = ?{$setSource}, message = ?
          WHERE job_id IS NULL AND no_rawat = ? AND nosep = ? AND kode_dokumen = ?";
        $params[] = $no_rawat;
        $params[] = $nosep;
        $params[] = $kode;
      } else {
        $sql = "UPDATE mlite_vedika_pdf_manifest
          SET generated = ?{$setSource}, message = ?
          WHERE job_id = ? AND kode_dokumen = ?";
        $params[] = (int) $jobId;
        $params[] = $kode;
      }

      $stmt = $pdo->prepare($sql);
      $stmt->execute($params);
    }

    private function _initializePDFManifestCore($jobId, $no_rawat, $nosep)
    {
      $this->_resetPDFManifest($jobId, $no_rawat, $nosep);

      $sep = $this->db('bridging_sep')
        ->where('no_sep', $nosep)
        ->where('no_rawat', $no_rawat)
        ->oneArray();

      // Tahap 1: empat dokumen inti yang wajib selalu ada pada PDF klaim.
      $this->_insertPDFManifestRow(
        $jobId, $no_rawat, $nosep, 10,
        'INACBG', 'PDF INACBG', 'wajib', 1, 1,
        'Menunggu tarikan PDF dari bridging e-Klaim'
      );
      $this->_insertPDFManifestRow(
        $jobId, $no_rawat, $nosep, 20,
        'SEP', 'Surat Elegibilitas Peserta (SEP)', 'wajib', 1, $sep ? 1 : 0,
        $sep ? 'Sumber bridging_sep tersedia; menunggu render' : 'bridging_sep tidak ditemukan'
      );
      $this->_insertPDFManifestRow(
        $jobId, $no_rawat, $nosep, 30,
        'BILLING', 'Billing / Bukti Pembayaran', 'wajib', 1, 1,
        'Menunggu render billing'
      );
      $this->_insertPDFManifestRow(
        $jobId, $no_rawat, $nosep, 40,
        'RESUME', 'Resume Medis Pasien', 'wajib', 1, 1,
        'Menunggu render resume medis'
      );

      return [
        'status' => (bool) $sep,
        'message' => $sep
          ? 'Manifest inti siap'
          : 'SEP wajib tetapi data bridging_sep tidak ditemukan'
      ];
    }

    private function _getPDFManifestRows($jobId, $no_rawat, $nosep)
    {
      $pdo = $this->db()->pdo();

      if ($jobId === null) {
        $stmt = $pdo->prepare("SELECT * FROM mlite_vedika_pdf_manifest
          WHERE job_id IS NULL AND no_rawat = ? AND nosep = ? ORDER BY urutan, id");
        $stmt->execute([$no_rawat, $nosep]);
      } else {
        $stmt = $pdo->prepare("SELECT * FROM mlite_vedika_pdf_manifest
          WHERE job_id = ? ORDER BY urutan, id");
        $stmt->execute([(int) $jobId]);
      }

      return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function _preparePDFKlaimRenderedHTML($html)
    {
      $html = (string) $html;
      $html = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $html);
      $html = preg_replace('/<link\b[^>]*>/i', '', $html);
      $html = preg_replace('/<a\b[^>]*id=["\']printPageButton["\'][^>]*>[\s\S]*?<\/a>/i', '', $html);
      $html = preg_replace(
        '/<iframe\b[^>]*><\/iframe>/i',
        '<div style="border:1px solid #999;padding:10px;margin:10px 0;">Berkas PDF eksternal tidak dirender di bagian HTML ini.</div>',
        $html
      );
      $html = str_replace('class="container"', '', $html);
      return $this->_cleanHTMLForMpdf($html);
    }

    /**
     * Sanitasi otomatis karakter spesial pada TEXT NODE HTML hasil render.
     *
     * Data klinis seperti "ROM <<", "<1 TAHUN" atau "A & B" tidak boleh
     * dianggap sebagai markup oleh mPDF. Tag HTML valid dari template tetap
     * dipertahankan; karakter spesial di luar tag dikonversi ke entity HTML.
     *
     * Dilakukan setelah template selesai dirender sehingga berlaku global
     * untuk SEP, Billing, Resume dan dokumen kondisional.
     */
    private function _pdfSanitizeRenderedBodySpecialChars($html)
    {
      $html = (string) $html;
      if ($html === '') {
        return '';
      }

      // "<1 TAHUN" dan "<<" tidak dianggap tag karena setelah '<' bukan
      // nama tag yang valid, sehingga akan tetap menjadi text node.
      $tagPattern = "~(<!--[\s\S]*?-->|<!DOCTYPE\b[^>]*>|<\?[\s\S]*?\?>|</?[A-Za-z][A-Za-z0-9:_-]*(?:\s+(?:\"[^\"]*\"|'[^']*'|[^'\">])*)?\s*/?>)~iu";
      $parts = preg_split($tagPattern, $html, -1, PREG_SPLIT_DELIM_CAPTURE);
      if ($parts === false) {
        return $html;
      }

      $isTagPattern = "~^(?:<!--[\s\S]*?-->|<!DOCTYPE\b[^>]*>|<\?[\s\S]*?\?>|</?[A-Za-z][A-Za-z0-9:_-]*(?:\s+(?:\"[^\"]*\"|'[^']*'|[^'\">])*)?\s*/?>)$~iu";

      foreach ($parts as &$part) {
        if ($part === '' || preg_match($isTagPattern, $part)) {
          continue;
        }

        // Pertahankan entity valid yang sudah ada; hanya ampersand mentah
        // seperti "Dokter & Perawat" yang diubah menjadi &amp;.
        $part = preg_replace(
          '/&(?!#\d+;|#x[0-9A-Fa-f]+;|[A-Za-z][A-Za-z0-9]+;)/u',
          '&amp;',
          $part
        );

        // Kasus utama yang sebelumnya menghentikan mPDF.
        $part = str_replace(['<', '>'], ['&lt;', '&gt;'], $part);
      }
      unset($part);

      return implode('', $parts);
    }

    /**
     * Cache gambar dari server WEBAPPS remote ke temp job sebelum diberikan ke mPDF.
     *
     * KLM memang dibuat di server mLITE ini (WEBAPPS_PATHX), tetapi berkas digital
     * lain dan radiologi berada di server WEBAPPS_URL. mPDF tidak dibiarkan fetch
     * URL tersebut sendiri karena kegagalan HTTP sesaat bisa menghasilkan PDF final
     * yang tetap sukses namun kehilangan sebagian gambar.
     *
     * Strategi: download dengan retry, validasi sebagai image, lalu rewrite src ke
     * file temp lokal. Jika satu saja asset wajib gagal diambil setelah retry, caller
     * menggagalkan job agar queue retry dan tidak mem-publish PDF yang tidak lengkap.
     */
    private function _pdfCacheRemoteWebappsImageSources($html, $tempDir)
    {
      $html = (string) $html;
      $cached = 0;
      $missingRemote = [];

      if (!is_dir($tempDir) && !mkdir($tempDir, 0770, true) && !is_dir($tempDir)) {
        return [
          'html' => $html,
          'cached' => 0,
          'missing_remote' => [[
            'url' => '',
            'message' => 'Gagal membuat temp directory cache asset remote: ' . $tempDir,
          ]],
        ];
      }

      $result = preg_replace_callback(
        '/(<img\\b[^>]*\\bsrc\\s*=\\s*)(["\\\'])([^"\\\']+)\\2/i',
        function ($match) use (&$cached, &$missingRemote, $tempDir) {
          $src = html_entity_decode((string) $match[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
          $srcPath = parse_url($src, PHP_URL_PATH);
          if (!is_string($srcPath) || $srcPath === '') {
            $srcPath = $src;
          }

          // Logo/TTE yang memang tersedia lokal tetap dibaca dari filesystem lokal.
          $ttdMarker = '/TTD/';
          $ttdPos = strpos($srcPath, $ttdMarker);
          if ($ttdPos !== false) {
            $relative = rawurldecode(substr($srcPath, $ttdPos + strlen($ttdMarker)));
            $relative = ltrim(str_replace('\\\\', '/', $relative), '/');
            if ($relative !== '' && strpos($relative, '..') === false) {
              $localPath = rtrim(WEBAPPS_PATHX, '/\\') . '/TTD/' . $relative;
              if (is_file($localPath) && is_readable($localPath) && filesize($localPath) > 0) {
                $safePath = htmlspecialchars(str_replace('\\\\', '/', $localPath), ENT_QUOTES, 'UTF-8');
                return $match[1] . $match[2] . $safePath . $match[2];
              }
            }
            return $match[0];
          }

          $remoteMarkers = ['/berkasrawat/', '/radiologi/'];
          foreach ($remoteMarkers as $marker) {
            $pos = strpos($srcPath, $marker);
            if ($pos === false) {
              continue;
            }

            $relative = rawurldecode(substr($srcPath, $pos + strlen($marker)));
            $relative = ltrim(str_replace('\\\\', '/', $relative), '/');
            if ($relative === '' || strpos($relative, '..') !== false) {
              $missingRemote[] = [
                'url' => $src,
                'message' => 'Path asset remote tidak valid',
              ];
              return $match[0];
            }

            $parts = array_map('rawurlencode', explode('/', $relative));
            $canonicalUrl = rtrim(WEBAPPS_URL, '/') . $marker . implode('/', $parts);
            $download = $this->_downloadRemoteImageToLocal(
              $canonicalUrl,
              $tempDir,
              $marker === '/radiologi/' ? 'radiologi' : 'berkas'
            );

            if (!empty($download['status'])) {
              $cached++;
              $safePath = htmlspecialchars(str_replace('\\\\', '/', $download['path']), ENT_QUOTES, 'UTF-8');
              return $match[1] . $match[2] . $safePath . $match[2];
            }

            $missingRemote[] = [
              'url' => $canonicalUrl,
              'message' => isset($download['message']) ? $download['message'] : 'Gagal download asset remote',
            ];
            return $match[0];
          }

          return $match[0];
        },
        $html
      );

      if (!is_string($result)) {
        $result = $html;
      }

      // de-duplicate berdasarkan URL + message
      $dedup = [];
      foreach ($missingRemote as $item) {
        $key = (isset($item['url']) ? $item['url'] : '') . '|' . (isset($item['message']) ? $item['message'] : '');
        $dedup[$key] = $item;
      }

      return [
        'html' => $result,
        'cached' => $cached,
        'missing_remote' => array_values($dedup),
      ];
    }

    private function _downloadRemoteImageToLocal($url, $tempDir, $prefix = 'asset', $maxAttempts = 3)
    {
      if (!function_exists('curl_init')) {
        return ['status' => false, 'message' => 'cURL belum aktif di PHP', 'url' => $url];
      }
      if (!is_dir($tempDir) && !mkdir($tempDir, 0770, true) && !is_dir($tempDir)) {
        return ['status' => false, 'message' => 'Gagal membuat folder cache image', 'path' => $tempDir];
      }

      $urlPath = (string) parse_url($url, PHP_URL_PATH);
      $ext = strtolower(pathinfo($urlPath, PATHINFO_EXTENSION));
      if (!preg_match('/^(?:jpe?g|png|gif|webp|bmp)$/', $ext)) {
        $ext = 'img';
      }
      $targetPath = rtrim($tempDir, '/\\') . '/' . preg_replace('/[^A-Za-z0-9_-]/', '_', $prefix)
        . '_' . sha1($url) . '.' . $ext;

      if (is_file($targetPath) && filesize($targetPath) > 0 && @getimagesize($targetPath) !== false) {
        return ['status' => true, 'path' => $targetPath, 'url' => $url, 'cached' => true];
      }

      $lastError = '';
      $lastHttp = 0;
      for ($attempt = 1; $attempt <= max(1, (int) $maxAttempts); $attempt++) {
        $fp = @fopen($targetPath, 'w+b');
        if (!$fp) {
          return ['status' => false, 'message' => 'Gagal membuat file cache image', 'path' => $targetPath];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);
        curl_setopt($ch, CURLOPT_USERAGENT, 'mLITE Vedika PDF Asset Cache');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_ENCODING, '');

        $ok = curl_exec($ch);
        $lastHttp = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $lastError = (string) curl_error($ch);
        curl_close($ch);
        fclose($fp);

        $validHttp = $lastHttp >= 200 && $lastHttp < 300;
        $validImage = is_file($targetPath) && filesize($targetPath) > 0 && @getimagesize($targetPath) !== false;
        if ($ok && $validHttp && $validImage) {
          return [
            'status' => true,
            'message' => 'Image remote berhasil dicache',
            'path' => $targetPath,
            'url' => $url,
            'attempt' => $attempt,
            'size' => filesize($targetPath),
          ];
        }

        @unlink($targetPath);
        if ($attempt < $maxAttempts) {
          usleep(250000 * $attempt);
        }
      }

      return [
        'status' => false,
        'message' => 'Gagal download/validasi image remote setelah ' . (int) $maxAttempts . ' percobaan',
        'url' => $url,
        'http_code' => $lastHttp,
        'curl_error' => $lastError,
      ];
    }

    private function _pdfExtractStyleHTML($html)
    {
      $styles = '';
      if (preg_match_all('/<style\b[^>]*>[\s\S]*?<\/style>/i', (string) $html, $matches)) {
        $styles = implode("\n", $matches[0]);
      }
      return $styles;
    }

    private function _pdfExtractBodyHTML($html)
    {
      if (preg_match('/<body\b[^>]*>([\s\S]*)<\/body\s*>/i', (string) $html, $match)) {
        return $match[1];
      }

      $body = preg_replace('/<head\b[^>]*>[\s\S]*?<\/head>/i', '', (string) $html);
      $body = preg_replace('/<\/?html\b[^>]*>/i', '', $body);
      return $body;
    }

    private function _pdfMarkerDivBounds($body, $marker, $offset = 0)
    {
      $markerPos = strpos($body, $marker, max(0, (int) $offset));
      if ($markerPos === false) {
        return null;
      }

      $before = substr($body, 0, $markerPos);
      $start = strripos($before, '<div');
      if ($start === false) {
        return null;
      }

      $close = stripos($body, '</div>', $markerPos);
      if ($close === false) {
        return null;
      }

      return [$start, $close + 6];
    }

    private function _pdfExtractMarkedSection($body, $startMarker, $endMarker)
    {
      $startBounds = $this->_pdfMarkerDivBounds($body, $startMarker);
      $endBounds = $startBounds
        ? $this->_pdfMarkerDivBounds($body, $endMarker, $startBounds[1])
        : null;
      if (!$startBounds || !$endBounds || $endBounds[1] <= $startBounds[0]) {
        return null;
      }

      return substr($body, $startBounds[0], $endBounds[1] - $startBounds[0]);
    }

    private function _pdfBodyAfterMarkedSection($body, $startMarker, $endMarker)
    {
      $startBounds = $this->_pdfMarkerDivBounds($body, $startMarker);
      $endBounds = $startBounds
        ? $this->_pdfMarkerDivBounds($body, $endMarker, $startBounds[1])
        : null;
      if (!$endBounds) {
        return '';
      }
      return substr($body, $endBounds[1]);
    }

    private function _pdfNormalizeStandaloneBody($body)
    {
      $body = (string) $body;
      $body = preg_replace('/^\s*(?:<br\s*\/?\s*>\s*)+/i', '', $body);
      $body = preg_replace('/^\s*<pagebreak\b[^>]*\/?\s*>\s*/i', '', $body, 1);
      $body = preg_replace(
        '/<fieldset\s+style=(["\'])page-break-before\s*:\s*always\s*;?\1/i',
        '<fieldset style=$1page-break-before:auto;$1',
        $body,
        1
      );
      return $body;
    }

    private function _newVedikaMpdf($tempDir, $relaxedTables = false)
    {
      if (!is_dir($tempDir) && !mkdir($tempDir, 0770, true) && !is_dir($tempDir)) {
        throw new \RuntimeException('Gagal membuat temp mPDF: ' . $tempDir);
      }

      $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'orientation' => 'P',
        'margin_left' => 8,
        'margin_right' => 8,
        'margin_top' => 8,
        'margin_bottom' => 8,
        'tempDir' => $tempDir,
        'img_dpi' => 96,
        'jpeg_quality' => 70,
      ]);

      $mpdf->SetCompression(true);
      $mpdf->simpleTables = true;
      $mpdf->packTableData = true;
      $mpdf->shrink_tables_to_fit = 1;
      $mpdf->use_kwt = false;
      $mpdf->keep_table_proportions = !$relaxedTables;
      $mpdf->tableMinSizePriority = !$relaxedTables;
      $mpdf->setAutoTopMargin = 'stretch';
      $mpdf->setAutoBottomMargin = 'stretch';

      $rsudLogoPath = WEBAPPS_PATHX . '/TTD/logo.png';
      if (is_readable($rsudLogoPath)) {
        $mpdf->imageVars['rsud_logo'] = file_get_contents($rsudLogoPath);
      }

      $bpjsLogoPath = BASE_DIR . '/plugins/vclaim/img/bpjslogo.png';
      if (is_readable($bpjsLogoPath)) {
        $mpdf->imageVars['bpjs_logo'] = file_get_contents($bpjsLogoPath);
      }

      return $mpdf;
    }

    private function _renderStandalonePDFPart($styles, $body, $targetPath, $tempDir, $sentinel, $relaxedTables = false)
    {
      $body = $this->_pdfNormalizeStandaloneBody($body);
      $html = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" />'
        . $styles
        . '</head><body>'
        . $body
        . '</body></html>';
      $html = $this->_cleanHTMLForMpdf($html);

      try {
        $mpdf = $this->_newVedikaMpdf($tempDir, $relaxedTables);
        $mpdf->WriteHTML($html);
        $mpdf->Output($targetPath, \Mpdf\Output\Destination::FILE);
        unset($mpdf);
      } catch (\Throwable $e) {
        return [
          'status' => false,
          'message' => $e->getMessage(),
          'path' => $targetPath
        ];
      }

      if (!is_file($targetPath) || filesize($targetPath) <= 0) {
        return [
          'status' => false,
          'message' => 'PDF bagian tidak terbentuk',
          'path' => $targetPath
        ];
      }

      $verified = $sentinel !== '' ? $this->_pdfContainsRenderSentinel($targetPath, $sentinel) : null;
      if ($verified === false) {
        return [
          'status' => false,
          'message' => 'Render bagian berhenti sebelum marker akhir: ' . $sentinel,
          'path' => $targetPath,
          'size' => filesize($targetPath),
          'verified' => false
        ];
      }

      return [
        'status' => true,
        'message' => $verified === true ? 'Bagian terverifikasi' : 'Bagian berhasil dirender; txtwrite tidak tersedia',
        'path' => $targetPath,
        'size' => filesize($targetPath),
        'verified' => $verified
      ];
    }

    private function _splitLargePDFTableChunk($tableHtml, $maxRows = 24, $maxBytes = 90000)
    {
      $tableHtml = (string) $tableHtml;
      if (strlen($tableHtml) <= $maxBytes) {
        return [$tableHtml];
      }

      if (!preg_match('/^(\s*<table\b[^>]*>)([\s\S]*?)(<\/table>\s*)$/i', $tableHtml, $tableMatch)) {
        return [$tableHtml];
      }

      if (!preg_match('/<tbody\b[^>]*>([\s\S]*?)<\/tbody>/i', $tableMatch[2], $tbodyMatch, PREG_OFFSET_CAPTURE)) {
        return [$tableHtml];
      }

      $tbodyFull = $tbodyMatch[0][0];
      $tbodyPos = $tbodyMatch[0][1];
      $tbodyInner = $tbodyMatch[1][0];
      if (!preg_match_all('/<tr\b[^>]*>[\s\S]*?<\/tr>/i', $tbodyInner, $rowMatches) || count($rowMatches[0]) <= $maxRows) {
        return [$tableHtml];
      }

      $prefixInner = substr($tableMatch[2], 0, $tbodyPos);
      $suffixInner = substr($tableMatch[2], $tbodyPos + strlen($tbodyFull));
      $tbodyOpen = '<tbody>';
      if (preg_match('/^<tbody\b[^>]*>/i', $tbodyFull, $openMatch)) {
        $tbodyOpen = $openMatch[0];
      }

      $parts = [];
      $currentRows = [];
      $currentBytes = 0;
      foreach ($rowMatches[0] as $row) {
        $rowBytes = strlen($row);
        if ($currentRows && (count($currentRows) >= $maxRows || ($currentBytes + $rowBytes) > $maxBytes)) {
          $parts[] = $tableMatch[1] . $prefixInner . $tbodyOpen . implode('', $currentRows) . '</tbody>' . $suffixInner . $tableMatch[3];
          $currentRows = [];
          $currentBytes = 0;
        }
        $currentRows[] = $row;
        $currentBytes += $rowBytes;
      }
      if ($currentRows) {
        $parts[] = $tableMatch[1] . $prefixInner . $tbodyOpen . implode('', $currentRows) . '</tbody>' . $suffixInner . $tableMatch[3];
      }

      return $parts ?: [$tableHtml];
    }

    private function _buildResumeRenderChunks($resumeBody)
    {
      $top = $this->_splitPDFHTMLTopLevelChunks((string) $resumeBody);
      $result = [];

      foreach ($top as $chunk) {
        if (trim($chunk) === '' || strpos($chunk, 'VEDIKA_DOC_RESUME_START') !== false || strpos($chunk, 'VEDIKA_DOC_RESUME</') !== false) {
          continue;
        }
        if (preg_match('/^\s*<pagebreak\b/i', $chunk)) {
          continue;
        }

        if ($this->_pdfHTMLChunkHasClass($chunk, 'div', 'resume-document')) {
          $inner = $this->_pdfHTMLOuterTagInner($chunk, 'div');
          if ($inner === null) {
            $result[] = $chunk;
            continue;
          }

          $resumeChunks = $this->_splitPDFHTMLTopLevelChunks($inner);
          foreach ($resumeChunks as $resumeChunk) {
            if (trim($resumeChunk) === '') {
              continue;
            }

            if ($this->_pdfHTMLChunkHasClass($resumeChunk, 'div', 'resume-section')) {
              $sectionInner = $this->_pdfHTMLOuterTagInner($resumeChunk, 'div');
              if ($sectionInner === null) {
                $tableParts = preg_match('/^\s*<table\b/i', $resumeChunk)
              ? $this->_splitLargePDFTableChunk($resumeChunk)
              : [$resumeChunk];
            foreach ($tableParts as $tablePart) {
              $result[] = '<div class="resume-document">' . $tablePart . '</div>';
            }
                continue;
              }

              $sectionChunks = $this->_splitPDFHTMLTopLevelChunks($sectionInner);
              if (!$sectionChunks) {
                $result[] = '<div class="resume-document"><div class="resume-section">'
                  . $sectionInner . '</div></div>';
                continue;
              }

              foreach ($sectionChunks as $sectionChunk) {
                if (trim($sectionChunk) === '') {
                  continue;
                }
                $tableParts = preg_match('/^\s*<table\b/i', $sectionChunk)
                  ? $this->_splitLargePDFTableChunk($sectionChunk)
                  : [$sectionChunk];
                foreach ($tableParts as $tablePart) {
                  $result[] = '<div class="resume-document"><div class="resume-section">'
                    . $tablePart . '</div></div>';
                }
              }
              continue;
            }

            $result[] = '<div class="resume-document">' . $resumeChunk . '</div>';
          }
          continue;
        }

        $result[] = $chunk;
      }

      return $result;
    }

    private function _groupPDFHTMLChunks($chunks, $maxBytes = 160000, $maxItems = 8)
    {
      $groups = [];
      $current = [];
      $bytes = 0;

      foreach ($chunks as $chunk) {
        $chunkBytes = strlen($chunk);
        if ($current && (count($current) >= $maxItems || ($bytes + $chunkBytes) > $maxBytes)) {
          $groups[] = implode("\n", $current);
          $current = [];
          $bytes = 0;
        }
        $current[] = $chunk;
        $bytes += $chunkBytes;
      }

      if ($current) {
        $groups[] = implode("\n", $current);
      }

      return $groups;
    }

    private function _renderResumePDFParts($styles, $resumeBody, $workDir, $jobId, $no_rawat, $nosep)
    {
      $chunks = $this->_buildResumeRenderChunks($resumeBody);
      if (!$chunks) {
        return ['status' => false, 'message' => 'Resume tidak menghasilkan chunk render', 'files' => []];
      }

      $groups = $this->_groupPDFHTMLChunks($chunks, 160000, 8);
      $files = [];
      $total = count($groups);

      foreach ($groups as $index => $groupBody) {
        $number = $index + 1;
        $sentinel = 'VEDIKA_RESUME_CHUNK_' . str_pad((string) $number, 3, '0', STR_PAD_LEFT)
          . '_' . strtoupper(substr(sha1($no_rawat . '|' . $nosep . '|' . $number), 0, 12));
        $body = $groupBody
          . '<div style="font-size:1px;line-height:1px;color:#ffffff;">'
          . $sentinel . '</div>';

        if ($number === $total) {
          $body .= '<div style="font-size:1px;line-height:1px;color:#ffffff;">VEDIKA_DOC_RESUME</div>';
        }

        $this->_touchPDFQueueStage(
          $jobId,
          'Tahap 6/9: render Resume chunk ' . $number . '/' . $total
        );

        $target = $workDir . '/resume_' . str_pad((string) $number, 3, '0', STR_PAD_LEFT) . '.pdf';
        $temp = $workDir . '/mpdf_resume_' . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
        $render = $this->_renderStandalonePDFPart($styles, $body, $target, $temp, $sentinel, true);
        if (empty($render['status'])) {
          $this->_updatePDFManifestRow(
            $jobId, $no_rawat, $nosep, 'RESUME', false,
            'Gagal render Resume chunk ' . $number . '/' . $total . ': ' . $render['message'],
            $total
          );
          return [
            'status' => false,
            'message' => 'Gagal render Resume chunk ' . $number . '/' . $total . ': ' . $render['message'],
            'files' => $files,
            'chunk_count' => $total
          ];
        }
        $files[] = $target;
      }

      $this->_updatePDFManifestRow(
        $jobId, $no_rawat, $nosep, 'RESUME', false,
        $total . ' chunk Resume berhasil dirender; menunggu merge final',
        $total
      );

      return [
        'status' => true,
        'message' => 'Resume berhasil dirender dalam ' . $total . ' chunk',
        'files' => $files,
        'chunk_count' => $total
      ];
    }

    private function _unwrapPDFExtraFieldset($html)
    {
      $html = trim((string) $html);
      if (preg_match('/^<fieldset\b[^>]*>([\s\S]*)<\/fieldset>\s*$/i', $html, $match)) {
        return trim($match[1]);
      }
      return $html;
    }

    private function _unwrapPDFExtraContainer($html)
    {
      $html = trim((string) $html);

      // Legacy extra document.
      $unwrapped = $this->_unwrapPDFExtraFieldset($html);
      if ($unwrapped !== $html) {
        return $unwrapped;
      }

      // Template baru membungkus dokumen kondisional dengan
      // <div class="vedika-extra-document">. Fallback lama tidak membuka
      // wrapper ini sehingga seluruh KFR tetap dianggap satu chunk (1/1).
      if (preg_match('/^<div\\b([^>]*)>([\\s\\S]*)<\\/div>\\s*$/i', $html, $match)) {
        $attrs = isset($match[1]) ? $match[1] : '';
        if (preg_match('/\\bclass\\s*=\\s*(["\\\'])([^"\\\']*)\\1/i', $attrs, $classMatch)) {
          $classes = preg_split('/\\s+/', trim($classMatch[2]));
          if (in_array('vedika-extra-document', $classes, true)) {
            return trim($match[2]);
          }
        }
      }

      return $html;
    }

    private function _stripVedikaExtraTemplateMarkers($html)
    {
      return preg_replace(
        '/<div\\b[^>]*>\\s*VEDIKA_EXTRA_[A-Z0-9_]+\\s*<\\/div>/i',
        '',
        (string) $html
      );
    }

    private function _buildExtraRenderDocuments($extrasBody)
    {
      $chunks = $this->_splitPDFHTMLTopLevelChunks((string) $extrasBody);
      $documents = [];

      foreach ($chunks as $chunk) {
        $chunk = trim((string) $chunk);
        if ($chunk === '') {
          continue;
        }

        // BR/pagebreak/comment di antara dokumen tidak perlu dirender sendiri.
        if (preg_match('/^(?:<br\s*\/?\s*>\s*)+$/i', $chunk) || preg_match('/^<pagebreak\b/i', $chunk)) {
          continue;
        }

        $plain = trim(strip_tags($chunk));
        if ($plain === '' && !preg_match('/<(fieldset|table|img|barcode|div)\b/i', $chunk)) {
          continue;
        }

        // Karena setiap dokumen nanti menjadi PDF sendiri, page-break-before pada
        // fieldset tidak dibutuhkan. Melepas wrapper juga menghindari mPDF
        // berhenti pada fieldset kompleks/malformed sebelum sentinel akhir.
        $documents[] = $this->_unwrapPDFExtraFieldset($chunk);
      }

      return $documents;
    }

    private function _renderExtraPDFDocumentFallback($styles, $documentBody, $workDir, $jobId, $no_rawat, $docNumber, $docTotal)
    {
      /*
       * Fallback ini sengaja menulis potongan HTML SATU PER SATU ke instance
       * mPDF yang sama. Dengan begitu KFR tetap menjadi satu dokumen/page flow,
       * tetapi parser tidak harus menelan satu blok HTML besar sekaligus.
       *
       * Bug sebelumnya: template baru memakai wrapper
       * <div class="vedika-extra-document">. Fallback hanya membuka fieldset,
       * sehingga KFR tetap satu chunk dan log selalu menunjukkan bagian 1/1.
       */
      $documentBody = $this->_unwrapPDFExtraContainer($documentBody);
      $documentBody = $this->_stripVedikaExtraTemplateMarkers($documentBody);
      $topChunks = $this->_splitPDFHTMLTopLevelChunks($documentBody);
      $smallChunks = [];

      foreach ($topChunks as $chunk) {
        $chunk = trim((string) $chunk);
        if ($chunk === '' || preg_match('/^<pagebreak\\b/i', $chunk)) {
          continue;
        }

        // Jangan jadikan marker internal template sebagai dokumen/chunk.
        $plain = trim(strip_tags($chunk));
        if ($plain !== '' && preg_match('/^VEDIKA_EXTRA_[A-Z0-9_]+$/i', $plain)) {
          continue;
        }

        if (preg_match('/^<table\\b/i', $chunk)) {
          foreach ($this->_splitLargePDFTableChunk($chunk, 12, 45000) as $tablePart) {
            if (trim($tablePart) !== '') {
              $smallChunks[] = $tablePart;
            }
          }
          continue;
        }

        $smallChunks[] = $chunk;
      }

      if (!$smallChunks) {
        return ['status' => false, 'message' => 'Fallback tidak menghasilkan potongan HTML', 'files' => []];
      }

      $target = $workDir . '/extra_' . str_pad((string) $docNumber, 3, '0', STR_PAD_LEFT)
        . '_chunked.pdf';
      $temp = $workDir . '/mpdf_extra_' . str_pad((string) $docNumber, 3, '0', STR_PAD_LEFT)
        . '_chunked';
      $sentinel = 'VEDIKA_EXTRA_DOC_'
        . str_pad((string) $docNumber, 3, '0', STR_PAD_LEFT)
        . '_CHUNKED_' . strtoupper(substr(sha1($no_rawat . '|extra-doc-chunked|' . $docNumber), 0, 12));

      try {
        $mpdf = $this->_newVedikaMpdf($temp, true);

        // CSS ditulis sekali, lalu setiap blok dokumen ditulis terpisah.
        if (trim((string) $styles) !== '') {
          $mpdf->WriteHTML((string) $styles);
        }

        $chunkTotal = count($smallChunks);
        foreach ($smallChunks as $chunkIndex => $chunk) {
          $chunkNumber = $chunkIndex + 1;
          $this->_touchPDFQueueStage(
            $jobId,
            'Tahap 7/9: fallback chunked dokumen kondisional ' . $docNumber . '/' . $docTotal
            . ' blok ' . $chunkNumber . '/' . $chunkTotal
          );
          $mpdf->WriteHTML($this->_pdfNormalizeStandaloneBody($chunk));
        }

        // Marker dibuat sedikit lebih besar agar Ghostscript txtwrite tidak
        // mengabaikannya, tetapi tetap putih sehingga tidak terlihat pengguna.
        $mpdf->WriteHTML(
          '<div style="font-size:6px;line-height:6px;color:#ffffff;">'
          . $sentinel . '</div>'
        );
        $mpdf->Output($target, \Mpdf\Output\Destination::FILE);
        unset($mpdf);
      } catch (\Throwable $e) {
        return [
          'status' => false,
          'message' => 'Fallback chunked exception: ' . $e->getMessage(),
          'files' => []
        ];
      }

      if (!is_file($target) || filesize($target) <= 0) {
        return ['status' => false, 'message' => 'Fallback chunked tidak membentuk PDF', 'files' => []];
      }

      $verified = $this->_pdfContainsRenderSentinel($target, $sentinel);
      if ($verified === false) {
        // Sentinel boleh hilang dari txtwrite. Cek isi nyata dokumen lengkap.
        $tailVerified = $this->_pdfVerifyHtmlTailRendered($target, $documentBody);
        if ($tailVerified !== true) {
          $pdfText = $this->_pdfExtractVerificationText($target);
          $tailInfo = '';
          if (is_string($pdfText) && trim($pdfText) !== '') {
            $normalized = $this->_pdfNormalizeVerificationText($pdfText);
            if (strlen($normalized) > 180) {
              $normalized = substr($normalized, -180);
            }
            $tailInfo = '; txt-tail=' . $normalized;
          }
          return [
            'status' => false,
            'message' => 'Fallback chunked selesai menulis ' . count($smallChunks)
              . ' blok tetapi verifikasi akhir belum lolos' . $tailInfo,
            'files' => []
          ];
        }
      }

      return [
        'status' => true,
        'message' => 'Fallback chunked berhasil menulis ' . count($smallChunks) . ' blok dalam satu PDF',
        'files' => [$target]
      ];
    }

    private function _renderExtraPDFParts($styles, $extrasBody, $workDir, $jobId, $no_rawat, $nosep)
    {
      $extrasBody = trim((string) $extrasBody);
      if ($extrasBody === '') {
        return ['status' => true, 'message' => 'Tidak ada dokumen HTML kondisional', 'files' => [], 'chunk_count' => 0];
      }

      // Jangan lagi menggabungkan beberapa dokumen kondisional menjadi satu
      // HTML besar. Setiap fieldset/dokumen dirender dengan mPDF fresh.
      $documents = $this->_buildExtraRenderDocuments($extrasBody);
      if (!$documents) {
        return ['status' => true, 'message' => 'Tidak ada dokumen HTML kondisional', 'files' => [], 'chunk_count' => 0];
      }

      $files = [];
      $total = count($documents);
      $renderedParts = 0;

      foreach ($documents as $index => $documentBody) {
        $number = $index + 1;
        $sentinel = 'VEDIKA_EXTRA_DOC_' . str_pad((string) $number, 3, '0', STR_PAD_LEFT)
          . '_' . strtoupper(substr(sha1($no_rawat . '|extra-doc|' . $number), 0, 12));
        $body = $documentBody
          . '<div style="font-size:1px;line-height:1px;color:#ffffff;">'
          . $sentinel . '</div>';

        $this->_touchPDFQueueStage(
          $jobId,
          'Tahap 7/9: render dokumen kondisional ' . $number . '/' . $total
        );

        $target = $workDir . '/extra_' . str_pad((string) $number, 3, '0', STR_PAD_LEFT) . '.pdf';
        $temp = $workDir . '/mpdf_extra_' . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
        $render = $this->_renderStandalonePDFPart($styles, $body, $target, $temp, $sentinel, true);

        if (empty($render['status'])) {
          // Beberapa template legacy (terutama KFR/rehab) dapat merender isi
          // lengkap tetapi marker HTML 1px tidak ikut terbaca oleh Ghostscript
          // txtwrite. Sebelum memecah ulang, verifikasi frasa nyata dari ekor
          // dokumen. Jika ekor ada, PDF dianggap lengkap dan aman diteruskan.
          $tailVerified = $this->_pdfVerifyHtmlTailRendered($target, $documentBody);
          if ($tailVerified === true) {
            $this->_touchPDFQueueStage(
              $jobId,
              'Tahap 7/9: dokumen kondisional ' . $number . '/' . $total
              . ' lengkap (verifikasi isi akhir)'
            );
            $files[] = $target;
            $renderedParts++;
            continue;
          }

          // Satu dokumen tertentu masih terlalu kompleks/panjang. Pecah hanya
          // dokumen tersebut; dokumen kondisional lain tidak perlu diulang.
          @unlink($target);
          $fallback = $this->_renderExtraPDFDocumentFallback(
            $styles, $documentBody, $workDir, $jobId, $no_rawat, $number, $total
          );
          if (empty($fallback['status'])) {
            return [
              'status' => false,
              'message' => 'Gagal render dokumen kondisional ' . $number . '/' . $total
                . '. Render awal: ' . $render['message'] . '; ' . $fallback['message'],
              'files' => $files,
              'chunk_count' => $total,
              'failed_document' => $number
            ];
          }
          foreach ($fallback['files'] as $fallbackFile) {
            $files[] = $fallbackFile;
            $renderedParts++;
          }
          continue;
        }

        $files[] = $target;
        $renderedParts++;
      }

      return [
        'status' => true,
        'message' => 'Dokumen kondisional berhasil dirender: ' . $total
          . ' dokumen menjadi ' . $renderedParts . ' bagian PDF',
        'files' => $files,
        'chunk_count' => $renderedParts,
        'document_count' => $total
      ];
    }

    private function _createPDFKlaimFile($no_rawat, $jobId = null)
    {
      $id = $this->convertNorawat($no_rawat);
      $vedika = $this->db('mlite_vedika')->where('no_rawat', $no_rawat)->oneArray();

      if (!$vedika) {
        return ['status' => false, 'message' => 'Data Vedika tidak ditemukan', 'no_rawat' => $no_rawat];
      }
      if ($vedika['status'] != 'Pengajuan') {
        return ['status' => false, 'message' => 'Status klaim belum Lengkap', 'no_rawat' => $no_rawat];
      }

      $nosep = isset($vedika['nosep']) ? trim((string) $vedika['nosep']) : trim((string) $this->_getSEPInfo('no_sep', $no_rawat));
      $safeSep = preg_replace('/[^A-Za-z0-9_\-]/', '', $nosep);
      if ($safeSep === '') {
        return ['status' => false, 'message' => 'Nomor SEP kosong/tidak valid untuk nama PDF klaim', 'no_rawat' => $no_rawat];
      }

      $this->_touchPDFQueueStage($jobId, 'Tahap 1/9: menyiapkan manifest dan data pasien');
      $manifestInit = $this->_initializePDFManifestCore($jobId, $no_rawat, $nosep);
      if (empty($manifestInit['status'])) {
        return [
          'status' => false,
          'message' => $manifestInit['message'],
          'no_rawat' => $no_rawat,
          'nosep' => $nosep,
          'manifest' => $this->_getPDFManifestRows($jobId, $no_rawat, $nosep)
        ];
      }

      $this->_touchPDFQueueStage($jobId, 'Tahap 2/9: render template sumber');
      $templateRenderLock = 'vedika_pdf_template_render';
      if (!$this->_acquirePDFQueueLock($templateRenderLock, 60)) {
        return [
          'status' => false,
          'message' => 'Timeout menunggu lock render template PDF',
          'no_rawat' => $no_rawat,
          'nosep' => $nosep
        ];
      }

      try {
        $html = $this->_renderPDFKlaimGenerateHTML($id);
      } finally {
        $this->_releasePDFQueueLock($templateRenderLock);
      }

      $hasExpectedIdentity = ($nosep !== '' && strpos($html, $nosep) !== false) || strpos($html, $no_rawat) !== false;
      if (!$hasExpectedIdentity) {
        return [
          'status' => false,
          'message' => 'Render PDF tidak memuat identitas pasien/SEP yang sedang diproses',
          'no_rawat' => $no_rawat,
          'nosep' => $nosep,
          'manifest' => $this->_getPDFManifestRows($jobId, $no_rawat, $nosep)
        ];
      }

      $html = $this->_preparePDFKlaimRenderedHTML($html);
      $styles = $this->_pdfExtractStyleHTML($html);
      $body = $this->_pdfExtractBodyHTML($html);

      // Sanitasi global free-text hasil render. Ini membuat karakter seperti
      // <, > dan & aman untuk mPDF tanpa escape manual di setiap field template.
      $body = $this->_pdfSanitizeRenderedBodySpecialChars($body);

      $requiredSections = [
        'SEP' => ['VEDIKA_DOC_SEP_START', 'VEDIKA_DOC_SEP'],
        'BILLING' => ['VEDIKA_DOC_BILLING_START', 'VEDIKA_DOC_BILLING'],
        'RESUME' => ['VEDIKA_DOC_RESUME_START', 'VEDIKA_DOC_RESUME'],
      ];
      $sectionBodies = [];
      foreach ($requiredSections as $code => $markers) {
        $sectionBodies[$code] = $this->_pdfExtractMarkedSection($body, $markers[0], $markers[1]);
        if ($sectionBodies[$code] === null) {
          $this->_updatePDFManifestRow(
            $jobId, $no_rawat, $nosep, $code, false,
            'Marker start/end bagian wajib tidak lengkap pada HTML sumber'
          );
          return [
            'status' => false,
            'message' => 'Bagian wajib ' . $code . ' tidak dapat dipisahkan dari HTML sumber',
            'no_rawat' => $no_rawat,
            'nosep' => $nosep,
            'manifest' => $this->_getPDFManifestRows($jobId, $no_rawat, $nosep)
          ];
        }
        $this->_updatePDFManifestRow(
          $jobId, $no_rawat, $nosep, $code, false,
          'Sumber HTML bagian tersedia; menunggu render per dokumen'
        );
      }
      $extrasBody = $this->_pdfBodyAfterMarkedSection($body, 'VEDIKA_DOC_RESUME_START', 'VEDIKA_DOC_RESUME');

      $dir = WEBAPPS_PATHX . '/berkasrawat/pages/upload/klaim';
      if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return ['status' => false, 'message' => 'Folder output klaim tidak dapat dibuat', 'path' => $dir];
      }

      $filename = $safeSep . '.pdf';
      $fullPath = $dir . '/' . $filename;
      $lokasi_file = 'pages/upload/klaim/' . $filename;
      $newFinalPath = $dir . '/new_' . getmypid() . '_' . $filename;

      $jobTempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . 'mlite-vedika-pdf'
        . DIRECTORY_SEPARATOR . sha1($no_rawat . '|' . $nosep . '|' . getmypid() . '|' . microtime(true));
      if (!is_dir($jobTempDir) && !mkdir($jobTempDir, 0770, true) && !is_dir($jobTempDir)) {
        return ['status' => false, 'message' => 'Gagal membuat temp directory PDF yang terisolasi', 'path' => $jobTempDir];
      }

      try {
        $mergeFiles = [];
        $skippedFiles = [];
        $coreVerify = [];

        // Asset berkas digital/radiologi berada di server WEBAPPS_URL, bukan di
        // filesystem mLITE ini. Cache remote terlebih dahulu dengan retry lalu
        // rewrite src ke temp lokal agar mPDF tidak melakukan fetch HTTP sendiri.
        $remoteAssetDir = $jobTempDir . '/remote_assets';
        $remoteAssetFailures = [];
        foreach ($sectionBodies as $sectionCode => $sectionBody) {
          $assetCache = $this->_pdfCacheRemoteWebappsImageSources($sectionBody, $remoteAssetDir);
          $sectionBodies[$sectionCode] = $assetCache['html'];
          if (!empty($assetCache['missing_remote'])) {
            foreach ($assetCache['missing_remote'] as $failure) {
              $failure['section'] = $sectionCode;
              $remoteAssetFailures[] = $failure;
            }
          }
        }
        $extraAssetCache = $this->_pdfCacheRemoteWebappsImageSources($extrasBody, $remoteAssetDir);
        $extrasBody = $extraAssetCache['html'];
        if (!empty($extraAssetCache['missing_remote'])) {
          foreach ($extraAssetCache['missing_remote'] as $failure) {
            $failure['section'] = 'EXTRA';
            $remoteAssetFailures[] = $failure;
          }
        }
        if ($remoteAssetFailures) {
          $sample = [];
          foreach (array_slice($remoteAssetFailures, 0, 5) as $failure) {
            $sample[] = (isset($failure['url']) ? $failure['url'] : '')
              . (isset($failure['message']) ? ' (' . $failure['message'] . ')' : '');
          }
          return [
            'status' => false,
            'message' => 'Berkas remote belum berhasil diambil lengkap; job akan retry agar PDF tidak kehilangan lampiran: '
              . implode('; ', $sample),
            'no_rawat' => $no_rawat,
            'nosep' => $nosep,
            'remote_asset_failures' => $remoteAssetFailures,
          ];
        }

        // 1. INACBG
        $this->_touchPDFQueueStage($jobId, 'Tahap 3/9: menarik PDF INACBG dari e-Klaim');
        $inacbgPdfPath = $jobTempDir . '/01_inacbg.pdf';
        $inacbgResult = $this->_saveKlaimInacbgPDF($nosep, $inacbgPdfPath);
        if (empty($inacbgResult['status'])) {
          $message = isset($inacbgResult['message']) ? $inacbgResult['message'] : 'PDF INACBG tidak tersedia';
          $this->_updatePDFManifestRow($jobId, $no_rawat, $nosep, 'INACBG', false, 'Gagal mengambil PDF INACBG: ' . $message, 0);
          return [
            'status' => false,
            'message' => 'PDF INACBG wajib tetapi gagal diambil: ' . $message,
            'no_rawat' => $no_rawat,
            'nosep' => $nosep,
            'manifest' => $this->_getPDFManifestRows($jobId, $no_rawat, $nosep)
          ];
        }
        $mergeFiles[] = $inacbgPdfPath;
        $this->_updatePDFManifestRow($jobId, $no_rawat, $nosep, 'INACBG', false, 'PDF INACBG siap; menunggu merge final');

        // 2. SEP
        $this->_touchPDFQueueStage($jobId, 'Tahap 4/9: render SEP');
        $sepPath = $jobTempDir . '/02_sep.pdf';
        $sepRender = $this->_renderStandalonePDFPart(
          $styles, $sectionBodies['SEP'], $sepPath, $jobTempDir . '/mpdf_sep', 'VEDIKA_DOC_SEP', false
        );
        if (empty($sepRender['status'])) {
          $this->_updatePDFManifestRow($jobId, $no_rawat, $nosep, 'SEP', false, 'Gagal render SEP: ' . $sepRender['message']);
          return [
            'status' => false,
            'message' => 'Gagal render SEP: ' . $sepRender['message'],
            'no_rawat' => $no_rawat,
            'manifest' => $this->_getPDFManifestRows($jobId, $no_rawat, $nosep)
          ];
        }
        $mergeFiles[] = $sepPath;
        $coreVerify['SEP'] = $sepRender['verified'];
        $this->_updatePDFManifestRow($jobId, $no_rawat, $nosep, 'SEP', false, 'SEP berhasil dirender terpisah; menunggu merge final');

        // 3. Billing
        $this->_touchPDFQueueStage($jobId, 'Tahap 5/9: render Billing');
        $billingPath = $jobTempDir . '/03_billing.pdf';
        $billingRender = $this->_renderStandalonePDFPart(
          $styles, $sectionBodies['BILLING'], $billingPath, $jobTempDir . '/mpdf_billing', 'VEDIKA_DOC_BILLING', false
        );
        if (empty($billingRender['status'])) {
          $this->_updatePDFManifestRow($jobId, $no_rawat, $nosep, 'BILLING', false, 'Gagal render Billing: ' . $billingRender['message']);
          return [
            'status' => false,
            'message' => 'Gagal render Billing: ' . $billingRender['message'],
            'no_rawat' => $no_rawat,
            'manifest' => $this->_getPDFManifestRows($jobId, $no_rawat, $nosep)
          ];
        }
        $mergeFiles[] = $billingPath;
        $coreVerify['BILLING'] = $billingRender['verified'];
        $this->_updatePDFManifestRow($jobId, $no_rawat, $nosep, 'BILLING', false, 'Billing berhasil dirender terpisah; menunggu merge final');

        // 4. Resume: selalu dipecah menjadi beberapa kelompok kecil dengan mPDF fresh.
        $resumeRender = $this->_renderResumePDFParts(
          $styles, $sectionBodies['RESUME'], $jobTempDir, $jobId, $no_rawat, $nosep
        );
        if (empty($resumeRender['status'])) {
          return [
            'status' => false,
            'message' => $resumeRender['message'],
            'no_rawat' => $no_rawat,
            'nosep' => $nosep,
            'render_mode' => 'per_document_resume_chunked',
            'manifest' => $this->_getPDFManifestRows($jobId, $no_rawat, $nosep)
          ];
        }
        foreach ($resumeRender['files'] as $resumeFile) {
          $mergeFiles[] = $resumeFile;
        }
        $coreVerify['RESUME'] = true;

        // 5. Dokumen kondisional HTML setelah Resume tetap dipertahankan, tetapi dirender terpisah.
        $extraRender = $this->_renderExtraPDFParts(
          $styles, $extrasBody, $jobTempDir, $jobId, $no_rawat, $nosep
        );
        if (empty($extraRender['status'])) {
          return [
            'status' => false,
            'message' => $extraRender['message'],
            'no_rawat' => $no_rawat,
            'nosep' => $nosep,
            'render_mode' => 'per_document_resume_chunked',
            'manifest' => $this->_getPDFManifestRows($jobId, $no_rawat, $nosep)
          ];
        }
        foreach ($extraRender['files'] as $extraFile) {
          $mergeFiles[] = $extraFile;
        }

        // 6. Berkas upload PDF tambahan, selain SEP(001) dan KLM.
        $this->_touchPDFQueueStage($jobId, 'Tahap 7b/9: menyiapkan berkas upload PDF');
        $berkasDigitalPdf = $this->db('berkas_digital_perawatan')
          ->where('no_rawat', $no_rawat)
          ->where('kode', '!=', '001')
          ->where('kode', '!=', 'KLM')
          ->like('lokasi_file', '%pdf')
          ->toArray();

        foreach ($berkasDigitalPdf as $berkas) {
          // Selain KLM, berkas upload adalah canonical di server WEBAPPS_URL.
          // Jangan memakai copy lokal yang mungkin tidak ada/stale.
          $remoteResult = $this->_downloadRemotePDFToLocal(
            $berkas['lokasi_file'],
            $jobTempDir,
            'remote_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $berkas['kode'])
          );
          if (empty($remoteResult['status'])) {
            return [
              'status' => false,
              'message' => 'Berkas PDF upload remote gagal diambil; PDF klaim tidak dipublish agar tidak lengkap: '
                . (isset($berkas['lokasi_file']) ? $berkas['lokasi_file'] : '')
                . ' - ' . (isset($remoteResult['message']) ? $remoteResult['message'] : 'download gagal'),
              'no_rawat' => $no_rawat,
              'nosep' => $nosep,
              'remote_pdf' => $remoteResult,
            ];
          }
          $mergeFiles[] = $remoteResult['path'];
        }

        // 7. Merge final.
        $this->_touchPDFQueueStage($jobId, 'Tahap 8/9: merge dan validasi PDF final');
        $mergeResult = $this->_mergeCompressPDFs($mergeFiles, $newFinalPath);
        if (empty($mergeResult['status'])) {
          return [
            'status' => false,
            'message' => 'Gagal merge PDF final',
            'no_rawat' => $no_rawat,
            'merge' => $mergeResult,
            'skipped_files' => $skippedFiles
          ];
        }

        if (!is_file($newFinalPath) || filesize($newFinalPath) <= 0) {
          return ['status' => false, 'message' => 'File hasil merge tidak terbentuk', 'path' => $newFinalPath];
        }

        // Sanitasi final untuk validator upload BPJS. Ghostscript dapat tetap
        // mempertahankan OpenAction non-JavaScript (mis. initial page/zoom),
        // sementara validator BPJS menolak token /OpenAction secara mutlak.
        $this->_touchPDFQueueStage($jobId, 'Tahap 8b/9: sanitasi PDF final untuk BPJS');
        $bpjsSafe = $this->_sanitizePDFForBPJS($newFinalPath);
        if (empty($bpjsSafe['status'])) {
          return [
            'status' => false,
            'message' => 'PDF final gagal sanitasi BPJS: ' . $bpjsSafe['message'],
            'path' => $newFinalPath,
            'bpjs_sanitize' => $bpjsSafe
          ];
        }

        $pdfHeader = file_get_contents($newFinalPath, false, null, 0, 5);
        $pdfSize = filesize($newFinalPath);
        $tailLength = min(4096, $pdfSize);
        $fh = fopen($newFinalPath, 'rb');
        $pdfTail = '';
        if ($fh) {
          if ($tailLength > 0) {
            fseek($fh, -$tailLength, SEEK_END);
            $pdfTail = fread($fh, $tailLength);
          }
          fclose($fh);
        }
        if ($pdfHeader !== '%PDF-' || strpos($pdfTail, '%%EOF') === false) {
          return [
            'status' => false,
            'message' => 'PDF final tidak lolos validasi struktur dasar',
            'path' => $newFinalPath,
            'size' => $pdfSize
          ];
        }

        if (!@rename($newFinalPath, $fullPath)) {
          return [
            'status' => false,
            'message' => 'Gagal publish PDF final secara atomic',
            'source' => $newFinalPath,
            'target' => $fullPath
          ];
        }

        // 8. Register KLM dan finalisasi manifest.
        $this->_touchPDFQueueStage($jobId, 'Tahap 9/9: registrasi KLM dan finalisasi manifest');
        $register = $this->_registerPDFKlaim($no_rawat, $lokasi_file);
        if (!$register) {
          return [
            'status' => false,
            'message' => 'PDF dibuat, tetapi gagal register ke database',
            'no_rawat' => $no_rawat,
            'file' => $lokasi_file,
            'path' => $fullPath,
            'manifest' => $this->_getPDFManifestRows($jobId, $no_rawat, $nosep)
          ];
        }

        $this->_updatePDFManifestRow($jobId, $no_rawat, $nosep, 'INACBG', true, 'Berhasil digabung ke PDF final');
        $this->_updatePDFManifestRow(
          $jobId, $no_rawat, $nosep, 'SEP', true,
          $coreVerify['SEP'] === true ? 'Ter-verifikasi dan berhasil digabung ke PDF final' : 'Berhasil digabung ke PDF final'
        );
        $this->_updatePDFManifestRow(
          $jobId, $no_rawat, $nosep, 'BILLING', true,
          $coreVerify['BILLING'] === true ? 'Ter-verifikasi dan berhasil digabung ke PDF final' : 'Berhasil digabung ke PDF final'
        );
        $this->_updatePDFManifestRow(
          $jobId, $no_rawat, $nosep, 'RESUME', true,
          'Resume berhasil digabung ke PDF final dalam ' . (int) $resumeRender['chunk_count'] . ' chunk',
          (int) $resumeRender['chunk_count']
        );

        return [
          'status' => true,
          'message' => 'PDF klaim berhasil dibuat dengan render per dokumen',
          'no_rawat' => $no_rawat,
          'nosep' => $nosep,
          'file' => $lokasi_file,
          'url' => url(WEBAPPS_URLX) . '/berkasrawat/' . $lokasi_file,
          'path' => $fullPath,
          'merge' => $mergeResult,
          'render_mode' => 'per_document_resume_chunked',
          'resume_chunks' => (int) $resumeRender['chunk_count'],
          'extra_chunks' => (int) $extraRender['chunk_count'],
          'skipped_files' => $skippedFiles,
          'manifest' => $this->_getPDFManifestRows($jobId, $no_rawat, $nosep)
        ];
      } catch (\Throwable $e) {
        return [
          'status' => false,
          'message' => $e->getMessage(),
          'file' => $e->getFile(),
          'line' => $e->getLine(),
          'no_rawat' => $no_rawat
        ];
      } finally {
        if (isset($jobTempDir) && is_dir($jobTempDir)) {
          try {
            $it = new \RecursiveIteratorIterator(
              new \RecursiveDirectoryIterator($jobTempDir, \FilesystemIterator::SKIP_DOTS),
              \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $item) {
              if ($item->isDir()) {
                @rmdir($item->getPathname());
              } else {
                @unlink($item->getPathname());
              }
            }
            @rmdir($jobTempDir);
          } catch (\Throwable $cleanupError) {
          }
        }
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
        '-dSAFER ' .
        '-dPrinted ' .
        '-dPreserveAnnots=false ' .
        '-dPreserveMarkedContent=false ' .
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

    private function _touchPDFQueueStage($jobId, $message)
    {
      if ($jobId === null) {
        return;
      }

      try {
        $stmt = $this->db()->pdo()->prepare("UPDATE mlite_vedika_pdf_queue
          SET message = ?, heartbeat_at = NOW()
          WHERE id = ? AND status = 'processing'");
        $stmt->execute([
          substr((string) $message, 0, 65000),
          (int) $jobId
        ]);
      } catch (\Throwable $e) {
        // Progress/heartbeat tidak boleh menggagalkan proses PDF utama.
      }
    }

    private function _recoverFailedPDFQueueJobs()
    {
      /*
       * Kompatibilitas untuk job lama yang sudah berhenti di attempts=3.
       * Versi baru memberi kesempatan otomatis sampai total 6 attempts.
       * Attempts tidak direset supaya retry tetap terbatas dan tidak infinite-loop.
       */
      try {
        $stmt = $this->db()->pdo()->prepare("UPDATE mlite_vedika_pdf_queue
          SET status = 'queued',
              message = 'Auto-retry lanjutan setelah gagal 3x; menunggu worker',
              started_at = NULL,
              finished_at = NULL,
              heartbeat_at = DATE_ADD(NOW(), INTERVAL 30 SECOND)
          WHERE status = 'failed'
            AND attempts >= 3
            AND attempts < 6");
        $stmt->execute();
      } catch (\Throwable $e) {
        // Recovery gagal tidak boleh menghentikan worker.
      }
    }

    private function _recoverStalePDFQueueJobs()
    {
      $pdo = $this->db()->pdo();

      /*
       * Jangan hanya mengandalkan umur heartbeat. Worker yang dimatikan paksa
       * melepas MySQL named lock pasien saat koneksinya putus. Job processing
       * baru dipulihkan bila lock pasien benar-benar sudah bebas, sehingga tidak
       * merebut job dari worker lain yang masih aktif.
       */
      $stale = $pdo->query("SELECT id, no_rawat, attempts
        FROM mlite_vedika_pdf_queue
        WHERE status = 'processing'
          AND COALESCE(heartbeat_at, started_at) < DATE_SUB(NOW(), INTERVAL 2 MINUTE)");
      $jobs = $stale->fetchAll(\PDO::FETCH_ASSOC);

      foreach ($jobs as $job) {
        $patientLock = 'vedika_pdf_patient_' . sha1($job['no_rawat']);
        $check = $pdo->prepare('SELECT IS_FREE_LOCK(?)');
        $check->execute([$patientLock]);
        if ((int) $check->fetchColumn() !== 1) {
          continue;
        }

        // Restart/kill worker bukan kegagalan dokumen; jangan habiskan jatah retry.
        $recover = $pdo->prepare("UPDATE mlite_vedika_pdf_queue
          SET status = 'queued',
              attempts = GREATEST(attempts - 1, 0),
              message = 'Worker terhenti/restart; job dikembalikan ke antrean',
              started_at = NULL,
              finished_at = NULL,
              heartbeat_at = NULL
          WHERE id = ? AND status = 'processing'");
        $recover->execute([$job['id']]);
      }
    }

    public function processPDFQueueOnce($workerId = '')
    {
      $pdo = $this->db()->pdo();
      $workerId = $workerId !== '' ? $workerId : php_uname('n') . ':' . getmypid();
      $claimLock = 'vedika_pdf_queue_claim';
      $job = null;

      $this->_recoverStalePDFQueueJobs();
      $this->_recoverFailedPDFQueueJobs();

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
            AND attempts < 6
            -- Beri jeda singkat untuk job yang baru saja dikembalikan ke queue
            -- (mis. patient lock masih dipakai worker lain), agar tidak hot-loop.
            AND (heartbeat_at IS NULL OR heartbeat_at < DATE_SUB(NOW(), INTERVAL 5 SECOND))
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
        /*
         * Gagal memperoleh patient lock BUKAN kegagalan generate PDF.
         * attempts sudah sempat +1 ketika job di-claim, jadi kembalikan lagi
         * agar menunggu worker lain tidak menghabiskan jatah retry.
         * heartbeat_at dipakai sebagai cooldown supaya worker tidak hot-loop.
         */
        $reset = $pdo->prepare("UPDATE mlite_vedika_pdf_queue
          SET status = 'queued',
              attempts = GREATEST(attempts - 1, 0),
              message = 'Menunggu proses PDF pasien yang sama',
              started_at = NULL,
              finished_at = NULL,
              heartbeat_at = NOW()
          WHERE id = ?");
        $reset->execute([$job['id']]);

        return [
          'status' => true,
          'idle' => true,
          'job_id' => (int) $job['id'],
          'no_rawat' => $job['no_rawat'],
          'message' => 'Pasien yang sama sedang diproses worker lain; tidak dihitung sebagai attempt'
        ];
      }

      try {
        $heartbeat = $pdo->prepare("UPDATE mlite_vedika_pdf_queue
          SET heartbeat_at = NOW()
          WHERE id = ?");
        $heartbeat->execute([$job['id']]);

        $result = $this->_createPDFKlaimFile($job['no_rawat'], (int) $job['id']);
        $success = !empty($result['status']);
        $message = isset($result['message'])
          ? $result['message']
          : ($success ? 'PDF selesai dibuat' : 'Pembuatan PDF gagal');

        $isWatchdogTimeout = strpos((string) $message, 'VEDIKA_WATCHDOG_TIMEOUT:') === 0;
        if (!$success && $isWatchdogTimeout) {
          // Hang/timeout worker bukan kegagalan dokumen. Kembalikan attempt yang
          // sempat bertambah dan beri cooldown pendek sebelum worker baru mencoba.
          $timeoutReset = $pdo->prepare("UPDATE mlite_vedika_pdf_queue
            SET status = 'queued',
                attempts = GREATEST(attempts - 1, 0),
                message = ?,
                started_at = NULL,
                finished_at = NULL,
                heartbeat_at = DATE_ADD(NOW(), INTERVAL 15 SECOND)
            WHERE id = ?");
          $timeoutReset->execute([
            substr('Watchdog timeout; worker akan restart otomatis. ' . $message, 0, 65000),
            $job['id']
          ]);

          return [
            'status' => false,
            'idle' => false,
            'job_id' => (int) $job['id'],
            'no_rawat' => $job['no_rawat'],
            'message' => 'Watchdog timeout; job dikembalikan ke antrean tanpa menghabiskan attempt',
            'result' => $result
          ];
        }

        // Tiga attempt pertama tetap retry normal. Attempt 4-6 adalah auto-retry
        // lanjutan dengan cooldown agar kasus berat tidak hot-loop terus menerus.
        $finalStatus = $success ? 'done' : ($job['attempts'] >= 6 ? 'failed' : 'queued');
        if (!$success && $finalStatus === 'queued') {
          $message .= $job['attempts'] >= 3
            ? ' (auto-retry lanjutan setelah cooldown)'
            : ' (akan dicoba lagi)';
        }

        $finish = $pdo->prepare("UPDATE mlite_vedika_pdf_queue
          SET status = ?,
              message = ?,
              finished_at = CASE WHEN ? IN ('done', 'failed') THEN NOW() ELSE NULL END,
              started_at = CASE WHEN ? = 'queued' THEN NULL ELSE started_at END,
              heartbeat_at = CASE
                WHEN ? = 'queued' AND ? >= 3 THEN DATE_ADD(NOW(), INTERVAL 5 MINUTE)
                ELSE NOW()
              END
          WHERE id = ?");
        $finish->execute([
          $finalStatus,
          substr((string) $message, 0, 65000),
          $finalStatus,
          $finalStatus,
          $finalStatus,
          $job['attempts'],
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
        $errorMessage = $e->getMessage();
        $isWatchdogTimeout = strpos((string) $errorMessage, 'VEDIKA_WATCHDOG_TIMEOUT:') === 0;

        if ($isWatchdogTimeout) {
          $timeoutReset = $pdo->prepare("UPDATE mlite_vedika_pdf_queue
            SET status = 'queued',
                attempts = GREATEST(attempts - 1, 0),
                message = ?,
                started_at = NULL,
                finished_at = NULL,
                heartbeat_at = DATE_ADD(NOW(), INTERVAL 15 SECOND)
            WHERE id = ?");
          $timeoutReset->execute([
            substr('Watchdog timeout; worker akan restart otomatis. ' . $errorMessage, 0, 65000),
            $job['id']
          ]);

          return [
            'status' => false,
            'idle' => false,
            'job_id' => (int) $job['id'],
            'no_rawat' => $job['no_rawat'],
            'message' => 'Watchdog timeout; job dikembalikan ke antrean tanpa menghabiskan attempt'
          ];
        }

        $finalStatus = $job['attempts'] >= 6 ? 'failed' : 'queued';
        if ($finalStatus === 'queued') {
          $errorMessage .= $job['attempts'] >= 3
            ? ' (auto-retry lanjutan setelah cooldown)'
            : ' (akan dicoba lagi)';
        }

        $failed = $pdo->prepare("UPDATE mlite_vedika_pdf_queue
          SET status = ?,
              message = ?,
              finished_at = CASE WHEN ? = 'failed' THEN NOW() ELSE NULL END,
              started_at = CASE WHEN ? = 'queued' THEN NULL ELSE started_at END,
              heartbeat_at = CASE
                WHEN ? = 'queued' AND ? >= 3 THEN DATE_ADD(NOW(), INTERVAL 5 MINUTE)
                ELSE NOW()
              END
          WHERE id = ?");
        $failed->execute([
          $finalStatus,
          substr($errorMessage, 0, 65000),
          $finalStatus,
          $finalStatus,
          $finalStatus,
          $job['attempts'],
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
    
    private function _pdfExtractVerificationText($pdfPath)
    {
      if (!is_file($pdfPath) || filesize($pdfPath) <= 0) {
        return false;
      }

      if (!function_exists('exec')) {
        return null;
      }

      $gs = 'gs';
      $whichOutput = [];
      $whichCode = 0;
      @exec('command -v gs 2>&1', $whichOutput, $whichCode);
      if ($whichCode === 0 && !empty($whichOutput[0])) {
        $gs = trim($whichOutput[0]);
      }

      $txtPath = tempnam(sys_get_temp_dir(), 'vedika_pdf_txt_');
      if ($txtPath === false) {
        return null;
      }

      $timeoutBin = '';
      $timeoutOutput = [];
      $timeoutCode = 1;
      @exec('command -v timeout 2>&1', $timeoutOutput, $timeoutCode);
      if ($timeoutCode === 0 && !empty($timeoutOutput[0])) {
        $timeoutBin = trim($timeoutOutput[0]);
      }

      $cmd = ($timeoutBin !== '' ? escapeshellcmd($timeoutBin) . ' --signal=TERM --kill-after=10s 120s ' : '') .
        escapeshellcmd($gs) . ' ' .
        '-q -dNOPAUSE -dBATCH -sDEVICE=txtwrite ' .
        '-sOutputFile=' . escapeshellarg($txtPath) . ' ' .
        escapeshellarg($pdfPath) . ' 2>&1';

      $output = [];
      $returnCode = 0;
      @exec($cmd, $output, $returnCode);

      if ($returnCode !== 0 || !is_file($txtPath)) {
        @unlink($txtPath);
        return null;
      }

      $pdfText = @file_get_contents($txtPath);
      @unlink($txtPath);
      return $pdfText === false ? null : $pdfText;
    }

    private function _pdfContainsRenderSentinel($pdfPath, $sentinel)
    {
      if ($sentinel === '') {
        return false;
      }

      $pdfText = $this->_pdfExtractVerificationText($pdfPath);
      if ($pdfText === false) {
        return false;
      }
      if ($pdfText === null) {
        return null;
      }

      return strpos($pdfText, $sentinel) !== false;
    }

    private function _pdfNormalizeVerificationText($text)
    {
      $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
      $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);
      $text = preg_replace('/\s+/u', ' ', trim((string) $text));
      return strtolower((string) $text);
    }

    private function _pdfVerifyHtmlTailRendered($pdfPath, $sourceHtml)
    {
      $pdfText = $this->_pdfExtractVerificationText($pdfPath);
      if ($pdfText === false) {
        return false;
      }
      if ($pdfText === null) {
        return null;
      }

      $source = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i', ' ', (string) $sourceHtml);
      $source = preg_replace('/<style\b[^>]*>[\s\S]*?<\/style>/i', ' ', $source);
      $source = preg_replace('/<!--([\s\S]*?)-->/', ' ', $source);
      $source = preg_replace('/<barcode\b[^>]*>/i', ' ', $source);
      $source = preg_replace('/<br\s*\/?\s*>/i', ' ', $source);

      /*
       * Marker VEDIKA sengaja ditambahkan sebagai sentinel renderer. Marker
       * tersebut justru tidak selalu dikeluarkan Ghostscript txtwrite. Jangan
       * biarkan marker template seperti VEDIKA_EXTRA_KFR_U0045_END menjadi
       * "isi akhir" yang harus dicari lagi di PDF, karena itu membuat fallback
       * selalu dianggap gagal walau konten KFR sebenarnya sudah lengkap.
       */
      $source = preg_replace('/VEDIKA_[A-Z0-9_]+/i', ' ', $source);
      $source = strip_tags($source);

      $sourceNormalized = $this->_pdfNormalizeVerificationText($source);
      $pdfNormalized = $this->_pdfNormalizeVerificationText($pdfText);
      if ($sourceNormalized === '' || $pdfNormalized === '') {
        return false;
      }

      $tokens = preg_split('/\s+/u', $sourceNormalized, -1, PREG_SPLIT_NO_EMPTY);
      if (!$tokens) {
        return false;
      }

      /*
       * Validasi utama: cari frasa nyata dari bagian akhir dokumen. Karena data
       * sudah dirender oleh template engine, token di sini adalah nama dokter,
       * tanggal, judul footer, dsb. Bukan placeholder template.
       */
      $tail = array_slice($tokens, -80);
      $candidateSizes = [12, 10, 8, 6, 5, 4];
      foreach ($candidateSizes as $size) {
        if (count($tail) < $size) {
          continue;
        }
        for ($end = count($tail); $end >= $size; $end--) {
          $candidateTokens = array_slice($tail, $end - $size, $size);
          $candidate = implode(' ', $candidateTokens);
          if (strlen($candidate) < 16) {
            continue;
          }
          if (strpos($pdfNormalized, $candidate) !== false) {
            return true;
          }
        }
      }

      /*
       * Beberapa PDF mengubah urutan teks footer/tabel saat txtwrite. Sebagai
       * verifikasi cadangan yang tetap konservatif, minta dua anchor nyata:
       * satu dari awal dokumen dan satu dari area akhir. Ini jauh lebih aman
       * daripada hanya menerima PDF karena file mempunyai %%EOF.
       */
      $head = array_slice($tokens, 0, 80);
      $headMatched = false;
      $tailMatched = false;

      foreach ([8, 6, 5, 4] as $size) {
        if (!$headMatched && count($head) >= $size) {
          for ($start = 0; $start <= count($head) - $size; $start++) {
            $candidate = implode(' ', array_slice($head, $start, $size));
            if (strlen($candidate) >= 16 && strpos($pdfNormalized, $candidate) !== false) {
              $headMatched = true;
              break;
            }
          }
        }

        if (!$tailMatched && count($tail) >= $size) {
          for ($start = 0; $start <= count($tail) - $size; $start++) {
            $candidate = implode(' ', array_slice($tail, $start, $size));
            if (strlen($candidate) >= 16 && strpos($pdfNormalized, $candidate) !== false) {
              $tailMatched = true;
              break;
            }
          }
        }

        if ($headMatched && $tailMatched) {
          return true;
        }
      }

      return false;
    }

    private function _writePDFKlaimHTMLChunked($mpdf, $html)
    {
      /*
       * mPDF lebih stabil untuk dokumen panjang bila HTML diproses dalam
       * potongan kecil. Implementasi ini sengaja tidak bergantung pada
       * ext-dom supaya aman pada instalasi PHP minimal.
       */

      // CSS ditulis satu kali dan akan tetap berlaku pada WriteHTML berikutnya.
      if (preg_match_all('/<style\b[^>]*>([\s\S]*?)<\/style>/i', $html, $styleMatches)) {
        foreach ($styleMatches[1] as $css) {
          if (trim($css) !== '') {
            $mpdf->WriteHTML('<style>' . $css . '</style>');
          }
        }
      }

      if (preg_match('/<body\b[^>]*>([\s\S]*)<\/body\s*>/i', $html, $bodyMatch)) {
        $bodyHtml = $bodyMatch[1];
      } else {
        $bodyHtml = preg_replace('/<head\b[^>]*>[\s\S]*?<\/head>/i', '', $html);
        $bodyHtml = preg_replace('/<style\b[^>]*>[\s\S]*?<\/style>/i', '', $bodyHtml);
      }

      $bodyChunks = $this->_splitPDFHTMLTopLevelChunks($bodyHtml);

      if (!$bodyChunks) {
        $mpdf->WriteHTML($bodyHtml);
        return;
      }

      foreach ($bodyChunks as $chunk) {
        if (trim($chunk) === '') {
          continue;
        }

        if ($this->_pdfHTMLChunkHasClass($chunk, 'div', 'resume-document')) {
          $resumeInner = $this->_pdfHTMLOuterTagInner($chunk, 'div');
          if ($resumeInner === null) {
            $mpdf->WriteHTML($chunk);
            continue;
          }

          $resumeChunks = $this->_splitPDFHTMLTopLevelChunks($resumeInner);
          if (!$resumeChunks) {
            $mpdf->WriteHTML($chunk);
            continue;
          }

          foreach ($resumeChunks as $resumeChunk) {
            if (trim($resumeChunk) === '') {
              continue;
            }

            if ($this->_pdfHTMLChunkHasClass($resumeChunk, 'div', 'resume-section')) {
              $sectionInner = $this->_pdfHTMLOuterTagInner($resumeChunk, 'div');
              if ($sectionInner === null) {
                $mpdf->WriteHTML('<div class="resume-document">' . $resumeChunk . '</div>');
                continue;
              }

              /*
               * Ini bagian terpenting untuk kasus resume panjang:
               * tabel section dan setiap entry CPPT diproses sendiri-sendiri.
               */
              $sectionChunks = $this->_splitPDFHTMLTopLevelChunks($sectionInner);
              if (!$sectionChunks) {
                $mpdf->WriteHTML(
                  '<div class="resume-document"><div class="resume-section">'
                  . $sectionInner
                  . '</div></div>'
                );
                continue;
              }

              foreach ($sectionChunks as $sectionChunk) {
                if (trim($sectionChunk) === '') {
                  continue;
                }

                $mpdf->WriteHTML(
                  '<div class="resume-document"><div class="resume-section">'
                  . $sectionChunk
                  . '</div></div>'
                );
              }

              continue;
            }

            $mpdf->WriteHTML('<div class="resume-document">' . $resumeChunk . '</div>');
          }

          continue;
        }

        $mpdf->WriteHTML($chunk);
      }
    }

    private function _splitPDFHTMLTopLevelChunks($html)
    {
      $chunks = [];
      $length = strlen($html);
      if ($length === 0) {
        return $chunks;
      }

      $voidTags = [
        'area' => true, 'base' => true, 'br' => true, 'col' => true,
        'embed' => true, 'hr' => true, 'img' => true, 'input' => true,
        'link' => true, 'meta' => true, 'param' => true, 'source' => true,
        'track' => true, 'wbr' => true,
        // custom mPDF tags
        'pagebreak' => true, 'barcode' => true,
      ];

      $offset = 0;
      $depth = 0;
      $chunkStart = null;
      $lastTopLevelEnd = 0;

      while (
        preg_match(
          '/<!--[\s\S]*?-->|<\/?([a-zA-Z][a-zA-Z0-9:_-]*)\b[^>]*>/',
          $html,
          $match,
          PREG_OFFSET_CAPTURE,
          $offset
        )
      ) {
        $token = $match[0][0];
        $tokenPos = $match[0][1];
        $tokenEnd = $tokenPos + strlen($token);
        $offset = $tokenEnd;

        // Comment di level teratas tidak perlu jadi chunk sendiri.
        if (strpos($token, '<!--') === 0) {
          if ($depth === 0) {
            $lastTopLevelEnd = $tokenEnd;
          }
          continue;
        }

        $tagName = isset($match[1][0]) ? strtolower($match[1][0]) : '';
        if ($tagName === '') {
          continue;
        }

        $isClosing = strpos($token, '</') === 0;
        $isSelfClosing = substr(rtrim($token), -2) === '/>' || isset($voidTags[$tagName]);

        if ($depth === 0) {
          $textBefore = substr($html, $lastTopLevelEnd, $tokenPos - $lastTopLevelEnd);
          if (trim($textBefore) !== '') {
            $chunks[] = $textBefore;
          }

          if ($isClosing) {
            // HTML tidak seimbang; simpan token agar tidak hilang.
            $chunks[] = $token;
            $lastTopLevelEnd = $tokenEnd;
            continue;
          }

          if ($isSelfClosing) {
            $chunks[] = $token;
            $lastTopLevelEnd = $tokenEnd;
            continue;
          }

          $chunkStart = $tokenPos;
          $depth = 1;
          continue;
        }

        if ($isClosing) {
          $depth--;
          if ($depth <= 0) {
            $depth = 0;
            if ($chunkStart !== null) {
              $chunks[] = substr($html, $chunkStart, $tokenEnd - $chunkStart);
            }
            $chunkStart = null;
            $lastTopLevelEnd = $tokenEnd;
          }
          continue;
        }

        if (!$isSelfClosing) {
          $depth++;
        }
      }

      if ($chunkStart !== null) {
        // HTML tidak seimbang: jangan buang sisanya.
        $chunks[] = substr($html, $chunkStart);
        $lastTopLevelEnd = $length;
      }

      if ($lastTopLevelEnd < $length) {
        $tail = substr($html, $lastTopLevelEnd);
        if (trim($tail) !== '') {
          $chunks[] = $tail;
        }
      }

      return $chunks;
    }

    private function _pdfHTMLChunkHasClass($chunk, $tagName, $className)
    {
      if (!preg_match(
        '/^\s*<' . preg_quote($tagName, '/') . '\b[^>]*\bclass\s*=\s*(["\'])([^"\']*)\1/i',
        $chunk,
        $match
      )) {
        return false;
      }

      $classes = preg_split('/\s+/', trim($match[2]));
      return in_array($className, $classes, true);
    }

    private function _pdfHTMLOuterTagInner($chunk, $tagName)
    {
      $tagNameQuoted = preg_quote($tagName, '/');

      if (!preg_match('/^\s*<' . $tagNameQuoted . '\b[^>]*>/i', $chunk, $openMatch)) {
        return null;
      }

      if (!preg_match('/<\/' . $tagNameQuoted . '>\s*$/i', $chunk, $closeMatch, PREG_OFFSET_CAPTURE)) {
        return null;
      }

      $openEnd = strlen($openMatch[0]);
      $closePos = $closeMatch[0][1];

      if ($closePos < $openEnd) {
        return null;
      }

      return substr($chunk, $openEnd, $closePos - $openEnd);
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
    
    /**
     * Netralisasi /OpenAction pada Catalog PDF tanpa mengubah panjang file.
     *
     * Beberapa validator upload BPJS menolak PDF hanya karena Catalog memiliki
     * /OpenAction, walaupun action tersebut cuma mengatur halaman/zoom awal dan
     * bukan JavaScript. Penggantian menggunakan nama PDF yang panjangnya sama,
     * sehingga offset xref tidak berubah dan struktur PDF tetap valid.
     */
    private function _pdfNeutralizeOpenAction($path)
    {
      if (!is_file($path) || filesize($path) <= 0) {
        return ['status' => false, 'message' => 'PDF sanitasi tidak ditemukan'];
      }

      $fh = @fopen($path, 'r+b');
      if (!$fh) {
        return ['status' => false, 'message' => 'PDF tidak dapat dibuka untuk sanitasi'];
      }

      $token = '/OpenAction';
      $replacement = '/OpenActOff'; // sama-sama 11 byte
      $chunkSize = 1024 * 1024;
      $overlap = strlen($token) - 1;
      $carry = '';
      $absoluteRead = 0;
      $offsets = [];

      while (!feof($fh)) {
        $data = fread($fh, $chunkSize);
        if ($data === false || $data === '') {
          break;
        }

        $buffer = $carry . $data;
        $bufferBase = $absoluteRead - strlen($carry);
        $searchAt = 0;
        while (($pos = strpos($buffer, $token, $searchAt)) !== false) {
          $absolutePos = $bufferBase + $pos;
          if ($absolutePos >= 0) {
            $offsets[$absolutePos] = $absolutePos;
          }
          $searchAt = $pos + strlen($token);
        }

        $absoluteRead += strlen($data);
        $carry = $overlap > 0 ? substr($buffer, -$overlap) : '';
      }

      $replaced = 0;
      foreach (array_values($offsets) as $absolutePos) {
        // Pastikan token berada di object Catalog, bukan kebetulan muncul pada
        // stream biner image/font.
        $contextStart = max(0, $absolutePos - 8192);
        fseek($fh, $contextStart, SEEK_SET);
        $context = fread($fh, 16384);
        if (!is_string($context) || $context === '') {
          continue;
        }

        $relativePos = $absolutePos - $contextStart;
        $before = substr($context, 0, $relativePos);
        $after = substr($context, $relativePos);
        $lastObj = strrpos($before, ' obj');
        $lastEndObj = strrpos($before, 'endobj');
        $nextEndObj = strpos($after, 'endobj');

        if ($lastObj === false || ($lastEndObj !== false && $lastEndObj > $lastObj) || $nextEndObj === false) {
          continue;
        }

        $objStart = max(0, $lastObj - 32);
        $catalogObject = substr($before, $objStart) . substr($after, 0, $nextEndObj + 6);
        if (!preg_match('/\/Type\s*\/Catalog\b/i', $catalogObject)) {
          continue;
        }

        fseek($fh, $absolutePos, SEEK_SET);
        if (fwrite($fh, $replacement) === strlen($replacement)) {
          $replaced++;
        }
      }

      fflush($fh);
      fclose($fh);

      return [
        'status' => true,
        'message' => $replaced > 0 ? 'OpenAction PDF dinetralisasi' : 'OpenAction tidak ditemukan',
        'openaction_removed' => $replaced,
      ];
    }

    private function _pdfHasRawToken($path, $token)
    {
      if (!is_file($path) || $token === '') {
        return false;
      }

      $fh = @fopen($path, 'rb');
      if (!$fh) {
        return false;
      }

      $chunkSize = 1024 * 1024;
      $overlap = max(0, strlen($token) - 1);
      $carry = '';
      $found = false;
      while (!feof($fh)) {
        $data = fread($fh, $chunkSize);
        if ($data === false || $data === '') {
          break;
        }
        $buffer = $carry . $data;
        if (strpos($buffer, $token) !== false) {
          $found = true;
          break;
        }
        $carry = $overlap > 0 ? substr($buffer, -$overlap) : '';
      }
      fclose($fh);
      return $found;
    }

    /**
     * Sanitasi akhir sebelum PDF KLM dipublish/di-upload ke BPJS.
     */
    private function _sanitizePDFForBPJS($path)
    {
      $neutralize = $this->_pdfNeutralizeOpenAction($path);
      if (empty($neutralize['status'])) {
        return $neutralize;
      }

      if ($this->_pdfHasRawToken($path, '/OpenAction')) {
        return [
          'status' => false,
          'message' => 'PDF masih mengandung /OpenAction setelah sanitasi',
          'openaction_removed' => isset($neutralize['openaction_removed']) ? $neutralize['openaction_removed'] : 0,
        ];
      }

      return [
        'status' => true,
        'message' => 'PDF lolos sanitasi OpenAction untuk BPJS',
        'openaction_removed' => isset($neutralize['openaction_removed']) ? $neutralize['openaction_removed'] : 0,
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
    
      $timeoutBin = '';
      $timeoutOutput = [];
      $timeoutCode = 1;
      @exec('command -v timeout 2>&1', $timeoutOutput, $timeoutCode);
      if ($timeoutCode === 0 && !empty($timeoutOutput[0])) {
        $timeoutBin = trim($timeoutOutput[0]);
      }
      $cmd = ($timeoutBin !== '' ? escapeshellcmd($timeoutBin) . ' --signal=TERM --kill-after=15s 300s ' : '') .
        escapeshellcmd($gs) . ' ' .
        '-sDEVICE=pdfwrite ' .
        '-dCompatibilityLevel=1.4 ' .
        '-dPDFSETTINGS=/ebook ' .
        '-dSAFER ' .
        '-dPrinted ' .
        '-dPreserveAnnots=false ' .
        '-dPreserveMarkedContent=false ' .
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
    
  
  /**
   * Cek fisik berkas digital remote untuk daftar pasien yang sedang tampil.
   *
   * Berkas selain 001 (SEP upload lama) dan KLM berada di WEBAPPS_URL.
   * Pengecekan dilakukan memakai HEAD secara paralel agar halaman Pengajuan,
   * Pengajuaninap, dan Indexcari tetap ringan. Hanya HTTP 404/410 atau file
   * dengan lokasi kosong/tidak valid yang dianggap "tidak ditemukan".
   *
   * Gangguan jaringan/timeout/HEAD tidak didukung tidak dianggap missing agar
   * tidak menimbulkan false positive pada UI. Worker PDF tetap menjadi validasi
   * final karena worker mengunduh dan memeriksa isi image/PDF secara penuh.
   */
  private function _getMissingRemoteBerkasAlertsForRows($rows)
  {
    if (!is_array($rows) || empty($rows)) return [];

    $noRawats = [];
    foreach ($rows as $row) {
      if (!is_array($row) || empty($row['no_rawat'])) continue;
      $noRawat = trim((string) $row['no_rawat']);
      if ($noRawat !== '') $noRawats[$noRawat] = true;
    }
    if (empty($noRawats)) return [];

    $placeholders = implode(',', array_fill(0, count($noRawats), '?'));
    $sql = "
      SELECT
        b.no_rawat,
        b.kode,
        b.lokasi_file,
        COALESCE(m.nama, b.kode) AS nama_berkas
      FROM berkas_digital_perawatan b
      LEFT JOIN master_berkas_digital m
        ON m.kode = b.kode
      WHERE b.no_rawat IN ($placeholders)
        AND b.kode NOT IN ('001', 'KLM')
    ";

    try {
      $stmt = $this->db()->pdo()->prepare($sql);
      $stmt->execute(array_keys($noRawats));
      $documents = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
      error_log('VEDIKA remote berkas UI check query: ' . $e->getMessage());
      return [];
    }

    if (empty($documents)) return [];

    $alerts = [];
    $urlMap = [];

    foreach ($documents as $doc) {
      $noRawat = isset($doc['no_rawat']) ? trim((string) $doc['no_rawat']) : '';
      $kode = isset($doc['kode']) ? trim((string) $doc['kode']) : '';
      $lokasi = isset($doc['lokasi_file']) ? trim((string) $doc['lokasi_file']) : '';
      $nama = isset($doc['nama_berkas']) && trim((string) $doc['nama_berkas']) !== ''
        ? trim((string) $doc['nama_berkas'])
        : ($kode !== '' ? $kode : 'Berkas');

      if ($noRawat === '') continue;

      if ($lokasi === '') {
        $alerts[$noRawat][] = 'FILE TIDAK DITEMUKAN: ' . $nama . ' (lokasi file kosong)';
        continue;
      }

      $url = $this->_buildRemoteBerkasURL($lokasi);
      if (!$url) {
        $alerts[$noRawat][] = 'FILE TIDAK DITEMUKAN: ' . $nama . ' (' . basename($lokasi) . ')';
        continue;
      }

      if (!isset($urlMap[$url])) $urlMap[$url] = [];
      $urlMap[$url][] = [
        'no_rawat' => $noRawat,
        'nama' => $nama,
        'lokasi_file' => $lokasi,
      ];
    }

    if (!empty($urlMap)) {
      $probe = $this->_probeRemoteBerkasURLs(array_keys($urlMap));
      foreach ($urlMap as $url => $refs) {
        if (!array_key_exists($url, $probe) || $probe[$url] !== false) {
          continue;
        }

        foreach ($refs as $ref) {
          $label = 'FILE TIDAK DITEMUKAN: '
            . $ref['nama']
            . ' (' . basename($ref['lokasi_file']) . ')';
          $alerts[$ref['no_rawat']][] = $label;
        }
      }
    }

    foreach ($alerts as $noRawat => $items) {
      $alerts[$noRawat] = array_values(array_unique($items));
    }

    return $alerts;
  }

  /**
   * Probe URL remote dengan HEAD secara paralel.
   *
   * return:
   *   true  = file terjangkau (HTTP 2xx/3xx)
   *   false = pasti tidak ditemukan (HTTP 404/410, atau Content-Length = 0)
   *   null  = tidak dapat dipastikan (timeout, DNS, 403/405, dll)
   */
  private function _probeRemoteBerkasURLs($urls)
  {
    $result = [];
    $urls = array_values(array_unique(array_filter(array_map('strval', (array) $urls))));
    if (empty($urls)) return $result;

    foreach ($urls as $url) $result[$url] = null;

    if (!function_exists('curl_multi_init') || !function_exists('curl_init')) {
      return $result;
    }

    // Batasi concurrency supaya halaman tetap ringan walau Indexcari 50 pasien.
    foreach (array_chunk($urls, 24) as $batch) {
      $mh = curl_multi_init();
      $handles = [];

      foreach ($batch as $url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_USERAGENT, 'mLITE Vedika Remote Berkas Check');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);

        curl_multi_add_handle($mh, $ch);
        $handles[$url] = $ch;
      }

      $running = null;
      do {
        $status = curl_multi_exec($mh, $running);
        if ($status !== CURLM_OK) break;
        if ($running) {
          $selected = curl_multi_select($mh, 1.0);
          if ($selected === -1) usleep(10000);
        }
      } while ($running);

      foreach ($handles as $url => $ch) {
        $errno = curl_errno($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentLength = (float) curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        $contentType = strtolower(trim((string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE)));

        if ($errno === 0 && $http >= 200 && $http < 400) {
          // Jika server secara eksplisit menyatakan panjang 0 byte atau justru
          // mengembalikan halaman HTML (custom 404/login page), anggap berkas
          // fisiknya tidak tersedia. Nilai -1 berarti Content-Length unknown.
          $looksLikeHtmlError = ($contentType !== '' && strpos($contentType, 'text/html') === 0);
          $result[$url] = ($contentLength === 0.0 || $looksLikeHtmlError) ? false : true;
        } elseif ($http === 404 || $http === 410) {
          $result[$url] = false;
        } else {
          // 403/405/5xx/timeout/DNS tidak cukup untuk menyimpulkan file hilang.
          $result[$url] = null;
        }

        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
      }

      curl_multi_close($mh);
    }

    return $result;
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
    
    private function _downloadRemotePDFToLocal($lokasi_file, $tempDir, $prefix = 'remote', $maxAttempts = 3)
    {
      if (!is_dir($tempDir) && !mkdir($tempDir, 0775, true) && !is_dir($tempDir)) {
        return ['status' => false, 'message' => 'Gagal membuat temp directory remote PDF', 'path' => $tempDir];
      }

      $url = $this->_buildRemoteBerkasURL($lokasi_file);
      if (!$url) {
        return ['status' => false, 'message' => 'Lokasi file tidak valid', 'lokasi_file' => $lokasi_file];
      }
      if (!function_exists('curl_init')) {
        return ['status' => false, 'message' => 'cURL belum aktif di PHP', 'url' => $url];
      }

      $targetPath = rtrim($tempDir, '/\\') . '/' . $prefix . '_' . md5($lokasi_file) . '.pdf';
      $lastHttp = 0;
      $lastError = '';

      for ($attempt = 1; $attempt <= max(1, (int) $maxAttempts); $attempt++) {
        $fp = @fopen($targetPath, 'w+b');
        if (!$fp) {
          return ['status' => false, 'message' => 'Gagal membuat file temporary lokal', 'path' => $targetPath];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_USERAGENT, 'mLITE Vedika PDF Merger');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_ENCODING, '');

        $ok = curl_exec($ch);
        $lastHttp = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $lastError = (string) curl_error($ch);
        curl_close($ch);
        fclose($fp);

        $validHttp = $lastHttp >= 200 && $lastHttp < 300;
        $validPdf = false;
        if ($ok && $validHttp && is_file($targetPath) && filesize($targetPath) > 4) {
          $header = file_get_contents($targetPath, false, null, 0, 4);
          $validPdf = ($header === '%PDF');
        }

        if ($validPdf) {
          return [
            'status' => true,
            'message' => 'PDF remote berhasil didownload',
            'url' => $url,
            'path' => $targetPath,
            'size' => filesize($targetPath),
            'attempt' => $attempt,
          ];
        }

        @unlink($targetPath);
        if ($attempt < $maxAttempts) {
          usleep(300000 * $attempt);
        }
      }

      return [
        'status' => false,
        'message' => 'Gagal download/validasi PDF remote setelah ' . (int) $maxAttempts . ' percobaan',
        'http_code' => $lastHttp,
        'curl_error' => $lastError,
        'url' => $url,
      ];
    }

  public function getLabHistory($id)
  {
    $targetNoRawat = revertNoRawat($id);
    $target = $this->db('reg_periksa')->where('no_rawat', $targetNoRawat)->oneArray();
    if (!$target || empty($target['no_rkm_medis']) || $target['status_lanjut'] !== 'Ranap') {
      echo $this->draw('labhistory.html', ['lab' => ['error' => 'Registrasi tujuan tidak ditemukan.']]);
      exit();
    }

    $pdo = $this->db()->pdo();
    $stmt = $pdo->prepare(
      "SELECT pl.*, COALESCE(jpl.nm_perawatan, pl.kd_jenis_prw) AS nm_perawatan,
              rp.tgl_registrasi AS tgl_kunjungan, rp.status_lanjut
       FROM periksa_lab pl
       INNER JOIN reg_periksa rp ON rp.no_rawat = pl.no_rawat
       LEFT JOIN jns_perawatan_lab jpl ON jpl.kd_jenis_prw = pl.kd_jenis_prw
       WHERE rp.no_rkm_medis = ? AND pl.no_rawat <> ?
       ORDER BY pl.tgl_periksa DESC, pl.jam DESC
       LIMIT 300"
    );
    $stmt->execute([$target['no_rkm_medis'], $targetNoRawat]);
    $history = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $detailStmt = $pdo->prepare(
      "SELECT dpl.*, COALESCE(tl.Pemeriksaan, dpl.id_template) AS nama_detail
       FROM detail_periksa_lab dpl
       LEFT JOIN template_laboratorium tl ON tl.id_template = dpl.id_template
       WHERE dpl.no_rawat = ? AND dpl.kd_jenis_prw = ?
         AND dpl.tgl_periksa = ? AND dpl.jam = ?
       ORDER BY dpl.id_template"
    );
    foreach ($history as &$item) {
      $detailStmt->execute([$item['no_rawat'], $item['kd_jenis_prw'], $item['tgl_periksa'], $item['jam']]);
      $item['details'] = $detailStmt->fetchAll(\PDO::FETCH_ASSOC);
      $item['selection_key'] = $this->_encodeLabHistoryKey($item);
    }
    unset($item);

    echo $this->draw('labhistory.html', ['lab' => [
      'target_no_rawat' => $targetNoRawat,
      'no_rkm_medis' => $target['no_rkm_medis'],
      'history' => $history,
      // GET dan POST memakai route yang sama agar dikenali konsisten oleh router mLITE.
      // Token dicetak server-side; jangan bergantung pada mlite.token milik browser.
      'copy_url' => url([ADMIN, 'vedika', 'labhistory', $id, '?t=' . $_SESSION['token']]),
    ]]);
    exit();
  }

  public function postLabHistory($id)
  {
    // Tujuan dikunci dari parameter route, bukan mempercayai hidden input.
    $_POST['target_no_rawat'] = revertNoRawat($id);
    return $this->postCopyLabHistory();
  }

  public function postCopyLabHistory()
  {
    ob_start();
    header('Content-Type: application/json; charset=utf-8');
    $targetNoRawat = isset($_POST['target_no_rawat']) ? trim((string) $_POST['target_no_rawat']) : '';
    $selected = isset($_POST['lab_items']) && is_array($_POST['lab_items']) ? $_POST['lab_items'] : [];
    if ($targetNoRawat === '' || !$selected) {
      return $this->_labJsonResponse(['ok' => false, 'message' => 'Pilih minimal satu pemeriksaan laboratorium.']);
    }
    if (count($selected) > 100) {
      return $this->_labJsonResponse(['ok' => false, 'message' => 'Maksimal 100 pemeriksaan dalam sekali proses.']);
    }

    $pdo = $this->db()->pdo();
    $targetStmt = $pdo->prepare("SELECT no_rkm_medis FROM reg_periksa WHERE no_rawat = ? AND status_lanjut = 'Ranap' LIMIT 1");
    $targetStmt->execute([$targetNoRawat]);
    $target = $targetStmt->fetch(\PDO::FETCH_ASSOC);
    if (!$target) {
      return $this->_labJsonResponse(['ok' => false, 'message' => 'Registrasi tujuan tidak ditemukan.']);
    }

    $sourceStmt = $pdo->prepare(
      'SELECT pl.* FROM periksa_lab pl
       INNER JOIN reg_periksa rp ON rp.no_rawat = pl.no_rawat
       WHERE rp.no_rkm_medis = ? AND pl.no_rawat = ? AND pl.no_rawat <> ?
         AND pl.kd_jenis_prw = ? AND pl.tgl_periksa = ? AND pl.jam = ? LIMIT 1'
    );
    $detailStmt = $pdo->prepare(
      'SELECT * FROM detail_periksa_lab
       WHERE no_rawat = ? AND kd_jenis_prw = ? AND tgl_periksa = ? AND jam = ?'
    );
    $copiedHeaders = 0;
    $copiedDetails = 0;
    $processed = 0;
    try {
      $auditStmt = $this->_prepareLabCopyAudit();
      $pdo->beginTransaction();
      foreach ($selected as $encoded) {
        $key = $this->_decodeLabHistoryKey($encoded);
        if (!$key) continue;
        $sourceStmt->execute([
          $target['no_rkm_medis'], $key['no_rawat'], $targetNoRawat,
          $key['kd_jenis_prw'], $key['tgl_periksa'], $key['jam'],
        ]);
        $header = $sourceStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$header) continue;

        $sourceNoRawat = $header['no_rawat'];
        $header['no_rawat'] = $targetNoRawat;
        $headerCount = $this->_insertLabRowIgnore('periksa_lab', $header);

        $detailStmt->execute([$sourceNoRawat, $key['kd_jenis_prw'], $key['tgl_periksa'], $key['jam']]);
        $detailCount = 0;
        foreach ($detailStmt->fetchAll(\PDO::FETCH_ASSOC) as $detail) {
          $detail['no_rawat'] = $targetNoRawat;
          $detailCount += $this->_insertLabRowIgnore('detail_periksa_lab', $detail);
        }
        if ($auditStmt) {
          $auditStmt->execute([
            $targetNoRawat, $sourceNoRawat, $key['kd_jenis_prw'], $key['tgl_periksa'],
            $key['jam'], $headerCount, $detailCount,
            (string) $this->core->getUserInfo('username', null, true),
          ]);
        }
        $copiedHeaders += $headerCount;
        $copiedDetails += $detailCount;
        $processed++;
      }
      $pdo->commit();
      return $this->_labJsonResponse([
        'ok' => true,
        'message' => $processed
          ? "Selesai. {$copiedHeaders} pemeriksaan dan {$copiedDetails} detail baru disalin. Data yang sudah ada dilewati."
          : 'Tidak ada pemeriksaan valid yang dapat disalin.',
      ]);
    } catch (\Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      return $this->_labJsonResponse(['ok' => false, 'message' => 'Gagal menyalin riwayat laboratorium: ' . $e->getMessage()]);
    }
  }

  private function _labJsonResponse(array $response)
  {
    if (ob_get_level() > 0) ob_clean();
    return $this->jsonResponse($response);
  }

  private function _prepareLabCopyAudit()
  {
    $pdo = $this->db()->pdo();
    try {
      $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `mlite_vedika_lab_copy_audit` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `target_no_rawat` varchar(17) NOT NULL,
          `source_no_rawat` varchar(17) NOT NULL,
          `kd_jenis_prw` varchar(15) NOT NULL,
          `source_tgl_periksa` date NOT NULL,
          `source_jam` time NOT NULL,
          `copied_header` tinyint unsigned NOT NULL DEFAULT 0,
          `copied_details` int unsigned NOT NULL DEFAULT 0,
          `copied_by` varchar(50) NOT NULL DEFAULT '',
          `created_at` datetime NOT NULL,
          PRIMARY KEY (`id`),
          KEY `idx_lab_copy_target` (`target_no_rawat`,`created_at`),
          KEY `idx_lab_copy_source` (`source_no_rawat`,`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1"
      );
      return $pdo->prepare(
        'INSERT INTO mlite_vedika_lab_copy_audit
         (target_no_rawat, source_no_rawat, kd_jenis_prw, source_tgl_periksa,
          source_jam, copied_header, copied_details, copied_by, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
      );
    } catch (\Throwable $e) {
      // Audit tidak boleh membatalkan salinan data klaim utama.
      error_log('Vedika lab copy audit unavailable: ' . $e->getMessage());
      return null;
    }
  }

  private function _encodeLabHistoryKey(array $row)
  {
    $json = json_encode([
      'no_rawat' => $row['no_rawat'], 'kd_jenis_prw' => $row['kd_jenis_prw'],
      'tgl_periksa' => $row['tgl_periksa'], 'jam' => $row['jam'],
    ]);
    return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
  }

  private function _decodeLabHistoryKey($encoded)
  {
    $encoded = strtr((string) $encoded, '-_', '+/');
    $padding = strlen($encoded) % 4;
    if ($padding) $encoded .= str_repeat('=', 4 - $padding);
    $data = json_decode((string) base64_decode($encoded, true), true);
    foreach (['no_rawat', 'kd_jenis_prw', 'tgl_periksa', 'jam'] as $field) {
      if (!is_array($data) || empty($data[$field])) return null;
    }
    return $data;
  }

  private function _insertLabRowIgnore($table, array $row)
  {
    if (!in_array($table, ['periksa_lab', 'detail_periksa_lab'], true) || !$row) {
      throw new \RuntimeException('Tabel laboratorium tidak diizinkan.');
    }
    $columns = array_keys($row);
    foreach ($columns as $column) {
      if (!preg_match('/^[A-Za-z0-9_]+$/', $column)) {
        throw new \RuntimeException('Nama kolom laboratorium tidak valid.');
      }
    }
    $quoted = array_map(function ($column) { return '`' . $column . '`'; }, $columns);
    $sql = 'INSERT IGNORE INTO `' . $table . '` (' . implode(',', $quoted) . ') VALUES ('
      . implode(',', array_fill(0, count($columns), '?')) . ')';
    $stmt = $this->db()->pdo()->prepare($sql);
    $stmt->execute(array_values($row));
    return (int) $stmt->rowCount();
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

  /**
   * Validasi dokumen klinis tambahan berdasarkan diagnosis/prosedur.
   *
   * Jika data diagnosis, prosedur, dan berkas sudah tersedia dari query daftar,
   * kirimkan ke method ini agar tidak melakukan query ulang per pasien.
   */
  private function _getRequiredDocumentAlerts($noRawat, $noRkmMedis = '', $diagnosisRows = null, $procedureRows = null, $documentRows = null)
  {
    $noRawat = trim((string) $noRawat);
    if ($noRawat === '') return [];

    $pdo = $this->db()->pdo();

    if (!is_array($diagnosisRows)) {
      $stmt = $pdo->prepare('SELECT kd_penyakit FROM diagnosa_pasien WHERE no_rawat = ?');
      $stmt->execute([$noRawat]);
      $diagnosisRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    if (!is_array($procedureRows)) {
      $stmt = $pdo->prepare('SELECT kode FROM prosedur_pasien WHERE no_rawat = ?');
      $stmt->execute([$noRawat]);
      $procedureRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    if (!is_array($documentRows)) {
      $stmt = $pdo->prepare('SELECT kode FROM berkas_digital_perawatan WHERE no_rawat = ?');
      $stmt->execute([$noRawat]);
      $documentRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    $diagnoses = [];
    foreach ($diagnosisRows as $item) {
      $code = isset($item['kd_penyakit']) ? $item['kd_penyakit'] : '';
      $code = strtoupper(trim((string) $code));
      if ($code !== '') $diagnoses[$code] = true;
    }

    $procedures = [];
    foreach ($procedureRows as $item) {
      $code = isset($item['kode']) ? $item['kode'] : '';
      $code = strtoupper(trim((string) $code));
      if ($code !== '') $procedures[$code] = true;
    }

    $documents = [];
    foreach ($documentRows as $item) {
      $code = isset($item['kode']) ? $item['kode'] : '';
      $code = strtoupper(trim((string) $code));
      if ($code !== '') $documents[$code] = true;
    }

    $alerts = [];
    $needDocument = function ($code, $message) use (&$alerts, $documents) {
      if (!isset($documents[strtoupper($code)])) $alerts[] = $message;
    };

    // D64.9 -> wajib Lembar Transfusi (kode berkas 021), khusus rawat inap.
    // Pada rawat jalan badge ini tidak ditampilkan karena transfusi bukan
    // persyaratan kelengkapan episode ralan.
    if (isset($diagnoses['D64.9'])) {
      $statusStmt = $pdo->prepare('SELECT status_lanjut FROM reg_periksa WHERE no_rawat = ? LIMIT 1');
      $statusStmt->execute([$noRawat]);
      $statusLanjut = (string) $statusStmt->fetchColumn();
      if (strcasecmp($statusLanjut, 'Ranap') === 0) {
        $needDocument('021', 'Lembar Transfusi dibutuhkan');
      }
    }

    // Ventilator/intubasi tertentu -> wajib Cardex (kode berkas 041).
    // Selain ICD-9 di prosedur_pasien, tindakan layanan ventilator juga
    // menjadi pemicu karena beberapa instalasi mencatatnya sebagai tarif.
    $needsCardex = false;
    foreach (['96.05', '96.71', '96.70'] as $procedureCode) {
      if (isset($procedures[$procedureCode])) {
        $needsCardex = true;
        break;
      }
    }

    if (!$needsCardex) {
      $cardexServiceGroups = [
        ['rawat_jl_dr',   ['IGD-11287', 'IGD-112122', 'IGD-112133', 'IGD-112136']],
        ['rawat_jl_pr',   ['IGD-11287', 'IGD-112122', 'IGD-112133', 'IGD-112136']],
        ['rawat_jl_drpr', ['IGD-11287', 'IGD-112122', 'IGD-112133', 'IGD-112136']],
        ['rawat_inap_dr',   ['ISO-11740', 'ISO-11741']],
        ['rawat_inap_pr',   ['ISO-11740', 'ISO-11741']],
        ['rawat_inap_drpr', ['ISO-11740', 'ISO-11741']],
      ];

      // Satukan pengecekan menjadi satu query agar validasi daftar pasien
      // tidak menambah enam round-trip database untuk setiap baris.
      $serviceQueries = [];
      $serviceParams = [];
      foreach ($cardexServiceGroups as [$table, $serviceCodes]) {
        $placeholders = implode(',', array_fill(0, count($serviceCodes), '?'));
        $serviceQueries[] = "SELECT 1 FROM {$table} WHERE no_rawat = ? AND kd_jenis_prw IN ({$placeholders})";
        $serviceParams[] = $noRawat;
        foreach ($serviceCodes as $serviceCode) {
          $serviceParams[] = $serviceCode;
        }
      }
      $serviceStmt = $pdo->prepare(implode(' UNION ALL ', $serviceQueries) . ' LIMIT 1');
      $serviceStmt->execute($serviceParams);
      $needsCardex = (bool) $serviceStmt->fetchColumn();
    }

    if ($needsCardex) {
      $needDocument('041', 'Cardex dibutuhkan');
    }

    // Seluruh A15 dan turunannya (A15, A15.0-A15.9, A15.xx) -> wajib No SITB.
    $hasA15 = false;
    foreach (array_keys($diagnoses) as $diagnosisCode) {
      if (preg_match('/^A15(?:\.|$)/', $diagnosisCode)) {
        $hasA15 = true;
        break;
      }
    }
    if ($hasA15) {
      if ($noRkmMedis === '') {
        $reg = $this->db('reg_periksa')->where('no_rawat', $noRawat)->oneArray();
        $noRkmMedis = isset($reg['no_rkm_medis']) ? trim((string) $reg['no_rkm_medis']) : '';
      }
      $hasSitb = false;
      if ($noRkmMedis !== '') {
        $sitb = $pdo->prepare('SELECT 1 FROM sitb_pasien_norm WHERE no_rkm_medis = ? LIMIT 1');
        $sitb->execute([$noRkmMedis]);
        $hasSitb = (bool) $sitb->fetchColumn();
      }
      if (!$hasSitb) $alerts[] = 'Input No SITB Pasien';
    }

    // Z37.0 -> SHK (022)
    if (isset($diagnoses['Z37.0'])) {
      $needDocument('022', 'Lengkapi SHK');
    //   $needDocument('007', 'Lengkapi CTG');
    //   $needDocument('023', 'Lengkapi Partograf');
    }

    // O80.9 -> Partograf (023)
    if (isset($diagnoses['O80.9'])) {
    //   $needDocument('007', 'Lengkapi CTG');
      $needDocument('023', 'Lengkapi Partograf');
    }
    
    // O82.9 -> CTG (007)
    if (isset($diagnoses['O82.9'])) {
      $needDocument('007', 'Lengkapi CTG');
    //   $needDocument('023', 'Lengkapi Partograf');
    }

    return array_values(array_unique($alerts));
  }

  private function _hasMissingRadiologyExpertise($noRawat)
  {
    $noRawat = trim((string) $noRawat);
    if ($noRawat === '') return false;

    $stmt = $this->db()->pdo()->prepare(
      'SELECT
         EXISTS(SELECT 1 FROM periksa_radiologi pr WHERE pr.no_rawat = ? LIMIT 1) AS has_exam,
         EXISTS(SELECT 1 FROM hasil_radiologi hr WHERE hr.no_rawat = ? LIMIT 1) AS has_result'
    );
    $stmt->execute([$noRawat, $noRawat]);
    $status = $stmt->fetch(\PDO::FETCH_ASSOC);

    return !empty($status['has_exam']) && empty($status['has_result']);
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
  
  public function getAsesmenPoli($status_lanjut, $no_rawat)
  {
    $rawNoRawat = revertNoRawat($no_rawat);
    $regPeriksa = $this->db('reg_periksa')
      ->join('pasien', 'pasien.no_rkm_medis=reg_periksa.no_rkm_medis')
      ->join('dokter', 'dokter.kd_dokter=reg_periksa.kd_dokter')
      ->where('reg_periksa.no_rawat', $rawNoRawat)
      ->oneArray();
    if (!$regPeriksa || $status_lanjut !== 'Ralan'
        || (string) $regPeriksa['status_lanjut'] !== 'Ralan'
        || (string) $regPeriksa['kd_poli'] === 'IGDK') {
      http_response_code(404);
      echo '<div class="modal-header"><h4 class="modal-title">SOAP Poli</h4></div>'
        . '<div class="modal-body"><div class="alert alert-danger">SOAP Poli hanya tersedia untuk pasien rawat jalan non-IGD.</div></div>';
      exit();
    }
    $asesmen = $this->db('pemeriksaan_ralan')
      ->where('no_rawat', $rawNoRawat)
      ->where('nik', $regPeriksa['kd_dokter'])
      ->desc('tgl_perawatan')->desc('jam_rawat')->oneArray();
    echo $this->draw('form.asesmenpoli.html', [
      'status_lanjut' => $status_lanjut, 'reg_periksa' => $regPeriksa, 'asesmen' => $asesmen
    ]);
    exit();
  }

  public function anyLaporanbedah($status_lanjut, $no_rawat)
  {
    return $this->getLaporanBedah($status_lanjut, $no_rawat);
  }

  public function getLaporanBedah($status_lanjut, $no_rawat)
  {
    $rawNoRawat = revertNoRawat($no_rawat);
    $regPeriksa = $this->db('reg_periksa')
      ->join('pasien', 'pasien.no_rkm_medis=reg_periksa.no_rkm_medis')
      ->where('reg_periksa.no_rawat', $rawNoRawat)
      ->oneArray();
    if (!$regPeriksa || !in_array((string) ($regPeriksa['kd_poli'] ?? ''), ['IGDK', 'U0034'], true)) {
      http_response_code(404);
      echo '<div class="modal-header"><h4 class="modal-title">Laporan Bedah</h4></div>'
        . '<div class="modal-body"><div class="alert alert-danger">Laporan bedah hanya tersedia untuk IGD dan poli U0034.</div></div>';
      exit();
    }
    $laporan = $this->db('laporan_bedah')->where('no_rawat', $rawNoRawat)->oneArray();
    if (!$laporan) {
      $laporan = ['operator' => $regPeriksa['kd_dokter'] ?? ''];
    }
    foreach (['mulai', 'selesai'] as $waktu) {
      if (!empty($laporan[$waktu])) {
        $laporan[$waktu] = str_replace(' ', 'T', substr((string) $laporan[$waktu], 0, 16));
      }
    }
    $dokter = $this->db('dokter')->where('status','=','1')->asc('nm_dokter')->toArray();
    $masterBedah = $this->db('master_b')->asc('kd_p')->toArray();
    $html = $this->draw('form.laporanbedah.html', [
      'status_lanjut' => $status_lanjut,
      'reg_periksa' => $regPeriksa,
      'laporan' => $laporan,
      'dokter' => $dokter,
      'master_bedah' => $masterBedah
    ]);
    if (!is_string($html) || trim($html) === '') {
      $html = '<div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Laporan Bedah</h4></div><div class="modal-body"><div class="alert alert-danger">Template form laporan bedah tidak menghasilkan isi.</div></div>';
    }
    echo $html;
    exit();
  }

  public function getMasterBedah($kode)
  {
    $template = $this->db('master_b')->where('kd_p', $kode)->oneArray();
    return $this->jsonResponse(['ok' => (bool) $template, 'template' => $template ?: null]);
  }

  public function postSaveLaporanBedah()
  {
    $noRawat = isset($_POST['no_rawat']) ? trim((string) $_POST['no_rawat']) : '';
    $regPeriksa = $this->db('reg_periksa')->where('no_rawat', $noRawat)->oneArray();
    if (!$regPeriksa || !in_array((string) $regPeriksa['kd_poli'], ['IGDK', 'U0034'], true)) {
      return $this->jsonResponse(['ok' => false, 'message' => 'Laporan bedah hanya tersedia untuk IGD dan poli U0034']);
    }
    $aksi = isset($_POST['aksi']) ? trim((string) $_POST['aksi']) : 'simpan';
    try {
      if ($aksi === 'hapus') {
        $this->db('laporan_bedah')->where('no_rawat', $noRawat)->delete();
        return $this->jsonResponse(['ok' => true, 'message' => 'Laporan bedah dihapus']);
      }
      $fields = [
        'mulai', 'selesai', 'jenis_anestesi', 'jenis_pembedahan',
        'diagnosa_preop', 'diagnosa_postop', 'jaringan', 'komplikasi',
        'implan', 'no_implan', 'tindakan_bedah', 'laporan_bedah', 'operator'
      ];
      $data = ['no_rawat' => $noRawat];
      foreach ($fields as $field) {
        $data[$field] = isset($_POST[$field]) ? trim((string) $_POST[$field]) : '';
      }
      foreach (['mulai', 'selesai'] as $waktu) {
        if ($data[$waktu] !== '') {
          $data[$waktu] = str_replace('T', ' ', $data[$waktu]);
          if (strlen($data[$waktu]) === 16) $data[$waktu] .= ':00';
        }
      }
      if ($this->db('laporan_bedah')->where('no_rawat', $noRawat)->oneArray()) {
        $this->db('laporan_bedah')->where('no_rawat', $noRawat)->save($data);
      } else {
        $this->db('laporan_bedah')->save($data);
      }
      return $this->jsonResponse(['ok' => true, 'message' => 'Laporan bedah berhasil disimpan']);
    } catch (\Throwable $e) {
      return $this->jsonResponse(['ok' => false, 'message' => 'Laporan bedah gagal disimpan: ' . $e->getMessage()]);
    }
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
  
  public function postSaveAsesmenPoli()
  {
    $noRawat = isset($_POST['no_rawat']) ? trim((string) $_POST['no_rawat']) : '';
    $tglPerawatan = isset($_POST['tgl_perawatan']) ? trim((string) $_POST['tgl_perawatan']) : '';
    $jamRawat = isset($_POST['jam_rawat']) ? trim((string) $_POST['jam_rawat']) : '';
    $regPeriksa = $this->db('reg_periksa')->where('no_rawat', $noRawat)->oneArray();
    if (!$regPeriksa || (string) $regPeriksa['status_lanjut'] !== 'Ralan'
        || (string) $regPeriksa['kd_poli'] === 'IGDK') {
      return $this->jsonResponse(['ok' => false, 'message' => 'SOAP Poli hanya dapat diedit untuk rawat jalan non-IGD']);
    }
    $dokter = (string) $regPeriksa['kd_dokter'];
    $asesmen = $this->db('pemeriksaan_ralan')->where('no_rawat', $noRawat)
      ->where('tgl_perawatan', $tglPerawatan)->where('jam_rawat', $jamRawat)
      ->where('nik', $dokter)->oneArray();
    if (!$asesmen) return $this->jsonResponse(['ok' => false, 'message' => 'SOAP dokter tidak ditemukan atau bukan milik dokter penanggung jawab']);
    $fields = ['suhu_tubuh','tensi','nadi','respirasi','tinggi','berat','spo','gcs',
      'kesadaran','keluhan','pemeriksaan','alergi','imun_ke','rtl','penilaian',
      'rpd','rpk','rpo','operasi','instruksi'];
    $data = [];
    foreach ($fields as $field) $data[$field] = isset($_POST[$field]) ? trim((string) $_POST[$field]) : '';
    try {
      $this->db('pemeriksaan_ralan')->where('no_rawat', $noRawat)
        ->where('tgl_perawatan', $tglPerawatan)->where('jam_rawat', $jamRawat)
        ->where('nik', $dokter)->save($data);
      return $this->jsonResponse(['ok' => true, 'message' => 'SOAP dokter berhasil diperbarui']);
    } catch (\Throwable $e) {
      return $this->jsonResponse(['ok' => false, 'message' => 'SOAP dokter gagal diperbarui: ' . $e->getMessage()]);
    }
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
      $diagnosa['inacbg_auto_code'] = $this->_mapIMDiagnosisToInacbg(isset($diagnosa['kd_penyakit']) ? $diagnosa['kd_penyakit'] : '', $diagnosa);
      $diagnosa['valid_grouping'] = $this->_isDiagnosisUsableForCoding($diagnosa);
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
    $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($items as &$item) {
      $item['inacbg_auto_code'] = $this->_mapIMDiagnosisToInacbg(isset($item['kode']) ? $item['kode'] : '', $item);
      $item['valid_for_coding'] = $this->_isDiagnosisUsableForCoding($item);
    }
    unset($item);
    return $this->jsonResponse(['ok' => true, 'items' => $items]);
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
    if (!$master || !$this->_isDiagnosisUsableForCoding($master)) {
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
          && $this->_isDiagnosisUsableForCoding($existingDiagnosis)
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
    return $this->_codingSuccessResponse('Diagnosis berhasil disimpan', $noRawat, $status);
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
    return $this->_codingSuccessResponse('Diagnosis berhasil dihapus', $noRawat, $status);
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
    if (!$master || !$this->_isDiagnosisUsableForCoding($master) || (isset($master['accpdx']) && strtoupper((string) $master['accpdx']) === 'N')) {
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
    return $this->_codingSuccessResponse('Diagnosis utama berhasil diubah', $noRawat, $status);
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
    if (!$master || !$this->_isDiagnosisUsableForCoding($master)) return $this->jsonResponse(['ok' => false, 'message' => 'Kode diagnosis pengganti tidak valid untuk grouping']);
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
    return $this->_codingSuccessResponse('Diagnosis berhasil disubstitusi', $noRawat, $status);
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
      $allowed = $this->_isDiagnosisUsableForCoding($row) && strtoupper((string) $row['accpdx']) !== 'N';
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
    return $this->_codingSuccessResponse('Prosedur berhasil disimpan', $noRawat, $status);
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
    return $this->_codingSuccessResponse('Volume prosedur diperbarui', $noRawat, $status);
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
    return $this->_codingSuccessResponse('Prosedur berhasil dihapus', $noRawat, $status);
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
    return $this->_codingSuccessResponse('Prosedur utama berhasil diubah', $noRawat, $status);
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
    return $this->_codingSuccessResponse('Prosedur berhasil disubstitusi', $noRawat, $status);
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
    $rawNoRawat = revertNoRawat($no_rawat);
    $reg_periksa = $this->db('reg_periksa')
      ->join('pasien', 'pasien.no_rkm_medis=reg_periksa.no_rkm_medis')
      ->join('poliklinik', 'poliklinik.kd_poli=reg_periksa.kd_poli')
      ->join('dokter', 'dokter.kd_dokter=reg_periksa.kd_dokter')
      ->join('penjab', 'penjab.kd_pj=reg_periksa.kd_pj')
      ->where('no_rawat', $rawNoRawat)
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
    // Dari halaman daftar, pertahankan SEP yang diklik. Ini penting ketika satu
    // no_rawat memiliki lebih dari satu SEP.
    $selectedSEP = isset($_GET['nosep']) ? trim((string) $_GET['nosep']) : '';
    $sepRow = [];
    if ($selectedSEP !== '') {
      $sepRow = $this->db('bridging_sep')
        ->where('no_sep', $selectedSEP)
        ->where('no_rawat', $rawNoRawat)
        ->oneArray();
    }
    if (!$sepRow) {
      $selectedSEP = $this->_getSEPInfo('no_sep', $rawNoRawat);
      $sepRow = $this->db('bridging_sep')->where('no_sep', $selectedSEP)->oneArray();
    }
    $reg_periksa['no_sep'] = $selectedSEP;
    $reg_periksa['kelas_rawat'] = isset($sepRow['klsrawat']) ? $sepRow['klsrawat'] : '';
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
                         "nomor_sep":"'.$reg_periksa['no_sep'].'"
                     }
                }';

    $get_claim_data = [];
    $get_claim_error = '';
    if (!$this->captureInacbgsHtml) {
      try {
        $msg = $this->Request($request);
        if(($msg['metadata']['message'] ?? '')=="Ok"){
          $get_claim_data = $msg;
          //echo json_encode($msg, true);
        } else {
          $get_claim_error = isset($msg['metadata']['message'])
            ? (string) $msg['metadata']['message']
            : 'Respons get_claim_data tidak dikenali';
        }
      } catch (\Throwable $e) {
        $get_claim_error = $e->getMessage();
      }
    }

    $adl = [];
    for($i=12; $i<=60; $i++){
       $adl[] = $i;
    }
    //echo json_encode($adl, true);

    $html = $this->draw('inacbgs.html', [
      'sitb' => is_array($sitb) ? $sitb : ['no_sitb' => ''],
      'worker_capture' => $this->captureInacbgsHtml,
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
      'get_claim_error' => htmlspecialchars($get_claim_error, ENT_QUOTES, 'UTF-8'),
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

    // Semua rawat jalan RS ini adalah reguler. Dalam E-Klaim,
    // jenis_rawat=3 berarti rawat jalan eksekutif, bukan IGD.
    $jnsrawat="2";
    if($this->getRegPeriksaInfo('status_lanjut', $_POST['no_rawat']) == "Ranap"){
        $jnsrawat="1";
    }
    if ($jnsrawat === "2") {
        $tarif_poli_eks = "0";
        $add_payment_pct = "0";
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
    try {
      return $this->_prosesKlaimFull();
    } catch (\Throwable $e) {
      $lastStep = $this->lastGroupingRequestMethod
        ? 'eklaim_' . $this->lastGroupingRequestMethod
        : 'proses_klaim';

      return $this->jsonResponse([
        'ok' => false,
        'last_step' => $lastStep,
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
        ],
        'error' => [
          'message' => $e->getMessage(),
          'type' => get_class($e)
        ]
      ]);
    }
  }

  private function _prosesKlaimFull()
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

    // Semua rawat jalan RS ini adalah reguler. Dalam E-Klaim,
    // jenis_rawat=3 berarti rawat jalan eksekutif, bukan IGD.
    $jnsrawat="2";
    if($this->getRegPeriksaInfo('status_lanjut', $_POST['no_rawat']) == "Ranap"){
        $jnsrawat="1";
    }
    if ($jnsrawat === "2") {
        $tarif_poli_eks = "0";
        $add_payment_pct = "0";
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
    $idrgGroupingResult = $r;
    $result['steps']['grouper_idrg'] = true;
    $result['steps']['final_idrg'] = true;

    // STEP 5 INACBG
    $r = $this->GroupingStage($nosep,$diagnosaINACBG,$procedureINACBG);
    if (!$r['ok']) return $this->stop($result, 'grouper_inacbg', $r);
    $inacbgGroupingResult = $r;
    $result['steps']['grouper_inacbg'] = true;
    $result['steps']['final_inacbg'] = true;

    // FINAL KLAIM sudah diverifikasi di dalam GroupingStage().
    $result['steps']['final_klaim'] = true;

    // STEP TERAKHIR: KIRIM KE DATA CENTER
    $r = $this->KirimKlaimIndividualKeDC($nosep, $isReedit);
    if (!$r['ok']) return $this->stop($result, 'kirim_datacenter', $r);
    $datacenterResult = $r;
    $result['steps']['kirim_datacenter'] = true;

    $result['ok'] = true;
    $result['last_step'] = 'kirim_datacenter';
    $result['datacenter'] = $r;
    $result['recap'] = $this->_saveGroupingRecap([
      'no_rawat' => $norawat,
      'nosep' => $nosep,
      'jenis_rawat' => $jnsrawat === '1' ? 'Ranap' : 'Ralan',
      'coder_nik' => isset($_POST['coder_nik']) ? trim((string) $_POST['coder_nik']) : '123123123123',
      'diagnosa_idrg' => $diagnosaIDRG,
      'prosedur_idrg' => $procedureIDRG,
      'diagnosa_inacbg' => $diagnosaINACBG,
      'prosedur_inacbg' => $procedureINACBG,
      'idrg_result' => $idrgGroupingResult,
      'inacbg_result' => $inacbgGroupingResult,
      'datacenter_result' => $datacenterResult,
      'payload' => $_POST
    ]);

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
      $requestMethod = 'request';
      $requestData = json_decode((string) $request, true);
      if (is_array($requestData) && isset($requestData['metadata']['method'])) {
          $requestMethod = preg_replace('/[^a-zA-Z0-9_.-]/', '', (string) $requestData['metadata']['method']);
      }
      $this->lastGroupingRequestMethod = $requestMethod;

      $requestTimeout = 60;
      if ($this->activeGroupingJobId !== null) {
          if ($this->groupingJobDeadline !== null) {
              $remaining = (int) floor($this->groupingJobDeadline - microtime(true));
              if ($remaining <= 0) {
                  throw new \RuntimeException('Batas waktu total proses grouping E-Klaim terlampaui');
              }
              $requestTimeout = max(1, min($requestTimeout, $remaining));
          }
          $progress = $this->db()->pdo()->prepare(
              "UPDATE mlite_vedika_grouping_queue
               SET message = ?, heartbeat_at = NOW()
               WHERE id = ? AND status = 'processing'"
          );
          $progress->execute([
              substr(
                  'Diproses oleh ' . (string) $this->activeGroupingWorkerId
                  . ' | E-Klaim: ' . $requestMethod,
                  0,
                  65000
              ),
              $this->activeGroupingJobId
          ]);
      }

      $json = $this->mc_encrypt ($request, $this->settings->get('vedika.eklaim_key'));
      $header = array("Content-Type: application/x-www-form-urlencoded");
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $this->settings->get('vedika.eklaim_url'));
      curl_setopt($ch, CURLOPT_HEADER, 0);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
      // Jangan biarkan worker menggantung tanpa batas ketika service E-Klaim
      // atau koneksi sedang bermasalah. Error ini akan masuk ke mekanisme retry.
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
      curl_setopt($ch, CURLOPT_TIMEOUT, $requestTimeout);
      $response = curl_exec($ch);
      if ($response === false) {
          $curlError = curl_error($ch);
          $curlNo = curl_errno($ch);
          curl_close($ch);
          throw new \RuntimeException('Koneksi E-Klaim gagal (' . $curlNo . '): ' . $curlError);
      }
      $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
      curl_close($ch);

      if ($this->activeGroupingJobId !== null) {
          $heartbeat = $this->db()->pdo()->prepare(
              "UPDATE mlite_vedika_grouping_queue SET heartbeat_at = NOW()
               WHERE id = ? AND status = 'processing'"
          );
          $heartbeat->execute([$this->activeGroupingJobId]);
      }

      // Beberapa versi E-Klaim membungkus payload terenkripsi dengan baris
      // BEGIN/END, sementara versi lain mengirim base64 langsung.
      $decodedSuccessfully = false;
      $msg = $this->_decodeEKlaimResponse($response, $decodedSuccessfully);
      if (!$decodedSuccessfully) {
          // Kompatibilitas perilaku integrasi lama: beberapa versi E-Klaim
          // mengembalikan envelope terenkripsi pendek pada idrg_procedure_set
          // yang gagal verifikasi signature dan dahulu menjadi JSON null.
          // Endpoint ini memang diperbolehkan mengembalikan null; kebenaran set
          // procedure akan tetap diverifikasi oleh idrg_grouper berikutnya.
          if (
              $requestMethod === 'idrg_procedure_set'
              && $this->_isStructurallyValidEKlaimEnvelope($response)
          ) {
              return null;
          }

          $typeLabel = $contentType !== '' ? preg_replace('/[\r\n]+/', '', $contentType) : 'unknown';
          $safePreview = '';
          if (stripos($typeLabel, 'json') !== false) {
              $preview = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $response);
              $safePreview = ' | Isi: ' . substr(trim((string) $preview), 0, 500);
          }
          throw new \RuntimeException(
              'Respons E-Klaim tidak valid atau tidak dapat didekripsi'
              . ' (HTTP ' . $httpCode . ', ' . $typeLabel . ', ' . strlen($response) . ' byte)'
              . $safePreview
          );
      }
      return $msg;
  }

  private function _isStructurallyValidEKlaimEnvelope($response)
  {
      if (!is_string($response) || trim($response) === '') return false;
      $normalized = str_replace(["\r\n", "\r"], "\n", trim($response));
      $payloadLines = [];
      foreach (preg_split('/\n/', $normalized) as $line) {
          $line = trim($line);
          if ($line === '' || preg_match('/^-{2,}(?:BEGIN|END)\b/i', $line)) continue;
          if (!preg_match('/^[A-Za-z0-9+\/=]+$/', $line)) return false;
          $payloadLines[] = $line;
      }
      if (!$payloadLines) return false;
      $decoded = base64_decode(implode('', $payloadLines), true);
      if ($decoded === false || strlen($decoded) < 42) return false;

      // signature 10 byte + IV 16 byte + ciphertext kelipatan block AES 16.
      return (strlen($decoded) - 26) % 16 === 0;
  }

  private function _decodeEKlaimResponse($response, &$decodedSuccessfully = false)
  {
      $decodedSuccessfully = false;
      if (!is_string($response) || trim($response) === '') return null;

      // Sebagian instalasi/proxy mengembalikan error JSON tanpa enkripsi.
      $trimmed = preg_replace('/^\xEF\xBB\xBF/', '', trim($response));
      $plain = json_decode($trimmed, true);
      if (json_last_error() === JSON_ERROR_NONE) {
          if (!is_string($plain)) {
              $decodedSuccessfully = true;
              return $plain;
          }
      }
      if (is_string($plain)) {
          $nested = json_decode($plain, true);
          if (json_last_error() === JSON_ERROR_NONE) {
              $decodedSuccessfully = true;
              return $nested;
          }
      }

      $normalized = str_replace(["\r\n", "\r"], "\n", trim($response));
      $candidates = [$normalized];

      $lines = preg_split('/\n/', $normalized);
      $payloadLines = [];
      foreach ($lines as $line) {
          $line = trim($line);
          if ($line === '' || preg_match('/^-{2,}(?:BEGIN|END)\b/i', $line)) {
              continue;
          }
          $payloadLines[] = $line;
      }
      if ($payloadLines) $candidates[] = implode('', $payloadLines);

      $firstNewline = strpos($normalized, "\n");
      $lastNewline = strrpos($normalized, "\n");
      if ($firstNewline !== false && $lastNewline !== false && $lastNewline > $firstNewline) {
          $candidates[] = trim(substr(
              $normalized,
              $firstNewline + 1,
              $lastNewline - $firstNewline - 1
          ));
      }

      foreach (array_unique($candidates) as $candidate) {
          if ($candidate === '') continue;
          $decrypted = @$this->mc_decrypt($candidate, $this->settings->get('vedika.eklaim_key'));
          if (!is_string($decrypted) || $decrypted === 'SIGNATURE_NOT_MATCH') continue;
          $decoded = json_decode($decrypted, true);
          if (json_last_error() === JSON_ERROR_NONE) {
              // JSON null adalah respons sah pada idrg_procedure_set di beberapa
              // versi E-Klaim dan memang diperbolehkan oleh alur grouping lama.
              $decodedSuccessfully = true;
              return $decoded;
          }
      }
      return null;
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
            // Instalasi E-Klaim RS menggunakan satu akun coder bersama.
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
            // Instalasi E-Klaim RS menggunakan satu akun coder bersama.
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
    private function _groupingDcFilterSql($alias, $filter)
    {
        $alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $alias);
        if ($alias === '' || $filter === 'all') return '';

        // Kompatibel dengan data baru (mlite_vedika.status_dc) dan histori lama
        // yang hanya tercatat di inacbg_data_terkirim.
        if ($filter === 'sent') {
            return " AND ({$alias}.status_dc = 'Terkirim DC'
                      OR EXISTS (
                          SELECT 1 FROM inacbg_data_terkirim idt_dc
                          WHERE idt_dc.no_sep = {$alias}.nosep
                      ))";
        }

        return " AND (COALESCE({$alias}.status_dc, '') <> 'Terkirim DC'
                  AND NOT EXISTS (
                      SELECT 1 FROM inacbg_data_terkirim idt_dc
                      WHERE idt_dc.no_sep = {$alias}.nosep
                  ))";
    }
    private function _getLatestGroupingDelivery($noRawat, $nosep)
    {
        $noRawat = trim((string) $noRawat);
        $nosep = trim((string) $nosep);

        if ($nosep === '') {
            return ['sent' => false, 'label' => 'Belum terkirim DC'];
        }

        try {
            $pdo = $this->db()->pdo();

            // Sumber utama: marker baru di mlite_vedika.
            $stmt = $pdo->prepare(
                "SELECT nosep AS no_sep, status_dc AS marker
                 FROM mlite_vedika
                 WHERE no_rawat = ? AND nosep = ? AND status_dc = 'Terkirim DC'
                 ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute([$noRawat, $nosep]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row) {
                return [
                    'sent' => true,
                    'label' => 'Sudah terkirim DC',
                    'local_marker' => (string) ($row['marker'] ?? 'Terkirim DC')
                ];
            }

            // Fallback histori: versi lama hanya mencatat SEP di tabel ini.
            $legacy = $pdo->prepare(
                "SELECT no_sep, nik FROM inacbg_data_terkirim
                 WHERE no_sep = ? LIMIT 1"
            );
            $legacy->execute([$nosep]);
            $legacyRow = $legacy->fetch(\PDO::FETCH_ASSOC);

            if ($legacyRow) {
                // Sinkronkan marker baru agar request berikutnya lebih ringan.
                try {
                    $sync = $pdo->prepare(
                        "UPDATE mlite_vedika
                         SET status_dc = 'Terkirim DC'
                         WHERE no_rawat = ? AND nosep = ?"
                    );
                    $sync->execute([$noRawat, $nosep]);
                } catch (\Throwable $ignored) {
                    // Status legacy tetap sah walaupun sinkronisasi lokal gagal.
                }

                return [
                    'sent' => true,
                    'label' => 'Sudah terkirim DC',
                    'local_marker' => (string) ($legacyRow['nik'] ?? 'Terkirim DC'),
                    'legacy' => true
                ];
            }
        } catch (\Throwable $e) {
            // Jangan mengganggu halaman daftar jika tabel/DB sementara bermasalah.
        }

        return ['sent' => false, 'label' => 'Belum terkirim DC'];
    }


  private function _markVedikaDcSent($nomorSep)
    {
        $nomorSep = trim((string) $nomorSep);
        if ($nomorSep === '') {
            throw new \RuntimeException('Nomor SEP kosong saat menyimpan status Data Center');
        }

        $pdo = $this->db()->pdo();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) $pdo->beginTransaction();

        try {
            // Tabel lama tetap diisi agar integrasi lain yang masih membacanya
            // tidak langsung terputus.
            $checkLegacy = $pdo->prepare(
                'SELECT 1 FROM inacbg_data_terkirim WHERE no_sep = ? LIMIT 1'
            );
            $checkLegacy->execute([$nomorSep]);
            if (!$checkLegacy->fetchColumn()) {
                $insertLegacy = $pdo->prepare(
                    "INSERT INTO inacbg_data_terkirim (no_sep, nik) VALUES (?, 'Terkirim DC')"
                );
                $insertLegacy->execute([$nomorSep]);
            }

            // Semua record Vedika dengan SEP yang sama ikut ditandai. Ini
            // membuat filter halaman cukup membaca mlite_vedika saja.
            $updateVedika = $pdo->prepare(
                "UPDATE mlite_vedika SET status_dc = 'Terkirim DC' WHERE nosep = ?"
            );
            $updateVedika->execute([$nomorSep]);

            $verify = $pdo->prepare(
                "SELECT 1 FROM mlite_vedika
                 WHERE nosep = ? AND status_dc = 'Terkirim DC' LIMIT 1"
            );
            $verify->execute([$nomorSep]);
            if (!$verify->fetchColumn()) {
                throw new \RuntimeException('status_dc mlite_vedika tidak berhasil diperbarui');
            }

            if ($ownsTransaction) $pdo->commit();
        } catch (\Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

  private function KirimKlaimIndividualKeDC($nomor_sep, $forceResend = false)
    {
        // Idempotent: jangan kirim ulang jika sebelumnya sudah sukses terkirim.
        $alreadySent = $this->db('inacbg_data_terkirim')
            ->where('no_sep', $nomor_sep)
            ->oneArray();

        if (!$forceResend && !empty($alreadySent)) {
            // Sinkronkan data lama yang sudah ada di tabel legacy ke kolom baru.
            try {
                $this->_markVedikaDcSent($nomor_sep);
            } catch (\Throwable $e) {
                // Status remote sudah terkirim; kegagalan sinkron lokal tidak
                // boleh memicu pengiriman ulang klaim ke Data Center.
            }
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
            try {
                $this->_markVedikaDcSent($nomor_sep);
                $response['local_save'] = 'saved';
            } catch (\Throwable $e) {
                // DB error tidak menggagalkan response DC
                $response['local_save']  = 'failed';
                $response['local_error'] = $e->getMessage();
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
        $pegawai = $this->db('pegawai')->where('nik', $username)->oneArray();
        $coderNik = isset($pegawai['no_ktp']) ? (string) $pegawai['no_ktp'] : '';
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
                 WHERE no_rawat = ? AND nosep = ? ORDER BY id DESC LIMIT 1'
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
            // Yang ditampilkan harus status job paling baru untuk pasangan klaim
            // yang sama. Jangan mencari "failed terakhir" karena error lama akan
            // terus muncul walaupun percobaan sesudahnya sudah selesai sukses.
            if (trim((string) $nosep) !== '') {
                $stmt = $this->db()->pdo()->prepare(
                    "SELECT status, last_step, message, finished_at
                     FROM mlite_vedika_grouping_queue
                     WHERE no_rawat = ? AND nosep = ?
                     ORDER BY id DESC LIMIT 1"
                );
                $stmt->execute([$noRawat, $nosep]);
            } else {
                $stmt = $this->db()->pdo()->prepare(
                    "SELECT status, last_step, message, finished_at
                     FROM mlite_vedika_grouping_queue
                     WHERE no_rawat = ?
                     ORDER BY id DESC LIMIT 1"
                );
                $stmt->execute([$noRawat]);
            }
            $failure = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$failure || (string) $failure['status'] !== 'failed') {
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

        // Pulihkan job lama hanya jika named lock pasien memang sudah bebas.
        // Dengan numprocs > 1, umur heartbeat saja tidak cukup: worker lain dapat
        // sedang aktif menunggu respons E-Klaim dan tidak boleh dianggap mati.
        $stale = $pdo->query(
            "SELECT id, no_rawat, nosep, target_status, attempts
             FROM mlite_vedika_grouping_queue
             WHERE status = 'processing'
               AND COALESCE(heartbeat_at, started_at) < DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
        );
        $staleJobs = $stale->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($staleJobs as $staleJob) {
            $stalePatientLock = 'vedika_grouping_patient_'
                . sha1($staleJob['nosep'] . '|' . $staleJob['no_rawat']);
            $lockState = $pdo->prepare('SELECT IS_FREE_LOCK(?)');
            $lockState->execute([$stalePatientLock]);
            if ((int) $lockState->fetchColumn() !== 1) {
                continue;
            }

            $isFinalFailure = (int) $staleJob['attempts'] >= 3;
            $recover = $pdo->prepare(
                "UPDATE mlite_vedika_grouping_queue
                 SET status = ?, last_step = CASE WHEN ? = 1 THEN 'worker' ELSE last_step END,
                     message = ?, started_at = CASE WHEN ? = 1 THEN started_at ELSE NULL END,
                     finished_at = CASE WHEN ? = 1 THEN NOW() ELSE NULL END,
                     heartbeat_at = NOW()
                 WHERE id = ? AND status = 'processing'
                   AND COALESCE(heartbeat_at, started_at) < DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
            );
            $recover->execute([
                $isFinalFailure ? 'failed' : 'queued',
                $isFinalFailure ? 1 : 0,
                $isFinalFailure ? 'Worker grouping terhenti tiga kali' : 'Mengulang pekerjaan setelah worker terhenti',
                $isFinalFailure ? 1 : 0,
                $isFinalFailure ? 1 : 0,
                $staleJob['id']
            ]);

            // Rollback hanya untuk job yang baru saja berhasil dipindahkan ke
            // failed, bukan seluruh riwayat job failed pada setiap putaran worker.
            if ($isFinalFailure && $recover->rowCount() === 1) {
                $rollback = $pdo->prepare(
                    'DELETE FROM mlite_vedika
                     WHERE no_rawat = ? AND nosep = ? AND status = ?'
                );
                $rollback->execute([
                    $staleJob['no_rawat'],
                    $staleJob['nosep'],
                    $staleJob['target_status']
                ]);
            }
        }

        $dequeueLock = 'vedika_grouping_dequeue';
        if (!$this->_acquirePDFQueueLock($dequeueLock, 2)) {
            return ['status' => true, 'idle' => true, 'message' => 'Antrean sedang diperiksa worker lain'];
        }

        $job = null;
        try {
            $select = $pdo->query(
                "SELECT * FROM mlite_vedika_grouping_queue
                 WHERE status = 'queued' AND attempts < 3
                   AND (heartbeat_at IS NULL OR heartbeat_at <= NOW())
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

        // Validasi master dilakukan lagi di worker untuk menutup celah halaman
        // lama/cached atau antrean yang sudah dibuat sebelum tombol dikunci.
        $codingValidation = $this->_validateEpisodeCoding($job['no_rawat']);
        if (!$codingValidation['ok']) {
            $message = trim(implode('; ', array_filter([
                $codingValidation['diagnosis_message'],
                $codingValidation['procedure_message']
            ])));
            $this->_failBackgroundGrouping($job, 'Validasi koding: ' . $message, 'validasi_kode_master');
            $this->_releasePDFQueueLock($patientLock);
            return [
                'status' => false,
                'idle' => false,
                'job_id' => (int) $job['id'],
                'no_rawat' => $job['no_rawat'],
                'message' => 'Validasi koding: ' . $message
            ];
        }

        // Service E-Klaim dipakai bersama dan pada sebagian instalasi tidak aman
        // menerima dua rangkaian grouping secara paralel. Antrean database tetap
        // boleh memiliki beberapa worker, tetapi akses API dibuat satu per satu.
        $eklaimLock = 'vedika_grouping_eklaim_global';
        if (!$this->_acquirePDFQueueLock($eklaimLock, 2)) {
            $retry = $pdo->prepare(
                "UPDATE mlite_vedika_grouping_queue
                 SET status = 'queued', attempts = GREATEST(attempts - 1, 0),
                     message = 'Menunggu giliran akses E-Klaim',
                     started_at = NULL,
                     heartbeat_at = DATE_ADD(NOW(), INTERVAL 3 SECOND) WHERE id = ?"
            );
            $retry->execute([$job['id']]);
            $this->_releasePDFQueueLock($patientLock);
            return [
                'status' => true,
                'idle' => false,
                'job_id' => (int) $job['id'],
                'message' => 'Menunggu giliran E-Klaim'
            ];
        }

        try {
            $this->activeGroupingJobId = (int) $job['id'];
            $this->activeGroupingWorkerId = $workerId;
            $this->groupingJobDeadline = microtime(true) + 300;
            $this->lastGroupingRequestMethod = null;

            $payload = $this->_buildBackgroundGroupingPayload($job['no_rawat'], $job['nosep']);
            $payload['coder_nik'] = (string) $job['coder_nik'];

            $oldPost = $_POST;
            $_POST = $payload;
            $this->captureJsonResponse = true;
            try {
                $result = $this->postProsesKlaimFull();
            } finally {
                $this->captureJsonResponse = false;
                $this->activeGroupingJobId = null;
                $this->activeGroupingWorkerId = null;
                $this->groupingJobDeadline = null;
                $_POST = $oldPost;
            }

            if (!is_array($result) || empty($result['ok'])) {
                $failureMessage = $this->_groupingFailureMessage($result);
                $failureStep = is_array($result) && isset($result['last_step'])
                    ? $result['last_step']
                    : 'response';

                // Hanya gangguan transport yang dicoba ulang. Penolakan bisnis
                // E-Klaim (mis. E2016) langsung dikembalikan kepada coder.
                if ($this->_isTransientGroupingMessage($failureMessage) && (int) $job['attempts'] < 3) {
                    $retry = $pdo->prepare(
                        "UPDATE mlite_vedika_grouping_queue
                         SET status = 'queued', last_step = ?, message = ?,
                             started_at = NULL,
                             heartbeat_at = DATE_ADD(NOW(), INTERVAL 10 SECOND)
                         WHERE id = ?"
                    );
                    $retry->execute([
                        substr((string) $failureStep, 0, 50),
                        substr('Gangguan koneksi, akan dicoba lagi: ' . $failureMessage, 0, 65000),
                        $job['id']
                    ]);
                    return [
                        'status' => false,
                        'idle' => false,
                        'job_id' => (int) $job['id'],
                        'no_rawat' => $job['no_rawat'],
                        'message' => $failureMessage
                    ];
                }

                $this->_failBackgroundGrouping($job, $failureMessage, $failureStep);
                return [
                    'status' => false,
                    'idle' => false,
                    'job_id' => (int) $job['id'],
                    'no_rawat' => $job['no_rawat'],
                    'message' => $failureMessage
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
            $errorStep = $this->lastGroupingRequestMethod
                ? 'eklaim_' . $this->lastGroupingRequestMethod
                : 'worker';
            $transient = $this->_isTransientGroupingException($e);
            if (!$transient || (int) $job['attempts'] >= 3) {
                $this->_failBackgroundGrouping($job, 'Worker gagal: ' . $e->getMessage(), $errorStep);
            } else {
                $retry = $pdo->prepare(
                    "UPDATE mlite_vedika_grouping_queue
                     SET status = 'queued', last_step = ?, message = ?,
                         started_at = NULL,
                         heartbeat_at = DATE_ADD(NOW(), INTERVAL 10 SECOND) WHERE id = ?"
                );
                $retry->execute([
                    substr($errorStep, 0, 50),
                    substr('Akan dicoba lagi: ' . $e->getMessage(), 0, 65000),
                    $job['id']
                ]);
            }

            return [
                'status' => false,
                'idle' => false,
                'job_id' => (int) $job['id'],
                'no_rawat' => $job['no_rawat'],
                'message' => $e->getMessage()
            ];
        } finally {
            $this->captureJsonResponse = false;
            $this->activeGroupingJobId = null;
            $this->activeGroupingWorkerId = null;
            $this->groupingJobDeadline = null;
            $this->lastGroupingRequestMethod = null;
            $this->_releasePDFQueueLock($eklaimLock);
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

        $messages = [];
        $this->_collectEKlaimErrors($result, $messages);
        if ($messages) {
            // Pesan terdalam biasanya merupakan sebab final dari E-Klaim.
            return end($messages);
        }

        return 'Data koding tidak lolos pada tahap '
            . (isset($result['last_step']) ? $result['last_step'] : 'grouping');
    }

    private function _collectEKlaimErrors($value, array &$messages)
    {
        if (!is_array($value)) return;

        if (isset($value['metadata']) && is_array($value['metadata'])) {
            $metadata = $value['metadata'];
            $message = isset($metadata['message']) ? trim((string) $metadata['message']) : '';
            $code = isset($metadata['code']) ? (int) $metadata['code'] : 0;
            $errorNo = isset($metadata['error_no']) ? trim((string) $metadata['error_no']) : '';
            if ($message !== '' && strcasecmp($message, 'Ok') !== 0 && ($code >= 400 || $errorNo !== '' || $code === 0)) {
                $messages[] = $message . ($errorNo !== '' ? ' (' . $errorNo . ')' : '');
            }
        }

        if (isset($value['message']) && is_scalar($value['message'])) {
            $message = trim((string) $value['message']);
            if ($message !== '' && strcasecmp($message, 'Ok') !== 0) $messages[] = $message;
        }

        foreach ($value as $child) {
            if (is_array($child)) $this->_collectEKlaimErrors($child, $messages);
        }
    }

    private function _isTransientGroupingException(\Throwable $e)
    {
        return $this->_isTransientGroupingMessage($e->getMessage());
    }

    private function _isTransientGroupingMessage($value)
    {
        $message = strtolower((string) $value);
        return strpos($message, 'koneksi e-klaim gagal') !== false
            || strpos($message, 'timed out') !== false
            || strpos($message, 'timeout') !== false
            || strpos($message, 'batas waktu total') !== false
            // HTTP berhasil tetapi envelope terenkripsi sesaat tidak dapat
            // diverifikasi adalah gangguan protokol/transport, bukan penolakan
            // bisnis koding. Tetap dibatasi maksimal tiga percobaan oleh worker.
            || strpos($message, 'respons e-klaim tidak valid atau tidak dapat didekripsi') !== false;
    }

    /**
     * Simpan snapshot hasil grouping yang berhasil. Rekap bersifat audit trail:
     * pengiriman ulang membuat revision_no baru dan tidak menimpa hasil lama.
     * Kegagalan pencatatan tidak boleh membatalkan klaim yang sudah terkirim.
     */
    private function _saveGroupingRecap(array $context)
    {
        try {
            $pdo = $this->db()->pdo();
            $this->_ensureGroupingRecapTable($pdo);

            $noRawat = trim((string) ($context['no_rawat'] ?? ''));
            $nosep = trim((string) ($context['nosep'] ?? ''));
            if ($noRawat === '' || $nosep === '') {
                return ['ok' => false, 'message' => 'Rekap tidak disimpan: no_rawat atau SEP kosong'];
            }

            // Ambil ulang data klaim setelah final dan Kirim DC agar angka yang
            // disimpan sama dengan tabel Data Claim pada modal INACBG manual.
            $claimResponse = null;
            $claimReadError = null;
            try {
                $claimResponse = $this->Request(json_encode([
                    'metadata' => ['method' => 'get_claim_data'],
                    'data' => ['nomor_sep' => $nosep]
                ]));
            } catch (\Throwable $e) {
                $claimReadError = $e->getMessage();
            }

            $claimEnvelope = is_array($claimResponse) && isset($claimResponse['response'])
                && is_array($claimResponse['response'])
                ? $claimResponse['response']
                : $claimResponse;
            $claimData = is_array($claimEnvelope) && isset($claimEnvelope['data'])
                && is_array($claimEnvelope['data'])
                ? $claimEnvelope['data']
                : [];
            $grouper = isset($claimData['grouper']) && is_array($claimData['grouper'])
                ? $claimData['grouper']
                : [];
            $idrg = isset($grouper['response_idrg']) && is_array($grouper['response_idrg'])
                ? $grouper['response_idrg']
                : [];
            $inacbg = isset($grouper['response_inacbg']) && is_array($grouper['response_inacbg'])
                ? $grouper['response_inacbg']
                : [];
            if (!$idrg) {
                $idrg = $this->_groupingRecapFindArray(
                    $context['idrg_result'] ?? [],
                    ['drg_code', 'drg_description']
                );
            }
            if (!$inacbg) {
                $inacbg = $this->_groupingRecapFindArray(
                    $context['inacbg_result'] ?? [],
                    ['cbg', 'cbg_code', 'tariff']
                );
            }
            $tarifDetail = isset($claimData['tarif_rs']) && is_array($claimData['tarif_rs'])
                ? $claimData['tarif_rs']
                : [];

            // Jika get_claim_data sesaat gagal, tarif payload masih cukup untuk
            // rekap biaya RS; response mentah tetap menyimpan alasan kegagalannya.
            if (!$tarifDetail) {
                $payload = isset($context['payload']) && is_array($context['payload'])
                    ? $context['payload']
                    : [];
                $tarifMap = [
                    'prosedur_non_bedah', 'prosedur_bedah', 'konsultasi', 'tenaga_ahli',
                    'keperawatan', 'penunjang', 'radiologi', 'laboratorium',
                    'pelayanan_darah', 'rehabilitasi', 'kamar', 'rawat_intensif',
                    'obat', 'obat_kronis', 'obat_kemoterapi', 'alkes', 'bmhp',
                    'sewa_alat', 'tarif_poli_eks'
                ];
                foreach ($tarifMap as $tarifField) {
                    $tarifDetail[$tarifField] = $payload[$tarifField] ?? 0;
                }
            }

            $tarifRs = 0.0;
            foreach ($tarifDetail as $tarifValue) {
                $money = $this->_groupingRecapMoney($tarifValue);
                if ($money !== null) $tarifRs += $money;
            }
            $tarifInacbg = $this->_groupingRecapMoney($inacbg['tariff'] ?? ($inacbg['tarif'] ?? null));
            $tarifIdrg = $this->_groupingRecapMoney(
                $idrg['tariff'] ?? ($idrg['tarif'] ?? ($idrg['base_tariff'] ?? null))
            );

            $source = $this->activeGroupingJobId !== null ? 'setstatus' : 'manual';
            $requestedBy = (string) $this->core->getUserInfo('username', null, true);
            if ($this->activeGroupingJobId !== null) {
                $queue = $this->db('mlite_vedika_grouping_queue')
                    ->where('id', $this->activeGroupingJobId)
                    ->oneArray();
                if ($queue && isset($queue['requested_by'])) {
                    $requestedBy = (string) $queue['requested_by'];
                }
            }

            $revisionStmt = $pdo->prepare(
                'SELECT COALESCE(MAX(revision_no), 0) + 1
                 FROM mlite_vedika_grouping_recap WHERE no_rawat = ? AND nosep = ?'
            );
            $revisionStmt->execute([$noRawat, $nosep]);
            $revision = max(1, (int) $revisionStmt->fetchColumn());

            $dcResult = isset($context['datacenter_result']) && is_array($context['datacenter_result'])
                ? $context['datacenter_result']
                : [];
            $dcResponse = isset($dcResult['response']) && is_array($dcResult['response'])
                ? $dcResult['response']
                : [];
            $dcStatus = isset($dcResponse['metadata']['message'])
                ? (string) $dcResponse['metadata']['message']
                : (!empty($dcResult['skipped']) ? 'Klaim sudah pernah dikirim ke Data Center' : 'Berhasil');
            $claimStatus = (string) ($claimData['klaim_status_cd']
                ?? ($claimData['bpjs_klaim_status_nm'] ?? ($claimData['bpjs_klaim_status_cd'] ?? '')));

            if ($claimReadError !== null) {
                $claimResponse = ['recap_read_error' => $claimReadError];
            }

            $insert = $pdo->prepare(
                'INSERT INTO mlite_vedika_grouping_recap
                (no_rawat, nosep, revision_no, source, jenis_rawat, requested_by, coder_nik,
                 diagnosa_idrg, prosedur_idrg, diagnosa_inacbg, prosedur_inacbg,
                 idrg_code, idrg_description, inacbg_code, inacbg_description,
                 tarif_rs, tarif_idrg, tarif_inacbg, selisih_inacbg_rs, tarif_rs_detail,
                 dc_status, claim_status, claim_data_json, idrg_result_json,
                 inacbg_result_json, dc_result_json, payload_json, created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())'
            );
            $insert->execute([
                $noRawat,
                $nosep,
                $revision,
                $source,
                ($context['jenis_rawat'] ?? '') === 'Ranap' ? 'Ranap' : 'Ralan',
                substr($requestedBy, 0, 50),
                substr((string) ($context['coder_nik'] ?? ''), 0, 50),
                (string) ($context['diagnosa_idrg'] ?? ''),
                (string) ($context['prosedur_idrg'] ?? ''),
                (string) ($context['diagnosa_inacbg'] ?? ''),
                (string) ($context['prosedur_inacbg'] ?? ''),
                (string) ($idrg['drg_code'] ?? ($idrg['code'] ?? '')),
                (string) ($idrg['drg_description'] ?? ($idrg['description'] ?? '')),
                (string) (($inacbg['cbg']['code'] ?? ($inacbg['cbg_code'] ?? ''))),
                (string) (($inacbg['cbg']['description'] ?? ($inacbg['cbg_description'] ?? ''))),
                $tarifRs,
                $tarifIdrg,
                $tarifInacbg,
                $tarifInacbg !== null ? $tarifInacbg - $tarifRs : null,
                $this->_groupingRecapJson($tarifDetail),
                substr($dcStatus, 0, 100),
                substr($claimStatus, 0, 100),
                $this->_groupingRecapJson($claimResponse),
                $this->_groupingRecapJson($context['idrg_result'] ?? []),
                $this->_groupingRecapJson($context['inacbg_result'] ?? []),
                $this->_groupingRecapJson($dcResult),
                $this->_groupingRecapJson($context['payload'] ?? [])
            ]);

            return [
                'ok' => true,
                'id' => (int) $pdo->lastInsertId(),
                'revision_no' => $revision,
                'source' => $source
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Klaim berhasil, tetapi rekap grouping gagal: ' . $e->getMessage()];
        }
    }

    private function _ensureGroupingRecapTable(\PDO $pdo)
    {
        static $ready = false;
        if ($ready) return;
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS mlite_vedika_grouping_recap (
              id bigint unsigned NOT NULL AUTO_INCREMENT,
              no_rawat varchar(17) NOT NULL, nosep varchar(30) NOT NULL,
              revision_no int unsigned NOT NULL DEFAULT 1,
              source enum('setstatus','manual') NOT NULL,
              jenis_rawat enum('Ralan','Ranap') NOT NULL,
              requested_by varchar(50) DEFAULT NULL, coder_nik varchar(50) DEFAULT NULL,
              diagnosa_idrg text, prosedur_idrg text, diagnosa_inacbg text, prosedur_inacbg text,
              idrg_code varchar(30) DEFAULT NULL, idrg_description varchar(255) DEFAULT NULL,
              inacbg_code varchar(30) DEFAULT NULL, inacbg_description varchar(255) DEFAULT NULL,
              tarif_rs decimal(18,2) NOT NULL DEFAULT 0.00,
              tarif_idrg decimal(18,2) DEFAULT NULL, tarif_inacbg decimal(18,2) DEFAULT NULL,
              selisih_inacbg_rs decimal(18,2) DEFAULT NULL, tarif_rs_detail longtext,
              dc_status varchar(100) DEFAULT NULL, claim_status varchar(100) DEFAULT NULL,
              claim_data_json longtext, idrg_result_json longtext, inacbg_result_json longtext,
              dc_result_json longtext, payload_json longtext,
              created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_vedika_grouping_revision (no_rawat,nosep,revision_no),
              KEY idx_vedika_grouping_sep (nosep), KEY idx_vedika_grouping_created (created_at),
              KEY idx_vedika_grouping_source (source,jenis_rawat)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC"
        );
        $ready = true;
    }

    private function _groupingRecapMoney($value)
    {
        if ($value === null || $value === '') return null;
        if (is_int($value) || is_float($value)) return (float) $value;
        $normalized = preg_replace('/[^0-9.\-]/', '', (string) $value);
        return $normalized === '' || $normalized === '-' ? null : (float) $normalized;
    }

    private function _groupingRecapJson($value)
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        return $json === false ? '{}' : $json;
    }

    private function _groupingRecapFindArray($value, array $keys)
    {
        if (!is_array($value)) return [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $value)) return $value;
        }
        foreach ($value as $child) {
            if (!is_array($child)) continue;
            $found = $this->_groupingRecapFindArray($child, $keys);
            if ($found) return $found;
        }
        return [];
    }

    private function _resolveCoderNik()
    {
        if (isset($_POST['coder_nik']) && trim((string) $_POST['coder_nik']) !== '') {
            return $this->validTeks(trim((string) $_POST['coder_nik']));
        }

        $username = $this->core->getUserInfo('username', null, true);
        $pegawai = $this->db('pegawai')->where('nik', $username)->oneArray();
        return isset($pegawai['no_ktp']) ? (string) $pegawai['no_ktp'] : '';
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
                $idrg[] = $code;
                $imOnly[] = $code;
                $inacbgCode = $this->_mapIMDiagnosisToInacbg($code, ['im' => '1']);
                if ($inacbgCode !== '') $inacbg[] = $inacbgCode;
            } else {
                // Non IM → keduanya
                $idrg[]   = $code;
                $inacbg[] = $code;
            }
        }
    
        return [
            'idrg'   => implode('#', array_values(array_unique($idrg))),
            'inacbg' => implode('#', array_values(array_unique($inacbg))),
            'im_codes' => implode('#', $imOnly)
        ];
    }

    private function _mapIMDiagnosisToInacbg($code, array $row = [])
    {
        static $cache = [];
        $code = strtoupper(trim((string) $code));
        if ($code === '' || !preg_match('/^([A-Z][0-9]{2})\.([0-9])([0-9])$/', $code, $match)) return '';
        if (array_key_exists($code, $cache)) return $cache[$code];
        $isIM = isset($row['im']) ? (string) $row['im'] === '1' : false;
        if (!array_key_exists('im', $row)) {
            $child = $this->db('penyakit')->where('kd_penyakit', $code)->oneArray();
            $isIM = $child && isset($child['im']) && (string) $child['im'] === '1';
        }
        if (!$isIM) return $cache[$code] = '';
        $parentCode = $match[1] . '.' . $match[2];
        $parent = $this->db('penyakit')->where('kd_penyakit', $parentCode)->oneArray();
        if (!$parent || !isset($parent['validcode']) || trim((string) $parent['validcode']) !== '0') return $cache[$code] = '';
        return $cache[$code] = $parentCode;
    }

    private function _isDiagnosisUsableForCoding(array $row)
    {
        $code = isset($row['kd_penyakit']) ? $row['kd_penyakit'] : (isset($row['kode']) ? $row['kode'] : '');
        return (isset($row['validcode']) && trim((string) $row['validcode']) === '1')
            || $this->_mapIMDiagnosisToInacbg($code, $row) !== '';
    }

    private function _onlyIMDiagnosisMessage($codes)
    {
        $list = array_values(array_filter(array_map('trim', explode('#', (string) $codes))));
        $label = count($list) ? ' (' . implode(', ', $list) . ')' : '';
        return 'Diagnosis INACBG kosong: seluruh diagnosis yang dipilih adalah kode IM' . $label
            . '. Kode IM hanya berlaku untuk grouping IDRG dan tidak dikirim ke INACBG. '
            . 'Tambahkan minimal satu diagnosis non-IM yang sesuai dengan dokumentasi klinis, lalu kirim ulang.';
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
            if ($this->_mapIMDiagnosisToInacbg(isset($row['kd_penyakit']) ? $row['kd_penyakit'] : '', $row) !== '') return null;
            $codes[] = $row['kd_penyakit'];
        }
        return $this->_onlyIMDiagnosisMessage(implode('#', $codes));
    }

    private function _diagnosisRowsOnlyIM(array $rows)
    {
        if (!$rows) return false;
        foreach ($rows as $row) {
            if (!isset($row['im']) || (string) $row['im'] !== '1') return false;
            $code = isset($row['kd_penyakit']) ? $row['kd_penyakit'] : (isset($row['kode']) ? $row['kode'] : '');
            if ($this->_mapIMDiagnosisToInacbg($code, $row) !== '') return false;
        }
        return true;
    }

    private function _validateCodingRows(array $diagnoses, array $procedures)
    {
        $diagnosisIssues = [];
        $procedureIssues = [];
        $invalidDiagnosis = [];
        $invalidProcedure = [];
        $hasPrimaryDiagnosis = false;
        $allDiagnosesIM = !empty($diagnoses);

        if (!$diagnoses) {
            $diagnosisIssues[] = 'Diagnosis belum diisi';
        }

        foreach ($diagnoses as $row) {
            $code = isset($row['kd_penyakit']) ? trim((string) $row['kd_penyakit']) : '';
            $priority = isset($row['prioritas']) ? (string) $row['prioritas'] : '';
            $valid = $this->_isDiagnosisUsableForCoding($row);
            if ($priority === '1') {
                $hasPrimaryDiagnosis = true;
                if (isset($row['accpdx']) && strtoupper(trim((string) $row['accpdx'])) === 'N') {
                    $diagnosisIssues[] = ($code !== '' ? $code . ': ' : '') . 'hanya boleh sebagai diagnosis sekunder';
                }
            }
            if (!$valid && $code !== '') $invalidDiagnosis[] = $code;
            if (!isset($row['im']) || (string) $row['im'] !== '1') $allDiagnosesIM = false;
        }

        if ($diagnoses && !$hasPrimaryDiagnosis) {
            $diagnosisIssues[] = 'Diagnosis utama belum ditentukan';
        }
        if ($invalidDiagnosis) {
            $diagnosisIssues[] = 'ICD tidak sesuai: ' . implode(', ', array_unique($invalidDiagnosis));
        }
        if ($allDiagnosesIM) {
            $diagnosisIssues[] = 'Semua diagnosis adalah kode IM; tambahkan diagnosis non-IM untuk INACBG';
        }

        foreach ($procedures as $row) {
            $code = isset($row['kode']) ? trim((string) $row['kode']) : '';
            $valid = isset($row['validcode']) && (string) $row['validcode'] === '1';
            if (!$valid && $code !== '') $invalidProcedure[] = $code;
        }
        if ($invalidProcedure) {
            $procedureIssues[] = 'ICD tidak sesuai: ' . implode(', ', array_unique($invalidProcedure));
        }

        return [
            'ok' => !$diagnosisIssues && !$procedureIssues,
            'diagnosis_message' => implode('; ', $diagnosisIssues),
            'procedure_message' => implode('; ', $procedureIssues)
        ];
    }

    private function _validateEpisodeCoding($noRawat)
    {
        $status = (string) $this->core->getRegPeriksaInfo('status_lanjut', $noRawat);
        $diagnoses = $this->db('diagnosa_pasien')
            ->join('penyakit', 'penyakit.kd_penyakit = diagnosa_pasien.kd_penyakit')
            ->where('diagnosa_pasien.no_rawat', $noRawat)
            ->where('diagnosa_pasien.status', $status)
            ->asc('diagnosa_pasien.prioritas')
            ->toArray();
        $procedures = $this->db('prosedur_pasien')
            ->join('icd9', 'icd9.kode = prosedur_pasien.kode')
            ->where('prosedur_pasien.no_rawat', $noRawat)
            ->where('prosedur_pasien.status', $status)
            ->asc('prosedur_pasien.prioritas')
            ->toArray();
        return $this->_validateCodingRows($diagnoses, $procedures);
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

    /**
     * Membandingkan kelas hak SEP (bridging_sep.klsrawat) dengan kelas kamar
     * yang dipakai pasien. Nilai SEP biasanya berupa 1/2/3, sedangkan tabel
     * kamar menyimpan teks seperti "Kelas 1".
     */
    private function _validateKelasRawat($kelasSep, $kelasKamar)
    {
      $normalize = function ($value) {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/^kelas\\s*/', '', $value);
        return trim($value);
      };

      $kelasSep = trim((string) $kelasSep);
      $kelasKamar = trim((string) $kelasKamar);
      $sepNormalized = $normalize($kelasSep);
      $kamarNormalized = $normalize($kelasKamar);
      $mismatch = false;
      // Nomor kelas lebih kecil berarti kelas kamar lebih tinggi:
      // Kelas 1 > Kelas 2 > Kelas 3. Hanya kamar yang lebih rendah
      // dari hak SEP yang dianggap tidak sesuai. Upgrade kamar tetap valid.
      if ($sepNormalized !== '' && $kamarNormalized !== ''
        && ctype_digit($sepNormalized) && ctype_digit($kamarNormalized)) {
        $mismatch = (int) $kamarNormalized > (int) $sepNormalized;
      }

      return [
        'mismatch' => $mismatch,
        'alert' => $mismatch
          ? 'Kelas SEP tidak sesuai kamar: SEP Kelas ' . $kelasSep . ', kamar ' . $kelasKamar
          : ''
      ];
    }

    private function _getKelasRawatValidation($no_rawat)
    {
      $sep = $this->db('bridging_sep')
        ->where('no_rawat', $no_rawat)
        ->where('jnspelayanan', '1')
        ->desc('tglsep')
        ->oneArray();
      $kamarInap = $this->db('kamar_inap')
        ->where('no_rawat', $no_rawat)
        ->desc('tgl_keluar')
        ->oneArray();
      $kamar = $kamarInap && !empty($kamarInap['kd_kamar'])
        ? $this->db('kamar')->where('kd_kamar', $kamarInap['kd_kamar'])->oneArray()
        : [];

      return $this->_validateKelasRawat(
        $sep['klsrawat'] ?? '',
        $kamar['kelas'] ?? ''
      );
    }

    public function getRingkasanMedis($status_lanjut, $no_rawat)
    {
      $no_rawat = revertNoRawat($no_rawat);

      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(
        $this->_codingState($no_rawat, $status_lanjut),
        JSON_UNESCAPED_UNICODE
      );
      exit();
    }

    private function _codingSuccessResponse($message, $noRawat, $status)
    {
      return $this->jsonResponse(array_merge(
        ['ok' => true, 'message' => $message],
        $this->_codingState($noRawat, $status)
      ));
    }

    private function _codingState($no_rawat, $status_lanjut)
    {
      if (!in_array($status_lanjut, ['Ralan', 'Ranap'], true)) {
        return [
          'diagnosa' => [],
          'prosedur' => [],
          'validation' => [
            'blocked' => true,
            'diagnosis_message' => 'Status layanan tidak valid',
            'procedure_message' => '',
            'only_im' => false
          ],
          'setstatus_url' => ''
        ];
      }

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

      $codingValidation = $this->_validateCodingRows($diagnosa, $prosedur);
      $regPeriksa = $this->db('reg_periksa')
        ->where('no_rawat', $no_rawat)
        ->oneArray();
      $noRkmMedis = $regPeriksa && isset($regPeriksa['no_rkm_medis'])
        ? $regPeriksa['no_rkm_medis']
        : '';
      $requiredDocumentAlerts = $this->_getRequiredDocumentAlerts($no_rawat, $noRkmMedis, $diagnosa, $prosedur);
      $kelasRawatValidation = $status_lanjut === 'Ranap'
        ? $this->_getKelasRawatValidation($no_rawat)
        : ['mismatch' => false, 'alert' => ''];
      $jenisPelayanan = $status_lanjut === 'Ranap' ? '1' : '2';
      $sep = $this->db('bridging_sep')
        ->where('no_rawat', $no_rawat)
        ->where('jnspelayanan', $jenisPelayanan)
        ->desc('tglsep')
        ->oneArray();
      $nosep = $sep && isset($sep['no_sep']) ? trim((string) $sep['no_sep']) : '';

      return [
        'diagnosa' => $hasilDiagnosa,
        'prosedur' => $hasilProsedur,
        'validation' => [
          'blocked' => !$codingValidation['ok'] || !empty($requiredDocumentAlerts) || $kelasRawatValidation['mismatch'],
          'kelas_rawat_mismatch' => $kelasRawatValidation['mismatch'],
          'kelas_rawat_message' => $kelasRawatValidation['alert'],
          'diagnosis_message' => $codingValidation['diagnosis_message'],
          'procedure_message' => $codingValidation['procedure_message'],
          'document_message' => implode('; ', $requiredDocumentAlerts),
          'only_im' => $this->_diagnosisRowsOnlyIM($diagnosa)
        ],
        'required_document_alerts' => $requiredDocumentAlerts,
        'setstatus_url' => $nosep !== ''
          ? url([ADMIN, 'vedika', 'setstatus', $nosep])
          : ''
      ];
    }

}
