$(document).ready(function() {
    $('#enableCheckbox').on('change', function() {
        if ($(this).is(':checked')) {
            $('#extraOptions').slideDown();
        } else {
            $('#extraOptions').slideUp();
        }
    });
});