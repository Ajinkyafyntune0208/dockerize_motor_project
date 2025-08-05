import swal from "sweetalert";
import { VerifyCkycnum } from "../../../../proposal.slice";


const getCreds = () => {
  if(import.meta.env.VITE_BROKER === "HEROCARE" && import.meta.env.PROD === "YES") {
    return {
      id: "oicl",
      password: "hHnbdbehe3)8@2"
    }
  }else if(import.meta.env.VITE_BROKER === "KMD" && process.env.PROD === "YES") {
    return {
      id: "oicl",
      password: "hHndbehe3)8@2",
    };
  }
   else {
    return {
      id: "oicl_uat",
      password: "oicl_uat"
    }
  }
};


export const camsckyc = (dispatch, verificationPayload) => {
  new window.CAMSKYCSDK(getCreds()?.id, getCreds().password, " ", verificationPayload?.enquiryId, (res) => {
    if (res?.data?.status === "kyc_result_approved") {
      swal(
        "Please note",
        "The data fetched from KYC (Know Your Customer) will be utilized to update the proposal.",
        "info"
      ).then(() => {
        res?.data?.details &&
          dispatch(
            VerifyCkycnum({ ...res?.data?.details, ...verificationPayload })
          );
      });
    }
  });
};
