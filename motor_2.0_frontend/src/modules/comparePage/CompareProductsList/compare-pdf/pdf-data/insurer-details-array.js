import moment from "moment";
import { Encrypt, currencyFormater } from "utils";

export const getInsurerDetailsArray = (insurerDetailsProps) => {
  // prettier-ignore
  const { newGroupedQuotesCompare, enquiry_id, _stToken, selectedTab, shortTerm, xutm } = insurerDetailsProps

  var insurerDetailsArray = [];
  insurerDetailsArray.push({
    logo: newGroupedQuotesCompare[0]?.companyLogo,
    companyName: newGroupedQuotesCompare[0]?.companyName,
    idv: `IDV: ${`₹ ${currencyFormater(
      parseInt(newGroupedQuotesCompare[0]?.idv)
    )}`}`,
    buy_link: `${window.location.protocol}//${
      window.location.host
    }${window.location.pathname.replace(
      /compare-quote/g,
      "quotes"
    )}?enquiry_id=${enquiry_id}${xutm ? `&xutm=${xutm}` : ``}${
      _stToken ? `&stToken=${_stToken}` : ``
    }`?.replace(/&/, "%26"),
    expiryDate: moment().format("DD-MM-YYYY"),
    buy_link_text: `Buy Now ₹ ${currencyFormater(
      parseInt(newGroupedQuotesCompare[0]?.finalPremium1)
    )}`,
    productId: newGroupedQuotesCompare[0]?.policyId,
    finalPremium1: currencyFormater(
      parseInt(newGroupedQuotesCompare[0]?.finalPremium1)
    ),
    selectedType: selectedTab === "tab2" ? Encrypt(selectedTab) : "",
    selectedTerm: shortTerm && selectedTab !== "tab2" ? Encrypt(shortTerm) : "",
  });
  insurerDetailsArray.push({
    logo: newGroupedQuotesCompare[1]?.companyLogo,
    companyName: newGroupedQuotesCompare[1]?.companyName,
    idv: `IDV: ${`₹ ${currencyFormater(
      parseInt(newGroupedQuotesCompare[1]?.idv)
    )}`}`,
    buy_link: `${window.location.protocol}//${
      window.location.host
    }${window.location.pathname.replace(
      /compare-quote/g,
      "quotes"
    )}?enquiry_id=${enquiry_id}${xutm ? `&xutm=${xutm}` : ``}${
      _stToken ? `&stToken=${_stToken}` : ``
    }`?.replace(/&/, "%26"),
    expiryDate: moment().format("DD-MM-YYYY"),
    buy_link_text: `Buy Now ₹ ${currencyFormater(
      parseInt(newGroupedQuotesCompare[1]?.finalPremium1)
    )}`,
    productId: newGroupedQuotesCompare[1]?.policyId,
    finalPremium1: currencyFormater(
      parseInt(newGroupedQuotesCompare[1]?.finalPremium1)
    ),
    selectedType: selectedTab === "tab2" ? Encrypt(selectedTab) : "",
    selectedTerm: shortTerm && selectedTab !== "tab2" ? Encrypt(shortTerm) : "",
  });
  if (Number(newGroupedQuotesCompare[2]?.idv) > 0) {
    insurerDetailsArray.push({
      logo: newGroupedQuotesCompare[2]?.companyLogo,
      companyName: newGroupedQuotesCompare[2]?.companyName,
      idv: `IDV: ${`₹ ${currencyFormater(
        parseInt(newGroupedQuotesCompare[2]?.idv)
      )}`}`,
      buy_link: `${window.location.protocol}//${
        window.location.host
      }${window.location.pathname.replace(
        /compare-quote/g,
        "quotes"
      )}?enquiry_id=${enquiry_id}${_stToken ? `&stToken=${_stToken}` : ``}`?.replace(/&/, "%26"),
      expiryDate: moment().format("DD-MM-YYYY"),
      buy_link_text: `Buy Now ₹ ${currencyFormater(
        parseInt(newGroupedQuotesCompare[2]?.finalPremium1)
      )}`,
      productId: newGroupedQuotesCompare[2]?.policyId,
      finalPremium1: currencyFormater(
        parseInt(newGroupedQuotesCompare[2]?.finalPremium1)
      ),
      selectedType: selectedTab === "tab2" ? Encrypt(selectedTab) : "",
      selectedTerm:
        shortTerm && selectedTab !== "tab2" ? Encrypt(shortTerm) : "",
    });
  }
  return insurerDetailsArray;
};
