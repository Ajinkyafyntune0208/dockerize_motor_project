//getting broker support Mail
import { TypeReturn } from "modules/type";
import swal from "sweetalert";

//getting logo url
export const getLogoUrl = () => {
  switch (import.meta.env?.VITE_BROKER) {
    case "OLA":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/ola.png`;
    case "FYNTUNE":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/fyntune.png`;
    case "KMD":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/kmd.png`;
    case "POLICYERA":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/policy-era.png`;
    case "ABIBL":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/abiblPdf.jpeg`;
    case "GRAM":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/gc.png`;
    case "ACE":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/ace.png`;
    case "SRIYAH":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/sriyah.png`;
    case "RB":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/rb.png`;
    case "SPA":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/insuringall.jpeg`;
    case "BAJAJ":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/bajajPdfLogo.png`;
    case "UIB":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/uib.png`;
    case "SRIDHAR":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/sridhar.png`;
    case "TATA":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/tata.gif`;
    case "HEROCARE":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/hero_care.png`;
    case "PAYTM":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/paytm.svg`;
    case "KAROINSURE":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/karoinsure.png`;
    case "INSTANTBEEMA":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/instant-beema.svg`;
    case "VCARE":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/vcare.jpeg`;
    case "WOMINGO":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/womingo.png`;
    case "ONECLICK":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/1clickpolicy.png`;
    default:
      break;
  }
};

//------------ getting addon name---------------
export const getAddonName = (addon) => {
  switch (addon) {
    case "roadSideAssistance":
      return "Road Side Assistance";
    case "roadSideAssistance2":
      return "Road Side Assistance (₹ 49)";
    case "zeroDepreciation":
      return "Zero Depreciation";
    case "imt23":
      return "IMT - 23";
    case "keyReplace":
      return "Key Replacement";
    case "engineProtector":
      return "Engine Protector";
    case "ncbProtection":
      return "NCB Protection";
    case "consumables":
      return "Consumable";
    case "tyreSecure":
      return "Tyre Secure";
    case "returnToInvoice":
      return "Return To Invoice";
    case "lopb":
      return "Loss of Personal Belongings";
    case "emergencyMedicalExpenses":
      return "Emergency Medical Expenses";
    case "windShield":
      return "Wind Shield";
    case "emiProtection":
      return "EMI Protection";
    case "additionalTowing":
      return "Additional Towing";
    case "batteryProtect":
      return "Battery Protect";
    default:
      return "";
  }
};

export const getAddonKey = (addonName) => {
  switch (addonName) {
    case "Road Side Assistance":
      return "roadSideAssistance";
    case "Road Side Assistance (₹ 49)":
      return "roadSideAssistance2";
    case "Zero Depreciation":
      return "zeroDepreciation";
    case "IMT - 23":
      return "imt23";
    case "Key Replacement":
      return "keyReplace";
    case "Engine Protector":
      return "engineProtector";
    case "NCB Protection":
      return "ncbProtection";
    case "Consumable":
      return "consumables";
    case "Tyre Secure":
      return "tyreSecure";
    case "Return To Invoice":
      return "returnToInvoice";
    case "Loss of Personal Belongings":
      return "lopb";
    case "Emergency Medical Expenses":
      return "emergencyMedicalExpenses";
    case "Wind Shield":
      return "windShield";
    case "EMI Protection":
      return "emiProtection";
    case "Additional Towing":
      return "additionalTowing";
    case "Battery Protect":
      return "batteryProtect";
    default:
      return "";
  }
};


//------------ getting nvb calculation---------------

export const getCalculatedNcb = (yearDiff) => {
  switch (yearDiff) {
    case 0:
      return "0%";
    case 1:
      return "0%";
    case 2:
      return "20%";
    case 3:
      return "25%";
    case 4:
      return "35%";
    case 5:
      return "45%";
    case 6:
      return "50%";
    case 7:
      return "50%";
    case 8:
      return "50%";

    default:
      return "0%";
  }
};

export const getNewNcb = (ncb) => {
  switch (ncb) {
    case "0%":
      return "20%";
    case "20%":
      return "25%";
    case "25%":
      return "35%";
    case "35%":
      return "45%";
    case "45%":
      return "50%";
    case "50%":
      return "50%";
    default:
      return "0%";
  }
};

export const getPolicyType = (type) => {
  switch (TypeReturn(type)) {
    case "car":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/carCpBlack.png`;
    case "bike":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/bikeBlack.png`;
    case "cv":
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/cvBlack.png`;
    default:
      return `${window.location.origin}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/carCpBlack.png`;
  }
};

export const sortOptions = (extPath) => [
  {
    name: "Premium",
    label: "Premium",
    value: "2",
    id: "2",
    icon: `${extPath}/assets/images/arrow-down.png`,
  },
  {
    name: "Premium",
    label: "Premium",
    value: "3",
    id: "3",
    icon: `${extPath}/assets/images/arrow-up.png`,
  },
  {
    name: "IDV",
    label: "IDV",
    value: "4",
    id: "4",
    icon: `${extPath}/assets/images/arrow-down.png`,
  },
  {
    name: "IDV",
    label: "IDV",
    value: "5",
    id: "5",
    icon: `${extPath}/assets/images/arrow-up.png`,
  },
];

//prettier-ignore
export const pdfExpiry = (date, diffDays, NoOfDays, enquiry_id, token, journey_type, typeId, shared) => {
  if (date && !(diffDays < NoOfDays())) {
    swal("Error", "Your Quote has been expired", "error", {
      closeOnClickOutside: false,
    }).then(() => {
      const newurl =
        window.location.protocol +
        "//" +
        window.location.host +
        window.location.pathname.replace(/proposal-page/g, "quotes") +
        `?enquiry_id=${enquiry_id}${token ? `&xutm=${token}` : ``}${
          journey_type ? `&journey_type=${journey_type}` : ``
        }${typeId ? `&typeid=${typeId}` : ``}${shared ? `&shared=${shared}` : ``}`;
      const link = document.createElement("a");
      link.href = newurl;
      document.body.appendChild(link);
      link.click();
    });
  }
}

export const DummyQuote = (typePolicy) => ({
  idv: "",
  minIdv: 0,
  maxIdv: "",
  vehicleIdv: "",
  qdata: null,
  ppEnddate: "",
  addonCover: null,
  addonCoverDataGet: "",
  rtoDecline: null,
  rtoDeclineNumber: null,
  mmvDecline: null,
  mmvDeclineName: null,
  policyType: typePolicy,
  businessType: "",
  coverType: "",
  hypothecation: "",
  hypothecationName: "",
  vehicleRegistrationNo: "",
  rtoNo: "",
  versionId: "",
  selectedAddon: [],
  showroomPrice: "",
  fuelType: "",
  ncbDiscount: "",
  companyName: "",
  companyLogo: "",
  productName: "",
  mmvDetail: {
    manfName: "",
    modelName: "",
    versionName: "",
    fuelType: "",
    seatingCapacity: "",
    carryingCapacity: "",
    cubicCapacity: "",
    grossVehicleWeight: "",
    vehicleType: "",
  },
  masterPolicyId: {
    policyId: "",
    policyNo: "",
    policyStartDate: "",
    policyEndDate: "",
    sumInsured: "",
    corpClientId: "",
    productSubTypeId: "",
    insuranceCompanyId: "",
    status: "",
    corpName: "",
    companyName: "",
    logo: "",
    productSubTypeName: "",
    flatDiscount: "",
    predefineSeries: "",
    isPremiumOnline: "",
    isProposalOnline: "",
    isPaymentOnline: "",
  },
  motorManfDate: "",
  vehicleRegisterDate: "",
  vehicleDiscountValues: {
    masterPolicyId: "",
    productSubTypeId: "",
    segmentId: "",
    rtoClusterId: "",
    carAge: "",
    aaiDiscount: "",
    icVehicleDiscount: "",
  },
  basicPremium: "",
  motorElectricAccessoriesValue: "",
  motorNonElectricAccessoriesValue: "",
  motorLpgCngKitValue: "",
  "totalAccessoriesAmount(netOdPremium)": "",
  totalOwnDamage: "",
  tppdPremiumAmount: "",
  compulsoryPaOwnDriver: "",
  coverUnnamedPassengerValue: "",
  defaultPaidDriver: "",
  motorAdditionalPaidDriver: "",
  cngLpgTp: "",
  seatingCapacity: "",
  deductionOfNcb: "",
  antitheftDiscount: "",
  aaiDiscount: "",
  voluntaryExcess: "",
  otherDiscount: "",
  totalLiabilityPremium: "",
  netPremium: "",
  serviceTaxAmount: "",
  serviceTax: "",
  totalDiscountOd: "",
  addOnPremiumTotal: "",
  addonPremium: "",
  vehicleLpgCngKitValue: "",
  quotationNo: "",
  premiumAmount: "",
  serviceDataResponseerrMsg: "success",
  userId: null,
  productSubTypeId: "",
  userProductJourneyId: "",
  serviceErrCode: null,
  serviceErrMsg: null,
  policyStartDate: "",
  policyEndDate: "",
  icOf: "",
  vehicleIn90Days: "N",
  getPolicyExpiryDate: null,
  getChangedDiscountQuoteid: "",
  vehicleDiscountDetail: {
    discountId: null,
    discountRate: null,
  },
  isPremiumOnline: "",
  isProposalOnline: "",
  isPaymentOnline: "",
  policyId: "",
  insuraneCompanyId: "",
  maxAddonsSelection: null,
  addOnsData: {
    inBuilt: {},
    additional: {},
    other: [],
    inBuiltPremium: "",
    additionalPremium: "",
    otherPremium: "",
  },
  applicableAddons: [],
  finalOdPremium: 0,
  finalTpPremium: 12,
  finalTotalDiscount: 0,
  finalNetPremium: 12,
  finalGstAmount: 0,
  finalPayableAmount: 0,
});

export const unNamedCoverFn = (type) => {
  const commonCoverAmounts = [
    "₹ 10,000",
    "₹ 20,000",
    "₹ 30,000",
    "₹ 40,000",
    "₹ 50,000",
    "₹ 60,000",
    "₹ 70,000",
    "₹ 80,000",
    "₹ 90,000",
    "₹ 1 lac",
    "₹ 1.1 lac",
    "₹ 1.2 lac",
    "₹ 1.3 lac",
    "₹ 1.4 lac",
    "₹ 1.5 lac",
    "₹ 1.6 lac",
    "₹ 1.7 lac",
    "₹ 1.8 lac",
    "₹ 1.9 lac",
    "₹ 2 lac",
  ];

  if (type === "bike") {
    return commonCoverAmounts.slice(0, 10);
  }
  return [...commonCoverAmounts];
};

export const getCoverValue = (value, invert) => {
  if (!invert) {
    switch (value) {
      case "₹ 2 lac":
        return 200000;
      case "₹ 1.9 lac":
        return 190000;
      case "₹ 1.8 lac":
        return 180000;
      case "₹ 1.7 lac":
        return 170000;
      case "₹ 1.6 lac":
        return 160000;
      case "₹ 1.5 lac":
        return 150000;
      case "₹ 1.4 lac":
        return 140000;
      case "₹ 1.3 lac":
        return 130000;
      case "₹ 1.2 lac":
        return 120000;
      case "₹ 1.1 lac":
        return 110000;
      case "₹ 1 lac":
        return 100000;
      case "₹ 90,000":
        return 90000;
      case "₹ 80,000":
        return 80000;
      case "₹ 70,000":
        return 70000;
      case "₹ 60,000":
        return 60000;
      case "₹ 50,000":
        return 50000;
      case "₹ 40,000":
        return 40000;
      case "₹ 30,000":
        return 30000;
      case "₹ 20,000":
        return 20000;
      case "₹ 10,000":
        return 10000;
      default:
        return 100000;
    }
  } else {
    switch (value * 1) {
      case 200000:
        return "₹ 2 lac";
      case 190000:
        return "₹ 1.9 lac";
      case 180000:
        return "₹ 1.8 lac";
      case 170000:
        return "₹ 1.6 lac";
      case 160000:
        return "₹ 1.6 lac";
      case 150000:
        return "₹ 1.5 lac";
      case 140000:
        return "₹ 1.4 lac";
      case 130000:
        return "₹ 1.3 lac";
      case 120000:
        return "₹ 1.2 lac";
      case 110000:
        return "₹ 1.1 lac";
      case 100000:
        return "₹ 1 lac";
      case 90000:
        return "₹ 90,000";
      case 80000:
        return "₹ 80,000";
      case 70000:
        return "₹ 70,000";
      case 60000:
        return "₹ 60,000";
      case 50000:
        return "₹ 50,000";
      case 40000:
        return "₹ 40,000";
      case 30000:
        return "₹ 30,000";
      case 20000:
        return "₹ 20,000";
      case 10000:
        return "₹ 10,000";
      default:
        return "₹ 1 lac";
    }
  }
};
