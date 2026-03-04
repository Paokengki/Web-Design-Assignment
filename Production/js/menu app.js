//For Shopping Cart part
// for cart popup
function toggleCartPopup(){
	const cartPopup = document.getElementById('cart-popup');
	cartPopup.classList.toggle('active');
}

//for close cart()
function closeCart() {
	const cartPopup = document.getElementById('cart-popup');
	cartPopup.classList.remove('active');
}

//for add to cart Button
function addToCart(itemName, itemPrice) {
	const cartItems = document.getElementById('cart-items').getElementsByTagName('tbody')[0];
	const existingItem = Array.from(cartItems.getElementsByTagName('tr')).find(item => item.cells[0].textContent === itemName);

	if (existingItem) {
		const itemCount = parseInt(existingItem.querySelector('.item-count').textContent) + 1;
		existingItem.querySelector('.item-count').textContent = itemCount;

		const itemTotal = parseFloat(existingItem.querySelector('.item-total').textContent) + parseFloat(itemPrice);
		existingItem.querySelector('.item-total').textContent = itemTotal.toFixed(2);
	} else {
		const newRow = cartItems.insertRow();
		newRow.innerHTML = `
			<td>${itemName}</td>
			<td class='item-count'>1</td>
			<td class='item-price'>${itemPrice}</td>
			<td class='item-total'>${itemPrice}</td>
		`;
	}
	updateCartCountAndTotal();
}

//Remove from cart Button
function removeFromCart(itemName, itemPrice) {
    const cartItems = document.getElementById('cart-items').getElementsByTagName('tbody')[0];
    const existingItem = Array.from(cartItems.getElementsByTagName('tr')).find(item => item.cells[0].textContent === itemName);

    if (existingItem) {
        const itemCount = parseInt(existingItem.querySelector('.item-count').textContent);
        if (itemCount > 1) {
            existingItem.querySelector('.item-count').textContent = itemCount - 1;
            const itemTotal = parseFloat(existingItem.querySelector('.item-total').textContent) - parseFloat(existingItem.querySelector('.item-price').textContent);
            existingItem.querySelector('.item-total').textContent = itemTotal.toFixed(2);
        } else {
            cartItems.removeChild(existingItem);
        }
        updateCartCountAndTotal(); 
	}
}

//update cart count and total
function updateCartCountAndTotal() {
    const cartCount = document.getElementById('cart-count');
    const cartTotal = document.getElementById('cart-total');
    const cartItems = document.querySelectorAll('#cart-items tbody tr');
    let totalCount = 0;
    let total = 0;

    cartItems.forEach(item => {
        const itemCount = parseInt(item.querySelector('.item-count').textContent);
        const itemTotal = parseFloat(item.querySelector('.item-total').textContent);
        totalCount += itemCount;
        total += itemTotal;
    });

    cartCount.textContent = totalCount;
    cartTotal.textContent = total.toFixed(2);
}
