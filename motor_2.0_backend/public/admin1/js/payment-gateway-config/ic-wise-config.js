async function postFetch(url, data) {
    const response = await fetch(url, {
        body: data,
        method: "POST",
        headers: {
            "X-CSRF-TOKEN": document
                .querySelector('[name="csrf-token"]')
                .getAttribute("content"),
        },
    });
    response.response = await response.json();
    return response;
}

onload = () => {
    const getCreds = (fieldSection, formButtonSection, paymentGateway, insuranceCompany) => {
        fieldSection.innerHTML = "";
        fieldSection.classList.add("d-none");
        formButtonSection.classList.add("d-none");

        if (!(insuranceCompany.val() && paymentGateway.val())) {
            return;
        }

        let value = paymentGateway.val();
        let formData = new FormData();
        formData.append("paymentGateway", value);
        formData.append("type", "icWiseConfig");
        formData.append("insuranceCompany", insuranceCompany.val());

        postFetch(getFieldsUrl, formData).then((res) => {
            let data = res.response;
            if (data.status === true) {
                let fields = data.data;
                if (fields.length > 0) {
                    fields.forEach((element) => {
                        let div = document.createElement("div");
                        div.classList.add(
                            "col-12",
                            "col-md-6",
                            "col-lg-4",
                            "col-xl-3",
                            "form-group"
                        );
                        div.innerHTML = `
                                <label for="${element.key}">${
                            element.label
                        }</label>
                                <input type="text" id="${
                                    element.key
                                }" name="${
                                    element.key
                                }" class="form-control" value="${
                            element.value ? element.value : ""
                        }">`;
                        fieldSection.appendChild(div);
                    });
                }
                fieldSection.classList.remove("d-none");
                formButtonSection.classList.remove("d-none");
            }
        });
    };
    const paymentGateway = $("#paymentGateway");
    const insuranceCompany = $("#insuranceCompany");

    const fieldSection = document.querySelector(".field-section");
    const formButtonSection = document.querySelector(".form-btn-section");

    document
        .querySelector("#paymentGateway")
        .addEventListener(
            "change",
            () => getCreds(
                fieldSection,
                formButtonSection,
                paymentGateway,
                insuranceCompany
            )
        );

        document
            .querySelector("#insuranceCompany")
            .addEventListener("change", () =>
                getCreds(
                    fieldSection,
                    formButtonSection,
                    paymentGateway,
                    insuranceCompany
                )
            );
};
