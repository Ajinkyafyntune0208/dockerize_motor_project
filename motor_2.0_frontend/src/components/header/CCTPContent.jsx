import React, { useEffect, useState } from "react";
import LogoutIcon from "@mui/icons-material/Logout";
import { _generateKey, downloadFile } from "utils";
import _ from "lodash";
//prettier-ignore
import { CallButton, ConfirmButton, PosWrapper,
         PosId, PosDiv, SendQuery,
       } from "./HeaderStyle";
import { _comparePDFTracking } from "analytics/compare-page/compare-tracking";
import { TypeReturn } from "modules/type";
import { useSelector } from "react-redux";
export const CCTPContent = (props) => {
  //prettier-ignore
  const { lessthan767, id, token, query, tokenData, filArr, showAgentDetails,
          lessthan993, handleRedirection, lessthan360, ut, type, validQuote,
          quoteComprehesive, quotesLoaded, quotetThirdParty, quoteShortTerm,
          setModal, MdOutlineMessage, comparePdfData, location, pdfBackground,
          loc, setSendQuotes, includeRoute, includeRouteShare, temp_data
        } = props;

  const [showDiv, setShowDiv] = useState(false);
  const { temp_data: proposalState } = useSelector((state) => state.proposal);

  useEffect(() => {
    setTimeout(() => {
      setShowDiv(true);
    }, 3000);
  }, []);

  const source = query.get("source");

  //variable for lead source check and restricting the eb-platform to hide agent details
  const leadSourceCheck =
    ((temp_data?.leadSource === "eb-platform" ||
      proposalState.leadSource === "eb-platform") &&
      import.meta.env?.VITE_BROKER === "ACE") ||
    ((source === "QR" ||
      source === "QR-REDIRECTION" ||
      temp_data?.leadSource === "QR" ||
      temp_data?.leadSource === "QR-REDIRECTION" ||
      proposalState.leadSource === "QR" ||
      proposalState.leadSource === "QR-REDIRECTION") &&
      import.meta.env?.VITE_BROKER === "BAJAJ");

  const handlePdfDownload = async () => {
    if (comparePdfData) {
      // Analytics | Compare PDF data tracking
      _comparePDFTracking(
        comparePdfData?.insurance_details,
        temp_data,
        TypeReturn(type),
        comparePdfData?.selectedAddons
      );

      const utf8Str = JSON.stringify(comparePdfData);
      const url = `${import.meta.env?.VITE_API_BASE_URL}/policyComparePdf`;

      try {
        const response = await fetch(url, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            validation: _generateKey(url),
          },
          body: JSON.stringify({ data: utf8Str }),
        });

        if (!response.ok) throw new Error("Failed to download PDF");

        const blob = await response.blob();
        const downloadUrl = window.URL.createObjectURL(blob);

        const a = document.createElement("a");
        a.href = downloadUrl;
        a.download = "compare.pdf";
        document.body.appendChild(a);
        a.click();
        a.remove();

        window.URL.revokeObjectURL(downloadUrl);
      } catch (error) {
        console.error("Error downloading PDF:", error);
      }
    }
  };

  const [copied, setCopied] = useState(false);

  function copyToClipboard(text) {
    setCopied(true);
    var selected = false;
    var el = document.createElement("textarea");
    el.value = text;
    el.setAttribute("readonly", "");
    el.style.position = "absolute";
    el.style.left = "-9999px";
    document.body.appendChild(el);
    if (document.getSelection().rangeCount > 0) {
      selected = document.getSelection().getRangeAt(0);
    }
    el.select();
    document.execCommand("copy");
    document.body.removeChild(el);
    if (selected) {
      document.getSelection().removeAllRanges();
      document.getSelection().addRange(selected);
    }
  }

  useEffect(() => {
    let timeoutId;

    if (copied) {
      timeoutId = setTimeout(() => {
        setCopied(false);
      }, 2000);
    }

    return () => {
      clearTimeout(timeoutId);
    };
  }, [copied]);

  return (
    <>
      {!leadSourceCheck && (
        <div>
          {import.meta.env.VITE_BROKER !== "RB" &&
            token &&
            !lessthan993 &&
            (!_.isEmpty(tokenData) || !_.isEmpty(filArr)) &&
            query.get("xutm") &&
            (localStorage?.SSO_user_motor ||
              import.meta.env.VITE_BROKER !== "RB") && (
              <PosWrapper>
                <PosId className="circle">
                  <PosId latter lessthan767={lessthan767}>
                    <text style={{ marginBottom: "0.2px", color: "white" }}>
                      {!_.isEmpty(filArr)
                        ? filArr[0]?.agentName.trim()[0]?.toUpperCase() || ""
                        : (tokenData?.seller_name[0] &&
                            tokenData?.seller_name.trim()[0]?.toUpperCase()) ||
                          ""}
                    </text>
                  </PosId>
                </PosId>
                {!lessthan993 && (
                  <div
                    style={{
                      display: "flex",
                      marginRight: lessthan767 ? "0px" : "30px",
                    }}
                  >
                    <PosDiv>
                      <PosId>
                        {!_.isEmpty(filArr)
                          ? filArr[0]?.agentName
                          : tokenData?.seller_name}
                      </PosId>
                      <PosId small>
                        {!_.isEmpty(filArr)
                          ? filArr[0]?.userName
                            ? filArr[0]?.userName
                            : filArr[0]?.agentId
                          : tokenData?.user_name}
                      </PosId>
                    </PosDiv>
                  </div>
                )}
              </PosWrapper>
            )}
        </div>
      )}
      <CallButton
        id={"callus2"}
        style={{
          ...(lessthan993 &&
            id && {
              display: "flex",
              justifyContent: "center",
              alignItems: "center",
              flexDirection: "column",
            }),
        }}
      >
        {import.meta.env.VITE_BROKER !== "RB" &&
        token &&
        lessthan993 &&
        query.get("xutm") &&
        (localStorage?.SSO_user_motor ||
          import.meta.env.VITE_BROKER !== "RB") &&
        !leadSourceCheck &&
        true ? (
          <div
            className={token && lessthan767 ? "w-100 d-flex" : ""}
            style={{ justifyContent: "space-evenly" }}
          >
            <div>
              {(!_.isEmpty(tokenData) || !_.isEmpty(filArr)) && (
                <PosWrapper lessthan767={lessthan767}>
                  <PosId className="circle" onClick={() => showAgentDetails()}>
                    <PosId latter lessthan767={lessthan767}>
                      <text
                        style={{
                          marginBottom: "0.2px",
                          color: "white",
                        }}
                      >
                        {!_.isEmpty(filArr)
                          ? filArr[0]?.agentName.trim()[0]?.toUpperCase() || ""
                          : (tokenData?.seller_name[0] &&
                              tokenData?.seller_name
                                .trim()[0]
                                ?.toUpperCase()) ||
                            ""}
                      </text>
                    </PosId>
                  </PosId>
                  {!lessthan993 && (
                    <div>
                      <PosDiv>
                        <PosId>
                          {!_.isEmpty(filArr)
                            ? filArr[0]?.agentName
                            : tokenData?.seller_name}
                        </PosId>
                        <PosId small>
                          {!_.isEmpty(filArr)
                            ? filArr[0]?.userName
                              ? filArr[0]?.userName
                              : filArr[0]?.agentId
                            : tokenData?.user_name}
                        </PosId>
                      </PosDiv>
                    </div>
                  )}
                </PosWrapper>
              )}
            </div>
            {lessthan993 && id && id?.length < 20 && (
              <div
                style={{
                  fontSize: lessthan360 ? "9.5px" : "10px",
                  marginTop: "-2px",
                }}
              >
                Trace ID :<br /> {id}
              </div>
            )}
          </div>
        ) : lessthan993 && id && id?.length < 20 ? (
          <div
            style={{
              fontSize: lessthan360 ? "9px" : "11px",
              marginTop: lessthan767 ? "5px" : "0px",
              marginRight: "15px",
            }}
          >
            Trace ID : {id}
          </div>
        ) : (
          <noscript />
        )}
      </CallButton>
      {includeRoute.includes(location.pathname) &&
        (ut || import.meta.env.VITE_BROKER !== "OLA") &&
        (location.pathname === `/${type}/quotes` ||
          location.pathname === `/${type}/compare-quote` ||
          location.pathname === `/${type}/proposal-page` ||
          location.pathname === `/payment-success`
        ) && (
          <CallButton
            style={{ display: "none" }}
            id={lessthan767 ? "shareQuotes1" : "shareQuotes2"}
            onClick={() => {
              setSendQuotes(true);
            }}
          >
            <SendQuery className="fa fa-share-alt" />
          </CallButton>
        )}
      <div>
        {!lessthan993 && id && showDiv && id?.length < 20 && (
          <ConfirmButton
            className="d-flex align-items-center justify-content-center"
            title={copied ? "Copied!" : "Copy Trace ID"}
            style={{
              // width: "180px",
              cursor: "copy",
              width: "250px",
              gap: "10px",
            }}
          >
            <label
              className="m-0 p-0"
              style={{
                fontSize: "14px",
                paddingTop: "3px",
                cursor: "copy",
              }}
            >
              {copied ? "Trace ID Copied" : `Trace ID : ${id}`}
            </label>
            {copied ? (
              <i className="fa fa-check" aria-hidden="true"></i>
            ) : (
              <i
                className="fa fa-files-o"
                onClick={() => copyToClipboard(id)}
              ></i>
            )}
          </ConfirmButton>
        )}
      </div>
      <div>
        {location.pathname === `/${type}/compare-quote` && !lessthan767 && (
          <ConfirmButton
            className="d-flex align-items-center justify-content-center"
            onClick={
              validQuote?.length > 1
                ? handlePdfDownload
                : console.log("Run", validQuote)
            }
            id={"comparePdfDownload"}
            style={{
              background: pdfBackground,
              cursor: validQuote?.length > 1 ? "pointer" : "not-allowed",
            }}
          >
            <i
              className="fa fa-download"
              aria-hidden="true"
              style={{
                fontSize: "14px",
                cursor: validQuote?.length > 1 ? "pointer" : "not-allowed",
                margin: "0px 5px",
              }}
            ></i>
            <label
              className="m-0 p-0"
              style={{
                fontSize: "14px",
                paddingTop: "3px",
                cursor: validQuote?.length > 1 ? "pointer" : "not-allowed",
              }}
            >
              PDF
            </label>
          </ConfirmButton>
        )}
      </div>
      <div>
        {includeRouteShare.includes(location.pathname) &&
          !lessthan767 &&
          (ut || import.meta.env.VITE_BROKER !== "OLA") && (
            <ConfirmButton
              hide
              id={"shareQuotes1"}
              style={{
                cursor:
                  (((quoteComprehesive && quoteComprehesive.length >= 1) ||
                    (quotetThirdParty && quotetThirdParty.length >= 1) ||
                    (quoteShortTerm && quoteShortTerm.length >= 1)) &&
                    !quotesLoaded) ||
                  loc[2] === "proposal-page" ||
                  loc[2] === "compare-quote" ||
                  loc[1] === "payment-success"
                    ? "pointer"
                    : "not-allowed",
              }}
              className="d-flex align-items-center justify-content-center"
              onClick={() =>
                ((((quoteComprehesive && quoteComprehesive.length >= 1) ||
                  (quotetThirdParty && quotetThirdParty.length >= 1) ||
                  (quoteShortTerm && quoteShortTerm.length >= 1)) &&
                  !quotesLoaded) ||
                  loc[2] === "proposal-page" ||
                  loc[2] === "compare-quote" ||
                  loc[1] === "payment-success") ?
                setSendQuotes(true) :
                swal("Info", "Please wait for quotes to load", "info")
              }
            >
              <i
                className="fa mr-2 fa-share-alt"
                style={{
                  fontSize: "14px",
                }}
              ></i>

              <label
                className="m-0 p-0"
                style={{
                  fontSize: "14px",
                  paddingTop: "3px",
                  cursor:
                    (((quoteComprehesive && quoteComprehesive.length >= 1) ||
                      (quotetThirdParty && quotetThirdParty.length >= 1) ||
                      (quoteShortTerm && quoteShortTerm.length >= 1)) &&
                      !quotesLoaded) ||
                    loc[2] === "proposal-page" ||
                    loc[2] === "compare-quote" ||
                    loc[1] === "payment-success"
                      ? "pointer"
                      : "not-allowed",
                }}
              >
                Share{" "}
                {loc[2] === "proposal-page"
                  ? "Proposal"
                  : loc[1] === "payment-success"
                  ? "Policy"
                  : "Quotes"}
              </label>
            </ConfirmButton>
          )}
      </div>
      {/* SSO login Element please do not remove and edit */}
      <div id="loginWidget" className={`pos-el-login`}></div>
      {
        <div
          style={{
            display: "none",
          }}
        >
          <ConfirmButton
            className="d-flex align-items-center justify-content-center callusHover"
            onClick={() => setModal(true)}
            id={"callus1"}
          >
            {import.meta.env.VITE_BROKER === "UIB" ? (
              <MdOutlineMessage className="mr-2 mdoutline" />
            ) : (
              <img
                src={`${
                  import.meta.env.VITE_BASENAME !== "NA"
                    ? `/${import.meta.env.VITE_BASENAME}`
                    : ""
                }/assets/images/tlphn.png`}
                alt="phone"
                className="mr-2 box-decoration"
                height="19"
                style={{ cursor: "pointer" }}
              />
            )}

            <label
              className="m-0 p-0"
              style={{
                fontSize: "14px",
                paddingTop: "3px",
                cursor: "pointer",
              }}
            >
              Contact Us
            </label>
          </ConfirmButton>
        </div>
      }
    </>
  );
};
