# PKU Gamping Online API Documentation

## Base URL
`http://localhost/pkug-onlineapi/index.php/api/`

## Authentication
Semua endpoint (kecuali Login) membutuhkan header `Authorization: Bearer <TOKEN>`.

### 1. Login
- **Endpoint**: `POST /auth/login`
- **Body**: 
  - `username` (string)
  - `password` (string)
- **Response**:
```json
{
    "status": true,
    "message": "Login successful",
    "token": "eyJ0eX..."
}
```

### 2. Daftar Dokter Berdasarkan Layanan (Poli)
- **Endpoint**: `GET /dokter/list`
- **Parameters**: 
  - `fs_kd_layanan` (string)
- **Response**: Array of dokter spesialis.

### 3. Daftar Jadwal Dokter
- **Endpoint**: `GET /jadwal/list`
- **Parameters**: 
  - `fs_kd_layanan` (string)
  - `fs_kd_petugas_medis` (string)
- **Response**: Array of jadwal dokter beserta status `is_closed`, `is_full`, `can_book`.

### 4. Simpan Booking Antrian
- **Endpoint**: `POST /booking/simpan`
- **Body**:
  - `fs_mr` (string) - Nomor RM pasien (tanpa prefix)
  - `fd_tgl_lahir` (string) - Format YYYY-MM-DD
  - `fs_kd_layanan` (string)
  - `fs_kd_petugas_medis` (string)
  - `fn_hari` (int)
  - `fd_tgl_antrian` (string)
  - `fs_kd_sesion_poli` (string)
  - `fs_jam_mulai` (string)
- **Response**:
```json
{
    "status": true,
    "message": "Booking berhasil",
    "data": {
        "no_booking": "BO...",
        "no_antrian": "AN...",
        "no_urut": 1
    }
}
```

### 5. Update Registrasi BPJS
- **Endpoint**: `POST /bpjs/registrasi`
- **Body**:
  - `fs_kd_trs` (string) - Nomor Booking
  - `fs_mr` (string)
  - `fs_no_hp` (string)
  - `fs_kd_identitas` (string) - Opsional
  - `fs_kd_reff` (string) - Opsional rujukan
- **Response**:
```json
{
    "status": true,
    "message": "Registrasi BPJS berhasil"
}
```

### 6. Data Beranda Pasien
- **Endpoint**: `GET /beranda`
- **Header**: `Authorization: Bearer <TOKEN>`
- Identitas pasien diambil dari `data.fs_mr` pada JWT, bukan dari parameter request.
- Data pasien dibaca dari `HOSPITAL.dbo.TC_MR`; booking aktif dibaca dari `TA_TRS_BOOKING` dan dilengkapi dengan `TA_TRS_ANTRIAN`, `TA_LAYANAN`, serta `TD_PEG`.
- Jika pasien belum memiliki booking aktif, `booking_aktif` bernilai `null`.

### 7. Antrean Pasien
- **Endpoint**: `GET /antrean`
- **Header**: `Authorization: Bearer <TOKEN>`
- Opsional: `fs_kd_antrian` untuk memilih antrean aktif milik pasien yang login. Nomor antrean milik pasien lain tidak akan dikembalikan.
- Respons memuat detail booking, nomor urut terakhir yang tercatat pada rekam medis poli, serta jumlah antrean sebelumnya yang belum tercatat selesai. Nilai status berasal dari registrasi, asesmen triage, dan rekam medis poli.
- `sedang_dilayani` bernilai `null` karena skema yang dapat diverifikasi belum menunjukkan field nomor yang sedang dipanggil. `nomor_terakhir_dilayani` adalah nomor terbaru pada rekam medis poli dan tidak diklaim sebagai nomor yang sedang dipanggil.
- `jumlah_di_depan` dan `nomor_terakhir_dilayani` bernilai `null` untuk jadwal selain hari ini atau jika sumber data tidak memiliki nilai.
- `lokasi_poli` saat ini `null`; belum ada sumber kolom lokasi/ruang yang terverifikasi di skema yang digunakan.

### Catatan Integrasi
- JWT login dan registrasi menyimpan identitas pada `data.fs_mr`. Endpoint pasien memetakan No. RM pendek ke format SIMRS yang digunakan saat ini sebelum query.
- `POST /booking/simpan` tetap menerima `fs_mr` agar kompatibel dengan klien yang ada, tetapi nilainya harus cocok dengan MR pada JWT.
- Koneksi utama (`default`) diarahkan ke SQL Server database `HOSPITAL`; akun aplikasi berada pada koneksi `local`. Koneksi dan ketersediaan tabel tetap perlu diuji pada server yang menjalankan database, karena tidak tersedia dalam pengujian lokal ini.
