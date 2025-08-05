import facebook from "assets/img/facebook.png";
import instagram from "assets/img/instra.png";
import linkedIn from "assets/img/in.png";
import xtlogo from "assets/img/x logo.png";
import twitterxlogo from "assets/img/twitterxlogo.png";

// b2c-footer-js

export const UAT = import.meta.env.VITE_API_BASE_URL.includes(
  "https://uatapimotor.bajajcapitalinsurance.com/api"
)
  ? "https://dev.bajajcapitalinsurance.com"
  : "";

export const PREPROD = import.meta.env.VITE_API_BASE_URL.includes(
  "https://stageapimotor.bajajcapitalinsurance.com/api"
)
  ? "https://pre-prod.bajajcapitalinsurance.com"
  : "";

export const PROD = import.meta.env.VITE_API_BASE_URL.includes(
  "https://apimotor.bajajcapitalinsurance.com/api"
)
  ? "https://www.bajajcapitalinsurance.com"
  : "";

export const ourServices = [
  {
    text: `Pet Insurance`,
    link: `${
      UAT || PREPROD || PROD
    }/general-insurance/pet/input/customer-details`,
  },
  {
    text: `2 Wheeler Insurance`,
    link: `${UAT || PREPROD || PROD}/general-insurance/car/lead-page`,
  },
  {
    text: `Car Insurance`,
    link: `${UAT || PREPROD || PROD}/general-insurance/car/lead-page`,
  },
  {
    text: `Health Insurance`,
    link: `${
      UAT || PREPROD || PROD
    }/general-insurance/health/input/basic-details`,
  },
  {
    text: `Term Life Insurance`,
    link: `https://marketing.bajajcapitalinsurance.com/get-term-quote`,
  },
];

export const quickLinks = [
  { text: `About us`, link: `${UAT || PREPROD || PROD}/AboutUs.aspx` },
  {
    text: `Important Policies`,
    link: `${UAT || PREPROD || PROD}/ImportantPolicies.aspx`,
  },
  {
    text: `Branch Locator`,
    link: `${UAT || PREPROD || PROD}/BranchPage.aspx`,
  },
  { text: `Earn with Us`, link: `https://pos.bajajcapitalinsurance.com/` },
];

export const followSection = [
  { icon: facebook, link: `https://www.facebook.com/bajajcapitalinsurance` },
  {
    icon: xtlogo,
    link: `https://twitter.com/bcl_insurance?s=11&t=FS4xjIPjCIWz7Ry0n1-czA`,
  },
  {
    icon: instagram,
    link: `https://www.instagram.com/bajaj.capital.insurance/`,
  },
  {
    icon: linkedIn,
    link: `https://www.linkedin.com/company/bajaj-capital-insurance-broking-ltd/`,
  },
];

export const legalPolicy = [
  { text: `Privacy Policy`, link: `/PrivacyPolicy.aspx` },
  { text: `Terms & Conditions`, link: `/TermsConditions.aspx` },
  { text: `Disclaimer`, link: `/ImportantPolicies.aspx` },
  { text: `CSR Policy`, link: `/CSRPolicy.aspx` },
  { text: `Anti-Fraud Policy`, link: `` },
];

// BAJAj Footer

export const ourBCLServices = [
  {
    text: "Term Insurance",
    link: `https://partner${
      import.meta.env.VITE_PROD === "YES" ? "" : "uat"
    }.bajajcapitalinsurance.com/RmLogin.aspx#insurance`,
  },
  {
    text: "Health Insurance",
    link: `https://partner${
      import.meta.env.VITE_PROD === "YES" ? "" : "uat"
    }.bajajcapitalinsurance.com/RmLogin.aspx#insurance`,
  },
  {
    text: "Car Insurance",
    link: `https://partner${
      import.meta.env.VITE_PROD === "YES" ? "" : "uat"
    }.bajajcapitalinsurance.com/RmLogin.aspx#insurance`,
  },
  {
    text: "Bike Insurance",
    link: `https://partner${
      import.meta.env.VITE_PROD === "YES" ? "" : "uat"
    }.bajajcapitalinsurance.com/RmLogin.aspx#insurance`,
  },
  {
    text: "Pet Insurance",
    link: `https://${
      import.meta.env.VITE_PROD === "YES" ? "" : "uat"
    }pet.bajajcapitalinsurance.com/input/customer-details?token=91c3efab-ec94-46fe-bbe3-c47a4e6a0adf`,
  },
  {
    text: "Travel Insurance",
    link: `https://${
      import.meta.env.VITE_PROD === "YES" ? "" : "uat"
    }travel.bajajcapitalinsurance.com/?token=d507a297-155f-4838-acfa-c6d5a442f1a3`,
  },
  {
    text: "Port Health Policy",
    link: `https://${
      import.meta.env.VITE_PROD === "YES" ? "" : "uat"
    }portability.bajajcapitalinsurance.com/input/basic-details?token=b0bc92d2-e33c-4497-b946-e4ce17c40b08`,
  },
  {
    text: "Super Top",
    link: `https://dev.bajajcapitalinsurance.com/general-insurance/top-up/input/basic-details`,
  },
  {
    text: "Endowment",
    link: `https://${
      import.meta.env.VITE_PROD === "YES" ? "" : "uat"
    }endowment.bajajcapitalinsurance.com/?token=e1f8d910-c1d1-4777-8db2-b844127e792f`,
  },
];
export const quickBCLLinks = [
  {
    text: "About us",
    link: `https://partner${
      import.meta.env.VITE_PROD === "YES" ? "" : "uat"
    }.bajajcapitalinsurance.com/about.aspx`,
  },
  {
    text: "Contact us",
    link: `https://partner${
      import.meta.env.VITE_PROD === "YES" ? "" : "uat"
    }.bajajcapitalinsurance.com/contact-us.aspx`,
  },
  { text: "Earn With Us", link: `https://uatpos.bajajcapitalinsurance.com/` },
];
export const legalBCLPolicy = [
  {
    text: "Privacy Policy",
    link: `https://partner${
      import.meta.env.VITE_PROD === "YES" ? "" : "uat"
    }.bajajcapitalinsurance.com/privacy-policy.aspx`,
  },
  {
    text: "Terms & Conditions",
    link: `https://partner${
      import.meta.env.VITE_PROD === "YES" ? "" : "uat"
    }.bajajcapitalinsurance.com/terms-and-conditions.aspx`,
  },
  {
    text: "Disclaimer",
    link: `https://partner${
      import.meta.env.VITE_PROD === "YES" ? "" : "uat"
    }.bajajcapitalinsurance.com/disclaimer.aspx`,
  },
  {
    text: "CSR Policy",
    link: `https://partner${
      import.meta.env.VITE_PROD === "YES" ? "" : "uat"
    }.bajajcapitalinsurance.com/csr-policy.aspx`,
  },
  {
    text: " Anti-Fraud Policy",
    link: `https://partner${
      import.meta.env.VITE_PROD === "YES" ? "" : "uat"
    }.bajajcapitalinsurance.com/antifraud-policy.aspx`,
  },
];
