<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use chriskacerguis\RestServer\RestController;

class Bpjs extends RestController {

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

    public function registrasi_post() {
        if (!$this->_verify()) return;

        $fs_kd_trs = $this->post('fs_kd_trs');
        $fs_mr = $this->post('fs_mr');
        $fs_no_hp = $this->post('fs_no_hp');
        $fs_kd_identitas = $this->post('fs_kd_identitas');
        $fs_kd_reff = $this->post('fs_kd_reff');

        if (empty($fs_kd_trs) || empty($fs_mr) || empty($fs_no_hp)) {
            $this->response(['status' => false, 'message' => 'Missing required fields'], RestController::HTTP_BAD_REQUEST);
            return;
        }

        $this->db->trans_start();

        $this->db->query("UPDATE TC_MR SET FS_TLP_PASIEN = '$fs_no_hp', FS_NO_HP = '$fs_no_hp' WHERE FS_MR = '$fs_mr'");
        $this->db->query("UPDATE TA_TRS_BOOKING SET FS_TLP_PASIEN_BOOK = '$fs_no_hp', FS_KETERANGAN = '$fs_kd_identitas', fs_no_rujukan_bpjs = '$fs_kd_reff' WHERE FS_KD_TRS = '$fs_kd_trs'");

        $this->db->trans_complete();

        if ($this->db->trans_status() === FALSE) {
            $this->response(['status' => false, 'message' => 'Gagal registrasi BPJS'], RestController::HTTP_INTERNAL_SERVER_ERROR);
        } else {
            $this->response(['status' => true, 'message' => 'Registrasi BPJS berhasil'], RestController::HTTP_OK);
        }
    }
}
