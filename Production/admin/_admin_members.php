<?php
require '../admin/base.php'; 
$pageTitle = "Member Management";
include '_admin_sidebar.php'; 

// Capture search term
$search = $_GET['search'] ?? "";
?>

<div class="main">
    <div class="main-navbar">
        <h1>Member Management</h1>
        <form action="_admin_members.php" method="GET" style="display: flex; gap: 10px;">
            <input type="text" name="search" placeholder="Search name or email..." 
                   value="<?php echo htmlspecialchars($search); ?>"
                   style="padding: 8px; border-radius: 20px; border: 1px solid #ddd; width: 250px;">
            <button type="submit" class="btn" style="padding: 8px 15px;">Search</button>
        </form>
    </div>

    <div class="main-menus">
        <div class="detail-wrapper">
            <?php
            // Fetch members (using your existing getAllMembers function)
            $members = getAllMembers($conn, $search); 
            if ($members && $members->num_rows > 0):
                while($row = $members->fetch_assoc()):
                    // Determine if user is currently suspended
                    $isSuspended = ($row['Suspend'] == 1);
            ?>
                <div class="detail-card" style="border: <?php echo $isSuspended ? '1px solid #e74c3c' : 'none'; ?>;">
                    <div class="detail-name">
                        <h4><?php echo htmlspecialchars($row['User_name']); ?></h4>
                        <p class="detail-sub" style="color: <?php echo $isSuspended ? '#e74c3c' : '#27ae60'; ?>;">
                            <?php echo $isSuspended ? "Account Suspended" : "Active Member"; ?>
                        </p>
                    </div>
                    
                    <div class="divider"></div>

                    <div style="display: flex; flex-direction: column; gap: 8px; margin-top: 10px;">
                        <form method="POST" action="_admin_members.php">
                            <input type="hidden" name="user_id" value="<?php echo $row['User_ID']; ?>">
                            <input type="hidden" name="current_status" value="<?php echo $row['Suspend']; ?>">
                            
                            <button type="submit" name="toggle_suspend" 
                                    style="background-color: <?php echo $isSuspended ? '#27ae60' : '#f39c12'; ?>; color: white; border: none; padding: 8px; border-radius: 5px; cursor: pointer; width: 100%; display: flex; align-items:center; justify-content: center; gap: 5px;">
                                
                                <ion-icon name="<?php echo $isSuspended ? 'play-outline' : 'pause-outline'; ?>"></ion-icon>
                                <?php echo $isSuspended ? "Unsuspend User" : "Suspend User"; ?>
                                
                            </button>
                        </form>

                        <form method="POST" action="_admin_members.php" onsubmit="return confirm('Permanently delete this user?');">
                            <input type="hidden" name="user_id" value="<?php echo $row['User_ID']; ?>">
                            <button type="submit" name="delete_user" 
                                    style="background-color: #c0392b; color: white; border: none; padding: 8px; border-radius: 5px; cursor: pointer; width: 100%;">
                                Delete Member
                            </button>
                        </form>
                    </div>
                </div>
            <?php endwhile; else: ?>
                <p>No members found.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../home/_home_footer.php'; ?>