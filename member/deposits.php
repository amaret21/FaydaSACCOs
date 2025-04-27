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
        SELECT m.member_id 
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
    
    // Get member's deposits with pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = 10;
    $offset = ($page - 1) * $perPage;
    
    $depositStmt = $conn->prepare("
        SELECT * FROM deposits 
        WHERE member_id = :member_id
        ORDER BY transaction_date DESC
        LIMIT :offset, :perPage
    ");
    $depositStmt->bindParam(':member_id', $member['member_id']);
    $depositStmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $depositStmt->bindParam(':perPage', $perPage, PDO::PARAM_INT);
    $depositStmt->execute();
    $deposits = $depositStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count for pagination
    $countStmt = $conn->prepare("SELECT COUNT(*) FROM deposits WHERE member_id = :member_id");
    $countStmt->bindParam(':member_id', $member['member_id']);
    $countStmt->execute();
    $totalDeposits = $countStmt->fetchColumn();
    $totalPages = ceil($totalDeposits / $perPage);
    
    // Get current balance
    $balanceStmt = $conn->prepare("
        SELECT SUM(credit) - SUM(debit) as balance 
        FROM deposits 
        WHERE member_id = :member_id AND status = 'approved'
    ");
    $balanceStmt->bindParam(':member_id', $member['member_id']);
    $balanceStmt->execute();
    $balance = $balanceStmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Member deposits error: " . $e->getMessage());
    $error = "Error loading deposit history";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Deposits - Fayda SACCO</title>
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
                    <marquee>Welcome to Fayda SACCO - My Deposits</marquee>
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
                        <li class="nav-item"><a class="nav-link active" href="deposits.php">My Deposits</a></li>
                        <li class="nav-item"><a class="nav-link" href="shares.php">My Shares</a></li>
                        <li class="nav-item"><a class="nav-link" href="loans.php">Loans</a></li>
                        <li class="nav-item"><a class="nav-link" href="profile.php">Profile</a></li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="container my-5">
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title">Current Balance</h5>
                                <h2 class="card-text">ETB <?php echo number_format($balance['balance'] ?? 0, 2); ?></h2>
                            </div>
                            <a href="make_deposit.php" class="btn btn-light">Make a Deposit</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Deposit History</h5>
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
                                <th>Description</th>
                                <th>Debit</th>
                                <th>Credit</th>
                                <th>Status</th>
                                <th>Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($deposits)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">No deposits found</td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $runningBalance = $balance['balance'] ?? 0;
                                foreach (array_reverse($deposits) as $deposit): 
                                    $runningBalance += ($deposit['debit'] - $deposit['credit']);
                                ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($deposit['transaction_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($deposit['transaction_id']); ?></td>
                                    <td><?php echo htmlspecialchars($deposit['description'] ?? 'Deposit'); ?></td>
                                    <td><?php echo $deposit['debit'] > 0 ? number_format($deposit['debit'], 2) : '-'; ?></td>
                                    <td><?php echo $deposit['credit'] > 0 ? number_format($deposit['credit'], 2) : '-'; ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $deposit['status'] === 'approved' ? 'success' : 
                                                 ($deposit['status'] === 'pending' ? 'warning' : 'danger'); 
                                        ?>">
                                            <?php echo ucfirst($deposit['status']); ?>
                                        </span>
                                    </td>
                                    <td>ETB <?php echo number_format($runningBalance, 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($totalPages > 1): ?>
                <nav aria-label="Deposit pagination">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                        </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
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