<?php
session_start();
require_once 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    try {
        $db = new Database();
        $conn = $db->connect();
        
        // Prepare SQL statement
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        if ($stmt->rowCount() === 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verify password
            if (password_verify($password, $user['password_hash'])) {
                // Password is correct, start session
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                // Update last login time
                $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = :user_id");
                $updateStmt->bindParam(':user_id', $user['user_id']);
                $updateStmt->execute();
                
                // Redirect based on role
                switch ($user['role']) {
                    case 'admin':
                        header("Location: admin/dashboard.php");
                        break;
                    case 'checker':
                        header("Location: checker/dashboard.php");
                        break;
                    case 'maker':
                        header("Location: maker/dashboard.php");
                        break;
                    case 'guest':
                        header("Location: member/dashboard.php");
                        break;
                    default:
                        header("Location: index.html");
                }
                exit();
            } else {
                // Invalid password
                header("Location: login.html?error=Invalid username or password");
                exit();
            }
        } else {
            // User not found
            header("Location: login.html?error=Invalid username or password");
            exit();
        }
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        header("Location: login.html?error=An error occurred. Please try again later.");
        exit();
    }
} else {
    header("Location: login.html");
    exit();
}
?>