import { currencyFormater } from "utils";
import { generateAddonList } from "../utils/addons/addon-list";
import { getOdList } from "../utils/od-list/od-list";
import { getPremiumPdfData } from "./pdf-data";

export const handleEmailClick = async (emailPdfProp) => {
  // prettier-ignore
  const {
    prefill, type, totalApplicableAddonsMotor, addonDiscountPercentage, quote, 
    addOnsAndOthers, others, othersList, temp_data, llpaidCon, revisedNcb, otherDiscounts, tempData,
    totalPremium, totalPremiumA, totalPremiumB, totalPremiumC, totalAddon, finalPremium, gst,
    selectedTab, shortTerm, Theme, setSendPdf, setSendQuotes, enquiry_id, gstStatus, extraLoading,
    theme_conf
  } = emailPdfProp;

  if (import.meta.env?.VITE_BROKER !== "OLA") {
    // addon list
    // prettier-ignore
    const addonListProps =  { type, totalApplicableAddonsMotor, addonDiscountPercentage, quote, 
    addOnsAndOthers, others, othersList, temp_data }
    const addonList = generateAddonList(addonListProps);

    // od list common
    const odListProps = { quote, addOnsAndOthers, type, temp_data };
    let odLists = getOdList(odListProps);

    // prettier-ignore
    const odObjectProps = { quote, addOnsAndOthers, type, odLists, totalPremiumA };

    if (
      temp_data?.journeyCategory === "GCV" &&
      addOnsAndOthers?.selectedAccesories?.includes("Trailer")
    ) {
      odLists["Trailer"] =
        quote?.trailerValue * 1
          ? `₹ ${currencyFormater(quote?.trailerValue)}`
          : "N/A";
    }

    // tp props
    // prettier-ignore
    const tpObjectProps = { quote, temp_data, addOnsAndOthers, type, llpaidCon, others, othersList, totalPremiumB,};

    // get discount list
    // prettier-ignore
    const discountObjectProps = { revisedNcb, addOnsAndOthers, temp_data, quote, otherDiscounts, type, totalPremiumC }

    // total amount props
    // prettier-ignore
    const totalAmountProps = { totalPremiumA, totalAddon, totalPremiumC, quote, extraLoading, totalPremiumB,
    totalPremium, gst, finalPremium }

    // pdf data props
    // prettier-ignore
    const pdfDataProps = { temp_data, enquiry_id, quote, type, prefill, gstStatus, tempData, odObjectProps,
    tpObjectProps, discountObjectProps, addonList, totalAddon, totalAmountProps, selectedTab,
    shortTerm, finalPremium, Theme, theme_conf }

    let data = getPremiumPdfData(pdfDataProps);

    if (data) {
      let stringifiedData = JSON.stringify(data);
      setSendPdf(stringifiedData);
      setSendQuotes(true);
    }
  }
};
