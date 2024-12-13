<?php
require_once '../backend/check_session.php';
include '../backend/database.php';

try {
    // Mengambil data user yang sedang login
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    // Inisialisasi query pencarian
    $search = isset($_GET['search']) ? $_GET['search'] : '';

    // Mengambil data operator/users dengan pencarian
    $query = "SELECT id, nama, email, role, status FROM users";

    // Tambahkan kondisi pencarian jika ada
    if (!empty($search)) {
        $query .= " WHERE nama LIKE :search";
        $search = "%$search%";
    }

    $query .= " ORDER BY id DESC";

    // Konfigurasi pagination
    $records_per_page = isset($_GET['show']) ? (int)$_GET['show'] : 10; // Default 10 entries
    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($current_page - 1) * $records_per_page;

    // Modifikasi query untuk mendapatkan total records
    $count_query = "SELECT COUNT(*) as total FROM users";
    if (!empty($search)) {
        $count_query .= " WHERE nama LIKE :search";
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
    $operators = $stmt->fetchAll();

    // Proses tambah operator
    if (isset($_POST['tambah_operator'])) {
        $nama = $_POST['nama'];
        $email = $_POST['email'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $_POST['role'];
        $status = $_POST['status'];

        try {
            $stmt = $conn->prepare("INSERT INTO users (nama, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$nama, $email, $password, $role, $status]);
            header("Location: operator.php?success=1");
            exit();
        } catch (PDOException $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }

    // Proses edit operator
    if (isset($_POST['edit_operator'])) {
        $id = $_POST['id'];
        $nama = $_POST['nama'];
        $email = $_POST['email'];
        $role = $_POST['role'];
        $status = $_POST['status'];

        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET nama = ?, email = ?, password = ?, role = ?, status = ? WHERE id = ?");
            $stmt->execute([$nama, $email, $password, $role, $status, $id]);
        } else {
            $stmt = $conn->prepare("UPDATE users SET nama = ?, email = ?, role = ?, status = ? WHERE id = ?");
            $stmt->execute([$nama, $email, $role, $status, $id]);
        }

        header("Location: operator.php?success=2");
        exit();
    }

    // Proses hapus operator
    if (isset($_POST['hapus_operator'])) {
        $id = $_POST['id'];

        try {
            // Pastikan tidak menghapus diri sendiri
            if ($id != $_SESSION['user_id']) {
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                header("Location: operator.php?success=3");
                exit();
            } else {
                $_SESSION['error'] = "Tidak dapat menghapus akun yang sedang digunakan!";
                header("Location: operator.php?error=1");
                exit();
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
            header("Location: operator.php?error=2");
            exit();
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
                    <h2>Data Operator</h2>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#tambahOperatorModal">
                        <i class="bi bi-plus-circle me-2"></i>Tambah Operator
                    </button>
                </div>

                <!-- Table Controls -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex gap-2">
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
                            <input type="search" name="search" class="form-control form-control-sm"
                                value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                                placeholder="Cari operator...">
                            <button type="submit" class="btn btn-primary btn-sm ms-2">Cari</button>
                            <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                                <a href="operator.php" class="btn btn-secondary btn-sm ms-2">Reset</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Main Table -->
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Lengkap</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            foreach ($operators as $operator):
                            ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><?php echo htmlspecialchars($operator['nama']); ?></td>
                                    <td><?php echo htmlspecialchars($operator['email']); ?></td>
                                    <td><?php echo htmlspecialchars($operator['role']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $operator['status'] == 'Aktif' ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo htmlspecialchars($operator['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editOperatorModal<?php echo $operator['id']; ?>">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </button>
                                        <?php if ($operator['id'] != $_SESSION['user_id']): ?>
                                            <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#hapusOperatorModal<?php echo $operator['id']; ?>">
                                                <i class="bi bi-trash"></i> Hapus
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <!-- Modal Edit -->
                                <div class="modal fade" id="editOperatorModal<?php echo $operator['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Operator</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <input type="hidden" name="id" value="<?php echo $operator['id']; ?>">
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">Nama Lengkap</label>
                                                        <input type="text" class="form-control" name="nama" value="<?php echo htmlspecialchars($operator['nama']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Email</label>
                                                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($operator['email']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Password</label>
                                                        <input type="password" class="form-control" name="password" placeholder="Kosongkan jika tidak ingin mengubah password">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Role</label>
                                                        <select class="form-select" name="role" required>
                                                            <option value="Admin" <?php echo $operator['role'] == 'Admin' ? 'selected' : ''; ?>>Admin</option>
                                                            <option value="Operator" <?php echo $operator['role'] == 'Operator' ? 'selected' : ''; ?>>Operator</option>
                                                            <option value="Kasir" <?php echo $operator['role'] == 'Kasir' ? 'selected' : ''; ?>>Kasir</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Status</label>
                                                        <select class="form-select" name="status" required>
                                                            <option value="Aktif" <?php echo $operator['status'] == 'Aktif' ? 'selected' : ''; ?>>Aktif</option>
                                                            <option value="Tidak Aktif" <?php echo $operator['status'] == 'Tidak Aktif' ? 'selected' : ''; ?>>Tidak Aktif</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" name="edit_operator" class="btn btn-success">Simpan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Modal Tambah -->
                                <div class="modal fade" id="tambahOperatorModal" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Tambah Operator</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">Nama Lengkap</label>
                                                        <input type="text" class="form-control" name="nama" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Email</label>
                                                        <input type="email" class="form-control" name="email" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Password</label>
                                                        <input type="password" class="form-control" name="password" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Role</label>
                                                        <select class="form-select" name="role" required>
                                                            <option value="Admin">Admin</option>
                                                            <option value="Operator" selected>Operator</option>
                                                            <option value="Kasir">Kasir</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Status</label>
                                                        <select class="form-select" name="status" required>
                                                            <option value="Aktif" selected>Aktif</option>
                                                            <option value="Tidak Aktif">Tidak Aktif</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" name="tambah_operator" class="btn btn-success">Simpan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Modal Hapus -->
                                <div class="modal fade" id="hapusOperatorModal<?php echo $operator['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Konfirmasi Hapus</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Apakah Anda yakin ingin menghapus operator <strong><?php echo htmlspecialchars($operator['nama']); ?></strong>?</p>
                                            </div>
                                            <div class="modal-footer">
                                                <form method="POST">
                                                    <input type="hidden" name="id" value="<?php echo $operator['id']; ?>">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" name="hapus_operator" class="btn btn-danger">Hapus</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination info dan controls -->
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

            <!-- Modal Tambah Operator -->
            <div class="modal fade" id="tambahOperatorModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Tambah Operator</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Nama Lengkap</label>
                                    <input type="text" class="form-control" name="nama" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Password</label>
                                    <input type="password" class="form-control" name="password" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Role</label>
                                    <select class="form-select" name="role" required>
                                        <option value="Admin">Admin</option>
                                        <option value="Operator" selected>Operator</option>
                                        <option value="Kasir">Kasir</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status" required>
                                        <option value="Aktif" selected>Aktif</option>
                                        <option value="Tidak Aktif">Tidak Aktif</option>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" name="tambah_operator" class="btn btn-success">Simpan</button>
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