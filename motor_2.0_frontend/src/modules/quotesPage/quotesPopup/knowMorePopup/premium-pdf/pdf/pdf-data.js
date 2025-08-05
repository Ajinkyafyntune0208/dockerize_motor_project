/* eslint-disable no-useless-computed-key */
import { getLogoUrl, getPolicyType } from "modules/quotesPage/quoteUtil";
import _ from "lodash";
import { ContactFn, brokerEmailFunction } from "components";
import {
  _getBusinessType,
  _getPolicyTypeData,
  _getPreviousPolicyExpiry,
  _getVehicleDetails,
  _loadingAmount,
  _totalAmount,
} from "./index";
import { odObject } from "../utils/od-list";
import { tpObject } from "../utils/tp-list";
import { discountObject } from "../utils/discount";
import { addonObject } from "../utils/addons";
import { Encrypt, currencyFormater } from "utils";
import moment from "moment";

export const getPremiumPdfData = (pdfDataProps) => {
  // prettier-ignore
  const {
    temp_data, enquiry_id, quote, type, prefill, gstStatus, tempData, odObjectProps,
    tpObjectProps, discountObjectProps, addonList, totalAddon, totalAmountProps, selectedTab,
    shortTerm, finalPremium, Theme, theme_conf
  } = pdfDataProps;

  return {
    site_logo: getLogoUrl(),
    ...(!_.isEmpty(temp_data?.agentDetails) &&
      !_.isEmpty(
        temp_data?.agentDetails?.filter(
          (x) => x?.sellerType === "P" || x?.sellerType === "E"
        )
      ) && {
        agentDetails: temp_data?.agentDetails?.filter(
          (x) => x?.sellerType === "P" || x?.sellerType === "E"
        )[0],
      }),
    traceId: temp_data?.traceId
      ? temp_data?.traceId
      : temp_data?.enquiry_id || enquiry_id,
    ic_logo: quote?.companyLogo,
    policy_type_logo: getPolicyType(type),
    toll_free_number: theme_conf?.broker_config?.phone || ContactFn(),
    toll_free_number_link: `tel:${theme_conf?.broker_config?.phone || ContactFn()}`,
    support_email: theme_conf?.broker_config?.email || brokerEmailFunction(),
    ic_name: quote?.companyName,
    product_name: quote?.productName,
    policy_tpe: _getPolicyTypeData(quote, temp_data, type),
    vehicle_details: _getVehicleDetails(quote, temp_data),
    fuel_type: `Fuel Type : ${
      quote?.fuelType ? quote?.fuelType.toUpperCase() : "N/A"
    }`,
    rto_code: `RTO : ${quote?.vehicleRegistrationNo} - ${
      temp_data?.rtoCity || prefill?.corporateVehiclesQuoteRequest?.rtoCity
    }`,
    seating_capacity: `Seating Capacity : ${temp_data?.seatingCapacity || 0}`,
    idv: `IDV : ${
      temp_data?.tab === "tab2"
        ? "Not Applicable"
        : `₹ ${currencyFormater(quote?.idv)}`
    }`,
    new_ncb: `New NCB : ${
      temp_data?.tab === "tab2" ? "Not Applicable" : `${quote?.ncbDiscount}%`
    }`,
    registration_date: `Reg Date : ${quote?.vehicleRegisterDate}`,
    gstSelected: gstStatus ? "Y" : "N",
    prev_policy: _getPreviousPolicyExpiry(temp_data, tempData),

    policy_start_date: !temp_data?.breakIn
      ? `Policy Start Date : ${quote?.policyStartDate}`
      : "",
    business_type: _getBusinessType(quote),
    od: odObject(odObjectProps),

    tp: tpObject(tpObjectProps),

    discount: discountObject(discountObjectProps),

    addon: addonObject(quote, addonList, totalAddon),

    totalLoading: _loadingAmount(quote),

    total: _totalAmount(totalAmountProps),

    btn_link: `${window.location.href
      ?.replace(/productId/, "oldId")
      ?.replace(/selectedType/, "oldType")
      ?.replace(/expiryDate/, "oldExpiry")
      ?.replace(/selectedTerm/, "oldTerm")}&productId=${Encrypt(
      quote?.policyId
    )}${selectedTab === "tab2" ? `&selectedType=${Encrypt(selectedTab)}` : ""}${
      shortTerm && selectedTab !== "tab2"
        ? `&selectedTerm=${Encrypt(shortTerm)}`
        : ""
    }&expiryDate=${moment().format("DD-MM-YYYY")}`,
    btn_link_text: `₹ ${currencyFormater(finalPremium)}`,
    btn_style: {
      background: Theme?.QuotePopups?.color || "rgb(189, 212, 0)",
      ["font-size"]: " 12px",
      ["line-height"]: "40px",
      ["border-radius"]: "50px",
    },
    color_scheme: Theme?.links?.color,
    selectedGvw: temp_data?.corporateVehiclesQuoteRequest?.selectedGvw,
    vehicleRegistrationNo:
      temp_data?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo,
  };
};
