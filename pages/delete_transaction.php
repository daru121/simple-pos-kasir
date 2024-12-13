<?php
require_once '../backend/check_session.php';
require_once '../backend/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

try {
    $conn->beginTransaction();

    // Check if it's a bulk delete
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['action']) && $input['action'] === 'bulk_delete') {
        $ids = $input['ids'];
        
        foreach ($ids as $id) {
            // Delete detail transaksi
            $stmt = $conn->prepare("DELETE FROM detail_transaksi WHERE transaksi_id = ?");
            $stmt->execute([$id]);

            // Get pembeli_id
            $stmt = $conn->prepare("SELECT pembeli_id FROM transaksi WHERE id = ?");
            $stmt->execute([$id]);
            $pembeli_id = $stmt->fetch(PDO::FETCH_COLUMN);

            // Delete transaction
            $stmt = $conn->prepare("DELETE FROM transaksi WHERE id = ?");
            $stmt->execute([$id]);

            // Check and delete pembeli if no other transactions
            if ($pembeli_id) {
                $stmt = $conn->prepare("SELECT COUNT(*) FROM transaksi WHERE pembeli_id = ?");
                $stmt->execute([$pembeli_id]);
                $count = $stmt->fetch(PDO::FETCH_COLUMN);
                
                if ($count == 0) {
                    $stmt = $conn->prepare("DELETE FROM pembeli WHERE id = ?");
                    $stmt->execute([$pembeli_id]);
                }
            }
        }

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => count($ids) . ' transaksi berhasil dihapus']);
        exit;
    }

    // Single delete (existing code)
    $id = $_POST['id'];

    // Delete detail transaksi first (karena ada foreign key)
    $stmt = $conn->prepare("DELETE FROM detail_transaksi WHERE transaksi_id = ?");
    $stmt->execute([$id]);

    // Get pembeli_id before deleting transaction
    $stmt = $conn->prepare("SELECT pembeli_id FROM transaksi WHERE id = ?");
    $stmt->execute([$id]);
    $pembeli_id = $stmt->fetch(PDO::FETCH_COLUMN);

    // Delete the transaction
    $stmt = $conn->prepare("DELETE FROM transaksi WHERE id = ?");
    $stmt->execute([$id]);

    // Delete pembeli if no other transactions exist for this buyer
    if ($pembeli_id) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM transaksi WHERE pembeli_id = ?");
        $stmt->execute([$pembeli_id]);
        $count = $stmt->fetch(PDO::FETCH_COLUMN);
        
        if ($count == 0) {
            $stmt = $conn->prepare("DELETE FROM pembeli WHERE id = ?");
            $stmt->execute([$pembeli_id]);
        }
    }

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Transaksi berhasil dihapus']);
} catch (PDOException $e) {
    $conn->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?> 