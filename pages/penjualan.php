<?php
require_once '../backend/check_session.php';
require_once '../backend/database.php';

try {
    // Mengambil data user yang sedang login
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    // Mengambil data barang untuk list produk
    $query = "SELECT b.*, k.nama_kategori, 
             (SELECT SUM(jumlah) FROM stok WHERE barang_id = b.id) as total_stok 
             FROM barang b 
             LEFT JOIN kategori k ON b.kategori_id = k.id";

    // Filter berdasarkan kategori jika ada
    if (isset($_GET['kategori']) && !empty($_GET['kategori'])) {
        $query .= " WHERE b.kategori_id = :kategori_id";
    }

    // Konfigurasi pagination untuk list produk
    $records_per_page = isset($_GET['show']) ? (int)$_GET['show'] : 8; // Default 8 items per page
    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($current_page - 1) * $records_per_page;

    // Modifikasi query untuk mendapatkan total records
    $count_query = "SELECT COUNT(*) as total FROM barang b LEFT JOIN kategori k ON b.kategori_id = k.id";
    if (isset($_GET['kategori']) && !empty($_GET['kategori'])) {
        $count_query .= " WHERE b.kategori_id = :kategori_id";
    }
    $count_stmt = $conn->prepare($count_query);
    if (isset($_GET['kategori']) && !empty($_GET['kategori'])) {
        $count_stmt->bindParam(':kategori_id', $_GET['kategori']);
    }
    $count_stmt->execute();
    $total_records = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_records / $records_per_page);

    // Modifikasi query utama untuk pagination
    $query .= " LIMIT :offset, :records_per_page";
    $stmt = $conn->prepare($query);
    if (isset($_GET['kategori']) && !empty($_GET['kategori'])) {
        $stmt->bindParam(':kategori_id', $_GET['kategori']);
    }
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll();

    // Mengambil data kategori untuk filter
    $stmt = $conn->prepare("SELECT * FROM kategori");
    $stmt->execute();
    $categories = $stmt->fetchAll();

    // Handle POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');

        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_to_cart':
                    $barang_id = $_POST['barang_id'];
                    $jumlah = $_POST['jumlah'] ?? 1;

                    // Cek stok tersedia
                    $stmt = $conn->prepare("SELECT b.*, (SELECT SUM(jumlah) FROM stok WHERE barang_id = b.id) as total_stok 
                                          FROM barang b WHERE b.id = ?");
                    $stmt->execute([$barang_id]);
                    $barang = $stmt->fetch();

                    if (!$barang || $barang['total_stok'] <= 0) {
                        echo json_encode(['status' => 'error', 'message' => 'Stok tidak tersedia']);
                        exit;
                    }

                    if ($barang['total_stok'] < $jumlah) {
                        echo json_encode(['status' => 'error', 'message' => 'Stok tidak mencukupi']);
                        exit;
                    }

                    if (!isset($_SESSION['cart'])) {
                        $_SESSION['cart'] = [];
                    }

                    $found = false;
                    foreach ($_SESSION['cart'] as &$item) {
                        if ($item['barang_id'] == $barang_id) {
                            $item['jumlah'] += $jumlah;
                            $found = true;
                            break;
                        }
                    }

                    if (!$found) {
                        $_SESSION['cart'][] = [
                            'barang_id' => $barang_id,
                            'nama_barang' => $barang['nama_barang'],
                            'harga' => $barang['harga'],
                            'jumlah' => $jumlah
                        ];
                    }

                    echo json_encode(['status' => 'success']);
                    exit;
                    break;

                case 'update_cart':
                    $index = $_POST['index'];
                    $jumlah = $_POST['jumlah'];

                    if (isset($_SESSION['cart'][$index])) {
                        // Check stock availability
                        $barang_id = $_SESSION['cart'][$index]['barang_id'];
                        $stmt = $conn->prepare("SELECT (SELECT SUM(jumlah) FROM stok WHERE barang_id = ?) as total_stok");
                        $stmt->execute([$barang_id]);
                        $result = $stmt->fetch();

                        if ($jumlah > $result['total_stok']) {
                            echo json_encode([
                                'status' => 'error',
                                'message' => 'Stok tidak mencukupi. Stok tersedia: ' . $result['total_stok']
                            ]);
                            exit;
                        }

                        $_SESSION['cart'][$index]['jumlah'] = $jumlah;
                        echo json_encode(['status' => 'success']);
                    } else {
                        echo json_encode(['status' => 'error', 'message' => 'Item tidak ditemukan']);
                    }
                    exit;
                    break;

                case 'remove_from_cart':
                    $index = $_POST['index'];

                    if (isset($_SESSION['cart'][$index])) {
                        array_splice($_SESSION['cart'], $index, 1);
                        echo json_encode(['status' => 'success']);
                    } else {
                        echo json_encode(['status' => 'error', 'message' => 'Item tidak ditemukan']);
                    }
                    exit;
                    break;

                case 'process_payment':
                    try {
                        $conn->beginTransaction();

                        // Check if buyer exists or create new one
                        $buyerName = $_POST['buyer_name'];
                        $stmt = $conn->prepare("SELECT id FROM pembeli WHERE nama = ?");
                        $stmt->execute([$buyerName]);
                        $buyer = $stmt->fetch();

                        if (!$buyer) {
                            // Create new buyer
                            $stmt = $conn->prepare("INSERT INTO pembeli (nama) VALUES (?)");
                            $stmt->execute([$buyerName]);
                            $buyerId = $conn->lastInsertId();
                        } else {
                            $buyerId = $buyer['id'];
                        }

                        $payment_amount = $_POST['payment_amount'];
                        $total = $_POST['total'];
                        $kembalian = $payment_amount - $total;

                        // Insert transaksi with buyer_id
                        $stmt = $conn->prepare("INSERT INTO transaksi (user_id, pembeli_id, total_harga, pembayaran, kembalian, tanggal) 
                                             VALUES (?, ?, ?, ?, ?, NOW())");
                        $stmt->execute([$_SESSION['user_id'], $buyerId, $total, $payment_amount, $kembalian]);
                        $transaksi_id = $conn->lastInsertId();

                        // Insert detail transaksi dan update stok
                        foreach ($_SESSION['cart'] as $item) {
                            $stmt = $conn->prepare("INSERT INTO detail_transaksi (transaksi_id, barang_id, jumlah, harga) 
                                                 VALUES (?, ?, ?, ?)");
                            $stmt->execute([$transaksi_id, $item['barang_id'], $item['jumlah'], $item['harga']]);

                            $stmt = $conn->prepare("UPDATE stok SET jumlah = jumlah - ? WHERE barang_id = ?");
                            $stmt->execute([$item['jumlah'], $item['barang_id']]);
                        }

                        $conn->commit();
                        unset($_SESSION['cart']); // Clear cart

                        echo json_encode([
                            'status' => 'success',
                            'message' => 'Pembayaran berhasil',
                            'kembalian' => $kembalian
                        ]);
                    } catch (Exception $e) {
                        $conn->rollBack();
                        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
                    }
                    exit;
                    break;

                case 'check_stock':
                    $barang_id = $_POST['barang_id'];

                    // Get available stock
                    $stmt = $conn->prepare("SELECT b.*, 
                                           (SELECT SUM(jumlah) FROM stok WHERE barang_id = b.id) as total_stok 
                                           FROM barang b WHERE b.id = ?");
                    $stmt->execute([$barang_id]);
                    $barang = $stmt->fetch();

                    // Calculate current cart quantity for this item
                    $current_cart_qty = 0;
                    if (isset($_SESSION['cart'])) {
                        foreach ($_SESSION['cart'] as $item) {
                            if ($item['barang_id'] == $barang_id) {
                                $current_cart_qty = $item['jumlah'];
                                break;
                            }
                        }
                    }

                    echo json_encode([
                        'status' => 'success',
                        'available_stock' => (int)$barang['total_stok'],
                        'cart_qty' => $current_cart_qty
                    ]);
                    exit;
                    break;
            }
        }
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Calculate cart totals
$totalItems = 0;
$grandTotal = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $totalItems += $item['jumlah'];
        $grandTotal += ($item['harga'] * $item['jumlah']);
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SimplePOS - Penjualan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/penjualan.css">
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
                <div class="row">

                    <!-- Left Side - Kasir Section -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Kasir</h5>
                                <div class="transaction-date">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text"><i class="bi bi-calendar3"></i></span>
                                        <input type="date" class="form-control" id="transactionDate" value="<?= date('Y-m-d') ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Cart Items -->
                                <div class="cart-table-container">
                                    <?php if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0): ?>
                                        <?php foreach ($_SESSION['cart'] as $index => $item): ?>
                                            <div class="cart-item" data-index="<?php echo $index; ?>">
                                                <div class="cart-item-name">
                                                    <?php echo htmlspecialchars($item['nama_barang']); ?>
                                                </div>
                                                <div class="cart-item-qty">
                                                    <input type="number"
                                                        class="form-control form-control-sm cart-qty-input"
                                                        value="<?php echo $item['jumlah']; ?>"
                                                        min="1"
                                                        onchange="updateQuantity(<?php echo $index; ?>, this.value)">
                                                </div>
                                                <div class="cart-item-total">
                                                    Rp <?php echo number_format($item['harga'] * $item['jumlah'], 0, ',', '.'); ?>
                                                </div>
                                                <div class="cart-item-actions">
                                                    <button class="btn btn-danger btn-sm"
                                                        onclick="removeFromCart(<?php echo $index; ?>)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="cart-empty">
                                            <i class="bi bi-cart-x"></i>
                                            <p>Keranjang kosong</p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Cart Summary -->
                                <div class="cart-summary">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Total Items:</span>
                                        <span id="totalItems"><?php echo $totalItems; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-3">
                                        <span>Grand Total:</span>
                                        <span id="grandTotal">Rp <?php echo number_format($grandTotal, 0, ',', '.'); ?></span>
                                    </div>
                                </div>

                                <!-- Payment Section -->
                                <div class="payment-section">
                                    <!-- Add this new field for buyer's name -->
                                    <div class="mb-3">
                                        <label class="form-label">Nama Pembeli</label>
                                        <input type="text"
                                            class="form-control"
                                            id="buyerName"
                                            placeholder="Masukkan nama pembeli">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Jumlah Bayar</label>
                                        <div class="input-group">
                                            <span class="input-group-text">Rp</span>
                                            <input type="number"
                                                class="form-control"
                                                id="paymentAmount"
                                                placeholder="0">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Kembalian</label>
                                        <div class="payment-result" id="changeAmount">Rp 0</div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-danger flex-grow-1" onclick="cancelCart()">
                                            <i class="bi bi-x-circle me-1"></i>Cancel
                                        </button>
                                        <button class="btn btn-success flex-grow-1" onclick="processPayment()">
                                            <i class="bi bi-check-circle me-1"></i>Bayar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Side - Product List -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">List Stok Barang</h5>
                                <button class="btn btn-light btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#filterSection">
                                    <i class="bi bi-funnel me-1"></i>Filter
                                </button>
                            </div>
                            <div class="card-body">
                                <!-- Filter Section -->
                                <div class="collapse mb-3" id="filterSection">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <select class="form-select" onchange="window.location.href='?kategori=' + this.value">
                                                <option value="">Semua Kategori</option>
                                                <?php foreach ($categories as $category): ?>
                                                    <option value="<?php echo $category['id']; ?>"
                                                        <?php echo (isset($_GET['kategori']) && $_GET['kategori'] == $category['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($category['nama_kategori']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <select class="form-select form-select-sm" onchange="changeEntriesPerPage(this.value)">
                                                <option value="8" <?php echo $records_per_page == 8 ? 'selected' : ''; ?>>Show 8</option>
                                                <option value="12" <?php echo $records_per_page == 12 ? 'selected' : ''; ?>>Show 12</option>
                                                <option value="24" <?php echo $records_per_page == 24 ? 'selected' : ''; ?>>Show 24</option>
                                                <option value="48" <?php echo $records_per_page == 48 ? 'selected' : ''; ?>>Show 48</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Products Grid -->
                                <div class="row g-3">
                                    <?php foreach ($products as $product):
                                        $stockStatus = $product['total_stok'] <= 0 ? 'danger' : 'success';
                                        $stockLabel = $product['total_stok'] <= 0 ? 'Stok Habis' : 'Stok: ' . $product['total_stok'];
                                    ?>
                                        <div class="col-md-3">
                                            <div class="card h-100">
                                                <div class="card-body">
                                                    <h6 class="card-title"><?php echo htmlspecialchars($product['nama_barang']); ?></h6>
                                                    <p class="card-text">
                                                        <small class="text-muted"><?php echo htmlspecialchars($product['nama_kategori']); ?></small>
                                                        <br>
                                                        <span class="fw-bold">Rp <?php echo number_format($product['harga'], 0, ',', '.'); ?></span>
                                                    </p>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span class="badge bg-<?php echo $stockStatus; ?>">
                                                            <?php echo $stockLabel; ?>
                                                        </span>
                                                        <?php if ($product['total_stok'] > 0): ?>
                                                            <button class="btn btn-primary btn-sm"
                                                                onclick="addToCart(<?php echo $product['id']; ?>)">
                                                                <i class="bi bi-cart-plus"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <button class="btn btn-secondary btn-sm" disabled>
                                                                <i class="bi bi-cart-x"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Setelah Products Grid -->
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <div>
                                        <?php
                                        $start = $total_records > 0 ? $offset + 1 : 0;
                                        $end = min($offset + $records_per_page, $total_records);
                                        echo "Showing $start to $end of $total_records items";
                                        ?>
                                    </div>
                                    <nav>
                                        <ul class="pagination pagination-sm mb-0">
                                            <?php if ($current_page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo ($current_page - 1); ?>&show=<?php echo $records_per_page; ?><?php echo isset($_GET['kategori']) ? '&kategori=' . $_GET['kategori'] : ''; ?>">Previous</a>
                                                </li>
                                            <?php else: ?>
                                                <li class="page-item disabled">
                                                    <span class="page-link">Previous</span>
                                                </li>
                                            <?php endif; ?>

                                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                                <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $i; ?>&show=<?php echo $records_per_page; ?><?php echo isset($_GET['kategori']) ? '&kategori=' . $_GET['kategori'] : ''; ?>"><?php echo $i; ?></a>
                                                </li>
                                            <?php endfor; ?>

                                            <?php if ($current_page < $total_pages): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo ($current_page + 1); ?>&show=<?php echo $records_per_page; ?><?php echo isset($_GET['kategori']) ? '&kategori=' . $_GET['kategori'] : ''; ?>">Next</a>
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
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function addToCart(productId) {
            // First check current cart quantity for this item
            fetch('?', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=check_stock&barang_id=${productId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'error') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Tidak dapat menambahkan ke keranjang',
                            text: data.message,
                            confirmButtonText: 'OK'
                        });
                        return;
                    }

                    if (data.cart_qty >= data.available_stock) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Batas Stok',
                            text: `Stok tersedia hanya ${data.available_stock}`,
                            confirmButtonText: 'OK'
                        });
                        return;
                    }

                    // If stock is available, proceed with adding to cart
                    fetch('?', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `action=add_to_cart&barang_id=${productId}&jumlah=1`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                location.reload();
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Tidak dapat menambahkan ke keranjang',
                                    text: data.message || 'Stok tidak tersedia',
                                    confirmButtonText: 'OK'
                                });
                            }
                        });
                });
        }

        function updateQuantity(index, newQuantity) {
            fetch('?', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=update_cart&index=${index}&jumlah=${newQuantity}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        location.reload();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Tidak dapat mengubah jumlah',
                            text: data.message || 'Stok tidak mencukupi',
                            confirmButtonText: 'OK'
                        });
                        location.reload();
                    }
                });
        }

        function removeFromCart(index) {
            if (confirm('Apakah Anda yakin ingin menghapus item ini?')) {
                fetch('?', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=remove_from_cart&index=${index}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            location.reload();
                        }
                    });
            }
        }

        function processPayment() {
            const paymentAmount = parseFloat(document.getElementById('paymentAmount').value) || 0;
            const buyerName = document.getElementById('buyerName').value.trim();
            const grandTotal = <?php echo $grandTotal; ?>;
            const transactionDate = document.getElementById('transactionDate').value;

            // Validate buyer's name
            if (!buyerName) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Peringatan',
                    text: 'Masukkan nama pembeli!',
                    confirmButtonColor: '#3085d6'
                });
                return;
            }

            if (!paymentAmount) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Peringatan',
                    text: 'Masukkan jumlah pembayaran!',
                    confirmButtonColor: '#3085d6'
                });
                return;
            }

            if (paymentAmount < grandTotal) {
                Swal.fire({
                    icon: 'error',
                    title: 'Pembayaran Kurang',
                    text: 'Jumlah pembayaran kurang dari total belanja!',
                    confirmButtonColor: '#d33'
                });
                return;
            }

            // Tampilkan loading animation
            Swal.fire({
                title: 'Memproses Pembayaran',
                html: 'Mohon tunggu sebentar...',
                allowOutsideClick: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('?', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=process_payment&payment_amount=${paymentAmount}&total=${grandTotal}&transaction_date=${transactionDate}&buyer_name=${encodeURIComponent(buyerName)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Tutup loading animation
                        Swal.close();

                        // Tampilkan animasi success
                        Swal.fire({
                            icon: 'success',
                            title: 'Pembayaran Berhasil!',
                            html: `
                            <div class="payment-success-details">
                                <p>Nama Pembeli: ${buyerName}</p>
                                <p>Tanggal: ${transactionDate}</p>
                                <p>Total Belanja: ${formatCurrency(grandTotal)}</p>
                                <p>Pembayaran: ${formatCurrency(paymentAmount)}</p>
                                <p>Kembalian: ${formatCurrency(data.kembalian)}</p>
                            </div>
                        `,
                            showConfirmButton: true,
                            confirmButtonColor: '#28a745',
                            confirmButtonText: 'Selesai',
                            allowOutsideClick: false
                        }).then((result) => {
                            if (result.isConfirmed) {
                                location.reload();
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal',
                            text: data.message || 'Terjadi kesalahan saat memproses pembayaran',
                            confirmButtonColor: '#d33'
                        });
                    }
                });
        }

        // Add helper function for currency formatting
        function formatCurrency(amount) {
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(amount);
        }

        // Update kembalian saat input pembayaran berubah
        document.getElementById('paymentAmount').addEventListener('input', function() {
            const paymentAmount = parseFloat(this.value) || 0;
            const grandTotal = <?php echo $grandTotal; ?>;
            const change = paymentAmount - grandTotal;

            const changeDisplay = document.getElementById('changeAmount');
            if (change >= 0) {
                changeDisplay.textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(change);
                changeDisplay.classList.remove('text-danger');
                changeDisplay.classList.add('text-success');
            } else {
                changeDisplay.textContent = 'Pembayaran Kurang';
                changeDisplay.classList.remove('text-success');
                changeDisplay.classList.add('text-danger');
            }
        });

        // Add this function after your existing JavaScript
        function validateDate(input) {
            const selectedDate = new Date(input.value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            // Reset time part for accurate date comparison
            selectedDate.setHours(0, 0, 0, 0);

            if (selectedDate > today) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Peringatan',
                    text: 'Tanggal tidak boleh lebih dari hari ini!',
                    confirmButtonColor: '#3085d6'
                });
                input.value = today.toISOString().split('T')[0];
                return;
            }

            // Optional: Batasi tanggal mundur maksimal 7 hari
            const minDate = new Date();
            minDate.setDate(minDate.getDate() - 7);
            minDate.setHours(0, 0, 0, 0);

            if (selectedDate < minDate) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Peringatan',
                    text: 'Tanggal tidak boleh kurang dari 7 hari yang lalu!',
                    confirmButtonColor: '#3085d6'
                });
                input.value = minDate.toISOString().split('T')[0];
            }
        }

        // Update CSS untuk input tanggal yang bisa diedit
        document.addEventListener('DOMContentLoaded', function() {
            const dateInput = document.getElementById('transactionDate');
            if (dateInput) {
                dateInput.style.cursor = 'pointer';
                dateInput.style.backgroundColor = '#fff';
            }
        });

        function changeEntriesPerPage(value) {
            let url = new URL(window.location.href);
            url.searchParams.set('show', value);
            url.searchParams.set('page', '1'); // Reset ke halaman pertama
            window.location.href = url.toString();
        }
    </script>
</body>

</html>