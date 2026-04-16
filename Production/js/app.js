$(function () {
    var $filterCards = $('.filter-card[data-filter]');
    var $restaurantLinks = $('.detail-card-link[data-category]');
    var $detailWrapper = $('.detail-wrapper').first();

    if ($filterCards.length === 0 || $restaurantLinks.length === 0 || $detailWrapper.length === 0) {
        return;
    }

    var $emptyMessage = $('<p class="filter-empty" style="display:none;">No restaurants found for this category.</p>');
    $detailWrapper.after($emptyMessage);

    function applyFilter(filterValue) {
        var visibleCount = 0;

        $restaurantLinks.each(function () {
            var $item = $(this);
            var itemCategory = String($item.data('category') || '').toLowerCase();
            var shouldShow = filterValue === 'all' || itemCategory === filterValue;

            if (shouldShow) {
                $item.show();
                visibleCount += 1;
            } else {
                $item.hide();
            }
        });

        if (visibleCount === 0) {
            $emptyMessage.show();
        } else {
            $emptyMessage.hide();
        }
    }

    $filterCards.on('click', function () {
        var $card = $(this);
        var selectedFilter = String($card.data('filter') || 'all').toLowerCase();

        $filterCards.removeClass('active');
        $card.addClass('active');
        applyFilter(selectedFilter);
    });

    applyFilter('all');
});
