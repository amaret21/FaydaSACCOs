<?php
session_start();
require_once '../db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.html?error=Please login first");
    exit();
}

try {
    $db = new Database();
    $conn = $db->connect();
    
    // Get member details and shares
    $memberStmt = $conn->prepare("
        SELECT m.*, 
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
    
    // Get share transactions
    $sharesStmt = $conn->prepare("
        SELECT * FROM deposits 
        WHERE member_id = :member_id AND description LIKE '%share%'
        ORDER BY transaction_date DESC
    ");
    $sharesStmt->bindParam(':member_id', $member['member_id']);
    $sharesStmt->execute();
    $shareTransactions = $sharesStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Member shares error: " . $e->getMessage());
    $error = "Error loading shares information";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Shares - Fayda SACCO</title>
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
                    <marquee>Welcome to Fayda SACCO - My Shares</marquee>
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
        <div class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Shares</h5>
                        <h2 class="card-text"><?php echo $member['share_count']; ?> Shares</h2>
                        <p class="card-text">ETB <?php echo number_format($member['share_amount'], 2); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Final Payment Date</h5>
                        <h2 class="card-text"><?php echo date('M d, Y', strtotime($member['final_payment_date'])); ?></h2>
                        <a href="buy_shares.php" class="btn btn-light">Buy More Shares</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Share Transactions</h5>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Transaction ID</th>
                                <th>Shares</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($shareTransactions)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">No share transactions found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($shareTransactions as $transaction): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($transaction['transaction_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['transaction_id']); ?></td>
                                    <td><?php echo ($transaction['credit'] / 100); ?> Shares</td>
                                    <td>ETB <?php echo number_format($transaction['credit'], 2); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $transaction['status'] === 'approved' ? 'success' : 
                                                 ($transaction['status'] === 'pending' ? 'warning' : 'danger'); 
                                        ?>">
                                            <?php echo ucfirst($transaction['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card shadow mt-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Share Benefits</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5>Dividend Information</h5>
                        <p>Your shares qualify for annual dividends based on SACCO performance.</p>
                        <div class="alert alert-info">
                            <strong>Last Dividend:</strong> ETB 15 per share (2022)
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h5>Voting Rights</h5>
                        <p>As a shareholder, you have voting rights in SACCO decisions.</p>
                        <ul>
                            <li>1 share = 1 vote</li>
                            <li>Participate in Annual General Meetings</li>
                            <li>Elect board members</li>
                        </ul>
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
    </script>
</body>
</html>