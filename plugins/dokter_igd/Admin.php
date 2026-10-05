<?php
namespace Plugins\Dokter_Igd;

use Systems\AdminModule;
use Plugins\Icd\DB_ICD;

class Admin extends AdminModule
{

    private $_uploads = WEBAPPS_PATH.'/berkasrawat/pages/upload';
    private function canDeleteOwnedRow($table, $conditions)
    {
      $row = $this->db($table);
      foreach ($conditions as $field => $value) {
        $row = $row->where($field, $value);
      }
      $row = $row->oneArray();
      if (!$row) return false;
      $username = (string) $this->core->getUserInfo('username', null, true);
      foreach (['kd_dokter', 'dokter_perujuk', 'nik', 'nip'] as $owner) {
        if (isset($row[$owner]) && (string) $row[$owner] === $username) return true;
      }
      return false;
    }
    public function navigation()
    {
        return [
            'Kelola'   => 'manage',
        ];
    }

    public function anyManage()
    {
        $tgl_kunjungan = date('Y-m-d');
        $tgl_kunjungan_akhir = date('Y-m-d');
        $status_periksa = '';

        if(isset($_POST['periode_rawat_jalan'])) {
          $tgl_kunjungan = $_POST['periode_rawat_jalan'];
        }
        if(isset($_POST['periode_rawat_jalan_akhir'])) {
          $tgl_kunjungan_akhir = $_POST['periode_rawat_jalan_akhir'];
        }
        if(isset($_POST['status_periksa'])) {
          $status_periksa = $_POST['status_periksa'];
        }
        $cek_vclaim = $this->db('mlite_modules')->where('dir', 'vclaim')->oneArray();
        $this->_Display($tgl_kunjungan, $tgl_kunjungan_akhir, $status_periksa);
        return $this->draw('manage.html', ['rawat_jalan' => $this->assign, 'cek_vclaim' => $cek_vclaim, 'admin_mode' => $this->settings->get('settings.admin_mode')]);
    }

    public function anyDisplay()
    {
        $tgl_kunjungan = date('Y-m-d');
        $tgl_kunjungan_akhir = date('Y-m-d');
        $status_periksa = '';

        if(isset($_POST['periode_rawat_jalan'])) {
          $tgl_kunjungan = $_POST['periode_rawat_jalan'];
        }
        if(isset($_POST['periode_rawat_jalan_akhir'])) {
          $tgl_kunjungan_akhir = $_POST['periode_rawat_jalan_akhir'];
        }
        if(isset($_POST['status_periksa'])) {
          $status_periksa = $_POST['status_periksa'];
        }
        $cek_vclaim = $this->db('mlite_modules')->where('dir', 'vclaim')->oneArray();
        $this->_Display($tgl_kunjungan, $tgl_kunjungan_akhir, $status_periksa);
        echo $this->draw('display.html', ['rawat_jalan' => $this->assign, 'cek_vclaim' => $cek_vclaim, 'admin_mode' => $this->settings->get('settings.admin_mode')]);
        exit();
    }

    public function _Display($tgl_kunjungan, $tgl_kunjungan_akhir, $status_periksa='')
    {
        $this->_addHeaderFiles();

        $this->assign['poliklinik']     = $this->db('poliklinik')->where('status', '1')->toArray();
        $this->assign['dokter']         = $this->db('dokter')->where('status', '1')->toArray();
        $this->assign['penjab']       = $this->db('penjab')->where('status', '1')->toArray();
        $this->assign['no_rawat'] = '';
        $this->assign['no_reg']     = '';
        $this->assign['tgl_registrasi']= date('Y-m-d');
        $this->assign['jam_reg']= date('H:i:s');

        $igd = $this->settings('settings', 'igd');
        $sql = "SELECT reg_periksa.*,
            pasien.*,
            dokter.*,
            poliklinik.*,
            penjab.*
          FROM reg_periksa, pasien, dokter, poliklinik, penjab
          WHERE reg_periksa.no_rkm_medis = pasien.no_rkm_medis
          AND reg_periksa.kd_poli = '$igd'
          AND reg_periksa.tgl_registrasi BETWEEN '$tgl_kunjungan' AND '$tgl_kunjungan_akhir'
          AND reg_periksa.kd_dokter = dokter.kd_dokter
          AND reg_periksa.kd_poli = poliklinik.kd_poli
          AND reg_periksa.kd_pj = penjab.kd_pj";

        if($status_periksa == 'belum') {
          $sql .= " AND reg_periksa.stts = 'Belum'";
        }
        if($status_periksa == 'selesai') {
          $sql .= " AND reg_periksa.stts = 'Sudah'";
        }
        if($status_periksa == 'lunas') {
          $sql .= " AND reg_periksa.status_bayar = 'Sudah Bayar'";
        }

        $stmt = $this->db()->pdo()->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $this->assign['list'] = [];
        foreach ($rows as $row) {
          $this->assign['list'][] = $row;
        }

    }

    public function postSaveDetail()
    {
      if($_POST['kat'] == 'tindakan') {
        $jns_perawatan = $this->db('jns_perawatan')->where('kd_jenis_prw', $_POST['kd_jenis_prw'])->oneArray();
        $this->db('rawat_jl_dr')->save([
          'no_rawat' => $_POST['no_rawat'],
          'kd_jenis_prw' => $_POST['kd_jenis_prw'],
          'kd_dokter' => $this->core->getUserInfo('username', null, true),
          'tgl_perawatan' => $_POST['tgl_perawatan'],
          'jam_rawat' => $_POST['jam_rawat'],
          'material' => $jns_perawatan['material'],
          'bhp' => $jns_perawatan['bhp'],
          'tarif_tindakandr' => $jns_perawatan['tarif_tindakandr'],
          'kso' => $jns_perawatan['kso'],
          'menejemen' => $jns_perawatan['menejemen'],
          'biaya_rawat' => $jns_perawatan['total_byrdr'],
          'stts_bayar' => 'Belum'
        ]);
      }
      if($_POST['kat'] == 'obat') {

          $no_resep = $this->core->setNoResep($_POST['tgl_perawatan']);
          $cek_resep = $this->db('resep_obat')->where('no_rawat', $_POST['no_rawat'])->where('tgl_peresepan', $_POST['tgl_perawatan'])->where('tgl_perawatan', '0000-00-00')->where('status', 'ralan')->oneArray();

          if(empty($cek_resep)) {

            $resep_obat = $this->db('resep_obat')
              ->save([
                'no_resep' => $no_resep,
                'tgl_perawatan' => '0000-00-00',
                'jam' => '00:00:00',
                'no_rawat' => $_POST['no_rawat'],
                'kd_dokter' => $this->core->getUserInfo('username', null, true),
                'tgl_peresepan' => $_POST['tgl_perawatan'],
                'jam_peresepan' => $_POST['jam_rawat'],
                'status' => 'ralan',
                'tgl_penyerahan' => '0000-00-00',
                'jam_penyerahan' => '00:00:00'
              ]);
            if ($this->db('resep_obat')->where('no_resep', $no_resep)->where('kd_dokter', $this->core->getUserInfo('username', null, true))->oneArray()) {
              $this->db('resep_dokter')
                ->save([
                  'no_resep' => $no_resep,
                  'kode_brng' => $_POST['kd_jenis_prw'],
                  'jml' => $_POST['jml'],
                  'aturan_pakai' => $_POST['aturan_pakai']
                ]);
            }

          } else {

            $no_resep = $cek_resep['no_resep'];

            $this->db('resep_dokter')
              ->save([
                'no_resep' => $no_resep,
                'kode_brng' => $_POST['kd_jenis_prw'],
                'jml' => $_POST['jml'],
                'aturan_pakai' => $_POST['aturan_pakai']
              ]);

          }

      }

      if($_POST['kat'] == 'racikan') {

        $no_resep = $this->core->setNoResep($_POST['tgl_perawatan']);
        $cek_resep = $this->db('resep_obat')->where('no_rawat', $_POST['no_rawat'])->where('tgl_peresepan', $_POST['tgl_perawatan'])->where('tgl_perawatan', '0000-00-00')->where('status', 'ralan')->oneArray();

        $_POST['jam_rawat'] = date('H:i:s');

        if(empty($cek_resep)) {

          $resep_obat = $this->db('resep_obat')
            ->save([
              'no_resep' => $no_resep,
              'tgl_perawatan' => '0000-00-00',
              'jam' => '00:00:00',
              'no_rawat' => $_POST['no_rawat'],
              'kd_dokter' => $this->core->getUserInfo('username', null, true),
              'tgl_peresepan' => $_POST['tgl_perawatan'],
              'jam_peresepan' => $_POST['jam_rawat'],
              'status' => 'ralan',
              'tgl_penyerahan' => '0000-00-00',
              'jam_penyerahan' => '00:00:00'
            ]);

          if ($this->db('resep_obat')->where('no_resep', $no_resep)->where('kd_dokter', $this->core->getUserInfo('username', null, true))->oneArray()) {
            $no_racik = $this->db('resep_dokter_racikan')->where('no_resep', $no_resep)->count();
            $no_racik = $no_racik+1;
            $this->db('resep_dokter_racikan')
              ->save([
                'no_resep' => $no_resep,
                'no_racik' => $no_racik,
                'nama_racik' => $_POST['nama_racik'],
                'kd_racik' => $_POST['kd_jenis_prw'],
                'jml_dr' => $_POST['jml'],
                'aturan_pakai' => $_POST['aturan_pakai'],
                'keterangan' => $_POST['keterangan']
              ]);
            $_POST['kode_brng'] = json_decode($_POST['kode_brng'], true);
            $_POST['kandungan'] = json_decode($_POST['kandungan'], true);
            for ($i = 0; $i < count($_POST['kode_brng']); $i++) {
              $kapasitas = $this->db('databarang')->where('kode_brng', $_POST['kode_brng'][$i]['value'])->oneArray();
              $jml = $_POST['jml']*$_POST['kandungan'][$i]['value'];
              $jml = round(($jml/$kapasitas['kapasitas']),1);
              $this->db('resep_dokter_racikan_detail')
                ->save([
                  'no_resep' => $no_resep,
                  'no_racik' => $no_racik,
                  'kode_brng' => $_POST['kode_brng'][$i]['value'],
                  'p1' => '1',
                  'p2' => '1',
                  'kandungan' => $_POST['kandungan'][$i]['value'],
                  'jml' => $jml
                ]);
            }
          }

        } else {

          $no_resep = $cek_resep['no_resep'];

          $no_racik = $this->db('resep_dokter_racikan')->where('no_resep', $no_resep)->count();
          $no_racik = $no_racik+1;
          $this->db('resep_dokter_racikan')
            ->save([
              'no_resep' => $no_resep,
              'no_racik' => $no_racik,
              'nama_racik' => $_POST['nama_racik'],
              'kd_racik' => $_POST['kd_jenis_prw'],
              'jml_dr' => $_POST['jml'],
              'aturan_pakai' => $_POST['aturan_pakai'],
              'keterangan' => $_POST['keterangan']
            ]);
          $_POST['kode_brng'] = json_decode($_POST['kode_brng'], true);
          $_POST['kandungan'] = json_decode($_POST['kandungan'], true);
          for ($i = 0; $i < count($_POST['kode_brng']); $i++) {
            $kapasitas = $this->db('databarang')->where('kode_brng', $_POST['kode_brng'][$i]['value'])->oneArray();
            $jml = $_POST['jml']*$_POST['kandungan'][$i]['value'];
            $jml = round(($jml/$kapasitas['kapasitas']),1);
            $this->db('resep_dokter_racikan_detail')
              ->save([
                'no_resep' => $no_resep,
                'no_racik' => $no_racik,
                'kode_brng' => $_POST['kode_brng'][$i]['value'],
                'p1' => '1',
                'p2' => '1',
                'kandungan' => $_POST['kandungan'][$i]['value'],
                'jml' => $jml
              ]);
          }

        }

      }

      if($_POST['kat'] == 'laboratorium') {
        $tgl_permintaan_lab = !empty($_POST['tgl_perawatan']) ? $_POST['tgl_perawatan'] : date('Y-m-d');
        $dokter_perujuk_lab = $this->core->getUserInfo('username', null, true);
        if (!$this->db('dokter')->where('kd_dokter', $dokter_perujuk_lab)->oneArray()) {
          $reg_lab = $this->db('reg_periksa')->where('no_rawat', $_POST['no_rawat'])->oneArray();
          if ($reg_lab && $this->db('dokter')->where('kd_dokter', $reg_lab['kd_dokter'])->oneArray()) $dokter_perujuk_lab = $reg_lab['kd_dokter'];
        }
        $cek_lab = $this->db('permintaan_lab')->where('no_rawat', $_POST['no_rawat'])->where('tgl_permintaan', $tgl_permintaan_lab)->where('tgl_sampel', '0000-00-00')->where('status', 'ralan')->oneArray();
        if(!$cek_lab) {
          $max_id = $this->db('permintaan_lab')->select(['noorder' => 'ifnull(MAX(CONVERT(RIGHT(noorder,4),signed)),0)'])->where('tgl_permintaan', $tgl_permintaan_lab)->oneArray();
          if(empty($max_id['noorder'])) {
            $max_id['noorder'] = '0000';
          }
          $_next_noorder = sprintf('%04s', ($max_id['noorder'] + 1));
          $noorder = 'PK'.str_replace('-', '', $tgl_permintaan_lab).$_next_noorder;

          $permintaan_lab = $this->db('permintaan_lab')
            ->save([
              'noorder' => $noorder,
              'no_rawat' => $_POST['no_rawat'],
              'tgl_permintaan' => $tgl_permintaan_lab,
              'jam_permintaan' => $_POST['jam_rawat'],
              'tgl_sampel' => '0000-00-00',
              'jam_sampel' => '00:00:00',
              'tgl_hasil' => '0000-00-00',
              'jam_hasil' => '00:00:00',
              'dokter_perujuk' => $dokter_perujuk_lab,
              'status' => 'ralan',
              'informasi_tambahan' => trim((string) ($_POST['informasi_tambahan'] ?? '')) ?: '-',
              'diagnosa_klinis' => trim((string) ($_POST['diagnosa_klinis'] ?? '')) ?: '-',
              'tambahan' => 'Tidak Cito'
            ]);
          $this->db('permintaan_lab_order')->save(['noorder'=>$noorder,'jam_permintaan'=>$tgl_permintaan_lab.' '.($_POST['jam_rawat'] ?? '00:00:00'),'jam_sampel'=>$tgl_permintaan_lab.' 00:00:00','jam_hasil'=>$tgl_permintaan_lab.' 00:00:00']);
          $this->db('permintaan_pemeriksaan_lab')
            ->save([
              'noorder' => $noorder,
              'kd_jenis_prw' => $_POST['kd_jenis_prw'],
              'stts_bayar' => 'Belum'
            ]);
          $template_laboratorium = $this->db('template_laboratorium')->where('kd_jenis_prw', $_POST['kd_jenis_prw'])->toArray();
          for ($i = 0; $i < count($template_laboratorium); $i++) {
            $this->db('permintaan_detail_permintaan_lab')
              ->save([
                'noorder' => $noorder,
                'kd_jenis_prw' => $_POST['kd_jenis_prw'],
                'id_template' => $template_laboratorium[$i]['id_template'],
                'stts_bayar' => 'Belum'
              ]);
          }
        } else {
          $noorder = $cek_lab['noorder'];
          $this->db('permintaan_pemeriksaan_lab')
            ->save([
              'noorder' => $noorder,
              'kd_jenis_prw' => $_POST['kd_jenis_prw'],
              'stts_bayar' => 'Belum'
            ]);
          $template_laboratorium = $this->db('template_laboratorium')->where('kd_jenis_prw', $_POST['kd_jenis_prw'])->toArray();
          for ($i = 0; $i < count($template_laboratorium); $i++) {
            $this->db('permintaan_detail_permintaan_lab')
              ->save([
                'noorder' => $noorder,
                'kd_jenis_prw' => $_POST['kd_jenis_prw'],
                'id_template' => $template_laboratorium[$i]['id_template'],
                'stts_bayar' => 'Belum'
              ]);
          }
        }
      }

      if($_POST['kat'] == 'radiologi') {
        $tgl_permintaan_rad = !empty($_POST['tgl_perawatan']) ? $_POST['tgl_perawatan'] : date('Y-m-d');
        $dokter_perujuk_rad = $this->core->getUserInfo('username', null, true);
        if (!$this->db('dokter')->where('kd_dokter', $dokter_perujuk_rad)->oneArray()) {
          $reg_rad = $this->db('reg_periksa')->where('no_rawat', $_POST['no_rawat'])->oneArray();
          if ($reg_rad && $this->db('dokter')->where('kd_dokter', $reg_rad['kd_dokter'])->oneArray()) $dokter_perujuk_rad = $reg_rad['kd_dokter'];
        }
        $cek_rad = $this->db('permintaan_radiologi')->where('no_rawat', $_POST['no_rawat'])->where('tgl_permintaan', $tgl_permintaan_rad)->where('tgl_sampel', '0000-00-00')->where('status', 'ralan')->oneArray();
        if(!$cek_rad) {
          $max_id = $this->db('permintaan_radiologi')->select(['noorder' => 'ifnull(MAX(CONVERT(RIGHT(noorder,4),signed)),0)'])->where('tgl_permintaan', $tgl_permintaan_rad)->oneArray();
          if(empty($max_id['noorder'])) {
            $max_id['noorder'] = '0000';
          }
          $_next_noorder = sprintf('%04s', ($max_id['noorder'] + 1));
          $noorder = 'PR'.str_replace('-', '', $tgl_permintaan_rad).$_next_noorder;

          $permintaan_rad = $this->db('permintaan_radiologi')
            ->save([
              'noorder' => $noorder,
              'no_rawat' => $_POST['no_rawat'],
              'tgl_permintaan' => $tgl_permintaan_rad,
              'jam_permintaan' => $_POST['jam_rawat'],
              'tgl_sampel' => '0000-00-00',
              'jam_sampel' => '00:00:00',
              'tgl_hasil' => '0000-00-00',
              'jam_hasil' => '00:00:00',
              'dokter_perujuk' => $dokter_perujuk_rad,
              'status' => 'ralan',
              'informasi_tambahan' => trim((string) ($_POST['informasi_tambahan'] ?? '')) ?: '-',
              'diagnosa_klinis' => trim((string) ($_POST['diagnosa_klinis'] ?? '')) ?: '-',
              'tambahan' => 'No Alarm'
            ]);
          $this->db('permintaan_pemeriksaan_radiologi')
            ->save([
              'noorder' => $noorder,
              'kd_jenis_prw' => $_POST['kd_jenis_prw'],
              'stts_bayar' => 'Belum'
            ]);

        } else {
          $noorder = $cek_rad['noorder'];
          $this->db('permintaan_pemeriksaan_radiologi')
            ->save([
              'noorder' => $noorder,
              'kd_jenis_prw' => $_POST['kd_jenis_prw'],
              'stts_bayar' => 'Belum'
            ]);
        }
      }

      exit();
    }

    public function postHapusDetail()
    {
      $table = in_array($_POST['provider'] ?? '', ['rawat_jl_dr','rawat_jl_pr','rawat_jl_drpr'], true) ? $_POST['provider'] : '';
      $conditions = [
        'no_rawat' => $_POST['no_rawat'],
        'kd_jenis_prw' => $_POST['kd_jenis_prw'],
        'tgl_perawatan' => $_POST['tgl_perawatan'],
        'jam_rawat' => $_POST['jam_rawat']
      ];
      if (!$table) {
        http_response_code(403);
        exit();
      }

      // Tindakan dokter & perawat adalah satu transaksi gabungan. Dokter IGD
      // boleh menghapus transaksi ini walaupun dibuat oleh user lain, tetapi
      // tetap pastikan targetnya memang ada agar tidak menerima kondisi bebas.
      if ($table === 'rawat_jl_drpr') {
        $target = $this->db($table);
        foreach ($conditions as $field => $value) {
          $target = $target->where($field, $value);
        }
        if (!$target->oneArray()) {
          http_response_code(403);
          exit();
        }
      } elseif (!$this->canDeleteOwnedRow($table, $conditions)) {
        http_response_code(403);
        exit();
      }
      if($_POST['provider'] == 'rawat_jl_dr') {
        $this->db('rawat_jl_dr')
        ->where('no_rawat', $_POST['no_rawat'])
        ->where('kd_jenis_prw', $_POST['kd_jenis_prw'])
        ->where('tgl_perawatan', $_POST['tgl_perawatan'])
        ->where('jam_rawat', $_POST['jam_rawat'])
        ->delete();
      }
      if($_POST['provider'] == 'rawat_jl_pr') {
        $this->db('rawat_jl_pr')
        ->where('no_rawat', $_POST['no_rawat'])
        ->where('kd_jenis_prw', $_POST['kd_jenis_prw'])
        ->where('tgl_perawatan', $_POST['tgl_perawatan'])
        ->where('jam_rawat', $_POST['jam_rawat'])
        ->delete();
      }
      if($_POST['provider'] == 'rawat_jl_drpr') {
        $this->db('rawat_jl_drpr')
        ->where('no_rawat', $_POST['no_rawat'])
        ->where('kd_jenis_prw', $_POST['kd_jenis_prw'])
        ->where('tgl_perawatan', $_POST['tgl_perawatan'])
        ->where('jam_rawat', $_POST['jam_rawat'])
        ->delete();
      }
      exit();
    }

    public function postHapusResep()
    {
      $ownerConditions = ['no_resep' => $_POST['no_resep'], 'no_rawat' => $_POST['no_rawat'] ?? ''];
      if (!$this->canDeleteOwnedRow('resep_obat', $ownerConditions)) { http_response_code(403); exit(); }
      if(isset($_POST['kd_jenis_prw'])) {
        $this->db('resep_dokter')
        ->where('no_resep', $_POST['no_resep'])
        ->where('kode_brng', $_POST['kd_jenis_prw'])
        ->delete();
      } else {
        $this->db('resep_obat')
        ->where('no_resep', $_POST['no_resep'])
        ->where('no_rawat', $_POST['no_rawat'])
        ->where('tgl_peresepan', $_POST['tgl_peresepan'])
        ->where('jam_peresepan', $_POST['jam_peresepan'])
        ->delete();
      }

      exit();
    }

    public function anyCopyResep()
    {
      $return = $this->db('resep_dokter')
        ->join('databarang', 'databarang.kode_brng=resep_dokter.kode_brng')
        ->join('gudangbarang', 'gudangbarang.kode_brng=resep_dokter.kode_brng')
        ->where('kd_bangsal', $this->settings->get('farmasi.deporalan'))
        ->where('no_resep', $_POST['no_resep'])
        ->toArray();
      echo $this->draw('copyresep.display.html', ['copy_resep' => $return]);
      exit();
    }

    public function postSaveCopyResep()
    {
      $_POST['kode_brng'] = json_decode($_POST['kode_brng'], true);
      $_POST['jml'] = json_decode($_POST['jml'], true);
      $_POST['aturan_pakai'] = json_decode($_POST['aturan_pakai'], true);

      $no_resep = $this->core->setNoResep($_POST['tgl_perawatan']);

      $resep_obat = $this->db('resep_obat')
        ->save([
          'no_resep' => $no_resep,
          'tgl_perawatan' => '0000-00-00',
          'jam' => '00:00:00',
          'no_rawat' => $_POST['no_rawat'],
          'kd_dokter' => $this->core->getUserInfo('username', null, true),
          'tgl_peresepan' => $_POST['tgl_perawatan'],
          'jam_peresepan' => $_POST['jam_rawat'],
          'status' => 'ralan',
          'tgl_penyerahan' => '0000-00-00',
          'jam_penyerahan' => '00:00:00'
        ]);

      for ($i = 0; $i < count($_POST['kode_brng']); $i++) {
        /*$cek_stok = $this->db('gudangbarang')
          ->join('databarang', 'databarang.kode_brng=gudangbarang.kode_brng')
          ->where('gudangbarang.kode_brng', $_POST['kode_brng'][$i]['value'])
          ->where('kd_bangsal', $this->settings->get('farmasi.deporalan'))
          ->oneArray();*/

        //if($cek_stok['stok'] < $cek_stok['stokminimal']) {
        //  echo "Error";
        //} else {
          $this->db('resep_dokter')
            ->save([
              'no_resep' => $no_resep,
              'kode_brng' => $_POST['kode_brng'][$i]['value'],
              'jml' => $_POST['jml'][$i]['value'],
              'aturan_pakai' => $_POST['aturan_pakai'][$i]['value']
            ]);
        //}

      }

      exit();
    }

    public function anyRincian()
    {
      $currentUser = (string) $this->core->getUserInfo('username', null, true);
      $rows_rawat_jl_dr = $this->db('rawat_jl_dr')->where('no_rawat', $_POST['no_rawat'])->toArray();
      $rows_rawat_jl_pr = $this->db('rawat_jl_pr')->where('no_rawat', $_POST['no_rawat'])->toArray();
      $rows_rawat_jl_drpr = $this->db('rawat_jl_drpr')->where('no_rawat', $_POST['no_rawat'])->toArray();

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
          $row['can_delete'] = ((string) ($row['kd_dokter'] ?? '') === $currentUser);
          $rawat_jl_dr[] = $row;
        }
      }

      if($rows_rawat_jl_pr) {
        foreach ($rows_rawat_jl_pr as $row) {
          $jns_perawatan = $this->db('jns_perawatan')->where('kd_jenis_prw', $row['kd_jenis_prw'])->oneArray();
          $row['nm_perawatan'] = $jns_perawatan['nm_perawatan'];
          $jumlah_total = $jumlah_total + $row['biaya_rawat'];
          $row['provider'] = 'rawat_jl_pr';
          $row['can_delete'] = ((string) ($row['kd_dokter'] ?? $row['nip'] ?? '') === $currentUser);
          $rawat_jl_pr[] = $row;
        }
      }

      if($rows_rawat_jl_drpr) {
        foreach ($rows_rawat_jl_drpr as $row) {
          $jns_perawatan = $this->db('jns_perawatan')->where('kd_jenis_prw', $row['kd_jenis_prw'])->oneArray();
          $row['nm_perawatan'] = $jns_perawatan['nm_perawatan'];
          $jumlah_total = $jumlah_total + $row['biaya_rawat'];
          $row['provider'] = 'rawat_jl_drpr';
          // Provider gabungan dapat dihapus oleh dokter IGD meski bukan
          // pembuat awal tindakan.
          $row['can_delete'] = true;
          $rawat_jl_drpr[] = $row;
        }
      }

      $rows = $this->db('resep_obat')
        ->join('dokter', 'dokter.kd_dokter=resep_obat.kd_dokter')
        ->join('resep_dokter', 'resep_dokter.no_resep=resep_obat.no_resep')
        ->where('no_rawat', $_POST['no_rawat'])
        ->group('resep_dokter.no_resep')
        ->toArray();
      $resep = [];
      $jumlah_total_resep = 0;
      foreach ($rows as $row) {
        $row['nomor'] = $i++;
        $row['can_delete'] = ((string) ($row['kd_dokter'] ?? '') === $currentUser);
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
        ->where('no_rawat', $_POST['no_rawat'])
        ->group('resep_dokter_racikan.no_resep')
        ->toArray();
      $resep_racikan = [];
      $jumlah_total_resep_racikan = 0;
      foreach ($rows_racikan as $row) {
        $row['nomor'] = $i++;
        $row['can_delete'] = ((string) ($row['kd_dokter'] ?? '') === $currentUser);
        $row['resep_dokter_racikan_detail'] = $this->db('resep_dokter_racikan_detail')->join('databarang', 'databarang.kode_brng=resep_dokter_racikan_detail.kode_brng')->where('no_resep', $row['no_resep'])->toArray();
        foreach ($row['resep_dokter_racikan_detail'] as $value) {
          $value['ralan'] = $value['jml'] * $value['ralan'];
          $jumlah_total_resep_racikan += floatval($value['ralan']);
        }
        $resep_racikan[] = $row;
      }

      $rows_laboratorium = $this->db('permintaan_lab')
        ->join('dokter', 'dokter.kd_dokter=permintaan_lab.dokter_perujuk')
        ->where('no_rawat', $_POST['no_rawat'])
        ->where('permintaan_lab.status', 'ralan')
        ->toArray();
      $laboratorium = [];
      foreach ($rows_laboratorium as $row) {
        $row['can_delete'] = ((string) ($row['dokter_perujuk'] ?? '') === $currentUser);
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
        ->where('no_rawat', $_POST['no_rawat'])
        ->where('permintaan_radiologi.status', 'ralan')
        ->toArray();
      $jumlah_total_rad = 0;
      $radiologi = [];

      if($rows_radiologi) {
        foreach ($rows_radiologi as $row) {
          $row['can_delete'] = ((string) ($row['dokter_perujuk'] ?? '') === $currentUser);
          $jns_perawatan = $this->db('jns_perawatan_radiologi')->where('kd_jenis_prw', $row['kd_jenis_prw'])->oneArray();
          $row['nm_perawatan'] = $jns_perawatan['nm_perawatan'];
          $row['kelas'] = $jns_perawatan['kelas'];
          $row['total_byr'] = $jns_perawatan['total_byr'];
          $jumlah_total_rad += $jns_perawatan['total_byr'];
          $radiologi[] = $row;
        }
      }

      $reg_periksa = $this->db('reg_periksa')->where('no_rawat', $_POST['no_rawat'])->oneArray();
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
        'no_rawat' => $_POST['no_rawat']
      ]);
      exit();
    }

    public function anyRincianEresep()
    {
      $i = 1;

      $rows = $this->db('resep_obat')
        ->join('dokter', 'dokter.kd_dokter=resep_obat.kd_dokter')
        ->join('resep_dokter', 'resep_dokter.no_resep=resep_obat.no_resep')
        ->where('no_rawat', $_POST['no_rawat'])
        ->where('resep_obat.status', 'ralan')
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
        ->where('no_rawat', $_POST['no_rawat'])
        ->group('resep_dokter_racikan.no_resep')
        ->where('resep_obat.status', 'ralan')
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

      $reg_periksa = $this->db('reg_periksa')->where('no_rawat', $_POST['no_rawat'])->oneArray();
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

      echo $this->draw('rincian.eresep.html', [
        'resep' => $resep,
        'resep_racikan' => $resep_racikan,
        'data_resep' => $data_resep,
        'jumlah_total_resep' => $jumlah_total_resep,
        'jumlah_total_resep_racikan' => $jumlah_total_resep_racikan,
        'no_rawat' => $_POST['no_rawat']
      ]);
      exit();
    }

    public function postHapusNomorPermintaanLaboratorium()
    {
      if (!$this->canDeleteOwnedRow('permintaan_lab', ['no_rawat'=>$_POST['no_rawat'], 'noorder'=>$_POST['noorder']])) { http_response_code(403); exit(); }
      $this->db('permintaan_lab')
      ->where('no_rawat', $_POST['no_rawat'])
      ->where('noorder', $_POST['noorder'])
      ->where('tgl_permintaan', $_POST['tgl_permintaan'])
      ->where('jam_permintaan', $_POST['jam_permintaan'])
      ->where('status', 'Ralan')
      ->delete();
      exit();
    }

    public function postHapusPermintaanLaboratorium()
    {
      $parent = $this->db('permintaan_lab')->where('noorder', $_POST['noorder'])->oneArray();
      if (!$parent || !$this->canDeleteOwnedRow('permintaan_lab', ['noorder'=>$_POST['noorder']])) { http_response_code(403); exit(); }
      $this->db('permintaan_pemeriksaan_lab')
      ->where('noorder', $_POST['noorder'])
      ->where('kd_jenis_prw', $_POST['kd_jenis_prw'])
      ->where('stts_bayar', 'Belum')
      ->delete();
      exit();
    }

    public function getDetailPermintaan($noorder, $kd_jenis_prw)
    {
      $rows = $this->db('permintaan_detail_permintaan_lab')->where('noorder', $noorder)->where('kd_jenis_prw', $kd_jenis_prw)->toArray();
      $detail_permintaan_lab = [];
      foreach ($rows as $row) {
        $row['template_laboratorium'] = $this->db('template_laboratorium')->where('kd_jenis_prw', $row['kd_jenis_prw'])->where('id_template', $row['id_template'])->oneArray();
        $detail_permintaan_lab[] = $row;
      }
      $this->tpl->set('detail', $detail_permintaan_lab);
      echo $this->tpl->draw(MODULES.'/dokter_igd/view/admin/details.html', true);
      exit();
    }

    public function postHapusDetailPermintaan()
    {
      if (!$this->canDeleteOwnedRow('permintaan_lab', ['noorder'=>$_POST['noorder']])) { http_response_code(403); exit(); }
      $this->db('permintaan_detail_permintaan_lab')
        ->where('noorder', $_POST['noorder'])
        ->where('kd_jenis_prw', $_POST['kd_jenis_prw'])
        ->where('id_template', $_POST['id_template'])
        ->delete();
      exit();
    }

    public function postHapusPermintaanRadiologi()
    {
      if (!$this->canDeleteOwnedRow('permintaan_radiologi', ['noorder'=>$_POST['noorder'], 'no_rawat'=>$_POST['no_rawat']])) { http_response_code(403); exit(); }
      $this->db('permintaan_pemeriksaan_radiologi')->where('noorder', $_POST['noorder'])->delete();
      $this->db('permintaan_radiologi')->where('noorder', $_POST['noorder'])->where('no_rawat', $_POST['no_rawat'])->delete();
      exit();
    }

    public function anyTriase()
    {
      $no_rawat = $_POST['no_rawat'] ?? $_GET['no_rawat'] ?? '';
      $data = [
        'igd' => $this->db('data_triase_igd')->where('no_rawat', $no_rawat)->oneArray(),
        'primer' => $this->db('data_triase_igdprimer')->where('no_rawat', $no_rawat)->oneArray(),
        'sekunder' => $this->db('data_triase_igdsekunder')->where('no_rawat', $no_rawat)->oneArray(),
        // Vital sign juga dapat diisi lebih dulu melalui asesmen medis.
        'asesmen' => $this->db('asesmen_medis_igd')->where('no_rawat', $no_rawat)->oneArray(),
        'master' => []
      ];
      foreach ([1,2,3,4,5] as $skala) {
        $data['master']['skala'.$skala] = $this->db('master_triase_skala'.$skala)
          ->join('master_triase_pemeriksaan', 'master_triase_pemeriksaan.kode_pemeriksaan=master_triase_skala'.$skala.'.kode_pemeriksaan')
          ->toArray();
        $data['terpilih']['skala'.$skala] = $this->db('data_triase_igddetail_skala'.$skala)->where('no_rawat', $no_rawat)->toArray();
      }
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode($data);
      exit();
    }

    public function postSaveTriase()
    {
      $no_rawat = trim((string) ($_POST['no_rawat'] ?? ''));
      if ($no_rawat === '') { http_response_code(422); echo json_encode(['ok'=>false,'message'=>'Nomor rawat kosong.']); exit(); }
      $value = function($key, $default = '-') { $v = trim((string) ($_POST[$key] ?? '')); return $v === '' ? $default : $v; };
      $triaseDate = trim((string) ($_POST['triase_datetime'] ?? ''));
      if ($triaseDate !== '') {
        $triaseDate = str_replace('T', ' ', $triaseDate);
        $triaseDate = substr($triaseDate, 0, 19);
        $parsedTriaseDate = strtotime($triaseDate);
        if ($parsedTriaseDate === false) $triaseDate = date('Y-m-d H:i:s');
        else $triaseDate = date('Y-m-d H:i:s', $parsedTriaseDate);
      } else {
        $triaseDate = date('Y-m-d H:i:s');
      }
      $nip = (string) $this->core->getUserInfo('username', null, true);
      $jenis = ($_POST['jenis_triase'] ?? 'primer') === 'sekunder' ? 'sekunder' : 'primer';
      $kd_dokter = $nip;
      if (!$this->db('dokter')->where('kd_dokter', $kd_dokter)->oneArray()) {
        $reg_triase = $this->db('reg_periksa')->where('no_rawat', $no_rawat)->oneArray();
        if ($reg_triase && $this->db('dokter')->where('kd_dokter', $reg_triase['kd_dokter'])->oneArray()) $kd_dokter = $reg_triase['kd_dokter'];
      }
      $base = [
        'no_rawat'=>$no_rawat, 'tgl_kunjungan'=>$triaseDate, 'cara_masuk'=>$value('cara_masuk', 'Jalan'),
        'alat_transportasi'=>$value('alat_transportasi', 'Sendiri'), 'alasan_kedatangan'=>$value('alasan_kedatangan'),
        'keterangan_kedatangan'=>$value('keterangan_kedatangan'), 'kode_kasus'=>$value('kode_kasus', '002'),
        'tekanan_darah'=>$value('tekanan_darah'), 'nadi'=>$value('nadi'), 'pernapasan'=>$value('pernapasan'),
        'bb'=>$value('bb'), 'suhu'=>$value('suhu'), 'saturasi_o2'=>$value('saturasi_o2'), 'nyeri'=>$value('nyeri'),
        'emergency'=>$value('emergency', 'True Emergency'), 'nip'=>$nip
      ];
      $this->db('data_triase_igd')->save($base);
      if ($jenis === 'primer') {
        $this->db('data_triase_igdprimer')->save([
          'no_rawat'=>$no_rawat, 'keluhan_utama'=>$value('keluhan_utama'), 'kebutuhan_khusus'=>$value('kebutuhan_khusus'),
          'catatan'=>$value('catatan'), 'plan'=>$value('plan', 'Ruang Resusitasi'), 'tanggaltriase'=>$triaseDate, 'kd_dokter'=>$kd_dokter
        ]);
      } else {
        $this->db('data_triase_igdsekunder')->save([
          'no_rawat'=>$no_rawat, 'anamnesa_singkat'=>$value('anamnesa_singkat'), 'catatan'=>$value('catatan'),
          'plan'=>$value('plan', 'Zona Kuning'), 'tanggaltriase'=>$triaseDate, 'kd_dokter'=>$kd_dokter
        ]);
      }
      foreach ([1,2,3,4,5] as $skala) {
        $table = 'data_triase_igddetail_skala'.$skala;
        $this->db($table)->where('no_rawat', $no_rawat)->delete();
        $items = json_decode($_POST['skala'.$skala] ?? '[]', true);
        if (is_array($items)) foreach ($items as $kode) {
          if (trim((string) $kode) !== '') $this->db($table)->save(['no_rawat'=>$no_rawat, 'kode_skala'.$skala=>trim((string) $kode)]);
        }
      }
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(['ok'=>true, 'jenis'=>$jenis]);
      exit();
    }

    public function anyAsesmenMedisIgd()
    {
      $no_rawat = trim((string) ($_POST['no_rawat'] ?? $_GET['no_rawat'] ?? ''));
      $data = $this->db('asesmen_medis_igd')->where('no_rawat', $no_rawat)->oneArray();
      // Jika triase dikerjakan lebih dulu, gunakan vital sign-nya sebagai nilai awal asesmen.
      $triase = $this->db('data_triase_igd')->where('no_rawat', $no_rawat)->oneArray();
      $image = $this->db('asesmen_medis_igd_image_marking')->where('no_rawat', $no_rawat)->oneArray();
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(['ok'=>true, 'asesmen'=>$data ?: [], 'triase'=>$triase ?: [], 'image'=>$image ?: []]);
      exit();
    }

    public function postSaveAsesmenMedisIgd()
    {
      $no_rawat = trim((string) ($_POST['no_rawat'] ?? ''));
      if ($no_rawat === '') { http_response_code(422); echo json_encode(['ok'=>false,'message'=>'Nomor rawat kosong.']); exit(); }
      $required = ['anamnesis','keadaan','kesadaran','kepala','mata','gigi','tht','thoraks','jantung','paru','abdomen','genital','ekstremitas','kulit'];
      $value = function($key, $default = '') { $v = trim((string) ($_POST[$key] ?? '')); return $v === '' ? $default : $v; };
      $reg = $this->db('reg_periksa')->where('no_rawat', $no_rawat)->oneArray();
      if (!$reg) { http_response_code(404); echo json_encode(['ok'=>false,'message'=>'Registrasi tidak ditemukan.']); exit(); }

      $imageUrl = '';
      $imageData = trim((string) ($_POST['image_data'] ?? ''));
      if ($imageData !== '') {
        if (!defined('API_WEBSITE_URL') || !defined('API_WEBSITE_KEY') || API_WEBSITE_URL === '' || API_WEBSITE_KEY === '') {
          http_response_code(500); echo json_encode(['ok'=>false,'message'=>'Konfigurasi API website belum diisi.']); exit();
        }
        $post = http_build_query(['no_rawat'=>$no_rawat, 'image_data'=>$imageData, 'token'=>API_WEBSITE_KEY]);
        $ch = curl_init(API_WEBSITE_URL);
        curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$post, CURLOPT_HTTPHEADER=>['X-API-Key: '.API_WEBSITE_KEY, 'Content-Type: application/x-www-form-urlencoded'], CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT=>8, CURLOPT_TIMEOUT=>30, CURLOPT_SSL_VERIFYPEER=>true]);
        $response = curl_exec($ch); $curlError = curl_error($ch); $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $decoded = is_string($response) ? json_decode($response, true) : null;
        if ($curlError || $httpCode < 200 || $httpCode >= 300 || !is_array($decoded) || empty($decoded['ok'])) {
          http_response_code(502); echo json_encode(['ok'=>false,'message'=>'Upload marking ke api-website gagal.','detail'=>$curlError ?: ($decoded['error'] ?? 'Respons API tidak valid.')]); exit();
        }
        $imageUrl = (string) ($decoded['url_image'] ?? '');
      }

      $now = date('Y-m-d H:i:s');
      $asesmen = [
        'no_rawat'=>$no_rawat, 'tanggal'=>date('Y-m-d'), 'jam'=>date('H:i:s'), 'kd_dokter'=>(string)($reg['kd_dokter'] ?? ''),
        'anamnesis'=>$value('anamnesis','Autoanamnesis'), 'hubungan'=>$value('hubungan'), 'keluhan_utama'=>$value('keluhan_utama'),
        'rps'=>$value('rps'), 'rpd'=>$value('rpd'), 'rpk'=>$value('rpk'), 'rpo'=>$value('rpo'), 'alergi'=>$value('alergi'),
        'keadaan'=>$value('keadaan','Sehat'), 'gcs'=>$value('gcs'), 'kesadaran'=>$value('kesadaran','CM'), 'td'=>$value('td'),
        'nadi'=>$value('nadi'), 'rr'=>$value('rr'), 'suhu'=>$value('suhu'), 'spo'=>$value('spo'), 'bb'=>$value('bb'), 'tb'=>$value('tb'),
        'kepala'=>$value('kepala','Tidak Diperiksa'), 'mata'=>$value('mata','Tidak Diperiksa'), 'gigi'=>$value('gigi','Tidak Diperiksa'),
        'tht'=>$value('tht','Tidak Diperiksa'), 'thoraks'=>$value('thoraks','Tidak Diperiksa'), 'jantung'=>$value('jantung','Tidak Diperiksa'),
        'paru'=>$value('paru','Tidak Diperiksa'), 'abdomen'=>$value('abdomen','Tidak Diperiksa'), 'genital'=>$value('genital','Tidak Diperiksa'),
        'ekstremitas'=>$value('ekstremitas','Tidak Diperiksa'), 'kulit'=>$value('kulit','Tidak Diperiksa'), 'ket_fisik'=>$value('ket_fisik'),
        'ket_lokalis'=>$value('ket_lokalis'), 'lab'=>$value('lab'), 'rad'=>$value('rad'), 'penunjang'=>$value('penunjang'),
        'diagnosis'=>$value('diagnosis'), 'tata'=>$value('tata'), 'edukasi'=>$value('edukasi')
      ];
      $this->db('asesmen_medis_igd')->where('no_rawat', $no_rawat)->delete();
      $this->db('asesmen_medis_igd')->save($asesmen);
      if ($imageUrl !== '') {
        $this->db('asesmen_medis_igd_image_marking')->where('no_rawat', $no_rawat)->delete();
        $this->db('asesmen_medis_igd_image_marking')->save(['no_rawat'=>$no_rawat,'tanggal'=>date('Y-m-d'),'jam'=>date('H:i:s'),'url_image'=>$imageUrl]);
      }
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(['ok'=>true,'message'=>'Asesmen medis IGD berhasil disimpan.','image'=>$imageUrl]);
      exit();
    }

    public function anySoap()
    {

      $prosedurs = $this->db('prosedur_pasien')
         ->where('no_rawat', $_POST['no_rawat'])
         ->asc('prioritas')
         ->toArray();
       $prosedur = [];
       foreach ($prosedurs as $row) {
         $icd9 = $this->db('icd9')->where('kode', $row['kode'])->oneArray();
         $row['nama'] = $icd9['deskripsi_panjang'];
         $prosedur[] = $row;
       }
       $diagnosas = $this->db('diagnosa_pasien')
         ->where('no_rawat', $_POST['no_rawat'])
         ->asc('prioritas')
         ->toArray();
       $diagnosa = [];
       foreach ($diagnosas as $row) {
         $icd10 = $this->db('penyakit')->where('kd_penyakit', $row['kd_penyakit'])->oneArray();
         $row['nama'] = $icd10['nm_penyakit'];
         $diagnosa[] = $row;
       }

      $i = 1;
      $row['nama_petugas'] = '';
      $row['departemen_petugas'] = '';
      $rows = $this->db('pemeriksaan_ralan')
        ->where('no_rawat', $_POST['no_rawat'])
        ->toArray();
      $result = [];
      foreach ($rows as $row) {
        $row['nomor'] = $i++;
        $row['nama_petugas'] = $this->core->getPegawaiInfo('nama',$row['nip']);
        $row['departemen_petugas'] = $this->core->getDepartemenInfo($this->core->getPegawaiInfo('departemen',$row['nip']));
        $result[] = $row;
      }

      $rows_ranap = $this->db('pemeriksaan_ranap')
       ->where('no_rawat', $_POST['no_rawat'])
       ->toArray();
      $result_ranap = [];
      foreach ($rows_ranap as $row) {
       $row['nomor'] = $i++;
       $row['nama_petugas'] = $this->core->getPegawaiInfo('nama',$row['nip']);
       $row['departemen_petugas'] = $this->core->getDepartemenInfo($this->core->getPegawaiInfo('departemen',$row['nip']));
       $result_ranap[] = $row;
      }

      echo $this->draw('soap.html', ['pemeriksaan' => $result, 'pemeriksaan_ranap' => $result_ranap, 'diagnosa' => $diagnosa, 'prosedur' => $prosedur, 'admin_mode' => $this->settings->get('settings.admin_mode')]);
      exit();
    }

    public function postSaveSOAP()
    {
      $_POST['nip'] = $this->core->getUserInfo('username', null, true);

      if(!$this->db('pemeriksaan_ralan')->where('no_rawat', $_POST['no_rawat'])->where('tgl_perawatan', $_POST['tgl_perawatan'])->where('jam_rawat', $_POST['jam_rawat'])->oneArray()) {
        $this->db('pemeriksaan_ralan')->save($_POST);
      } else {
        $this->db('pemeriksaan_ralan')->where('no_rawat', $_POST['no_rawat'])->where('tgl_perawatan', $_POST['tgl_perawatan'])->where('jam_rawat', $_POST['jam_rawat'])->save($_POST);
      }
      exit();
    }

    public function postHapusSOAP()
    {
      $this->db('pemeriksaan_ralan')->where('no_rawat', $_POST['no_rawat'])->where('tgl_perawatan', $_POST['tgl_perawatan'])->where('jam_rawat', $_POST['jam_rawat'])->delete();
      exit();
    }

    public function anyKontrol()
    {
      $rows = $this->db('booking_registrasi')
        ->select([
          'tanggal_periksa' => 'booking_registrasi.tanggal_periksa',
          'no_reg' => 'booking_registrasi.no_reg',
          'nm_poli' => 'poliklinik.nm_poli',
          'nm_dokter' => 'dokter.nm_dokter',
          'png_jawab' => 'penjab.png_jawab',
          'status' => 'booking_registrasi.status'
        ])
        ->join('poliklinik', 'poliklinik.kd_poli=booking_registrasi.kd_poli')
        ->join('dokter', 'dokter.kd_dokter=booking_registrasi.kd_dokter')
        ->join('penjab', 'penjab.kd_pj=booking_registrasi.kd_pj')
        ->where('no_rkm_medis', $_POST['no_rkm_medis'])
        ->toArray();
      $i = 1;
      $result = [];
      foreach ($rows as $row) {
        $row['nomor'] = $i++;
        $result[] = $row;
      }
      echo $this->draw('kontrol.html', ['booking_registrasi' => $result]);
      exit();
    }

    public function postSaveKontrol()
    {

      $query = $this->db('skdp_bpjs')->save([
        'tahun' => date('Y'),
        'no_rkm_medis' => $_POST['no_rkm_medis'],
        'diagnosa' => $_POST['diagnosa'],
        'terapi' => $_POST['terapi'],
        'alasan1' => $_POST['alasan1'],
        'alasan2' => '',
        'rtl1' => $_POST['rtl1'],
        'rtl2' => '',
        'tanggal_datang' => $_POST['tanggal_datang'],
        'tanggal_rujukan' => $_POST['tanggal_rujukan'],
        'no_antrian' => $this->core->setNoSKDP(),
        'kd_dokter' => $this->core->getUserInfo('username', null, true),
        'status' => 'Menunggu'
      ]);

      if ($query) {
        $this->db('booking_registrasi')
          ->save([
            'tanggal_booking' => date('Y-m-d'),
            'jam_booking' => date('H:i:s'),
            'no_rkm_medis' => $_POST['no_rkm_medis'],
            'tanggal_periksa' => $_POST['tanggal_datang'],
            'kd_dokter' => $this->core->getUserInfo('username', null, true),
            'kd_poli' => $this->core->getRegPeriksaInfo('kd_poli', $_POST['no_rawat']),
            'no_reg' => $this->core->setNoBooking($this->core->getUserInfo('username', null, true), $this->core->getRegPeriksaInfo('kd_poli', $_POST['no_rawat']), $_POST['tanggal_rujukan']),
            'kd_pj' => $this->core->getRegPeriksaInfo('kd_pj', $_POST['no_rawat']),
            'limit_reg' => 0,
            'waktu_kunjungan' => $_POST['tanggal_datang'].' '.date('H:i:s'),
            'status' => 'Belum'
          ]);
      }

      exit();
    }

    public function postHapusKontrol()
    {
      $this->db('pemeriksaan_ralan')->where('no_rawat', $_POST['no_rawat'])->delete();
      exit();
    }

    public function anyLayanan()
    {
      $layanan = $this->db('jns_perawatan')
        ->where('total_byrdr', '<>', '0')
        ->where('status', '1')
        ->like('nm_perawatan', '%'.$_POST['layanan'].'%')
        ->limit(10)
        ->toArray();
      echo $this->draw('layanan.html', ['layanan' => $layanan]);
      exit();
    }

    public function anyObat()
    {
      $obat = $this->db('databarang')
        ->join('gudangbarang', 'gudangbarang.kode_brng=databarang.kode_brng')
        ->where('status', '1')
        ->where('gudangbarang.kd_bangsal', $this->settings->get('farmasi.deporalan'))
        ->like('databarang.nama_brng', '%'.$_POST['obat'].'%')
        ->limit(10)
        ->toArray();
      echo $this->draw('obat.html', ['obat' => $obat]);
      exit();
    }

    public function anyObatRacikan()
    {
      $obat = $this->db('databarang')
        ->join('gudangbarang', 'gudangbarang.kode_brng=databarang.kode_brng')
        ->where('status', '1')
        ->where('gudangbarang.kd_bangsal', $this->settings->get('farmasi.deporalan'))
        ->like('databarang.nama_brng', '%'.$_POST['obat'].'%')
        ->limit(10)
        ->toArray();
      echo $this->draw('obat.racikan.html', ['obat' => $obat]);
      exit();
    }

    public function anyRacikan()
    {
      $racikan = $this->db('metode_racik')
        ->like('nm_racik', '%'.$_POST['racikan'].'%')
        ->toArray();
      echo $this->draw('racikan.html', ['racikan' => $racikan]);
      exit();
    }

    public function anyLaboratorium()
    {
      $laboratorium = $this->db('jns_perawatan_lab')
        ->where('status', '1')
        ->like('nm_perawatan', '%'.$_POST['laboratorium'].'%')
        ->limit(10)
        ->toArray();
      echo $this->draw('laboratorium.html', ['laboratorium' => $laboratorium]);
      exit();
    }

    public function anyRadiologi()
    {
      $radiologi = $this->db('jns_perawatan_radiologi')
        ->where('status', '1')
        ->like('nm_perawatan', '%'.$_POST['radiologi'].'%')
        ->limit(10)
        ->toArray();
      echo $this->draw('radiologi.html', ['radiologi' => $radiologi]);
      exit();
    }

    public function postAturanPakai()
    {

      if(isset($_POST["query"])){
        $output = '';
        $key = "%".$_POST["query"]."%";
        $rows = $this->db('master_aturan_pakai')->like('aturan', $key)->limit(10)->toArray();
        $output = '';
        if(count($rows)){
          foreach ($rows as $row) {
            $output .= '<li class="list-group-item link-class">'.$row["aturan"].'</li>';
          }
        }
        echo $output;
      }

      exit();

    }

    public function postProviderList()
    {

      if(isset($_POST["query"])){
        $output = '';
        $key = "%".$_POST["query"]."%";
        $rows = $this->db('dokter')->like('nm_dokter', $key)->where('status', '1')->limit(10)->toArray();
        $output = '';
        if(count($rows)){
          foreach ($rows as $row) {
            $output .= '<li class="list-group-item link-class">'.$row["kd_dokter"].': '.$row["nm_dokter"].'</li>';
          }
        }
        echo $output;
      }

      exit();

    }

    public function postProviderList2()
    {

      if(isset($_POST["query"])){
        $output = '';
        $key = "%".$_POST["query"]."%";
        $rows = $this->db('petugas')->like('nama', $key)->limit(10)->toArray();
        $output = '';
        if(count($rows)){
          foreach ($rows as $row) {
            $output .= '<li class="list-group-item link-class">'.$row["nip"].': '.$row["nama"].'</li>';
          }
        }
        echo $output;
      }

      exit();

    }

    public function getAjax()
    {
        header('Content-type: text/html');
        $show = isset($_GET['show']) ? $_GET['show'] : "";
        switch($show){
        	default:
          break;
          case "databarang":
          $rows = $this->db('databarang')
            ->join('gudangbarang', 'gudangbarang.kode_brng=databarang.kode_brng')
            ->where('status', '1')
            ->where('stok', '>', '10')
            ->where('gudangbarang.kd_bangsal', $this->settings->get('farmasi.deporalan'))
            ->like('databarang.nama_brng', '%'.$_GET['nama_brng'].'%')
            ->limit(10)
            ->toArray();

          foreach ($rows as $row) {
            $array[] = array(
                'kode_brng' => $row['kode_brng'],
                'nama_brng'  => $row['nama_brng']
            );
          }
          echo json_encode($array, true);
          break;
          case "aturan_pakai":
          $rows = $this->db('master_aturan_pakai')->like('aturan', '%'.$_GET['aturan'].'%')->toArray();
          foreach ($rows as $row) {
            $array[] = array(
                'aturan'  => $row['aturan']
            );
          }
          echo json_encode($array, true);
          break;
          case "jns_perawatan":
          $rows = $this->db('jns_perawatan')->like('nm_perawatan', '%'.$_GET['nm_perawatan'].'%')->toArray();
          foreach ($rows as $row) {
            $array[] = array(
                'kd_jenis_prw' => $row['kd_jenis_prw'],
                'nm_perawatan'  => $row['nm_perawatan']
            );
          }
          echo json_encode($array, true);
          break;
          case "jns_perawatan_lab":
          $rows = $this->db('jns_perawatan_lab')->like('nm_perawatan', '%'.$_GET['nm_perawatan'].'%')->toArray();
          foreach ($rows as $row) {
            $array[] = array(
                'kd_jenis_prw' => $row['kd_jenis_prw'],
                'nm_perawatan'  => $row['nm_perawatan']
            );
          }
          echo json_encode($array, true);
          break;
          case "jns_perawatan_radiologi":
          $rows = $this->db('jns_perawatan_radiologi')->like('nm_perawatan', '%'.$_GET['nm_perawatan'].'%')->toArray();
          foreach ($rows as $row) {
            $array[] = array(
                'kd_jenis_prw' => $row['kd_jenis_prw'],
                'nm_perawatan'  => $row['nm_perawatan']
            );
          }
          echo json_encode($array, true);
          break;
          case "icd10":
          $phrase = '';
          if(isset($_GET['s']))
            $phrase = $_GET['s'];

          $rows = $this->data_icd('icd10')->like('kode', '%'.$phrase.'%')->orLike('nama', '%'.$phrase.'%')->toArray();
          foreach ($rows as $row) {
            $array[] = array(
                'kd_penyakit' => $row['kode'],
                'nm_penyakit'  => $row['nama']
            );
          }
          echo json_encode($array, true);
          break;
          case "icd9":
          $phrase = '';
          if(isset($_GET['s']))
            $phrase = $_GET['s'];

          $rows = $this->data_icd('icd9')->like('kode', '%'.$phrase.'%')->orLike('nama', '%'.$phrase.'%')->toArray();
          foreach ($rows as $row) {
            $array[] = array(
                'kode' => $row['kode'],
                'deskripsi_panjang'  => $row['nama']
            );
          }
          echo json_encode($array, true);
          break;
        }
        exit();
    }

    public function getEresep($no_rawat)
    {
      $no_rawat = revertNorawat($no_rawat);
      $i = 1;

      $rows = $this->db('resep_obat')
        ->join('dokter', 'dokter.kd_dokter=resep_obat.kd_dokter')
        ->join('resep_dokter', 'resep_dokter.no_resep=resep_obat.no_resep')
        ->where('no_rawat', $no_rawat)
        ->where('resep_obat.status', 'ralan')
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
        ->where('resep_obat.status', 'ralan')
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

      $reg_periksa = $this->db('reg_periksa')->where('no_rawat', $no_rawat)->oneArray();
      $rows_data_resep = $this->db('resep_obat')
      ->join('reg_periksa', 'reg_periksa.no_rawat=resep_obat.no_rawat')
      ->where('resep_obat.kd_dokter', $this->core->getUserInfo('username', null, true))
      ->where('reg_periksa.no_rkm_medis', $this->core->getRegPeriksaInfo('no_rkm_medis', $no_rawat))
      ->toArray();

      $data_resep = [];
      foreach ($rows_data_resep as $row) {
        $row['resep_dokter'] = $this->db('resep_dokter')
          ->join('databarang', 'databarang.kode_brng=resep_dokter.kode_brng')
          ->where('no_resep', $row['no_resep'])
          ->toArray();
        $data_resep[] = $row;
      }

      echo $this->draw('eresep.html', [
        'resep' => $resep,
        'resep_racikan' => $resep_racikan,
        'data_resep' => $data_resep,
        'jumlah_total_resep' => $jumlah_total_resep,
        'jumlah_total_resep_racikan' => $jumlah_total_resep_racikan,
        'no_rawat' => $no_rawat
      ]);
      exit();
    }

    public function postCekWaktu()
    {
      echo date('H:i:s');
      exit();
    }

    public function getJavascript()
    {
        header('Content-type: text/javascript');
        $cek_pegawai = $this->db('pegawai')->where('nik', $this->core->getUserInfo('username', $_SESSION['mlite_user']))->oneArray();
        $cek_role = '';
        if($cek_pegawai) {
          $cek_role = $this->core->getPegawaiInfo('nik', $this->core->getUserInfo('username', $_SESSION['mlite_user']));
        }
        echo $this->draw(MODULES.'/dokter_igd/js/admin/dokter_igd.js', ['cek_role' => $cek_role]);
        exit();
    }

    public function getCss()
    {
        header('Content-type: text/css');
        echo $this->draw(MODULES.'/dokter_igd/css/admin/dokter_igd.css');
        exit();
    }

    private function _addHeaderFiles()
    {
        $this->core->addCSS(url('assets/css/dataTables.bootstrap.min.css'));
        $this->core->addJS(url('assets/jscripts/jquery.dataTables.min.js'));
        $this->core->addJS(url('assets/jscripts/dataTables.bootstrap.min.js'));
        $this->core->addCSS(url('assets/css/bootstrap-datetimepicker.css'));
        $this->core->addJS(url('assets/jscripts/moment-with-locales.js'));
        $this->core->addJS(url('assets/jscripts/bootstrap-datetimepicker.js'));
        $this->core->addCSS(url([ADMIN, 'dokter_igd', 'css']));
        $this->core->addJS(url([ADMIN, 'dokter_igd', 'javascript']), 'footer');
    }

    protected function data_icd($table)
    {
        return new DB_ICD($table);
    }

}
