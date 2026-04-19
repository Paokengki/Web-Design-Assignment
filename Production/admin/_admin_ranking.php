<?php
// admin/_admin_ranking.php

// 1. Include base logic and database connection
require_once '../base.php'; 

// 2. Set page title for the sidebar/header
$pageTitle = "Restaurant Performance Ranking";

// 3. Include the shared admin sidebar
include '_admin_sidebar.php'; 
?>

<div class="main">
    <div class="main-navbar" style="margin-bottom: 20px;">
        <div class="main-title">
            <h1>Business Analytics</h1>
        </div>
        <a href="../admin/_admin_setting.php" style="text-decoration: none; color: inherit;">
            <div class="profile" style="cursor: pointer;">
                <span class="user"><ion-icon name="people-circle-outline"></ion-icon></span>
                <strong>Admin</strong>
            </div>
        </a>
    </div>

    <div id="alertBox" style="display:none; margin: 20px; padding: 15px; border-radius: 8px; font-weight: bold;"></div>

    <div class="main-menus">
        <div class="ranking-container" style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <div>
                    <h2 style="color: var(--darkBrown); margin: 0;">Restaurant Rankings</h2>
                    <p style="color: #666; font-size: 0.9rem; margin-top: 5px;">Based on successful transactions and total revenue (MYR).</p>
                </div>
                <button onclick="loadRestaurantRanking()" class="save-btn" style="width: auto; padding: 10px 20px; display: flex; align-items: center; gap: 8px;">
                    <ion-icon name="refresh-outline"></ion-icon> Refresh Data
                </button>
            </div>
            
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; min-width: 600px;">
                    <thead>
                        <tr style="text-align: left; background: #f8f9fa;">
                            <th style="padding: 15px; border-bottom: 2px solid #eee;">Rank</th>
                            <th style="padding: 15px; border-bottom: 2px solid #eee;">Restaurant Name</th>
                            <th style="padding: 15px; border-bottom: 2px solid #eee; text-align: center;">Total Transactions</th>
                            <th style="padding: 15px; border-bottom: 2px solid #eee; text-align: right;">Total Revenue (RM)</th>
                        </tr>
                    </thead>
                    <tbody id="rankingBody">
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 40px; color: #999;">
                                <ion-icon name="sync-outline" style="animation: spin 2s linear infinite; font-size: 2rem;"></ion-icon>
                                <p>Fetching latest sales data...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    .ranking-row:hover {
        background-color: #fff9f4;
        transition: 0.3s;
    }

    .rank-badge {
        display: inline-block;
        width: 30px;
        height: 30px;
        line-height: 30px;
        text-align: center;
        border-radius: 50%;
        background: var(--primaryColor);
        color: white;
        font-weight: bold;
    }

    .top-rank {
        background: #ffc107; /* Gold for #1 */
        color: #000;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadRestaurantRanking();
});

function loadRestaurantRanking() {
    const tbody = document.getElementById('rankingBody');
    
    // Ensure the path to your API file is correct
    fetch('../api/admin/restaurant_transaction_ranking.php?limit=10')
        .then(response => {
            if (!response.ok) throw new Error('Could not fetch data. Check if you are logged in.');
            return response.json();
        })
        .then(data => {
            if (data.success && data.ranking.length > 0) {
                tbody.innerHTML = ''; 
                data.ranking.forEach((item, index) => {
                    const isFirst = index === 0 ? 'top-rank' : '';
                    const row = `
                        <tr class="ranking-row" style="border-bottom: 1px solid #eee;">
                            <td style="padding: 15px;">
                                <span class="rank-badge ${isFirst}">#${index + 1}</span>
                            </td>
                            <td style="padding: 15px; font-weight: 600; color: var(--blackColor);">
                                ${item.restaurantName}
                            </td>
                            <td style="padding: 15px; text-align: center;">
                                <span style="background: #e1f5fe; color: #0288d1; padding: 4px 10px; border-radius: 12px; font-size: 0.85rem; font-weight: bold;">
                                    ${item.transactionCount} orders
                                </span>
                            </td>
                            <td style="padding: 15px; text-align: right; color: #27ae60; font-weight: bold; font-size: 1.1rem;">
                                ${item.totalSales.toLocaleString('en-MY', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                            </td>
                        </tr>`;
                    tbody.innerHTML += row;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding: 40px;">No successful transactions found yet.</td></tr>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; color: #c0392b; padding: 40px;">
                <ion-icon name="alert-circle-outline" style="font-size: 2rem;"></ion-icon><br>
                Error loading data: ${error.message}
            </td></tr>`;
        });
}
</script>

<?php 
// Include the shared footer
include '../home/_home_footer.php'; 
?>