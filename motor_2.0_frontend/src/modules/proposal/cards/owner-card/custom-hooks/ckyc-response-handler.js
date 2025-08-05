import { useEffect } from "react";
import swal from "sweetalert";
import { HyperVergeFn } from "modules/proposal/form-section/proposal-logic";
import { downloadFile } from "utils";
import { useDispatch } from "react-redux";
import { clear, VerifyCkycnum } from "../../../proposal.slice";
import _ from "lodash";
import { _ckycTracking } from "analytics/proposal-tracking/ckyc-tracking";

export const useCkycResponseHandler = ({
  temp_data,
  verifyCkycnum,
  enquiry_id,
  setOwner,
  tempOwner,
  setOtp_id,
  otp_id,
  show1,
  setShow1,
  setResubmit,
  setValue,
  setCustomerDetails,
  ckycValue,
  fields,
  uploadFile,
  setShow,
  setuploadFile,
  setckycValue,
  identity,
  setIsRedirected,
  setLoading,
  disclaimer,
  setVerifiedData,
}) => {
  const dispatch = useDispatch();
  const companyAlias = temp_data?.selectedQuote?.companyAlias;
  const RenderDisclaimer = `\n\n Note:- ${disclaimer}`;

  //CKYC session in new tab
  const newTab =
    companyAlias === "universal_sompo" ||
    companyAlias === "edelweiss" ||
    companyAlias === "godigit";
  //form data is required for liberty
  const isForm =
    companyAlias === "liberty_videocon" || companyAlias === "future_generali";

  const handler = (HyperKycResult) => {
    if (HyperKycResult.status === "error") {
      swal(
        "Error",
        HyperKycResult?.errorMessage || "Something went wrong",
        "error"
      );
    } else if (
      HyperKycResult.status === "auto_approved" ||
      HyperKycResult.status === "auto_declined" ||
      HyperKycResult.status === "needs_review"
    ) {
      //A Delay is required
      setLoading(true);
      setTimeout(() => {
        dispatch(
          VerifyCkycnum({
            HyperKycResult,
            companyAlias,
            mode: "ckyc",
            enquiryId: enquiry_id,
          })
        );
        setLoading(false);
      }, 5000);
    }
  };


  useEffect(() => {
    //Error after ckyc number verification failure
    if (
      verifyCkycnum &&
      !verifyCkycnum?.verification_status &&
      companyAlias === "united_india"
    ) {
      if (verifyCkycnum?.accessToken) {
        HyperVergeFn(
          verifyCkycnum?.accessToken,
          companyAlias,
          enquiry_id,
          handler,
          temp_data
        );
      } else {
        swal({
          title: "Please Note",
          text:
            verifyCkycnum?.message.replace(/<break>/g, `\n`) ||
            "Something went wrong",
          icon: "error",
          buttons: {
            cancel: "Retry",
          },
          dangerMode: true,
          closeOnClickOutside: false,
        });
      }
    }
    //Error after ckyc number verification failure
    else if (
      verifyCkycnum &&
      !verifyCkycnum?.verification_status &&
      ckycValue === "YES"
    ) {
      verifyCkycnum?.redirection_url && companyAlias === "cholla_mandalam"
        ? swal({
            title: "Confirm Action",
            text: verifyCkycnum?.message
              ? `${verifyCkycnum?.message.replace(
                  /<break>/g,
                  `\n`
                )}${RenderDisclaimer}`
              : `CKYC number verification failed.${RenderDisclaimer}`,
            icon: "info",
            buttons: {
              cancel: verifyCkycnum?.message.includes("Edit Details")
                ? "Edit Details"
                : "Try again",
              catch: {
                text: "Redirect for verification",
                value: "confirm",
              },
            },
            dangerMode: true,
            closeOnClickOutside: false,
          }).then((caseValue) => {
            switch (caseValue) {
              case "confirm":
                setIsRedirected(true);
                const metaData = verifyCkycnum?.meta_data;
                downloadFile(
                  verifyCkycnum?.redirection_url,
                  false,
                  newTab,
                  isForm,
                  metaData
                );
                break;
              default:
            }
          })
        : swal({
            title: "Confirm Action",
            text: verifyCkycnum?.message
              ? `${verifyCkycnum?.message.replace(/<break>/g, `\n`)}`
              : "CKYC number verification failed. Please try with PAN number",
            icon: "info",
            buttons: {
              cancel: verifyCkycnum?.message.includes("Edit Details")
                ? "Edit Details"
                : "Try again",
              catch: {
                text: "Try using other ID",
                value: "confirm",
              },
            },
            dangerMode: true,
            closeOnClickOutside: false,
          }).then((caseValue) => {
            switch (caseValue) {
              case "confirm":
                setckycValue("NO");
                setValue("ckycNumber", "");
                identity && setValue(identity, "");
                break;
              default:
            }
          });
      //Error after step1 failure.
      //If redirection URL is present then user is redirected to vendor's ckyc portal
    } else if (
      verifyCkycnum &&
      !verifyCkycnum?.verification_status &&
      ckycValue === "NO" &&
      verifyCkycnum?.redirection_url
    ) {
      swal({
        title: "Confirm Action",
        text: verifyCkycnum?.message
          ? `${verifyCkycnum?.message.replace(
              /<break>/g,
              `\n`
            )}${RenderDisclaimer}`
          : `CKYC number verification failed.${RenderDisclaimer}`,
        icon: "info",
        buttons: {
          cancel: verifyCkycnum?.message.includes("Edit Details")
            ? "Edit Details"
            : "Try again",
          catch: {
            text: "Redirect for verification",
            value: "confirm",
          },
        },
        dangerMode: true,
        closeOnClickOutside: false,
      }).then((caseValue) => {
        switch (caseValue) {
          case "confirm":
            setIsRedirected(true);
            const metaData = verifyCkycnum?.meta_data;
            downloadFile(
              verifyCkycnum?.redirection_url,
              false,
              newTab,
              isForm,
              metaData
            );
            break;
          default:
        }
      });
      //On getting otp
    } else if (
      verifyCkycnum &&
      !verifyCkycnum?.verification_status &&
      ckycValue === "NO" &&
      !verifyCkycnum?.redirection_url &&
      !!verifyCkycnum?.otp_id
    ) {
      swal("Success", "OTP sent successfully", "success").then(() => {
        setOtp_id(verifyCkycnum?.otp_id);
        setShow1(true);
      });
      //Step 2 failure
    } else if (
      verifyCkycnum &&
      !verifyCkycnum?.verification_status &&
      ckycValue === "NO" &&
      companyAlias === "sbi"
    ) {
      swal(
        "Please note",
        verifyCkycnum?.message.replace(/<break>/g, `\n`) ||
          "No Record found. Try other options or try again.",
        "error"
      ).then(() => setuploadFile(true));
    } else if (
      verifyCkycnum &&
      !verifyCkycnum?.verification_status &&
      ckycValue === "NO" &&
      fields?.includes("fileupload") &&
      !uploadFile
    ) {
      show1 && setShow1(false);
      otp_id && setOtp_id();
      swal({
        title: "Confirm Action",
        text: verifyCkycnum?.message
          ? `${verifyCkycnum?.message.replace(/<break>/g, `\n`)}`
          : "Ckyc number verification failed. Please upload the required files for verification.",
        icon: "info",
        buttons: {
          cancel: verifyCkycnum?.message.includes("Edit Details")
            ? "Edit Details"
            : "Try Again",
          catch: {
            text: "Try other method",
            value: "confirm",
          },
        },
        dangerMode: true,
        closeOnClickOutside: false,
      }).then((caseValue) => {
        switch (caseValue) {
          case "confirm":
            setuploadFile(true);
            break;
          default:
        }
      });
      //Step 3 failure
    } else if (
      verifyCkycnum &&
      !verifyCkycnum?.verification_status &&
      ckycValue === "NO" &&
      fields?.includes("fileupload") &&
      uploadFile
    ) {
      swal("Error", "CKYC number verification failed.", "error", {
        closeOnClickOutside: false,
      });
    } else if (
      verifyCkycnum &&
      !verifyCkycnum?.verification_status &&
      !verifyCkycnum?.ckyc_id &&
      !verifyCkycnum?.otp_id &&
      !!verifyCkycnum?.message
    ) {
      swal("Error", verifyCkycnum?.message.replace(/<break>/g, `\n`), "error", {
        closeOnClickOutside: false,
      });
    } else if (
      verifyCkycnum &&
      !!verifyCkycnum?.verification_status &&
      !!verifyCkycnum?.customer_details
    ) {
      //Analytics | CKYC Success Tracking
      _ckycTracking(temp_data);
      //if ckyc is verified and ic is sending back data then that data should be prefilled and those fields should be blocked from editing
      if (companyAlias === "sbi") {
        setCustomerDetails(verifyCkycnum?.customer_details);
        setShow(true);
      } else {
        show1 && setShow1(false);
        otp_id && setOtp_id();
        Object.keys(tempOwner)?.forEach((each) => {
          setValue(each, tempOwner[each]);
        });
        //verified data consists keys that should not be editable on ckyc verification
        setVerifiedData(Object.keys(verifyCkycnum?.customer_details));

        Object.keys(verifyCkycnum?.customer_details)?.forEach((each) => {
          verifyCkycnum?.customer_details[each] &&
            setValue(each, verifyCkycnum?.customer_details[each]);
        });
        if (
          verifyCkycnum?.customer_details &&
          (Object.keys(verifyCkycnum?.customer_details)?.includes(
            "addressLine1"
          ) ||
            Object.keys(verifyCkycnum?.customer_details)?.includes(
              "addressLine2"
            ) ||
            Object.keys(verifyCkycnum?.customer_details)?.includes(
              "addressLine3"
            ))
        ) {
          let { addressLine1, addressLine2, addressLine3 } =
            verifyCkycnum?.customer_details || {};
          setValue(
            "address",
            `${addressLine1 ? addressLine1 : ""}${
              addressLine2 ? ` ${addressLine2}` : ""
            }${addressLine3 ? ` ${addressLine3}` : ""}`
          );
        }
        if (verifyCkycnum?.meta_data?.ckyc_status === "CKYCInProgress") {
          swal(
            "Documents uploaded Successfully",
            "Insurance company is reviewing your documents and will update you the status.",
            "info"
          ).then(() => {
            dispatch(clear("verifyCkycnum"));
          });
        } else {
          !_.isEmpty(verifyCkycnum?.customer_details) &&
            setCustomerDetails(verifyCkycnum?.customer_details);
          !_.isEmpty(verifyCkycnum?.customer_details) && setShow(true);
          _.isEmpty(verifyCkycnum?.customer_details) &&
            swal("Success", "CKYC verified.", "success").then(() => {
              dispatch(clear("verifyCkycnum"));
            });
        }
        setResubmit(true);
      }
    } else if (verifyCkycnum && !!verifyCkycnum?.verification_status) {
      show1 && setShow1(false);
      otp_id && setOtp_id();
      setOwner(tempOwner);
      dispatch(clear("verifyCkycnum"));
    }
  }, [verifyCkycnum]);
};
