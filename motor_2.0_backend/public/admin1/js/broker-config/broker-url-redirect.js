$(document).ready(function() {
    
    function toggleVisibility() {
        if ($('#logo_url').is(':checked')) {
            $('.logo_url').show();
        } else {
            $('.logo_url').hide();
        }

        if ($('#success_payment_url_redirection').is(':checked')) {
            $('.success_payment_url_redirection').show();
        } else {
            $('.success_payment_url_redirection').hide();
        }

        if ($('#other_failure_url').is(':checked')) {
            $('.other_failure_url').show();
        } else {
            $('.other_failure_url').hide();
        }
    }

    toggleVisibility();

    $('#logo_url').change(function() {
        toggleVisibility();
    });
    
    $('#success_payment_url_redirection').change(function() {
        toggleVisibility();
    });

    $('#other_failure_url').change(function() {
        toggleVisibility();
    });
});