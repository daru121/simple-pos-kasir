<?php
require_once '../backend/check_session.php';
include '../backend/database.php';

try {
    // Mengambil data user yang sedang login
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    // Mengambil data barang untuk dropdown
    $stmt_barang = $conn->prepare("SELECT * FROM barang");
    $stmt_barang->execute();
    $barangs = $stmt_barang->fetchAll();

    // Inisialisasi query pencarian
    $search = isset($_GET['search']) ? $_GET['search'] : '';

    // Ubah query untuk mengambil data stok
    $query = "SELECT 
                b.id as barang_id,
                b.nama_barang, 
                k.nama_kategori,
                b.harga,
                COALESCE(SUM(s.jumlah), 0) as total_stok
              FROM barang b
              LEFT JOIN kategori k ON b.kategori_id = k.id
              LEFT JOIN stok s ON b.id = s.barang_id
              GROUP BY b.id, b.nama_barang, k.nama_kategori, b.harga";

    // Tambahkan kondisi pencarian jika ada
    if (!empty($search)) {
        $query = "SELECT 
                    b.id as barang_id,
                    b.nama_barang, 
                    k.nama_kategori,
                    b.harga,
                    COALESCE(SUM(s.jumlah), 0) as total_stok
                  FROM barang b
                  LEFT JOIN kategori k ON b.kategori_id = k.id
                  LEFT JOIN stok s ON b.id = s.barang_id
                  WHERE b.nama_barang LIKE :search 
                     OR k.nama_kategori LIKE :search 
                     OR b.harga LIKE :search
                  GROUP BY b.id, b.nama_barang, k.nama_kategori, b.harga";
    }

    // Konfigurasi pagination
    $records_per_page = isset($_GET['show']) ? (int)$_GET['show'] : 10; // Default 10 entries
    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($current_page - 1) * $records_per_page;

    // Modifikasi query untuk mendapatkan total records
    $count_query = "SELECT COUNT(*) as total FROM barang";
    if (!empty($search)) {
        $count_query .= " WHERE barang.nama_barang LIKE :search";
    }
    $count_stmt = $conn->prepare($count_query);
    if (!empty($search)) {
        $count_stmt->bindParam(':search', $search);
    }
    $count_stmt->execute();
    $total_records = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_records / $records_per_page);

    // Modifikasi query utama untuk pagination
    $query .= " LIMIT :offset, :records_per_page";
    $stmt = $conn->prepare($query);
    if (!empty($search)) {
        $stmt->bindParam(':search', $search);
    }
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':records_per_page', $records_per_page, PDO::PARAM_INT);

    $stmt->execute();
    $stoks = $stmt->fetchAll();

    // Proses tambah stok
    if (isset($_POST['tambah_stok'])) {
        $barang_id = $_POST['barang_id'];
        $jumlah = $_POST['jumlah'];

        try {
            $stmt = $conn->prepare("INSERT INTO stok (barang_id, jumlah) VALUES (?, ?)");
            $stmt->execute([$barang_id, $jumlah]);
            header("Location: stok.php?success=1");
            exit();
        } catch (PDOException $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }

    // Proses edit stok
    if (isset($_POST['edit_stok'])) {
        $barang_id = $_POST['barang_id'];
        $jumlah = $_POST['jumlah'];

        try {
            // Hapus semua stok lama
            $stmt = $conn->prepare("DELETE FROM stok WHERE barang_id = ?");
            $stmt->execute([$barang_id]);

            // Tambah stok baru
            if ($jumlah != 0) {
                $stmt = $conn->prepare("INSERT INTO stok (barang_id, jumlah) VALUES (?, ?)");
                $stmt->execute([$barang_id, $jumlah]);
            }

            header("Location: stok.php?success=2");
            exit();
        } catch (PDOException $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }

    // Proses hapus stok
    if (isset($_POST['hapus_stok'])) {
        $barang_id = $_POST['barang_id'];

        try {
            $stmt = $conn->prepare("DELETE FROM stok WHERE barang_id = ?");
            $stmt->execute([$barang_id]);
            header("Location: stok.php?success=3");
            exit();
        } catch (PDOException $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SimplePOS - Operator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
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
                <!-- Header Section -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Stok Barang</h2>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#tambahStokModal">
                        <i class="bi bi-plus-circle me-2"></i>Tambah Data
                    </button>
                </div>

                <!-- Table Controls -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex gap-2">
                        <button class="btn btn-secondary btn-sm" onclick="exportToExcel()">
                            <i class="bi bi-file-earmark-excel me-1"></i>Excel
                        </button>
                        <button class="btn btn-secondary btn-sm" onclick="copyToClipboard()">
                            <i class="bi bi-clipboard me-1"></i>Copy
                        </button>
                        <button class="btn btn-secondary btn-sm" onclick="exportToPDF()">
                            <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                        </button>
                        <button class="btn btn-secondary btn-sm" onclick="printTable()">
                            <i class="bi bi-printer me-1"></i>Print
                        </button>
                        <div class="d-flex align-items-center">
                            <span class="me-2">Show</span>
                            <select class="form-select form-select-sm" style="width: auto;" onchange="changeEntriesPerPage(this.value)">
                                <option value="10" <?php echo $records_per_page == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="25" <?php echo $records_per_page == 25 ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo $records_per_page == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $records_per_page == 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                            <span class="ms-2">entries</span>
                        </div>
                    </div>
                    <!-- Update bagian search -->
                    <div class="d-flex align-items-center">
                        <label class="me-2">Search:</label>
                        <form action="" method="GET" class="d-flex">
                            <input type="search" name="search" class="form-control form-control-sm"
                                value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                                placeholder="Cari barang...">
                            <button type="submit" class="btn btn-primary btn-sm ms-2">Cari</button>
                            <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                                <a href="stok.php" class="btn btn-secondary btn-sm ms-2">Reset</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Main Table -->
                <div class="table-responsive">
                    <table class="table table-bordered" id="dataTable">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Barang</th>
                                <th>Kategori Barang</th>
                                <th>Harga Modal</th>
                                <th>Stok Barang</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            foreach ($stoks as $stok):
                            ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><?php echo htmlspecialchars($stok['nama_barang']); ?></td>
                                    <td><?php echo htmlspecialchars($stok['nama_kategori']); ?></td>
                                    <td>Rp <?php echo number_format($stok['harga'], 0, ',', '.'); ?></td>
                                    <td><?php echo $stok['total_stok']; ?></td>
                                    <td>
                                        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editStokModal<?php echo $stok['barang_id']; ?>">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </button>
                                        <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#hapusStokModal<?php echo $stok['barang_id']; ?>">
                                            <i class="bi bi-trash"></i> Hapus
                                        </button>
                                    </td>
                                </tr>

                                <!-- Modal Edit -->
                                <div class="modal fade" id="editStokModal<?php echo $stok['barang_id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Stok</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <input type="hidden" name="barang_id" value="<?php echo $stok['barang_id']; ?>">
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">Nama Barang</label>
                                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($stok['nama_barang']); ?>" readonly>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Jumlah Stok</label>
                                                        <input type="number" class="form-control" name="jumlah" value="<?php echo $stok['total_stok']; ?>" required>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" name="edit_stok" class="btn btn-success">Simpan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Modal Hapus -->
                                <div class="modal fade" id="hapusStokModal<?php echo $stok['barang_id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Hapus Stok</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <input type="hidden" name="barang_id" value="<?php echo $stok['barang_id']; ?>">
                                                <div class="modal-body">
                                                    <p>Apakah Anda yakin ingin menghapus stok barang "<?php echo htmlspecialchars($stok['nama_barang']); ?>"?</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" name="hapus_stok" class="btn btn-danger">Hapus</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <?php
                        $start = $total_records > 0 ? $offset + 1 : 0;
                        $end = min($offset + $records_per_page, $total_records);
                        echo "Showing $start to $end of $total_records entries";
                        if (!empty($search)) {
                            echo " (filtered from $total_records total entries)";
                        }
                        ?>
                    </div>
                    <nav>
                        <ul class="pagination">
                            <?php if ($current_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo ($current_page - 1); ?>&show=<?php echo $records_per_page; ?><?php echo !empty($search) ? '&search=' . $search : ''; ?>">Previous</a>
                                </li>
                            <?php else: ?>
                                <li class="page-item disabled">
                                    <span class="page-link">Previous</span>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&show=<?php echo $records_per_page; ?><?php echo !empty($search) ? '&search=' . $search : ''; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($current_page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo ($current_page + 1); ?>&show=<?php echo $records_per_page; ?><?php echo !empty($search) ? '&search=' . $search : ''; ?>">Next</a>
                                </li>
                            <?php else: ?>
                                <li class="page-item disabled">
                                    <span class="page-link">Next</span>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            </div>

            <!-- Modal Tambah Stok -->
            <div class="modal fade" id="tambahStokModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Tambah Stok</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Nama Barang</label>
                                    <select class="form-select" name="barang_id" required>
                                        <option value="">Pilih barang</option>
                                        <?php foreach ($barangs as $barang): ?>
                                            <option value="<?php echo $barang['id']; ?>">
                                                <?php echo htmlspecialchars($barang['nama_barang']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Jumlah Stok</label>
                                    <input type="number" class="form-control" name="jumlah" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" name="tambah_stok" class="btn btn-success">Simpan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        <script src="../assets/js/sidebar.js"></script>

        <script>
            function exportToExcel() {
                // Membuat workbook baru
                let csv = 'No,Nama Barang,Kategori Barang,Harga Barang,Stok Barang,Tanggal\n';

                const rows = document.querySelectorAll('#dataTable tbody tr');
                rows.forEach((row) => {
                    let rowData = [];
                    // Hanya ambil 6 kolom pertama
                    for (let i = 0; i < 6; i++) {
                        // Bersihkan data dari koma untuk menghindari konflik CSV
                        let cellData = row.cells[i].textContent.trim().replace(/,/g, ' ');
                        rowData.push(cellData);
                    }
                    csv += rowData.join(',') + '\n';
                });

                // Buat file dan download
                const blob = new Blob([csv], {
                    type: 'text/csv;charset=utf-8;'
                });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.setAttribute('download', 'data_stok.csv');
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }

            function copyToClipboard() {
                const rows = document.querySelectorAll('#dataTable tbody tr');
                let text = 'No\tNama Barang\tKategori Barang\tHarga Barang\tStok Barang\tTanggal\n';

                rows.forEach((row) => {
                    let rowData = [];
                    for (let i = 0; i < 6; i++) {
                        rowData.push(row.cells[i].textContent.trim());
                    }
                    text += rowData.join('\t') + '\n';
                });

                navigator.clipboard.writeText(text)
                    .then(() => alert('Data berhasil disalin ke clipboard!'))
                    .catch(err => console.error('Gagal menyalin data:', err));
            }

            function printTable() {
                const printContent = document.createElement('div');
                printContent.innerHTML = `
                    <style>
                        table { border-collapse: collapse; width: 100%; }
                        th, td { border: 1px solid black; padding: 8px; text-align: left; }
                        th { background-color: #f2f2f2; }
                        @media print { @page { margin: 1cm; } }
                    </style>
                    <h2 style="text-align: center;">Data Stok Barang</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Barang</th>
                                <th>Kategori Barang</th>
                                <th>Harga Barang</th>
                                <th>Stok Barang</th>
                                <th>Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${Array.from(document.querySelectorAll('#dataTable tbody tr')).map(row => `
                                <tr>
                                    ${Array.from(row.cells).slice(0, 6).map(cell => `
                                        <td>${cell.textContent.trim()}</td>
                                    `).join('')}
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;

                const originalContent = document.body.innerHTML;
                document.body.innerHTML = printContent.innerHTML;
                window.print();
                document.body.innerHTML = originalContent;
                location.reload();
            }

            function exportToPDF() {
                const printContent = document.createElement('div');
                printContent.innerHTML = `
                    <style>
                        table { border-collapse: collapse; width: 100%; }
                        th, td { border: 1px solid black; padding: 8px; text-align: left; }
                        th { background-color: #f2f2f2; }
                        @media print { @page { margin: 1cm; } }
                    </style>
                    <h2 style="text-align: center;">Data Stok Barang</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Barang</th>
                                <th>Kategori Barang</th>
                                <th>Harga Barang</th>
                                <th>Stok Barang</th>
                                <th>Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${Array.from(document.querySelectorAll('#dataTable tbody tr')).map(row => `
                                <tr>
                                    ${Array.from(row.cells).slice(0, 6).map(cell => `
                                        <td>${cell.textContent.trim()}</td>
                                    `).join('')}
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;

                const originalContent = document.body.innerHTML;
                document.body.innerHTML = printContent.innerHTML;
                window.print();
                document.body.innerHTML = originalContent;
                location.reload();
            }

            function changeEntriesPerPage(value) {
                let url = new URL(window.location.href);
                url.searchParams.set('show', value);
                url.searchParams.set('page', '1'); // Reset ke halaman pertama
                window.location.href = url.toString();
            }
        </script>


</body>

</html>