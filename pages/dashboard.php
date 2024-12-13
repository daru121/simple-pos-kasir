<?php
require_once '../backend/check_session.php';
include '../backend/database.php';

// Get user data
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        // Redirect jika user tidak ditemukan
        header('Location: ../index.php');
        exit;
    }
} catch (PDOException $e) {
    // Handle error
    error_log("Error fetching user data: " . $e->getMessage());
    $user = ['nama' => 'Unknown User']; // Default value
}

// Get today's data
$stmt = $conn->prepare("SELECT 
    COALESCE(SUM(t.total_harga), 0) as total_sales,
    COUNT(*) as total_transactions,
    COALESCE(SUM(dt.jumlah * (dt.harga - b.harga_modal)), 0) as total_profit
    FROM transaksi t
    LEFT JOIN detail_transaksi dt ON t.id = dt.transaksi_id
    LEFT JOIN barang b ON dt.barang_id = b.id
    WHERE DATE(t.tanggal) = CURDATE()");
$stmt->execute();
$today = $stmt->fetch();

// Get yesterday's data for comparison
$stmt = $conn->prepare("SELECT 
    COALESCE(SUM(t.total_harga), 0) as total_sales,
    COUNT(*) as total_transactions,
    COALESCE(SUM(dt.jumlah * (dt.harga - b.harga_modal)), 0) as total_profit
    FROM transaksi t
    LEFT JOIN detail_transaksi dt ON t.id = dt.transaksi_id
    LEFT JOIN barang b ON dt.barang_id = b.id
    WHERE DATE(t.tanggal) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)");
$stmt->execute();
$yesterday = $stmt->fetch();

// Get total products
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM barang");
$stmt->execute();
$totalProducts = $stmt->fetch()['total'];

// Calculate percentage changes
$salesChange = $yesterday['total_sales'] != 0 ?
    (($today['total_sales'] - $yesterday['total_sales']) / $yesterday['total_sales']) * 100 : 100;
$transactionsChange = $yesterday['total_transactions'] != 0 ?
    (($today['total_transactions'] - $yesterday['total_transactions']) / $yesterday['total_transactions']) * 100 : 100;
$profitChange = $yesterday['total_profit'] != 0 ?
    (($today['total_profit'] - $yesterday['total_profit']) / $yesterday['total_profit']) * 100 : 100;

// Tambahkan fungsi untuk mendapatkan data chart berdasarkan periode
function getChartData($conn, $days) {
    $stmt = $conn->prepare("
        SELECT 
            DATE(t.tanggal) as date,
            SUM(t.total_harga) as total_sales,
            SUM(dt.jumlah * (dt.harga - b.harga_modal)) as total_profit
        FROM transaksi t
        LEFT JOIN detail_transaksi dt ON t.id = dt.transaksi_id
        LEFT JOIN barang b ON dt.barang_id = b.id
        WHERE t.tanggal >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        GROUP BY DATE(t.tanggal)
        ORDER BY date ASC
    ");
    $stmt->execute([$days]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get initial chart data (7 days)
$chartData = getChartData($conn, 7);

// Get data for donut chart (top 5 products)
$stmt = $conn->prepare("
    SELECT 
        b.nama_barang,
        SUM(dt.jumlah) as total_sold
    FROM detail_transaksi dt
    JOIN barang b ON dt.barang_id = b.id
    WHERE dt.transaksi_id IN (
        SELECT id FROM transaksi 
        WHERE DATE(tanggal) = CURDATE()
    )
    GROUP BY b.id, b.nama_barang
    ORDER BY total_sold DESC
    LIMIT 5
");
$stmt->execute();
$topProducts = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SimplePOS - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo-container">
            <h4 class="text-center py-4 m-0">Pedagang<span class="text-light">Aksesoris</span></h4>
        </div>

        <div class="profile">
            <div class="d-flex align-items-center gap-3">
                <div class="profile-img">
                    <img src="https://awsimages.detik.net.id/community/media/visual/2017/06/22/c5f6a4b7-d06e-4512-b239-0b7d4d31714e_11.jpg?w=600&q=90" alt="Profile">
                </div>
                <div>
                    <h6 class="mb-0"><?php echo $user['nama']; ?></h6>
                    <small class="online-status">● Online</small>
                </div>
            </div>
        </div>

        <div class="nav-header">MAIN NAVIGATION</div>

        <nav class="nav flex-column">
            <a class="nav-link" href="dashboard.php">
                <i class="bi bi-speedometer2"></i>
                <span>DASHBOARD</span>
            </a>

            <div class="nav-item">
                <a class="nav-link has-arrow" href="#" data-bs-toggle="collapse" data-bs-target="#masterDataCollapse">
                    <i class="bi bi-database"></i>
                    <span>MASTER DATA</span>
                    <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse" id="masterDataCollapse">
                    <div class="submenu">
                        <a class="nav-link" href="kategori.php"><i class="bi bi-tags"></i> KATEGORI</a>
                        <a class="nav-link" href="barang.php"><i class="bi bi-box"></i> BARANG</a>
                        <a class="nav-link" href="operator.php"><i class="bi bi-person"></i> OPERATOR</a>
                        <a class="nav-link" href="stok.php"><i class="bi bi-box-seam"></i> STOK</a>
                    </div>
                </div>
            </div>

            <div class="nav-item">
                <a class="nav-link has-arrow" href="#" data-bs-toggle="collapse" data-bs-target="#penjualanCollapse">
                    <i class="bi bi-cart"></i>
                    <span>PENJUALAN</span>
                    <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse" id="penjualanCollapse">
                    <div class="submenu">
                        <a class="nav-link" href="penjualan.php"><i class="bi bi-cart-plus"></i> KASIR</a>
                        <a class="nav-link" href="info_penjualan.php"><i class="bi bi-info-circle"></i> INFORMASI</a>
                    </div>
                </div>
            </div>

            <a class="nav-link" href="laporan.php"><i class="bi bi-file-text"></i> LAPORAN</a>

            <div class="nav-divider"></div>
            <a class="nav-link text-danger" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">
                <i class="bi bi-box-arrow-right"></i>
                <span>LOGOUT</span>
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-navbar">
            <div class="d-flex justify-content-between align-items-center">
                <button class="btn btn-link text-light" id="sidebarToggle">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <div class="user-profile">
                    <a href="dashboard.php">
                        <img src="../assets/img/pedagangaksesoris.jpg" alt="Profile" class="rounded-circle profile-image" style="width: 40px; height: 40px; cursor: pointer;">
                    </a>
                </div>
            </div>
        </div>

        <div class="content-area">
            <!-- Statistics Cards -->
            <div class="row g-3 mb-4">
                <!-- Penjualan Card -->
                <div class="col-md-3">
                    <div class="card stat-card bg-primary bg-gradient text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-subtitle mb-2">Penjualan Hari Ini</h6>
                                    <h3 class="card-title mb-2">Rp <?= number_format($today['total_sales'], 0, ',', '.') ?></h3>
                                    <p class="card-text" style="color: <?= $salesChange >= 0 ? '#4ade80' : '#ff4d4d' ?> !important;">
                                        <i class="bi bi-arrow-<?= $salesChange >= 0 ? 'up' : 'down' ?>"></i>
                                        <?= abs(round($salesChange, 1)) ?>% vs kemarin
                                    </p>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-cart-check-fill fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transaksi Card -->
                <div class="col-md-3">
                    <div class="card stat-card bg-success bg-gradient text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-subtitle mb-2">Transaksi Hari Ini</h6>
                                    <h3 class="card-title mb-2"><?= $today['total_transactions'] ?></h3>
                                    <p class="card-text" style="color: <?= $transactionsChange >= 0 ? '#4ade80' : '#ff4d4d' ?> !important;">
                                        <i class="bi bi-arrow-<?= $transactionsChange >= 0 ? 'up' : 'down' ?>"></i>
                                        <?= abs(round($transactionsChange, 1)) ?>% vs kemarin
                                    </p>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-receipt fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profit Card -->
                <div class="col-md-3">
                    <div class="card stat-card bg-info bg-gradient text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-subtitle mb-2">Profit Hari Ini</h6>
                                    <h3 class="card-title mb-2" style="white-space: nowrap;">Rp <?= number_format($today['total_profit'], 0, ',', '.') ?></h3>
                                    <p class="card-text" style="color: <?= $profitChange >= 0 ? '#4ade80' : '#ff4d4d' ?> !important;">
                                        <i class="bi bi-arrow-<?= $profitChange >= 0 ? 'up' : 'down' ?>"></i>
                                        <?= abs(round($profitChange, 1)) ?>% vs kemarin
                                    </p>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-graph-up-arrow fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Produk Card -->
                <div class="col-md-3">
                    <div class="card stat-card bg-warning bg-gradient text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-subtitle mb-2">Total Produk</h6>
                                    <h3 class="card-title mb-2"><?= $totalProducts ?></h3>
                                    <p class="card-text text-light">
                                        <i class="bi bi-box-seam"></i> Total Katalog
                                    </p>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-box-seam-fill fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row g-3 mt-2">
                <!-- Line Chart -->
                <div class="col-md-8">
                    <div class="card chart-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 class="card-title mb-0">Trend Penjualan & Profit</h5>
                            </div>
                            <div class="chart-container">
                                <canvas id="trendChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Donut Chart -->
                <div class="col-md-4">
                    <div class="card chart-card h-100">
                        <div class="card-body">
                            <h5 class="card-title mb-4">Produk Terlaris Hari Ini</h5>
                            <div class="chart-container">
                                <canvas id="donutChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Convert PHP data to JavaScript
        const chartData = <?= json_encode($chartData) ?>;
        const topProducts = <?= json_encode($topProducts) ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/sidebar.js"></script>
    <script src="../assets/js/dashboard.js"></script>
</body>

</html>