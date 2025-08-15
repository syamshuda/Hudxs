<?php
// /checkout.php (Versi Final dengan Sistem Ongkir Berbasis Zona & Fitur Lengkap)
$page_title = "Checkout";
require_once 'config/database.php';

// Pengecekan login dipindahkan ke atas sebelum output HTML untuk menghindari error header
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pembeli') {
    header("Location: " . BASE_URL . "/auth/login.php?redirect=checkout");
    exit();
}

require_once 'includes/header.php';
require_once 'includes/functions.php';

// Tentukan item yang akan diproses
$is_buy_now = isset($_SESSION['buy_now_item']) && !empty($_SESSION['buy_now_item']);
$items_to_process = $is_buy_now ? [$_SESSION['buy_now_item']['produk_id'] => $_SESSION['buy_now_item']['jumlah']] : ($_SESSION['keranjang'] ?? []);

if (empty($items_to_process)) {
    header("Location: " . BASE_URL . "/keranjang.php?status=kosong"); 
    exit();
}

$user_id = $_SESSION['user_id'];
$user_data = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT nama_lengkap, email, no_telepon, can_use_cod FROM users WHERE id = $user_id"));

// Inisialisasi variabel
$total_harga_produk = 0;
$total_berat = 0;
$ada_produk_fisik = false;
$ada_produk_digital = false;
$kecamatan_asal_toko = ''; // Variabel Kunci untuk menyimpan kecamatan asal
$produk_di_ringkasan = [];

$ids_to_fetch = array_keys($items_to_process);
if (!empty($ids_to_fetch)) {
    $ids_string = implode(',', array_map('intval', $ids_to_fetch));
    $query = "SELECT p.*, t.nama_toko, t.kecamatan as toko_kecamatan FROM produk p JOIN toko t ON p.toko_id = t.id WHERE p.id IN ($ids_string)";
    $result = mysqli_query($koneksi, $query);

    while ($row = mysqli_fetch_assoc($result)) {
        $jumlah = $items_to_process[$row['id']];
        $promo_data_item = getEffectivePriceAndPromoStatus($row);
        $total_harga_produk += $promo_data_item['price'] * $jumlah;
        
        // Simpan data untuk ditampilkan di ringkasan
        $produk_di_ringkasan[] = [
            'nama' => $row['nama_produk'],
            'gambar' => $row['gambar_produk'],
            'jumlah' => $jumlah,
            'promo_data' => $promo_data_item
        ];
        
        if ($row['jenis_produk'] == 'digital') $ada_produk_digital = true;
        if ($row['jenis_produk'] == 'fisik') {
            $ada_produk_fisik = true;
            $total_berat += ($row['berat'] * $jumlah);
            // Asumsi semua produk dalam satu checkout berasal dari toko yang sama
            if (empty($kecamatan_asal_toko)) {
                $kecamatan_asal_toko = $row['toko_kecamatan'];
            }
        }
    }
}
?>

<main class="main-container">
    <div class="d-flex align-items-center mb-4">
        <a href="<?php echo $is_buy_now ? 'javascript:history.back()' : BASE_URL . '/keranjang.php'; ?>" class="text-dark text-decoration-none fs-4 me-3"><i class="bi bi-arrow-left-circle"></i></a>
        <h1>Checkout</h1>
    </div>

    <?php if(isset($_GET['status']) && $_GET['status'] == 'gagal'): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['pesan'] ?? 'Terjadi kesalahan.'); ?></div>
    <?php endif; ?>

    <form action="<?php echo BASE_URL; ?>/proses_checkout.php" method="POST" id="formCheckout">
        <input type="hidden" name="is_buy_now" value="<?php echo $is_buy_now ? '1' : '0'; ?>">
        <div class="row">
            <div class="col-lg-7">
                <?php if ($ada_produk_digital): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header"><h4>Pengiriman Produk Digital</h4></div>
                    <div class="card-body">
                        <p class="text-muted">Produk digital akan dikirimkan ke email ini.</p>
                        <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email_pengiriman_digital" value="<?php echo htmlspecialchars($user_data['email']); ?>" required></div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($ada_produk_fisik): ?>
                <div id="physical-section">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header"><h4>Alamat Pengiriman</h4></div>
                        <div class="card-body">
                            <div id="shipping-warning" class="alert alert-warning" style="display: none;"></div>
                            <div class="mb-3"><label class="form-label">Nama Penerima</label><input type="text" class="form-control" name="nama_penerima" value="<?php echo htmlspecialchars($user_data['nama_lengkap']); ?>" required></div>
                            <div class="mb-3"><label class="form-label">Nomor Telepon</label><input type="tel" class="form-control" name="no_telepon" value="<?php echo htmlspecialchars($user_data['no_telepon'] ?? ''); ?>" required></div>
                            <div class="mb-3"><label class="form-label">Provinsi Tujuan</label><select class="form-select" id="provinsi_tujuan" name="provinsi_tujuan" required><option value="">-- Pilih Lokasi --</option></select></div>
                            <div class="mb-3"><label class="form-label">Kota/Kabupaten Tujuan</label><select class="form-select" id="kota_tujuan" name="kota_tujuan" required disabled><option value="">Pilih provinsi</option></select></div>
                            <div class="mb-3"><label class="form-label">Kecamatan Tujuan</label><select class="form-select" id="kecamatan_tujuan" name="kecamatan_tujuan" required disabled><option value="">Pilih kota/kab.</option></select></div>
                            <div class="mb-3"><label class="form-label">Detail Alamat</label><textarea class="form-control" name="alamat_lengkap" rows="3" placeholder="Nama jalan, gedung, no. rumah..." required></textarea></div>
                            <div class="mb-3"><label class="form-label">Kode Pos</label><input type="text" class="form-control" name="kode_pos" required></div>
                            <div class="mt-3"><label class="form-label">Pilih Kurir</label><select class="form-select" id="kurir" name="kurir" required><option value="">-- Pilih Kurir --</option><option value="jnt">J&T Express</option><option value="pos">POS Indonesia</option><option value="lokal">Kurir Lokal</option></select></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="card shadow-sm mb-4">
                    <div class="card-header"><h4>Metode Pembayaran</h4></div>
                    <div class="card-body">
                        <div class="form-check"><input class="form-check-input" type="radio" name="metode_pembayaran" id="metodeTransfer" value="Transfer Bank" checked><label class="form-check-label" for="metodeTransfer">Transfer Bank & QRIS</label></div>
                        <?php if ($user_data['can_use_cod'] && $ada_produk_fisik): ?>
                        <div class="form-check"><input class="form-check-input" type="radio" name="metode_pembayaran" id="metodeCOD" value="COD"><label class="form-check-label" for="metodeCOD">Bayar di Tempat (COD)</label></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header"><h4>Catatan untuk Penjual (Opsional)</h4></div>
                    <div class="card-body"><textarea name="catatan_pembeli" class="form-control" rows="3" placeholder="Contoh: Tolong bungkus dengan rapi untuk kado."></textarea></div>
                </div>
            </div>
            
            <div class="col-lg-5">
                 <div class="card shadow-sm mb-4">
                    <div class="card-header"><h4>Ringkasan Pesanan</h4></div>
                    <div class="card-body">
                        <?php foreach($produk_di_ringkasan as $item): ?>
                        <div class="d-flex mb-3">
                            <img src="/uploads/produk/<?php echo htmlspecialchars($item['gambar']); ?>" class="rounded me-3" width="60" height="60" style="object-fit: cover;">
                            <div class="flex-grow-1">
                                <small><?php echo htmlspecialchars($item['nama']); ?></small>
                                <div class="fw-bold">x <?php echo $item['jumlah']; ?></div>
                            </div>
                            <div class="text-end">
                                <span class="fw-bold">Rp <?php echo number_format($item['promo_data']['price'] * $item['jumlah']); ?></span>
                                <?php if ($item['promo_data']['is_promo']): ?>
                                    <br><small class="text-muted"><del>Rp <?php echo number_format($item['promo_data']['harga_normal'] * $item['jumlah']); ?></del></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <hr>
                        
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0"><span>Subtotal Produk</span><span id="subtotalProduk">Rp <?php echo number_format($total_harga_produk); ?></span></li>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0" id="ongkir-summary" style="<?php if (!$ada_produk_fisik) echo 'display:none !important;'; ?>">
                                <span>Biaya Ongkir <small id="kurir-terpilih" class="text-muted"></small></span>
                                <span id="biayaOngkir">Rp 0</span>
                            </li>
                            <li id="voucherDiscountDisplay" class="list-group-item d-flex justify-content-between align-items-center px-0" style="display: none;"><span>Diskon Voucher</span><span id="diskonVoucher" class="text-success">- Rp 0</span></li>
                        </ul>
                        <div id="voucherSection" class="mt-3">
                             <div class="input-group"><input type="text" class="form-control" id="kodeVoucher" placeholder="Masukkan Kode Voucher"><button class="btn btn-outline-secondary" type="button" id="applyVoucherBtn">Terapkan</button></div>
                            <div id="voucherStatus" class="form-text mt-1"></div>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between fw-bold fs-5"><span>Total Pembayaran</span><span id="totalPembayaran">Rp <?php echo number_format($total_harga_produk); ?></span></div>
                    </div>
                    <div class="card-footer">
                        <input type="hidden" name="total_harga" id="inputTotalHarga" value="<?php echo $total_harga_produk; ?>">
                        <input type="hidden" name="biaya_ongkir" id="inputOngkir" value="0">
                        <input type="hidden" name="kode_voucher_digunakan" id="inputKodeVoucher" value="">
                        <input type="hidden" name="nilai_diskon_voucher" id="inputNilaiDiskonVoucher" value="0">
                        <div class="d-grid"><button type="submit" class="btn btn-success btn-lg" id="btnBuatPesanan" disabled>Lengkapi Data</button></div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</main>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    const BASE_URL = '<?php echo BASE_URL; ?>';
    const adaProdukFisik = <?php echo $ada_produk_fisik ? 'true' : 'false'; ?>;
    const kecamatanAsal = "<?php echo htmlspecialchars($kecamatan_asal_toko); ?>";
    const totalBerat = <?php echo $total_berat; ?>;
    let biayaOngkirSaatIni = 0;
    let diskonVoucherSaatIni = 0;

    function updateTotalDisplay() {
        let subtotal = parseFloat($('#inputTotalHarga').val()) || 0;
        let total = subtotal + biayaOngkirSaatIni - diskonVoucherSaatIni;
        total = Math.max(0, total);
        $('#biayaOngkir').text('Rp ' + biayaOngkirSaatIni.toLocaleString('id-ID'));
        $('#totalPembayaran').text('Rp ' + total.toLocaleString('id-ID'));
        $('#inputOngkir').val(biayaOngkirSaatIni);
        updateCheckoutButtonState();
    }
    
    function updateCheckoutButtonState() {
        let canCheckout = true;
        let buttonText = 'Buat Pesanan';
        if (adaProdukFisik) {
            if (!$('#kurir').val() || !$('#provinsi_tujuan').val() || !$('#kota_tujuan').val() || !$('#kecamatan_tujuan').val() || biayaOngkirSaatIni < 0) {
                canCheckout = false;
                buttonText = 'Lengkapi Alamat & Kurir';
                if (biayaOngkirSaatIni < 0) {
                    buttonText = 'Rute Pengiriman Tidak Tersedia';
                }
            }
        }
        $('#btnBuatPesanan').prop('disabled', !canCheckout).text(buttonText);
    }

    async function updateOngkir() {
        biayaOngkirSaatIni = 0;
        $('#kurir-terpilih').text('');
        const kurir = $('#kurir').val();
        const kecamatanTujuan = $('#kecamatan_tujuan').val();
        
        if (!adaProdukFisik || !kurir || !kecamatanTujuan) {
            updateTotalDisplay();
            return;
        }
        
        $('#biayaOngkir').text('Menghitung...');
        let params = { 
            berat: totalBerat, 
            kurir: kurir, 
            kecamatan_asal: kecamatanAsal, 
            kecamatan_tujuan: kecamatanTujuan 
        };
        
        try {
            const response = await fetch(`${BASE_URL}/api_ongkir.php?${$.param(params)}`);
            if (!response.ok) throw new Error('Network response was not ok');
            const data = await response.json();
            
            if (data.success) {
                biayaOngkirSaatIni = data.biaya;
                $('#kurir-terpilih').text(`(${kurir.toUpperCase()})`);
            } else {
                biayaOngkirSaatIni = -1; // Tandai sebagai tidak tersedia
                alert(data.message || 'Gagal menghitung ongkir untuk rute ini.');
            }
        } catch (error) {
            biayaOngkirSaatIni = -1;
            alert('Terjadi kesalahan saat mengambil data ongkir.');
        } finally {
            updateTotalDisplay();
        }
    }

    if (adaProdukFisik) {
        if (!kecamatanAsal) {
            $('#shipping-warning').text('Penjual belum mengatur alamat asal pengiriman. Tidak dapat melanjutkan checkout.').show();
            $('#formCheckout').find('input, select, textarea, button').prop('disabled', true);
            return;
        }

        const provSelect = $('#provinsi_tujuan');
        const kotaSelect = $('#kota_tujuan');
        const kecSelect = $('#kecamatan_tujuan');

        $.getJSON(`${BASE_URL}/api_alamat.php?get=tujuan_provinsi&kecamatan_asal=${encodeURIComponent(kecamatanAsal)}`, function(data) {
            if(data.length === 0){
                provSelect.empty().append('<option value="">Wilayah anda belum masuk jangkauan pengiriman kami</option>').prop('disabled', true);
                 $('#btnBuatPesanan').prop('disabled', true).text('Tujuan Tidak Terjangkau');
            } else {
                 provSelect.empty().append('<option value="">-- Pilih Provinsi --</option>');
                 $.each(data, function(key, value) { provSelect.append($('<option>', { value: value, text: value })); });
            }
        });

        provSelect.on('change', function() {
            const prov = $(this).val();
            kotaSelect.empty().append('<option value="">-- Pilih Kota/Kab. --</option>').prop('disabled', true);
            kecSelect.empty().append('<option value="">-- Pilih Kecamatan --</option>').prop('disabled', true);
            if (prov) {
                $.getJSON(`${BASE_URL}/api_alamat.php?get=tujuan_kota&kecamatan_asal=${encodeURIComponent(kecamatanAsal)}&provinsi=${encodeURIComponent(prov)}`, function(data) {
                    kotaSelect.prop('disabled', false);
                    $.each(data, function(key, value) { kotaSelect.append($('<option>', { value: value, text: value })); });
                });
            }
        });

        kotaSelect.on('change', function() {
            const prov = provSelect.val();
            const kota = $(this).val();
            kecSelect.empty().append('<option value="">-- Pilih Kecamatan --</option>').prop('disabled', true);
            if (kota && prov) {
                 $.getJSON(`${BASE_URL}/api_alamat.php?get=tujuan_kecamatan&kecamatan_asal=${encodeURIComponent(kecamatanAsal)}&provinsi=${encodeURIComponent(prov)}&kota=${encodeURIComponent(kota)}`, function(data) {
                    kecSelect.prop('disabled', false);
                    $.each(data, function(key, value) { kecSelect.append($('<option>', { value: value, text: value })); });
                });
            }
        });
        
        $('#kurir, #kecamatan_tujuan').on('change', updateOngkir);
    }
    
    $('#applyVoucherBtn').on('click', async function() {
        const kodeVoucher = $('#kodeVoucher').val().trim();
        if (!kodeVoucher) return;
        
        $('#voucherStatus').text('Memeriksa...').removeClass('text-success text-danger');
        
        try {
            const response = await fetch(BASE_URL + '/proses_apply_voucher.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `kode_voucher=${encodeURIComponent(kodeVoucher)}`,
            });
            const data = await response.json();
            
            if (data.success) {
                diskonVoucherSaatIni = data.discount_amount;
                $('#voucherStatus').addClass('text-success').text(data.message);
                $('#inputKodeVoucher').val(data.applied_code);
                $('#inputNilaiDiskonVoucher').val(diskonVoucherSaatIni);
                $('#diskonVoucher').text('- Rp ' + diskonVoucherSaatIni.toLocaleString('id-ID'));
                $('#voucherDiscountDisplay').css('display', 'flex');
            } else {
                diskonVoucherSaatIni = 0;
                $('#voucherStatus').addClass('text-danger').text(data.message);
                $('#inputKodeVoucher').val('');
                $('#inputNilaiDiskonVoucher').val(0);
                $('#voucherDiscountDisplay').hide();
            }
            updateTotalDisplay();
        } catch (error) {
            $('#voucherStatus').addClass('text-danger').text('Terjadi kesalahan jaringan.');
        }
    });

    $('#kodeVoucher').on('input', function() {
        if (diskonVoucherSaatIni > 0) {
            diskonVoucherSaatIni = 0;
            $('#inputKodeVoucher').val('');
            $('#inputNilaiDiskonVoucher').val(0);
            $('#voucherStatus').text('').removeClass('text-success text-danger');
            $('#voucherDiscountDisplay').hide();
            updateTotalDisplay();
        }
    });
    
    $('#formCheckout').find('input, select, textarea').on('input change', updateCheckoutButtonState);
    updateCheckoutButtonState();
});
</script>