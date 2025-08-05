import { useEffect } from "react";
import { fetchToken } from "utils";
import _ from "lodash";
import { TypeReturn } from "modules/type";
import { BlockedSections } from "modules/quotesPage/addOnCard/cardConfig";
import { getBrokerWebsite } from "../helper";
import moment from "moment";
import {
  ContactFn,
  brokerEmailFunction,
  getBrokerLogoUrl,
  getIRDAI,
} from "components";
import { getPolicyType } from "modules/quotesPage/quoteUtil";
import { setComparePdfData } from "modules/quotesPage/quote.slice";
import { getVehicleInsurerType, getVehicleModel } from "./helper";
import { getAddonArray } from "./pdf-data/addons/addon-array";
import { getDiscountArray } from "./pdf-data/discount/discount-array";
import { getPremiumBreakupArray } from "./pdf-data/premium-breakup-array";
import { getAccessoriesArray } from "./pdf-data/accessories/accessories-array";
import { getOtherCoversArray } from "./pdf-data/other-covers-array";
import {
  getAdditionalArray,
  getAdditionalArrayGcv,
} from "./pdf-data/additional/additional-array";
import { getTotalPremiumArray } from "./pdf-data/premium-array";
import { getInsurerDetailsArray } from "./pdf-data/insurer-details-array";
import { getUspArray } from "./pdf-data/usp/usp-array";

export const usePdfCreate = (pdfProps) => {
  // prettier-ignore
  const { newGroupedQuotesCompare, temp_data, addOnsAndOthers, type, shortTerm, enquiry_id,
        selectedTab, theme_conf, Theme, gstStatus, dispatch, xutm } = pdfProps
  const _stToken = fetchToken();

  useEffect(() => {
    if (newGroupedQuotesCompare) {
      // premium break array creation
      const premiumBreakupProps = { temp_data, newGroupedQuotesCompare };
      const premiumBreakupArray = getPremiumBreakupArray(premiumBreakupProps);

      // accessories array creation
      // prettier-ignore
      const accessoriesProps = { temp_data, addOnsAndOthers, type, newGroupedQuotesCompare }
      const accessoriesArray = getAccessoriesArray(accessoriesProps);

      // other cover array creation
      const otherCoverProps = { temp_data, newGroupedQuotesCompare };
      const otherCoversArray = getOtherCoversArray(otherCoverProps);

      //additional array creation
      // prettier-ignore
      const additionalArrayProps = { temp_data, shortTerm, type, addOnsAndOthers, newGroupedQuotesCompare }
      const additionalArray = getAdditionalArray(additionalArrayProps);

      //additional array gcv creation
      // prettier-ignore
      const additionalArrayGcvProps = { shortTerm, temp_data, addOnsAndOthers, newGroupedQuotesCompare };
      const additionalArrayGcv = getAdditionalArrayGcv(additionalArrayGcvProps);

      //discount array creation
      // prettier-ignore
      const discountProps = { temp_data, addOnsAndOthers, type, newGroupedQuotesCompare };
      const discountArray = getDiscountArray(discountProps);

      //addon array creation
      // prettier-ignore
      const addonProps = { type, temp_data, newGroupedQuotesCompare, addOnsAndOthers }
      const AddonArray = getAddonArray(addonProps);

      // total premium array
      const totalPremiumArray = getTotalPremiumArray(newGroupedQuotesCompare);

      // insurer details array
      // prettier-ignore
      const insurerDetailsProps = { newGroupedQuotesCompare, enquiry_id, _stToken, selectedTab, shortTerm, xutm }
      const insurerDetailsArray = getInsurerDetailsArray(insurerDetailsProps);

      // usp array
      const uspList = getUspArray(newGroupedQuotesCompare);

      // 3 and 5 years cpa condition
      const index = AddonArray[0][0].indexOf("Compulsory Personal Accident");
      AddonArray[0][index] = `Compulsory Personal Accident ${
        !_.isEmpty(addOnsAndOthers?.isTenure) && TypeReturn(type) === "car"
          ? "(3 Years)"
          : !_.isEmpty(addOnsAndOthers?.isTenure) && TypeReturn(type) === "bike"
          ? "(5 Years)"
          : ""
      }`;

      var data = {
        broker_logo: getBrokerLogoUrl(),
        broker_name:
          import.meta.env?.VITE_BROKER === "TATA"
            ? "TMIBASL"
            : import.meta.env?.VITE_TITLE,
        broker_website:
          `http://${window.location.hostname}` || getBrokerWebsite(),
        broker_phone: theme_conf?.broker_config?.phone || ContactFn(),
        quote_color_code: (Theme?.QuoteCard?.color || "#bdd400").slice(1),
        broker_email: theme_conf?.broker_config?.email || brokerEmailFunction(),
        policy_type:
          newGroupedQuotesCompare[0]?.productName === "Comprehensive" &&
          temp_data?.newCar &&
          TypeReturn(type) !== "cv"
            ? `Bundled(1 yr OD + ${TypeReturn(type) === "car" ? 3 : 5} yr TP)`
            : newGroupedQuotesCompare[0]?.productName,
        policy_type_logo: getPolicyType(type),
        vehicle_reg_no_title: "Vehicle Reg No",
        vehicle_reg_no: temp_data?.regNo || temp_data?.rtoNumber,
        rto_title: "Rto No",
        rto_no: temp_data?.rtoNumber,
        vehicle_model_title: "Make and Model",
        vehicle_model: getVehicleModel(newGroupedQuotesCompare),
        vehicle_reg_date_title: "Reg Date / Mfg Year",
        vehicle_reg_date: newGroupedQuotesCompare[0]?.vehicleRegisterDate,
        IRDIANumber: theme_conf?.broker_config?.irdanumber || getIRDAI(),
        vehicle_insurance_type_title: "Policy Type / Plan Type",
        // prettier-ignore
        vehicle_insurance_type: getVehicleInsurerType(newGroupedQuotesCompare, temp_data, type),
        gstSelected: gstStatus ? "Y" : "N",
        quote_id_title: "Quote ID: ",
        quote_id: temp_data?.traceId ? temp_data?.traceId : enquiry_id,
        quote_date_title: "Quote Date: ",
        quote_date: moment().format("DD-MM-YYYY"),
        customer_name_title: "Customer Name :",
        customer_name:
          temp_data?.firstName && temp_data?.lastName
            ? temp_data?.firstName + " " + temp_data?.lastName
            : temp_data?.firstName || temp_data?.lastName
            ? temp_data?.firstName || temp_data?.lastName
            : "",
        customer_phone_title: "Customer Number :",
        customer_phone: temp_data?.mobileNo || " ",
        customer_email_title: "Customer Email :",
        customer_email: temp_data?.emailId || " ",
        ...(!_.isEmpty(temp_data?.agentDetails) &&
          !_.isEmpty(
            temp_data?.agentDetails.filter((item) => item.sellerType === "P")
          ) && {
            pos_name_title: "POS Name:",
            pos_name: temp_data?.agentDetails.filter(
              (item) => item.sellerType === "P"
            )[0]?.agentName,
            pos_phone_title: "POS Phone:",
            pos_phone: temp_data?.agentDetails.filter(
              (item) => item.sellerType === "P"
            )[0]?.agentMobile,
          }),
        ic_usp_title: "Insurer USP",
        ic_usp: uspList,
        premium_breakup_title: "Premium Breakup",
        premium_breakup: premiumBreakupArray,
        accessories_title: "Accessories",
        accessories: accessoriesArray,
        otherCover: otherCoversArray,
        other_cover_title: "Other Covers",
        ...{
          additonal_cover_title: "Additional Covers",
          additonal_cover:
            temp_data.journeyCategory === "GCV"
              ? additionalArrayGcv
              : additionalArray?.map(
                  (item) => !_.isEmpty(item) && _.compact(item)
                ),
        },
        discount_title: " Discounts/Deductibles ",
        discounts:
          temp_data?.odOnly ||
          temp_data.journeyCategory === "GCV" ||
          BlockedSections(
            import.meta.env.VITE_BROKER,
            temp_data?.journeyCategory
          )?.includes("unnamed pa cover")
            ? []
            : discountArray,
        addons_title: AddonArray.includes("IMT - 23")
          ? "Addons & Covers"
          : "Addons",
        addons: AddonArray,
        premium_breakup_total_title: "GST",
        premium_breakup_total: totalPremiumArray,
        insurance_details: insurerDetailsArray,
        color_scheme: Theme?.links?.color,
        selectedGvw: temp_data?.corporateVehiclesQuoteRequest?.selectedGvw,
        selectedAddons: addOnsAndOthers?.selectedAddons,
      };
    }
    dispatch(setComparePdfData(data));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [newGroupedQuotesCompare]);
};
