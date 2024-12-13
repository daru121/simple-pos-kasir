<?php
require_once '../backend/check_session.php';
require_once '../backend/database.php';

if (!isset($_GET['id'])) {
    echo "Invalid request";
    exit;
}

try {
    // Get transaction details with buyer name
    $stmt = $conn->prepare("
        SELECT 
            t.*,
            dt.jumlah,
            dt.harga as harga_jual,
            b.nama_barang,
            b.harga_modal,
            (dt.jumlah * (dt.harga - b.harga_modal)) as item_profit,
            p.nama as buyer_name
        FROM transaksi t
        JOIN detail_transaksi dt ON t.id = dt.transaksi_id
        JOIN barang b ON dt.barang_id = b.id
        LEFT JOIN pembeli p ON t.pembeli_id = p.id
        WHERE t.id = ?
    ");
    $stmt->execute([$_GET['id']]);
    $details = $stmt->fetchAll();

    if (count($details) > 0) {
        $transaction = $details[0];
        ?>
        <div class="transaction-details">
            <div class="mb-3">
                <h6>Informasi Transaksi</h6>
                <table class="table table-sm">
                    <tr>
                        <td>Nama Pembeli</td>
                        <td>: <?= htmlspecialchars($transaction['buyer_name']) ?></td>
                    </tr>
                    <tr>
                        <td>Tanggal</td>
                        <td>: <?= date('d/m/Y H:i', strtotime($transaction['tanggal'])) ?></td>
                    </tr>
                    <tr>
                        <td>Total Pembayaran</td>
                        <td>: Rp <?= number_format($transaction['total_harga'], 0, ',', '.') ?></td>
                    </tr>
                </table>
            </div>

            <h6>Detail Barang</h6>
            <table class="table table-bordered table-sm">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Barang</th>
                        <th>Jumlah</th>
                        <th>Harga</th>
                        <th>Subtotal</th>
                        <th>Profit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    foreach($details as $detail): 
                        $subtotal = $detail['jumlah'] * $detail['harga_jual'];
                    ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= htmlspecialchars($detail['nama_barang']) ?></td>
                        <td><?= $detail['jumlah'] ?></td>
                        <td>Rp <?= number_format($detail['harga_jual'], 0, ',', '.') ?></td>
                        <td>Rp <?= number_format($subtotal, 0, ',', '.') ?></td>
                        <td>Rp <?= number_format($detail['item_profit'], 0, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    } else {
        echo "Transaction not found";
    }
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 