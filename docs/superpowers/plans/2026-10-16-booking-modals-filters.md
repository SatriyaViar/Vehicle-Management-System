# Modal Detail, Alert, dan Filter Booking Implementation Plan

> **For agentic workers:** Implement task-by-task with `executing-plans` after the user explicitly approves execution. Checkboxes track progress. No subagents, commits, or push without a separate explicit request.

**Goal:** Detail dan seluruh aksi booking dapat dipakai dalam modal, alert aplikasi tampil sebagai modal, dan daftar/laporan/ekspor memakai filter lengkap yang sama.

**Architecture:** Perluas satu query Eloquent yang sudah dipakai daftar dan ekspor. Vue memakai satu form filter bersama, query URL sebagai sumber filter aktif serta ID modal detail, satu dialog alert global, dan komponen detail yang dipakai dari daftar maupun laporan. Gunakan native `<dialog>` dan gaya Hope UI yang ada; form buat/edit tetap halaman penuh.

**Tech Stack:** Laravel 12, PostgreSQL, Vue 3, Vue Router, Axios, Vite, CSS Hope UI, native HTML dialog, PHPUnit, `node:test`.

**Spec:** Desain singkat yang disetujui dalam percakapan 16 Oktober 2026; aturan dasar BR-01–BR-21 dan kontrak waktu/otorisasi di `docs/technical-design.md`. Permintaan terakhir: tulis plan dahulu dan tunggu aba-aba implementasi.

## Global Constraints

- Filter `from`/`to` selalu mengacu pada `bookings.created_at` dalam Asia/Jakarta, awal inklusif dan hari sesudah `to` eksklusif; waktu booking dan completion tidak berubah.
- Parameter filter: `search` (kode/pemakai/tujuan), `status`, `from`, `to`, `vehicle_id`, `driver_id`, `approver_id`. Semuanya saling AND; pencarian teks case-insensitive dan `%`/`_` literal.
- Filter aktif di URL daftar/laporan, ikut persis ke ekspor Excel; `detail=ID` menyimpan modal terbuka dan Back menutup modal yang dibuka dari daftar/laporan.
- Detail modal berisi data, dua approval, log, keputusan, dan completion; Edit membuka form yang ada. Route lama `/bookings/:id` tetap menerima deep link lewat redirect ke `/bookings?detail=ID`.
- Semua konfirmasi dan kegagalan/sukses aksi request ditampilkan lewat dialog; validasi field dan empty/loading/error state halaman tetap terlihat inline. Login dan dashboard termasuk cakupan alert.
- Hak admin/approver serta CSRF tetap ditegakkan Laravel. Jangan menambah dependency, tabel, status booking, atau operasi bisnis baru.
- Sesudah login/session regeneration, backend mengembalikan CSRF token baru dan frontend memperbarui Axios serta meta token sebelum mutasi berikutnya.
- Pertahankan perubahan pengguna yang sudah ada di `agent.md`; jangan commit/push tanpa izin eksplisit.

## Review Focus

1. `search` berisi `%`, `_`, atau tanda kutip: diperlakukan sebagai teks literal dan tidak mengubah cakupan query (Task 1).
2. Approver memfilter `approver_id` milik orang lain: hasil tetap hanya booking yang ditugaskan ke akun login; detail booking lain tetap 403 (Task 1).
3. Query URL berisi `detail` dan filter bersama, lalu pengguna menekan Back/Reset: modal/fokus dan filter tidak saling hilang atau menghidupkan modal lagi (Task 2, 3, 5).
4. Escape membatalkan konfirmasi aksi; menutup modal detail mengembalikan fokus, tidak menyimpan keputusan (Task 4, 5).
5. Filter berubah ketika ekspor atau aksi modal berlangsung: ekspor hanya memakai filter yang **sudah diterapkan**, daftar/laporan diperbarui tanpa membuka data di luar hak akses (Task 3, 5).

## File Map

| File | Tanggung jawab |
|---|---|
| `app/Http/Requests/BookingFilterRequest.php`, `app/Models/Booking.php` | Validasi filter dan satu query bersama daftar/ekspor. |
| `app/Http/Controllers/BookingController.php`, `app/Http/Controllers/BookingReportController.php`, `routes/web.php` | Pakai filter tervalidasi yang sama; endpoint opsi filter login dengan hanya id/label. |
| `resources/js/booking-filters.js`, `resources/js/components/BookingFilters.vue` | Baca/tulis parameter URL yang dikenal; form filter daftar/laporan. |
| `resources/js/alerts.js`, `resources/js/components/AppAlert.vue`, `resources/js/App.vue` | Dialog global untuk info, error, konfirmasi; tombol dan Escape menyelesaikan promise satu kali. |
| `resources/js/pages/BookingDetail.vue`, `resources/js/pages/Bookings.vue`, `resources/js/pages/Reports.vue`, `resources/js/router/index.js` | Satu detail modal dari dua daftar, deep link URL dan refresh data setelah aksi. |
| `resources/js/pages/Login.vue`, `resources/js/pages/Dashboard.vue`, `resources/js/pages/BookingForm.vue`, `resources/js/layouts/AppLayout.vue` | Gunakan alert global untuk feedback request yang ada; pertahankan validasi/error state inline. |
| `app/Http/Controllers/AuthController.php`, `tests/Feature/AuthTest.php` | Kirim dan verifikasi CSRF token setelah login agar mutasi pertama memakai token session baru. |
| `resources/css/app.css` | Warna/fokus/ukuran dialog yang selaras dengan tema dan mobile. |
| `tests/Feature/BookingTest.php`, `tests/Feature/ReportingTest.php`, `tests/UI/*.test.js` | Verifikasi filter, ekspor, URL, dan state alert. |
| `docs/technical-design.md`, `README.md` | Tambahkan kontrak filter, modal, URL, dan cara pakai yang benar-benar sudah diuji. |

---

### Task 1: Filter Laravel dan ekspor yang konsisten

**Interfaces:** `Booking::scopeFiltered(Builder $query, array $filters): Builder`; query `search`, `status`, `from`, `to`, `vehicle_id`, `driver_id`, `approver_id`; `GET /api/booking-filter-options` untuk pengguna login, hanya `{vehicles:[{id,plate_number}],drivers:[{id,name}],approvers:[{id,name}]}`. `GET /api/bookings` dan `GET /api/reports/bookings/export` mengirim array hasil `$request->validated()` ke scope yang sama.

- [x] Tulis tes gagal di `tests/Feature/BookingTest.php`: buat dua booking berbeda lalu assert kombinasi `search=MiNe&status=pending&vehicle_id=<id>&driver_id=<id>&approver_id=<id>` hanya mengembalikan ID target; buat nama `Unit%31` vs `UnitX31` serta `Unit_31` vs `UnitX31` untuk memastikan pencarian `%25`/`_` literal. Assert ID/status/tanggal invalid memberi 422, opsi filter memerlukan login, serta approver tetap melihat hanya booking miliknya walau memakai ID approver lain dan membuka URL detail booking asing.
- [x] Tulis tes gagal di `tests/Feature/ReportingTest.php`: daftar dengan ketujuh parameter mengembalikan satu kode target; parse file `.xlsx` seperti tes ekspor yang sudah ada dan assert kode target ada, kode lain tidak. Pertahankan tes batas tanggal dan ekspor admin-only.
- [x] Jalankan tes spesifik untuk melihat kegagalan karena filter tambahan belum dipakai: `docker compose build app`, `docker compose --profile test run --rm test php artisan test --filter=BookingTest`, dan `docker compose --profile test run --rm test php artisan test --filter=ReportingTest`. Expected: test baru FAIL pada jumlah hasil, 422 atau opsi yang belum ada (bukan gagal boot); image Docker memuat kode saat build.
- [x] Perluas `BookingFilterRequest` (`search`: string max 120; tiga ID: integer + exists, approver harus role `approver`), ganti `scopeFiltered` ke array, escape wildcard dalam pencarian `ILIKE` berbasis binding, dan gunakan scope yang sama di dua controller. Tambahkan opsi filter ringkas di `routes/web.php`; jangan buka endpoint `booking-options` admin untuk approver.
- [x] Build ulang image dan jalankan tes spesifik sampai lulus. Periksa bahwa validasi/date boundary, keterbatasan role, dan format Excel lama tetap benar.

### Task 2: Kontrak filter URL yang dapat diuji

**Interfaces:** `readBookingFilters(query, defaults = {}) -> object` hanya tujuh parameter filter; `toBookingQuery(filters) -> object` membuang nilai kosong; `withDetail(query, id) -> object`, `withoutDetail(query) -> object` menjaga filter ketika ID ditambah/dihapus. Semua di `resources/js/booking-filters.js`; `detail` tidak pernah dikirim ke backend/Excel.

- [x] Tulis `tests/UI/booking-filters.test.js` (`node:test` + `node:assert/strict`): `readBookingFilters({search:'BK', detail:'7'})` menghasilkan `{search:'BK'}`; `withoutDetail(withDetail({status:'pending'},7))` menghasilkan `{status:'pending'}`; `toBookingQuery({search:'',status:'pending'})` menghasilkan `{status:'pending'}`. Assert query array/parameter asing diabaikan dan default periode laporan hanya berlaku bila **kedua** tanggal tidak ada, bukan menutupi tanggal setengah lengkap.
- [x] Jalankan `node --test tests/UI/booking-filters.test.js` dan amati FAIL pada import/fungsi yang belum ada (bukan sintaks tes).
- [x] Tulis fungsi kecil di `resources/js/booking-filters.js` sesuai tes; nilai query yang malformed tetap dapat divalidasi server, tidak disulap menjadi filter lain.
- [x] Jalankan ulang tes tersebut sampai lulus.

### Task 3: Form filter bersama di daftar dan laporan

**Interfaces:** `<BookingFilters :value="appliedFilters" :defaults="defaults" :options="options" :loading="loading" @apply="..." @reset="..." />`; `value` berasal dari route query; `apply` mengirim tujuh nilai filter, `reset` kembali ke tanpa filter untuk daftar dan bulan berjalan WIB untuk laporan. `options` dari `/api/booking-filter-options`. Kedua halaman me-request `/api/bookings` dengan `readBookingFilters(route.query, defaults)` dan memperbarui URL melalui `router.push/replace`.

- [x] Catat acceptance test gagal sebelum mengubah UI: daftar saat ini tidak punya filter dan laporan hanya punya tiga kolom; klik filter kendaraan/driver/approver, refresh, Back dan ekspor tidak dapat memenuhi hasil yang sama. Gunakan tes helper Task 2 sebagai pemeriksaan runnable perubahan URL.
- [x] Buat `resources/js/components/BookingFilters.vue` dengan input kata kunci, status, tanggal dibuat (WIB), kendaraan, driver, approver, Terapkan/Reset. Pastikan input memiliki label, status loading/error opsi, dan filter tetap bisa dipakai bila opsi gagal dimuat (tawarkan retry). Saat tanggal tidak lengkap atau `from > to`, tampilkan error dan jangan kirim query invalid dari form.
- [x] Pasang pada `Bookings.vue` dan `Reports.vue`. Saat pertama masuk laporan tanpa tanggal di URL, `router.replace` menuliskan tanggal awal bulan–hari ini WIB, tanpa menghapus `detail`; query parsial tetap diteruskan untuk divalidasi. Watch parameter filter di route agar refresh/Back memuat hasil yang sama; abaikan perubahan `detail` agar membuka modal tidak memicu fetch filter. Abaikan respons request lama bila filter berubah sebelum request selesai. Sembunyikan/nonaktifkan ekspor saat loading/gagal dan simpan snapshot filter *yang berhasil diterapkan*; ubah tombol ekspor menjadi request blob Axios dengan error inline sementara (modal dari Task 4/6), lalu `URL.createObjectURL`/revoke sesudah download. Reset dan submit menutup detail jika sedang terbuka.
- [x] Untuk hasil nol, bedakan `Belum ada pemesanan` dan `Tidak ada hasil sesuai filter`, dengan Reset sebagai aksi; pertahankan role approver dan layout tabel/kartu mobile.
- [x] Jalankan `node --test tests/UI/booking-filters.test.js`, `npm run build`, serta cek manual query string, refresh/Back, pencarian tanpa hasil, opsi filter, dan ekspor. Jika filter gagal, hasil lama tidak boleh diberi label sebagai hasil filter baru.

### Task 4: Dialog alert global yang aman untuk keyboard

**Interfaces:** `notice(message, title = 'Pemberitahuan'): Promise<void>`, `confirmAction(message, title = 'Konfirmasi'): Promise<boolean>`, `finishAlert(accepted = false): void`, dan `currentAlert: Ref<object|null>` dari `resources/js/alerts.js`. `AppAlert.vue` dipasang sekali di `App.vue`. Escape/batal menyelesaikan `confirmAction` dengan `false`, tombol utama dengan `true`; panggilan alert berturut-turut tidak menimpa promise sebelumnya.

- [x] Tulis `tests/UI/alerts.test.js` dengan `node:test`: panggil `const decision = confirmAction('Tolak?'); finishAlert(false); assert.equal(await decision, false)`; uji `finishAlert(true)` menghasilkan `true` satu kali; panggil `notice('Satu')` lalu `notice('Dua')` dan assert `currentAlert.value.message` menampilkan Satu hingga selesai, baru kemudian Dua. Event close terlambat dari Satu tidak boleh menutup Dua. Escape native memanggil `finishAlert(false)`; verifikasi lewat click-through Task 5.
- [x] Jalankan `node --test tests/UI/alerts.test.js` sampai terlihat FAIL karena API belum tersedia.
- [x] Implementasikan state/promise minimal di `alerts.js`, lalu `<dialog>` native pada `AppAlert.vue`: `showModal()`, judul terhubung `aria-labelledby`, fokus awal pada Batal untuk aksi berisiko, `cancel`/`close` idempoten (dialog berikutnya baru diaktifkan setelah dialog sebelumnya benar-benar ditutup), pengembalian fokus ke pemicu, serta Tab/Shift+Tab yang tetap berada pada tombol dialog saat alert bertumpuk di atas modal detail.
- [x] Tambahkan gaya di `resources/css/app.css` untuk foreground solid, backdrop, max-width/height dan scroll internal, fokus 3:1, warna terang/gelap dan target 44px. Pertahankan tema Hope UI; jangan impor Bootstrap JS.
- [x] Jalankan tes `node --test tests/UI/alerts.test.js` dan `npm run build` sampai lulus.

### Task 5: Detail modal dari daftar dan laporan, termasuk aksi workflow

**Interfaces:** `<BookingDetail :id="detailId" :return-focus="detailTrigger" @dismiss="closeDetail" @changed="reloadList" />` dirender hanya bila route daftar/laporan memiliki `?detail=ID`. `detailTrigger` adalah anchor daftar yang membuka modal atau `null` untuk deep link. Komponen bertanggung jawab atas `GET /api/bookings/{id}` dan aksi yang sama dengan halaman detail lama; tutup modal menghapus `detail` tetapi menjaga filter lain. Setelah aksi berhasil, refresh data modal dan daftar/laporan tanpa mengganti filter.

- [x] Catat acceptance test gagal pada browser sebelum edit: Detail masih pindah halaman, Back tidak menutup modal, approval/complete tidak tersedia dari daftar/laporan. Tes backend otorisasi dan workflow yang ada harus tetap hijau.
- [x] Ubah `BookingDetail.vue` menjadi `<dialog>` dengan rincian, dua approval, log, status, Setujui/Tolak/Selesaikan, dan Edit menuju route form lama. Ganti `window.confirm` dengan `confirmAction` untuk **ketiga** aksi; batal/Escape tidak memanggil API. Setelah request sukses kirim `changed`, refresh detail, dan tampilkan `notice`; kegagalan menampilkan `notice` tanpa menutup modal atau menghilangkan error state inline.
- [x] Ubah link kode/Detail pada `Bookings.vue` dan `Reports.vue` agar `router.push` dengan `withDetail(route.query, booking.id)`; komponen yang sama menangani daftar desktop/mobile dan laporan desktop/mobile. Saat modal ditutup gunakan `withoutDetail`; refresh/Back membuka/menutup sesuai URL, termasuk ID invalid/403 (tampilkan pesan + tombol tutup, tanpa data lama). Route `/bookings/:id` melakukan redirect ke `/bookings?detail=ID`; sesudah create/edit gunakan redirect ini atau target query langsung.
- [x] Pastikan `dialog.cancel`, tombol Tutup, perubahan route, dan unmount tidak meninggalkan scroll/fokus terperangkap. Parent menangkap anchor melalui `@click` dan meneruskan `return-focus`; setelah modal dilepas, pulihkan fokus ke anchor tersebut bila masih terhubung. Setelah keputusan berhasil, pindahkan fokus ke heading saat tombol aksi sebelumnya hilang. Pada modal yang berisi banyak log, header/tombol tetap dapat dicapai di mobile; alert konfirmasi boleh tampil di atas modal detail dan fokus kembali dengan benar.
- [x] Jalankan tes Node, feature tests workflow/role, dan build; klik via browser dari kedua halaman (admin & approver), lakukan L1/L2/reject/complete, batalkan aksi, refresh deep link, Back, dan uji akses 403.

### Task 6: Feedback request menyeluruh, dokumentasi, dan verifikasi

**Interfaces:** `notice` dipakai untuk error request login/dashboard/daftar/form/laporan/detail, sukses buat/edit/approval/complete, dan error ekspor; field-level `errors` tetap di bawah input; loading/empty/error state tetap ada. Tidak ada modal sukses login/logout baru bila tidak ada aksi yang perlu dikonfirmasi.

- [x] Catat acceptance test gagal: login gagal/form 422/error memuat/dashboard/ekspor masih hanya alert inline; error export dengan href tidak dapat diketahui Vue. Tulis tes `test_login_returns_the_rotated_csrf_token_for_the_next_mutation` yang membuktikan token dari login membuat POST valid-JSON mencapai validasi 422, bukan 419. Tes auth, reporting, dan booking yang ada menjadi dasar regresi backend.
- [x] Ganti pemanggilan feedback request pada `Login.vue`, `Dashboard.vue`, `BookingForm.vue`, `Bookings.vue`, `Reports.vue`, dan `BookingDetail.vue` dengan `notice` di cabang `catch`/sukses yang relevan; pertahankan error inline untuk retry dan validasi per field. Pastikan Login.vue memperbarui Axios dan meta token dari respons login. Jangan munculkan modal berulang akibat watcher, `onMounted`, atau respons lama yang menyusul request filter baru.
- [x] Perbarui `docs/technical-design.md` (kontrak query/endpoint, modal/detail URL, aksesibilitas) dan `README.md` (penggunaan filter, detail modal, ekspor) sesuai implementasi yang sudah terverifikasi; jangan menimpa perubahan yang belum di-commit di `agent.md`.
- [x] Verifikasi penuh: `node --test`, `npm run build`, `docker compose build app`, `docker compose --profile test run --rm test php artisan test`, `composer validate --no-check-publish`, `docker compose config --quiet`, `git diff --check`. Image harus di-build ulang sebelum tes Docker karena source di-`COPY` saat build.
- [x] Jalankan container dari image baru dan click-through: admin/approver, filter gabungan+Reset+Back+refresh, modal dari daftar/laporan, dialog konfirmasi dengan Escape, keputusan L1/L2/reject/complete, validasi form & kegagalan ekspor, unduhan Excel sesuai filter. Periksa tema terang/gelap, keyboard+fokus, 200% zoom dan viewport mobile tanpa horizontal overflow/exception console. Catat setiap interaksi dan hasilnya sebelum menyatakan selesai.

## Execution Gate

Rencana dilaksanakan di checkout saat ini setelah aba-aba eksplisit pengguna. Semua enam task sudah diverifikasi. Tidak ada delegasi atau push; agent tidak menjalankan perintah commit.
