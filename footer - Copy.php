<?php
// /includes/footer.php (Versi Final yang Aman & Anti-Cache)
?>
</main> <footer class="main-footer">
    <div class="container py-5">
        <div class="row">
            <div class="col-md-4 mb-4">
                <h5>Tentang Sabaku ID</h5>
                <ul class="list-unstyled">
                    <li><a href="/tentang_kami.php">Tentang Kami</a></li>
                    <li><a href="/kontak.php">Kontak</a></li>
                    <li><a href="/kebijakan_pengembalian.php">Kebijakan Pengembalian</a></li>
                    <li><a href="/faq.php">FAQ</a></li>
                </ul>
            </div>
            <div class="col-md-4 mb-4">
                <h5>Ikuti Kami</h5>
                <p>Dapatkan info terbaru mengenai produk dan promo kami.</p>
                <div>
                    <a href="#" class="social-icon"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="social-icon"><i class="bi bi-instagram"></i></a>
                    <a href="#" class="social-icon"><i class="bi bi-tiktok"></i></a>
                    <a href="#" class="social-icon"><i class="bi bi-youtube"></i></a>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <h5>Kontak Kami</h5>
                <p class="mb-1"><strong>Sabaku ID - SMKN 1 Bangkalan</strong></p>
                <p class="mb-1">Jl. Kenanga No. 4 Mlajah Bangkalan</p>
                <p class="mb-1">Email: officialsabaku@gmail.com</p>
            </div>
        </div>
        <hr>
        <div class="copyright-section">
            <img src="/assets/img/logo_sabaku.png" alt="Logo Sabaku ID" class="copyright-logo">
            <img src="/assets/img/logo_smkn1.png" alt="Logo SMKN 1 Bangkalan" class="copyright-logo">
            <p class="mb-0">&copy; 2025 Sabaku.ID – SMKN 1 Bangkalan</p>
        </div>
    </div>
</footer>

<footer class="mobile-nav-footer">
    <a href="/" class="mobile-nav-item">
        <span>🏠</span><br>Beranda
    </a>
    <a href="/trending.php" class="mobile-nav-item">
        <span>🔥</span><br>Trending
    </a>
    <a href="/notifikasi.php" class="mobile-nav-item">
        <span>🔔</span><br>Notifikasi
    </a>
    <a href="/saya.php" class="mobile-nav-item">
        <span>👤</span><br>Saya
    </a>
</footer>

<style>
    /* --- Footer Utama (Desktop) --- */
    .main-footer {
        background-color: #f8f9fa;
        border-top: 1px solid #e7e7e7;
        color: #555;
        display: none; /* Sembunyikan di mobile secara default */
    }
    .main-footer h5 { color: #333; font-weight: bold; margin-bottom: 1rem; }
    .main-footer ul li { margin-bottom: 0.5rem; }
    .main-footer a { text-decoration: none; color: #555; transition: color 0.2s; }
    .main-footer a:hover { color: #007bff; }
    .social-icon { font-size: 1.5rem; margin-right: 1rem; }
    .social-icon i { transition: transform 0.2s; }
    .social-icon:hover i { transform: scale(1.2); }

    /* --- Style untuk Copyright di Footer --- */
    .copyright-section {
        display: flex;
        justify-content: center;
        align-items: center;
        text-align: center;
    }
    .copyright-logo {
        height: 30px;
        width: auto;
        margin: 0 5px;
    }

    /* --- Pengaturan Responsif --- */
    @media (min-width: 768px) {
        .main-footer {
            display: block; /* Tampilkan footer utama di layar besar */
        }
        .mobile-nav-footer {
            display: none; /* Sembunyikan navigasi mobile di layar besar */
        }
    }
</style>
<script src="/assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>