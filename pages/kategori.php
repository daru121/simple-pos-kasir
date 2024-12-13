<?php
require_once '../backend/check_session.php';
include '../backend/database.php';

try {
    // Mengambil data user yang sedang login
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    // Konfigurasi pagination
    $records_per_page = isset($_GET['show']) ? (int)$_GET['show'] : 10; // Default 10 entries
    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($current_page - 1) * $records_per_page;

    // Modifikasi query untuk mendapatkan total records
    $count_query = "SELECT COUNT(*) as total FROM kategori";
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->execute();
    $total_records = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_records / $records_per_page);

    // Modifikasi query utama untuk pagination
    $stmt_kategori = $conn->prepare("SELECT * FROM kategori ORDER BY id DESC LIMIT :offset, :records_per_page");
    $stmt_kategori->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt_kategori->bindParam(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt_kategori->execute();
    $kategoris = $stmt_kategori->fetchAll();

    // Proses tambah kategori
    if (isset($_POST['tambah_kategori'])) {
        $nama_kategori = $_POST['nama_kategori'];

        $stmt = $conn->prepare("INSERT INTO kategori (nama_kategori) VALUES (?)");
        $stmt->execute([$nama_kategori]);

        header("Location: kategori.php");
        exit();
    }

    // Proses edit kategori
    if (isset($_POST['edit_kategori'])) {
        $id = $_POST['id'];
        $nama_kategori = $_POST['nama_kategori'];

        $stmt = $conn->prepare("UPDATE kategori SET nama_kategori = ? WHERE id = ?");
        $stmt->execute([$nama_kategori, $id]);

        header("Location: kategori.php");
        exit();
    }

    // Proses hapus kategori
    if (isset($_POST['hapus_kategori'])) {
        $id = $_POST['id'];

        $stmt = $conn->prepare("DELETE FROM kategori WHERE id = ?");
        $stmt->execute([$id]);

        header("Location: kategori.php");
        exit();
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
    <title>SimplePOS - Kategori</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/kategori.css">
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
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <h2>Kategori Barang</h2>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#tambahKategoriModal">
                        <i class="bi bi-plus-circle me-2"></i>Tambah Data
                    </button>
                </div>

                <!-- Card untuk tabel -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <!-- Filter dan Search -->
                        <div class="row mb-3">
                            <div class="col-md-2">
                                <div class="d-flex align-items-center">
                                    <span class="me-2">Show</span>
                                    <select class="form-select form-select-sm w-auto" onchange="changeEntriesPerPage(this.value)">
                                        <option value="10" <?php echo $records_per_page == 10 ? 'selected' : ''; ?>>10</option>
                                        <option value="25" <?php echo $records_per_page == 25 ? 'selected' : ''; ?>>25</option>
                                        <option value="50" <?php echo $records_per_page == 50 ? 'selected' : ''; ?>>50</option>
                                        <option value="100" <?php echo $records_per_page == 100 ? 'selected' : ''; ?>>100</option>
                                    </select>
                                    <span class="ms-2">entries</span>
                                </div>
                            </div>
                            <div class="col-md-3 ms-auto">
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="bi bi-search"></i>
                                    </span>
                                    <input type="text" class="form-control border-start-0" placeholder="Search...">
                                </div>
                            </div>
                        </div>

                        <!-- Tabel -->
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th><b>No</b></th>
                                        <th><b>Nama Kategori</b></th>
                                        <th class="text-end"><b>Aksi</b></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $no = 1;
                                    foreach ($kategoris as $kategori):
                                    ?>
                                        <tr>
                                            <td><?php echo $no++; ?></td>
                                            <td><?php echo $kategori['nama_kategori']; ?></td>
                                            <td class="text-end">
                                                <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editKategoriModal<?php echo $kategori['id']; ?>">
                                                    <i class="bi bi-pencil-square"></i> Edit
                                                </button>
                                                <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteKategoriModal<?php echo $kategori['id']; ?>">
                                                    <i class="bi bi-trash"></i> Hapus
                                                </button>
                                            </td>
                                        </tr>

                                        <!-- Modal Edit untuk setiap kategori -->
                                        <div class="modal fade" id="editKategoriModal<?php echo $kategori['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Edit Kategori</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="id" value="<?php echo $kategori['id']; ?>">
                                                            <div class="mb-3">
                                                                <label class="form-label">Nama Kategori</label>
                                                                <input type="text" class="form-control" name="nama_kategori" value="<?php echo $kategori['nama_kategori']; ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                            <button type="submit" name="edit_kategori" class="btn btn-success">Simpan</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Modal Delete untuk setiap kategori -->
                                        <div class="modal fade" id="deleteKategoriModal<?php echo $kategori['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Hapus Kategori</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="id" value="<?php echo $kategori['id']; ?>">
                                                            <p>Apakah Anda yakin ingin menghapus kategori "<?php echo $kategori['nama_kategori']; ?>"?</p>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                            <button type="submit" name="hapus_kategori" class="btn btn-danger">Hapus</button>
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
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div>
                                <?php
                                $start = $total_records > 0 ? $offset + 1 : 0;
                                $end = min($offset + $records_per_page, $total_records);
                                echo "Showing $start to $end of $total_records entries";
                                ?>
                            </div>
                            <nav>
                                <ul class="pagination pagination-sm mb-0">
                                    <?php if ($current_page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo ($current_page - 1); ?>&show=<?php echo $records_per_page; ?>">Previous</a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">Previous</span>
                                        </li>
                                    <?php endif; ?>

                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&show=<?php echo $records_per_page; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($current_page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo ($current_page + 1); ?>&show=<?php echo $records_per_page; ?>">Next</a>
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
        </div>

        <!-- Update Modal Tambah Kategori -->
        <div class="modal fade" id="tambahKategoriModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Tambah Kategori</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Nama Kategori</label>
                                <input type="text" class="form-control" name="nama_kategori" placeholder="Masukkan nama kategori" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" name="tambah_kategori" class="btn btn-success">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/sidebar.js"></script>
    <script>
        function changeEntriesPerPage(value) {
            let url = new URL(window.location.href);
            url.searchParams.set('show', value);
            url.searchParams.set('page', '1'); // Reset ke halaman pertama
            window.location.href = url.toString();
        }
    </script>
</body>

</html>