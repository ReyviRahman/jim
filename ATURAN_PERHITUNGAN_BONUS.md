# Aturan Perhitungan Bonus

Dokumen ini menjelaskan perilaku kode halaman **Perhitungan Bonus** pada `/dashboard/admin/rekap-bonus/{user}/detail`, berdasarkan implementasi yang diperiksa pada 26 September 2026.

## 1. Pengertian

- **Penerima bonus:** karyawan yang detailnya sedang dibuka, bukan pengguna yang sedang login.
- **Follow Up 1 / Follow Up 2:** karyawan pada kolom `follow_up_id` dan `follow_up_id_two` di membership.
- **Nominal dasar:** `total_paid` membership; jika kosong, dianggap 0.
- **Nominal akhir:** nominal dasar setelah aturan penuh atau pembagian dua diterapkan.
- **Sales Admin:** dalam dokumen ini merujuk pada role `kasir_gym`, bukan role `admin`.

## 2. Transaksi yang masuk perhitungan

Membership harus memenuhi seluruh syarat berikut:

1. Status pembayaran `paid` (lunas).
2. Tipe bukan `visit`.
3. Bukan paket operasional: `operational_request_id` kosong.
4. Memiliki transaksi dengan `payment_date` di antara tanggal awal dan akhir filter, termasuk kedua batasnya.
5. Tidak memiliki transaksi dengan `payment_date` setelah tanggal akhir filter.
6. Memenuhi hubungan follow-up sesuai role penerima bonus.

Dasar perhitungan menggunakan **seluruh `total_paid` membership**, bukan hanya penjumlahan pembayaran dalam periode filter. Membership tidak harus berstatus aktif agar masuk perhitungan ini.

Pencarian nama member ikut membatasi data dan total bonus yang ditampilkan.

## 3. Perbedaan per role

| Role penerima | Syarat hubungan dengan membership | Master persentase |
| --- | --- | --- |
| Sales (`sales`) | Menjadi Follow Up 1 atau Follow Up 2 | `SalesKonsultan` |
| Sales Admin / Kasir Gym (`kasir_gym`) | Menjadi Follow Up 1 atau Follow Up 2 | `KasirKonsultan` |
| PT (`pt`) | Hanya membership bertipe `pt`; harus menjadi Follow Up 1 sekaligus Follow Up 2 | `CoachKonsultan` |
| Role lainnya | Query umum menggunakan Follow Up 1 atau Follow Up 2, tetapi tidak ada master persentase yang dipilih | Bonus default 0 |

Daftar utama rekap hanya menampilkan akun aktif dengan role `pt`, `sales`, atau `kasir_gym`.

### Sales online

Flag `is_sales_online` tidak diperiksa dalam rumus bonus. Sales online dan sales non-online dengan role `sales` menggunakan aturan yang sama.

### Head Coach

Status Head Coach memberikan akses khusus. Pemilihan master persentase dan syarat transaksi tetap mengikuti nilai `role` akun penerima. Akun Head Coach dengan role `pt` mengikuti perhitungan PT.

## 4. Urutan aturan nominal penuh dan pembagian dua

Aturan dijalankan berurutan; ketika satu kondisi terpenuhi, hasil langsung dikembalikan.

| Urutan | Kondisi | Nominal akhir |
| --- | --- | --- |
| 1 | Paket operasional | 0 |
| 2 | Kedua follow-up terisi, menunjuk orang yang sama, dan role orang tersebut `pt` atau `kasir_gym` | Nominal dasar penuh |
| 3 | Harga tidak disarankan menurut pemeriksaan pada bagian 5 | Nominal dasar ÷ 2 |
| 4 | Kedua follow-up terisi dan menunjuk orang berbeda | Nominal dasar ÷ 2 |
| 5 | Selain kondisi di atas | Nominal dasar penuh |

Implikasinya:

- Kedua follow-up adalah sales yang sama: tetap dibagi dua jika harga tidak disarankan.
- Kedua follow-up adalah kasir gym atau PT yang sama: nominal penuh, termasuk jika harga tidak disarankan.
- Follow-up berbeda sekaligus harga tidak disarankan: dibagi dua **satu kali**, bukan dibagi empat.
- Hanya satu follow-up terisi: tidak dibagi dua karena perbedaan follow-up, tetapi masih dapat dibagi dua karena harga tidak disarankan.
- Fungsi nominal akhir menghasilkan nilai per membership; bukan rumus berbeda untuk masing-masing penerima.
- Untuk rekap PT, membership dengan follow-up berbeda **tidak masuk rekap**, walaupun fungsi nominal akhir umum memiliki aturan pembagian dua untuk kondisi tersebut.

## 5. Penentuan harga tidak disarankan

Fungsi perhitungan memeriksa urutan berikut:

1. Jika `normal_price > 0` dan `price_paid >= normal_price`, harga tidak dianggap tidak disarankan.
2. Jika kondisi pertama tidak terpenuhi, tetapi `net_price > 0` dan `price_paid >= net_price`, harga tidak dianggap tidak disarankan.
3. Jika kedua kondisi di atas tidak terpenuhi dan `price_paid > 0`, harga dianggap tidak disarankan.
4. Jika `price_paid <= 0`, kondisi harga tidak disarankan tidak diaktifkan oleh pemeriksaan ini.

Catatan implementasi:

- Nilai `unrecommended_price` tidak menjadi batas pembanding langsung dalam rumus pembagian dua.
- Jika harga normal dan net belum diisi atau bernilai 0, `price_paid` yang positif tetap masuk kondisi harga tidak disarankan, kecuali sudah memperoleh pengecualian pada urutan 2 bagian 4.
- Label harga pada tampilan bukan penentu rumus; perhitungan menggunakan pemeriksaan di atas.

## 6. Rentang persentase dan rumus bonus

Setelah nominal akhir semua membership yang lolos filter dijumlahkan, sistem mencari rentang pada master sesuai role.

| Bentuk rentang | Kondisi cocok |
| --- | --- |
| `rentang_satu = min` | Total nominal akhir ≤ `rentang_dua` |
| `rentang_dua = plus` | Total nominal akhir ≥ `rentang_satu` |
| Kedua batas berupa angka | Total nominal akhir ≥ batas awal dan ≤ batas akhir |

Batas rentang bersifat inklusif. Jika beberapa rentang cocok, implementasi mengambil kecocokan pertama dari hasil pengambilan data. Jika tidak ada rentang cocok, persentase dan bonus menjadi 0.

```text
Total nominal akhir = jumlah nominal akhir seluruh membership yang lolos filter
Bonus bruto         = round(total nominal akhir × persen / 100, 2)
Bonus bersih        = bonus bruto − nominal potongan
```

Persentase dikenakan pada **seluruh total nominal akhir**, bukan per lapisan rentang. Angka persentase berasal dari data master dan tidak ditetapkan sebagai angka tetap dalam dokumen ini.

## 7. Periode default saat halaman dibuka

Role yang diperiksa adalah role **penerima bonus**.

| Role | Periode default |
| --- | --- |
| `pt` | Tanggal 1 sampai hari terakhir bulan berjalan |
| Selain `pt` | Tanggal 16 sampai tanggal 15 berikutnya |

Untuk selain PT:

- Hari ini tanggal 16 atau setelahnya: 16 bulan berjalan sampai 15 bulan berikutnya.
- Hari ini sebelum tanggal 16: 16 bulan sebelumnya sampai 15 bulan berjalan.

Contoh:

| Hari ini | Awal periode | Akhir periode |
| --- | --- | --- |
| 16 September 2026 | 16 September 2026 | 15 Oktober 2026 |
| 15 September 2026 | 16 Agustus 2026 | 15 September 2026 |
| 1 Januari 2027 | 16 Desember 2026 | 15 Januari 2027 |

Pengguna dapat mengganti periode melalui kalender atau preset. Preset **Bulan Ini** tetap berarti bulan kalender, termasuk ketika dibuka untuk penerima non-PT.

## 8. Pembayaran dan potongan

- Admin dapat membuat pembayaran bonus, mengubah potongan pembayaran, dan menghapus pembayaran.
- Admin dan Head Coach dapat melihat riwayat/detail pembayaran serta mengunduh PDF.
- Kasir Gym dapat membuka rekap melalui route admin, tetapi tidak memperoleh hak memproses pembayaran bonus.
- Potongan tidak boleh negatif atau melebihi bonus bruto.
- Keterangan potongan wajib diisi jika potongan lebih dari 0.
- Pembayaran tidak dapat dibuat jika snapshot kosong, total nominal akhir tidak positif, atau bonus tidak positif.
- Pembayaran menyimpan snapshot data dan perhitungan saat pembayaran dibuat.
- Pencarian dalam modal hanya menyaring baris yang terlihat; tidak mengubah total snapshot pembayaran.
- Riwayat pembayaran tidak dibatasi oleh filter periode/pencarian rekap utama.

## 9. Contoh pembagian nominal

Asumsikan nominal dasar Rp1.000.000. Persentase **5% di bawah hanya ilustrasi**, bukan nilai master yang dipastikan berlaku.

| Kondisi | Nominal akhir | Bonus bruto jika 5% |
| --- | --- | --- |
| Kedua follow-up sales yang sama, harga normal/net | Rp1.000.000 | Rp50.000 |
| Kedua follow-up sales yang sama, harga tidak disarankan | Rp500.000 | Rp25.000 |
| Kedua follow-up kasir gym yang sama, harga tidak disarankan | Rp1.000.000 | Rp50.000 |
| Kedua follow-up PT yang sama, paket PT, harga tidak disarankan | Rp1.000.000 | Rp50.000 |
| Follow-up berbeda, untuk penerima non-PT yang lolos filter | Rp500.000 | Rp25.000 |
| Follow-up berbeda, untuk rekap PT | Tidak masuk rekap | Tidak dihitung |

## 10. Referensi kode

- `app/Models/Membership.php`: `scopeForBonusRecipient()`, `calculateNominalAkhir()`, `getPriceLabel()`.
- `app/Models/SalesKonsultan.php`: pencocokan rentang sales.
- `app/Models/KasirKonsultan.php`: pencocokan rentang kasir gym.
- `app/Models/CoachKonsultan.php`: pencocokan rentang PT.
- `resources/views/pages/dashboard/admin/rekap-bonus/⚡index.blade.php`: daftar penerima bonus.
- `resources/views/pages/dashboard/admin/rekap-bonus/⚡detail.blade.php`: periode, filter, total, persentase, pembayaran, dan otorisasi tindakan.
- `routes/web.php`: akses route rekap bonus.
- `tests/Feature/RekapBonusDetailTableTest.php`: pengujian perhitungan dan periode default.
- `tests/Feature/BonusPaymentTest.php`: pengujian pembayaran, snapshot, potongan, dan akses.
