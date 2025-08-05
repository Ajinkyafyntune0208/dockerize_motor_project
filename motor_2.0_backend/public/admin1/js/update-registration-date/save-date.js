$(document).ready(function () {

    let isChanged = false;

    // Store initial values
    let initialRegistrationDate = $('#registration_date').val();
    let initialManufactureDate = $('#manufacture_date').val();
    let initialInvoiceDate = $('#invoice_date').val();

    $('.update-field').on('change', function () {
        let currentRegistrationDate = $('#registration_date').val();
        let currentManufactureDate = $('#manufacture_date').val();
        let currentInvoiceDate = $('#invoice_date').val(); 

        // Show update button only if the values have changed
        if (currentRegistrationDate !== initialRegistrationDate || currentManufactureDate !== initialManufactureDate || currentInvoiceDate !== initialInvoiceDate) {
            $('#updateBtn').show();
            isChanged = true;
        } else {
            $('#updateBtn').hide();
            isChanged = false;
        }
    });

    $('#updateBtn').on('click', function () {
        if (!isChanged) return;

        let enquiryId = $("input[name='enquiry_id']").val();
        let registrationDate = $('#registration_date').val();
        let manufactureDate = $('#manufacture_date').val();
        let invoiceDate = $('#invoice_date').val();

        $.ajax({
            url: saveDate,
            method: "POST",
            data: {
                _token: document.querySelector('[name="csrf-token"]').getAttribute("content"),
                enquiry_id: enquiryId,
                registration_date: registrationDate,
                manufacture_date: manufactureDate,
                invoice_date: invoiceDate
            },
            success: function (response) {
                if (response.success) {

                    $('#successMessage').text(response.message).show();
                    $('#failureMessage').hide();
                    $('#updateBtn').hide();
                    isChanged = false;

                    setTimeout(() => {
                        $('#successMessage').fadeOut();
                    }, 3000);

                } else {

                    $('#failureMessage').text(response.message).show();

                    setTimeout(() => {
                        $('#failureMessage').fadeOut();
                    }, 3000);

                    location.reload();
                }
            },
            error: function () {
                $('#failureMessage').text("Error updating dates. Please try again").show();
                setTimeout(() => {
                    $('#failureMessage').fadeOut();
                }, 3000);
            }
        });
    });
});