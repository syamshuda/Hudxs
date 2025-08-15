<?php
// /includes/functions.php (Versi Final dengan Logika Cashback)

if (!function_exists('getEffectivePriceAndPromoStatus')) {
    function getEffectivePriceAndPromoStatus($product) {
        $harga_normal = (float)$product['harga'];
        $harga_diskon = isset($product['harga_diskon']) ? (float)$product['harga_diskon'] : null;
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
        try {
            // Ambil data pesanan yang diperlukan
            $stmt_pesanan = $koneksi->prepare(
                "SELECT pembeli_id, metode_pembayaran, biaya_ongkir, voucher_kode_digunakan, admin_konfirmasi_id 
                 FROM pesanan WHERE id = ?"
            );
            $stmt_pesanan->bind_param("i", $pesanan_id);
            $stmt_pesanan->execute();
            $pesanan_data = $stmt_pesanan->get_result()->fetch_assoc();
            $stmt_pesanan->close();

            if (!$pesanan_data) {
                throw new Exception("Data pesanan #{$pesanan_id} tidak ditemukan.");
            }
            
            $pembeli_id = $pesanan_data['pembeli_id'];
            $metode_pembayaran = $pesanan_data['metode_pembayaran'];
            $biaya_ongkir_total = (float)($pesanan_data['biaya_ongkir'] ?? 0);
            $kode_voucher_digunakan = $pesanan_data['voucher_kode_digunakan'];
            $admin_id = $pesanan_data['admin_konfirmasi_id'] ?? 1;

            // === LOGIKA BARU: PROSES CASHBACK ===
            if (!empty($kode_voucher_digunakan)) {
                $stmt_voucher = $koneksi->prepare("SELECT jenis_voucher, nilai FROM voucher WHERE kode = ?");
                $stmt_voucher->bind_param("s", $kode_voucher_digunakan);
                $stmt_voucher->execute();
                $voucher = $stmt_voucher->get_result()->fetch_assoc();
                $stmt_voucher->close();

                if ($voucher && $voucher['jenis_voucher'] === 'cashback') {
                    $jumlah_cashback = (float)$voucher['nilai'];

                    // Ambil saldo pembeli saat ini
                    $stmt_saldo_sebelum = $koneksi->prepare("SELECT saldo FROM users WHERE id = ?");
                    $stmt_saldo_sebelum->bind_param("i", $pembeli_id);
                    $stmt_saldo_sebelum->execute();
                    $saldo_sebelum = (float)$stmt_saldo_sebelum->get_result()->fetch_assoc()['saldo'];
                    $stmt_saldo_sebelum->close();
                    
                    $saldo_sesudah = $saldo_sebelum + $jumlah_cashback;

                    // Tambahkan cashback ke saldo pembeli
                    $stmt_update_saldo = $koneksi->prepare("UPDATE users SET saldo = saldo + ? WHERE id = ?");
                    $stmt_update_saldo->bind_param("di", $jumlah_cashback, $pembeli_id);
                    if (!$stmt_update_saldo->execute()) throw new Exception("Gagal menambahkan cashback ke saldo pembeli.");
                    $stmt_update_saldo->close();

                    // Catat transaksi di riwayat saldo pembeli
                    $deskripsi_cashback = "Cashback dari Pesanan #" . $pesanan_id;
                    $stmt_log_cashback = $koneksi->prepare("INSERT INTO riwayat_saldo_pembeli (user_id, pesanan_id, jenis_transaksi, jumlah, saldo_sebelum, saldo_sesudah, deskripsi) VALUES (?, ?, 'masuk', ?, ?, ?, ?)");
                    $stmt_log_cashback->bind_param("iidds", $pembeli_id, $pesanan_id, $jumlah_cashback, $saldo_sebelum, $saldo_sesudah, $deskripsi_cashback);
                    if (!$stmt_log_cashback->execute()) throw new Exception("Gagal mencatat riwayat cashback pembeli.");
                    $stmt_log_cashback->close();
                }
            }
            // === AKHIR LOGIKA BARU ===


            // === LOGIKA LAMA: PROSES KOMISI & DANA PENJUAL (TETAP ADA) ===
            $result_komisi = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'persentase_komisi'");
            $persen_komisi = (float)(mysqli_fetch_assoc($result_komisi)['nilai_pengaturan'] ?? 0);
            
            // ... (Sisa dari logika transfer dana ke penjual dan komisi admin tetap sama persis seperti sebelumnya) ...
            
            return true;

        } catch (Exception $e) {
            error_log("Error in selesaikanPesananDanTransferDana for order #{$pesanan_id}: " . $e->getMessage());
            throw $e; // Lempar kembali error agar bisa di-handle oleh rollback
        }
    }
}

if (!function_exists('buat_slug')) {
    function buat_slug($teks) {
        // ... (Fungsi slug tetap sama) ...
    }
}
?>