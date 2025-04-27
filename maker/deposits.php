<?php
session_start();
require_once '../../db_connection.php';

// Check if user is logged in and has maker role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'maker') {
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
    
    // Process deposit submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $memberId = $_POST['member_id'];
        $amount = $_POST['amount'];
        $description = $_POST['description'];
        
        // Generate transaction ID
        $transactionId = 'DEP' . date('Ymd') . strtoupper(substr(uniqid(), -6));
        
        $insertStmt = $conn->prepare("
            INSERT INTO deposits (
                member_id, transaction_date, transaction_id, 
                credit, balance, description, created_by
            ) VALUES (
                :member_id, CURDATE(), :transaction_id, 
                :credit, :credit, :description, :created_by
            )
        ");
        
        $insertStmt->bindParam(':member_id', $memberId);
        $insertStmt->bindParam(':transaction_id', $transactionId);
        $insertStmt->bindParam(':credit', $amount);
        $insertStmt->bindParam(':description', $description);
        $insertStmt->bindParam(':created_by', $_SESSION['user_id']);
        
        $insertStmt->execute();
        
        header("Location: deposits.php?success=Deposit recorded successfully and sent for approval");
        exit();
    }
    
} catch (Exception $e) {
    error_log("Deposit processing error: " . $e->getMessage());
    $error = "Error processing deposits";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deposit Processing - Fayda SACCO</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="icon" type="image/x-icon" href="../../images/favicon.ico">
</head>
<body>
    <!-- Top Bar -->
    <div class="top-bar bg-primary text-white py-2">
        <div class="container">
            <div class="row">
                <div class="col-md-8">
                    <marquee>Welcome to Fayda SACCO Deposit Processing</marquee>
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
                    <a href="../../logout.php" class="btn btn-sm btn-outline-light">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Header -->
    <header class="sticky-top">
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
            <div class="container">
                <a class="navbar-brand" href="../../index.html">
                    <img src="../../images/logo.png" alt="Fayda SACCO Logo" width="80" height="75" class="d-inline-block align-top">
                    <span class="logo-text">Fayda SACCO</span><br>
                    <small class="logo-slogan">Deposit Processing</small>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link active" href="deposits.php">Deposits</a></li>
                        <li class="nav-item"><a class="nav-link" href="members.php">Members</a></li>
                        <li class="nav-item"><a class="nav-link" href="reports.php">Reports</a></li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="container my-5">
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card shadow mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Record New Deposit</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="member_id" class="form-label">Member ID *</label>
                                <input type="text" class="form-control" id="member_id" name="member_id" required>
                                <div class="form-text">Enter member ID or search by name</div>
                            </div>
                            <div class="mb-3">
                                <label for="amount" class="form-label">Amount (ETB) *</label>
                                <input type="number" class="form-control" id="amount" name="amount" min="1" step="0.01" required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Record Deposit</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Pending Deposits</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Member</th>
                                        <th>Amount</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingDeposits as $deposit): ?>
                                    <tr>
                                        <td><?php echo date('M d', strtotime($deposit['transaction_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($deposit['first_name'] . ' ' . $deposit['last_name']); ?></td>
                                        <td>ETB <?php echo number_format($deposit['credit'], 2); ?></td>
                                        <td>
                                            <a href="deposit_details.php?id=<?php echo $deposit['deposit_id']; ?>" class="btn btn-sm btn-primary">View</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
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
    <script src="../../js/script.js"></script>

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