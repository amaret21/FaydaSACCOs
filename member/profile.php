<?php
session_start();
require_once '../db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.html?error=Please login first");
    exit();
}

// Initialize CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize variables
$success = '';
$error = '';

try {
    $db = new Database();
    $conn = $db->connect();
    
    // Get member details
    $memberStmt = $conn->prepare("
        SELECT m.*, u.username, u.email 
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
    
    // Handle profile update
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error = "Security token mismatch. Please try again.";
        } else {
            // Sanitize and validate inputs
            $firstName = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING);
            $middleName = filter_input(INPUT_POST, 'middle_name', FILTER_SANITIZE_STRING) ?? '';
            $lastName = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
            $mobilePhone = filter_input(INPUT_POST, 'mobile_phone', FILTER_SANITIZE_STRING);
            $phoneNumber = filter_input(INPUT_POST, 'phone_number', FILTER_SANITIZE_STRING) ?? '';
            $city = filter_input(INPUT_POST, 'city', FILTER_SANITIZE_STRING);
            $subCity = filter_input(INPUT_POST, 'sub_city', FILTER_SANITIZE_STRING);
            $kebele = filter_input(INPUT_POST, 'kebele', FILTER_SANITIZE_STRING);
            $wereda = filter_input(INPUT_POST, 'wereda', FILTER_SANITIZE_STRING);
            $houseNo = filter_input(INPUT_POST, 'house_no', FILTER_SANITIZE_STRING);
            $occupation = filter_input(INPUT_POST, 'occupation', FILTER_SANITIZE_STRING);
            
            // Validate required fields
            if (empty($firstName) || empty($lastName) || empty($email) || empty($mobilePhone)) {
                $error = "Required fields cannot be empty";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Invalid email format";
            } elseif (!preg_match('/^\+?[0-9]{8,15}$/', $mobilePhone)) {
                $error = "Invalid mobile phone format";
            } else {
                // Update member information
                $updateStmt = $conn->prepare("
                    UPDATE members SET
                        first_name = :first_name,
                        middle_name = :middle_name,
                        last_name = :last_name,
                        mobile_phone = :mobile_phone,
                        phone_number = :phone_number,
                        city = :city,
                        sub_city = :sub_city,
                        kebele = :kebele,
                        wereda = :wereda,
                        house_no = :house_no,
                        occupation = :occupation
                    WHERE user_id = :user_id
                ");
                
                $updateStmt->bindParam(':first_name', $firstName);
                $updateStmt->bindParam(':middle_name', $middleName);
                $updateStmt->bindParam(':last_name', $lastName);
                $updateStmt->bindParam(':mobile_phone', $mobilePhone);
                $updateStmt->bindParam(':phone_number', $phoneNumber);
                $updateStmt->bindParam(':city', $city);
                $updateStmt->bindParam(':sub_city', $subCity);
                $updateStmt->bindParam(':kebele', $kebele);
                $updateStmt->bindParam(':wereda', $wereda);
                $updateStmt->bindParam(':house_no', $houseNo);
                $updateStmt->bindParam(':occupation', $occupation);
                $updateStmt->bindParam(':user_id', $_SESSION['user_id']);
                
                if (!$updateStmt->execute()) {
                    $error = "Failed to update profile. Please try again.";
                } else {
                    // Update user email if changed
                    if ($email !== $member['email']) {
                        $emailStmt = $conn->prepare("UPDATE users SET email = :email WHERE user_id = :user_id");
                        $emailStmt->bindParam(':email', $email);
                        $emailStmt->bindParam(':user_id', $_SESSION['user_id']);
                        
                        if (!$emailStmt->execute()) {
                            $error = "Profile updated but email change failed";
                        } else {
                            $success = "Profile updated successfully!";
                        }
                    } else {
                        $success = "Profile updated successfully!";
                    }
                    
                    // Refresh member data
                    $memberStmt->execute();
                    $member = $memberStmt->fetch(PDO::FETCH_ASSOC);
                }
            }
        }
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error = "A database error occurred. Please try again later.";
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    $error = "An unexpected error occurred. Please try again.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Fayda SACCO</title>
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
                    <marquee>Welcome to Fayda SACCO - My Profile</marquee>
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
                        <li class="nav-item"><a class="nav-link" href="loans.php">Loans</a></li>
                        <li class="nav-item"><a class="nav-link active" href="profile.php">Profile</a></li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="container my-5">
        <div class="row">
            <div class="col-md-4">
                <div class="card shadow mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Profile Information</h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <img src="../images/profile-placeholder.png" alt="Profile" class="rounded-circle" width="120" height="120">
                        </div>
                        <h4><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name'], ENT_QUOTES, 'UTF-8'); ?></h4>
                        <p class="text-muted">Member since <?php echo date('M Y', strtotime($member['registration_date'])); ?></p>
                        
                        <hr>
                        
                        <h5>Account Details</h5>
                        <p>
                            <strong>Username:</strong> <?php echo htmlspecialchars($member['username'], ENT_QUOTES, 'UTF-8'); ?><br>
                            <strong>Member ID:</strong> <?php echo htmlspecialchars($member['member_id'], ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                        
                        <h5 class="mt-3">Contact</h5>
                        <p>
                            <i class="fas fa-phone me-2"></i> <?php echo htmlspecialchars($member['mobile_phone'], ENT_QUOTES, 'UTF-8'); ?><br>
                            <i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($member['email'], ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                    </div>
                </div>
                
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Change Password</h5>
                    </div>
                    <div class="card-body">
                        <form id="passwordForm" action="change_password.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                                <div class="form-text">At least 8 characters</div>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                            </div>
                            <button type="submit" class="btn btn-primary">Change Password</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Edit Profile</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                            <h5 class="mb-3">Personal Information</h5>
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="first_name" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo htmlspecialchars($member['first_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="middle_name" class="form-label">Middle Name</label>
                                    <input type="text" class="form-control" id="middle_name" name="middle_name" 
                                           value="<?php echo htmlspecialchars($member['middle_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo htmlspecialchars($member['last_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($member['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="mobile_phone" class="form-label">Mobile Phone *</label>
                                    <input type="tel" class="form-control" id="mobile_phone" name="mobile_phone" 
                                           value="<?php echo htmlspecialchars($member['mobile_phone'], ENT_QUOTES, 'UTF-8'); ?>" required pattern="\+?[0-9]{8,15}">
                                    <div class="form-text">Format: +251XXXXXXXXX or 09XXXXXXXX</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="phone_number" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone_number" name="phone_number" 
                                           value="<?php echo htmlspecialchars($member['phone_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="occupation" class="form-label">Occupation *</label>
                                <input type="text" class="form-control" id="occupation" name="occupation" 
                                       value="<?php echo htmlspecialchars($member['occupation'], ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            
                            <hr>
                            <h5 class="mb-3">Address Information</h5>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="city" class="form-label">City *</label>
                                    <input type="text" class="form-control" id="city" name="city" 
                                           value="<?php echo htmlspecialchars($member['city'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="sub_city" class="form-label">Sub-City/Zone *</label>
                                    <input type="text" class="form-control" id="sub_city" name="sub_city" 
                                           value="<?php echo htmlspecialchars($member['sub_city'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="kebele" class="form-label">Kebele *</label>
                                    <input type="text" class="form-control" id="kebele" name="kebele" 
                                           value="<?php echo htmlspecialchars($member['kebele'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="wereda" class="form-label">Wereda *</label>
                                    <input type="text" class="form-control" id="wereda" name="wereda" 
                                           value="<?php echo htmlspecialchars($member['wereda'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="house_no" class="form-label">House Number *</label>
                                <input type="text" class="form-control" id="house_no" name="house_no" 
                                       value="<?php echo htmlspecialchars($member['house_no'], ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Update Profile</button>
                            </div>
                        </form>
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

        // Password form validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (!currentPassword) {
                alert('Please enter your current password');
                e.preventDefault();
                return false;
            }
            
            if (newPassword.length < 8) {
                alert('Password must be at least 8 characters long');
                e.preventDefault();
                return false;
            }
            
            if (newPassword !== confirmPassword) {
                alert('Passwords do not match');
                e.preventDefault();
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>