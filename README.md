# PKU Gamping Online API

REST API untuk layanan online RSU PKU Muhammadiyah Gamping. Dibangun dengan
CodeIgniter 3 dan library [codeigniter-restserver](https://github.com/chriskacerguis/codeigniter-restserver)
(`chriskacerguis\RestServer\RestController`).

## Daftar Isi

- [Base URL](#base-url)
- [Format Response](#format-response)
- [Autentikasi](#autentikasi)
- [Endpoint](#endpoint)
  - [Auth](#auth)
  - [Layanan (Poli)](#layanan-poli)
  - [Dokter](#dokter)
  - [Jadwal Dokter](#jadwal-dokter)
  - [Booking Antrian](#booking-antrian)
  - [Beranda](#beranda)
  - [Antrean](#antrean)
  - [BPJS](#bpjs)
  - [Farmasi](#farmasi)
  - [Riwayat](#riwayat)
  - [Penunjang](#penunjang)
  - [Master](#master)
  - [Wilayah](#wilayah)

## Base URL

```
http://localhost/<nama-folder-project>/index.php/api
```

> Internet Server (XAMPP/Laragon) menggunakan `index.php`. Jika memakai
> `php -S localhost:8000`, gunakan `http://localhost:8000/index.php/api`.

Semua angka MR yang dikirim dapat berupa nomor polos (maks. 10 digit) dan akan
dikonversi otomatis ke format SIMRS (`347104100000000` + nomor), atau langsung
format panjang MR SIMRS.

## Format Response

Default format `json`. Field status menggunakan `status` (boolean), field pesan
menggunakan `message`.

```json
{
    "status": true,
    "message": "Login berhasil",
    "data": {}
}
```

Semua CORS diizinkan (allow any domain) dan seluruh format `json`, `array`,
`csv`, `html`, `jsonp`, `php`, `serialized`, `xml` didukung.

## Autentikasi

Autentikasi memakai **JWT (HS256)**. Endpoint yang dilindungi wajib menyertakan
header:

```
Authorization: Bearer <TOKEN>
```

Token diperoleh dari `POST /auth/login` atau `POST /auth/register` dan berlaku
selama **24 jam**. Bila token tidak valid/kadaluarsa, server membalas `401`
dengan `{"status": false, "message": "Unauthorized Access"}`.

Endpoint yang saat ini memverifikasi JWT: `auth/me`, `user/fcm-token`,
`booking/simpan`, `booking/aktif`, `bpjs/registrasi`, `beranda`, `antrean`,
`riwayat`, dan `penunjang`.
Endpoint katalog seperti `layanan`, `dokter`, `jadwal`, `farmasi`, `master`, dan
`wilayah` bersifat publik, demikian juga `auth/login`, `auth/register`,
`auth/cekNik`, dan endpoint OTP/password-reset. Endpoint Riwayat dan Penunjang
mengambil MR dari JWT; parameter `fs_mr` pada query string tidak digunakan.
Detail laboratorium juga dibatasi ke transaksi milik MR pada token.

## Konfigurasi Environment

Kredensial database tidak disimpan di repository. Atur variabel berikut pada
environment proses PHP/Apache Laragon, lalu restart Apache:

| Variabel | Kegunaan |
| -------- | -------- |
| `JWT_SECRET` | Secret HS256 acak, minimal 32 karakter |
| `DB_HOSPITAL_HOST`, `DB_HOSPITAL_USERNAME`, `DB_HOSPITAL_PASSWORD` | SQL Server SIMRS |
| `DB_LOCAL_HOST`, `DB_LOCAL_USERNAME`, `DB_LOCAL_PASSWORD` | Database akun online |
| `DB_FARMASI_HOST`, `DB_FARMASI_USERNAME`, `DB_FARMASI_PASSWORD` | Database antrean farmasi |
| `DB_HOSPITAL_DATABASE`, `DB_LOCAL_DATABASE`, `DB_FARMASI_DATABASE` | Opsional; default `HOSPITAL`, `pkug_online`, `antrian` |

Untuk melihat nama kolom tabel SIMRS yang dipakai API, jalankan `php db_test.php`
dari terminal. Skrip ini read-only, memerlukan driver `sqlsrv`, dan hanya dapat
dijalankan lewat CLI.

Secret JWT yang baru akan membuat token lama tidak berlaku; pasien perlu login
kembali setelah perubahan secret.

---

## Endpoint

### Auth

#### POST /auth/login

Login akun pasien, mengembalikan JWT.

| Parameter   | Tipe   | Wajib | Keterangan       |
| ----------- | ------ | :---: | ---------------- |
| `fs_email`  | string |   ✔   | Email akun       |
| `fs_password` | string |  ✔   | Password akun    |

**Response `200`**

```json
{
    "status": true,
    "message": "Login berhasil",
    "token": "<JWT>",
    "user": {
        "fs_id": "uuid",
        "fs_mr": "3471041000000001",
        "fs_email": "pasien@mail.com",
        "fs_nik": "3401...",
        "fs_nm_pasien": "Nama Pasien"
    }
}
```

#### POST /auth/cekNik

Cek apakah NIK sudah punya akun, dan apakah datanya ada di SIMRS. Dipakai saat
pendaftaran pasien baru.

| Parameter | Tipe   | Wajib | Keterangan |
| --------- | ------ | :---: | ---------- |
| `fs_nik`  | string |   ✔   | NIK pasien |

**Response `200`** (sudah terdaftar):

```json
{ "status": false, "message": "NIK ini sudah terdaftar sebagai akun. Silakan login." }
```

**Response `200`** (ditemukan di SIMRS):

```json
{
    "status": true,
    "found": true,
    "fs_mr": "3471041000000001",
    "fs_nm_pasien": "Nama Pasien",
    "fd_tgl_lahir": "1990-01-01",
    "message": "Data pasien ditemukan di SIMRS"
}
```

**Response `200`** (pasien baru):

```json
{ "status": true, "found": false, "message": "NIK tidak ditemukan, akan membuat data pasien baru" }
```

#### POST /auth/register

Membuat akun pasien baru. Jika `fs_mr` kosong, data rekam medis dibuat otomatis
di SIMRS (pasien baru). Jika `fs_mr` diisi, RM + NIK diverifikasi di SIMRS.
Mengembalikan JWT (auto-login).

| Parameter          | Tipe   | Wajib | Keterangan                                              |
| ------------------ | ------ | :---: | ------------------------------------------------------- |
| `fs_email`         | string |   ✔   | Email akun                                              |
| `fs_password`      | string |   ✔   | Minimal 8 karakter                                      |
| `fs_nik`           | string |   ✔   | NIK pasien                                              |
| `fs_mr`            | string |       | No. RM jika pasien sudah ada di SIMRS                   |
| `fs_nm_pasien`     | string |       | Nama pasien (khusus pasien baru)                        |
| `fs_no_hp`         | string |       | No. HP (khusus pasien baru)                             |
| `fs_temp_lahir`    | string |       | Tempat lahir                                            |
| `fd_tgl_lahir`     | string |       | Tanggal lahir `YYYY-MM-DD`                              |
| `fs_jenis_kelamin` | string |       | Jenis kelamin                                           |
| `fs_gol_darah`     | string |       | Golongan darah                                          |
| `fs_alamat`        | string |       | Alamat                                                  |
| `fs_kd_agama`      | string |       | Kode agama                                              |
| `fs_kd_pekerjaan`  | string |       | Kode pekerjaan                                          |
| `fs_kd_pendidikan` | string |       | Kode pendidikan                                         |
| `fs_status_kawin`  | string |       | Kode status kawin                                       |
| `fs_kd_suku`       | string |       | Kode suku (default `004`)                               |
| `fs_nm_ibu_kandung`| string |       | Nama ibu kandung                                        |
| `fs_warga_negara`  | string |       | Kewarganegaraan                                         |
| `fs_kd_kelurahan`  | string |       | Kode kelurahan                                          |

**Response `201`**

```json
{
    "status": true,
    "message": "Akun berhasil dibuat",
    "token": "<JWT>",
    "user": { "fs_id": "uuid", "fs_mr": "3471041000000001", "fs_email": "pasien@mail.com", "fs_nik": "3401...", "fs_nm_pasien": "Nama Pasien" }
}
```

#### GET /auth/me

Mengambil profil akun yang sedang login beserta data medis dari SIMRS.

- **Header**: `Authorization: Bearer <TOKEN>`

**Response `200`**

```json
{
    "status": true,
    "data": {
        "fs_id": "uuid",
        "fs_mr": "3471041000000001",
        "fs_email": "pasien@mail.com",
        "fs_nik": "3401...",
        "fd_created_at": "2026-01-01 10:00:00",
        "simrs": { "FS_NM_PASIEN": "Nama Pasien", "FD_TGL_LAHIR": "1990-01-01", "...": "..." }
    }
}
```

### Layanan (Poli)

#### GET /layanan/list

Daftar poli/layanan aktif yang relevan (Rawat Jalan).

- **Autentikasi**: tidak perlu

**Response `200`**

```json
{
    "status": true,
    "data": [
        { "fs_kd_layanan": "P001", "fs_nm_layanan": "Poli Penyakit Dalam" }
    ]
}
```

### Dokter

#### GET /dokter/list

Daftar dokter spesialis pada suatu layanan/poli.

- **Autentikasi**: tidak perlu

| Parameter       | Tipe   | Wajib | Keterangan    |
| --------------- | ------ | :---: | ------------- |
| `fs_kd_layanan` | string |   ✔   | Kode layanan  |

**Response `200`**

```json
{
    "status": true,
    "data": [
        { "fs_kd_peg": "S188", "fs_nm_alias": "dr. Ali", "fs_nm_peg": "dr. Ali Sp.PD", "fs_kd_layanan": "P001", "fs_nm_layanan": "Poli Penyakit Dalam" }
    ]
}
```

### Jadwal Dokter

#### GET /jadwal/list

Jadwal dokter 45 hari ke depan beserta ketersediaan kuota.

- **Autentikasi**: tidak perlu

| Parameter              | Tipe   | Wajib | Keterangan     |
| ---------------------- | ------ | :---: | -------------- |
| `fs_kd_layanan`        | string |   ✔   | Kode layanan   |
| `fs_kd_petugas_medis`  | string |   ✔   | Kode dokter    |

**Response `200`** (setiap item ditambah field `is_closed`, `is_full`,
`can_book`, `terdaftar`)

```json
{
    "status": true,
    "data": [
        {
            "fd_tgl": "2026-06-01",
            "fn_hari": "2",
            "is_closed": false,
            "is_full": false,
            "can_book": true,
            "terdaftar": 3
        }
    ]
}
```

### Booking Antrian

#### POST /booking/simpan

Membuat booking/pendaftaran jadwal dokter.

- **Header**: `Authorization: Bearer <TOKEN>`

| Parameter              | Tipe   | Wajib | Keterangan                          |
| ---------------------- | ------ | :---: | ----------------------------------- |
| `fs_mr`                | string |   ✔   | No. RM (polos atau panjang)         |
| `fd_tgl_lahir`         | string |   ✔   | Tanggal lahir `YYYY-MM-DD`          |
| `fs_kd_layanan`        | string |   ✔   | Kode layanan                        |
| `fs_kd_petugas_medis`  | string |   ✔   | Kode dokter                         |
| `fn_hari`              | int    |   ✔   | Hari ke- (0–6)                      |
| `fd_tgl_antrian`       | string |   ✔   | Tanggal periksa `YYYY-MM-DD`        |
| `fs_kd_sesion_poli`    | string |   ✔   | Kode sesi poli                      |
| `fs_jam_mulai`         | string |   ✔   | Jam mulai `HH:MM`                   |

**Response `200`**

```json
{
    "status": true,
    "message": "Booking berhasil",
    "data": {
        "no_booking": "BO1000001A",
        "no_antrian": "AN1000001A",
        "no_urut": 1
    }
}
```

Aturan validasi: MR + tanggal lahir harus cocok, kuota tidak penuh, maksimal
1 booking per jadwal dan 3 pendaftaran per hari.

#### GET /booking/aktif

Mengambil booking aktif pasien (tanggal periksa >= hari ini).

- **Header**: `Authorization: Bearer <TOKEN>`
- **MR**: diambil dari token JWT, bukan parameter.

**Response `200`**

```json
{
    "status": true,
    "data": {
        "no_booking": "BO1000001A",
        "no_antrian": "AN1000001A",
        "no_urut": 1,
        "fd_tgl_periksa": "YYYY-MM-DD",
        "fs_jam_mulai": "08:00",
        "fs_kd_layanan": "P001",
        "fs_kd_petugas_medis": "S188",
        "poliName": "Poli Penyakit Dalam",
        "dokterName": "dr. Ali Sp.PD"
    }
}
```

### Beranda

#### GET /beranda

Mengambil identitas pasien dari `data.fs_mr` pada JWT dan booking aktif terdekat
untuk hari ini atau sesudahnya. MR tidak diterima dari query string.

- **Header**: `Authorization: Bearer <TOKEN>`
- **Response `200`**: `data.pasien` memuat MR dan nama pasien; `data.booking_aktif`
  memuat nomor booking/antrian, urutan, tanggal, jam, poli, dan dokter. Nilai
  `booking_aktif` adalah `null` bila tidak ada booking aktif.

### Antrean

#### GET /antrean (alias GET /antrean/status)

Mengambil booking aktif milik pasien pada JWT dan status proses layanannya.

- **Header**: `Authorization: Bearer <TOKEN>`
- **MR**: diambil dari token JWT.

| Parameter       | Tipe   | Wajib | Keterangan |
| --------------- | ------ | :---: | ---------- |
| `fs_kd_antrian` | string |       | Memilih booking aktif milik pasien yang login |

`status_pasien`: `0` booking belum terdaftar, `1` terdaftar/menuju triage,
`2` triage selesai/menunggu poli, `3` pelayanan poli tercatat selesai.

```json
{
    "status": true,
    "data": {
        "antrean": {
            "no_booking": "BO...",
            "no_antrian": "AN...",
            "no_urut": 1,
            "tanggal_periksa": "YYYY-MM-DD",
            "nama_poli": "Nama Poli",
            "nama_dokter": "Nama Dokter"
        },
        "status_pasien": 0,
        "status_text": "Booking, belum terdaftar",
        "sedang_dilayani": null,
        "nomor_terakhir_dilayani": null,
        "jumlah_di_depan": null,
        "lokasi_poli": null
    }
}
```

`sedang_dilayani` dan `lokasi_poli` belum memiliki sumber data terverifikasi.
`nomor_terakhir_dilayani` hanya menunjukkan nomor terakhir pada rekam medis
poli, bukan nomor panggilan langsung. Nilai antrean untuk hari ini perlu
diverifikasi pada database SIMRS.

### BPJS

#### POST /bpjs/registrasi

Melengkapi data HP dan rujukan BPJS untuk booking pasien milik akun yang login.

- **Header**: `Authorization: Bearer <TOKEN>`
- `fs_mr` harus cocok dengan MR pada token dan `fs_kd_trs` harus merupakan booking aktif milik pasien tersebut.

| Parameter         | Tipe   | Wajib | Keterangan              |
| ----------------- | ------ | :---: | ----------------------- |
| `fs_kd_trs`       | string |   ✔   | No. booking             |
| `fs_mr`           | string |   ✔   | No. RM                  |
| `fs_no_hp`        | string |   ✔   | No. HP                  |
| `fs_kd_identitas` | string |       | NIK/identitas           |
| `fs_kd_reff`      | string |       | No. rujukan BPJS        |

**Response `200`**

```json
{ "status": true, "message": "Registrasi BPJS berhasil" }
```

### Farmasi

#### GET /farmasi/antrean

Daftar nomor antrean apotek, dipisah obat racik dan non-racik.

- **Autentikasi**: tidak perlu

**Response `200`**

```json
{
    "status": true,
    "data": {
        "racik": ["B-001", "D-002"],
        "non_racik": ["A-001", "C-002"]
    }
}
```

### Riwayat

#### GET /riwayat/list

Riwayat kunjungan/pelayanan rawat jalan pasien (paginasi 10 per halaman).

- **Header**: `Authorization: Bearer <TOKEN>`
- **MR**: diambil dari token JWT.

| Parameter | Tipe | Wajib | Keterangan |
| --------- | ---- | :---: | ---------- |
| `page`    | int  |       | Halaman (default `1`) |

**Response `200`**

```json
{
    "status": true,
    "data": [
        { "FS_KD_REG": "...", "FD_TGL_MASUK": "2026-01-01", "poli": "Poli Umum", "dokter": "dr. X", "FS_TERAPI": "..." }
    ],
    "pagination": { "current_page": 1, "total_pages": 5, "total_items": 42, "has_next": true }
}
```

### Penunjang

#### GET /penunjang/radiologi

Hasil pemeriksaan radiologi pasien.

- **Header**: `Authorization: Bearer <TOKEN>`
- **MR**: diambil dari token JWT.

**Response `200`**

```json
{
    "status": true,
    "data": [
        { "fs_kd_trs": "...", "fd_tgl_trs": "2026-01-01", "FS_NM_TARIF": "Rontgen Thorax", "FS_KETERANGAN2HASIL_RAD": "...", "urllink1": "..." }
    ]
}
```

#### GET /penunjang/laboratorium

Daftar transaksi pemeriksaan laboratorium pasien (paginasi 10 per halaman).

- **Header**: `Authorization: Bearer <TOKEN>`
- **MR**: diambil dari token JWT.

| Parameter | Tipe | Wajib | Keterangan |
| --------- | ---- | :---: | ---------- |
| `page`    | int  |       | Default `1` |

**Response `200`**

```json
{
    "status": true,
    "data": [ { "FS_KD_TRS": "...", "FD_TGL_TRS": "2026-01-01", "FS_JAM_TRS": "08:00" } ],
    "page": 1
}
```

#### GET /penunjang/laboratorium_detail

Detail hasil satu transaksi laboratorium.

- **Header**: `Authorization: Bearer <TOKEN>`
- Transaksi hanya mengembalikan hasil jika terhubung ke MR pada JWT.

| Parameter   | Tipe   | Wajib | Keterangan          |
| ----------- | ------ | :---: | ------------------- |
| `fs_kd_trs` | string |   ✔   | ID transaksi lab    |

**Response `200`**

```json
{
    "status": true,
    "data": [
        { "pemeriksaan": "Hemoglobin", "hasil": "14.2", "satuan": "g/dL", "nilai_rujukan": "13-17", "flag": "N" }
    ]
}
```

### Master

Endpoint data referensi dari SIMRS.

- **Autentikasi**: tidak perlu (semua GET)

| Endpoint                  | Keterangan              | Field `data`                                  |
| ------------------------- | ----------------------- | --------------------------------------------- |
| `GET /master/pekerjaan`   | Daftar pekerjaan        | `FS_KD_PEKERJAAN_DK`, `FS_NM_PEKERJAAN_DK`    |
| `GET /master/pendidikan`  | Daftar pendidikan       | `FS_KD_PENDIDIKAN_DK`, `FS_NM_PENDIDIKAN_DK`  |
| `GET /master/status_kawin`| Daftar status kawin     | `FS_KD_STATUS_KAWIN_DK`, `FS_NM_STATUS_KAWIN_DK` |
| `GET /master/agama`       | Daftar agama            | `FS_KD_AGAMA`, `FS_NM_AGAMA`                  |

### Wilayah

Endpoint data wilayah bertingkat dari SIMRS.

- **Autentikasi**: tidak perlu (semua GET)

| Endpoint                   | Parameter           | Wajib | Keterangan                          |
| -------------------------- | ------------------- | :---: | ----------------------------------- |
| `GET /wilayah/provinsi`    | –                   |       | Daftar provinsi                     |
| `GET /wilayah/kabupaten`   | `fs_kd_propinsi`    |   ✔   | Daftar kabupaten/kota               |
| `GET /wilayah/kecamatan`   | `fs_kd_kabupaten`   |   ✔   | Daftar kecamatan                    |
| `GET /wilayah/kelurahan`   | `fs_kd_kecamatan`   |   ✔   | Daftar kelurahan/desa               |
| `GET /wilayah/detail`      | `fs_kd_kelurahan`   |   ✔   | Detail lengkap (kel→kec→kab→prov)   |

**Response `200`** (contoh `GET /wilayah/detail`)

```json
{
    "status": true,
    "data": {
        "FS_NM_KELURAHAN": "Banyuraden",
        "FS_NM_KECAMATAN": "Gamping",
        "FS_NM_KABUPATEN": "Sleman",
        "FS_NM_PROPINSI": "DI Yogyakarta"
    }
}
```

---

## Kode Status HTTP

| Kode  | Arti                                     |
| ----- | ---------------------------------------- |
| `200` | Berhasil                                 |
| `201` | Dibuat (registrasi berhasil)             |
| `400` | Parameter tidak lengkap / data tidak valid |
| `401` | Tidak terautentikasi / kredensial salah  |
| `404` | Data tidak ditemukan                     |
| `409` | Konflik (email/NIK sudah terdaftar)      |
| `500` | Kesalahan server/database                |

## Contoh Penggunaan

Contoh request tersedia di file [`api_requests.http`](api_requests.http) (dapat
dijalankan dengan REST Client VS Code / JetBrains HTTP Client).

```bash
# Login
curl -X POST "http://localhost/<nama-folder-project>/index.php/api/auth/login" \
  -d "fs_email=<email-pasien>" -d "fs_password=<password>"

# Akses endpoint terproteksi
curl "http://localhost/<nama-folder-project>/index.php/api/booking/aktif" \
  -H "Authorization: Bearer <TOKEN>"
```
