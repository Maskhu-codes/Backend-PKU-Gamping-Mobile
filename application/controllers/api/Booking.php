<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use chriskacerguis\RestServer\RestController;

class Booking extends RestController {

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

    public function simpan_post() {
        if (!$this->_verify()) return;

        $fs_mr = $this->post('fs_mr');
        $fd_tgl_lahir = $this->post('fd_tgl_lahir');
        $fs_kd_layanan = $this->post('fs_kd_layanan');
        $fs_kd_petugas_medis = $this->post('fs_kd_petugas_medis');
        $fn_hari = $this->post('fn_hari');
        $fd_tgl_antrian = $this->post('fd_tgl_antrian');
        $fs_kd_sesion_poli = $this->post('fs_kd_sesion_poli');
        $fs_jam_mulai = $this->post('fs_jam_mulai');

        if (empty($fs_mr) || empty($fd_tgl_lahir) || empty($fs_kd_layanan) || empty($fs_kd_petugas_medis) || empty($fd_tgl_antrian)) {
            $this->response(['status' => false, 'message' => 'Missing required fields'], RestController::HTTP_BAD_REQUEST);
            return;
        }

        $today = date('Y-m-d');
        $timetoday = date('H:i:s');
        
        if (strlen($fs_mr) > 10) {
            $fs_mr_full = $fs_mr;
        } else {
            $fs_mr_full = number_format(347104100000000 + $fs_mr, 0, "", "");
        }

        $q_mr = $this->db->query("SELECT FS_MR,FD_TGL_LAHIR FROM TC_MR WHERE (FS_MR = '$fs_mr_full') AND (FD_TGL_LAHIR = '$fd_tgl_lahir')")->row_array();
        if (!$q_mr) {
            $this->response(['status' => false, 'message' => 'Nomor MR dan Tanggal Lahir Tidak Cocok'], RestController::HTTP_BAD_REQUEST);
            return;
        }

        $q_max = $this->db->query("SELECT FN_MAX FROM TA_JADWAL_DOKTER WHERE (FS_KD_LAYANAN = '$fs_kd_layanan') AND (FS_KD_DOKTER = '$fs_kd_petugas_medis') AND (FN_HARI = '$fn_hari')")->row_array();
        $FN_MAX = $q_max ? $q_max['FN_MAX'] : 0;

        $q_daftar = $this->db->query("SELECT COUNT(fs_kd_trs) fn_daftar FROM ta_trs_booking WHERE fd_tgl_void='3000-01-01' AND fs_kd_layanan='$fs_kd_layanan' AND fs_kd_petugas_medis='$fs_kd_petugas_medis' AND fd_tgl_periksa='$fd_tgl_antrian'")->row_array();
        $fn_daftar = $q_daftar ? $q_daftar['fn_daftar'] : 0;

        if ($fn_daftar >= $FN_MAX) {
            $this->response(['status' => false, 'message' => 'Pasien Sudah Penuh'], RestController::HTTP_BAD_REQUEST);
            return;
        }

        $row_count = $this->db->query("SELECT * FROM TA_TRS_BOOKING WHERE FS_MR = '$fs_mr_full' AND FS_KD_LAYANAN = '$fs_kd_layanan' AND FD_TGL_PERIKSA = '$fd_tgl_antrian' AND FS_KD_PETUGAS_MEDIS = '$fs_kd_petugas_medis' AND FS_KD_SESION_POLI = '$fs_kd_sesion_poli' AND FD_TGL_VOID = '3000-01-01'")->num_rows();
        $row_count2 = $this->db->query("SELECT * FROM TA_TRS_BOOKING WHERE FS_MR = '$fs_mr_full' AND FD_TGL_TRS = '$today' AND FD_TGL_VOID='3000-01-01'")->num_rows();

        if ($row_count >= 1) {
            $this->response(['status' => false, 'message' => 'Anda Sudah Terdaftar 2 kali di jadwal ini'], RestController::HTTP_BAD_REQUEST);
            return;
        } elseif ($row_count2 >= 3) {
            $this->response(['status' => false, 'message' => 'Maksimal Pendaftaran 3 kali sehari'], RestController::HTTP_BAD_REQUEST);
            return;
        }

        $this->db->trans_start();

        $q_urut = $this->db->query("SELECT fn_no_urut FROM ta_no_antrian WHERE fd_tgl_antrian = '$fd_tgl_antrian' AND fs_kd_layanan = '$fs_kd_layanan' AND fb_prioritas = 0 AND fs_kd_petugas_medis = '$fs_kd_petugas_medis'");
        if ($q_urut->num_rows() > 0) {
            $data_urut = $q_urut->row_array();
            $NOURUT = $data_urut['fn_no_urut'] + 1;
            $this->db->query("UPDATE ta_no_antrian SET fn_no_urut = '$NOURUT' WHERE fd_tgl_antrian = '$fd_tgl_antrian' AND fs_kd_layanan = '$fs_kd_layanan' AND fb_prioritas =0 AND fs_kd_petugas_medis='$fs_kd_petugas_medis'");
        } else {
            $NOURUT = 1;
            $this->db->query("INSERT INTO ta_no_antrian (fd_tgl_antrian,fs_kd_layanan,fn_no_urut,fb_prioritas,fs_kd_petugas_medis,fs_jam_mulai) VALUES ('$fd_tgl_antrian','$fs_kd_layanan','$NOURUT',0,'$fs_kd_petugas_medis','$fs_jam_mulai')");
        }

        $q_antrian = $this->db->query("
            DECLARE @UpdatedTable TABLE (NewValue INT);
            SET NOCOUNT ON;
            UPDATE TZ_PARAMETER_NO
            SET FN_VALUE = FN_VALUE + 1
            OUTPUT INSERTED.FN_VALUE INTO @UpdatedTable
            WHERE FS_KD_PARAMETER = 'NOANTRIAN';
            SELECT NewValue AS FN_VALUE FROM @UpdatedTable
        ")->row_array();
        
        if (!$q_antrian) {
            $this->response(['status' => false, 'message' => 'Gagal generate No Antrian'], RestController::HTTP_INTERNAL_SERVER_ERROR);
            return;
        }
        $NOANTRIAN2 = $q_antrian['FN_VALUE'];
        $NOANTRIAN = 'AN' . substr($NOANTRIAN2 + 10000000, -7) . 'A';

        $q_booking = $this->db->query("
            DECLARE @UpdatedTable TABLE (NewValue INT);
            SET NOCOUNT ON;
            UPDATE TZ_PARAMETER_NO
            SET FN_VALUE = FN_VALUE + 1
            OUTPUT INSERTED.FN_VALUE INTO @UpdatedTable
            WHERE FS_KD_PARAMETER = 'NOBOOKING';
            SELECT NewValue AS FN_VALUE FROM @UpdatedTable
        ")->row_array();
        
        if (!$q_booking) {
            $this->response(['status' => false, 'message' => 'Gagal generate No Booking'], RestController::HTTP_INTERNAL_SERVER_ERROR);
            return;
        }
        $NOBOOKING2 = $q_booking['FN_VALUE'];
        $NOBOOKING = 'BO' . substr($NOBOOKING2 + 10000000, -7) . 'A';

        $q_px = $this->db->query("SELECT FS_NM_PASIEN,FS_ALM_PASIEN,FS_TLP_PASIEN,FD_TGL_LAHIR FROM TC_MR WHERE FS_MR = '$fs_mr_full'")->row_array();

        $this->db->query("INSERT INTO ta_trs_booking (fs_kd_trs, fd_tgl_trs, fs_jam_trs, fs_kd_petugas, fs_mr, fs_nm_pasien_book, fs_alm_pasien_book, fs_tlp_pasien_book, fd_tgl_lahir_book, fs_kd_layanan, fs_kd_layanan2, fs_kd_layanan3, fs_kd_petugas_medis, fs_kd_petugas_medis2, fs_kd_petugas_medis3, fd_tgl_periksa, fs_jam_periksa, fs_kd_sesion_poli, fb_prioritas, fs_jam_mulai, fs_kd_sesion_poli2, fs_jam_mulai2, fs_kd_sesion_poli3, fs_jam_mulai3, fs_kd_cara_booking_dk, fs_kd_trs_antrian) VALUES ('$NOBOOKING', '$today', '$timetoday', 'TAMU', '$fs_mr_full', '$q_px[FS_NM_PASIEN]', '$q_px[FS_ALM_PASIEN]', '$q_px[FS_TLP_PASIEN]', '$q_px[FD_TGL_LAHIR]', '$fs_kd_layanan', ' ', ' ', '$fs_kd_petugas_medis', ' ', ' ', '$fd_tgl_antrian', '00:00:00', '$fs_kd_sesion_poli', 0, '$fs_jam_mulai', ' ', '00:00', ' ', '00:00', 'OL', '$NOANTRIAN')");

        $this->db->query("INSERT INTO ta_trs_antrian (fs_kd_trs, fd_tgl_trs, fs_jam_trs, fs_kd_petugas, fs_mr, fs_nm_pasien_antrian, fs_kd_layanan, fs_kd_petugas_medis, fd_tgl_antrian, fb_prioritas, fs_kd_sesion_poli, fs_jam_mulai, fn_no_urut) VALUES ('$NOANTRIAN', '$today', '$timetoday', 'TAMU', '$fs_mr_full', '$q_px[FS_NM_PASIEN]', '$fs_kd_layanan', '$fs_kd_petugas_medis', '$fd_tgl_antrian', 0, '$fs_kd_sesion_poli', '$fs_jam_mulai', '$NOURUT')");

        $this->db->query("INSERT INTO ta_trs_mr_mutasi (fs_kd_trs_buat, fs_mr, fs_kd_mutasi, fd_tgl_mutasi, fs_jam_mutasi, fs_kd_petugas_medis, fs_kd_layanan, fd_tgl_praktek, fs_jam_praktek, fs_kd_sesion_poli, fs_kd_petugas, fn_status) VALUES ('$NOBOOKING', '$fs_mr_full', '$NOBOOKING', '$today', '$timetoday', '$fs_kd_petugas_medis', '$fs_kd_layanan', '$fd_tgl_antrian', '$fs_jam_mulai', '$fs_kd_sesion_poli', 'TAMU', 0)");

        $this->db->trans_complete();

        if ($this->db->trans_status() === FALSE) {
            $this->response(['status' => false, 'message' => 'Gagal menyimpan booking'], RestController::HTTP_INTERNAL_SERVER_ERROR);
        } else {
            $this->response(['status' => true, 'message' => 'Booking berhasil', 'data' => ['no_booking' => $NOBOOKING, 'no_antrian' => $NOANTRIAN, 'no_urut' => $NOURUT]], RestController::HTTP_OK);
        }
    }

    public function aktif_get()
    {
        $decoded = $this->_verify();
        if (!$decoded) return;

        $fs_mr = $decoded->data->fs_mr;
        if (empty($fs_mr)) {
            $this->response(['status' => false, 'message' => 'Pasien belum memiliki RM'], RestController::HTTP_BAD_REQUEST);
            return;
        }

        if (strlen($fs_mr) > 10) {
            $fs_mr_full = $fs_mr;
        } else {
            $fs_mr_full = number_format(347104100000000 + $fs_mr, 0, "", "");
        }

        $today = date('Y-m-d');

        $sql = "
            SELECT TOP 1 
                a.fs_kd_trs AS no_booking,
                a.fs_kd_trs_antrian AS no_antrian,
                b.fn_no_urut AS no_urut,
                a.fd_tgl_periksa,
                a.fs_jam_mulai,
                a.fs_kd_layanan,
                a.fs_kd_petugas_medis,
                c.FS_NM_LAYANAN AS poliName,
                d.FS_NM_PEG AS dokterName
            FROM TA_TRS_BOOKING a
            LEFT JOIN TA_TRS_ANTRIAN b ON a.fs_kd_trs_antrian = b.fs_kd_trs
            LEFT JOIN TA_LAYANAN c ON a.fs_kd_layanan = c.FS_KD_LAYANAN
            LEFT JOIN TD_PEG d ON a.fs_kd_petugas_medis = d.FS_KD_PEG
            WHERE a.fs_mr = '$fs_mr_full' 
              AND a.fd_tgl_periksa >= '$today'
              AND a.fd_tgl_void = '3000-01-01'
            ORDER BY a.fd_tgl_periksa DESC
        ";

        $q = $this->db->query($sql)->row_array();

        if ($q) {
            $this->response(['status' => true, 'data' => $q], RestController::HTTP_OK);
        } else {
            $this->response(['status' => false, 'message' => 'Tidak ada pendaftaran aktif'], RestController::HTTP_NOT_FOUND);
        }
    }
}
