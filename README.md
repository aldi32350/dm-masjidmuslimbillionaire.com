# dm.masjidmuslimbillionaire.com — TaskFlow Pro

Aplikasi monitoring pekerjaan tim (PHP + MySQL).

## Setup
Isi `DB_PASS` di `config.php` dengan password database dari cPanel sebelum diupload ke hosting.
Tabel & kolom baru dibuat otomatis saat aplikasi pertama kali dibuka.

## Fitur v3.4

### Teks Siap Salin (EOD)
Menu **Teks Siap Salin** membuat laporan EOD otomatis dengan format:

```
EOD - Nama
Unit ...
Hari, tanggal
Target perpekan :
1. ...
Yang dikerjakan Hari ini :
1. ...   ← tugas berstatus Selesai pada tanggal itu (+ yang sedang diproses)
Problem
Solusi :
```

- Unit & Target perpekan bisa disimpan sebagai default per personil.
  Jika target kosong, diambil dari tugas bertipe *Mingguan* minggu itu.
- Kepala Divisi / Kepala Unit bisa membuat EOD untuk anggota timnya.

### Bot Telegram (menu khusus Kepala Divisi)
1. Buat bot di Telegram lewat **@BotFather** → `/newbot`, salin tokennya.
2. Menu **Bot Telegram** → tempel token → **Simpan** → **Pasang Webhook**.
3. Opsional: tambahkan bot ke grup tim, ketik `/id` di grup, isi Chat ID Grup.
4. Tiap personil: menu profil → **Hubungkan Telegram** → tekan Start di bot.
5. Pengingat otomatis: salin 2 perintah dari halaman Bot Telegram ke cPanel → **Cron Jobs**
   (contoh pagi `0 7 * * *`, sore `30 16 * * *`).

Perintah bot: `/tugas` (dengan tombol Mulai/Selesai), `/selesai ID`, `/mulai ID`, `/eod`,
`/terlambat`, `/tim` (Kepala), `/baru Nama | Judul | YYYY-MM-DD` (Kepala), `/id`.

Notifikasi otomatis: tugas baru → personil; tugas selesai → atasan dan/atau grup.

## File
| File | Isi |
|---|---|
| `index.php`, `app.js` | Tampilan & logika utama |
| `fitur.js` | Tampilan Teks Siap Salin & Bot Telegram |
| `api.php` | REST API |
| `lib.php` | Fungsi bersama: EOD, Telegram, status tugas |
| `telegram.php` | Webhook bot & jadwal pengingat |
