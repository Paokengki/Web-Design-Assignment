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
			<a href="login.php"><ion-icon name="log-out-outline"></ion-icon>Logout</a>
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
				<h2 class="main-title">Recommendations</h2>
				<div class="main-arrow">
					<ion-icon class="back" name="chevron-back-circle-outline"></ion-icon>
					<ion-icon class="next" name="chevron-forward-circle-outline"></ion-icon>
				</div>
			</div>
			<!--highlight menu-->
			<div class="highlight-wrapper">
				<a href="feeka coffee.html" class="highlight-link">
				<div class="highlight-card">
					<a href="feeka coffee.html" class="highlight-link">
					<img class="highlight-img" src="material/Feeka coffee roasters/shop.jpg">
					<div class="highlight-desc">	
						<h4>Feeka Coffee Roasters</h4>
						<p>Rating 4.9 <ion-icon name="star"></ion-icon>
						<ion-icon name="star"></ion-icon>
						<ion-icon name="star"></ion-icon>
						<ion-icon name="star"></ion-icon>
						<ion-icon name="star-half"></ion-icon>
						</p>
						</a>
					</div>
										
				</div>
				<div class="highlight-card">
					<a href="95 cafe menu.html" class="highlight-link">
					<img class="highlight-img" src="material/95 Degres Art Cafe/shop.jpg">
					<div class="highlight-desc">
						<h4>95 Degres Art Cafe</h4>
						<p>Rating 4.8 <ion-icon name="star"></ion-icon>
						<ion-icon name="star"></ion-icon>
						<ion-icon name="star"></ion-icon>
						<ion-icon name="star"></ion-icon>
						<ion-icon name="star-half"></ion-icon>
						</p>
						</a>
					</div>
				</div>
				<div class="highlight-card">
					<a href="copper pot.html" class="highlight-link">
					<img class="highlight-img" src="material/Copper Pot Cafe/shop.jpg">
					<div class="highlight-desc">
						<h4>Copper Pot Cafe</h4>
						<p>Rating 4.7 <ion-icon name="star"></ion-icon>
						<ion-icon name="star"></ion-icon>
						<ion-icon name="star"></ion-icon>
						<ion-icon name="star"></ion-icon>
						<ion-icon name="star-half"></ion-icon>
						</p>
						</a>
					</div>
				</div>
				
			</div>
		</div>
		<!-- main menus / order -->
		<div class="main-menus">
			<!--filter section-->
			<div class="main-filter'>
				<div>
					<h2 class="main-title">Menu<br>Category</h2>
					<div class="main-arrow">
						<ion-icon class="back-menus" name="chevron-back-circle-outline"></ion-icon>
						<ion-icon class="next-menus" name="chevron-forward-circle-outline"></ion-icon>
					</div>
				</div>
			
			<div class="filter-wrapper">
				<div class="filter-card">
					<div class="filter-icon">
						<ion-icon name="restaurant-outline"></ion-icon>
					</div>
					<p>All Cafe</p>
				</div>
				<div class="filter-card">
					<div class="filter-icon">
						<ion-icon name="cafe-outline"></ion-icon>
					</div>
					<p>Coffee</p>
				</div>
				<div class="filter-card">
					<div class="filter-icon">
						<ion-icon name="ice-cream-outline"></ion-icon>
					</div>
					<p>Dessert</p>
				</div>
					<div class="filter-card">
						<div class="filter-icon">
							<ion-icon name="fast-food-outline"></ion-icon>
						</div>
						<p>Food</p>
					</div>
				</div>
				<hr class="divider">
				<!--list of food menus-->
				<div class="main-detail">
					<h2 class="main-title">Choose Order</h2>
						<div class="detail-wrapper">
							<?php
							$cafeImageFolders = [
								"95 Degres Art Cafe" => "95 Degres Art Cafe",
								"Copper Pot Cafe" => "Copper Pot Cafe",
								"Feeka Coffee Roasters" => "Feeka coffee roasters",
								"Good Friends Restaurant & Cafe" => "Good Friends Restaurant & Cafe",
								"JDV Cafe" => "JDV CAFE",
								"Lil' Bird & The Big Bear Cafe" => "Lil' Bird & The Big Bear Cafe",
								"The Foxhole Bakery Cafe" => "The Foxhole Bakery Cafe",
								"The Great Cafe" => "The Great Cafe",
								"Upstairs Cafe" => "Upstairs Cafe"
							];
							?>
							<?php
							if ($result->num_rows > 0) {
								while($row = $result->fetch_assoc()) {
									$cafeName = $row['Name'];
									$imageFolder = isset($cafeImageFolders[$cafeName]) ? $cafeImageFolders[$cafeName] : "images";
									$imageSrc = "material/" . $imageFolder . "/shop.jpg";
							?>
									<a class="detail-card-link" href="cafe.php?id=<?php echo (int) $row['Restaurant_ID']; ?>">
									<div class="detail-card">
										<img class="detail-img" src="<?php echo htmlspecialchars($imageSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($cafeName, ENT_QUOTES, 'UTF-8'); ?>">

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
