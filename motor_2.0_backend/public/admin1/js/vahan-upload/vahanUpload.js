
document.addEventListener("DOMContentLoaded", function () {
    const success = document.getElementById("successMessage");
    const error = document.getElementById("failureMessage");

    if (success) {
        setTimeout(() => {
            success.style.display = "none";
        }, 3000);
    }

    if (error) {
        setTimeout(() => {
            error.style.display = "none";
        }, 3000);
    }
});

$("#data-table").DataTable({
    "responsive": true,
    "lengthChange": true,
    "autoWidth": false,
    "scrollX": true,
}).buttons().container().appendTo('#form');
