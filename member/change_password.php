<?php
session_start();
require_once '../db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.html?error=Please login first");
    exit();
}

// Initialize variables
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    try {
        $db = new Database();
        $conn = $db->connect();
        
        // Verify current password
        $userStmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = :user_id");
        $userStmt->bindParam(':user_id', $_SESSION['user_id']);
        $userStmt->execute();
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            $error = "Current password is incorrect";
        } elseif ($newPassword !== $confirmPassword) {
            $error = "New passwords do not match";
        } elseif (strlen($newPassword) < 8) {
            $error = "Password must be at least 8 characters";
        } else {
            // Update password
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $conn->prepare("UPDATE users SET password_hash = :password_hash WHERE user_id = :user_id");
            $updateStmt->bindParam(':password_hash', $newHash);
            $updateStmt->bindParam(':user_id', $_SESSION['user_id']);
            $updateStmt->execute();
            
            $success = "Password changed successfully!";
        }
    } catch (Exception $e) {
        error_log("Password change error: " . $e->getMessage());
        $error = "Error changing password. Please try again.";
    }
}

// Redirect back to profile page with status
if ($success) {
    header("Location: profile.php?success=" . urlencode($success));
} else {
    header("Location: profile.php?error=" . urlencode($error));
}
exit();
?>