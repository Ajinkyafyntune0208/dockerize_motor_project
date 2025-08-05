import _ from "lodash";
import { getBrokerLogoUrl } from "components";

export const saveCampaignData = (
  lead_source,
  utm_source,
  utm_medium,
  utm_campaign
) => {
  return {
    ...(lead_source && { lead_source }),
    ...(utm_source && { utm_source }),
    ...(utm_medium && { utm_medium }),
    ...(utm_campaign && { utm_campaign }),
  };
};

export const saveTokenData = (tokenData, token, journey_type) => {
  return {
    ...(journey_type === "Z3JhbWNvdmVyLWFwcC1qb3VybmV5" && {
      addtionalData: tokenData,
    }),
    ...(token && { token: token }),
    ...((!_.isEmpty(tokenData) || localStorage?.SSO_user) && {
      tokenResp: !_.isEmpty(tokenData) ? tokenData : localStorage?.SSO_user,
    }),
    ...{
      sellerType:
        tokenData?.usertype || tokenData?.seller_type
          ? tokenData?.usertype || tokenData?.seller_type
          : null,
    },
    ...(tokenData?.category && { categoryName: tokenData?.category }),
    ...(tokenData?.relationSbi && {
      relationSbi: tokenData?.relationSbi,
    }),
    ...(token && {
      ...(tokenData?.first_name && {
        userfirstName: tokenData?.first_name,
      }),
      ...(tokenData?.last_name && {
        userlastName: tokenData?.last_name,
      }),
      ...(tokenData?.user_name && {
        userName: tokenData?.user_name,
      }),
      agentId: tokenData?.seller_id,
      agentName: tokenData?.seller_name,
      agentMobile: tokenData?.mobile,
      agentEmail: tokenData?.email,
      ...((tokenData?.usertype === "P" || tokenData?.seller_type === "P") && {
        panNo: tokenData?.pan_no,
        aadharNo: tokenData?.aadhar_no,
      }),
    }),
  };
};

export const saveConsentData = (consent) => {
  return {
    ...(["ABIBL", "ACE"].includes(import.meta.env.VITE_BROKER) && {
      whatsappConsent: consent,
    }),
  };
};

export const saveLeadData = (
  temp_data,
  enquiry_id,
  journey_type,
  type,
  source,
  typeId
) => {
  return {
    stage: "1",
    ...(type !== "cv" && { section: type }),
    firstName: temp_data?.firstName,
    lastName: temp_data?.lastName,
    fullName: temp_data?.fullName,
    mobileNo: temp_data?.mobileNo,
    whatsappNo: temp_data?.whatsappNo,
    emailId: temp_data?.emailId,
    userProductJourneyId: enquiry_id?.enquiryId,
    enquiryId: enquiry_id?.enquiryId,
    ...((journey_type || source) && {
      journeyType: journey_type || source,
    }),
    ...(typeId && { productSubTypeId: typeId }),
  };
};

export const onSubmitLead = (
  theme_conf,
  consent,
  data,
  tokenData,
  source,
  autoRegister
) => {
  return {
    ...saveConsentData(consent),
    ...data,
    ...(source && { source }),
    ...((!_.isEmpty(tokenData) || localStorage?.SSO_user) && {
      tokenResp: !_.isEmpty(tokenData) ? tokenData : localStorage?.SSO_user,
    }),
    ...(theme_conf?.broker_config?.lead_otp &&
      (!(
        import.meta.env.VITE_BROKER === "BAJAJ" &&
        import.meta.env.VITE_BASENAME === "NA"
      ) ||
        (import.meta.env.VITE_BROKER === "HEROCARE" &&
          !(
            ["P", "E"].includes(tokenData?.seller_type) ||
            ["P", "E"].includes(tokenData?.sellerType)
          )) ||
        (import.meta.env.VITE_BROKER === "TATA" &&
          (["P", "E"].includes(tokenData?.seller_type) ||
            ["P", "E"].includes(tokenData?.sellerType)))) &&
      !autoRegister && {
        isOtpRequired: "Y",
        logo: getBrokerLogoUrl(),
      }),
  };
};
