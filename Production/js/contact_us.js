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

    // Perform client-side validation first so the form shows all errors at once.
    $form.on('submit', function (event) {
        var errors = [];
        var fields = [
            { name: 'name', validator: validateInput, message: 'Please enter your name.' },
            { name: 'phone', validator: validateInput, message: 'Please enter your phone number.' },
            { name: 'email', validator: validateEmail, message: 'Please enter a valid email address.' },
            { name: 'message', validator: validateInput, message: 'Please enter your message.' }
        ];

        $.each(fields, function (_, field) {
            var value = $form.find('[name="' + field.name + '"]').val();
            if (!field.validator(value)) {
                errors.push(field.message);
            }
        });

        if (errors.length > 0) {
            event.preventDefault();
            $messageArea.html(buildErrorList(errors));
            $('html, body').animate({ scrollTop: $messageArea.offset().top - 20 }, 200);
        }
    });
});
