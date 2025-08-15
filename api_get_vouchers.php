<?php
// /api_get_vouchers.php
require_once 'config/database.php';
header('Content-Type: application/json');

$response = ['vouchers' => []];
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode($response);
    exit();
}

$user_id = $_SESSION['user_id'];
$subtotal_produk = isset($_GET['subtotal']) ? (float)$_GET['subtotal'] : 0;
$biaya_ongkir = isset($_GET['ongkir']) ? (float)$_GET['ongkir'] : 0;
$toko_ids_in_cart = [];

$items_in_cart = $_SESSION['keranjang'] ?? ($_SESSION['buy_now_item'] ? [$_SESSION['buy_now_item']['produk_id'] => $_SESSION['buy_now_item']['jumlah']] : []);
if (!empty($items_in_cart)) {
    $ids_string = implode(',', array_map('intval', array_keys($items_in_cart)));
    $result = mysqli_query($koneksi, "SELECT DISTINCT toko_id FROM produk WHERE id IN ($ids_string)");
    while ($row = mysqli_fetch_assoc($result)) {
        $toko_ids_in_cart[] = $row['toko_id'];
    }
}
if(empty($toko_ids_in_cart)) {
    echo json_encode($response);
    exit();
}

$toko_ids_placeholder = implode(',', array_fill(0, count($toko_ids_in_cart), '?'));

$stmt = $koneksi->prepare("
    SELECT v.*
    FROM klaim_voucher kv
    JOIN voucher v ON kv.voucher_id = v.id
    WHERE kv.user_id = ? 
      AND v.toko_id IN ($toko_ids_placeholder)
      AND v.is_active = 1 
      AND v.tanggal_akhir > NOW()
      AND (v.jumlah_penggunaan_total IS NULL OR v.jumlah_digunakan_saat_ini < v.jumlah_penggunaan_total)
");

$types = 'i' . str_repeat('i', count($toko_ids_in_cart));
$params = array_merge([$user_id], $toko_ids_in_cart);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$vouchers = [];
while ($voucher = $result->fetch_assoc()) {
    $voucher['bisa_dipakai'] = true;
    $voucher['alasan_tidak_bisa'] = '';
    
    if ($voucher['min_pembelian'] > 0 && $subtotal_produk < $voucher['min_pembelian']) {
        $voucher['bisa_dipakai'] = false;
        $butuh_belanja_lagi = number_format($voucher['min_pembelian'] - $subtotal_produk);
        $voucher['alasan_tidak_bisa'] = "Butuh min. belanja Rp{$butuh_belanja_lagi} lagi.";
    }

    if ($voucher['jenis_voucher'] == 'gratis_ongkir' && $biaya_ongkir <= 0) {
        $voucher['bisa_dipakai'] = false;
        $voucher['alasan_tidak_bisa'] = "Hanya berlaku untuk pengiriman.";
    }
    
    $vouchers[] = $voucher;
}
$response['vouchers'] = $vouchers;
echo json_encode($response);
$koneksi->close();
?>