<?php
defined('BASEPATH') or exit('No direct script access allowed');

use chriskacerguis\RestServer\RestController;

class Farmasi extends RestController
{
    private $db_antrian;

    public function __construct()
    {
        parent::__construct();
        // Koneksi ke database antrian apotek secara terpisah
        $this->db_antrian = $this->load->database('antrian-farmasi', TRUE);
    }

    public function antrean_get()
    {
        $racik = [];
        $non_racik = [];

        // --- DATA OBAT RACIK ---
        // Antrian B
        $q_b = $this->db_antrian->query("SELECT nomor_antrian FROM temp_apotek3");
        if ($q_b) {
            foreach ($q_b->result_array() as $row) {
                $racik[] = 'B-' . $row['nomor_antrian'];
            }
        }
        // Antrian D
        $q_d = $this->db_antrian->query("SELECT nomor_antrian FROM temp_apotek1");
        if ($q_d) {
            foreach ($q_d->result_array() as $row) {
                $racik[] = 'D-' . $row['nomor_antrian'];
            }
        }

        // --- DATA OBAT NON RACIK ---
        // Antrian A
        $q_a = $this->db_antrian->query("SELECT nomor_antrian FROM temp_apotek4");
        if ($q_a) {
            foreach ($q_a->result_array() as $row) {
                $non_racik[] = 'A-' . $row['nomor_antrian'];
            }
        }
        // Antrian C
        $q_c = $this->db_antrian->query("SELECT nomor_antrian FROM temp_apotek2");
        if ($q_c) {
            foreach ($q_c->result_array() as $row) {
                $non_racik[] = 'C-' . $row['nomor_antrian'];
            }
        }

        $this->response([
            'status' => true,
            'data' => [
                'racik' => $racik,
                'non_racik' => $non_racik
            ]
        ], RestController::HTTP_OK);
    }
}
