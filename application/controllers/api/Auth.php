<?php
defined('BASEPATH') or exit('No direct script access allowed');

use chriskacerguis\RestServer\RestController;

class Auth extends RestController
{

    private $db_local;
    private $db_simrs;

    public function __construct()
    {
        parent::__construct();
        $this->load->helper('jwt');
        // Koneksi ke database akun pasien (MySQL lokal)
        $this->db_local = $this->load->database('local', TRUE);
        // Koneksi ke database SIMRS (SQL Server)
        $this->db_simrs = $this->load->database('default', TRUE);
    }

    // ─── Helper: Verifikasi JWT ──────────────────────────────────────────────
    private function _verify()
    {
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

    // ─── Helper: Generate UUID v4 ────────────────────────────────────────────
    private function _generateUUID()
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    // ─── POST /auth/login ────────────────────────────────────────────────────
    public function login_post()
    {
        $fs_email    = $this->post('fs_email');
        $fs_password = $this->post('fs_password');

        if (empty($fs_email) || empty($fs_password)) {
            $this->response(['status' => false, 'message' => 'Email dan password wajib diisi'], RestController::HTTP_BAD_REQUEST);
            return;
        }

        // Cari akun di DB lokal
        $user = $this->db_local->query(
            "SELECT fs_id, fs_mr, fs_email, fs_password_hash, fs_nik, fb_is_active
             FROM ta_user_pasien
             WHERE fs_email = ?",
            [$fs_email]
        )->row_array();

        if (!$user) {
            $this->response(['status' => false, 'message' => 'Email atau password salah'], RestController::HTTP_UNAUTHORIZED);
            return;
        }

        if (!$user['fb_is_active']) {
            $this->response(['status' => false, 'message' => 'Akun Anda tidak aktif'], RestController::HTTP_UNAUTHORIZED);
            return;
        }

        if (!password_verify($fs_password, $user['fs_password_hash'])) {
            $this->response(['status' => false, 'message' => 'Email atau password salah'], RestController::HTTP_UNAUTHORIZED);
            return;
        }

        // Ambil nama dari SIMRS jika ada fs_mr
        $fs_nm_pasien = '';
        if (!empty($user['fs_mr'])) {
            $simrs_data = $this->db_simrs->query(
                "SELECT FS_NM_PASIEN FROM TC_MR WHERE FS_MR = ?",
                [$user['fs_mr']]
            )->row_array();
            $fs_nm_pasien = $simrs_data ? $simrs_data['FS_NM_PASIEN'] : '';
        }

        $payload = [
            'iss'  => base_url(),
            'aud'  => base_url(),
            'iat'  => time(),
            'exp'  => time() + (3600 * 24), // 24 jam
            'data' => [
                'fs_id'       => $user['fs_id'],
                'fs_mr'       => $user['fs_mr'],
                'fs_email'    => $user['fs_email'],
                'fs_nik'      => $user['fs_nik'],
                'fs_nm_pasien' => $fs_nm_pasien,
            ]
        ];

        $token = generate_jwt($payload);

        $this->response([
            'status'  => true,
            'message' => 'Login berhasil',
            'token'   => $token,
            'user'    => $payload['data'],
        ], RestController::HTTP_OK);
    }

    // ─── POST /auth/cekNik ───────────────────────────────────────────────────
    public function cekNik_post()
    {
        $fs_nik = $this->post('fs_nik');

        if (empty($fs_nik)) {
            $this->response(['status' => false, 'message' => 'NIK wajib diisi'], RestController::HTTP_BAD_REQUEST);
            return;
        }

        // Cek apakah NIK sudah terdaftar di akun online
        $existing = $this->db_local->query(
            "SELECT fs_id FROM ta_user_pasien WHERE fs_nik = ?",
            [$fs_nik]
        )->row_array();

        if ($existing) {
            $this->response([
                'status'  => false,
                'message' => 'NIK ini sudah terdaftar sebagai akun. Silakan login.'
            ], RestController::HTTP_OK);
            return;
        }

        // Cari NIK di SIMRS
        $mr_data = $this->db_simrs->query(
            "SELECT TOP 1 FS_MR, FS_NM_PASIEN, FD_TGL_LAHIR
             FROM TC_MR
             WHERE FS_KD_IDENTITAS = ?",
            [$fs_nik]
        )->row_array();

        if ($mr_data) {
            $this->response([
                'status'      => true,
                'found'       => true,
                'fs_mr'       => $mr_data['FS_MR'],
                'fs_nm_pasien' => $mr_data['FS_NM_PASIEN'],
                'fd_tgl_lahir' => $mr_data['FD_TGL_LAHIR'],
                'message'     => 'Data pasien ditemukan di SIMRS',
            ], RestController::HTTP_OK);
        } else {
            $this->response([
                'status'  => true,
                'found'   => false,
                'message' => 'NIK tidak ditemukan, akan membuat data pasien baru',
            ], RestController::HTTP_OK);
        }
    }

    // ─── POST /auth/register ─────────────────────────────────────────────────
    public function register_post()
    {
        $fs_email    = $this->post('fs_email');
        $fs_password = $this->post('fs_password');
        $fs_nik      = $this->post('fs_nik');
        $fs_mr       = $this->post('fs_mr');       // Opsional — jika pasien sudah di SIMRS
        $fs_nm_pasien = $this->post('fs_nm_pasien'); // Nama — dari form jika pasien baru
        $fs_no_hp    = $this->post('fs_no_hp');    // Untuk INSERT TC_MR jika pasien baru

        // Validasi wajib
        if (empty($fs_email) || empty($fs_password) || empty($fs_nik)) {
            $this->response(['status' => false, 'message' => 'Email, password, dan NIK wajib diisi'], RestController::HTTP_BAD_REQUEST);
            return;
        }

        if (strlen($fs_password) < 8) {
            $this->response(['status' => false, 'message' => 'Password minimal 8 karakter'], RestController::HTTP_BAD_REQUEST);
            return;
        }

        // Cek duplikat email
        $email_exists = $this->db_local->query(
            "SELECT fs_id FROM ta_user_pasien WHERE fs_email = ?",
            [$fs_email]
        )->row_array();

        if ($email_exists) {
            $this->response(['status' => false, 'message' => 'Email sudah terdaftar. Silakan login.'], RestController::HTTP_CONFLICT);
            return;
        }

        // Cek duplikat NIK
        $nik_exists = $this->db_local->query(
            "SELECT fs_id FROM ta_user_pasien WHERE fs_nik = ?",
            [$fs_nik]
        )->row_array();

        if ($nik_exists) {
            $this->response(['status' => false, 'message' => 'NIK sudah terdaftar. Silakan login.'], RestController::HTTP_CONFLICT);
            return;
        }

        // Tentukan fs_mr
        if (empty($fs_mr)) {
            // Pasien baru — buat RM di SIMRS
            // ① Ambil & increment No. RM secara atomik — AMAN dari race condition
            $q_nomr = $this->db_simrs->query(
                "DECLARE @UpdatedTable TABLE (NewValue INT);
                 SET NOCOUNT ON;
                 UPDATE TZ_PARAMETER_NO
                 SET    FN_VALUE = FN_VALUE + 1
                 OUTPUT INSERTED.FN_VALUE INTO @UpdatedTable
                 WHERE  FS_KD_PARAMETER = 'NOMR';
                 SELECT NewValue AS FN_VALUE FROM @UpdatedTable"
            )->row_array();

            if (!$q_nomr) {
                $this->response(['status' => false, 'message' => 'Gagal generate No. RM'], RestController::HTTP_INTERNAL_SERVER_ERROR);
                return;
            }

            $fn_nomr_baru = $q_nomr['FN_VALUE'];
            $fs_mr = number_format(347104100000000 + $fn_nomr_baru, 0, '', '');

            $fs_temp_lahir = $this->post('fs_temp_lahir');
            $fd_tgl_lahir = $this->post('fd_tgl_lahir');
            $fs_jenis_kelamin = $this->post('fs_jenis_kelamin');
            $fs_gol_darah = $this->post('fs_gol_darah');
            $fs_alamat = $this->post('fs_alamat');
            $fs_kd_agama = $this->post('fs_kd_agama');
            $fs_kd_pekerjaan = $this->post('fs_kd_pekerjaan');
            $fs_kd_pendidikan = $this->post('fs_kd_pendidikan');
            $fs_status_kawin = $this->post('fs_status_kawin');
            $fs_kd_suku = $this->post('fs_kd_suku') ?: '004'; // Default 004
            $fs_nm_ibu_kandung = $this->post('fs_nm_ibu_kandung');
            $fs_warga_negara = $this->post('fs_warga_negara');
            $fs_kd_kelurahan = $this->post('fs_kd_kelurahan');

            // ② INSERT TC_MR (dalam transaction)
            $this->db_simrs->trans_start();

            $this->db_simrs->query(
                "INSERT INTO TC_MR
                 (FS_MR, FD_TGL_MR, FS_NM_PASIEN, FD_TGL_LAHIR, FS_TEMP_LAHIR, FS_JNS_KELAMIN,
                  FS_GOL_DARAH, FS_KD_IDENTITAS, FS_TLP_PASIEN, FS_ALM_PASIEN,
                  FS_KD_AGAMA, FS_KD_PEKERJAAN_DK, FS_KD_PENDIDIKAN_DK, FS_KD_STATUS_KAWIN_DK,
                  FS_KD_SUKU, FS_NM_IBU_KANDUNG, FS_WARGA_NEGARA, FS_KD_KELURAHAN,
                  UPDUSR, UPDTGL, UPDJAM, FB_AKTIF, FD_TGL_ENTRY, FS_JAM_ENTRY, FS_KD_PTG_ENTRY, CRTTGL, CRTJAM, CRTUSR)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'ONLINE',?,?,'1',?,?,'ONLINE',?,?,'ONLINE')",
                [
                    $fs_mr,
                    date('Y-m-d'),
                    $fs_nm_pasien,
                    $fd_tgl_lahir,
                    $fs_temp_lahir,
                    $fs_jenis_kelamin,
                    $fs_gol_darah,
                    $fs_nik,
                    $fs_no_hp,
                    $fs_alamat,
                    $fs_kd_agama,
                    $fs_kd_pekerjaan,
                    $fs_kd_pendidikan,
                    $fs_status_kawin,
                    $fs_kd_suku,
                    $fs_nm_ibu_kandung,
                    $fs_warga_negara,
                    $fs_kd_kelurahan,
                    date('Y-m-d'),
                    date('H:i:s'),
                    date('Y-m-d'),
                    date('H:i:s'),
                    date('Y-m-d'),
                    date('H:i:s')
                ]
            );

            $this->db_simrs->trans_complete();

            if ($this->db_simrs->trans_status() === FALSE) {
                $this->response(['status' => false, 'message' => 'Gagal membuat data rekam medis'], RestController::HTTP_INTERNAL_SERVER_ERROR);
                return;
            }
        } else {
            // Verifikasi RM + NIK cocok di SIMRS
            $verify_mr = $this->db_simrs->query(
                "SELECT FS_MR FROM TC_MR WHERE FS_MR = ? AND FS_KD_IDENTITAS = ?",
                [$fs_mr, $fs_nik]
            )->row_array();

            if (!$verify_mr) {
                $this->response(['status' => false, 'message' => 'No. RM dan NIK tidak cocok di data SIMRS'], RestController::HTTP_BAD_REQUEST);
                return;
            }
        }

        // Hash password dan generate UUID
        $fs_id           = $this->_generateUUID();
        $fs_password_hash = password_hash($fs_password, PASSWORD_BCRYPT);

        // INSERT akun ke DB lokal
        $inserted = $this->db_local->query(
            "INSERT INTO ta_user_pasien (fs_id, fs_mr, fs_email, fs_password_hash, fs_nik, fb_is_active)
             VALUES (?, ?, ?, ?, ?, 1)",
            [$fs_id, $fs_mr, $fs_email, $fs_password_hash, $fs_nik]
        );

        if (!$inserted) {
            $this->response(['status' => false, 'message' => 'Gagal membuat akun'], RestController::HTTP_INTERNAL_SERVER_ERROR);
            return;
        }

        // Ambil nama dari SIMRS
        $nama = $fs_nm_pasien ?: '';
        if (!empty($fs_mr)) {
            $simrs_data = $this->db_simrs->query(
                "SELECT FS_NM_PASIEN FROM TC_MR WHERE FS_MR = ?",
                [$fs_mr]
            )->row_array();
            $nama = $simrs_data ? $simrs_data['FS_NM_PASIEN'] : $fs_nm_pasien;
        }

        // Auto-login: buat JWT
        $payload = [
            'iss'  => base_url(),
            'aud'  => base_url(),
            'iat'  => time(),
            'exp'  => time() + (3600 * 24),
            'data' => [
                'fs_id'        => $fs_id,
                'fs_mr'        => $fs_mr,
                'fs_email'     => $fs_email,
                'fs_nik'       => $fs_nik,
                'fs_nm_pasien' => $nama,
            ]
        ];

        $token = generate_jwt($payload);

        $this->response([
            'status'  => true,
            'message' => 'Akun berhasil dibuat',
            'token'   => $token,
            'user'    => $payload['data'],
        ], RestController::HTTP_CREATED);
    }

    // ─── GET /auth/me ────────────────────────────────────────────────────────
    public function me_get()
    {
        $decoded = $this->_verify();
        if (!$decoded) return;

        $fs_id = $decoded->data->fs_id;

        // Ambil data akun dari DB lokal
        $user = $this->db_local->query(
            "SELECT fs_id, fs_mr, fs_email, fs_nik, fb_is_active, fd_created_at
             FROM ta_user_pasien
             WHERE fs_id = ?",
            [$fs_id]
        )->row_array();

        if (!$user) {
            $this->response(['status' => false, 'message' => 'Akun tidak ditemukan'], RestController::HTTP_NOT_FOUND);
            return;
        }

        // Ambil data medis dari SIMRS jika ada fs_mr
        $simrs_data = null;
        if (!empty($user['fs_mr'])) {
            $simrs_data = $this->db_simrs->query(
                "SELECT FS_MR, FS_NM_PASIEN, FD_TGL_LAHIR, FS_JNS_KELAMIN,
                        FS_GOL_DARAH, FS_ALM_PASIEN, FS_TLP_PASIEN, FS_TLP_PASIEN,
                        FS_KD_STATUS_KAWIN_DK, FS_KD_AGAMA, FS_KD_PENDIDIKAN_DK, 
                        FS_KD_PEKERJAAN_DK, FS_TEMP_LAHIR, FS_KD_IDENTITAS, FS_KD_KELURAHAN, FS_ALERGI,
                        FS_RIW_PENYAKIT_DAHULU
                 FROM TC_MR
                 WHERE FS_MR = ?",
                [$user['fs_mr']]
            )->row_array();
        }

        $this->response([
            'status' => true,
            'data'   => [
                'fs_id'        => $user['fs_id'],
                'fs_mr'        => $user['fs_mr'],
                'fs_email'     => $user['fs_email'],
                'fs_nik'       => $user['fs_nik'],
                'fd_created_at' => $user['fd_created_at'],
                'simrs'        => $simrs_data,
            ]
        ], RestController::HTTP_OK);
    }
    
        // ====================================================================
    // ENDPOINT 1: POST /auth/forgotPassword
    // Kirim kode OTP 6 digit ke email pasien
    // ====================================================================
    public function forgotPassword_post()
    {
        $email = $this->post('fs_email') ?? $this->post('email');

        if (empty($email)) {
            return $this->response([
                'status'  => false,
                'message' => 'Email wajib diisi.'
            ], RestController::HTTP_BAD_REQUEST);
        }

        // Cek apakah email terdaftar di DB Lokal (MySQL)
        $user = $this->db_local->get_where('ta_user_pasien', ['fs_email' => $email])->row();

        if (!$user) {
            return $this->response([
                'status'  => false,
                'message' => 'Email tidak terdaftar di sistem kami.'
            ], RestController::HTTP_NOT_FOUND);
        }

        // Generate OTP 6 digit acak
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Hapus OTP lama (jika ada) di DB Lokal
        $this->db_local->delete('ta_password_reset', ['fs_email' => $email]);

        // Simpan OTP baru ke tabel ta_password_reset di DB Lokal
        $this->db_local->insert('ta_password_reset', [
            'fs_email'       => $email,
            'fs_otp'         => $otp,
            'fs_reset_token' => null,
            'fd_expired_at'  => date('Y-m-d H:i:s', strtotime('+10 minutes')),
            'fd_created_at'  => date('Y-m-d H:i:s'),
        ]);

        // Kirim OTP ke email pasien
        try {
            $this->_sendOtpEmail($email, $otp);
        } catch (\Exception $e) {
            log_message('error', 'Gagal kirim email OTP: ' . $e->getMessage());
        }

        return $this->response([
            'status'  => true,
            'message' => 'Kode OTP telah dikirim ke email Anda.',
            'otp'     => $otp, // Disertakan agar mudah dicek saat testing dev
        ], RestController::HTTP_OK);
    }

    // ====================================================================
    // ENDPOINT 2: POST /auth/verifyOtp
    // Verifikasi kode OTP 6 digit yang dimasukkan pasien
    // ====================================================================
    public function verifyOtp_post()
    {
        $email = $this->post('fs_email') ?? $this->post('email');
        $otp   = $this->post('fs_otp') ?? $this->post('otp');

        if (empty($email) || empty($otp)) {
            return $this->response([
                'status'  => false,
                'message' => 'Email dan kode OTP wajib diisi.'
            ], RestController::HTTP_BAD_REQUEST);
        }

        // Cari OTP di database lokal
        $record = $this->db_local->get_where('ta_password_reset', [
            'fs_email' => $email,
            'fs_otp'   => $otp,
        ])->row();

        if (!$record) {
            return $this->response([
                'status'  => false,
                'message' => 'Kode OTP tidak valid.'
            ], RestController::HTTP_BAD_REQUEST);
        }

        // Cek apakah OTP sudah kedaluwarsa
        if (strtotime($record->fd_expired_at) < time()) {
            return $this->response([
                'status'  => false,
                'message' => 'Kode OTP sudah kedaluwarsa. Silakan minta kode baru.'
            ], RestController::HTTP_BAD_REQUEST);
        }

        // OTP valid! Generate reset_token untuk langkah selanjutnya
        $resetToken = bin2hex(random_bytes(32));

        // Update tabel di DB Lokal: simpan reset_token dan perpanjang expired
        $this->db_local->where('fn_id', $record->fn_id);
        $this->db_local->update('ta_password_reset', [
            'fs_reset_token' => $resetToken,
            'fd_expired_at'  => date('Y-m-d H:i:s', strtotime('+30 minutes')),
        ]);

        return $this->response([
            'status'      => true,
            'message'     => 'Kode OTP valid.',
            'reset_token' => $resetToken,
        ], RestController::HTTP_OK);
    }

    // ====================================================================
    // ENDPOINT 3: POST /auth/resendOtp
    // Kirim ulang kode OTP baru ke email pasien
    // ====================================================================
    public function resendOtp_post()
    {
        $email = $this->post('fs_email') ?? $this->post('email');

        if (empty($email)) {
            return $this->response([
                'status'  => false,
                'message' => 'Email wajib diisi.'
            ], RestController::HTTP_BAD_REQUEST);
        }

        // Cek apakah email terdaftar di DB Lokal
        $user = $this->db_local->get_where('ta_user_pasien', ['fs_email' => $email])->row();

        if (!$user) {
            return $this->response([
                'status'  => false,
                'message' => 'Email tidak terdaftar di sistem kami.'
            ], RestController::HTTP_NOT_FOUND);
        }

        // Generate OTP baru
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Hapus OTP lama, simpan OTP baru di DB Lokal
        $this->db_local->delete('ta_password_reset', ['fs_email' => $email]);
        $this->db_local->insert('ta_password_reset', [
            'fs_email'       => $email,
            'fs_otp'         => $otp,
            'fs_reset_token' => null,
            'fd_expired_at'  => date('Y-m-d H:i:s', strtotime('+10 minutes')),
            'fd_created_at'  => date('Y-m-d H:i:s'),
        ]);

        // Kirim OTP ke email
        try {
            $this->_sendOtpEmail($email, $otp);
        } catch (\Exception $e) {
            log_message('error', 'Gagal kirim email OTP: ' . $e->getMessage());
        }

        return $this->response([
            'status'  => true,
            'message' => 'Kode OTP baru berhasil dikirim ke email Anda.',
            'otp'     => $otp,
        ], RestController::HTTP_OK);
    }

    // ====================================================================
    // ENDPOINT 4: POST /auth/resetPassword
    // Simpan kata sandi baru pasien
    // ====================================================================
    public function resetPassword_post()
    {
        $email      = $this->post('fs_email') ?? $this->post('email');
        $password   = $this->post('fs_password') ?? $this->post('password');
        $resetToken = $this->post('fs_reset_token') ?? $this->post('reset_token');

        if (empty($email) || empty($password)) {
            return $this->response([
                'status'  => false,
                'message' => 'Email dan kata sandi baru wajib diisi.'
            ], RestController::HTTP_BAD_REQUEST);
        }

        if (strlen($password) < 8) {
            return $this->response([
                'status'  => false,
                'message' => 'Kata sandi minimal 8 karakter.'
            ], RestController::HTTP_BAD_REQUEST);
        }

        // Verifikasi reset_token di DB Lokal
        $record = $this->db_local->get_where('ta_password_reset', [
            'fs_email'       => $email,
            'fs_reset_token' => $resetToken,
        ])->row();

        if (!$record) {
            return $this->response([
                'status'  => false,
                'message' => 'Token reset tidak valid. Silakan ulangi proses lupa kata sandi.'
            ], RestController::HTTP_BAD_REQUEST);
        }

        // Cek apakah token sudah kedaluwarsa
        if (strtotime($record->fd_expired_at) < time()) {
            return $this->response([
                'status'  => false,
                'message' => 'Token reset sudah kedaluwarsa. Silakan ulangi proses lupa kata sandi.'
            ], RestController::HTTP_BAD_REQUEST);
        }

        // Update password hash di tabel ta_user_pasien (DB Lokal)
        $this->db_local->where('fs_email', $email);
        $this->db_local->update('ta_user_pasien', [
            'fs_password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        // Hapus data reset di DB Lokal (karena sudah terpakai)
        $this->db_local->delete('ta_password_reset', ['fs_email' => $email]);

        return $this->response([
            'status'  => true,
            'message' => 'Kata sandi berhasil diperbarui. Silakan login dengan kata sandi baru.'
        ], RestController::HTTP_OK);
    }

    // ====================================================================
    // FUNGSI HELPER: Kirim Email OTP (Private)
    // ====================================================================
    private function _sendOtpEmail($email, $otp)
    {
        $this->load->library('email');

        $config = [
            'protocol'  => 'smtp',
            'smtp_host' => 'ssl://smtp.gmail.com',
            'smtp_port' => 465,
            'smtp_user' => 'emailpengirim@gmail.com',     // GANTI dengan email Gmail pengirim
            'smtp_pass' => 'abcd efgh ijkl mnop',          // GANTI dengan App Password Gmail
            'mailtype'  => 'html',
            'charset'   => 'utf-8',
            'newline'   => "\r\n",
        ];

        $this->email->initialize($config);
        $this->email->from('emailpengirim@gmail.com', 'RS PKU Muhammadiyah Gamping');
        $this->email->to($email);
        $this->email->subject('Kode OTP Reset Kata Sandi - RS PKU Gamping');
        $this->email->message("
            <h3>Reset Kata Sandi</h3>
            <p>Kode OTP Anda adalah:</p>
            <h1 style='color: #1E40AF; letter-spacing: 8px;'>$otp</h1>
            <p>Kode ini berlaku selama <strong>10 menit</strong>.</p>
            <p>Jika Anda tidak meminta reset kata sandi, abaikan email ini.</p>
            <br>
            <p>Salam,<br>RS PKU Muhammadiyah Gamping</p>
        ");

        $this->email->send();
    }
}
