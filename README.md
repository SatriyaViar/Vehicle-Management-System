# FleetOps

Aplikasi pemesanan kendaraan perusahaan tambang. Admin pool mencatat pemakai, kendaraan, driver, jadwal, dan dua penyetuju. Kendaraan baru dihitung dipakai setelah admin menandai perjalanan selesai.

## Teknologi

| Bagian | Versi yang diuji |
|---|---|
| PHP | 8.2.34 |
| Laravel | 12.70.0 |
| PostgreSQL | 18.6 |
| Node.js untuk build frontend | 22.23.3 |
| Vue | 3.5.44 |
| Vue Router | 4.6.4 |
| Vite | 6.4.4 |
| Dashboard style | Hope UI Vue Dashboard 4.0.1, layout dan styling yang dipakai saja |
| Excel | PhpSpreadsheet 5.10.0 |

Semua versi di atas diambil dari image/runtime atau lockfile yang digunakan saat aplikasi diuji. PHP dan Node lokal tidak diperlukan, cukup Docker Desktop dengan Docker Compose.

## Menjalankan aplikasi

1. Salin `.env.example` menjadi `.env`:

   ```powershell
   Copy-Item .env.example .env
   ```

   Isi `DB_PASSWORD` dengan kata sandi lokal pilihanmu.

2. Build image aplikasi:

   ```powershell
   docker compose build app
   ```

3. Buat APP_KEY. Salin hasil perintah ini ke nilai `APP_KEY` di `.env`:

   ```powershell
   docker compose run --rm --no-deps app php artisan key:generate --show
   ```

4. Jalankan Laravel dan PostgreSQL:

   ```powershell
   docker compose up -d
   ```

5. Buat tabel dan isi data demo:

   ```powershell
   docker compose exec app php artisan migrate --seed --force
   ```

6. Buka [http://localhost:8000](http://localhost:8000).

PostgreSQL disimpan dalam volume Docker bernama `postgres_data`. `docker compose down` menghentikan aplikasi tanpa menghapus data. `docker compose down -v` menghapus volume dan seluruh data database.

## Akun demo

Semua akun demo memakai kata sandi `demo12345`.

| Peran | Email |
|---|---|
| Admin pool | `admin@fleetops.test` |
| Penyetuju level 1 | `manager@fleetops.test` |
| Penyetuju level 2 | `head@fleetops.test` |

## Cara demo

1. Login sebagai admin, buat pemesanan atas nama pegawai, lalu pilih kendaraan, driver, jadwal, dan dua approver yang berbeda.
2. Login sebagai penyetuju level 1 dan setujui pemesanan. Penyetuju level 2 belum dapat memberi keputusan sebelum tahap ini selesai.
3. Login sebagai penyetuju level 2 dan setujui pemesanan. Admin sekarang dapat melihat booking berstatus disetujui.
4. Setelah waktu mulai perjalanan, login sebagai admin dan pilih **Tandai perjalanan selesai**.
5. Lihat dashboard. Grafik menghitung booking selesai menurut `completed_at` WIB. Halaman laporan memfilter tanggal pembuatan booking dan menyediakan unduhan Excel.

Admin boleh mengubah booking hanya selama level 1 belum memutuskan. Booking yang ditolak tidak menahan kendaraan atau driver. Booking selesai lebih awal tetap menahan slot sampai `end_at` yang direncanakan.

## Tes

Compose menyediakan database terpisah bernama `fleetops_test` untuk tes, bukan database demo:

```powershell
docker compose --profile test run --rm test php artisan test
```

Pada instalasi pertama, `docker/postgres/init.sql` membuat database tes saat volume PostgreSQL dibuat. Tes feature menggunakan PostgreSQL, termasuk constraint dan query waktu. Image web membangun Vue dengan `npm ci` dan Vite; container Laravel menyajikan hasil build pada origin yang sama.

## Scope versi ini

Versi pertama mencakup login session dan CSRF, dua peran, pemesanan dan edit terbatas, validasi bentrok kendaraan/driver, dua level approval, penolakan, completion, log proses, dashboard, laporan periodik, Excel, dan data demo delapan lokasi. Monitoring BBM, jadwal servis, GPS, notifikasi, dan CRUD master kendaraan/driver/lokasi belum dibuat.

Pemeriksaan bentrok dilakukan di aplikasi saat booking dibuat/diubah dan saat level 2 menyetujui. Pengecekan terakhir mencegah booking yang konflik lolos approval normal, tetapi tidak mengunci penulisan yang terjadi persis bersamaan.

## Atribusi

Layout dan stylesheet diadaptasi dari [Hope UI Vue Dashboard](https://github.com/iqonicdesignofficial/hope-ui-vue-dashboard) oleh Iqonic Design. Proyek template tersebut menyebut lisensi MIT pada README-nya. FleetOps memakai Vue/Vite dari Laravel dan tidak membawa seluruh halaman demo template.
