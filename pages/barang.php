<?php
require_once '../backend/check_session.php';
include '../backend/database.php';

try {
    // Mengambil data user yang sedang login
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    // Mengambil data kategori untuk dropdown
    $stmt_kategori = $conn->prepare("SELECT * FROM kategori");
    $stmt_kategori->execute();
    $kategoris = $stmt_kategori->fetchAll();

    // Inisialisasi query pencarian
    $search = isset($_GET['search']) ? $_GET['search'] : '';

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

    // Mengambil data barang dengan pencarian
    $query = "SELECT barang.*, kategori.nama_kategori 
             FROM barang 
             LEFT JOIN kategori ON barang.kategori_id = kategori.id";

    // Tambahkan kondisi pencarian jika ada
    if (!empty($search)) {
        $query .= " WHERE barang.nama_barang LIKE :search";
        $search = "%$search%";
    }

    $query .= " ORDER BY barang.id DESC";

    $query .= " LIMIT :offset, :records_per_page";
    $stmt_barang = $conn->prepare($query);

    // Bind parameter pencarian jika ada
    if (!empty($search)) {
        $stmt_barang->bindParam(':search', $search);
    }
    $stmt_barang->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt_barang->bindParam(':records_per_page', $records_per_page, PDO::PARAM_INT);

    $stmt_barang->execute();
    $barangs = $stmt_barang->fetchAll();

    // Proses hapus barang
    if (isset($_POST['hapus_barang'])) {
        $id = $_POST['id'];

        try {
            // Cek apakah barang masih terkait dengan tabel lain
            $check_stok = $conn->prepare("SELECT COUNT(*) FROM stok WHERE barang_id = ?");
            $check_stok->execute([$id]);
            $has_stok = $check_stok->fetchColumn() > 0;

            $check_transaksi = $conn->prepare("SELECT COUNT(*) FROM detail_transaksi WHERE barang_id = ?");
            $check_transaksi->execute([$id]);
            $has_transaksi = $check_transaksi->fetchColumn() > 0;

            if ($has_stok || $has_transaksi) {
                $_SESSION['error'] = "Barang tidak dapat dihapus karena masih terkait dengan data stok atau transaksi!";
                header("Location: barang.php?error=1");
                exit();
            }

            // Jika tidak ada keterkaitan, hapus barang
            $stmt = $conn->prepare("DELETE FROM barang WHERE id = ?");
            $stmt->execute([$id]);

            $_SESSION['success'] = "Data barang berhasil dihapus!";
            header("Location: barang.php?success=3");
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
            header("Location: barang.php?error=2");
            exit();
        }
    }

    // Proses tambah barang
    if (isset($_POST['tambah_barang'])) {
        $nama_barang = $_POST['nama_barang'];
        $kategori_id = $_POST['kategori_id'];
        $harga_modal = $_POST['harga_modal'];
        $harga = $_POST['harga'];

        $stmt = $conn->prepare("INSERT INTO barang (nama_barang, kategori_id, harga_modal, harga) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nama_barang, $kategori_id, $harga_modal, $harga]);
        header("Location: barang.php");
        exit();
    }

    // Proses edit barang
    if (isset($_POST['edit_barang'])) {
        $id = $_POST['id'];
        $nama_barang = $_POST['nama_barang'];
        $kategori_id = $_POST['kategori_id'];
        $harga_modal = $_POST['harga_modal'];
        $harga = $_POST['harga'];

        try {
            $stmt = $conn->prepare("UPDATE barang SET nama_barang = ?, kategori_id = ?, harga_modal = ?, harga = ? WHERE id = ?");
            $stmt->execute([$nama_barang, $kategori_id, $harga_modal, $harga, $id]);
            header("Location: barang.php?success=2");
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
    <title>SimplePOS - Barang</title>
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
                    <h2>Data Barang</h2>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#tambahBarangModal">
                        <i class="bi bi-plus-circle me-2"></i>Tambah data
                    </button>
                </div>

                <!-- Tambahkan di bawah header -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php
                        echo $_SESSION['success'];
                        unset($_SESSION['success']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php
                        echo $_SESSION['error'];
                        unset($_SESSION['error']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Table Controls -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex gap-2">
                        <div class="btn-group" role="group">
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
                        </div>
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
                    <div class="d-flex align-items-center">
                        <label class="me-2">Search:</label>
                        <form action="" method="GET" class="d-flex">
                            <input type="search" name="search" class="form-control form-control-sm" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                            <button type="submit" class="btn btn-primary btn-sm ms-2">Cari</button>
                            <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                                <a href="barang.php" class="btn btn-secondary btn-sm ms-2">Reset</a>
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
                                <th>Harga Jual</th>
                                <th>Profit</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            foreach ($barangs as $barang):
                                // Hitung profit
                                $profit = $barang['harga'] - $barang['harga_modal'];
                            ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><?php echo $barang['nama_barang']; ?></td>
                                    <td><?php echo $barang['nama_kategori']; ?></td>
                                    <td>Rp <?php echo number_format($barang['harga_modal'], 0, ',', '.'); ?></td>
                                    <td>Rp <?php echo number_format($barang['harga'], 0, ',', '.'); ?></td>
                                    <td>Rp <?php echo number_format($profit, 0, ',', '.'); ?></td>
                                    <td>
                                        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editBarangModal<?php echo $barang['id']; ?>">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </button>
                                        <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#hapusBarangModal<?php echo $barang['id']; ?>">
                                            <i class="bi bi-trash"></i> Hapus
                                        </button>
                                    </td>
                                </tr>

                                <!-- Modal Edit untuk setiap barang -->
                                <div class="modal fade" id="editBarangModal<?php echo $barang['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Barang</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <input type="hidden" name="id" value="<?php echo $barang['id']; ?>">
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">Nama Barang</label>
                                                        <input type="text" class="form-control" name="nama_barang" value="<?php echo htmlspecialchars($barang['nama_barang']); ?>" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Kategori Barang</label>
                                                        <select class="form-select" name="kategori_id" required>
                                                            <option value="">Pilih kategori barang</option>
                                                            <?php foreach ($kategoris as $kategori): ?>
                                                                <option value="<?php echo $kategori['id']; ?>" <?php echo $barang['kategori_id'] == $kategori['id'] ? 'selected' : ''; ?>>
                                                                    <?php echo $kategori['nama_kategori']; ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Harga Modal</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text">Rp.</span>
                                                            <input type="number" class="form-control" name="harga_modal" value="<?php echo $barang['harga_modal']; ?>" required>
                                                        </div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Harga Jual</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text">Rp.</span>
                                                            <input type="number" class="form-control" name="harga" value="<?php echo $barang['harga']; ?>" required>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" name="edit_barang" class="btn btn-success">Simpan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Modal Hapus -->
                                <div class="modal fade" id="hapusBarangModal<?php echo $barang['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Hapus Barang</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <input type="hidden" name="id" value="<?php echo $barang['id']; ?>">
                                                <div class="modal-body">
                                                    <p>Apakah Anda yakin ingin menghapus barang "<?php echo htmlspecialchars($barang['nama_barang']); ?>"?</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" name="hapus_barang" class="btn btn-danger">Hapus</button>
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
        </div>
    </div>

    <!-- Modal Tambah Barang -->
    <div class="modal fade" id="tambahBarangModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Barang</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nama Barang</label>
                            <input type="text" class="form-control" name="nama_barang" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Kategori Barang</label>
                            <select class="form-select" name="kategori_id" required>
                                <option value="">Pilih kategori barang</option>
                                <?php foreach ($kategoris as $kategori): ?>
                                    <option value="<?php echo $kategori['id']; ?>"><?php echo $kategori['nama_kategori']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Harga Modal</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp.</span>
                                <input type="number" class="form-control" name="harga_modal" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Harga Jual</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp.</span>
                                <input type="number" class="form-control" name="harga" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="tambah_barang" class="btn btn-success">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/sidebar.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Script untuk fungsi export -->
    <script>
        function exportToExcel() {
            var data = [];

            // Menambahkan header
            data.push(['No', 'Nama Barang', 'Kategori Barang', 'Harga Modal', 'Harga Jual', 'Profit']);

            // Menambahkan data baris
            var rows = document.querySelectorAll('#dataTable tbody tr');
            rows.forEach(function(row) {
                var rowData = [];
                // Mengambil 6 kolom (termasuk profit, tanpa kolom aksi)
                for (var i = 0; i < 6; i++) {
                    rowData.push(row.cells[i].textContent.trim());
                }
                data.push(rowData);
            });

            // Convert ke CSV
            var csv = data.map(row => row.join(',')).join('\n');

            // Download file
            var blob = new Blob([csv], {
                type: 'text/csv;charset=utf-8;'
            });
            var link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'data_barang.csv';
            link.click();
        }

        function copyToClipboard() {
            var data = [];

            // Header
            data.push(['No', 'Nama Barang', 'Kategori Barang', 'Harga Modal', 'Harga Jual', 'Profit'].join('\t'));

            // Data
            var rows = document.querySelectorAll('#dataTable tbody tr');
            rows.forEach(function(row) {
                var rowData = [];
                for (var i = 0; i < 6; i++) {
                    rowData.push(row.cells[i].textContent.trim());
                }
                data.push(rowData.join('\t'));
            });

            var textToCopy = data.join('\n');

            navigator.clipboard.writeText(textToCopy).then(function() {
                alert('Data telah disalin ke clipboard!');
            }).catch(function(err) {
                console.error('Gagal menyalin: ', err);
            });
        }

        function exportToPDF() {
            var data = [];

            // Mengambil data dari tabel
            var rows = document.querySelectorAll('#dataTable tbody tr');
            rows.forEach(function(row) {
                var rowData = [];
                // Hanya mengambil 4 kolom pertama
                for (var i = 0; i < 6; i++) {
                    rowData.push(row.cells[i].textContent.trim());
                }
                data.push(rowData);
            });

            // Membuat struktur dokumen PDF
            var docDefinition = {
                content: [{
                        text: 'Data Barang',
                        style: 'header'
                    },
                    {
                        table: {
                            headerRows: 1,
                            widths: ['auto', '*', '*', 'auto', 'auto', 'auto'],
                            body: [
                                ['No', 'Nama Barang', 'Kategori Barang', 'Harga Modal', 'Harga Jual', 'Profit'],
                                ...data
                            ]
                        }
                    }
                ],
                styles: {
                    header: {
                        fontSize: 18,
                        bold: true,
                        margin: [0, 0, 0, 10]
                    }
                }
            };

            // Download PDF
            window.print();
        }

        function printTable() {
            var printDiv = document.createElement('div');
            printDiv.innerHTML = `
                <style>
                    table { border-collapse: collapse; width: 100%; }
                    th, td { border: 1px solid black; padding: 8px; text-align: left; }
                    th { background-color: #f2f2f2; }
                    @media print { @page { margin: 1cm; } }
                </style>
                <h2 style="text-align: center;">Data Barang</h2>
            `;

            var table = document.createElement('table');
            var thead = document.createElement('thead');
            var tbody = document.createElement('tbody');

            // Header
            var headerRow = document.createElement('tr');
            ['No', 'Nama Barang', 'Kategori Barang', 'Harga Modal', 'Harga Jual', 'Profit'].forEach(function(text) {
                var th = document.createElement('th');
                th.textContent = text;
                headerRow.appendChild(th);
            });
            thead.appendChild(headerRow);

            // Data
            var rows = document.querySelectorAll('#dataTable tbody tr');
            rows.forEach(function(row) {
                var newRow = document.createElement('tr');
                for (var i = 0; i < 6; i++) {
                    var td = document.createElement('td');
                    td.textContent = row.cells[i].textContent.trim();
                    newRow.appendChild(td);
                }
                tbody.appendChild(newRow);
            });

            table.appendChild(thead);
            table.appendChild(tbody);
            printDiv.appendChild(table);

            var originalContents = document.body.innerHTML;
            document.body.innerHTML = printDiv.innerHTML;
            window.print();
            document.body.innerHTML = originalContents;
            location.reload();
        }

        function changeEntriesPerPage(value) {
            let url = new URL(window.location.href);
            url.searchParams.set('show', value);
            url.searchParams.set('page', '1'); // Reset ke halaman pertama
            window.location.href = url.toString();
        }

        // Validasi harga modal tidak boleh lebih besar dari harga jual
        document.querySelector('form').addEventListener('submit', function(e) {
            const hargaModal = parseFloat(document.querySelector('input[name="harga_modal"]').value);
            const hargaJual = parseFloat(document.querySelector('input[name="harga"]').value);

            if (hargaModal >= hargaJual) {
                e.preventDefault();
                alert('Harga modal harus lebih kecil dari harga jual!');
            }
        });

        // Auto format input harga
        document.querySelectorAll('input[type="number"]').forEach(function(input) {
            input.addEventListener('input', function() {
                if (this.value < 0) this.value = 0;
            });
        });

        // Validasi form edit
        document.querySelectorAll('form').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                if (this.querySelector('input[name="harga_modal"]') && this.querySelector('input[name="harga"]')) {
                    const hargaModal = parseFloat(this.querySelector('input[name="harga_modal"]').value);
                    const hargaJual = parseFloat(this.querySelector('input[name="harga"]').value);

                    if (hargaModal >= hargaJual) {
                        e.preventDefault();
                        alert('Harga modal harus lebih kecil dari harga jual!');
                    }
                }
            });
        });
    </script>
</body>

</html>