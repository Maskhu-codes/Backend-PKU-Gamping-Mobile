<?php
defined('BASEPATH') or exit('No direct script access allowed');

use chriskacerguis\RestServer\RestController;

class Penunjang extends RestController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    private function get_mr()
    {
        $fs_mr = $this->get('fs_mr');
        if (!$fs_mr) return false;
        
        if (strlen($fs_mr) <= 10) {
            return number_format(347104100000000 + $fs_mr, 0, "", "");
        }
        return $fs_mr;
    }

    public function radiologi_get()
    {
        $fs_mr = $this->get_mr();
        if (!$fs_mr) {
            $this->response(['status' => false, 'message' => 'MR tidak valid'], RestController::HTTP_BAD_REQUEST);
            return;
        }

        $sql = "
            SELECT aa.fs_kd_trs, aa.fd_tgl_trs, bb.fs_jam_keterangan, cc.FS_NM_TARIF, 
                   bb.fs_keterangan, bb.FS_KETERANGAN2HASIL_RAD, bb.urllink1 
            FROM HOSPITAL.dbo.TA_TRS_TDK_UMUM aa 
            LEFT JOIN HOSPITAL.dbo.TA_TRS_TDK_UMUM2 bb ON aa.fs_kd_trs = bb.fs_kd_trs AND CAST(bb.fs_keterangan AS VARCHAR(MAX)) <> '' 
            LEFT JOIN HOSPITAL.dbo.TA_TARIF cc ON bb.fs_kd_tarif = cc.fs_kd_tarif 
            INNER JOIN HOSPITAL.dbo.TA_REGISTRASI d ON aa.fs_kd_reg = d.fs_kd_reg
            WHERE aa.fd_tgl_void = '3000-01-01' AND bb.fb_void = 0 AND d.fs_mr = ?
            ORDER BY aa.fd_tgl_trs DESC
        ";

        $data = $this->db->query($sql, [$fs_mr])->result_array();

        $this->response([
            'status' => true,
            'data' => $data
        ], RestController::HTTP_OK);
    }

    public function laboratorium_get()
    {
        $fs_mr = $this->get_mr();
        if (!$fs_mr) {
            $this->response(['status' => false, 'message' => 'MR tidak valid'], RestController::HTTP_BAD_REQUEST);
            return;
        }

        $page = (int) $this->get('page') ?: 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;
        $row_start = $offset + 1;
        $row_end = $offset + $limit;

        $sql = "
            WITH CTE AS (
                SELECT a.FS_KD_TRS, a.FD_TGL_TRS, a.FS_JAM_TRS,
                       ROW_NUMBER() OVER(ORDER BY a.FD_TGL_TRS DESC, a.FS_JAM_TRS DESC) as RowNum
                FROM HOSPITAL.dbo.TA_TRS_TDK_UMUM a
                INNER JOIN HOSPITAL.dbo.TA_REGISTRASI c ON a.FS_KD_REG = c.FS_KD_REG
                WHERE c.FS_MR = ? AND a.FD_TGL_VOID = '3000-01-01' AND c.FD_TGL_VOID = '3000-01-01'
                AND a.FS_KD_LAYANAN = 'P013'
                GROUP BY a.FS_KD_TRS, a.FD_TGL_TRS, a.FS_JAM_TRS
            )
            SELECT * FROM CTE WHERE RowNum BETWEEN ? AND ?
        ";

        $data = $this->db->query($sql, [$fs_mr, $row_start, $row_end])->result_array();

        $this->response([
            'status' => true,
            'data' => $data,
            'page' => $page
        ], RestController::HTTP_OK);
    }

    public function laboratorium_detail_get()
    {
        $fs_kd_trs = $this->get('fs_kd_trs');
        if (!$fs_kd_trs) {
            $this->response(['status' => false, 'message' => 'ID Transaksi diperlukan'], RestController::HTTP_BAD_REQUEST);
            return;
        }

        $sql = "
            SELECT dd.fs_nm_jenis_pemeriksaan as pemeriksaan, 
                   bb.FS_HASIL as hasil, bb.FS_SATUAN as satuan, bb.FS_NILAI_RUJUKAN as nilai_rujukan, bb.FS_FLAG_HASIL as flag
            FROM HOSPITAL.dbo.TA_TRS_TDK_UMUM5 bb 
            LEFT JOIN HOSPITAL.dbo.TA_JENIS_PEMERIKSAAN dd ON bb.FS_KD_JENIS_PEMERIKSAAN = dd.fs_kd_jenis_pemeriksaan 
            WHERE bb.FS_KD_TRS = ? 
                AND CAST(bb.FS_HASIL AS VARCHAR(MAX)) <> '' 
                AND CAST(bb.FS_HASIL AS VARCHAR(MAX)) <> 'HASIL MENYUSUL' 
        ";

        $data = $this->db->query($sql, [$fs_kd_trs])->result_array();

        $this->response([
            'status' => true,
            'data' => $data
        ], RestController::HTTP_OK);
    }
}
