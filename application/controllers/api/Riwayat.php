<?php
defined('BASEPATH') or exit('No direct script access allowed');

use chriskacerguis\RestServer\RestController;

class Riwayat extends RestController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    public function list_get()
    {
        $fs_mr = $this->get('fs_mr');
        $page = (int)$this->get('page') ?: 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;

        if (!$fs_mr) {
            $this->response(['status' => false, 'message' => 'MR tidak valid'], RestController::HTTP_BAD_REQUEST);
            return;
        }

        if (strlen($fs_mr) <= 10) {
            $fs_mr = number_format(347104100000000 + $fs_mr, 0, "", "");
        }

        $sql_count = "
            SELECT COUNT(*) AS total
            FROM HOSPITAL.dbo.TA_REGISTRASI a
            INNER JOIN PKU.dbo.TAC_RJ_MEDIS d ON a.FS_KD_REG = d.FS_KD_REG
            WHERE a.FS_MR = '$fs_mr' AND a.FD_TGL_VOID = '3000-01-01'
        ";
        $total_row = $this->db->query($sql_count)->row_array();
        $total = $total_row ? (int)$total_row['total'] : 0;
        $total_pages = ceil($total / $limit);

        $sql_data = "
            SELECT * FROM (
                SELECT 
                    a.FS_KD_REG,
                    a.FD_TGL_MASUK,
                    b.FS_NM_LAYANAN AS poli,
                    c.FS_NM_PEG AS dokter,
                    d.FS_TERAPI,
                    ROW_NUMBER() OVER(ORDER BY a.FD_TGL_MASUK DESC) AS row_num
                FROM HOSPITAL.dbo.TA_REGISTRASI a
                LEFT JOIN HOSPITAL.dbo.TA_LAYANAN b ON a.FS_KD_LAYANAN = b.FS_KD_LAYANAN
                LEFT JOIN HOSPITAL.dbo.TD_PEG c ON a.FS_KD_MEDIS = c.FS_KD_PEG
                INNER JOIN PKU.dbo.TAC_RJ_MEDIS d ON a.FS_KD_REG = d.FS_KD_REG
                WHERE a.FS_MR = '$fs_mr' AND a.FD_TGL_VOID = '3000-01-01'
            ) AS sub
            WHERE row_num > $offset AND row_num <= " . ($offset + $limit) . "
        ";
        $data = $this->db->query($sql_data)->result_array();

        $this->response([
            'status' => true,
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_items' => $total,
                'has_next' => $page < $total_pages
            ]
        ], RestController::HTTP_OK);
    }
}
