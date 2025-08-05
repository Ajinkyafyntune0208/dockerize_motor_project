import { getBrokerLogoUrl } from "components/Details-funtion-folder/DetailsHolder";
import { Encrypt } from "utils";
import { getType } from "./helper";
import _ from "lodash";

export const quotePageShareData = (
  token,
  userDataHome,
  temp_data,
  EmailsId,
  MobileNo,
  sendAll,
  enquiry_id,
  typeCheck,
  loc,
  quoteList,
  gstStatus
) => {
  const isDropoutStage = ["Proposal Accepted", "Payment Initiated"].includes(
    temp_data?.journeyStage?.stage
  );
  return {
    userType: token ? "pos" : "employee",
    firstName:
      userDataHome?.firstName ||
      temp_data?.firstName ||
      userDataHome?.userProposal?.additonalData?.owner?.firstName ||
      "Customer",
    lastName:
      userDataHome?.lastName ||
      temp_data?.lastName ||
      userDataHome?.userProposal?.additonalData?.owner?.lastName ||
      "",
    emailId: EmailsId && !_.isEmpty(EmailsId) ? EmailsId : null,
    mobileNo: MobileNo,
    logo: getBrokerLogoUrl(),
    notificationType: sendAll ? "all" : typeCheck === 1 ? "sms" : "email",
    enquiryId: enquiry_id,
    to: `91${MobileNo}`,
    url: window.location.href,
    link: isDropoutStage
      ? `${window.location.href}&dropout=${Encrypt(true)}&icr=${Encrypt(true)}`
      : `${window.location.href}&shared=${Encrypt(true)}`,
    domain: `http://${window.location.hostname}`,
    type: getType(loc[2], "", temp_data),
    ...(loc[2] === "quotes" && { quotes: quoteList }),
    ...(loc[2] === "quotes" && {
      productName: quoteList[0]?.productName,
    }),
    ...(loc[2] === "proposal-page" && {
      productName: temp_data?.selectedQuote?.productName,
    }),
    gstSelected: gstStatus ? "Y" : "N",

    ic_logo: temp_data?.selectedQuote?.companyLogo,
  };
};

export const quotePageWhatsappData = (
  token,
  userDataHome,
  temp_data,
  MobileNo,
  shareQuotesFromToaster,
  loc,
  enquiry_id,
  quoteList,
  gstStatus,
  shared
) => {
  return {
    userType: token ? "pos" : "employee",
    firstName:
      userDataHome?.firstName ||
      temp_data?.firstName ||
      userDataHome?.userProposal?.additonalData?.owner?.firstName ||
      "Customer",
    lastName:
      userDataHome?.lastName ||
      temp_data?.lastName ||
      userDataHome?.userProposal?.additonalData?.owner?.lastName ||
      " ",
    to: `91${MobileNo}`,
    type: shareQuotesFromToaster
      ? "driverDetails"
      : getType(
          loc[2],
          loc[2] === "proposal-page" ? temp_data?.journeyStage?.stage : false,
          temp_data
        ),
    enquiryId: enquiry_id,
    url: window.location.href,

    link:
      temp_data?.journeyStage?.stage === "Proposal Accepted" ||
      temp_data?.journeyStage?.stage === "Payment Initiated"
        ? `${window.location.href}&dropout=${Encrypt(true)}&icr=${Encrypt(
            true
          )}`
        : `${window.location.href}${
            shared ? `&shared=${Encrypt(shared)}` : ""
          }`,

    ...(loc[2] === "proposal-page" && {
      proposalData: temp_data?.userProposal,
    }),

    ...(loc[2] === "quotes" && { quotes: quoteList }),
    gstSelected: gstStatus ? "Y" : "N",
  };
};

export const compareEmailData = (
  token,
  userDataHome,
  temp_data,
  comparePdfData,
  EmailsId,
  enquiry_id,
  typeCheck,
  MobileNo,
  gstStatus
) => {
  return {
    userType: token ? "pos" : "employee",
    name:
      (userDataHome?.firstName || temp_data?.firstName || "Customer") +
      " " +
      (userDataHome?.lastName || temp_data?.lastName || " "),
    data: JSON.stringify(comparePdfData),
    email: EmailsId,
    emailId: EmailsId,
    logo: getBrokerLogoUrl(),
    enquiryId: enquiry_id,
    subject: "Compare PDF",
    link: window.location.href,
    vehicle_model: userDataHome?.modelName,
    rto: userDataHome?.rtoNumber,
    regNumber: userDataHome?.regNo,
    previos_policy_expiry_date: userDataHome?.expiry || "NEW",
    fuel_type: userDataHome?.fuel,
    productName: comparePdfData?.policy_type,
    reg_date: userDataHome?.regDate,
    ...(typeCheck === 3 && {
      url: window.location.href,
      mobileNo: MobileNo,
      to: `91${MobileNo}`,
      type: "comparepdf",
      notificationType: "all",
    }),
    gstSelected: gstStatus ? "Y" : "N",
  };
};

export const compareSmsWhatsappData = (
  token,
  userDataHome,
  temp_data,
  comparePdfData,
  enquiry_id,
  typeCheck,
  MobileNo,
  gstStatus
) => {
  return {
    userType: token ? "pos" : "employee",
    name:
      (userDataHome?.firstName || temp_data?.firstName || "Customer") +
      " " +
      (userDataHome?.lastName || temp_data?.lastName || " "),
    data: JSON.stringify(comparePdfData),
    logo: getBrokerLogoUrl(),
    enquiryId: enquiry_id,
    subject: "Compare PDF",
    link: window.location.href,
    vehicle_model: userDataHome?.modelName,
    rto: userDataHome?.rtoNumber,
    regNumber: userDataHome?.regNo,
    previos_policy_expiry_date: userDataHome?.expiry || "NEW",
    fuel_type: userDataHome?.fuel,
    productName: comparePdfData?.policy_type,
    reg_date: userDataHome?.regDate,
    ...(typeCheck === 3 && {
      url: window.location.href,
      mobileNo: MobileNo,
      to: `91${MobileNo}`,
      type: "comparepdf",
      notificationType: "all",
    }),
    gstSelected: gstStatus ? "Y" : "N",
  };
};

export const compareSmsData = (
  MobileNo,
  enquiry_id,
  temp_data,
  comparePdfData,
  userDataHome,
  token
) => {
  return {
    to: `91${MobileNo}`,
    enquiryId: enquiry_id,
    link:
      temp_data?.journeyStage?.stage === "Proposal Accepted" ||
      temp_data?.journeyStage?.stage === "Payment Initiated"
        ? `${window.location.href}&dropout=${Encrypt(true)}&icr=${Encrypt(
            true
          )}`
        : window.location.href,
    domain: `http://${window.location.hostname}`,
    notificationType: "sms",
    mobileNo: MobileNo,
    logo: getBrokerLogoUrl(),
    type: "comparepdf",
    productName: comparePdfData?.policy_type,
    firstName:
      userDataHome?.firstName ||
      temp_data?.firstName ||
      userDataHome?.userProposal?.additonalData?.owner?.firstName ||
      "Customer",
    lastName:
      userDataHome?.lastName ||
      temp_data?.lastName ||
      userDataHome?.userProposal?.additonalData?.owner?.lastName ||
      " ",
    userType: token ? "pos" : "employee",
  };
};

export const compareWhatsappData = (
  token,
  userDataHome,
  temp_data,
  MobileNo,
  enquiry_id,
  comparePdfData,
  loc,
  gstStatus
) => {
  return {
    userType: token ? "pos" : "employee",
    firstName:
      userDataHome?.firstName ||
      temp_data?.firstName ||
      userDataHome?.userProposal?.additonalData?.owner?.firstName ||
      "Customer",
    lastName:
      userDataHome?.lastName ||
      temp_data?.lastName ||
      userDataHome?.userProposal?.additonalData?.owner?.lastName ||
      " ",
    to: `91${MobileNo}`,
    enquiryId: enquiry_id,
    url: window.location.href,
    data: JSON.stringify(comparePdfData),
    type: getType(loc[2], "", temp_data),
    link:
      temp_data?.journeyStage?.stage === "Proposal Accepted" ||
      temp_data?.journeyStage?.stage === "Payment Initiated"
        ? `${window.location.href}&dropout=${Encrypt(true)}&icr=${Encrypt(
            true
          )}`
        : window.location.href,
    gstSelected: gstStatus ? "Y" : "N",
  };
};

export const paymentEmailData = (
  enquiry_id,
  sendAll,
  typeCheck,
  EmailsId,
  userDataHome,
  temp_data,
  policy,
  MobileNo,
  gstStatus
) => {
  return {
    enquiryId: enquiry_id,
    notificationType: sendAll ? "all" : typeCheck === 1 ? "sms" : "email",
    domain: `http://${window.location.hostname}`,
    type: "paymentSuccess",
    email: EmailsId,
    emailId: EmailsId,
    firstName:
      userDataHome?.firstName ||
      temp_data?.firstName ||
      userDataHome?.userProposal?.additonalData?.owner?.firstName ||
      "Customer",
    lastName:
      userDataHome?.lastName ||
      temp_data?.lastName ||
      userDataHome?.userProposal?.additonalData?.owner?.lastName ||
      " ",
    action: window.location.href,
    link: window.location.href,
    productName: temp_data?.selectedQuote?.productName,
    policyNumber: policy?.policyNumber,
    logo: getBrokerLogoUrl(),
    downloadUrl: policy?.pdfUrl ? policy?.pdfUrl : window.location.href,
    mobileNo: MobileNo,
    to: `91${MobileNo}`,
    gstSelected: gstStatus ? "Y" : "N",

    ic_logo: temp_data?.selectedQuote?.companyLogo,
  };
};

export const paymentWhatsappData = (
  enquiry_id,
  userDataHome,
  temp_data,
  MobileNo,
  policy,
  gstStatus
) => {
  return {
    enquiryId: enquiry_id,
    domain: `http://${window.location.hostname}`,
    type: "paymentSuccess",
    notificationType: "whatsapp",
    firstName:
      userDataHome?.firstName ||
      temp_data?.firstName ||
      userDataHome?.userProposal?.additonalData?.owner?.firstName ||
      "Customer",
    lastName:
      userDataHome?.lastName ||
      temp_data?.lastName ||
      userDataHome?.userProposal?.additonalData?.owner?.lastName ||
      " ",
    mobileNo: MobileNo,
    to: `91${MobileNo}`,
    url: window.location.href,
    action: window.location.href,
    link: window.location.href,
    logo: getBrokerLogoUrl(),
    downloadUrl: policy?.pdfUrl ? policy?.pdfUrl : window.location.href,
    gstSelected: gstStatus ? "Y" : "N",
  };
};

export const premiumWhatsappData = (
  token,
  userDataHome,
  temp_data,
  MobileNo,
  premiumBreakuppdf,
  enquiry_id,
  sendPdf,
  gstStatus
) => {
  return {
    userType: token ? "pos" : "employee",
    firstName:
      userDataHome?.firstName ||
      temp_data?.firstName ||
      userDataHome?.userProposal?.additonalData?.owner?.firstName ||
      "Customer",
    lastName:
      userDataHome?.lastName ||
      temp_data?.lastName ||
      userDataHome?.userProposal?.additonalData?.owner?.lastName ||
      " ",
    to: `91${MobileNo}`,
    type: premiumBreakuppdf,
    enquiryId: enquiry_id,
    url: window.location.href,
    data: sendPdf,
    link:
      temp_data?.journeyStage?.stage === "Proposal Accepted" ||
      temp_data?.journeyStage?.stage === "Payment Initiated"
        ? `${window.location.href}&dropout=${Encrypt(true)}&icr=${Encrypt(
            true
          )}`
        : window.location.href,
    gstSelected: gstStatus ? "Y" : "N",
  };
};

export const premiumEmailData = (
  token,
  userDataHome,
  temp_data,
  sendPdf,
  EmailsId,
  enquiry_id,
  loc,
  quoteList,
  finalPremiumlist1,
  typeCheck,
  MobileNo,
  gstStatus
) => {
  return {
    userType: token ? "pos" : "employee",
    name:
      (userDataHome?.firstName || temp_data?.firstName || "Customer") +
      " " +
      (userDataHome?.lastName || temp_data?.lastName || " "),
    data: sendPdf,
    email: EmailsId,
    emailId: EmailsId,
    logo: getBrokerLogoUrl(),
    enquiryId: enquiry_id,
    subject: "Premium Breakup",
    link:
      temp_data?.journeyStage?.stage === "Proposal Accepted" ||
      temp_data?.journeyStage?.stage === "Payment Initiated"
        ? `${window.location.href}&dropout=${Encrypt(true)}&icr=${Encrypt(
            true
          )}`
        : window.location.href,
    vehicle_model: userDataHome?.modelName,
    rto: userDataHome?.rtoNumber,
    regNumber: userDataHome?.regNo,
    previos_policy_expiry_date: userDataHome?.expiry || "NEW",
    fuel_type: userDataHome?.fuel,
    ...(loc[2] === "quotes" && { quotes: quoteList }),
    productName: finalPremiumlist1[0]?.productName,
    reg_date: userDataHome?.regDate,
    ...(typeCheck === 3 &&
      sendPdf && {
        to: `91${MobileNo}`,
        type: "premiumBreakuppdf",
        notificationType: "all",
        url: window.location.href,
      }),
    gstSelected: gstStatus ? "Y" : "N",
  };
};
