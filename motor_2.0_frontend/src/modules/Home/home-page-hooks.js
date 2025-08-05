import { useEffect } from "react";
import swal from "sweetalert";
import { reloadPage, RedirectFn } from "utils";
import { clear, Prefill, LinkTrigger } from "./home.slice";
import { AccessControl, Disable_B2C } from "utils";
import _ from "lodash";
import { useSelector } from "react-redux";

export const useCheckEnquiry = (
  temp_data,
  location,
  type,
  history,
  enquiry_id,
  token
) => {
  const { theme_conf } = useSelector((state) => state.home);
  useEffect(() => {
    if (
      ![`/${type}/lead-page`, `/${type}/auto-register`].includes(
        location.pathname
      )
    ) {
      if (temp_data?.enquiry_id || (enquiry_id && enquiry_id !== "null")) {
      } else {
        swal("Info", "Enquiry id not found, redirecting to homepage", "info", {
          closeOnClickOutside: false,
        }).then(() => history.replace(`/${type}/lead-page`));
      }
    }

    //Redirection after breakin submission
    if (["Y", "y"].includes(temp_data?.userProposal?.isBreakinCase)) {
      swal("Info", "Breakin policy already generated.", "info", {
        closeOnClickOutside: false,
      }).then(() =>
        token
          ? reloadPage(
              theme_conf?.broker_config?.broker_asset?.other_failure_url?.url ||
                RedirectFn(token)
            )
          : history.replace(`/${type}/lead-page`)
      );
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data]);
};

export const useErrorHandling = (
  dispatch,
  error,
  temp_data,
  type,
  enquiry_id,
  errorSpecific,
  token,
  journey_type,
  typeId,
  _stToken,
  shared
) => {
  let rcNum = temp_data?.regNo;
  useEffect(() => {
    if (error && error.includes("newEnquiryId::")) {
      let processEnquiry = error.split("::");
      reloadPage(
        `${
          import.meta.env.VITE_BASENAME === "NA"
            ? ""
            : `/${import.meta.env.VITE_BASENAME}`
        }/${type}/registration?enquiry_id=${processEnquiry[1]}${
          token ? `&xutm=${token}` : ``
        }${journey_type ? `&journey_type=${journey_type}` : ``}${
          typeId ? `&typeid=${typeId}` : ``
        }${_stToken ? `&stToken=${_stToken}` : ``}${
          rcNum ? `&rcNum=${rcNum}` : ``
        }${shared ? `&shared=${shared}` : ``}`
      ); //adding the url with rc number
      //url enquiry to be updated here
    } else if (error) {
      swal({
        title: "Error",
        text: enquiry_id
          ? `${`Trace ID:- ${
              temp_data?.traceId ? temp_data?.traceId : enquiry_id
            }.\n Error Message:- ${error}`}`
          : error,
        icon: "error",
        buttons: {
          cancel: "Dismiss",
          ...(errorSpecific && {
            catch: {
              text: "See more details",
              value: "confirm",
            },
          }),
        },
        dangerMode: true,
      }).then((caseValue) => {
        switch (caseValue) {
          case "confirm":
            swal(
              "Error",
              enquiry_id
                ? `${`Trace ID:- ${
                    temp_data?.traceId ? temp_data?.traceId : enquiry_id
                  }.\n Error Message:- ${errorSpecific}`}`
                : errorSpecific,
              "error"
            );
            break;
          default:
            if (
              error.toLowerCase() === "token data missing" &&
              import.meta.env.VITE_BROKER === "RB"
            ) {
              localStorage.removeItem("SSO_user");
              localStorage.removeItem("SSO_user_motor");
              reloadPage(
                import.meta.env.VITE_PROD === "YES"
                  ? "https://partners.renewbuy.com/v2/"
                  : "https://partners.rbstaging.in/"
              );
            }
        }
      });
    }
    return () => {
      dispatch(clear());
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [error]);
};

export const useAccessControl = (type, typeAccess, history) => {
  useEffect(() => {
    if (!_.isEmpty(typeAccess)) {
      AccessControl(type, typeAccess, history);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [typeAccess]);
};

export const usePrefill = (dispatch, enquiry_id) => {
  useEffect(() => {
    if (enquiry_id) dispatch(Prefill({ enquiryId: enquiry_id }));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [enquiry_id]);
};

export const useLinkTrigger = (dispatch, key) => {
  useEffect(() => {
    key && dispatch(LinkTrigger({ key: key }));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key]);
};

export const useB2CAccess = (
  temp_data,
  checkSellerType,
  token,
  journey_type
) => {
  const { theme_conf } = useSelector((state) => state.home) || {};
  useEffect(() => {
    Disable_B2C(
      temp_data,
      checkSellerType,
      token,
      journey_type,
      false,
      theme_conf
    );
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [token, temp_data]);
};

export const getAvatar = (type) => {
  return type === "cv" && import.meta.env.VITE_BROKER === "BAJAJ"
    ? `${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/pickup.png`
    : type !== "bike"
    ? `${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/taxi-car1.png`
    : `${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/bike3.png`;
};
