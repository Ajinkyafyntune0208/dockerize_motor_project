import React, { useEffect, useState } from "react";
import { Spinner, Modal } from "react-bootstrap";
import _ from "lodash";
import { Button } from "components";
import { useSelector, useDispatch } from "react-redux";
import {
  clear,
  Prefill,
  ProposalPdf,
  VerifyGodigitKyc,
} from "../proposal.slice";
import swal from "sweetalert";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";
import { useMediaPredicate } from "react-media-hook";
import { currencyFormater, downloadFile } from "utils";
import { PreSubmitKyc } from "./kyc-status";
import { useProfileTracking } from "../proposal-hooks";
import { _paymentStageTracking } from "analytics/payment-initiated/payment-initiated";
import { _paymentTracking } from "analytics/proposal-tracking/payment-modal-tracking";
import { el } from "date-fns/locale";
import { CtUserLogin } from "analytics/clevertap";

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

const PaymentModal = (props) => {
  const { verifyCkycnum } = useSelector((state) => state.proposal);
  const { theme_conf, encryptUser } = useSelector((state) => state.home);
  const [submitData, setSubmitData] = useState();
  const [loading, setLoading] = useState(false);
  const dispatch = useDispatch();
  const enquiry_id = props?.enquiry_id;
  const lessthan768 = useMediaPredicate("(max-width: 768px)");
  const pre_payment_ckyc = true;

  const { temp_data, error_other, prefillLoad } = useSelector(
    (state) => state.proposal
  );

  const isProposalShareable =
    theme_conf?.broker_config?.broker_asset?.communication_configuration
      ?.proposal_payment;

  const disclaimer = (pg) => {
    if (pg) {
      return (
        theme_conf?.broker_config?.ckyc_redirection_message ||
        (import.meta.env.VITE_BROKER === "TATA"
          ? "You are being redirected to the Insurer website, for completing the Offline KYC process. TMIBASL has limited control over third-party websites and our privacy policy may not apply to them. Please ensure utmost care while sharing the details"
          : "You are being redirected to an external website")
      );
    } else {
      return (
        theme_conf?.broker_config?.payment_redirection_message ||
        (import.meta.env.VITE_BROKER === "TATA"
          ? "You are being redirected to the payment gateway website. TMIBASL has limited control over third-party websites and our privacy policy may not apply to them. Please ensure utmost care while sharing the details"
          : "You are being redirected to an external website")
      );
    }
  };

  const RenderDisclaimer = (pg) => `\n\n Note:- ${disclaimer(pg)}`;
  //Analytics | user profile tracking
  /*eslint-disable*/
  // props?.submit && useProfileTracking(dispatch, temp_data, encryptUser);

  useEffect(() => {
    props?.submit && setSubmitData(props?.submit);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [props?.submit]);

  useEffect(() => {
    if (props?.rsKycStatus?.kyc_status) {
      setSubmitData(props?.rsKycStatus);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [props?.rsKycStatus]);

  //load prefill data
  useEffect(() => {
    if (enquiry_id && props.show) {
      dispatch(Prefill({ enquiryId: enquiry_id }, true));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [enquiry_id, props.show]);

  // verify ckyc for godigit in RB
  const verifyCkyc = () => {
    setLoading(true);
    dispatch(
      VerifyGodigitKyc(
        {
          UserProductJourneyId: props.enquiry_id,
          proposalHash: props?.proposalHash,
        },
        setLoading
      )
    );
  };

  //Analytics | Payment Stage Tracking
  useEffect(() => {
    if (!_.isEmpty(temp_data?.userProposal && props?.show1)) {
      _paymentStageTracking(temp_data);
    }
    if (!!temp_data?.proposalExtraFields?.cisUrl && props?.show1) {
      downloadFile(temp_data?.proposalExtraFields?.cisUrl, false, true);
    }
  }, [temp_data, props?.show1]);

  useEffect(() => {
    if (
      verifyCkycnum &&
      pre_payment_ckyc &&
      props?.companyAlias === "godigit"
    ) {
      setSubmitData(verifyCkycnum);
      dispatch(clear("verifyCkycnum"));
    }
  }, [verifyCkycnum]);

  //onError
  useEffect(() => {
    if (error_other) {
      swal(
        "Error",
        props?.enquiry_id
          ? `${`Trace ID:- ${
              temp_data?.traceId ? temp_data?.traceId : props?.enquiry_id
            }.\n Error Message:- ${error_other}`}`
          : error_other,
        "error"
      );
    }
    return () => {
      dispatch(clear());
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [error_other]);

  //Finsall
  const isFullPaymentAvailable =
    temp_data?.selectedQuote?.finsall &&
    temp_data?.selectedQuote?.finsall.includes("FULL_PAYMENT");
  const isEmiAvailable =
    temp_data?.selectedQuote?.finsall &&
    temp_data?.selectedQuote?.finsall.includes("EMI");
  const finsallOptions = _.compact([
    isFullPaymentAvailable && "finsall",
    isEmiAvailable && "finsall-emi",
  ]);
  const isPgAvailable =
    temp_data?.selectedQuote?.finsall &&
    temp_data?.selectedQuote?.finsall.includes("IC_PAYMENT");

  // Enabling below Payment Popup only when cisEnabled (Bank Details) is selected through IC Config
  const RenderCIS = (
    <p style={{ color: "red", fontSize: "14px" }}>
      {temp_data?.selectedQuote?.companyAlias === "universal_sompo" ? (
        <b>
          By Proceeding,
          <p>
            I/We authorize the Company to share / verify the information
            provided by me/us pertaining to my proposal with rating agencies,
            third parties or services providers for the purpose of underwriting
            the proposal, issuance, servicing and claims settlement of the
            policy, thereafter. I hereby consent to and authorize Universal
            Sompo General Insurance Company Limited (“Company”) and its
            representatives to collect, use, share and disclose information
            provided by me, as per the Privacy policy of the Company. Company or
            its representatives are also hereby authorised to contact me
            (including overriding my registry on NCPR/NDNC and/or under any
            extant TRAI regulations) and / or notify about the services being
            rendered by the Company.
          </p>
          <p>
            In case of disability, I/We hereby declare that a duly authorized
            representative appointed by me has explained details with respect to
            the proposal form, policy documents, terms and conditions and the
            EIA in the language that is best understood by me.
          </p>
        </b>
      ) : (
        <b>
          By Proceeding, I/We hereby declare that the{" "}
          <u>
            <a href={temp_data?.proposalExtraFields?.cisUrl} target="_blank">
              Customer Information Sheet
            </a>
          </u>{" "}
          has been duly received and thoroughly reviewed. I/We confirm
          understanding and noting the details contained therein.
        </b>
      )}
    </p>
  );

  const renderPolicyDetails = () => {
    const {
      userProposal,
      breakinExpiryDate,
      quoteLog,
      selectedQuote,
      proposalExtraFields,
    } = temp_data || {};
    const {
      proposalNo,
      policyStartDate,
      policyEndDate,
      finalPayableAmount,
      idv,
    } = userProposal || {};
    const isIDVDifferent = quoteLog?.idv * 1 !== idv * 1;
    const formattedPolicyStartDate = policyStartDate
      ? policyStartDate.split("-").join("/")
      : "N/A";
    const totalPremium = currencyFormater(Number(finalPayableAmount) || 0);
    const insuredDeclaredValue = currencyFormater(Number(idv * 1) || 0);
    const isUniversalSompo = selectedQuote?.companyAlias === "universal_sompo";
    const isShriram = selectedQuote?.companyAlias === "shriram";
    const showCIS =
      (!!proposalExtraFields?.cisUrl || isUniversalSompo) &&
      Array.isArray(props?.fields) &&
      props?.fields.includes("cisEnabled");

    if (_.isEmpty(userProposal) || prefillLoad) {
      return (
        <p>
          <Spinner animation="border" size="sm" /> Please wait.
        </p>
      );
    }

    return (
      <div>
        <p>
          {
            <text>
              Your new <b>{selectedQuote?.companyName}'s</b> policy 
              {proposalNo ? ` with proposal number ${proposalNo}` : ""}{" "}
            </text>
          }
          {breakinExpiryDate && !(policyStartDate || policyEndDate) ? (
            isShriram ? (
              <text>
                will commence after <b>2 days post</b> payment
              </text>
            ) : (
              <text>
                will commence on the <b>payment date.</b>
              </text>
            )
          ) : (
            <text>
              will start from <b>{formattedPolicyStartDate}.</b>
            </text>
          )}
        </p>
        <p name="premium_payable">{`The total premium payable is ₹ ${totalPremium}.`}</p>
        <p>{RenderDisclaimer()}</p>
        {isIDVDifferent && (
          <p style={{ color: "red" }}>
            {`Your Insured Declared Value is ₹ ${insuredDeclaredValue}.`}
          </p>
        )}
        {showCIS && RenderCIS}
        <p>Do you wish to proceed?</p>
      </div>
    );
  };

  //Identity Set for CT
  temp_data?.userProposal?.mobileNumber &&
    CtUserLogin(temp_data?.userProposal?.mobileNumber, false, false, temp_data);

  return (
    <Modal
      {...props}
      size={
        temp_data?.userProposal?.isFinsallAvailable === "Y" ||
        temp_data?.selectedQuote?.companyAlias === "universal_sompo"
          ? "lg"
          : "md"
      }
      aria-labelledby="contained-modal-title-vcenter"
      centered
      backdrop={"static"}
      keyboard={false}
    >
      {!submitData?.kyc_status &&
      temp_data?.userProposal?.isCkycVerified !== "Y" &&
      props?.ckycPresent &&
      pre_payment_ckyc &&
      ["godigit", "royal_sundaram", "kotak", "raheja", "new_india"].includes(
        props?.companyAlias
      ) ? (
        <PreSubmitKyc
          submitData={submitData}
          companyAlias={props?.companyAlias}
          loading={loading}
          disclaimer={RenderDisclaimer}
          pre_payment_ckyc={pre_payment_ckyc}
          verifyCkyc={verifyCkyc}
          lessthan768={lessthan768}
        />
      ) : (
        <>
          <Modal.Header closeButton>
            <Modal.Title id="contained-modal-title-vcenter">
              Confirmation Required
            </Modal.Title>
          </Modal.Header>
          <Modal.Body>{renderPolicyDetails()}</Modal.Body>
          <Modal.Footer>
            <Button
              type="button"
              buttonStyle="outline-solid"
              id="proposal-pdf-download"
              onClick={() => [
                dispatch(ProposalPdf(props._proposalPdf())),
                props?.downloadEvent(),
              ]}
              hex1={
                Theme?.paymentConfirmation?.Button?.hex1
                  ? Theme?.paymentConfirmation?.Button?.hex1
                  : "#4ca729"
              }
              hex2={
                Theme?.paymentConfirmation?.Button?.hex2
                  ? Theme?.paymentConfirmation?.Button?.hex2
                  : "#4ca729"
              }
              borderRadius="5px"
              color={
                Theme?.PaymentConfirmation?.buttonTextColor
                  ? Theme?.PaymentConfirmation?.buttonTextColor
                  : "white"
              }
              style={{ ...(lessthan768 && { width: "100%" }) }}
              shadow={"none"}
            >
              <text
                style={{
                  fontSize: "15px",
                  padding: "-20px",
                  margin: "-20px -5px -20px -5px",
                  fontWeight: "400",
                }}
              >
                {lessthan768 ? "Download Proposal Pdf" : ""}
                <i
                  className={`fa fa-download ${lessthan768 ? "ml-2" : ""}`}
                ></i>
              </text>
            </Button>
            {(isProposalShareable?.email ||
              isProposalShareable?.whatsapp_api ||
              isProposalShareable?.whatsapp_redirection ||
              isProposalShareable?.sms) && (
              <Button
                type="button"
                buttonStyle="outline-solid"
                id="share-proposal"
                onClick={() => {
                  return [
                    props?.setSendQuotes(true),
                    props?.setShareProposalPayment(true),
                    props?.shareEvent(),
                    props?.onHide(),
                  ];
                }}
                hex1={
                  Theme?.paymentConfirmation?.Button?.hex1
                    ? Theme?.paymentConfirmation?.Button?.hex1
                    : "#4ca729"
                }
                hex2={
                  Theme?.paymentConfirmation?.Button?.hex2
                    ? Theme?.paymentConfirmation?.Button?.hex2
                    : "#4ca729"
                }
                borderRadius="5px"
                color={
                  Theme?.PaymentConfirmation?.buttonTextColor
                    ? Theme?.PaymentConfirmation?.buttonTextColor
                    : "white"
                }
                style={{ ...(lessthan768 && { width: "100%" }) }}
                shadow={"none"}
              >
                <text
                  style={{
                    fontSize: "15px",
                    padding: "-20px",
                    margin: "-20px -5px -20px -5px",
                    fontWeight: "400",
                  }}
                >
                  {lessthan768 ? "Share Payment Link" : ""}
                  <i
                    className={`fa fa-share-alt ${lessthan768 ? "ml-2" : ""}`}
                  ></i>
                </text>
              </Button>
            )}
            {temp_data?.userProposal?.isFinsallAvailable === "Y" && (
              <>
                {finsallOptions.map((item) => {
                  return (
                    <Button
                      type="submit"
                      buttonStyle="outline-solid"
                      onClick={() => props?.payment(item)}
                      id="pay-with-finsall"
                      hex1={
                        Theme?.paymentConfirmation?.Button?.hex1
                          ? Theme?.paymentConfirmation?.Button?.hex1
                          : "#4ca729"
                      }
                      hex2={
                        Theme?.paymentConfirmation?.Button?.hex2
                          ? Theme?.paymentConfirmation?.Button?.hex2
                          : "#4ca729"
                      }
                      borderRadius="5px"
                      color={
                        Theme?.PaymentConfirmation?.buttonTextColor
                          ? Theme?.PaymentConfirmation?.buttonTextColor
                          : "white"
                      }
                      style={{ ...(lessthan768 && { width: "100%" }) }}
                    >
                      <text
                        style={{
                          fontSize: "15px",
                          padding: "-20px",
                          margin: "-20px -5px -20px -5px",
                          fontWeight: "400",
                        }}
                      >
                        {`Pay with ${item === "finsall" ? "Finsall" : "EMI"}`}
                      </text>
                    </Button>
                  );
                })}
              </>
            )}
            {(temp_data?.userProposal?.isFinsallAvailable !== "Y" ||
              isPgAvailable) && (
              <Button
                type="submit"
                buttonStyle="outline-solid"
                onClick={() => props?.payment()}
                id="proceed-to-payment"
                hex1={
                  Theme?.paymentConfirmation?.Button?.hex1
                    ? Theme?.paymentConfirmation?.Button?.hex1
                    : "#4ca729"
                }
                hex2={
                  Theme?.paymentConfirmation?.Button?.hex2
                    ? Theme?.paymentConfirmation?.Button?.hex2
                    : "#4ca729"
                }
                borderRadius="5px"
                color={
                  Theme?.PaymentConfirmation?.buttonTextColor
                    ? Theme?.PaymentConfirmation?.buttonTextColor
                    : "white"
                }
                style={{ ...(lessthan768 && { width: "100%" }) }}
                shadow={"none"}
              >
                <text
                  style={{
                    fontSize: "15px",
                    padding: "-20px",
                    margin: "-20px -5px -20px -5px",
                    fontWeight: "400",
                  }}
                >
                  {"Proceed to payment"}
                  <i className="fa fa-arrow-circle-right ml-2"></i>
                </text>
              </Button>
            )}
          </Modal.Footer>
        </>
      )}
    </Modal>
  );
};

export default PaymentModal;
