import _ from "lodash";
import { Encrypt } from "utils";
import * as yup from "yup";

export const getType = (name, journeyStage, temp_data) => {
  switch (name) {
    case "quotes":
      return "shareQuotes";
    case "proposal-page":
      return temp_data?.journeyStage?.stage === "Proposal Accepted" ||
        temp_data?.journeyStage?.stage === "Payment Initiated" ||
        temp_data?.journeyStage?.stage === "Inspection Accept" ||
        temp_data?.journeyStage?.stage.toLowerCase() === "payment failed"
        ? "proposalCreated"
        : "shareProposal";
    case "payment-success":
      return "successPayment";
    case "compare-quote":
      return "comparepdf";
    default:
      return "";
  }
};

export const yupValidate = yup.object({
  mobileNo: yup
    .string()
    .matches(/^[6-9]\d{9}$/, "Invalid mobile number")
    .nullable()
    .transform((v, o) => (o === "" ? null : v))
    .min(10, "Mobile No. should be 10 digits")
    .max(10, "Mobile No. should be 10 digits"),
});

export const quoteOption = (finalPremiumlist1) => {
  return !_.isEmpty(finalPremiumlist1)
    ? _.map(finalPremiumlist1).map((x) => ({
        name: `${x?.name}`,
        id: x?.name,
        label: `${x?.name}, Premium: ${
          x?.finalPremium * 1 ? Math.round(x?.finalPremium) : 0
        }`,
        value: x?.name,
        idv: x?.idv,
        logo: x?.logo,
        finalPremiumNoGst: x?.finalPremiumNoGst,
        finalPremium: x?.finalPremium,
        gst: x?.gst,
        action: window.location.href,
        productName: x?.productName,
        policyId: x?.policyId,
        policyType: x?.policyType,
        applicableAddons: x?.applicableAddons,
        companyAlias: x?.companyAlias,
      }))
    : [];
};

export const whatsappContent = (
  stage,
  temp_data,
  shared,
  WhatsappRedirect,
  policyPdfUrl
) => {
  if (WhatsappRedirect) {
    return `Hi, I have shared ${stage}. Please click on the URL to download the PDF. \n \n ${policyPdfUrl}`;
  } else {
    return `Hi I have shared you ${stage} please check it out \n \n ${
      ["Proposal Accepted", "Payment Initiated"].includes(
        temp_data?.journeyStage?.stage
      )
        ? `${window.location.href}&dropout=${Encrypt(true)}&icr=${Encrypt(
            true
          )}`
        : `${window.location.href}${
            !_.isEmpty(shared) ? `&shared=${Encrypt(shared)}` : ""
          }`
    }`;
  }
};
