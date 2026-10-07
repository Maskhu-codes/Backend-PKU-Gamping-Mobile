<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use chriskacerguis\RestServer\RestController;

class Dokter extends RestController {

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
        
        if (empty($fs_kd_layanan)) {
            $this->response(['status' => false, 'message' => 'fs_kd_layanan is required'], RestController::HTTP_BAD_REQUEST);
            return;
        }

        $query2 = "SELECT DISTINCT aa.fs_kd_peg, fs_nm_alias, fs_nm_peg, dd.fs_kd_layanan, dd.fs_nm_layanan
            FROM td_peg aa  
            INNER JOIN dbo.TD_PEG_LAYANAN bb ON aa.FS_KD_PEG = bb.FS_KD_PEG
            INNER JOIN dbo.TD_PEG_SAT_TUGAS cc ON aa.FS_KD_PEG = cc.FS_KD_PEG
            INNER JOIN dbo.TA_LAYANAN dd ON bb.FS_KD_LAYANAN = dd.FS_KD_LAYANAN
            INNER JOIN dbo.TA_INSTALASI ee ON dd.FS_KD_INSTALASI = ee.FS_KD_INSTALASI
            INNER JOIN dbo.TA_JADWAL_DOKTER ff ON aa.FS_KD_PEG=ff.FS_KD_DOKTER
            WHERE dd.fs_kd_layanan = '$fs_kd_layanan' AND
            aa.FB_AKTIF_DINAS = 'True' AND
            aa.FS_KD_PEG <> 'S000' AND
            cc.FS_KD_SAT_TUGAS = 'MD' AND
            (ee.FS_KD_INSTALASI_DK = '2' OR ee.FS_KD_INSTALASI_DK = '7' OR ee.FS_KD_INSTALASI_DK = '8' OR ee.FS_KD_INSTALASI_DK = '9') 
            AND aa.fs_kd_peg like 'S%'
            ORDER BY fs_nm_peg ASC";
            
        $result = $this->db->query($query2)->result_array();

        $this->response([
            'status' => true,
            'data' => $result
        ], RestController::HTTP_OK);
    }
}
