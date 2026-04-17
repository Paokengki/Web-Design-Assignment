<body>
	<!-- siddebar -->
	<div class ="sidebar">
		<!-- Logo -->
		<h1 class="logo">Cafe Dash</h1>
		
		<!-- List of menus -->
		<div class="sidebar-menus">
			<a href="#"><ion-icon name="storefront-outline"></ion-icon>Home</a>
			<a href="#"><ion-icon name="receipt-outline"></ion-icon>Bills</a>
			<a href="#"><ion-icon name="wallet-outline"></ion-icon>Wallet</a>
			<a href="#"><ion-icon name="notifications-outline"></ion-icon>Notification</a>
			<a href="contact_us.php"><ion-icon name="chatbubbles-outline"></ion-icon>Contact Us</a>
			<a href="setting.php"><ion-icon name="settings-outline"></ion-icon>Setting</a>
		</div>
		<!-- logout -->
		<div class="sidebar-logout">
			<a href="index.php"><ion-icon name="log-out-outline"></ion-icon>Logout</a>
		</div>	
		
	</div>
	
	<!-- main -->
	<div class="main">
		<!--main navbar-->
		<div class="main-navbar">
			<!--menu when appear on mobile version-->
			<ion-icon class="menu-toggle" name="menu-outline"></ion-icon>
			<!--search bar-->
			<div class="search">
				<input type="text" placeholder="What you want to eat?">
				<button class="search-btn">Search</button>
			</div>
			<!--profile icon on  lefty side of navbar-->
			<div class="profile">
				<a class="cart" href="#"><ion-icon name="cart-outline"></ion-icon></a>
				<a class="user" href="#"><ion-icon name="person-outline"></ion-icon></a>
			</div>
		</div>
		<!--main highlight-->
		<div class="main-highlight">
			<!--titlle section and arrow-->
			<div class="main-header">
				<h2 class="main-title">Top Recommendations</h2>
			</div>
			<!--highlight menu-->
			<div class="highlight-wrapper">
				<?php
				$sql = "SELECT Restaurant_ID, Name, Rating FROM Restaurant ORDER BY Rating DESC LIMIT 3";
				$result = $conn->query($sql);
				
				if ($result && $result->num_rows > 0) {
					while ($row = $result->fetch_assoc()) {
						$cafeId = (int) $row['Restaurant_ID'];
						$cafeName = htmlspecialchars($row['Name'], ENT_QUOTES, 'UTF-8');
						$rating = (float) $row['Rating'];
						$shopImage = "material/" . $cafeName . "/shop.jpg";
						$starCount = (int) floor($rating);
						$hasHalf = ($rating - $starCount) >= 0.5;
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
		<!-- main menus / order -->
		<div class="main-menus">
			<!--filter section-->
			<div class="main-filter">
				<div>
					<h2 class="main-title">Menu<br>Category</h2>
				</div>
			
			<div class="filter-wrapper">
				<div class="filter-card active" data-filter="all">
					<div class="filter-icon">
						<ion-icon name="restaurant-outline"></ion-icon>
					</div>
					<p>All Cafe</p>
				</div>
				<div class="filter-card" data-filter="coffee">
					<div class="filter-icon">
						<ion-icon name="cafe-outline"></ion-icon>
					</div>
					<p>Coffee</p>
				</div>
				<div class="filter-card" data-filter="dessert">
					<div class="filter-icon">
						<ion-icon name="ice-cream-outline"></ion-icon>
					</div>
					<p>Dessert</p>
				</div>
					<div class="filter-card" data-filter="food">
						<div class="filter-icon">
							<ion-icon name="fast-food-outline"></ion-icon>
						</div>
						<p>Food</p>
					</div>
				</div>
			</div>
				<hr class="divider">
				<!--list of food menus-->
				<div class="main-detail">
					<h2 class="main-title">Choose Order</h2>
						<div class="detail-wrapper">
							<?php
							$sql = "SELECT * FROM Restaurant";
							$result = $conn->query($sql);

							if ($result->num_rows > 0) {
								while($row = $result->fetch_assoc()) {
									$cafeName = $row['Name'];
									$shopImage = "material/" . $cafeName . "/shop.jpg";
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
								<img class="detail-img" 
								src="<?php echo htmlspecialchars($shopImage, ENT_QUOTES, 'UTF-8'); ?>" 
								alt="Shop Image"
								>

								<div class="detail-desc">
									<div class="detail-name">
										<h4><?php echo $row['Name']; ?></h4>

										<p class="detail-sub">
											<?php echo $row['Address']; ?>
										</p>

										<p class="Rating">
											Rating <?php echo $row['Rating']; ?>
										</p>
									</div>
								</div>
							</div>
							</a>
							<?php
								}
							} else {
								echo "No restaurant found";
							}
							?>
						</div>
					</div>
				</div>
			</div>
