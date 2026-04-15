$(function () {
    var $form = $('#contactForm');
    var $messageArea = $('#formErrorMessages');

    function escapeHtml(value) {
        return $('<div>').text(value).html();
    }

    function buildErrorList(errors) {
        var listItems = errors.map(function (message) {
            return '<li>' + escapeHtml(message) + '</li>';
        });

        return '<div class="error-box"><ul>' + listItems.join('') + '</ul></div>';
    }

    function validateInput(value) {
        return $.trim(value) !== '';
    }

    function validateEmail(value) {
        return /^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}$/i.test($.trim(value));
    }

    $form.on('submit', function (event) {
        var errors = [];
        var name = $form.find('[name="name"]').val();
        var phone = $form.find('[name="phone"]').val();
        var email = $form.find('[name="email"]').val();
        var message = $form.find('[name="message"]').val();

        if (!validateInput(name)) {
            errors.push('Please enter your name.');
        }

        if (!validateInput(phone)) {
            errors.push('Please enter your phone number.');
        }

        if (!validateEmail(email)) {
            errors.push('Please enter a valid email address.');
        }

        if (!validateInput(message)) {
            errors.push('Please enter your message.');
        }

        if (errors.length > 0) {
            event.preventDefault();
            $messageArea.html(buildErrorList(errors));
            $('html, body').animate({ scrollTop: $messageArea.offset().top - 20 }, 200);
        }
    });
});
