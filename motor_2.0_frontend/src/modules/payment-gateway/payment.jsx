import React, { useEffect, useMemo, useRef } from "react";
import { useSelector, useDispatch } from "react-redux";
import { useLocation, useHistory } from "react-router";
import {
  PaymentApi,
  clear,
  saveOrder,
  raw as ClearRaw,
} from "./payment-gateway.slice";
import { Loader } from "components";
import { reloadPage, Encrypt, scrollToTop } from "utils";
import swal from "sweetalert";
import _ from "lodash";
import { TypeReturn } from "modules/type";
import { fetchToken } from "utils";
import { _paymentInitTracking } from "analytics/payment-initiated/payment-initiated";

export const Payment = (props) => {
  const location = useLocation();
  const history = useHistory();
  const dispatch = useDispatch();
  const query = new URLSearchParams(location.search);
  const enquiry_id = query.get("enquiry_id");
  const breakin = query.get("breakin");
  const token = query.get("xutm") || localStorage?.SSO_user_motor;
  const typeId = query.get("typeid");
  const journey_type = query.get("journey_type");
  const shared = query.get("shared");
  const icr = query.get("icr");
  const { temp_data } = useSelector((state) => state.proposal);
  const { payment, error, loading, success, RZerror, raw } = useSelector(
    (state) => state.payment
  );
  const { type } = props?.match?.params;
  const PolicyId = temp_data?.selectedQuote?.policyId || "";
  const companyAlias = !_.isEmpty(temp_data?.selectedQuote)
    ? temp_data?.selectedQuote?.companyAlias
    : "";
  const _stToken = fetchToken();

  //scroll to top
  useEffect(() => {
    scrollToTop();
  }, []);

  //Token expire
  useEffect(() => {
    if (raw?.redirection_link && !raw?.status) {
      reloadPage(raw?.redirection_link);
    }
    return () => {
      dispatch(ClearRaw({}));
    };
  }, [raw?.redirection_link]);

  useEffect(() => {
    if (!_.isEmpty(temp_data) && enquiry_id) {
      _paymentInitTracking(temp_data);
    }
    if (_.isEmpty(temp_data) && enquiry_id) {
      history.replace(
        `/${type}/proposal-page?enquiry_id=${enquiry_id}&dropout=${Encrypt(
          true
        )}${token ? `&xutm=${token}` : ``}${typeId ? `&typeid=${typeId}` : ``}${
          journey_type ? `&journey_type=${journey_type}` : ``
        }${icr ? `&icr=${icr}` : ``}${_stToken ? `&stToken=${_stToken}` : ``}${
          shared ? `&shared=${shared}` : ``
        }`
      );
    }
  }, [temp_data, enquiry_id]);

  //dispatch
  useMemo(() => {
    if (PolicyId && companyAlias && enquiry_id) {
      dispatch(
        PaymentApi(
          {
            policyId: PolicyId,
            companyAlias,
            enquiryId: enquiry_id,
            userProductJourneyId: enquiry_id,
            lastProposalModifiedTime: temp_data?.lastProposalModifiedTime,
          },
          TypeReturn(type) && TypeReturn(type) !== "cv"
            ? TypeReturn(type)
            : false
        )
      );
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [PolicyId, companyAlias, enquiry_id]);

  useEffect(() => {
    if (payment?.payment_type || payment?.paymentType) {
      if (payment?.paymentUrl) {
        reloadPage(`${payment?.paymentUrl}`);
      }
      return () => {
        dispatch(clear("payment"));
      };
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [payment]);

  //onError
  useEffect(() => {
    if (error) {
      swal(
        "Error",
        `${`Trace ID:- ${
          temp_data?.traceId ? temp_data?.traceId : enquiry_id
        }.\n Error Message:- ${error}`}`,
        "error"
      ).then(() => {
        if (
          [
            "Proposal integrity check failed. You will be redirected to quote page.",
            "Proposal integrity check failed.",
          ].includes(error)
        ) {
          if (
            error ===
            "Proposal integrity check failed. You will be redirected to quote page."
          ) {
            reloadPage(
              window.location.href.replace(/payment-gateway/g, "quotes")
            );
          }
          if (error === "Proposal integrity check failed.") {
            reloadPage(window.location.href);
          }
        } else {
          if (breakin) {
            history.replace(
              `/${type}/proposal-page?enquiry_id=${enquiry_id}&dropout=${Encrypt(
                true
              )}${token ? `&xutm=${token}` : ``}${
                typeId ? `&typeid=${typeId}` : ``
              }${journey_type ? `&journey_type=${journey_type}` : ``}${
                icr ? `&icr=${icr}` : ``
              }${_stToken ? `&stToken=${_stToken}` : ``}${
                shared ? `&shared=${shared}` : ``
              }`
            );
          } else {
            history.replace(
              `/${type}/proposal-page?enquiry_id=${enquiry_id}&dropout=${Encrypt(
                true
              )}${token ? `&xutm=${token}` : ``}${
                typeId ? `&typeid=${typeId}` : ``
              }${journey_type ? `&journey_type=${journey_type}` : ``}${
                icr ? `&icr=${icr}` : ``
              }${_stToken ? `&stToken=${_stToken}` : ``}${
                shared ? `&shared=${shared}` : ``
              }`
            );
          }
        }
      });
    }
    return () => {
      dispatch(clear("urlLoad"));
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [error]);

  //Razorpay onSuccess
  useEffect(() => {
    if (success) {
      history.replace(
        `/payment-success?enquiry_id=${enquiry_id}${
          _stToken ? `&stToken=${_stToken}` : ``
        }`
      );
    }

    return () => {
      dispatch(clear("pdf"));
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [success]);

  //Razorpay onError
  useEffect(() => {
    if (RZerror) {
      history.replace(
        `/payment-failure?enquiry_id=${enquiry_id}${
          _stToken ? `&stToken=${_stToken}` : ``
        }`
      );
    }

    return () => {
      dispatch(clear("pdf"));
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [RZerror]);

  //form submission
  const formRef = useRef(null);
  useEffect(() => {
    if (
      Number(payment?.payment_type) === 0 ||
      Number(payment?.paymentType) === 0
    ) {
      formRef.current.submit();
    }
  }, [payment]);

  const Inputs = !_.isEmpty(payment?.form_data) ? (
    Object.keys(payment?.form_data).map((k, i) => {
      return (
        <input type="hidden" name={`${k}`} value={payment?.form_data[`${k}`]} />
      );
    })
  ) : (
    <noscript />
  );

  const FORM = payment?.payment_type !== "PAYTM" && (
    <form
      ref={formRef}
      id="future-generali-gateway"
      action={payment?.form_action}
      method={payment?.form_method}
    >
      {Inputs}
    </form>
  );

  /*----------Razor-pay----------*/
  const loadRazorpay = (
    url = "https://checkout.razorpay.com/v1/checkout.js"
  ) => {
    return new Promise((resolve) => {
      const script = document.createElement("script");
      script.src = url;
      script.onload = () => {
        resolve(true);
      };
      script.onerror = () => {
        resolve(false);
      };
      document.body.appendChild(script);
    });
  };

  if (document.domain === "localhost") {
    // develoment
  } else {
    //production
  }

  // display RazorPay
  useEffect(() => {
    if (payment?.orderId) displayRazorpay();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [payment?.orderId]);

  const displayRazorpay = async () => {
    const result = await loadRazorpay(
      "https://checkout.razorpay.com/v1/checkout.js"
    );

    if (!result) {
      swal("Info", "Razorpay SDK failed to load. Are you online?", "info");
      return;
    }

    const options = {
      key: payment?.clientKey || import.meta.env.VITE_KEY_ID_RAZORPAY_TEST, // Enter the Key ID generated from the Dashboard
      id: payment?.order_id,
      amount: payment?.amount, // Amount is in currency subunits. Default currency is INR. Hence, 50000 refers to 50000 paise
      currency: "INR",
      name:
        temp_data?.selectedQuote?.companyAlias === "sbi"
          ? "SBI General Insurance"
          : temp_data?.selectedQuote?.productName,
      // retry: {enabled: false},
      // retry: false,
      description: "Motor Insurance, Compare and get the best deals.",
      image: temp_data?.selectedQuote?.companyLogo,
      // order_id: order.id, //This is a sample Order ID. Pass the `id` obtained in the response of Step 1
      order_id: payment?.order_id || payment?.orderId,
      handler: function (response) {
        return [
          dispatch(
            saveOrder(
              {
                ...response,
                order_id: payment?.order_id,
                enquiryId: enquiry_id,
              },
              payment?.returnUrl
            )
          ),
        ];
      },
      modal: {
        ondismiss: function () {
          swal(
            "Redirecting",
            "Payment cancelled! Redirecting to proposal",
            "info"
          ).then(() => {
            history.replace(
              `/${type}/proposal-page?enquiry_id=${enquiry_id}&dropout=${Encrypt(
                true
              )}${token ? `&xutm=${token}` : ``}${
                typeId ? `&typeid=${typeId}` : ``
              }${journey_type ? `&journey_type=${journey_type}` : ``}${
                icr ? `&icr=${icr}` : ``
              }${_stToken ? `&stToken=${_stToken}` : ``}${
                shared ? `&shared=${shared}` : ``
              }`
            );
          });
        },
      },
      prefill: {
        email: temp_data?.userEmail || "",
        contact: temp_data?.userMobile || "",
      },
      notes: {
        address: "Address Here",
      },
      theme: {
        color: "#45b4d9",
      },
    };
    let paymentObject = new window.Razorpay(options);
    paymentObject.on("payment.failed", function ({ error }) {
      console.table(error);
      dispatch(
        saveOrder(
          { ...error, order_id: payment?.order_id, enquiryId: enquiry_id },
          payment?.returnUrl
        )
      );
      swal(
        "Error",
        enquiry_id
          ? `${`Trace ID:- ${
              temp_data?.traceId ? temp_data?.traceId : enquiry_id
            }.\n Error Message:- ${error}`}`
          : error.description,
        "warning"
      ).then(() => {
        history.replace(
          `/payment-failure?enquiry_id=${enquiry_id}&dropout=${Encrypt(true)}${
            token ? `&xutm=${token}` : ``
          }${typeId ? `&typeid=${typeId}` : ``}${
            journey_type ? `&journey_type=${journey_type}` : ``
          }${icr ? `&icr=${icr}` : ``}${_stToken ? `&stToken=${_stToken}` : ``}
          ${shared ? `&shared=${shared}` : ``}`
        );
      });
    });
    paymentObject.open();
    // e.preventDefault();
  };

  /*----x-----Razor-pay-----x----*/

  /*----------paytm----------*/
  function onScriptLoad() {
    var config = {
      root: "",
      flow: "DEFAULT",
      data: {
        orderId: payment?.form_data?.body?.orderId,
        token: payment?.form_data?.txnToken,
        tokenType: "TXN_TOKEN",
        amount: payment?.form_data?.txnAmount?.value,
      },
      handler: {
        notifyMerchant: function (eventName, data) {
          console.log("notifyMerchant handler function called");
          console.log("eventName => ", eventName);
          console.log("data => ", data);
        },
      },
    };

    if (window.Paytm && window.Paytm.CheckoutJS) {
      window.Paytm.CheckoutJS.init(config)
        .then(function onSuccess() {
          window.Paytm.CheckoutJS.invoke();
        })
        .catch(function onError(error) {
          console.log("Error => ", error);
        });
    }
  }

  /*----x-----paytm----x-----*/
  return !loading ? (
    <div style={{ height: "100vh" }}>
      {payment?.payment_type ? (
        payment?.payment_type === "PAYTM" ? (
          [onScriptLoad(), <noscript />]
        ) : (
          <noscript />
        )
      ) : (
        FORM
      )}
    </div>
  ) : (
    <div style={{ height: "100vh" }}>
      <Loader />
    </div>
  );
};
