<?php
require_once '../backend/check_session.php';
require_once '../backend/database.php';

try {
    // Get user data
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    // Get sales information with buyer details
    $query = "SELECT 
        t.id,
        p.nama as buyer_name,
        t.tanggal,
        t.total_harga,
        SUM(dt.jumlah * (dt.harga - b.harga_modal)) as profit
    FROM transaksi t
    LEFT JOIN pembeli p ON t.pembeli_id = p.id
    LEFT JOIN detail_transaksi dt ON t.id = dt.transaksi_id
    LEFT JOIN barang b ON dt.barang_id = b.id
    WHERE 1=1";
    
    $params = [];

    if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
        $query .= " AND DATE(t.tanggal) BETWEEN :start_date AND :end_date";
        $params[':start_date'] = $_GET['start_date'];
        $params[':end_date'] = $_GET['end_date'];
    }

    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $query .= " AND p.nama LIKE :search";
        $params[':search'] = '%' . $_GET['search'] . '%';
    }

    $query .= " GROUP BY t.id ORDER BY t.tanggal DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();

} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SimplePOS - Informasi Penjualan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/info_penjualan.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
    
        <!-- Main content area -->
        <div class="content-area">
            <div class="container-fluid">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Informasi Penjualan</h5>
                        <div class="d-flex gap-3">
                            <div class="date-range-filter">
                                <div class="input-group">
                                    <input type="date" 
                                           class="form-control form-control-sm" 
                                           id="startDate" 
                                           name="start_date" 
                                           value="<?= isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days')) ?>">
                                </div>
                                <span class="input-group-text">to</span>
                                <div class="input-group">
                                    <input type="date" 
                                           class="form-control form-control-sm" 
                                           id="endDate" 
                                           name="end_date" 
                                           value="<?= isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d') ?>">
                                </div>
                            </div>
                            <div class="search-filter">
                                <div class="input-group">
                                    <input type="text" 
                                           class="form-control form-control-sm" 
                                           id="searchInput" 
                                           placeholder="Cari nama pembeli..." 
                                           value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
                                    <button class="btn btn-primary btn-sm" type="button" onclick="applyFilter()">
                                        <i class="bi bi-search"></i> Cari
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="mb-3 d-flex justify-content-end">
                            <button class="btn btn-danger btn-sm" onclick="deleteSelected()" id="bulkDeleteBtn" style="display: none;">
                                <i class="bi bi-trash"></i> Hapus Data Terpilih
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>
                                            <input type="checkbox" class="form-check-input" id="selectAll" onclick="toggleSelectAll()">
                                        </th>
                                        <th>No</th>
                                        <th>Nama Pembeli</th>
                                        <th>Tanggal Transaksi</th>
                                        <th>Total Pembelian</th>
                                        <th>Profit</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $no = 1;
                                    foreach($transactions as $transaction): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="form-check-input row-checkbox" 
                                                       value="<?= $transaction['id'] ?>" 
                                                       onchange="toggleDeleteButton()">
                                            </td>
                                            <td><?= $no++ ?></td>
                                            <td><?= htmlspecialchars($transaction['buyer_name']) ?></td>
                                            <td><?= date('d/m/Y H:i', strtotime($transaction['tanggal'])) ?></td>
                                            <td>Rp <?= number_format($transaction['total_harga'], 0, ',', '.') ?></td>
                                            <td>Rp <?= number_format($transaction['profit'], 0, ',', '.') ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-info" onclick="viewDetail(<?= $transaction['id'] ?>)">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button class="btn btn-danger" onclick="deleteTransaction(<?= $transaction['id'] ?>)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Modal for Transaction Details -->
    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Transaksi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/sidebar.js"></script>
    <script>
    function viewDetail(transactionId) {
        // Show loading state
        const modal = new bootstrap.Modal(document.getElementById('detailModal'));
        document.getElementById('detailContent').innerHTML = 'Loading...';
        modal.show();

        // Fetch transaction details
        fetch(`get_transaction_detail.php?id=${transactionId}`)
            .then(response => response.text())
            .then(data => {
                document.getElementById('detailContent').innerHTML = data;
            })
            .catch(error => {
                document.getElementById('detailContent').innerHTML = 'Error loading details';
            });
    }

    function applyFilter() {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        const search = document.getElementById('searchInput').value;
        
        let url = new URL(window.location.href);
        url.searchParams.set('start_date', startDate);
        url.searchParams.set('end_date', endDate);
        if (search) {
            url.searchParams.set('search', search);
        } else {
            url.searchParams.delete('search');
        }
        
        window.location.href = url.toString();
    }

    function deleteTransaction(id) {
        Swal.fire({
            title: 'Hapus Transaksi?',
            text: "Data yang dihapus tidak dapat dikembalikan!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading
                Swal.fire({
                    title: 'Menghapus...',
                    text: 'Mohon tunggu sebentar',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Send delete request
                fetch('delete_transaction.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Terhapus!',
                            text: data.message,
                            showConfirmButton: false,
                            timer: 1500
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal!',
                            text: data.message || 'Terjadi kesalahan saat menghapus data'
                        });
                    }
                })
                .catch(error => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Terjadi kesalahan pada server'
                    });
                });
            }
        });
    }

    function toggleSelectAll() {
        const selectAllCheckbox = document.getElementById('selectAll');
        const rowCheckboxes = document.getElementsByClassName('row-checkbox');
        
        Array.from(rowCheckboxes).forEach(checkbox => {
            checkbox.checked = selectAllCheckbox.checked;
        });
        
        toggleDeleteButton();
    }

    function toggleDeleteButton() {
        const rowCheckboxes = document.getElementsByClassName('row-checkbox');
        const checkedBoxes = Array.from(rowCheckboxes).filter(cb => cb.checked);
        const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
        
        bulkDeleteBtn.style.display = checkedBoxes.length > 0 ? 'block' : 'none';
    }

    function deleteSelected() {
        const selectedIds = Array.from(document.getElementsByClassName('row-checkbox'))
            .filter(cb => cb.checked)
            .map(cb => cb.value);
        
        if (selectedIds.length === 0) return;
        
        Swal.fire({
            title: 'Hapus Transaksi Terpilih?',
            text: `${selectedIds.length} data yang dipilih akan dihapus dan tidak dapat dikembalikan!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus Semua!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading
                Swal.fire({
                    title: 'Menghapus...',
                    text: 'Mohon tunggu sebentar',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Send delete request
                fetch('delete_transaction.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'bulk_delete',
                        ids: selectedIds
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Terhapus!',
                            text: data.message,
                            showConfirmButton: false,
                            timer: 1500
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal!',
                            text: data.message || 'Terjadi kesalahan saat menghapus data'
                        });
                    }
                })
                .catch(error => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Terjadi kesalahan pada server'
                    });
                });
            }
        });
    }
    </script>
</body>
</html> 