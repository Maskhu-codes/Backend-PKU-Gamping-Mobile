<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use chriskacerguis\RestServer\RestController;

class Layanan extends RestController {

    public function __construct() {
        parent::__construct();
        $this->load->database();
    }

    public function list_get() {
        // Query untuk mengambil Poli (Layanan) yang aktif dan relevan (Instalasi Rawat Jalan, dsb)
        $query = "
            SELECT DISTINCT dd.fs_kd_layanan, dd.fs_nm_layanan 
            FROM HOSPITAL.dbo.TA_LAYANAN dd
            INNER JOIN HOSPITAL.dbo.TA_INSTALASI ee ON dd.FS_KD_INSTALASI = ee.FS_KD_INSTALASI
            WHERE dd.fb_aktif = 'True' 
            AND (ee.FS_KD_INSTALASI_DK = '2' OR ee.FS_KD_INSTALASI_DK = '7' OR ee.FS_KD_INSTALASI_DK = '8' OR ee.FS_KD_INSTALASI_DK = '9') 
            ORDER BY dd.fs_nm_layanan ASC
        ";
            
        $result = $this->db->query($query)->result_array();

        $this->response([
            'status' => true,
            'data' => $result
        ], RestController::HTTP_OK);
    }
}
