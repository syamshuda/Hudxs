<?php
// /includes/functions.php

if (!function_exists('getEffectivePriceAndPromoStatus')) {
    function getEffectivePriceAndPromoStatus($product) {
        $harga_normal = $product['harga'];
        $harga_diskon = $product['harga_diskon'];
        $promo_mulai = $product['promo_mulai'];
        $promo_akhir = $product['promo_akhir'];
        $now = new DateTime();
        $effective_price = $harga_normal;
        $is_promo_active = false;
        $promo_text = '';
        $promo_badge_class = '';

        if ($harga_diskon !== null && $harga_diskon < $harga_normal) {
            if ($promo_mulai && $promo_akhir) {
                $start_date = new DateTime($promo_mulai);
                $end_date = new DateTime($promo_akhir);
                if ($now >= $start_date && $now <= $end_date) {
                    $effective_price = $harga_diskon;
                    $is_promo_active = true;
                    $persen_diskon = round((($harga_normal - $harga_diskon) / $harga_normal) * 100);
                    $promo_text = 'Diskon ' . $persen_diskon . '%';
                    $promo_badge_class = 'bg-warning text-dark';
                }
            } else {
                $effective_price = $harga_diskon;
                $is_promo_active = true;
                $persen_diskon = round((($harga_normal - $harga_diskon) / $harga_normal) * 100);
                $promo_text = 'Diskon ' . $persen_diskon . '%';
                $promo_badge_class = 'bg-primary';
            }
        }
        return [
            'price' => $effective_price,
            'is_promo' => $is_promo_active,
            'promo_text' => $promo_text,
            'promo_badge_class' => $promo_badge_class,
            'harga_normal' => $harga_normal
        ];
    }
}


if (!function_exists('selesaikanPesananDanTransferDana')) {
    function selesaikanPesananDanTransferDana($pesanan_id, $koneksi) {
        // Transaksi sekarang dikelola di file pemanggil (cth: pesanan_diterima.php)
        // untuk memastikan rollback berjalan dengan benar jika terjadi error di luar fungsi ini.
        
        try {
            // 1. Ambil semua data finansial dari pesanan
            $stmt_pesanan = $koneksi->prepare(
                "SELECT u.nama_lengkap AS nama_pembeli, p.metode_pembayaran, p.biaya_ongkir, p.nilai_diskon_voucher, p.admin_konfirmasi_id 
                 FROM pesanan p 
                 JOIN users u ON p.pembeli_id = u.id 
                 WHERE p.id = ?"
            );
            $stmt_pesanan->bind_param("i", $pesanan_id);
            $stmt_pesanan->execute();
            $pesanan_data = $stmt_pesanan->get_result()->fetch_assoc();
            $stmt_pesanan->close();

            if (!$pesanan_data) {
                throw new Exception("Data pesanan #{$pesanan_id} tidak ditemukan.");
            }
            
            $metode_pembayaran = $pesanan_data['metode_pembayaran'] ?? 'Transfer Bank';
            $biaya_ongkir_total = (float)($pesanan_data['biaya_ongkir'] ?? 0);
            $diskon_voucher_total = (float)($pesanan_data['nilai_diskon_voucher'] ?? 0);
            $admin_id = $pesanan_data['admin_konfirmasi_id'] ?? 1;

            // 2. Ambil persentase komisi
            $result_komisi = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'persentase_komisi'");
            $persen_komisi = (float)(mysqli_fetch_assoc($result_komisi)['nilai_pengaturan'] ?? 0);

            // 3. Hitung pendapatan produk per toko
            $query_items = "SELECT 
                                t.id as toko_id, 
                                SUM(dp.jumlah * dp.harga_satuan) as total_produk_toko 
                            FROM detail_pesanan dp 
                            JOIN produk pr ON dp.produk_id = pr.id 
                            JOIN toko t ON pr.toko_id = t.id 
                            WHERE dp.pesanan_id = ? 
                            GROUP BY t.id";
            $stmt_items = $koneksi->prepare($query_items);
            $stmt_items->bind_param("i", $pesanan_id);
            $stmt_items->execute();
            $result_items = $stmt_items->get_result();
            
            $total_komisi_admin_dari_pesanan = 0;
            
            // 4. Proses setiap toko dalam pesanan
            while($toko = $result_items->fetch_assoc()) {
                $toko_id = $toko['toko_id'];
                $total_produk_toko = (float)$toko['total_produk_toko'];

                $pendapatan_produk_setelah_voucher = $total_produk_toko - $diskon_voucher_total;
                $komisi_admin = $pendapatan_produk_setelah_voucher * ($persen_komisi / 100);
                $total_komisi_admin_dari_pesanan += $komisi_admin;
                
                $perubahan_saldo = 0;
                $deskripsi_log = "";
                $jenis_transaksi_log = "";

                if ($metode_pembayaran === 'COD') {
                    $perubahan_saldo = -1 * abs($komisi_admin);
                    $deskripsi_log = "Pemotongan komisi {$persen_komisi}% untuk Pesanan COD #{$pesanan_id}";
                    $jenis_transaksi_log = 'keluar';
                } else { 
                    $pendapatan_final_penjual = $pendapatan_produk_setelah_voucher - $komisi_admin + $biaya_ongkir_total;
                    $perubahan_saldo = max(0, $pendapatan_final_penjual);
                    
                    $deskripsi_log = sprintf(
                        "Pendapatan dari Pesanan #%d (Produk: Rp %s, Ongkir: Rp %s, Diskon: -Rp %s, Komisi: -Rp %s)",
                        $pesanan_id,
                        number_format($total_produk_toko, 0, ',', '.'),
                        number_format($biaya_ongkir_total, 0, ',', '.'),
                        number_format($diskon_voucher_total, 0, ',', '.'),
                        number_format($komisi_admin, 2, ',', '.')
                    );
                    $jenis_transaksi_log = 'masuk';
                }

                // Update saldo toko
                $stmt_update_saldo = $koneksi->prepare("UPDATE toko SET saldo = saldo + ? WHERE id = ?");
                $stmt_update_saldo->bind_param("di", $perubahan_saldo, $toko_id);
                if (!$stmt_update_saldo->execute()) throw new Exception("Gagal update saldo toko #{$toko_id}.");
                $stmt_update_saldo->close();

                // Catat di riwayat keuangan penjual
                $jumlah_log = abs($perubahan_saldo);
                $stmt_log_penjual = $koneksi->prepare("INSERT INTO riwayat_transaksi_penjual (toko_id, pesanan_id, jenis_transaksi, jumlah, deskripsi) VALUES (?, ?, ?, ?, ?)");
                $stmt_log_penjual->bind_param("iisds", $toko_id, $pesanan_id, $jenis_transaksi_log, $jumlah_log, $deskripsi_log); 
                if (!$stmt_log_penjual->execute()) throw new Exception("Gagal mencatat riwayat transaksi penjual.");
                $stmt_log_penjual->close();
            }
            $stmt_items->close();

            // 5. Catat pendapatan komisi dan UPDATE SALDO ADMIN
            if ($total_komisi_admin_dari_pesanan > 0) {
                $deskripsi_komisi_admin = "Komisi {$persen_komisi}% dari Pesanan #" . $pesanan_id;
                $stmt_log_admin = $koneksi->prepare("INSERT INTO riwayat_transaksi_admin (admin_user_id, jenis_transaksi, referensi_id, jumlah, deskripsi) VALUES (?, 'komisi_masuk', ?, ?, ?)");
                $stmt_log_admin->bind_param("iids", $admin_id, $pesanan_id, $total_komisi_admin_dari_pesanan, $deskripsi_komisi_admin);
                if (!$stmt_log_admin->execute()) throw new Exception("Gagal mencatat komisi admin.");
                $stmt_log_admin->close();
                
                $stmt_update_admin_saldo = $koneksi->prepare("UPDATE pengaturan SET nilai_pengaturan = nilai_pengaturan + ? WHERE nama_pengaturan = 'saldo_admin'");
                $stmt_update_admin_saldo->bind_param("d", $total_komisi_admin_dari_pesanan);
                if (!$stmt_update_admin_saldo->execute()) throw new Exception("Gagal update saldo admin.");
                $stmt_update_admin_saldo->close();
            }
            
            return true;

        } catch (Exception $e) {
            // ================== PERBAIKAN SINTAKS DI SINI (LINE 173) ==================
            // Menggunakan sintaks yang benar: {$pesanan_id}
            error_log("Error in selesaikanPesananDanTransferDana for order #{$pesanan_id}: " . $e->getMessage());
            // =========================================================================
            
            // Lemparkan kembali exception agar bisa ditangani oleh skrip yang memanggilnya
            throw $e;
        }
    }
}
?>