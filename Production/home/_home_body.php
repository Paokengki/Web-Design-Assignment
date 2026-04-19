<?php
require_once __DIR__ . '/../profile/avatar_csv_functions.php';

$user_id = $_SESSION['user_id'] ?? null;
$profile_image = null;

if ($user_id !== null) {
	$profile_image = getAvatarFromCSV($user_id);
}

if (!$profile_image) {
	$profile_image = '../material/images/uploads/avatar_1_1776275013.jpg';
}
?>

<!-- main -->
<div class="main">
	<div class="main-navbar">
		<ion-icon class="menu-toggle" name="menu-outline"></ion-icon>
		<div class="search">
			<input type="text" placeholder="What you want to eat?">
			<button class="search-btn">Search</button>
		</div>
		<div class="profile">
			<a class="cart" id="openCartBtn" href="#" aria-label="Open cart"><ion-icon name="cart-outline"></ion-icon></a>
			<a class="user user-avatar" href="setting.php#profile" aria-label="Open profile settings">
				<img src="<?php echo htmlspecialchars($profile_image, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile photo">
			</a>
		</div>
	</div>

	<div class="main-highlight">
		<div class="main-header">
			<h2 class="main-title">Top Recommendations</h2>
		</div>
		<div class="highlight-wrapper">
			<!-- Show the top-rated cafes as quick entry cards. -->
			<?php
			$sql = "SELECT Restaurant_ID, Name, Rating FROM Restaurant ORDER BY Rating DESC LIMIT 3";
			$result = $conn->query($sql);

			if ($result && $result->num_rows > 0) {
				while ($row = $result->fetch_assoc()) {
					$cafeId = (int) $row['Restaurant_ID'];
					$cafeName = htmlspecialchars($row['Name'], ENT_QUOTES, 'UTF-8');
					$rating = (float) $row['Rating'];
					$shopImage = "../material/" . $cafeName . "/shop.jpg";
				?>
				<a href="cafe.php?id=<?php echo $cafeId; ?>" class="highlight-link">
					<div class="highlight-card">
						<img class="highlight-img" src="<?php echo htmlspecialchars($shopImage, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo $cafeName; ?>">
						<div class="highlight-desc">
							<h4><?php echo $cafeName; ?></h4>
							<p class="highlight-rating">Rating <?php echo number_format($rating, 1); ?></p>
						</div>
					</div>
				</a>
				<?php
				}
			}
			?>
		</div>
	</div>

	<div class="main-menus">
		<div class="main-filter">
			<div>
				<h2 class="main-title">Menu<br>Category</h2>
			</div>

			<!-- These cards drive the client-side filter in app.js. -->
			<div class="filter-wrapper">
				<div class="filter-card active" data-filter="all">
					<div class="filter-icon"><ion-icon name="restaurant-outline"></ion-icon></div>
					<p>All Cafe</p>
				</div>
				<div class="filter-card" data-filter="coffee">
					<div class="filter-icon"><ion-icon name="cafe-outline"></ion-icon></div>
					<p>Coffee</p>
				</div>
				<div class="filter-card" data-filter="dessert">
					<div class="filter-icon"><ion-icon name="ice-cream-outline"></ion-icon></div>
					<p>Dessert</p>
				</div>
				<div class="filter-card" data-filter="food">
					<div class="filter-icon"><ion-icon name="fast-food-outline"></ion-icon></div>
					<p>Food</p>
				</div>
			</div>
		</div>

		<hr class="divider">

		<div class="main-detail">
			<h2 class="main-title">Choose Order</h2>
			<div class="detail-wrapper">
				<!-- Render all restaurants and map each one to a filter category. -->
				<?php
				$sql = "SELECT * FROM Restaurant";
				$result = $conn->query($sql);

				if ($result && $result->num_rows > 0) {
					while ($row = $result->fetch_assoc()) {
						$cafeName = $row['Name'];
						$shopImage = "../material/" . $cafeName . "/shop.jpg";
						$restaurantType = strtolower(trim((string) ($row['Restaurant_type'] ?? '')));
						$category = 'food';

						if ($restaurantType === '') {
							$category = 'food';
						} elseif (strpos($restaurantType, 'coffee') !== false || strpos($restaurantType, 'cafe') !== false) {
							$category = 'coffee';
						} elseif (strpos($restaurantType, 'dessert') !== false || strpos($restaurantType, 'bakery') !== false || strpos($restaurantType, 'cake') !== false || strpos($restaurantType, 'ice') !== false) {
							$category = 'dessert';
						}
					?>
					<a class="detail-card-link" data-category="<?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?>" href="cafe.php?id=<?php echo (int) $row['Restaurant_ID']; ?>">
						<div class="detail-card">
							<img class="detail-img" src="<?php echo htmlspecialchars($shopImage, ENT_QUOTES, 'UTF-8'); ?>" alt="Shop Image">
							<div class="detail-desc">
								<div class="detail-name">
									<h4><?php echo $row['Name']; ?></h4>
									<p class="detail-sub"><?php echo $row['Address']; ?></p>
									<p class="Rating">Rating <?php echo $row['Rating']; ?></p>
								</div>
							</div>
						</div>
					</a>
					<?php
					}
				} else {
					echo 'No restaurant found';
				}
				?>
			</div>
		</div>
	</div>

	<?php require_once __DIR__ . '/../includes/cart_modal.php'; ?>
</div>

<?php require_once __DIR__ . '/_home_footer.php'; ?>