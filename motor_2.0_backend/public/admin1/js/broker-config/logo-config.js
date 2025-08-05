const logoForm = document.querySelector('[name="logoForm"]')

logoForm.addEventListener('submit', (e) => {
    e.preventDefault();
    let form = e.target
    let formData = new FormData(form)

    postFetch(brokerLogoUrl, formData).then((res) => {
        let response = res.response
        alert(response.message)
        if (response.status) {
            location.reload()
        }
    })
})

async function postFetch(url, data)
{
    const response = await fetch(url, {
        body : data,
        method : 'POST',
        headers : {
            'X-CSRF-TOKEN' : document.querySelector('[name="csrf-token"]').getAttribute('content')
        }
        
    });
    response.response = await response.json();
    return response;
}

function viewClicked(element)
{
    document.querySelector('.broker-logo-title').innerHTML = element.getAttribute('data-modal-title')
    document.querySelector('.broker-logo-img').setAttribute('src', element.getAttribute('data-src'))
    $('#brokerLogoModal').modal('show');
}

function closeModal()
{
    $('#brokerLogoModal').modal('hide');
}