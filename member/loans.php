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
    
    // Get member details
    $memberStmt = $conn->prepare("
        SELECT m.member_id, m.share_amount,
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
    
    // Get member's loans
    $loansStmt = $conn->prepare("
        SELECT * FROM loans 
        WHERE member_id = :member_id
        ORDER BY application_date DESC
    ");
    $loansStmt->bindParam(':member_id', $member['member_id']);
    $loansStmt->execute();
    $loans = $loansStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate maximum loan eligibility (3 times share amount)
    $maxLoan = $member['share_amount'] * 3;
    
} catch (Exception $e) {
    error_log("Member loans error: " . $e->getMessage());
    $error = "Error loading loan information";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Loans - Fayda SACCO</title>
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
                    <marquee>Welcome to Fayda SACCO - My Loans</marquee>
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
                        <li class="nav-item"><a class="nav-link" href="shares.php">My Shares</a></li>
                        <li class="nav-item"><a class="nav-link active" href="loans.php">Loans</a></li>
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
                        <h5 class="card-title">Loan Eligibility</h5>
                        <h2 class="card-text">ETB <?php echo number_format($maxLoan, 2); ?></h2>
                        <p class="card-text">3 times your share amount (ETB <?php echo number_format($member['share_amount'], 2); ?>)</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Apply for Loan</h5>
                        <p>Get affordable loans with competitive interest rates</p>
                        <a href="apply_loan.php" class="btn btn-light">Apply Now</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">My Loans</h5>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (empty($loans)): ?>
                    <div class="alert alert-info">
                        You don't have any active loans. <a href="apply_loan.php" class="alert-link">Apply for a loan</a>.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Loan #</th>
                                    <th>Amount</th>
                                    <th>Interest</th>
                                    <th>Term</th>
                                    <th>Status</th>
                                    <th>Date Applied</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($loans as $loan): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($loan['loan_number']); ?></td>
                                    <td>ETB <?php echo number_format($loan['amount'], 2); ?></td>
                                    <td><?php echo $loan['interest_rate']; ?>%</td>
                                    <td><?php echo $loan['term_months']; ?> months</td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $loan['status'] === 'approved' ? 'success' : 
                                                 ($loan['status'] === 'pending' ? 'warning' : 'danger'); 
                                        ?>">
                                            <?php echo ucfirst($loan['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($loan['application_date'])); ?></td>
                                    <td>
                                        <a href="loan_details.php?id=<?php echo $loan['loan_id']; ?>" class="btn btn-sm btn-primary">View</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow mt-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Loan Products</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title">Emergency Loan</h5>
                                <p class="card-text">
                                    <strong>Amount:</strong> Up to ETB 50,000<br>
                                    <strong>Interest:</strong> 12% per annum<br>
                                    <strong>Term:</strong> 6 months
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title">Business Loan</h5>
                                <p class="card-text">
                                    <strong>Amount:</strong> Up to ETB 200,000<br>
                                    <strong>Interest:</strong> 10% per annum<br>
                                    <strong>Term:</strong> 12-24 months
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title">Asset Financing</h5>
                                <p class="card-text">
                                    <strong>Amount:</strong> Up to ETB 500,000<br>
                                    <strong>Interest:</strong> 8% per annum<br>
                                    <strong>Term:</strong> 24-36 months
                                </p>
                            </div>
                        </div>
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