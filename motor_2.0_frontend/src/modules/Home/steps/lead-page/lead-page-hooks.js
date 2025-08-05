import { useEffect } from "react";
import { Url } from "modules/proposal/proposal.slice";
import { TokenValidation, clear } from "modules/Home/home.slice";
import _ from "lodash";
import swal from "sweetalert";
import { reloadPage, RedirectFn } from "utils";
import { Extn } from "./helper";
import { toast } from "react-toastify";
import { _trackVerification } from "analytics/user-creation.js/user-creation";
import { useSelector } from "react-redux";

//Lead-page
export const useLeadGeneration = (dispatch, type, enquiry_id) => {
  useEffect(() => {
    if (enquiry_id?.enquiryId)
      setTimeout(() =>
        dispatch(
          Url({
            proposalUrl: window.location.href,
            quoteUrl: window.location.href,
            stage: "Lead Generation",
            userProductJourneyId: enquiry_id?.enquiryId,
            ...(type !== "cv" && { section: type }),
            ...{ skipCrm: "Y" },
          }),
          100
        )
      );
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [enquiry_id?.enquiryId]);
};

export const usePrefillAPI = (temp_data, reset, setSameNumber) => {
  useEffect(() => {
    if (
      temp_data?.firstName ||
      temp_data?.lastName ||
      temp_data?.mobileNo ||
      temp_data?.emailId ||
      temp_data?.whatsappNo
    ) {
      if (temp_data?.whatsappNo === temp_data?.mobileNo) {
        setSameNumber(true);
      } else {
        setSameNumber(false);
      }
      reset({
        firstName: temp_data?.firstName,
        lastName: temp_data?.lastName,
        fullName: _.isEmpty(temp_data?.firstName)
          ? ""
          : _.isEmpty(temp_data?.lastName)
          ? temp_data?.firstName
          : temp_data?.firstName + " " + temp_data?.lastName,
        mobileNo: temp_data?.mobileNo,
        emailId: temp_data?.emailId,
        whatsappNo: temp_data?.whatsappNo,
      });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data]);
};

export const useTokenData = (temp_data, tokenData, setValue) => {
  useEffect(() => {
    if (
      !_.isEmpty(tokenData) &&
      tokenData.sellerType !== "P" &&
      tokenData.seller_type !== "P" && 
      import.meta.env.VITE_BROKER !== "KAROINSURE"
    ) {
      !temp_data?.fullName &&
        tokenData?.first_name &&
        setValue(
          "fullName",
          `${tokenData?.first_name}${
            tokenData?.last_name ? ` ${tokenData?.last_name}` : ""
          }`
        );
      tokenData?.email && setValue("emailId", tokenData?.email);
      !temp_data?.mobileNo &&
        tokenData?.mobile &&
        setValue("mobileNo", tokenData?.mobile);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [tokenData]);
};

//prettier-ignore
export const useTokenValidation = (dispatch, token, journey_type, tokenFailure, rd_link, setbtnDisable) => {
  const { theme_conf } = useSelector((state) => state.home);
  useEffect(() => {
    if (token && import.meta.env.VITE_BROKER !== "RB") {
      let decodedToken = decodeURIComponent((token + "").replace(/\+/g, "%20"));
      dispatch(
        TokenValidation({
          token: String(decodedToken).replace(/'/g, ""),
          ...(journey_type && {
            journeyType: journey_type,
          }),
        })
      );
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [token]);

  useEffect(() => {
    if (tokenFailure) {
      swal("Error", tokenFailure, "error").then(() =>
        reloadPage(rd_link || theme_conf?.broker_config?.broker_asset?.other_failure_url?.url  || RedirectFn(token))
      );
    }
    setbtnDisable(false);
    return () => {
      dispatch(clear("token"));
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [tokenFailure]);
};

const requireOTP = (theme_conf, tokenData) =>
  theme_conf?.broker_config?.lead_otp &&
  ((import.meta.env.VITE_BROKER === "BAJAJ" &&
    import.meta.env.VITE_BASENAME === "general-insurance") ||
    (import.meta.env.VITE_BROKER === "HEROCARE" &&
      !(
        ["P", "E"].includes(tokenData?.seller_type) ||
        ["P", "E"].includes(tokenData?.sellerType)
      )) ||
    (import.meta.env.VITE_BROKER === "TATA" &&
      (["P", "E"].includes(tokenData?.seller_type) ||
        ["P", "E"].includes(tokenData?.sellerType))));

export const useHandleSuccess = (
  dispatch,
  history,
  temp_data,
  type,
  show,
  saveQuoteData,
  enquiry_id,
  token,
  typeId,
  journey_type,
  _stToken,
  vt,
  autoRegister,
  theme_conf,
  tokenData,
  shared
) => {
  useEffect(() => {
    if (saveQuoteData && temp_data?.enquiry_id) {
      //GA event throw
      import.meta.env.VITE_PROD !== "YES" &&
        import.meta.env.VITE_BROKER === "BAJAJ" &&
        import.meta.env.VITE_BASENAME !== "NA" &&
        Extn.GA_Event("submit-success", type);
      enquiry_id?.corporate_id && Extn.notify(toast);
      enquiry_id?.corporate_id &&
        document.getElementById("fireBtn") &&
        document.getElementById("fireBtn").click();
      /*Handling redirection*/
      setTimeout(
        () => {
          history[autoRegister ? "replace" : "push"](
            `/${type}/registration?enquiry_id=${
              enquiry_id?.enquiryId || temp_data?.enquiry_id
            }${token ? `&xutm=${token}` : ``}${
              typeId ? `&typeid=${typeId}` : ``
            }${journey_type ? `&journey_type=${journey_type}` : ``}${
              _stToken ? `&stToken=${_stToken}` : ``
            }${vt ? `&vt=${vt}` : ``}${shared ? `&shared=${shared}` : ``}`
          );
        },
        enquiry_id?.corporate_id ? 5000 : 0
      );
    }
    return () => {
      /*Clearing state*/
      temp_data?.enquiry_id &&
        setTimeout(
          () => dispatch(clear("saveQuoteData")),
          enquiry_id?.corporate_id ? 4500 : 0
        );
      temp_data?.enquiry_id &&
        setTimeout(
          () => dispatch(clear("enquiry_id")),
          enquiry_id?.corporate_id ? 4500 : 20
        );
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [saveQuoteData, temp_data?.enquiry_id, show]);
};

//Generate OTP
export const useGenerateOTP = (tokenData, theme_conf, enquiry_id, setShow) => {
  useEffect(() => {
    if (
      theme_conf?.broker_config?.lead_otp &&
      ((import.meta.env.VITE_BROKER === "BAJAJ" &&
        import.meta.env.VITE_BASENAME === "general-insurance") ||
        (import.meta.env.VITE_BROKER === "HEROCARE" &&
          !(
            ["P", "E"].includes(tokenData?.seller_type) ||
            ["P", "E"].includes(tokenData?.sellerType)
          )) ||
        (import.meta.env.VITE_BROKER === "TATA" &&
          (["P", "E"].includes(tokenData?.seller_type) ||
            ["P", "E"].includes(tokenData?.sellerType)))) &&
      !_.isEmpty(enquiry_id)
    ) {
      setShow(true);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [enquiry_id]);
};
