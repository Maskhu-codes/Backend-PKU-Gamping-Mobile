<?php
defined('BASEPATH') or exit('No direct script access allowed');

use chriskacerguis\RestServer\RestController;

class Beranda extends RestController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('jwt');
        $this->load->database();
    }

    private function _verify()
    {
        $authorization = $this->input->get_request_header('Authorization');
        if ($authorization && preg_match('/Bearer\s+(\S+)/', $authorization, $matches)) {
            $decoded = verify_jwt($matches[1]);
            if ($decoded) {
                return $decoded;
            }
        }

        $this->response(['status' => false, 'message' => 'Unauthorized Access'], RestController::HTTP_UNAUTHORIZED);
        return false;
    }

    public function index_get()
    {
        $decoded = $this->_verify();
        if (!$decoded) {
            return;
        }

        $fs_mr = isset($decoded->data->fs_mr) ? normalize_patient_mr($decoded->data->fs_mr) : null;
        if (!$fs_mr) {
            $this->response(['status' => false, 'message' => 'Pasien belum memiliki RM'], RestController::HTTP_BAD_REQUEST);
            return;
        }

        $patient_query = $this->db->query(
            "SELECT FS_MR AS fs_mr, FS_NM_PASIEN AS fs_nm_pasien
             FROM TC_MR
             WHERE FS_MR = ?",
            [$fs_mr]
        );
        if (!$patient_query) {
            $this->response(['status' => false, 'message' => 'Gagal mengambil data pasien'], RestController::HTTP_INTERNAL_SERVER_ERROR);
            return;
        }

        $patient = $patient_query->row_array();
        if (!$patient) {
            $this->response(['status' => false, 'message' => 'Data pasien tidak ditemukan'], RestController::HTTP_NOT_FOUND);
            return;
        }

        $today = date('Y-m-d');
        $booking_query = $this->db->query(
            "SELECT TOP 1
                a.FS_KD_TRS AS no_booking,
                a.FS_KD_TRS_ANTRIAN AS no_antrian,
                b.FN_NO_URUT AS no_urut,
                CONVERT(VARCHAR(10), a.FD_TGL_PERIKSA, 23) AS tanggal_periksa,
                a.FS_JAM_MULAI AS jam_mulai,
                a.FS_KD_LAYANAN AS fs_kd_layanan,
                c.FS_NM_LAYANAN AS nama_poli,
                a.FS_KD_PETUGAS_MEDIS AS fs_kd_petugas_medis,
                d.FS_NM_PEG AS nama_dokter
             FROM TA_TRS_BOOKING a
             LEFT JOIN TA_TRS_ANTRIAN b ON a.FS_KD_TRS_ANTRIAN = b.FS_KD_TRS
             LEFT JOIN TA_LAYANAN c ON a.FS_KD_LAYANAN = c.FS_KD_LAYANAN
             LEFT JOIN TD_PEG d ON a.FS_KD_PETUGAS_MEDIS = d.FS_KD_PEG
             WHERE a.FS_MR = ?
               AND a.FD_TGL_PERIKSA >= ?
               AND a.FD_TGL_VOID = '3000-01-01'
             ORDER BY a.FD_TGL_PERIKSA ASC, a.FS_JAM_MULAI ASC",
            [$fs_mr, $today]
        );
        if (!$booking_query) {
            $this->response(['status' => false, 'message' => 'Gagal mengambil data booking'], RestController::HTTP_INTERNAL_SERVER_ERROR);
            return;
        }

        $this->response([
            'status' => true,
            'data' => [
                'pasien' => $patient,
                'booking_aktif' => $booking_query->row_array() ?: null,
            ],
        ], RestController::HTTP_OK);
    }
}
