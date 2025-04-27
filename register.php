<?php
session_start();
require_once 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect all form data
    $firstName = trim($_POST['firstName']);
    $middleName = trim($_POST['middleName'] ?? '');
    $lastName = trim($_POST['lastName']);
    $dob = $_POST['dob'];
    $gender = $_POST['gender'];
    $maritalStatus = $_POST['maritalStatus'];
    $nationality = $_POST['nationality'];
    $idType = $_POST['idType'];
    $idNumber = trim($_POST['idNumber']);
    $tin = trim($_POST['tin'] ?? '');
    $occupation = trim($_POST['occupation']);
    $city = trim($_POST['city']);
    $subCity = trim($_POST['subCity']);
    $kebele = trim($_POST['kebele']);
    $wereda = trim($_POST['wereda']);
    $houseNo = trim($_POST['houseNo']);
    $mobilePhone = trim($_POST['mobilePhone']);
    $phoneNumber = trim($_POST['phoneNumber'] ?? '');
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $shareCount = (int)$_POST['shareCount'];
    $finalPaymentDate = $_POST['finalPaymentDate'];
    
    // Auto-generate username
    $username = strtolower($firstName);
    if (!empty($middleName)) {
        $username .= substr(strtolower($middleName), 0, 1);
    }
    $username .= substr(strtolower($lastName), 0, 1);
    
    // Handle file upload
    $idPhotoPath = '';
    if (isset($_FILES['idPhoto']) && $_FILES['idPhoto']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/id_photos/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExt = pathinfo($_FILES['idPhoto']['name'], PATHINFO_EXTENSION);
        $fileName = 'id_' . $idNumber . '_' . time() . '.' . $fileExt;
        $idPhotoPath = $uploadDir . $fileName;
        
        if (!move_uploaded_file($_FILES['idPhoto']['tmp_name'], $idPhotoPath)) {
            header("Location: register.html?error=Failed to upload ID photo");
            exit();
        }
    } else {
        header("Location: register.html?error=ID photo is required");
        exit();
    }
    
    try {
        $db = new Database();
        $conn = $db->connect();
        
        // Begin transaction
        $conn->beginTransaction();
        
        // Create user account
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $userStmt = $conn->prepare("
            INSERT INTO users (username, password_hash, role, is_active) 
            VALUES (:username, :password_hash, 'guest', 1)
        ");
        $userStmt->bindParam(':username', $username);
        $userStmt->bindParam(':password_hash', $passwordHash);
        $userStmt->execute();
        $userId = $conn->lastInsertId();
        
        // Create member record
        $memberStmt = $conn->prepare("
            INSERT INTO members (
                user_id, first_name, middle_name, last_name, date_of_birth, id_number, id_type, 
                gender, marital_status, nationality, tin, occupation, city, sub_city, kebele, 
                wereda, house_no, mobile_phone, phone_number, share_count, share_amount, final_payment_date
            ) VALUES (
                :user_id, :first_name, :middle_name, :last_name, :date_of_birth, :id_number, :id_type, 
                :gender, :marital_status, :nationality, :tin, :occupation, :city, :sub_city, :kebele, 
                :wereda, :house_no, :mobile_phone, :phone_number, :share_count, :share_amount, :final_payment_date
            )
        ");
        
        $shareAmount = $shareCount * 100; // Assuming 100 ETB per share
        
        $memberStmt->bindParam(':user_id', $userId);
        $memberStmt->bindParam(':first_name', $firstName);
        $memberStmt->bindParam(':middle_name', $middleName);
        $memberStmt->bindParam(':last_name', $lastName);
        $memberStmt->bindParam(':date_of_birth', $dob);
        $memberStmt->bindParam(':id_number', $idNumber);
        $memberStmt->bindParam(':id_type', $idType);
        $memberStmt->bindParam(':gender', $gender);
        $memberStmt->bindParam(':marital_status', $maritalStatus);
        $memberStmt->bindParam(':nationality', $nationality);
        $memberStmt->bindParam(':tin', $tin);
        $memberStmt->bindParam(':occupation', $occupation);
        $memberStmt->bindParam(':city', $city);
        $memberStmt->bindParam(':sub_city', $subCity);
        $memberStmt->bindParam(':kebele', $kebele);
        $memberStmt->bindParam(':wereda', $wereda);
        $memberStmt->bindParam(':house_no', $houseNo);
        $memberStmt->bindParam(':mobile_phone', $mobilePhone);
        $memberStmt->bindParam(':phone_number', $phoneNumber);
        $memberStmt->bindParam(':share_count', $shareCount);
        $memberStmt->bindParam(':share_amount', $shareAmount);
        $memberStmt->bindParam(':final_payment_date', $finalPaymentDate);
        
        $memberStmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        // Log the user in
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = 'guest';
        
        header("Location: member/dashboard.php?success=Registration successful!");
        exit();
    } catch (PDOException $e) {
        // Roll back transaction if error occurs
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        
        // Delete uploaded file if registration failed
        if (!empty($idPhotoPath) && file_exists($idPhotoPath)) {
            unlink($idPhotoPath);
        }
        
        error_log("Registration error: " . $e->getMessage());
        
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            if (strpos($e->getMessage(), 'username') !== false) {
                header("Location: register.html?error=Username already exists");
            } elseif (strpos($e->getMessage(), 'id_number') !== false) {
                header("Location: register.html?error=ID number already registered");
            } else {
                header("Location: register.html?error=Duplicate entry detected");
            }
        } else {
            header("Location: register.html?error=Registration failed. Please try again.");
        }
        exit();
    }
} else {
    header("Location: register.html");
    exit();
}
?>