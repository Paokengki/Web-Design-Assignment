// jian how see this file for reference only, not to be used in the project
// cafe.js - JavaScript for cafe menu and cart interactions
$(function () {
    var $overlay = $('#menuModalOverlay');
    var $form = $('#menuModalForm');
    var $modalTitle = $('#modalItemTitle');
    var $qtyInput = $('#itemQty');
    var $drinkOptions = $('#drinkOptions');
    var $remarkInput = $('#itemRemark');
    var $cartOverlay = $('#cartModalOverlay');
    var $cartItemsContainer = $('#cartItemsContainer');
    var $cartSubtotal = $('#cartSubtotal');
    var $cartSst = $('#cartSst');
    var $cartGrandTotal = $('#cartGrandTotal');
    var currentItem = null;

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function formatMoney(value) {
        var amount = Number(value) || 0;
        return 'RM ' + amount.toFixed(2);
    }

    function callCartApi(action, payload) {
        return $.ajax({
            url: 'cart_actions.php',
            method: 'POST',
            dataType: 'json',
            data: $.extend({ action: action }, payload || {})
        });
    }

    function openModal(itemName, itemType, isDrink, itemAmount) {
        currentItem = {
            name: itemName,
            type: itemType,
            isDrink: isDrink,
            amount: Number(itemAmount) || 0
        };

        $modalTitle.text(itemName);
        $qtyInput.val(1);
        $remarkInput.val('');
        $form.find('input[name="sugar"][value="Normal"]').prop('checked', true);
        $form.find('input[name="ice"][value="Normal"]').prop('checked', true);

        if (isDrink) {
            $drinkOptions.removeClass('hidden');
        } else {
            $drinkOptions.addClass('hidden');
        }

        $overlay.addClass('open').attr('aria-hidden', 'false');
    }

    function closeModal() {
        $overlay.removeClass('open').attr('aria-hidden', 'true');
        currentItem = null;
    }

    function closeCartModal() {
        $cartOverlay.removeClass('open').attr('aria-hidden', 'true');
    }

    function renderCart(data) {
        var items = (data && data.items) ? data.items : [];
        var html = '';

        if (items.length === 0) {
            html = '<p class="cart-empty">Your cart is empty.</p>';
        } else {
            $.each(items, function (_, item) {
                var options = [];
                if (item.sugar) {
                    options.push('Sugar: ' + escapeHtml(item.sugar));
                }
                if (item.ice) {
                    options.push('Ice: ' + escapeHtml(item.ice));
                }
                if (item.remark) {
                    options.push('Remark: ' + escapeHtml(item.remark));
                }

                html += '<div class="cart-item-row">';
                html += '  <div class="cart-item-main">';
                html += '    <p class="cart-item-name">' + escapeHtml(item.itemName) + '</p>';
                html += '    <p class="cart-item-meta">Qty: ' + escapeHtml(item.quantity) + ' x ' + formatMoney(item.unitAmount) + '</p>';
                if (options.length > 0) {
                    html += '    <p class="cart-item-options">' + options.join(' | ') + '</p>';
                }
                html += '    <div class="cart-item-controls">';
                html += '      <button type="button" class="cart-mini-btn cart-qty-minus" data-cart-id="' + escapeHtml(item.cartId) + '" data-qty="' + escapeHtml(item.quantity) + '">-</button>';
                html += '      <span class="cart-qty-value">' + escapeHtml(item.quantity) + '</span>';
                html += '      <button type="button" class="cart-mini-btn cart-qty-plus" data-cart-id="' + escapeHtml(item.cartId) + '" data-qty="' + escapeHtml(item.quantity) + '">+</button>';
                html += '      <button type="button" class="cart-remove-btn" data-cart-id="' + escapeHtml(item.cartId) + '">Remove</button>';
                html += '    </div>';
                html += '  </div>';
                html += '  <div class="cart-item-total">' + formatMoney(item.lineTotal) + '</div>';
                html += '</div>';
            });
        }

        $cartItemsContainer.html(html);
        $cartSubtotal.text(formatMoney(data.subtotal));
        $cartSst.text(formatMoney(data.sst));
        $cartGrandTotal.text(formatMoney(data.grandTotal));
    }

    function openCartModal() {
        $.ajax({
            url: 'cart_actions.php',
            method: 'GET',
            dataType: 'json',
            data: { action: 'get' }
        }).done(function (response) {
            if (!response || !response.success) {
                alert('Unable to load cart now.');
                return;
            }

            renderCart(response);
            $cartOverlay.addClass('open').attr('aria-hidden', 'false');
        }).fail(function () {
            alert('Unable to load cart now.');
        });
    }

    $(document).on('click', '.menu-item-trigger', function () {
        var $btn = $(this);
        openModal(
            $btn.data('item-name') || 'Item',
            $btn.data('item-type') || 'Food / Beverage',
            String($btn.data('is-drink')) === '1',
            $btn.data('item-amount') || 0
        );
    });

    $('#openCartBtn').on('click', function (event) {
        event.preventDefault();
        openCartModal();
    });

    $('#qtyMinus').on('click', function () {
        var current = Math.max(1, parseInt($qtyInput.val() || '1', 10));
        $qtyInput.val(Math.max(1, current - 1));
    });

    $('#qtyPlus').on('click', function () {
        var current = Math.max(1, parseInt($qtyInput.val() || '1', 10));
        $qtyInput.val(current + 1);
    });

    $qtyInput.on('change', function () {
        var current = parseInt($qtyInput.val() || '1', 10);
        $qtyInput.val(isNaN(current) || current < 1 ? 1 : current);
    });

    $('#closeModalBtn').on('click', function () {
        closeModal();
    });

    $('#closeCartBtn').on('click', function () {
        closeCartModal();
    });

    $cartItemsContainer.on('click', '.cart-remove-btn', function () {
        var cartId = String($(this).data('cart-id') || '');
        if (cartId === '') {
            return;
        }

        callCartApi('remove', { cart_id: cartId }).done(function (response) {
            if (!response || !response.success) {
                alert('Failed to remove item.');
                return;
            }

            renderCart(response);
        }).fail(function () {
            alert('Failed to remove item.');
        });
    });

    $cartItemsContainer.on('click', '.cart-qty-minus, .cart-qty-plus', function () {
        var $btn = $(this);
        var cartId = String($btn.data('cart-id') || '');
        var currentQty = Math.max(1, parseInt($btn.data('qty') || '1', 10));
        var nextQty = currentQty;

        if ($btn.hasClass('cart-qty-minus')) {
            nextQty = Math.max(1, currentQty - 1);
        } else {
            nextQty = currentQty + 1;
        }

        if (cartId === '' || nextQty === currentQty) {
            return;
        }

        callCartApi('update', {
            cart_id: cartId,
            quantity: nextQty
        }).done(function (response) {
            if (!response || !response.success) {
                alert('Failed to update quantity.');
                return;
            }

            renderCart(response);
        }).fail(function () {
            alert('Failed to update quantity.');
        });
    });

    $overlay.on('click', function (event) {
        if (event.target === this) {
            closeModal();
        }
    });

    $cartOverlay.on('click', function (event) {
        if (event.target === this) {
            closeCartModal();
        }
    });

    $(document).on('keydown', function (event) {
        if (event.key === 'Escape') {
            if ($overlay.hasClass('open')) {
                closeModal();
            }
            if ($cartOverlay.hasClass('open')) {
                closeCartModal();
            }
        }
    });

    $form.on('submit', function (event) {
        event.preventDefault();
        if (!currentItem) {
            return;
        }

        var quantity = Math.max(1, parseInt($qtyInput.val() || '1', 10));
        var remark = $.trim($remarkInput.val());
        var summary = currentItem.name + ' x' + quantity;

        if (currentItem.isDrink) {
            var sugar = $form.find('input[name="sugar"]:checked').val() || 'Normal';
            var ice = $form.find('input[name="ice"]:checked').val() || 'Normal';
            summary += '\nSugar: ' + sugar + ' | Ice: ' + ice;
        }

        if (remark !== '') {
            summary += '\nRemark: ' + remark;
        }

        callCartApi('add', {
            item_name: currentItem.name,
            item_type: currentItem.type,
            quantity: quantity,
            unit_amount: currentItem.amount,
            sugar: currentItem.isDrink ? ($form.find('input[name="sugar"]:checked').val() || 'Normal') : '',
            ice: currentItem.isDrink ? ($form.find('input[name="ice"]:checked').val() || 'Normal') : '',
            remark: remark
        }).done(function (response) {
            if (!response || !response.success) {
                alert('Failed to add item to cart.');
                return;
            }

            alert('Added to order:\n' + summary);
            closeModal();
        }).fail(function () {
            alert('Failed to add item to cart.');
        });
    });
});
