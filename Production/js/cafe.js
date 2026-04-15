$(function () {
    var $overlay = $('#menuModalOverlay');
    var $form = $('#menuModalForm');
    var $modalTitle = $('#modalItemTitle');
    var $qtyInput = $('#itemQty');
    var $drinkOptions = $('#drinkOptions');
    var $remarkInput = $('#itemRemark');
    var currentItem = null;

    function openModal(itemName, itemType, isDrink) {
        currentItem = {
            name: itemName,
            type: itemType,
            isDrink: isDrink
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

    $(document).on('click', '.menu-item-trigger', function () {
        var $btn = $(this);
        openModal(
            $btn.data('item-name') || 'Item',
            $btn.data('item-type') || 'Food / Beverage',
            String($btn.data('is-drink')) === '1'
        );
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

    $overlay.on('click', function (event) {
        if (event.target === this) {
            closeModal();
        }
    });

    $(document).on('keydown', function (event) {
        if (event.key === 'Escape' && $overlay.hasClass('open')) {
            closeModal();
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

        alert('Added to order:\n' + summary);
        closeModal();
    });
});
