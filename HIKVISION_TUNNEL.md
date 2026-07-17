# Panduan Koneksi Hikvision melalui Tunnel

Dokumen ini menjelaskan cara membuat perangkat Hikvision di jaringan gym dapat diakses oleh aplikasi Laravel yang berjalan di hosting. Tunnel dijalankan pada komputer/jaringan yang **satu LAN dengan perangkat**, bukan pada server hosting.

> Jangan pernah menyimpan password perangkat di Git, dokumentasi, atau chat publik. Simpan hanya di `.env` pada aplikasi.

## Alur koneksi

```text
Laravel hosting -> URL tunnel HTTPS -> komputer gym (ngrok/cloudflared) -> Hikvision LAN
```

IP seperti `192.168.11.196` hanya dapat diakses dari LAN gym. Hosting tidak dapat mengaksesnya langsung tanpa tunnel, VPN, atau port-forward.

## Persiapan

1. Pastikan komputer Windows yang menjalankan tunnel bisa membuka perangkat.

   ```powershell
   curl.exe --digest -u "admin:<PASSWORD>" http://192.168.11.196/ISAPI/System/deviceInfo
   ```

   Bila perangkat hanya merespons HTTPS, gunakan:

   ```powershell
   curl.exe -k --digest -u "admin:<PASSWORD>" https://192.168.11.196/ISAPI/System/deviceInfo
   ```

2. Pastikan Laravel memakai endpoint berikut di `.env`:

   ```env
   HIKVISION_BASE_URL=https://alamat-tunnel-anda
   HIKVISION_USERNAME=admin
   HIKVISION_PASSWORD=<PASSWORD_PERANGKAT>
   HIKVISION_TIMEOUT=10
   HIKVISION_CONNECT_TIMEOUT=5
   HIKVISION_USER_ENDPOINT="/ISAPI/AccessControl/UserInfo/Record?format=json"
   HIKVISION_USER_SEARCH_ENDPOINT="/ISAPI/AccessControl/UserInfo/Search?format=json"
   QUEUE_CONNECTION=database
   ```

3. Setelah mengubah `.env` pada hosting, bersihkan cache konfigurasi:

   ```bash
   php artisan config:clear
   ```

4. Uji URL publik dari komputer lain atau dari hosting:

   ```powershell
   curl.exe --digest -u "admin:<PASSWORD>" https://alamat-tunnel-anda/ISAPI/System/deviceInfo
   ```

   Respons XML `DeviceInfo` berarti tunnel dan Digest Auth sudah benar. Jangan mengubah request insert dari `POST` menjadi `PUT`; aplikasi memakai `POST` untuk endpoint `UserInfo/Record`.

## Opsi 1 — ngrok

Gunakan ini untuk pengujian cepat. URL pada paket gratis dapat berubah saat ngrok dihentikan, sehingga `HIKVISION_BASE_URL` harus diperbarui jika URL berubah.

### Instal dan autentikasi

1. Instal ngrok untuk Windows dan login ke akun ngrok.
2. Masukkan authtoken sekali saja:

   ```powershell
   ngrok config add-authtoken <NGROK_AUTHTOKEN>
   ```

### Jalankan tunnel

Jika perangkat terbukti merespons HTTP port 80, gunakan:

```powershell
ngrok http http://192.168.11.196:80
```

Jika perangkat benar-benar hanya dapat diakses melalui HTTPS port 443, gunakan:

```powershell
ngrok http https://192.168.11.196:443
```

Salin nilai `Forwarding`, misalnya:

```text
https://contoh.ngrok-free.dev -> http://192.168.11.196:80
```

Kemudian pada hosting:

```env
HIKVISION_BASE_URL=https://contoh.ngrok-free.dev
```

Jangan menambahkan slash ganda sebelum `/ISAPI`; URL akhir harus berbentuk:

```text
https://contoh.ngrok-free.dev/ISAPI/System/deviceInfo
```

### Memeriksa ngrok

- Buka `http://127.0.0.1:4040` di komputer gym untuk melihat request dari aplikasi.
- `401 Unauthorized` pada request pertama normal untuk Digest Auth: perangkat memberi challenge lalu klien mengirim request berikutnya dengan Digest.
- Respons akhir `200` berarti berhasil. `400` berarti payload API ditolak perangkat, sedangkan `503`, `ERR_NGROK_3004`, atau `ERR_NGROK_3200` berarti tunnel/upstream belum siap.

## Opsi 2 — Cloudflare Tunnel

Cloudflare Tunnel cocok untuk penggunaan tetap karena tunnel membuat koneksi keluar dari komputer gym; tidak perlu port-forward atau IP publik. Untuk penggunaan cepat, gunakan Quick Tunnel. Untuk produksi, gunakan Named/Remotely Managed Tunnel dengan domain sendiri.

### Quick Tunnel (pengujian)

Instal `cloudflared`, lalu jalankan pada komputer gym:

```powershell
cloudflared tunnel --url http://192.168.11.196:80
```

Cloudflared akan menampilkan URL `https://<acak>.trycloudflare.com`. Isi URL tersebut ke `HIKVISION_BASE_URL`, bersihkan config cache di hosting, lalu lakukan uji `curl` di bagian Persiapan.

Quick Tunnel hanya untuk tes: URL dapat berubah dan proses berhenti saat jendela terminal ditutup.

Jika upstream perangkat menggunakan HTTPS dengan sertifikat internal, uji lebih dulu apakah perangkat mengembalikan HTTP yang lengkap melalui proxy. Bila perlu, buat Named Tunnel dan konfigurasikan origin TLS sesuai perangkat; jangan menonaktifkan validasi TLS secara global pada Laravel.

### Named Tunnel (disarankan untuk produksi)

1. Tambahkan domain Anda ke Cloudflare.
2. Di Cloudflare Zero Trust, buka **Networks → Tunnels → Create a tunnel** dan pilih connector Windows.
3. Pada komputer gym, jalankan Command Prompt atau PowerShell sebagai Administrator lalu instal connector dengan token yang diberikan dashboard:

   ```powershell
   cloudflared.exe service install <TUNNEL_TOKEN>
   ```

4. Di tunnel tersebut, tambah **Public Hostname**:

   - Hostname: misalnya `hikvision.domain-anda.com`
   - Service type: `HTTP`
   - URL: `http://192.168.11.196:80`

   Jika perangkat memang menggunakan HTTPS yang berfungsi baik di proxy, pilih `HTTPS` dan URL `https://192.168.11.196:443`.

5. Cloudflare membuat DNS route untuk hostname itu. Isi ke hosting:

   ```env
   HIKVISION_BASE_URL=https://hikvision.domain-anda.com
   ```

6. Pastikan service `cloudflared` berstatus berjalan. Di Windows:

   ```powershell
   Get-Service cloudflared
   ```

   Atau jalankan connector sementara dari terminal:

   ```powershell
   cloudflared tunnel run --token <TUNNEL_TOKEN>
   ```

## Menjalankan sinkronisasi massal

Sinkronisasi seluruh member menggunakan Laravel Queue agar tidak membuat request web menunggu sekitar 1.000 API call.

Pada local, jalankan worker di terminal terpisah:

```powershell
php artisan queue:work database --queue=hikvision --tries=3 --timeout=60 --sleep=1
```

Pada hosting, proses yang sama harus hidup terus-menerus melalui Supervisor, system service, atau fasilitas background worker dari provider hosting:

```bash
php artisan queue:work database --queue=hikvision --tries=3 --timeout=60 --sleep=1
```

Setelah worker hidup, buka halaman akun member, klik **Sinkronkan Semua Member**, pastikan tanggal default 1 Januari–31 Desember, lalu jadwalkan. Setiap job mengecek `employeeNo = users.id`; yang sudah berada di perangkat akan dilewati.

Untuk melihat kegagalan antrean:

```bash
php artisan queue:failed
```

Untuk mengulang job yang gagal pada queue Hikvision:

```bash
php artisan queue:retry --queue=hikvision
```

## Keamanan

- Biarkan komputer gym dan `cloudflared`/ngrok menyala selama sinkronisasi berjalan.
- Gunakan password admin perangkat yang kuat dan ganti password yang pernah dibagikan.
- Jangan mempublikasikan URL tunnel di tempat umum.
- Untuk penggunaan produksi, batasi akses ke hostname tunnel hanya dari IP hosting melalui Cloudflare WAF/Access yang sesuai. Pastikan aturan tersebut tidak memblokir request Laravel.
- Bila tunnel dihentikan, hosting akan mendapat timeout atau respons gateway; hidupkan kembali tunnel sebelum mengulang job gagal.

## Referensi resmi

- [ngrok HTTP endpoints](https://ngrok.com/docs/http/)
- [Cloudflare Tunnel overview](https://developers.cloudflare.com/tunnel/)
- [Cloudflare Tunnel setup](https://developers.cloudflare.com/tunnel/setup/)
- [Cloudflare published applications](https://developers.cloudflare.com/cloudflare-one/networks/connectors/cloudflare-tunnel/routing-to-tunnel/)
