<?php
require_once '../backend/check_session.php';
require_once '../backend/database.php';

// Get user data - Add this at the beginning
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        header('Location: ../index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Error fetching user data: " . $e->getMessage());
    $user = ['nama' => 'Unknown User']; // Default value
}

// Function to get daily report
function getDailyReport($date = null)
{
    global $conn;
    $date = $date ?? date('Y-m-d');

    // Validasi format tanggal
    if (!DateTime::createFromFormat('Y-m-d', $date)) {
        $date = date('Y-m-d');
    }

    $query = "SELECT 
                t.id as transaksi_id,
                t.tanggal,
                t.total_harga as total,
                GROUP_CONCAT(
                    CONCAT(
                        b.nama_barang, '|',
                        dt.jumlah, '|',
                        dt.harga, '|',
                        b.harga_modal
                    ) SEPARATOR ';;'
                ) as detail_barang
              FROM transaksi t
              JOIN detail_transaksi dt ON t.id = dt.transaksi_id
              JOIN barang b ON dt.barang_id = b.id
              WHERE DATE(t.tanggal) = ?
              GROUP BY t.id, t.tanggal, t.total_harga
              ORDER BY t.tanggal DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute([$date]);
    return $stmt->fetchAll();
}

// Function to get monthly report
function getMonthlyReport($month = null, $year = null)
{
    global $conn;
    $month = $month ?? date('m');
    $year = $year ?? date('Y');

    $query = "SELECT 
                DATE_FORMAT(t.tanggal, '%M %Y') as bulan,
                COUNT(DISTINCT t.id) as total_transaksi,
                SUM(t.total_harga) as total_penjualan,
                SUM((dt.harga - b.harga_modal) * dt.jumlah) as total_profit
              FROM transaksi t
              JOIN detail_transaksi dt ON t.id = dt.transaksi_id
              JOIN barang b ON dt.barang_id = b.id
              WHERE MONTH(t.tanggal) = ? AND YEAR(t.tanggal) = ?
              GROUP BY MONTH(t.tanggal), YEAR(t.tanggal)";

    $stmt = $conn->prepare($query);
    $stmt->execute([$month, $year]);
    return $stmt->fetchAll();
}

// Function to get yearly report
function getYearlyReport($year = null)
{
    global $conn;
    $year = $year ?? date('Y');

    $query = "SELECT 
                YEAR(t.tanggal) as tahun,
                COUNT(DISTINCT t.id) as total_transaksi,
                SUM(t.total_harga) as total_penjualan,
                SUM((dt.harga - b.harga_modal) * dt.jumlah) as total_profit
              FROM transaksi t
              JOIN detail_transaksi dt ON t.id = dt.transaksi_id
              JOIN barang b ON dt.barang_id = b.id
              WHERE YEAR(t.tanggal) = ?
              GROUP BY YEAR(t.tanggal)";

    $stmt = $conn->prepare($query);
    $stmt->execute([$year]);
    return $stmt->fetchAll();
}

// Modifikasi fungsi exportToExcel
function exportToExcel($dailyReport, $selectedDate)
{
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="laporan_penjualan_' . $selectedDate . '.xls"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
?>
    <html>

    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    </head>

    <body>
        <table border="1">
            <thead>
                <tr>
                    <th colspan="5" style="text-align: center; font-size: 16px; font-weight: bold;">LAPORAN PENJUALAN HARIAN</th>
                </tr>
                <tr>
                    <th colspan="5">Tanggal: <?php echo date('d/m/Y', strtotime($selectedDate)); ?></th>
                </tr>
                <tr>
                    <th>No</th>
                    <th>Tanggal</th>
                    <th>Detail Pembelian</th>
                    <th>Total</th>
                    <th>Profit</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $no = 1;
                $grandTotal = 0;
                $totalProfit = 0;
                foreach ($dailyReport as $row):
                    $details = explode(';;', $row['detail_barang']);
                    $rowProfit = 0;
                    $detailText = '';
                    foreach ($details as $detail) {
                        list($nama, $jumlah, $harga, $harga_modal) = explode('|', $detail);
                        $subtotal = $jumlah * $harga;
                        $rowProfit += ($harga - $harga_modal) * $jumlah;
                        $detailText .= $nama . ' (' . $jumlah . ' x Rp ' . number_format($harga, 0, ',', '.') . ' = Rp ' . number_format($subtotal, 0, ',', '.') . ")\n";
                    }
                    $grandTotal += $row['total'];
                    $totalProfit += $rowProfit;
                ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($row['tanggal'])); ?></td>
                        <td><?php echo $detailText; ?></td>
                        <td>Rp <?php echo number_format($row['total'], 0, ',', '.'); ?></td>
                        <td>Rp <?php echo number_format($rowProfit, 0, ',', '.'); ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="3" style="text-align: right;"><b>Total:</b></td>
                    <td><b>Rp <?php echo number_format($grandTotal, 0, ',', '.'); ?></b></td>
                    <td><b>Rp <?php echo number_format($totalProfit, 0, ',', '.'); ?></b></td>
                </tr>
            </tbody>
        </table>
    </body>

    </html>
<?php
    exit;
}

// Modifikasi bagian yang menangani request export
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    $selectedDate = $_GET['date'] ?? date('Y-m-d');
    $dailyReport = getDailyReport($selectedDate); // Mengambil data hanya untuk tanggal yang dipilih
    exportToExcel($dailyReport, $selectedDate);
}

function exportMonthlyToExcel($monthlyReport, $selectedMonth, $selectedYear)
{
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="laporan_bulanan_' . $selectedMonth . '_' . $selectedYear . '.xls"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
?>
    <html>

    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    </head>

    <body>
        <table border="1">
            <thead>
                <tr>
                    <th colspan="4" style="text-align: center; font-size: 16px; font-weight: bold;">LAPORAN PENJUALAN BULANAN</th>
                </tr>
                <tr>
                    <th colspan="4">Periode: <?php echo date('F Y', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear)); ?></th>
                </tr>
                <tr>
                    <th style="background: #f3f3f3; font-weight: bold;">No</th>
                    <th style="background: #f3f3f3; font-weight: bold;">Bulan</th>
                    <th style="background: #f3f3f3; font-weight: bold;">Total Transaksi</th>
                    <th style="background: #f3f3f3; font-weight: bold;">Total Penjualan</th>
                    <th style="background: #f3f3f3; font-weight: bold;">Total Profit</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $no = 1;
                $totalPenjualan = 0;
                $totalProfit = 0;
                foreach ($monthlyReport as $row):
                    $totalPenjualan += $row['total_penjualan'];
                    $totalProfit += $row['total_profit'];
                ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= $row['bulan'] ?></td>
                        <td><?= $row['total_transaksi'] ?></td>
                        <td>Rp <?= number_format($row['total_penjualan'], 0, ',', '.') ?></td>
                        <td>Rp <?= number_format($row['total_profit'], 0, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="fw-bold">
                    <td colspan="3" class="text-end">Total:</td>
                    <td>Rp <?= number_format($totalPenjualan, 0, ',', '.') ?></td>
                    <td>Rp <?= number_format($totalProfit, 0, ',', '.') ?></td>
                </tr>
            </tfoot>
        </table>
    </body>

    </html>
<?php
    exit;
}

// Tambahkan kondisi untuk handle export bulanan
if (isset($_GET['export']) && $_GET['export'] == 'excel_monthly') {
    exportMonthlyToExcel($monthlyReport, $selectedMonth, $selectedYear);
}

// Tambahkan fungsi untuk export Excel laporan tahunan
function exportYearlyToExcel($yearlyReport, $selectedYear)
{
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="laporan_tahunan_' . $selectedYear . '.xls"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
?>
    <html>

    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    </head>

    <body>
        <table border="1">
            <thead>
                <tr>
                    <th colspan="4" style="text-align: center; font-size: 16px; font-weight: bold;">LAPORAN PENJUALAN TAHUNAN</th>
                </tr>
                <tr>
                    <th colspan="4">Tahun: <?php echo $selectedYear; ?></th>
                </tr>
                <tr>
                    <th style="background: #f3f3f3; font-weight: bold;">No</th>
                    <th style="background: #f3f3f3; font-weight: bold;">Tahun</th>
                    <th style="background: #f3f3f3; font-weight: bold;">Total Transaksi</th>
                    <th style="background: #f3f3f3; font-weight: bold;">Total Penjualan</th>
                    <th style="background: #f3f3f3; font-weight: bold;">Total Profit</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $no = 1;
                $totalPenjualan = 0;
                $totalProfit = 0;
                foreach ($yearlyReport as $row):
                    $totalPenjualan += $row['total_penjualan'];
                    $totalProfit += $row['total_profit'];
                ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= $row['tahun'] ?></td>
                        <td><?= $row['total_transaksi'] ?></td>
                        <td>Rp <?= number_format($row['total_penjualan'], 0, ',', '.') ?></td>
                        <td>Rp <?= number_format($row['total_profit'], 0, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="fw-bold">
                    <td colspan="3" class="text-end">Total:</td>
                    <td>Rp <?= number_format($totalPenjualan, 0, ',', '.') ?></td>
                    <td>Rp <?= number_format($totalProfit, 0, ',', '.') ?></td>
                </tr>
            </tfoot>
        </table>
    </body>

    </html>
<?php
    exit;
}

// Tambahkan kondisi untuk handle export tahunan
if (isset($_GET['export']) && $_GET['export'] == 'excel_yearly') {
    exportYearlyToExcel($yearlyReport, $selectedYear);
}

// Handle report requests
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$selectedMonth = $_GET['month'] ?? date('m');
$selectedYear = $_GET['year'] ?? date('Y');

$dailyReport = getDailyReport($selectedDate);
$monthlyReport = getMonthlyReport($selectedMonth, $selectedYear);
$yearlyReport = getYearlyReport($selectedYear);

// Calculate totals
$dailyTotal = array_sum(array_column($dailyReport, 'total'));
$monthlyTotal = array_sum(array_column($monthlyReport, 'total_penjualan'));
$yearlyTotal = array_sum(array_column($yearlyReport, 'total_penjualan'));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SimplePOS - Laporan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/laporan.css">
    <style>
        .card {
            transition: all 0.3s ease;
            border-radius: 10px;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background: linear-gradient(45deg, #4e73df, #36b9cc);
            color: white;
            padding: 1rem;
        }

        .table {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .profit-positive {
            color: #1cc88a;
            font-weight: bold;
        }

        .profit-negative {
            color: #e74a3b;
            font-weight: bold;
        }

        canvas {
            animation: slideIn 0.8s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
    </style>
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
            <div class="container-fluid">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Laporan Penjualan</h2>
                </div>

                <!-- Report Tabs -->
                <ul class="nav nav-tabs mb-4" id="reportTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active text-dark" id="daily-tab" data-bs-toggle="tab" data-bs-target="#daily" type="button" role="tab">
                            <i class="bi bi-calendar-day me-2"></i>Laporan Harian
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link text-dark" id="monthly-tab" data-bs-toggle="tab" data-bs-target="#monthly" type="button" role="tab">
                            <i class="bi bi-calendar-month me-2"></i>Laporan Bulanan
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link text-dark" id="yearly-tab" data-bs-toggle="tab" data-bs-target="#yearly" type="button" role="tab">
                            <i class="bi bi-calendar me-2"></i>Laporan Tahunan
                        </button>
                    </li>
                </ul>
                <!-- Tab Content -->
                <div class="tab-content" id="reportTabContent">
                    <!-- Daily Report -->
                    <div class="tab-pane fade show active" id="daily" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <h5 class="card-title mb-0">Laporan Harian</h5>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex gap-2 justify-content-end">
                                            <input type="date" class="form-control w-auto" id="reportDate"
                                                value="<?php echo $selectedDate; ?>"
                                                max="<?php echo date('Y-m-d'); ?>">
                                            <button class="btn btn-primary" onclick="searchByDate()">
                                                <i class="bi bi-search me-2"></i>Cari
                                            </button>
                                            <button class="btn btn-success" onclick="exportExcel()">
                                                <i class="bi bi-file-earmark-excel me-2"></i>Export Excel
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>No</th>
                                                <th>Tanggal</th>
                                                <th>Detail Pembelian</th>
                                                <th>Total</th>
                                                <th width="2%">Profit</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $no = 1;
                                            $totalProfit = 0;
                                            foreach ($dailyReport as $row):
                                                $details = explode(';;', $row['detail_barang']);
                                                $rowProfit = 0;
                                                foreach ($details as $detail) {
                                                    list($nama, $jumlah, $harga, $harga_modal) = explode('|', $detail);
                                                    $rowProfit += ($harga - $harga_modal) * $jumlah;
                                                }
                                                $totalProfit += $rowProfit;
                                            ?>
                                                <tr>
                                                    <td><?= $no++ ?></td>
                                                    <td><?= date('d/m/Y H:i', strtotime($row['tanggal'])) ?></td>
                                                    <td>
                                                        <?php foreach ($details as $detail):
                                                            list($nama, $jumlah, $harga) = explode('|', $detail);
                                                            echo $nama . ', ' . $jumlah . ' x Rp ' . number_format($harga, 0, ',', '.') . ' = Rp ' . number_format($jumlah * $harga, 0, ',', '.') . '<br>';
                                                        endforeach; ?>
                                                    </td>
                                                    <td>Rp <?= number_format($row['total'], 0, ',', '.') ?></td>
                                                    <td>Rp <?= number_format($rowProfit, 0, ',', '.') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="fw-bold">
                                                <td colspan="3" class="text-end">Total:</td>
                                                <td>Rp <?= number_format($dailyTotal, 0, ',', '.') ?></td>
                                                <td>Rp <?= number_format($totalProfit, 0, ',', '.') ?></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Monthly Report -->
                    <div class="tab-pane fade" id="monthly" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <h5 class="card-title mb-0">Laporan Bulanan</h5>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex gap-2 justify-content-end">
                                            <select class="form-select w-auto" id="monthSelect">
                                                <option value="">Pilih Bulan</option>
                                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                                    <option value="<?= $i ?>" <?= $selectedMonth == $i ? 'selected' : '' ?>>
                                                        <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                                                    </option>
                                                <?php endfor; ?>
                                            </select>
                                            <button class="btn btn-primary" onclick="searchByMonth()">
                                                <i class="bi bi-search me-2"></i>Cari
                                            </button>
                                            <button class="btn btn-success" onclick="exportMonthlyExcel()">
                                                <i class="bi bi-file-earmark-excel me-2"></i>Export Excel
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>No</th>
                                                <th>Bulan</th>
                                                <th>Total Transaksi</th>
                                                <th>Total Penjualan</th>
                                                <th>Total Profit</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $no = 1;
                                            $totalPenjualan = 0;
                                            $totalProfit = 0;
                                            foreach ($monthlyReport as $row):
                                                $totalPenjualan += $row['total_penjualan'];
                                                $totalProfit += $row['total_profit'];
                                            ?>
                                                <tr>
                                                    <td><?= $no++ ?></td>
                                                    <td><?= $row['bulan'] ?></td>
                                                    <td><?= $row['total_transaksi'] ?></td>
                                                    <td>Rp <?= number_format($row['total_penjualan'], 0, ',', '.') ?></td>
                                                    <td>Rp <?= number_format($row['total_profit'], 0, ',', '.') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="fw-bold">
                                                <td colspan="3" class="text-end">Total:</td>
                                                <td>Rp <?= number_format($totalPenjualan, 0, ',', '.') ?></td>
                                                <td>Rp <?= number_format($totalProfit, 0, ',', '.') ?></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Yearly Report -->
                    <div class="tab-pane fade" id="yearly" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <h5 class="card-title mb-0">Laporan Tahunan</h5>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex gap-2 justify-content-end">
                                            <select class="form-select w-auto" id="yearSelect">
                                                <?php
                                                $currentYear = date('Y');
                                                for ($i = $currentYear; $i >= $currentYear - 5; $i--): ?>
                                                    <option value="<?= $i ?>" <?= $selectedYear == $i ? 'selected' : '' ?>>
                                                        <?= $i ?>
                                                    </option>
                                                <?php endfor; ?>
                                            </select>
                                            <button class="btn btn-primary" onclick="searchByYear()">
                                                <i class="bi bi-search me-2"></i>Cari
                                            </button>
                                            <button class="btn btn-success" onclick="exportYearlyExcel()">
                                                <i class="bi bi-file-earmark-excel me-2"></i>Export Excel
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>No</th>
                                                <th>Tahun</th>
                                                <th>Total Transaksi</th>
                                                <th>Total Penjualan</th>
                                                <th>Total Profit</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $no = 1;
                                            $totalPenjualan = 0;
                                            $totalProfit = 0;
                                            foreach ($yearlyReport as $row):
                                                $totalPenjualan += $row['total_penjualan'];
                                                $totalProfit += $row['total_profit'];
                                            ?>
                                                <tr>
                                                    <td><?= $no++ ?></td>
                                                    <td><?= $row['tahun'] ?></td>
                                                    <td><?= $row['total_transaksi'] ?></td>
                                                    <td>Rp <?= number_format($row['total_penjualan'], 0, ',', '.') ?></td>
                                                    <td>Rp <?= number_format($row['total_profit'], 0, ',', '.') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="fw-bold">
                                                <td colspan="3" class="text-end">Total:</td>
                                                <td>Rp <?= number_format($totalPenjualan, 0, ',', '.') ?></td>
                                                <td>Rp <?= number_format($totalProfit, 0, ',', '.') ?></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        <script src="../assets/js/sidebar.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Set default date jika tidak ada tanggal yang dipilih
                const reportDate = document.getElementById('reportDate');
                if (!reportDate.value) {
                    reportDate.value = new Date().toISOString().split('T')[0];
                }

                // Batasi tanggal maksimal ke hari ini
                reportDate.max = new Date().toISOString().split('T')[0];
            });

            // Fungsi untuk mencari berdasarkan tanggal
            function searchByDate() {
                const selectedDate = document.getElementById('reportDate').value;
                if (!selectedDate) {
                    alert('Pilih tanggal terlebih dahulu!');
                    return;
                }
                window.location.href = `?date=${selectedDate}`;
            }

            // Fungsi untuk export Excel
            function exportExcel() {
                const selectedDate = document.getElementById('reportDate').value;
                if (!selectedDate) {
                    alert('Pilih tanggal terlebih dahulu!');
                    return;
                }
                window.location.href = `?export=excel&date=${selectedDate}`;
            }

            // Event listener untuk perubahan tanggal
            document.getElementById('reportDate').addEventListener('change', function() {
                const selectedDate = this.value;
                const today = new Date().toISOString().split('T')[0];

                // Validasi tanggal tidak boleh lebih dari hari ini
                if (selectedDate > today) {
                    alert('Tanggal tidak boleh lebih dari hari ini!');
                    this.value = today;
                    return;
                }

                // Otomatis update laporan saat tanggal berubah
                window.location.href = `?date=${selectedDate}`;
            });

            // Fungsi untuk mencari berdasarkan bulan
            function searchByMonth() {
                const selectedMonth = document.getElementById('monthSelect').value;
                const currentYear = new Date().getFullYear(); // Selalu gunakan tahun saat ini

                if (!selectedMonth) {
                    alert('Pilih bulan terlebih dahulu!');
                    return;
                }

                window.location.href = `?month=${selectedMonth}&year=${currentYear}`;
            }

            // Fungsi untuk export Excel laporan bulanan
            function exportMonthlyExcel() {
                const selectedMonth = document.getElementById('monthSelect').value;
                const currentYear = new Date().getFullYear(); // Selalu gunakan tahun saat ini

                if (!selectedMonth) {
                    alert('Pilih bulan terlebih dahulu!');
                    return;
                }

                window.location.href = `?export=excel_monthly&month=${selectedMonth}&year=${currentYear}`;
            }

            // Event listener untuk perubahan bulan
            document.getElementById('monthSelect').addEventListener('change', function() {
                const selectedMonth = this.value;
                const currentYear = new Date().getFullYear(); // Selalu gunakan tahun saat ini

                if (selectedMonth) {
                    window.location.href = `?month=${selectedMonth}&year=${currentYear}`;
                }
            });

            // Tambahkan validasi untuk bulan saat halaman dimuat
            document.addEventListener('DOMContentLoaded', function() {
                const monthSelect = document.getElementById('monthSelect');

                // Set default ke bulan saat ini jika belum dipilih
                if (!monthSelect.value) {
                    monthSelect.value = new Date().getMonth() + 1;
                }

                // Validasi bulan tidak boleh lebih dari bulan saat ini
                monthSelect.addEventListener('change', function() {
                    const currentDate = new Date();
                    const selectedMonth = parseInt(this.value);

                    if (selectedMonth > currentDate.getMonth() + 1) {
                        alert('Tidak dapat memilih bulan lebih dari bulan saat ini!');
                        this.value = currentDate.getMonth() + 1;
                    }
                });
            });

            // Fungsi untuk mencari berdasarkan tahun
            function searchByYear() {
                const selectedYear = document.getElementById('yearSelect').value;
                if (!selectedYear) {
                    alert('Pilih tahun terlebih dahulu!');
                    return;
                }
                window.location.href = `?year=${selectedYear}`;
            }

            // Fungsi untuk export Excel laporan tahunan
            function exportYearlyExcel() {
                const selectedYear = document.getElementById('yearSelect').value;
                if (!selectedYear) {
                    alert('Pilih tahun terlebih dahulu!');
                    return;
                }
                window.location.href = `?export=excel_yearly&year=${selectedYear}`;
            }

            // Event listener untuk perubahan tahun
            document.getElementById('yearSelect').addEventListener('change', function() {
                const selectedYear = this.value;
                if (selectedYear) {
                    window.location.href = `?year=${selectedYear}`;
                }
            });

            // Validasi tahun saat halaman dimuat
            document.addEventListener('DOMContentLoaded', function() {
                const yearSelect = document.getElementById('yearSelect');

                // Set default ke tahun saat ini jika belum dipilih
                if (!yearSelect.value) {
                    yearSelect.value = new Date().getFullYear();
                }

                // Validasi tahun tidak boleh lebih dari tahun saat ini
                yearSelect.addEventListener('change', function() {
                    const currentYear = new Date().getFullYear();
                    const selectedYear = parseInt(this.value);

                    if (selectedYear > currentYear) {
                        alert('Tidak dapat memilih tahun lebih dari tahun saat ini!');
                        this.value = currentYear;
                    }
                });
            });

            // Fungsi untuk format currency
            function formatCurrency(value) {
                return 'Rp ' + new Intl.NumberFormat('id-ID').format(value);
            }

            // Konfigurasi warna
            const colors = {
                penjualan: {
                    background: 'rgba(54, 162, 235, 0.2)',
                    border: 'rgba(54, 162, 235, 1)'
                },
                profit: {
                    background: 'rgba(75, 192, 192, 0.2)',
                    border: 'rgba(75, 192, 192, 1)'
                }
            };

            // Inisialisasi Chart Harian
            function initDailyChart(data) {
                const ctx = document.getElementById('dailyChart').getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: [{
                                label: 'Penjualan',
                                data: data.penjualan,
                                backgroundColor: colors.penjualan.background,
                                borderColor: colors.penjualan.border,
                                borderWidth: 1
                            },
                            {
                                label: 'Profit',
                                data: data.profit,
                                backgroundColor: colors.profit.background,
                                borderColor: colors.profit.border,
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        animation: {
                            duration: 1000,
                            easing: 'easeInOutQuart'
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return formatCurrency(value);
                                    }
                                }
                            }
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.dataset.label + ': ' + formatCurrency(context.raw);
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // Inisialisasi Chart Bulanan
            function initMonthlyChart(data) {
                const ctx = document.getElementById('monthlyChart').getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.labels,
                        datasets: [{
                                label: 'Penjualan',
                                data: data.penjualan,
                                borderColor: colors.penjualan.border,
                                backgroundColor: colors.penjualan.background,
                                fill: true,
                                tension: 0.4
                            },
                            {
                                label: 'Profit',
                                data: data.profit,
                                borderColor: colors.profit.border,
                                backgroundColor: colors.profit.background,
                                fill: true,
                                tension: 0.4
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        animation: {
                            duration: 1000,
                            easing: 'easeInOutQuart'
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return formatCurrency(value);
                                    }
                                }
                            }
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.dataset.label + ': ' + formatCurrency(context.raw);
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // Inisialisasi Chart Tahunan
            function initYearlyChart(data) {
                const ctx = document.getElementById('yearlyChart').getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: [{
                                label: 'Penjualan',
                                data: data.penjualan,
                                backgroundColor: colors.penjualan.background,
                                borderColor: colors.penjualan.border,
                                borderWidth: 1
                            },
                            {
                                label: 'Profit',
                                data: data.profit,
                                backgroundColor: colors.profit.background,
                                borderColor: colors.profit.border,
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        animation: {
                            duration: 1000,
                            easing: 'easeInOutQuart'
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return formatCurrency(value);
                                    }
                                }
                            }
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.dataset.label + ': ' + formatCurrency(context.raw);
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // Inisialisasi chart saat halaman dimuat
            document.addEventListener('DOMContentLoaded', function() {
                // Data untuk chart harian
                const dailyData = {
                    labels: <?= json_encode(array_map(function ($row) {
                                return date('d/m', strtotime($row['tanggal']));
                            }, $dailyReport)) ?>,
                    penjualan: <?= json_encode(array_map(function ($row) {
                                    return $row['total'];
                                }, $dailyReport)) ?>,
                    profit: <?= json_encode(array_map(function ($row) {
                                $profit = 0;
                                foreach (explode(';;', $row['detail_barang']) as $detail) {
                                    list($nama, $jumlah, $harga, $harga_modal) = explode('|', $detail);
                                    $profit += ($harga - $harga_modal) * $jumlah;
                                }
                                return $profit;
                            }, $dailyReport)) ?>
                };

                // Data untuk chart bulanan
                const monthlyData = {
                    labels: <?= json_encode(array_map(function ($row) {
                                return $row['bulan'];
                            }, $monthlyReport)) ?>,
                    penjualan: <?= json_encode(array_map(function ($row) {
                                    return $row['total_penjualan'];
                                }, $monthlyReport)) ?>,
                    profit: <?= json_encode(array_map(function ($row) {
                                return $row['total_profit'];
                            }, $monthlyReport)) ?>
                };

                // Data untuk chart tahunan
                const yearlyData = {
                    labels: <?= json_encode(array_map(function ($row) {
                                return $row['tahun'];
                            }, $yearlyReport)) ?>,
                    penjualan: <?= json_encode(array_map(function ($row) {
                                    return $row['total_penjualan'];
                                }, $yearlyReport)) ?>,
                    profit: <?= json_encode(array_map(function ($row) {
                                return $row['total_profit'];
                            }, $yearlyReport)) ?>
                };

                // Inisialisasi semua chart
                initDailyChart(dailyData);
                initMonthlyChart(monthlyData);
                initYearlyChart(yearlyData);
            });
        </script>
</body>

</html>