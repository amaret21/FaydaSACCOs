<?php
session_start();
require_once '../../db_connection.php';

// Check if user is logged in and has checker role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'checker') {
    header("Location: ../../login.html?error=Unauthorized access");
    exit();
}

try {
    $db = new Database();
    $conn = $db->connect();
    
    // Get pending deposits
    $depositStmt = $conn->query("
        SELECT d.*, m.first_name, m.last_name 
        FROM deposits d
        JOIN members m ON d.member_id = m.member_id
        WHERE d.status = 'pending'
        ORDER BY d.created_at DESC
    ");
    $pendingDeposits = $depositStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process approval/rejection
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $depositId = $_POST['deposit_id'];
        $action = $_POST['action'];
        
        $updateStmt = $conn->prepare("
            UPDATE deposits 
            SET status = :status, approved_by = :approved_by 
            WHERE deposit_id = :deposit_id
        ");
        
        $status = ($action === 'approve') ? 'approved' : 'rejected';
        $updateStmt->bindParam(':status', $status);
        $updateStmt->bindParam(':approved_by', $_SESSION['user_id']);
        $updateStmt->bindParam(':deposit_id', $depositId);
        $updateStmt->execute();
        
        if ($action === 'approve') {
            // Update member's balance if approved
            $balanceStmt = $conn->prepare("
                UPDATE members m
                JOIN deposits d ON m.member_id = d.member_id
                SET m.share_amount = m.share_amount + d.credit
                WHERE d.deposit_id = :deposit_id
            ");
            $balanceStmt->bindParam(':deposit_id', $depositId);
            $balanceStmt->execute();
        }
        
        header("Location: approve_deposits.php?success=Deposit " . $status . " successfully");
        exit();
    }
    
} catch (Exception $e) {
    error_log("Deposit approval error: " . $e->getMessage());
    $error = "Error processing deposit approval";
}
?>