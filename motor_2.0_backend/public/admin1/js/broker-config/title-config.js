document.getElementById('dataForm').addEventListener('submit', function(event) {
    event.preventDefault();

    const formData = {
        generic: {
            title: document.getElementById('genericTitle').value,
            description: document.getElementById('genericDescription').value
        },
        car: {
            title: document.getElementById('carTitle').value,
            description: document.getElementById('carDescription').value
        },
        bike: {
            title: document.getElementById('bikeTitle').value,
            description: document.getElementById('bikeDescription').value
        },
        cv: {
            title: document.getElementById('cvTitle').value,
            description: document.getElementById('cvDescription').value
        }
    };

    const jsonData = JSON.stringify(formData);

    fetch(this.action, {
        method: this.method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
        },
        body: jsonData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Data stored successfully!');
            // document.getElementById('dataForm').reset();
        } else {
            console.warn('Validation error:', data.message);
            alert('Error: ' + data.message);
        }
    })
    .catch((error) => {
        console.error('Error:', error);
    });
});