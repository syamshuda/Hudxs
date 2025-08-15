<?php
// /api_alamat.php (Versi Diperbarui untuk Alamat Asal & Tujuan)
require_once 'config/database.php';
header('Content-Type: application/json');

$get = $_GET['get'] ?? '';
$provinsi = $_GET['provinsi'] ?? '';
$kota = $_GET['kota'] ?? '';
$kecamatan_asal = $_GET['kecamatan_asal'] ?? ''; // Parameter baru

$response = [];

try {
    // === UNTUK PEMILIHAN ALAMAT PENJUAL (ASAL) ===
    if ($get === 'provinsi_asal') {
        $query = "SELECT DISTINCT provinsi_asal FROM ongkos_kirim ORDER BY provinsi_asal ASC";
        $result = $koneksi->query($query);
        while ($row = $result->fetch_assoc()) { $response[] = $row['provinsi_asal']; }
    } 
    elseif ($get === 'kota_asal' && !empty($provinsi)) {
        $stmt = $koneksi->prepare("SELECT DISTINCT kota_asal FROM ongkos_kirim WHERE provinsi_asal = ? ORDER BY kota_asal ASC");
        $stmt->bind_param("s", $provinsi);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) { $response[] = $row['kota_asal']; }
        $stmt->close();
    } 
    elseif ($get === 'kecamatan_asal' && !empty($kota)) {
        $stmt = $koneksi->prepare("SELECT DISTINCT kecamatan_asal FROM ongkos_kirim WHERE kota_asal = ? ORDER BY kecamatan_asal ASC");
        $stmt->bind_param("s", $kota);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) { $response[] = $row['kecamatan_asal']; }
        $stmt->close();
    }

    // === UNTUK PEMILIHAN ALAMAT PEMBELI (TUJUAN) - Logika lama tetap ada ===
    elseif ($get === 'provinsi_tujuan' && !empty($kecamatan_asal)) {
        $stmt = $koneksi->prepare("SELECT DISTINCT provinsi_tujuan FROM ongkos_kirim WHERE kecamatan_asal = ? ORDER BY provinsi_tujuan ASC");
        $stmt->bind_param("s", $kecamatan_asal);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) { $response[] = $row['provinsi_tujuan']; }
        $stmt->close();
    }
    elseif ($get === 'kota_tujuan' && !empty($kecamatan_asal) && !empty($provinsi)) {
        $stmt = $koneksi->prepare("SELECT DISTINCT kota_tujuan FROM ongkos_kirim WHERE kecamatan_asal = ? AND provinsi_tujuan = ? ORDER BY kota_tujuan ASC");
        $stmt->bind_param("ss", $kecamatan_asal, $provinsi);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) { $response[] = $row['kota_tujuan']; }
        $stmt->close();
    } 
    elseif ($get === 'kecamatan_tujuan' && !empty($kecamatan_asal) && !empty($kota)) {
        $stmt = $koneksi->prepare("SELECT DISTINCT kecamatan_tujuan FROM ongkos_kirim WHERE kecamatan_asal = ? AND kota_tujuan = ? ORDER BY kecamatan_tujuan ASC");
        $stmt->bind_param("ss", $kecamatan_asal, $kota);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) { $response[] = $row['kecamatan_tujuan']; }
        $stmt->close();
    }

} catch (Exception $e) {
    http_response_code(500);
    $response = ['error' => 'Terjadi kesalahan pada server.'];
}

echo json_encode($response);
$koneksi->close();
?>