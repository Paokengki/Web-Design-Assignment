$(function () {
    var $filterCards = $('.filter-card[data-filter]');
    var $restaurantLinks = $('.detail-card-link[data-category]');
    var $detailWrapper = $('.detail-wrapper').first();
    var $searchInput = $('.search input[type="text"]').first();
    var $searchButton = $('.search-btn').first();
    
    function runCafeSearch() {
        var keyword = $.trim($searchInput.val() || '');

        if (keyword === '') {
            alert('Please enter a cafe name.');
            $searchInput.trigger('focus');
            return;
        }

        $.ajax({
            url: 'search_cafe.php',
            method: 'GET',
            dataType: 'json',
            data: { q: keyword }
        }).done(function (response) {
            if (response && response.success && response.found && response.cafeId) {
                window.location.href = 'cafe.php?id=' + encodeURIComponent(response.cafeId);
                return;
            }

            alert('No cafe found for "' + keyword + '".');
        }).fail(function () {
            alert('Unable to search cafe right now. Please try again.');
        });
    }

    if ($searchInput.length > 0 && $searchButton.length > 0) {
        $searchButton.on('click', function () {
            runCafeSearch();
        });

        $searchInput.on('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                runCafeSearch();
            }
        });
    }

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
