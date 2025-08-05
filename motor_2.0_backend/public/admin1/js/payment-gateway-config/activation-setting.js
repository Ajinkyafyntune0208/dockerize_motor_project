

async function postFetch(url, data) {
    const response = await fetch(url, {
        body: data,
        method: 'POST',
        headers:{
            'X-CSRF-TOKEN' : document.querySelector('[name="csrf-token"]').getAttribute('content')
        }
    });
    response.response = await response.json();
    return response;
}

onload = ()=>{
    const paymentGateway = $("#paymentGateway");
    const configType = $("#configType");

    const formButtonSection = document.querySelector('.form-btn-section');
    const configTypeSection = document.querySelector('.config-type-section');

    document.querySelector('#paymentGateway').addEventListener('change', (e) => {
        formButtonSection.classList.add('d-none');
        configTypeSection.classList.add('d-none');

        configType.empty();
        configType.selectpicker('refresh')

        let value = paymentGateway.val();
        let formData = new FormData()
        formData.append('paymentGateway', value)


        postFetch(getCOnfigTypeUrl, formData).then((res) => {
            let data = res.response;
            if(data.status === true) {
                let fields = data.data;
                let configMethods = fields.configMethods;
                let selectedConfig = fields.selectedConfig;

                if (configMethods) {
                    const emptyOption = new Option('Please select', '');
                    configType.append(emptyOption);
                    emptyOption.disabled = true;
                    emptyOption.selected = true;

                    for (const key in configMethods) {
                        const newOption = new Option(configMethods[key], key);
                        if (selectedConfig == key) {
                            newOption.selected = true;
                        }
                        configType.append(newOption);
                    }
                }

                configType.selectpicker('refresh')

                formButtonSection.classList.remove('d-none')
                configTypeSection.classList.remove('d-none')
            }
        })

    })
}