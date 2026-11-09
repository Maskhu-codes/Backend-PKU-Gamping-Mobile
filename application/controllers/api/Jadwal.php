<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use chriskacerguis\RestServer\RestController;

class Jadwal extends RestController {

    public function __construct() {
        parent::__construct();
        $this->load->helper('jwt');
        $this->load->database();
    }

    private function _verify() {
        $headers = $this->input->get_request_header('Authorization');
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                $token = $matches[1];
                $decoded = verify_jwt($token);
                if ($decoded) {
                    return $decoded;
                }
            }
        }
        $this->response(['status' => false, 'message' => 'Unauthorized Access'], RestController::HTTP_UNAUTHORIZED);
        return false;
    }

    public function list_get() {
        // if (!$this->_verify()) return; // Endpoint public (tidak perlu login)

        $fs_kd_layanan = $this->get('fs_kd_layanan');
        $fs_kd_petugas_medis = $this->get('fs_kd_petugas_medis');

        if (empty($fs_kd_layanan) || empty($fs_kd_petugas_medis)) {
            $this->response(['status' => false, 'message' => 'fs_kd_layanan and fs_kd_petugas_medis are required'], RestController::HTTP_BAD_REQUEST);
            return;
        }

        $hari_ini = date("Y-m-d");
        $next_day = date('Y-m-d', strtotime('+1 days', strtotime($hari_ini)));
        $tgl_terakhir = date('Y-m-d', strtotime('+45 days', strtotime($hari_ini)));

        $query2 = "SELECT * FROM HOSPITAL.dbo.TA_JADWAL_DOKTER a 
                   JOIN dbo.if_get_kalender('$next_day','$tgl_terakhir') b ON a.FN_HARI=b.fn_hari
                   WHERE a.FS_KD_DOKTER = '$fs_kd_petugas_medis' AND fs_kd_layanan='$fs_kd_layanan'
                   ORDER BY b.fd_tgl ASC";
                   
        $result = $this->db->query($query2)->result_array();

        $jadwal_list = [];
        foreach ($result as $data2) {
            $tgl = $data2['fd_tgl'];
            $fn_hari = $data2['fn_hari'];

            // Cek pasien terdaftar
            $q_daftar = $this->db->query("SELECT COUNT(fs_kd_trs) fn_daftar FROM ta_trs_booking WHERE fd_tgl_void='3000-01-01' AND fs_kd_layanan='$fs_kd_layanan' AND fs_kd_petugas_medis='$fs_kd_petugas_medis' AND fd_tgl_periksa='$tgl'")->row_array();
            $fn_daftar = $q_daftar ? $q_daftar['fn_daftar'] : 0;

            // Cek maksimal pasien
            $q_max = $this->db->query("SELECT FN_MAX FROM TA_JADWAL_DOKTER WHERE (FS_KD_LAYANAN = '$fs_kd_layanan') AND (FS_KD_DOKTER = '$fs_kd_petugas_medis') AND (FN_HARI = '$fn_hari')")->row_array();
            $fn_max = $q_max ? $q_max['FN_MAX'] : 0;

            // Cek Poli Tutup
            $q_closed = $this->db->query("SELECT aa.fs_kd_trs FROM ta_trs_closed_poli aa INNER JOIN ta_trs_closed_poli2 bb ON aa.fs_kd_trs = bb.fs_kd_trs WHERE aa.fs_kd_layanan = '$fs_kd_layanan' AND aa.fs_kd_petugas_medis = '$fs_kd_petugas_medis' AND aa.fd_tgl_void = '3000-01-01' AND bb.fd_tgl_closed = '$tgl'")->row_array();
            
            $is_closed = !empty($q_closed['fs_kd_trs']);
            $is_full = ($fn_daftar >= $fn_max);
            $can_book = (!$is_closed && !$is_full);

            $data2['is_closed'] = $is_closed;
            $data2['is_full'] = $is_full;
            $data2['can_book'] = $can_book;
            $data2['terdaftar'] = $fn_daftar;

            $jadwal_list[] = $data2;
        }

        $this->response([
            'status' => true,
            'data' => $jadwal_list
        ], RestController::HTTP_OK);
    }
}
