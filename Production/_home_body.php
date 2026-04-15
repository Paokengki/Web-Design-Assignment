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
			<a href="#"><ion-icon name="chatbubbles-outline"></ion-icon>Contact Us</a>
			<a href="setting.php"><ion-icon name="settings-outline"></ion-icon>Setting</a>
		</div>
		<!-- logout -->
		<div class="sidebar-logout">
			<a href="login.html"><ion-icon name="log-out-outline"></ion-icon>Logout</a>
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
						<div class="detail-card">
							<a href="95 cafe menu.html" class="highlight-link">
							<img class="detail-img" src="material/95 Degres Art Cafe/shop.jpg">
							<div class="detail-desc">
								<div class="detail-name">
									<h4>95 Degres Art Cafe</h4>
									<p class="detail-sub">1, Jalan SS 15/8a, Ss 15, 47500 Subang Jaya, Selangor, Malaysia</p></br>
									<p class="Rating">Rating 4.8</p>  
									<ion-icon name="star"></ion-icon>
									<ion-icon name="star"></ion-icon>
									<ion-icon name="star"></ion-icon>
									<ion-icon name="star"></ion-icon>
									<ion-icon name="star-half"></ion-icon>
									</p>
									</a>
								</div>
								<ion-icon class="detail-favorites" name="bookmark-outline"></ion-icon>					
							</div>
						</div>
						<div class="detail-card">
							<img class="detail-img" src="material/Copper Pot Cafe/shop.jpg">
							<div class="detail-desc">
								<div class="detail-name">
									<a href="copper pot.html" class="highlight-link">
									<h4>Copper Pot Cafe</h4>
									<p class="detail-sub">23, Jalan USJ 11/4g, Subang Jaya, 47620 Subang Jaya, Selangor, Malaysia</p></br>
									<p class="Rating">Rating 4.7</p>  
									<ion-icon name="star"></ion-icon>
									<ion-icon name="star"></ion-icon>
									<ion-icon name="star"></ion-icon>
									<ion-icon name="star"></ion-icon>
									<ion-icon name="star-half"></ion-icon>
									</p>
									</a>
								</div>
								<ion-icon class="detail-favorites" name="bookmark-outline"></ion-icon>							
							</div>
						</div>
						<div class="detail-card">
							<img class="detail-img" src="material/Feeka coffee roasters/shop.jpg">
							<div class="detail-desc">
								<div class="detail-name">
									<a href="feeka coffee.html" class="highlight-link">
									<h4>Feeka coffee roasters</h4>
									<p class="detail-sub">53, Jalan SS 15/5a, Ss 15, 47500 Subang Jaya, Selangor, Malaysia</p></br>
									<p class="Rating">Rating 4.9</p>  
									<ion-icon name="star"></ion-icon>
									<ion-icon name="star"></ion-icon>
									<ion-icon name="star"></ion-icon>
									<ion-icon name="star"></ion-icon>
									<ion-icon name="star-half"></ion-icon>
									</p>
									</a>
								</div>
								<ion-icon class="detail-favorites" name="bookmark-outline"></ion-icon>	
							</div>
						</div>
						<div class="detail-card">
							<img class="detail-img" src="material/Good Friends Restaurant & Cafe/shop.jpg">
							<div class="detail-desc">
								<div class="detail-name">
									<a href="good friend.html" class="highlight-link">
									<h4>Good Friends Restaurant & Cafe</h4>
									<p class="detail-sub">10, Jalan USJ 10/1a, Subang Jaya, 47620 Subang Jaya, Selangor, Malaysia</p></br>
									<p class="Rating">Rating 4.3</p>  
									<ion-icon name="star"></ion-icon>
									<ion-icon name="star"></ion-icon>
									<ion-icon name="star"></ion-icon>
									<ion-icon name="star"></ion-icon>
									<ion-icon name="star-half"></ion-icon>
									</p>
									</a>
								</div>
								<ion-icon class="detail-favorites" name="bookmark-outline"></ion-icon>	
							</div>
						</div>
						<div class="detail-card">
							<img class="detail-img" src="material/JDV CAFE/shop.jpg">
							<div class="detail-desc">
								<div class="detail-name">
								<a href="JDV CAFE.html" class="highlight-link">
									<h4>JDV CAFE</h4>
									<p class="detail-sub">36, Jalan USJ 9/5p, Subang Jaya, 47620 Subang Jaya, Selangor, Malaysia</p></br>
									<p class="Rating">Rating 4.5</p>  
									<ion-icon name="star"></ion-icon>
									<ion-icon name="star"></ion-icon>
									<ion-icon name="star"></ion-icon>
									<ion-icon name="star"></ion-icon>
									<ion-icon name="star-half"></ion-icon>
									</p>
									</a>
								</div>
								<ion-icon class="detail-favorites" name="bookmark-outline"></ion-icon>	
							</div>
						</div>
						<div class="detail-card">
							<img class="detail-img" src="material/Lil' Bird & The Big Bear Cafe/shop.jpg">
							<div class="detail-desc">
								<div class="detail-name">
								<a href="lil bird.html" class="highlight-link">
									<h4>Lil' Bird & The Big Bear Cafe</h4>
									<p class="detail-sub">12, Jalan USJ 4/7g, Subang Jaya, 47600 Subang Jaya, Selangor, Malaysia</p></br>
									<p class="Rating">Rating 4.2</p>  
									<ion-icon name="star"></ion-icon>
									<ion-icon name="star"></ion-icon>
									<ion-icon name="star"></ion-icon>
									<ion-icon name="star"></ion-icon>
									<ion-icon name="star-half"></ion-icon>
									</p>
									</a>
								</div>
								<ion-icon class="detail-favorites" name="bookmark-outline"></ion-icon>	
							</div>
						</div>
						<div class="detail-card">
							<img class="detail-img" src="material/The Foxhole Bakery Cafe/shop.jpg">
							<div class="detail-desc">
								<div class="detail-name">
									<a href="the foxhole.html" class="highlight-link">
									<h4>The Foxhole Bakery Cafe</h4>
									<p class="detail-sub">20, Jalan USJ 1/3c, Subang Jaya, 47600 Subang Jaya, Selangor, Malaysia</p></br>
									<p class="Rating">Rating 4.1</p>  
									<ion-icon name="star"></ion-icon>
									<ion-icon name="star"></ion-icon>
									<ion-icon name="star"></ion-icon>
									<ion-icon name="star"></ion-icon>
									<ion-icon name="star-half"></ion-icon>
									</p>
									</a>
								</div>
								<ion-icon class="detail-favorites" name="bookmark-outline"></ion-icon>	
							</div>
						</div><div class="detail-card">
							<img class="detail-img" src="material/The Great Cafe/shop.jpg">
							<div class="detail-desc">
								<div class="detail-name">
									<a href="the great.html" class="highlight-link">
									<h4>The Great Cafe</h4>
									<p class="detail-sub">42, Jalan USJ 21/10, Subang Jaya, 47630 Subang Jaya, Selangor, Malaysia</p></br>
									<p class="Rating">Rating 4.45</p>  
									<ion-icon name="star"></ion-icon>
									<ion-icon name="star"></ion-icon>
									<ion-icon name="star"></ion-icon>
									<ion-icon name="star"></ion-icon>
									<ion-icon name="star-half"></ion-icon>
									</p>
									</a>
								</div>
								<ion-icon class="detail-favorites" name="bookmark-outline"></ion-icon>	
							</div>
						</div><div class="detail-card">
							<img class="detail-img" src="material/Upstairs Cafe/shop.jpg">
							<div class="detail-desc">
								<div class="detail-name">
									<a href="upstairs.html" class="highlight-link">
									<h4>Upstairs Cafe</h4>
									<p class="detail-sub">14, Jalan USJ 3/2d, Subang Jaya, 47610 Subang Jaya, Selangor, Malaysia</p></br>
									<p class="Rating">Rating 4.6</p>  
									<ion-icon name="star"></ion-icon>
									<ion-icon name="star"></ion-icon>
									<ion-icon name="star"></ion-icon>
									<ion-icon name="star"></ion-icon>
									<ion-icon name="star-half"></ion-icon>
									</p>
									</a>
								</div>
								<ion-icon class="detail-favorites" name="bookmark-outline"></ion-icon>	
							</div>
						</div>
					</div>
				</div>
			</div>
