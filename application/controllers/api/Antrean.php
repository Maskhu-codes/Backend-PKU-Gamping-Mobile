<?php
defined('BASEPATH') or exit('No direct script access allowed');

use chriskacerguis\RestServer\RestController;

class Antrean extends RestController
{

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    public function status_get()
    {
        $fs_kd_layanan = $this->get('fs_kd_layanan');
        $fs_kd_medis = $this->get('fs_kd_petugas_medis');
        $fs_mr = $this->get('fs_mr');
        $fs_kd_antrian = $this->get('fs_kd_antrian');
        $tanggal = $this->get('tanggal') ?: date('Y-m-d');

        if (!$fs_kd_layanan || !$fs_kd_medis) {
            $this->response(['status' => false, 'message' => 'Layanan dan Dokter harus diisi'], RestController::HTTP_BAD_REQUEST);
            return;
        }

        $sql_terlayani = "
            SELECT TOP 1 c.FN_NO_URUT 
            FROM PKU.dbo.TAC_RJ_MEDIS a 
            LEFT JOIN HOSPITAL.dbo.TA_REGISTRASI b ON a.FS_KD_REG=b.FS_KD_REG 
            INNER JOIN HOSPITAL.dbo.TA_TRS_ANTRIAN c ON a.FS_KD_REG = c.FS_KD_REG 
            WHERE a.mdd='$tanggal' AND a.FS_KD_MEDIS = '$fs_kd_medis' 
            AND b.FS_KD_LAYANAN = '$fs_kd_layanan' 
            AND b.FD_TGL_VOID = '3000-01-01' 
            ORDER BY c.FN_NO_URUT DESC
        ";
        $q_terlayani = $this->db->query($sql_terlayani)->row_array();
        $no_sedang_dilayani = $q_terlayani ? $q_terlayani['FN_NO_URUT'] : '-';

        $status_pasien = 0;

        if ($fs_kd_antrian) {
            $sql_reg = "
                SELECT FS_KD_REG FROM HOSPITAL.dbo.TA_REGISTRASI 
                WHERE FS_KD_TRS_ANTRIAN = '$fs_kd_antrian' AND FD_TGL_VOID = '3000-01-01'
            ";
            $q_reg = $this->db->query($sql_reg)->row_array();

            if ($q_reg) {
                $status_pasien = 1; // Terdaftar (Menuju Triage)
                $kd_reg = $q_reg['FS_KD_REG'];

                $sql_triage = "SELECT FS_KD_REG FROM PKU.dbo.TAC_ASES_PER2 WHERE FS_KD_REG = '$kd_reg'";
                if ($this->db->query($sql_triage)->row_array()) {
                    $status_pasien = 2; // Triage Selesai (Menunggu Poli)
                }

                $sql_medis = "SELECT FS_KD_REG FROM PKU.dbo.TAC_RJ_MEDIS WHERE FS_KD_REG = '$kd_reg'";
                if ($this->db->query($sql_medis)->row_array()) {
                    $status_pasien = 3; // Poli Selesai (Menuju Farmasi)
                }
            } else {
                $status_pasien = 0; // Belum terdaftar (Hanya Booking)
            }
        }

        $this->response([
            'status' => true,
            'data' => [
                'sedang_dilayani' => $no_sedang_dilayani,
                'status_pasien' => $status_pasien
            ]
        ], RestController::HTTP_OK);
    }
}
