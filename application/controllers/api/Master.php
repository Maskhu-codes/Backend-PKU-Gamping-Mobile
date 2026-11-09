<?php
defined('BASEPATH') or exit('No direct script access allowed');

use chriskacerguis\RestServer\RestController;

class Master extends RestController {

    private $db_simrs;

    public function __construct() {
        parent::__construct();
        $this->db_simrs = $this->load->database('default', TRUE);
    }

    public function pekerjaan_get() {
        $query = $this->db_simrs->query("SELECT FS_KD_PEKERJAAN_DK, FS_NM_PEKERJAAN_DK FROM TA_PEKERJAAN_DK ORDER BY FS_NM_PEKERJAAN_DK ASC");
        if (!$query) {
            $this->response(['status' => false, 'error' => $this->db_simrs->error()], 500);
            return;
        }
        $data = $query->result_array();
        $this->response(['status' => true, 'data' => $data], RestController::HTTP_OK);
    }

    public function pendidikan_get() {
        $query = $this->db_simrs->query("SELECT FS_KD_PENDIDIKAN_DK, FS_NM_PENDIDIKAN_DK FROM TA_PENDIDIKAN_DK ORDER BY FS_KD_PENDIDIKAN_DK ASC");
        if (!$query) {
            $this->response(['status' => false, 'error' => $this->db_simrs->error()], 500);
            return;
        }
        $data = $query->result_array();
        $this->response(['status' => true, 'data' => $data], RestController::HTTP_OK);
    }

    public function status_kawin_get() {
        $query = $this->db_simrs->query("SELECT FS_KD_STATUS_KAWIN_DK, FS_NM_STATUS_KAWIN_DK FROM TA_STATUS_KAWIN_DK ORDER BY FS_KD_STATUS_KAWIN_DK ASC");
        if (!$query) {
            $this->response(['status' => false, 'error' => $this->db_simrs->error()], 500);
            return;
        }
        $data = $query->result_array();
        $this->response(['status' => true, 'data' => $data], RestController::HTTP_OK);
    }

    public function agama_get() {
        $query = $this->db_simrs->query("SELECT FS_KD_AGAMA, FS_NM_AGAMA FROM TA_AGAMA ORDER BY FS_KD_AGAMA ASC");
        if (!$query) {
            $this->response(['status' => false, 'error' => $this->db_simrs->error()], 500);
            return;
        }
        $data = $query->result_array();
        $this->response(['status' => true, 'data' => $data], RestController::HTTP_OK);
    }
}
