<?php
defined('BASEPATH') or exit('No direct script access allowed');

use chriskacerguis\RestServer\RestController;

class Antrean extends RestController
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

    public function status_get()
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

        $today = date('Y-m-d');
        $fs_kd_antrian = trim((string) $this->get('fs_kd_antrian'));
        $sql_booking = "
            SELECT TOP 1
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
              AND a.FD_TGL_VOID = '3000-01-01'";
        $params = [$fs_mr, $today];

        if ($fs_kd_antrian) {
            $sql_booking .= " AND a.FS_KD_TRS_ANTRIAN = ?";
            $params[] = $fs_kd_antrian;
        }
        $sql_booking .= " ORDER BY a.FD_TGL_PERIKSA ASC, a.FS_JAM_MULAI ASC";

        $booking_query = $this->db->query($sql_booking, $params);
        if (!$booking_query) {
            $this->response(['status' => false, 'message' => 'Gagal mengambil data antrean'], RestController::HTTP_INTERNAL_SERVER_ERROR);
            return;
        }

        $booking = $booking_query->row_array();
        if (!$booking) {
            $this->response(['status' => false, 'message' => 'Tidak ada antrean aktif untuk pasien ini'], RestController::HTTP_NOT_FOUND);
            return;
        }

        $status_pasien = 0;
        $registration_query = $this->db->query(
            "SELECT FS_KD_REG FROM TA_REGISTRASI
             WHERE FS_KD_TRS_ANTRIAN = ? AND FD_TGL_VOID = '3000-01-01'",
            [$booking['no_antrian']]
        );
        if (!$registration_query) {
            $this->response(['status' => false, 'message' => 'Gagal mengambil status registrasi'], RestController::HTTP_INTERNAL_SERVER_ERROR);
            return;
        }
        $registration = $registration_query->row_array();

        if ($registration) {
            $status_pasien = 1;
            $kd_reg = $registration['FS_KD_REG'];

            $triage_query = $this->db->query(
                "SELECT FS_KD_REG FROM PKU.dbo.TAC_ASES_PER2 WHERE FS_KD_REG = ?",
                [$kd_reg]
            );
            $medical_query = $this->db->query(
                "SELECT FS_KD_REG FROM PKU.dbo.TAC_RJ_MEDIS WHERE FS_KD_REG = ?",
                [$kd_reg]
            );
            if (!$triage_query || !$medical_query) {
                $this->response(['status' => false, 'message' => 'Gagal mengambil status pelayanan'], RestController::HTTP_INTERNAL_SERVER_ERROR);
                return;
            }
            if ($triage_query->row_array()) {
                $status_pasien = 2;
            }
            if ($medical_query->row_array()) {
                $status_pasien = 3;
            }
        }

        $nomor_terakhir_dilayani = null;
        $jumlah_di_depan = null;
        if ($booking['tanggal_periksa'] === $today && $booking['no_urut'] !== null) {
            $current_query = $this->db->query(
                "SELECT TOP 1 c.FN_NO_URUT
                 FROM PKU.dbo.TAC_RJ_MEDIS a
                 LEFT JOIN TA_REGISTRASI b ON a.FS_KD_REG = b.FS_KD_REG
                 INNER JOIN TA_TRS_ANTRIAN c ON a.FS_KD_REG = c.FS_KD_REG
                 WHERE a.MDD = ?
                   AND a.FS_KD_MEDIS = ?
                   AND b.FS_KD_LAYANAN = ?
                   AND b.FD_TGL_VOID = '3000-01-01'
                 ORDER BY c.FN_NO_URUT DESC",
                [$today, $booking['fs_kd_petugas_medis'], $booking['fs_kd_layanan']]
            );
            $ahead_query = $this->db->query(
                "SELECT COUNT(DISTINCT q.FS_KD_TRS) AS jumlah
                 FROM TA_TRS_ANTRIAN q
                 LEFT JOIN TA_TRS_BOOKING b
                   ON b.FS_KD_TRS_ANTRIAN = q.FS_KD_TRS
                  AND b.FD_TGL_VOID = '3000-01-01'
                 LEFT JOIN TA_REGISTRASI r
                   ON r.FS_KD_TRS_ANTRIAN = q.FS_KD_TRS
                  AND r.FD_TGL_VOID = '3000-01-01'
                 LEFT JOIN PKU.dbo.TAC_RJ_MEDIS m ON m.FS_KD_REG = r.FS_KD_REG
                 WHERE q.FD_TGL_ANTRIAN = ?
                   AND q.FS_KD_LAYANAN = ?
                   AND q.FS_KD_PETUGAS_MEDIS = ?
                   AND q.FN_NO_URUT < ?
                   AND (b.FS_KD_TRS IS NOT NULL OR r.FS_KD_REG IS NOT NULL)
                   AND m.FS_KD_REG IS NULL",
                [$today, $booking['fs_kd_layanan'], $booking['fs_kd_petugas_medis'], $booking['no_urut']]
            );
            if (!$current_query || !$ahead_query) {
                $this->response(['status' => false, 'message' => 'Gagal mengambil informasi antrean'], RestController::HTTP_INTERNAL_SERVER_ERROR);
                return;
            }
            $current = $current_query->row_array();
            $ahead = $ahead_query->row_array();
            $nomor_terakhir_dilayani = $current ? $current['FN_NO_URUT'] : null;
            $jumlah_di_depan = $ahead ? (int) $ahead['jumlah'] : null;
        }

        $this->response([
            'status' => true,
            'data' => [
                'antrean' => $booking,
                'sedang_dilayani' => null,
                'nomor_terakhir_dilayani' => $nomor_terakhir_dilayani,
                'jumlah_di_depan' => $jumlah_di_depan,
                'lokasi_poli' => null,
                'status_pasien' => $status_pasien,
                'status_text' => [
                    0 => 'Booking, belum terdaftar',
                    1 => 'Terdaftar, menuju triage',
                    2 => 'Triage selesai, menunggu poli',
                    3 => 'Poli selesai, menuju farmasi',
                ][$status_pasien],
            ]
        ], RestController::HTTP_OK);
    }
}
