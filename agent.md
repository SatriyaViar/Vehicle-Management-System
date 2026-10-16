# Petunjuk kerja FleetOps

Baca `docs/technical-design.md` sebelum merancang, mengubah, atau menguji aplikasi. Dokumen itu adalah acuan scope, BR-01 sampai BR-21, model data, diagram aktivitas, kontrak waktu/API, serta skenario uji. Jika implementasi menemukan kontradiksi, perbaiki rancangan yang terkait dan minta keputusan pengguna; jangan menambah fitur diam-diam. Proyek ini adalah technical test Fullstack Developer (Intern) dengan target pengumpulan 16 Oktober 2026 pukul 12.00.

## Status dan urutan SDLC

Spesifikasi `docs/technical-design.md` sudah disetujui dan pengguna mengizinkan pelaksanaan rencana. Implementasi dikerjakan oleh main agent secara native. Rencana ada di `docs/superpowers/plans/2026-10-15-fleetops-v1.md`; jangan mulai fitur di luar scope yang dikunci tanpa keputusan pengguna.

Urutan pekerjaan setelah persetujuan: (1) Docker Compose untuk aplikasi Laravel 12 + Vue 3/Vite + Hope UI secukupnya dan PostgreSQL, migration dan seeder; (2) login dan workflow booking/approval/log; (3) dashboard dan ekspor Excel; (4) feature tests pada database PostgreSQL terpisah, uji UI responsif, dan README. Kerjakan serta verifikasi satu alur utuh sebelum memperluas cakupan.

## Target versi pertama

- Admin mencatat pemesanan atas nama pegawai, memilih kendaraan, driver, dua approver berbeda, dan hanya boleh mengedit sebelum level 1 memutuskan.
- Approval berurutan tepat dua level. Reject pada salah satu level mengakhiri booking. Booking `approved` baru dihitung sebagai pemakaian setelah admin menandainya `completed`.
- Cek bentrok kendaraan dan driver pada rentang `[start_at, end_at)` untuk booking `pending`, `approved`, dan `completed`. `rejected` tidak memblokir. Early completion tidak membuka slot lebih cepat.
- Semua waktu bisnis WIB (Asia/Jakarta). Input `datetime-local` tanpa offset ditafsirkan di Laravel sebagai WIB; respons mengandung offset; dashboard memakai `completed_at` WIB, laporan memakai `created_at` WIB dengan filter batas akhir eksklusif.
- Satu origin: session Laravel, CSRF, JSON routes pada `routes/web.php`, Vue untuk UI. Backend menjaga seluruh izin, state, dan waktu server.
- Reviewer menjalankan aplikasi melalui Docker Compose; Vite membangun aset Vue dalam image aplikasi. Migration/seeding eksplisit; jangan jalankan `migrate:fresh` pada database demo ketika menjalankan tes.
- Enam event log, satu grafik pemakaian, laporan periodik dan Excel `.xlsx`, enam jenis halaman sesuai spesifikasi, feature tests, PDM, activity diagram, dan README yang bisa digunakan penilai.

## Batas cakupan

Jangan membuat CRUD master data, portal pegawai, halaman Approvals atau Activity Logs tersendiri, modul BBM/servis/GPS, notifikasi, user management, approval engine, Redis, queue, repository pattern, atau analitik tambahan untuk versi pertama. Lokasi (pusat, cabang, enam tambang), kendaraan, driver, dan approver diisi melalui seeder. CRUD kendaraan/driver/lokasi hanya dipertimbangkan setelah seluruh target inti lulus uji dan pengguna meminta penambahan.

Jangan menebak versi PHP, PostgreSQL, atau Node dalam README. Periksa versi aktual sesudah lingkungan implementasi tersedia. Jangan memasukkan secret `.env` atau akun sungguhan ke repo; README boleh menuliskan kredensial **akun demo dari seeder**.

## Skill lokal yang relevan

Gunakan tool `skill` bawaan OpenCode untuk memuat skill yang relevan sebelum langkah yang memerlukannya; nama di bawah berasal dari skill yang tersedia pada laptop ini. Pilih sesuai pekerjaan, tidak perlu menjalankan semuanya setiap saat. Jangan mengunduh atau menyalin berkas skill ke proyek.

| Pekerjaan | Skill | Kapan dipakai |
|---|---|---|
| Memulai sesi kerja baru | `using-superpowers` | Periksa aturan pemilihan skill sebelum tindakan; bila sudah dimuat di sesi, jangan muat ulang. |
| Kebutuhan/desain baru atau perubahan perilaku | `brainstorming` | Sepakati desain dan perubahan scope sebelum implementasi. |
| Rencana setelah spesifikasi disetujui | `writing-plans` | Buat urutan tugas, file, dependency, dan verifikasi per langkah sebelum coding. |
| Melaksanakan rencana yang disetujui | `executing-plans` | Kerjakan secara bertahap di sesi ini. |
| Fitur atau bugfix dengan logika bisnis | `test-driven-development` | Mulai dari skenario yang gagal, implementasikan, lalu verifikasi. |
| Bug atau kegagalan tes | `systematic-debugging` | Cari akar masalah sebelum mengubah kode. |
| Pilihan implementasi | `ponytail` | Gunakan solusi terkecil yang memenuhi kontrak; jangan memotong validasi, keamanan, atau kebutuhan eksplisit. |
| Desain dan implementasi enam halaman Vue | `antislop` bersama `ui-ux-pro-max`, `antislop-ui`, `antislop-human`, `antislop-layoutmobile` sesuai kebutuhan | Pakai Hope UI secukupnya; cek interaksi, aksesibilitas, dan mobile. Sebelum memulai kerja UI, ikuti instruksi `antislop` untuk menanyakan kapan filternya diterapkan kepada pengguna. |
| Teks UI, README, dan copy | `antislop-copywriting` bersama `antislop` | Tulis klaim yang bisa dibuktikan dan instruksi yang langsung dapat diikuti. |
| Menerima masukan review kode | `receiving-code-review` | Verifikasi sarannya terhadap kode dan kontrak bisnis sebelum mengubah implementasi. |
| Menyatakan pekerjaan selesai | `verification-before-completion` | Jalankan tes/build yang relevan, baca hasilnya, dan laporkan yang sungguh terverifikasi. |

Ikuti instruksi skill yang dimuat serta instruksi pengguna yang lebih baru jika scope berubah. Skill adalah panduan proses, bukan alasan menambah framework, dependensi, dokumen, atau menu baru.

Main agent mengerjakan sebagian besar implementasi secara native. Subagent hanya boleh dipertimbangkan bila tugasnya benar-benar terpisah dan memberi manfaat nyata; minta persetujuan eksplisit dari pengguna sebelum mengirim tugas tersebut.

## Pemeriksaan sebelum penyerahan

- Migration + seeder bisa dijalankan dari database kosong; dua akun approver yang berbeda tersedia untuk demo; semua constraint/index dalam spesifikasi ada.
- Tes membuktikan konflik kendaraan/driver, urutan keputusan, edit terbatas, reject, completion waktu server, enam log, batas bulan WIB, laporan, serta isi file Excel.
- Admin masuk ke `/dashboard`, approver ke `/bookings`. Tindakan yang disembunyikan Vue tetap ditolak Laravel bila dipanggil langsung.
- `README.md` menjelaskan cara instalasi, konfigurasi, menjalankan aplikasi/tes, versi aktual, kredensial demo, dan langkah demo end-to-end.
- Hanya klaim fitur yang benar-benar bekerja. Tidak ada tombol/menu kosong dan tidak ada data grafik statis yang berpura-pura berasal dari booking.
