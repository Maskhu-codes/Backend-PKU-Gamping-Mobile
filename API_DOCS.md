# PKU Gamping Online API Documentation

## Base URL
`http://localhost/<nama-folder-project>/index.php/api/`

## Authentication
Endpoint yang memerlukan login menyebutkan header `Authorization: Bearer <TOKEN>`.
Saat ini JWT menyimpan identitas pasien di `data.fs_mr`. Endpoint `beranda`,
`antrean`, booking aktif, `riwayat`, dan `penunjang` mengambil identitas dari
token, bukan parameter MR.
Untuk kebijakan endpoint lainnya, lihat bagian Autentikasi di [README.md](README.md).

### 1. Login
- **Endpoint**: `POST /auth/login`
- **Body**: 
  - `fs_email`, `username`, `identifier`, `fs_nik`, atau `fs_mr` (string)
  - `fs_password` atau `password` (string)
- **Response**:
```json
{
    "status": true,
    "message": "Login berhasil",
    "token": "<JWT>",
    "user": {
        "fs_mr": "<MR pasien>",
        "fs_nm_pasien": "<Nama pasien>"
    }
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
- `fs_mr` harus sesuai MR pada JWT dan `fs_kd_trs` harus merupakan booking aktif milik pasien tersebut.

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

### 8. Booking Aktif
- **Endpoint**: `GET /booking/aktif`
- **Header**: `Authorization: Bearer <TOKEN>`
- MR diambil dari token JWT. Mengembalikan booking aktif terdekat atau `404` bila tidak ada.

### 9. Riwayat dan Penunjang
- **Endpoint**: `GET /riwayat/list`, `GET /penunjang/radiologi`, `GET /penunjang/laboratorium`, `GET /penunjang/laboratorium_detail`
- **Header**: `Authorization: Bearer <TOKEN>`
- MR diambil dari JWT. `fs_mr` pada query string tidak dipakai. Detail laboratorium dibatasi ke transaksi milik pasien pada token.

### Catatan Integrasi
- JWT login dan registrasi menyimpan identitas pada `data.fs_mr`. Endpoint pasien memetakan No. RM pendek ke format SIMRS yang digunakan saat ini sebelum query.
- `POST /booking/simpan` dan `POST /bpjs/registrasi` tetap menerima `fs_mr` agar kompatibel dengan klien yang ada, tetapi nilainya harus cocok dengan MR pada JWT.
- Koneksi database dan `JWT_SECRET` wajib diatur melalui environment; lihat bagian Konfigurasi Environment di [README.md](README.md). Rotasi JWT secret membatalkan token yang sudah diterbitkan.
