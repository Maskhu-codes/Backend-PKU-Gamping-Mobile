<?php
defined('BASEPATH') or exit('No direct script access allowed');

use chriskacerguis\RestServer\RestController;

class Wilayah extends RestController {

    private $db_simrs;

    public function __construct() {
        parent::__construct();
        $this->db_simrs = $this->load->database('default', TRUE);
    }

    public function provinsi_get() {
        $data = $this->db_simrs->query("SELECT FS_KD_PROPINSI, FS_NM_PROPINSI FROM TA_PROPINSI ORDER BY FS_NM_PROPINSI ASC")->result_array();
        $this->response(['status' => true, 'data' => $data], RestController::HTTP_OK);
    }

    public function kabupaten_get() {
        $fs_kd_propinsi = $this->get('fs_kd_propinsi');
        if (empty($fs_kd_propinsi)) {
            $this->response(['status' => false, 'message' => 'Parameter fs_kd_propinsi diperlukan'], RestController::HTTP_BAD_REQUEST);
            return;
        }

        $data = $this->db_simrs->query(
            "SELECT FS_KD_KABUPATEN, FS_NM_KABUPATEN FROM TA_KABUPATEN WHERE FS_KD_PROPINSI = ? ORDER BY FS_NM_KABUPATEN ASC",
            [$fs_kd_propinsi]
        )->result_array();

        $this->response(['status' => true, 'data' => $data], RestController::HTTP_OK);
    }

    public function kecamatan_get() {
        $fs_kd_kabupaten = $this->get('fs_kd_kabupaten');
        if (empty($fs_kd_kabupaten)) {
            $this->response(['status' => false, 'message' => 'Parameter fs_kd_kabupaten diperlukan'], RestController::HTTP_BAD_REQUEST);
            return;
        }

        $data = $this->db_simrs->query(
            "SELECT FS_KD_KECAMATAN, FS_NM_KECAMATAN FROM TA_KECAMATAN WHERE FS_KD_KABUPATEN = ? ORDER BY FS_NM_KECAMATAN ASC",
            [$fs_kd_kabupaten]
        )->result_array();

        $this->response(['status' => true, 'data' => $data], RestController::HTTP_OK);
    }

    public function kelurahan_get() {
        $fs_kd_kecamatan = $this->get('fs_kd_kecamatan');
        if (empty($fs_kd_kecamatan)) {
            $this->response(['status' => false, 'message' => 'Parameter fs_kd_kecamatan diperlukan'], RestController::HTTP_BAD_REQUEST);
            return;
        }

        $data = $this->db_simrs->query(
            "SELECT FS_KD_KELURAHAN, FS_NM_KELURAHAN FROM TA_KELURAHAN WHERE FS_KD_KECAMATAN = ? ORDER BY FS_NM_KELURAHAN ASC",
            [$fs_kd_kecamatan]
        )->result_array();

        $this->response(['status' => true, 'data' => $data], RestController::HTTP_OK);
    }
    public function detail_get() {
        $fs_kd_kelurahan = $this->get('fs_kd_kelurahan');
        if (empty($fs_kd_kelurahan)) {
            $this->response(['status' => false, 'message' => 'Parameter fs_kd_kelurahan diperlukan'], RestController::HTTP_BAD_REQUEST);
            return;
        }

        $query = $this->db_simrs->query(
            "SELECT 
                kel.FS_NM_KELURAHAN, 
                kec.FS_NM_KECAMATAN, 
                kab.FS_NM_KABUPATEN, 
                prop.FS_NM_PROPINSI 
             FROM TA_KELURAHAN kel
             LEFT JOIN TA_KECAMATAN kec ON kel.FS_KD_KECAMATAN = kec.FS_KD_KECAMATAN
             LEFT JOIN TA_KABUPATEN kab ON kec.FS_KD_KABUPATEN = kab.FS_KD_KABUPATEN
             LEFT JOIN TA_PROPINSI prop ON kab.FS_KD_PROPINSI = prop.FS_KD_PROPINSI
             WHERE kel.FS_KD_KELURAHAN = ?",
            [$fs_kd_kelurahan]
        );

        $data = $query ? $query->row_array() : null;

        if ($data) {
            $this->response(['status' => true, 'data' => $data], RestController::HTTP_OK);
        } else {
            $this->response(['status' => false, 'message' => 'Data tidak ditemukan'], RestController::HTTP_NOT_FOUND);
        }
    }
}
