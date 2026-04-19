<?php
require '../admin/base.php'; 
$pageTitle = "Member Management";
include '_admin_sidebar.php'; 

// Capture search term
$search = $_GET['search'] ?? "";
?>

<div class="main">
    <div class="main-navbar" style="margin-bottom: 30px;">
        <h1>Member Management</h1>
        <form action="_admin_members.php" method="GET" style="display: flex; gap: 10px;">
            <input type="text" name="search" placeholder="Search name or email..." 
                   value="<?php echo htmlspecialchars($search); ?>"
                   style="padding: 8px; border-radius: 20px; border: 1px solid #ddd; width: 250px;">
            <button type="submit" class="search-btn" style="padding: 8px 15px;">Search</button>
        </form>
    </div>

    <?php if (isset($_SESSION['msg'])): 
        $msg = $_SESSION['msg'];
        $isError = stripos($msg, 'error') !== false ? true : false;
        unset($_SESSION['msg']);
    ?>
        <div id="notification-bar" style="position: fixed; top: 20px; right: 20px; max-width: 400px; padding: 16px 20px; border-radius: 8px; background: <?php echo $isError ? '#e74c3c' : '#27ae60'; ?>; color: white; box-shadow: 0 4px 12px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 12px; z-index: 9999; animation: slideIn 0.3s ease-out, slideOut 3s ease-out 2.7s forwards;">
            <div style="font-size: 20px;">
                <?php echo $isError ? '❌' : '✓'; ?>
            </div>
            <div style="flex: 1; font-weight: 500;">
                <?php echo htmlspecialchars($msg); ?>
            </div>
            <button onclick="document.getElementById('notification-bar').style.display='none';" style="background: transparent; border: none; color: white; font-size: 18px; cursor: pointer; padding: 0; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">✕</button>
        </div>
        <style>
            @keyframes slideIn {
                from {
                    transform: translateX(420px);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            @keyframes slideOut {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(420px);
                    opacity: 0;
                }
            }
        </style>
    <?php endif; ?>

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
                <div class="detail-card" style="border: <?php echo $isSuspended ? '1px solid #e74c3c' : 'none'; ?>; padding: 20px; transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(0,0,0,0.1);" onmouseover="this.style.boxShadow='0 8px 20px rgba(0,0,0,0.15)'; this.style.transform='translateY(-4px)';" onmouseout="this.style.boxShadow='0 2px 8px rgba(0,0,0,0.1)'; this.style.transform='translateY(0)';">
                    <div class="detail-name">
                        <h4 style="color: #333; font-size: 18px; margin-bottom: 8px;"><?php echo htmlspecialchars($row['User_Name']); ?></h4>
                        <p class="detail-sub" style="color: <?php echo $isSuspended ? '#e74c3c' : '#27ae60'; ?>; font-weight: 500;">
                            <?php echo $isSuspended ? "Account Suspended" : "Active Member"; ?>
                        </p>
                    </div>
                    
                    <div class="divider" style="margin: 15px 0;"></div>

                    <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 15px;">
                        <form method="POST" action="_admin_members.php">
                            <input type="hidden" name="user_id" value="<?php echo $row['User_ID']; ?>">
                            <input type="hidden" name="current_status" value="<?php echo $row['Suspend']; ?>">
                            
                            <button type="submit" name="toggle_suspend" 
                                    style="background-color: <?php echo $isSuspended ? '#27ae60' : '#f39c12'; ?>; color: white; border: none; padding: 10px 16px; border-radius: 6px; cursor: pointer; width: 100%; display: flex; align-items:center; justify-content: center; gap: 8px; font-weight: 600; transition: all 0.3s ease;" onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.2)'; this.style.transform='scale(1.02)';" onmouseout="this.style.boxShadow='none'; this.style.transform='scale(1)';">
                                
                                <ion-icon name="<?php echo $isSuspended ? 'play-outline' : 'pause-outline'; ?>"></ion-icon>
                                <?php echo $isSuspended ? "Unsuspend User" : "Suspend User"; ?>
                                
                            </button>
                        </form>

                        <form method="POST" action="_admin_members.php">
                            <input type="hidden" name="user_id" value="<?php echo $row['User_ID']; ?>">
                            <button type="submit" name="delete_user" 
                                    style="background-color: #c0392b; color: white; border: none; padding: 10px 16px; border-radius: 6px; cursor: pointer; width: 100%; font-weight: 600; transition: all 0.3s ease;" onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.2)'; this.style.transform='scale(1.02)';" onmouseout="this.style.boxShadow='none'; this.style.transform='scale(1)';">
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