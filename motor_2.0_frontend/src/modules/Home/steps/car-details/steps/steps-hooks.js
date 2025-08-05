import { useEffect } from "react";
import moment from "moment";
import { set_temp_data, SaveQuoteData } from "modules/Home/home.slice";

//Year
export const useAutoTrigger = (dispatch, urlParams, varParams) => {
  const { token, enquiry_id } = urlParams;
  const { temp_data, saod, year, yearArray, diffMonthsRollOver } = varParams;
  const businessTypeValue =
    temp_data?.journeyType === 3 ||
    diffMonthsRollOver === 0 ||
    diffMonthsRollOver < 9
      ? "newbusiness"
      : temp_data?.breakIn
      ? "breakin"
      : "rollover";

  useEffect(() => {
    if (year) {
      dispatch(
        set_temp_data({
          vehicleInvoiceDate: `${
            Number(new Date().getFullYear()) !==
            Number(year === "Brand New" ? new Date().getFullYear() : year)
              ? `01`
              : moment().format("DD-MM-YYYY").split("-")[0]
          }-${
            year === "Brand New"
              ? moment().format("DD-MM-YYYY").split("-")[1]
              : Number(year) === Number(new Date().getFullYear()) &&
                yearArray.includes("Brand New")
              ? "01"
              : moment().format("DD-MM-YYYY").split("-")[1]
          }-${
            moment(
              year === "Brand New" ? String(new Date().getFullYear()) : year
            )
              .format("DD-MM-YYYY")
              .split("-")[2]
          }`,
          regDate: `${
            Number(new Date().getFullYear()) !==
            Number(year === "Brand New" ? new Date().getFullYear() : year)
              ? `01`
              : moment().format("DD-MM-YYYY").split("-")[0]
          }-${
            year === "Brand New"
              ? moment().format("DD-MM-YYYY").split("-")[1]
              : Number(year) === Number(new Date().getFullYear()) &&
                yearArray.includes("Brand New")
              ? "01"
              : moment().format("DD-MM-YYYY").split("-")[1]
          }-${
            moment(
              year === "Brand New" ? String(new Date().getFullYear()) : year
            )
              .format("DD-MM-YYYY")
              .split("-")[2]
          }`,
          manfDate: `${
            year === "Brand New"
              ? moment().format("DD-MM-YYYY").split("-")[1]
              : Number(year) === Number(new Date().getFullYear()) &&
                yearArray.includes("Brand New")
              ? "01"
              : moment().format("DD-MM-YYYY").split("-")[1]
          }-${
            moment(
              year === "Brand New" ? String(new Date().getFullYear()) : year
            )
              .format("DD-MM-YYYY")
              .split("-")[2]
          }`,
          regNo: temp_data?.regNo,
          // ownerTypeId: null,
        })
      );

      dispatch(
        set_temp_data({
          newCar:
            temp_data?.journeyType === 3 ||
            diffMonthsRollOver === 0 ||
            diffMonthsRollOver < 9
              ? true
              : false,
          leadJourneyEnd: false,
          odOnly: saod(
            `${
              Number(new Date().getFullYear()) !==
              Number(year === "Brand New" ? new Date().getFullYear() : year)
                ? `01`
                : moment().format("DD-MM-YYYY").split("-")[0]
            }-${
              year === "Brand New"
                ? moment().format("DD-MM-YYYY").split("-")[1]
                : Number(year) === Number(new Date().getFullYear()) &&
                  yearArray.includes("Brand New")
                ? "01"
                : moment().format("DD-MM-YYYY").split("-")[1]
            }-${
              moment(
                year === "Brand New" ? String(new Date().getFullYear()) : year
              )
                .format("DD-MM-YYYY")
                .split("-")[2]
            }`
          ),
        })
      );
      let quoteData = {
        stage: "9",
        vehicleRegisterDate:
          year &&
          `${
            Number(new Date().getFullYear()) !==
            Number(year === "Brand New" ? new Date().getFullYear() : year)
              ? `01`
              : moment().format("DD-MM-YYYY").split("-")[0]
          }-${
            year === "Brand New"
              ? moment().format("DD-MM-YYYY").split("-")[1]
              : Number(year) === Number(new Date().getFullYear()) &&
                yearArray.includes("Brand New")
              ? "01"
              : moment().format("DD-MM-YYYY").split("-")[1]
          }-${
            moment(
              year === "Brand New" ? String(new Date().getFullYear()) : year
            )
              .format("DD-MM-YYYY")
              .split("-")[2]
          }`,
        vehicleInvoiceDate:
          year &&
          `${
            Number(new Date().getFullYear()) !==
            Number(year === "Brand New" ? new Date().getFullYear() : year)
              ? `01`
              : moment().format("DD-MM-YYYY").split("-")[0]
          }-${
            year === "Brand New"
              ? moment().format("DD-MM-YYYY").split("-")[1]
              : Number(year) === Number(new Date().getFullYear()) &&
                yearArray.includes("Brand New")
              ? "01"
              : moment().format("DD-MM-YYYY").split("-")[1]
          }-${
            moment(
              year === "Brand New" ? String(new Date().getFullYear()) : year
            )
              .format("DD-MM-YYYY")
              .split("-")[2]
          }`,
        manufactureYear:
          year &&
          `${
            year === "Brand New"
              ? moment().format("DD-MM-YYYY").split("-")[1]
              : Number(year) === Number(new Date().getFullYear()) &&
                yearArray.includes("Brand New")
              ? "01"
              : moment().format("DD-MM-YYYY").split("-")[1]
          }-${
            moment(
              year === "Brand New" ? String(new Date().getFullYear()) : year
            )
              .format("DD-MM-YYYY")
              .split("-")[2]
          }`,
        rtoNumber: temp_data?.rtoNumber,
        rto: temp_data?.rtoNumber,
        vehicleRegisterAt: temp_data?.rtoNumber,
        seatingCapacity: temp_data?.seatingCapacity,
        version: temp_data?.versionId,
        versionName: temp_data?.versionName,
        vehicleRegistrationNo: temp_data?.regNo,
        fuelType: temp_data?.fuel,
        vehicleLpgCngKitValue: temp_data?.kit_val ? temp_data?.kit_val : null,
        model: temp_data?.modelId,
        modelName: temp_data?.modelName,
        manfactureId: temp_data?.manfId,
        manfactureName: temp_data?.manfName,
        enquiryId: enquiry_id,
        userProductJourneyId: enquiry_id,
        policyType: saod(
          `${
            Number(new Date().getFullYear()) !==
            Number(year === "Brand New" ? new Date().getFullYear() : year)
              ? `01`
              : moment().format("DD-MM-YYYY").split("-")[0]
          }-${
            year === "Brand New"
              ? moment().format("DD-MM-YYYY").split("-")[1]
              : Number(year) === Number(new Date().getFullYear()) &&
                yearArray.includes("Brand New")
              ? "01"
              : moment().format("DD-MM-YYYY").split("-")[1]
          }-${
            moment(
              year === "Brand New" ? String(new Date().getFullYear()) : year
            )
              .format("DD-MM-YYYY")
              .split("-")[2]
          }`
        )
          ? "own_damage"
          : "comprehensive",
        businessType: businessTypeValue,
        policyExpiryDate: businessTypeValue === "newbusiness" ? "New" : "",
      };
      if (
        temp_data?.journeyType === 3 ||
        diffMonthsRollOver === 0 ||
        diffMonthsRollOver < 9
      ) {
        dispatch(
          SaveQuoteData({
            ...quoteData,
            previousNcb: 0,
            applicableNcb: 0,
            ...(token && { token: token }),
          })
        );
      } else {
        dispatch(
          SaveQuoteData({ ...quoteData, ...(token && { token: token }) })
        );
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [year]);
};
