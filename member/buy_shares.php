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

try {
    $db = new Database();
    $conn = $db->connect();
    
    // Get member details
    $memberStmt = $conn->prepare("
        SELECT m.member_id, m.first_name, m.last_name, m.share_count, m.share_amount,
               (SELECT SUM(credit) - SUM(debit) 
                FROM deposits 
                WHERE member_id = m.member_id AND status = 'approved') as balance
        FROM members m
        JOIN users u ON m.user_id = u.user_id
        WHERE u.user_id = :user_id
    ");
    $memberStmt->bindParam(':user_id', $_SESSION['user_id']);
    $memberStmt->execute();
    $member = $memberStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$member) {
        header("Location: ../login.html?error=Member not found");
        exit();
    }
    
    // Process share purchase
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $shares = (int)$_POST['shares'];
        $paymentMethod = $_POST['payment_method'];
        $transactionProof = $_FILES['transaction_proof'];
        
        // Validate shares
        if ($shares <= 0) {
            $error = "Number of shares must be greater than zero";
        } else {
            // Handle file upload
            $proofPath = '';
            if ($transactionProof['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../uploads/share_payments/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileExt = pathinfo($transactionProof['name'], PATHINFO_EXTENSION);
                $fileName = 'shares_' . $member['member_id'] . '_' . time() . '.' . $fileExt;
                $proofPath = $uploadDir . $fileName;
                
                if (!move_uploaded_file($transactionProof['tmp_name'], $proofPath)) {
                    $error = "Failed to upload transaction proof";
                }
            } else {
                $error = "Transaction proof is required";
            }
            
            if (!$error) {
                $amount = $shares * 100; // 100 ETB per share
                
                // Generate transaction ID
                $transactionId = 'SHR' . date('Ymd') . strtoupper(substr(uniqid(), -6));
                
                // Record share purchase
                $insertStmt = $conn->prepare("
                    INSERT INTO deposits (
                        member_id, transaction_date, transaction_id, 
                        credit, balance, description, payment_method, proof_path, created_by
                    ) VALUES (
                        :member_id, CURDATE(), :transaction_id, 
                        :credit, :credit, :description, :payment_method, :proof_path, :created_by
                    )
                ");
                
                $description = "Purchase of $shares shares";
                $insertStmt->bindParam(':member_id', $member['member_id']);
                $insertStmt->bindParam(':transaction_id', $transactionId);
                $insertStmt->bindParam(':credit', $amount);
                $insertStmt->bindParam(':description', $description);
                $insertStmt->bindParam(':payment_method', $paymentMethod);
                $insertStmt->bindParam(':proof_path', $proofPath);
                $insertStmt->bindParam(':created_by', $_SESSION['user_id']);
                $insertStmt->execute();
                
                $success = "Share purchase submitted successfully! It will be processed within 24 hours.";
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Share purchase error: " . $e->getMessage());
    $error = "Error processing share purchase. Please try again.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buy Shares - Fayda SACCO</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="icon" type="image/x-icon" href="../images/favicon.ico">
</head>
<body>
    <!-- Top Bar -->
    <div class="top-bar bg-primary text-white py-2">
        <div class="container">
            <div class="row">
                <div class="col-md-8">
                    <marquee>Welcome to Fayda SACCO - Buy Shares</marquee>
                </div>
                <div class="col-md-2 text-end">
                    <select id="language" class="form-select form-select-sm d-inline-block w-auto">
                        <option value="en">English</option>
                        <option value="am">አማርኛ</option>
                        <option value="om">Afaan Oromo</option>
                    </select>
                </div>
                <div class="col-md-2 text-end">
                    <span id="datetime"></span>
                    <a href="../logout.php" class="btn btn-sm btn-outline-light">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Header -->
    <header class="sticky-top">
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
            <div class="container">
                <a class="navbar-brand" href="../index.html">
                    <img src="../images/logo.png" alt="Fayda SACCO Logo" width="80" height="75" class="d-inline-block align-top">
                    <span class="logo-text">Fayda SACCO</span><br>
                    <small class="logo-slogan">Member Portal</small>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="deposits.php">My Deposits</a></li>
                        <li class="nav-item"><a class="nav-link active" href="shares.php">My Shares</a></li>
                        <li class="nav-item"><a class="nav-link" href="loans.php">Loans</a></li>
                        <li class="nav-item"><a class="nav-link" href="profile.php">Profile</a></li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Buy Additional Shares</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                            <div class="text-center">
                                <a href="shares.php" class="btn btn-primary">View My Shares</a>
                                <a href="buy_shares.php" class="btn btn-outline-primary">Buy More Shares</a>
                            </div>
                        <?php else: ?>
                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?php echo $error; ?></div>
                            <?php endif; ?>
                            
                            <div class="alert alert-info mb-4">
                                <h5><i class="fas fa-info-circle me-2"></i> Share Information</h5>
                                <p>
                                    <strong>Current Shares:</strong> <?php echo $member['share_count']; ?><br>
                                    <strong>Share Value:</strong> ETB 500 per share<br>
                                    <strong>Minimum Purchase:</strong> 3 share
                                </p>
                            </div>
                            
                            <form method="POST" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="member_id" class="form-label">Member ID</label>
                                    <input type="text" class="form-control" id="member_id" 
                                           value="<?php echo htmlspecialchars($member['member_id']); ?>" readonly>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="member_name" class="form-label">Member Name</label>
                                    <input type="text" class="form-control" id="member_name" 
                                           value="<?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>" readonly>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="shares" class="form-label">Number of Shares *</label>
                                    <input type="number" class="form-control" id="shares" name="shares" 
                                           min="1" value="1" required>
                                    <div class="form-text">Total Amount: ETB <span id="shareAmount">1000.00</span></div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="payment_method" class="form-label">Payment Method *</label>
                                    <select class="form-select" id="payment_method" name="payment_method" required>
                                        <option value="" selected disabled>Select Payment Method</option>
                                        <option value="bank">Bank Transfer</option>
                                        <option value="mobile">Mobile Money</option>
                                        <option value="cash">Cash Deposit</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="transaction_proof" class="form-label">Transaction Proof *</label>
                                    <input type="file" class="form-control" id="transaction_proof" name="transaction_proof" required>
                                    <div class="form-text">Upload screenshot or receipt of your payment</div>
                                </div>
                                
                                <div class="alert alert-info">
                                    <h5><i class="fas fa-info-circle me-2"></i> Payment Instructions</h5>
                                    <p>Please make your payment to one of the following accounts:</p>
                                    <ul>
                                        <li><strong>Bank Transfer:</strong> Fayda SACCO, Account #123456789, Commercial Bank</li>
                                        <li><strong>Mobile Money:</strong> 0912345678 (Fayda SACCO)</li>
                                    </ul>
                                    <p class="mb-0">Share purchases are processed within 24 hours on business days.</p>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">Submit Share Purchase</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-dark text-white pt-4 pb-2">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0">&copy; 2023 Fayda SACCO. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="#" class="text-white me-3">Privacy Policy</a>
                    <a href="#" class="text-white">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/script.js"></script>

    <script>
        // Date and time display
        function updateDateTime() {
            const now = new Date();
            const options = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            document.getElementById('datetime').textContent = now.toLocaleDateString('en-US', options);
        }

        setInterval(updateDateTime, 60000);
        updateDateTime();

        // Calculate share amount
        document.getElementById('shares').addEventListener('input', function() {
            const shares = this.value;
            const amount = shares * 1000;
            document.getElementById('shareAmount').textContent = amount.toFixed(2);
        });
    </script>
</body>
</html>