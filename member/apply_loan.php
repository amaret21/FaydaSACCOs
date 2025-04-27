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
    
    // Get member details and eligibility
    $memberStmt = $conn->prepare("
        SELECT m.member_id, m.first_name, m.last_name, m.share_amount,
               (SELECT SUM(credit) - SUM(debit) 
                FROM deposits 
                WHERE member_id = m.member_id AND status = 'approved') as balance,
               (SELECT COUNT(*) FROM loans WHERE member_id = m.member_id AND status = 'approved') as active_loans
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
    
    // Calculate maximum loan amount (3 times share amount)
    $maxLoan = $member['share_amount'] * 3;
    
    // Process loan application
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $amount = $_POST['amount'];
        $purpose = $_POST['purpose'];
        $term = $_POST['term'];
        $collateral = $_POST['collateral'];
        
        // Validate amount
        if ($amount <= 0) {
            $error = "Amount must be greater than zero";
        } elseif ($amount > $maxLoan) {
            $error = "Amount exceeds your maximum loan eligibility of ETB " . number_format($maxLoan, 2);
        } elseif ($member['active_loans'] > 0) {
            $error = "You already have an active loan. Please repay it before applying for a new one.";
        } else {
            // Generate loan number
            $loanNumber = 'LN' . date('Ymd') . strtoupper(substr(uniqid(), -6));
            
            // Default interest rate based on term
            $interestRate = ($term <= 12) ? 12 : (($term <= 24) ? 10 : 8);
            
            // Record loan application
            $insertStmt = $conn->prepare("
                INSERT INTO loans (
                    member_id, loan_number, amount, interest_rate, term_months, 
                    purpose, collateral, application_date, status
                ) VALUES (
                    :member_id, :loan_number, :amount, :interest_rate, :term_months, 
                    :purpose, :collateral, CURDATE(), 'pending'
                )
            ");
            
            $insertStmt->bindParam(':member_id', $member['member_id']);
            $insertStmt->bindParam(':loan_number', $loanNumber);
            $insertStmt->bindParam(':amount', $amount);
            $insertStmt->bindParam(':interest_rate', $interestRate);
            $insertStmt->bindParam(':term_months', $term);
            $insertStmt->bindParam(':purpose', $purpose);
            $insertStmt->bindParam(':collateral', $collateral);
            $insertStmt->execute();
            
            $success = "Loan application submitted successfully! It will be processed within 3 business days.";
        }
    }
    
} catch (Exception $e) {
    error_log("Loan application error: " . $e->getMessage());
    $error = "Error processing loan application. Please try again.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Loan - Fayda SACCO</title>
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
                    <marquee>Welcome to Fayda SACCO - Apply for Loan</marquee>
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
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Loan Application</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                            <div class="text-center">
                                <a href="loans.php" class="btn btn-primary">View My Loans</a>
                            </div>
                        <?php else: ?>
                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?php echo $error; ?></div>
                            <?php endif; ?>
                            
                            <div class="alert alert-info mb-4">
                                <h5><i class="fas fa-info-circle me-2"></i> Loan Eligibility</h5>
                                <p>You can borrow up to <strong>3 times your share amount</strong>.</p>
                                <p class="mb-0"><strong>Maximum Loan Amount:</strong> ETB <?php echo number_format($maxLoan, 2); ?></p>
                            </div>
                            
                            <form method="POST">
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
                                    <label for="amount" class="form-label">Loan Amount (ETB) *</label>
                                    <input type="number" class="form-control" id="amount" name="amount" 
                                           min="1" max="<?php echo $maxLoan; ?>" step="0.01" required>
                                    <div class="form-text">Maximum: ETB <?php echo number_format($maxLoan, 2); ?></div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="term" class="form-label">Loan Term (Months) *</label>
                                    <select class="form-select" id="term" name="term" required>
                                        <option value="" selected disabled>Select Loan Term</option>
                                        <option value="6">6 Months (12% interest)</option>
                                        <option value="12">12 Months (12% interest)</option>
                                        <option value="18">18 Months (10% interest)</option>
                                        <option value="24">24 Months (10% interest)</option>
                                        <option value="36">36 Months (8% interest)</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="purpose" class="form-label">Loan Purpose *</label>
                                    <select class="form-select" id="purpose" name="purpose" required>
                                        <option value="" selected disabled>Select Purpose</option>
                                        <option value="Emergency">Emergency</option>
                                        <option value="Business">Business</option>
                                        <option value="Education">Education</option>
                                        <option value="Agriculture">Agriculture</option>
                                        <option value="Construction">Construction</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="collateral" class="form-label">Collateral Description</label>
                                    <textarea class="form-control" id="collateral" name="collateral" rows="3"></textarea>
                                    <div class="form-text">Describe any collateral you can provide (property, guarantor, etc.)</div>
                                </div>
                                
                                <div class="alert alert-warning">
                                    <h5><i class="fas fa-exclamation-triangle me-2"></i> Important Information</h5>
                                    <ul class="mb-0">
                                        <li>Loan processing takes 3-5 business days</li>
                                        <li>You must have at least 3 months membership to qualify</li>
                                        <li>Late payments incur additional charges</li>
                                    </ul>
                                </div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="agree_terms" required>
                                    <label class="form-check-label" for="agree_terms">
                                        I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">loan terms and conditions</a>
                                    </label>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">Submit Application</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Terms Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="termsModalLabel">Loan Terms and Conditions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6>Loan Repayment</h6>
                    <p>By applying for this loan, you agree to:</p>
                    <ul>
                        <li>Repay the loan according to the agreed schedule</li>
                        <li>Make monthly payments by the due date</li>
                        <li>Pay any late fees for missed payments</li>
                    </ul>

                    <h6 class="mt-4">Interest Rates</h6>
                    <p>Interest rates are fixed for the duration of the loan:</p>
                    <ul>
                        <li>6-12 month loans: 12% per annum</li>
                        <li>13-24 month loans: 10% per annum</li>
                        <li>25-36 month loans: 8% per annum</li>
                    </ul>

                    <h6 class="mt-4">Default Consequences</h6>
                    <p>Failure to repay may result in:</p>
                    <ul>
                        <li>Additional penalty fees</li>
                        <li>Suspension of membership privileges</li>
                        <li>Legal action to recover the amount</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">I Understand</button>
                </div>
            </div>
        </div>
    </div>

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