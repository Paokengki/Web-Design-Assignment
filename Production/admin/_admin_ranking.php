<?php
// admin/_admin_ranking.php

// 1. Include base logic and database connection
require_once '../base.php'; 

// 2. Set page title for the sidebar/header
$pageTitle = "Restaurant Performance Ranking";
$extraStylesheets = ['../css/admin.css'];
$extraScripts = ['../js/admin_ranking.js'];

// 3. Include the shared admin sidebar
include '_admin_sidebar.php'; 
?>

<div class="main">
    <div class="main-navbar">
        <div class="main-title">
            <h1>Business Analytics</h1>
        </div>
        <a href="../admin/_admin_setting.php">
            <div class="profile">
                <span class="user"><ion-icon name="people-circle-outline"></ion-icon></span>
                <strong>Admin</strong>
            </div>
        </a>
    </div>

    <div id="alertBox"></div>

    <div class="main-menus">
        <div class="ranking-container">
            
            <div class="ranking-header">
                <div>
                    <h2>Restaurant Rankings</h2>
                    <p>Based on successful transactions and total revenue (MYR).</p>
                </div>
                <button onclick="loadRestaurantRanking()" class="save-btn">
                    <ion-icon name="refresh-outline"></ion-icon> Refresh Data
                </button>
            </div>
            
            <div class="table-responsive">
                <table class="ranking-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Restaurant Name</th>
                            <th>Total Transactions</th>
                            <th>Total Revenue (RM)</th>
                        </tr>
                    </thead>
                    <tbody id="rankingBody">
                        <tr>
                            <td colspan="4" class="ranking-loading">
                                <ion-icon name="sync-outline"></ion-icon>
                                <p>Fetching latest sales data...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


<?php 
// Include the shared footer
include '../home/_home_footer.php'; 
?>