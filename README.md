# GDK Commerce — Website PPOB & Produk Digital

Frontend HTML + CSS + JavaScript, backend **PHP 8.1+ & MySQL**, supplier **Digiflazz**, payment gateway **dummy** (siap diganti gateway asli).

## Struktur

```
index.html, produk.html, tentang.html, kontak.html   Halaman publik
toko.html, produk-digital.html                       Toko produk digital (daftar & detail + checkout)
login.html, akun.html, pembayaran.html               Login/daftar, riwayat, status pembayaran
admin.html                                           Panel admin (khusus role admin)
kebijakan-privasi.html, syarat-ketentuan.html, 404.html
assets/css/style.css, assets/js/app.js               Tampilan & logika frontend (memanggil API)
assets/css/admin.css, assets/js/admin.js             Panel admin
assets/js/digital.js                                 Toko produk digital
assets/uploads/digital/                              Gambar produk digital (publik)
storage/digital/                                     File produk digital (PRIVAT, hanya lewat api/download.php)
assets/img/                                          Logo

api/                         Backend PHP
  config.sample.php          Contoh konfigurasi  → salin menjadi config (lihat langkah 3)
  bootstrap.php              Inisialisasi
  auth/                      me, register, login, logout
  catalog.php                Kategori, produk & harga, metode bayar
  check-name.php             Cek nama pelanggan PLN prabayar
  inquiry.php                Cek tagihan pascabayar
  orders.php                 Buat pesanan, daftar & detail pesanan
  payment/callback.php       Notifikasi dari payment gateway
  payment/simulate.php       Simulasi bayar (HANYA gateway dummy)
  webhook/digiflazz.php      Update status transaksi dari Digiflazz
  cron/sync-products.php     Sinkron produk & harga dari Digiflazz
  cron/check-orders.php      Kedaluwarsa & cek ulang status pesanan
  cron/make-admin.php        Jadikan pengguna sebagai admin (CLI)
  admin/                     API panel admin: dashboard, orders, products, users, supplier
  lib/                       Db, Auth, OrderService, Supplier/*, Payment/*

database/schema.sql          Struktur tabel
database/seed.sql            Kategori, brand, & produk contoh
database/seed_digital.sql    20 produk digital contoh (dari katalog gdkcommerce.id)
database/migrations/         Perubahan skema untuk database yang sudah berjalan
```

## Alur transaksi

```
Pilih produk → Buat pesanan (status: Menunggu Pembayaran)
  → Payment gateway kirim callback "PAID" (dummy: tombol "Simulasikan Pembayaran")
  → Pesanan dikirim ke Digiflazz (status: Diproses)
  → Webhook Digiflazz / pengecekan otomatis → Berhasil (SN tampil) atau Gagal (refund manual)
```

Harga **selalu dihitung di server**. Callback pembayaran diverifikasi dengan signature, nominal dicocokkan, dan setiap pesanan hanya bisa diproses satu kali (aman dari callback ganda).

---

## Deploy ke Hostinger

### 1. Database
1. hPanel → **Databases → MySQL Databases** → buat database + user. Catat nama DB, user, password.
2. Buka **phpMyAdmin** → pilih database → **Import** `database/schema.sql`, lalu `database/seed.sql`, lalu `database/seed_digital.sql`.

### 2. Upload file
hPanel → **File Manager** → `public_html` → upload seluruh isi folder (termasuk `.htaccess`, `api/`, `assets/`, `database/`).
Pastikan **PHP 8.1 atau lebih baru** (hPanel → Advanced → PHP Configuration) dan SSL aktif.

### 3. Konfigurasi (penting)
Salin `api/config.sample.php` lalu isi. Pilih salah satu lokasi:
- **Disarankan:** di luar `public_html`, dengan nama `gdk-config.php`
  (mis. `/home/u123456789/domains/domainanda.com/gdk-config.php`). File di luar `public_html` tidak bisa diakses dari internet.
- Atau `api/config.php` (sudah diblokir dari browser oleh `api/.htaccess`).

Isi minimal:
- `app.env` → `production`, `app.url` → domain Anda
- `db.*` → dari langkah 1 (host di Hostinger biasanya `localhost`)
- `payment.callback_secret` → string acak panjang

### 4. Uji coba (mode simulasi)
Dengan `supplier.driver = 'mock'` dan `payment.driver = 'dummy'`, semua transaksi disimulasikan:
daftar akun → beli produk → klik **Simulasikan Pembayaran** → transaksi berhasil.

Nomor uji supplier mock:
| Nomor tujuan berakhiran | Hasil |
|---|---|
| `0000` | Gagal (atau tagihan tidak ditemukan) |
| `9999` | Diproses dulu, lalu Berhasil |
| lainnya | Berhasil langsung |

### 5. Hubungkan Digiflazz
1. Daftar di [digiflazz.com](https://digiflazz.com), verifikasi akun, isi deposit.
2. Dashboard → **Atur Koneksi → API**:
   - Ambil **Username** dan **API Key** (Development untuk uji, Production untuk live).
   - **Whitelist IP** server Hostinger Anda. IP keluar bisa dicek lewat SSH: `curl -s https://api.ipify.org`
   - **Webhook URL:** `https://domainanda.com/api/webhook/digiflazz.php`, isi **Secret**, lalu salin ke `supplier.digiflazz.webhook_secret`.
3. Di config: `supplier.driver = 'digiflazz'`, isi `username`, `api_key`, `testing = true` (pakai API key Development).
4. Jalankan sinkron produk (SSH atau Cron "Run now"):
   ```
   php ~/domains/domainanda.com/public_html/api/cron/sync-products.php
   ```
   SKU contoh dari seed akan dinonaktifkan dan diganti SKU asli dari akun Anda. Brand Digiflazz yang
   belum dipetakan akan dicetak di akhir — petakan lewat phpMyAdmin di tabel `brands`
   (kolom `df_category` & `df_brand`), lalu sinkron ulang.
   Nama kategori/brand Digiflazz di seed adalah perkiraan; **cocokkan dengan hasil sync di akun Anda.**
5. Setelah uji coba lancar: ganti ke API key Production dan `testing = false`.

> **Pengaman:** selama `payment.driver = 'dummy'`, sistem **menolak pesanan** bila Digiflazz memakai saldo asli
> (`testing = false`). Ini mencegah produk terkirim tanpa pembayaran. Hubungkan gateway asli dulu sebelum live.

### 6. Cron Jobs
hPanel → **Advanced → Cron Jobs** → tambahkan:
| Jadwal | Perintah |
|---|---|
| Tiap 5 menit | `php /home/uXXXX/domains/domainanda.com/public_html/api/cron/check-orders.php` |
| Tiap 6 jam | `php /home/uXXXX/domains/domainanda.com/public_html/api/cron/sync-products.php` |

### 7. Payment gateway asli (tahap berikutnya)
Buat class baru di `api/lib/Payment/` yang mengimplementasikan `PaymentInterface`
(`create()` untuk membuat VA/QRIS, `parseCallback()` untuk verifikasi notifikasi), daftarkan di
`api/lib/Payment/Factory.php`, lalu daftarkan URL `https://domainanda.com/api/payment/callback.php`
di dashboard gateway (Midtrans / Xendit / Tripay).

---

## Panel Admin (`/admin.html`)

### Membuat admin pertama
1. Daftar akun biasa lewat website.
2. Jadikan admin, pilih salah satu:
   - phpMyAdmin → SQL: `UPDATE users SET role = 'admin' WHERE email = 'email@anda.com';`
   - SSH: `php ~/domains/domainanda.com/public_html/api/cron/make-admin.php email@anda.com`
3. Masuk, lalu menu **Admin** muncul di navbar. Admin berikutnya bisa ditambahkan dari tab **Pengguna**.

> Database yang dibuat **sebelum** panel admin ada: jalankan sekali `database/migrations/002_admin.sql` di phpMyAdmin.

### Fitur
| Tab | Isi |
|---|---|
| **Ringkasan** | Omzet, laba kotor & transaksi (hari ini / 7 / 30 hari), saldo supplier, peringatan refund / pesanan macet / produk gangguan, grafik 14 hari, produk terlaris |
| **Pesanan** | Filter status & tanggal, pencarian (invoice, nomor tujuan, email, produk), detail lengkap + log API. Aksi: cek ulang ke supplier, tandai berhasil (wajib SN), tandai gagal (wajib alasan), catatan internal |
| **Refund** | Pesanan gagal yang sudah dibayar. Kembalikan dana pelanggan, lalu klik **Refund Selesai** + keterangan |
| **Produk & Harga** | Ubah harga jual (ditolak bila di bawah modal), kunci harga, aktif/nonaktif, markup massal, pengaturan brand (aktif, terlaris, biaya admin pascabayar, pemetaan Digiflazz) |
| **Pengguna** | Cari, nonaktifkan akun (otomatis keluar), jadikan / cabut admin |
| **Supplier** | Mode & saldo, sinkron produk sekarang + laporan brand yang belum dipetakan, log API terbaru |

Semua aksi admin tercatat di tabel `api_logs` (channel `admin`) beserta email admin dan IP-nya.

**Laba kotor** = harga jual − harga modal supplier (belum dikurangi biaya payment gateway).

---

## Toko Produk Digital (game, plugin, software, website service, e-book)

Halaman **Produk** punya dua bagian: *Top Up & Tagihan* (PPOB) dan *Produk Digital* (`toko.html`). Produk digital dikelola di
**Admin → Produk Digital**: tambah/ubah, harga coret, gambar, kategori, nama toko, deskripsi, aktif/pilihan.

| Cara kirim | Setelah pembeli membayar |
|---|---|
| **Unduh file** | Tombol unduh langsung muncul. File privat (maks. 20× unduh per pesanan, hanya pemilik yang login) atau link eksternal https untuk file besar |
| **Kode lisensi** | Satu kode diambil otomatis dari stok (Admin → Lisensi). Stok habis = produk tidak bisa dibeli |
| **Manual** | Pesanan masuk antrean **Digital: menunggu dikirim**. Buka pesanan → **Kirim Produk** (link/akun/kode). Pembeli langsung melihatnya. **Batalkan & Refund** mengembalikan dana ke Saldo GDK |

- Kolom **Pertanyaan ke pembeli** (mis. "ID akun game", "Domain website") tampil saat checkout dan bisa diwajibkan.
- 20 produk contoh dari katalog lama dimasukkan dengan cara kirim **Manual**. Ubah ke Unduh/Lisensi setelah file/kode tersedia.
- Isi pengiriman & kode lisensi disimpan **terenkripsi** (butuh `app.key`).
- **Batas upload file** mengikuti PHP hosting (hPanel → PHP Configuration → `upload_max_filesize` & `post_max_size`). File besar: pakai link eksternal.
- Disarankan menyimpan file produk di luar `public_html`: isi `digital.storage` di config (mis. `/home/u123456789/gdk-files`).

**Upgrade database yang sudah berjalan:** jalankan `database/migrations/004_digital.sql`, lalu import `database/seed_digital.sql`.

---

## Program Agen (mitra penjual)

Pengguna bisa menjadi **agen** untuk menjual kembali produk PPOB GDK Commerce dengan harga khusus.

| Langkah | Siapa | Di mana |
|---|---|---|
| 1. Ajukan diri (nama toko, kota, keterangan) | Pengguna | **Akun Saya → Jadi Agen GDK** |
| 2. Setujui / tolak (wajib alasan) | Admin | **Admin → Agen** (badge merah = pengajuan menunggu) |
| 3. Deposit saldo (min. Rp 10.000, kelipatan Rp 1.000) | Agen | **Akun Saya → Deposit Saldo** → halaman pembayaran |
| 4. Transaksi pakai Saldo GDK dengan harga agen | Agen | Halaman **Produk** (banner "Harga Agen aktif") |

**Harga agen**
- Otomatis: `modal + 50% dari laba harga umum`, dibulatkan ke atas kelipatan Rp 50 (atur di config `agent.margin_share` & `agent.round`).
  Contoh: modal 10.000, harga umum 11.000 → harga agen 10.500.
- Manual per produk: **Admin → Produk & Harga → kolom Harga agen** (kosongkan = otomatis). Ditolak bila di bawah modal atau di atas harga umum.
- Massal: **Markup Massal → Harga yang diubah: Harga agen**.
- Pascabayar: **Pengaturan Brand → Admin agen** (kosongkan = sama dengan biaya admin umum).
- Sinkron Digiflazz mengembalikan harga agen ke otomatis, kecuali produk yang **dikunci** 🔒.
- Harga agen tidak pernah di bawah modal dan tidak pernah lebih mahal dari harga umum.

**Deposit** memakai payment gateway yang sama dengan pesanan (saat ini **dummy** → tombol *Simulasikan Pembayaran*).
Deposit tidak dihitung sebagai omzet; transaksi agen ditandai **Agen** dan bisa difilter di **Admin → Pesanan → Transaksi agen / Deposit agen**.
Mencabut agen (**Admin → Agen → Cabut Agen**) mengembalikan pengguna ke harga umum; saldonya tetap.

> **Sebelum live dengan saldo asli:** hubungkan payment gateway asli. Selama gateway dummy, deposit hanya saldo uji coba —
> hapus dengan `DELETE FROM wallet_ledger; UPDATE users SET balance = 0;` sebelum go-live.

**Upgrade database yang sudah berjalan:** jalankan `database/migrations/005_agent.sql` sekali di phpMyAdmin.

---

## Saldo GDK, Refund Otomatis & Kirim Ulang

### Saldo GDK (dompet akun)
- Pesanan yang **gagal di supplier setelah dibayar** → total pembayaran **otomatis masuk ke Saldo GDK** pelanggan (bisa dimatikan: `wallet.auto_refund = false`).
- Pelanggan bisa membayar dengan **Saldo GDK** (instan, tanpa biaya). Saldo & mutasi tampil di halaman **Akun Saya**.
- Setiap perubahan saldo tercatat di tabel `wallet_ledger` dan tidak bisa terjadi dua kali untuk transaksi yang sama.
- Pelanggan ingin mencairkan saldo ke rekening? Transfer manual, lalu di **Admin → Pengguna → klik saldo → Penyesuaian** isi nominal negatif + keterangan.
- **Total Saldo GDK pelanggan adalah kewajiban Anda** (uang pelanggan yang Anda pegang) — tampil di tab Ringkasan.

> **Sebelum live:** hapus saldo hasil uji coba (dari pembayaran dummy):
> `DELETE FROM wallet_ledger; UPDATE users SET balance = 0;`

### Kirim ulang pesanan gagal (Admin → detail pesanan → **Kirim Ulang ke Supplier**)
- Dikirim dengan nomor transaksi supplier baru: `INVOICE` + `R1`, `R2`, … (maks `wallet.max_retry`, default 3). Invoice pelanggan tidak berubah.
- Bila dana sudah direfund ke saldo, refund **ditarik kembali** dulu. Jika saldo pelanggan sudah terpakai, kirim ulang ditolak (pelanggan tidak bisa mendapat produk + uang sekaligus).
- Bila gagal lagi, dana otomatis kembali ke saldo. Webhook dari nomor transaksi lama diabaikan.
- Pascabayar: sistem melakukan cek tagihan ulang; bila jumlah tagihan naik > Rp 5.000, kirim ulang dibatalkan.

---

## Verifikasi Dua Langkah (2FA)

- **Wajib untuk admin** (`security.admin_2fa = true`): saat membuka panel admin pertama kali, admin diminta memindai QR dengan
  Google Authenticator / Microsoft Authenticator / Authy, lalu menyimpan **8 kode cadangan**.
- Pelanggan bisa mengaktifkannya (opsional) di **Akun Saya → Keamanan Akun**.
- **Wajib isi `app.key`** di config sebelum ada yang mengaktifkan 2FA:
  `php -r "echo bin2hex(random_bytes(32));"` — dan jangan diganti setelahnya.
- HP hilang: pakai kode cadangan, atau admin lain → **Pengguna → Reset 2FA**. Untuk admin terakhir:
  `php api/cron/reset-2fa.php email@anda.com` (SSH) atau SQL di file tersebut.

### Upgrade database yang sudah berjalan
Jalankan berurutan di phpMyAdmin (sekali saja): `database/migrations/002_admin.sql` (bila belum), lalu `003_wallet_retry_2fa.sql`.

---

## Operasional
- **Harga, produk, brand, refund, pengguna:** kelola lewat Panel Admin.
- **Sinkron tidak pernah mengubah status aktif** yang Anda atur; produk yang hilang dari price list hanya ditandai "Gangguan".
- **Log API** (supplier, pembayaran, webhook): tabel `api_logs`.
