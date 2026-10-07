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
