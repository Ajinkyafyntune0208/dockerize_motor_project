import React, { useState, useRef, useEffect } from "react";
import { Link, useHistory, useLocation } from "react-router-dom";
import "./Header.scss";
import styled, { createGlobalStyle } from "styled-components";
import "styled-components/macro";
import CallMe from "components/Popup/callMe/CallMe";
import SendQuotes from "components/Popup/sendQuote/SendQuotes";
import { useOutsideClick } from "../../../hoc";
// import { setSearchQuery } from '../../modules/home/home.slice';
import { useDispatch, useSelector } from "react-redux";
import { _generateKey, reloadPage } from "../../../utils";
import _ from "lodash";
import { Button, ContactFn } from "components";
import { useMediaPredicate } from "react-media-hook";
import { setTempData } from "../../../modules/quotesPage/filterConatiner/quoteFilter.slice";
import { downloadFile } from "utils";
import DropdownOnHover from "./dropdown/DropdownOnHover";
import {
  advertising,
  financing,
  investing,
  protecting,
} from "./dropdown/helper";
import { _comparePDFTracking } from "analytics/compare-page/compare-tracking";
import { TypeReturn } from "modules/type";

const Header = () => {
  const location = useLocation();
  const history = useHistory();
  const query = new URLSearchParams(location.search);
  const enquiry_id = query.get("enquiry_id");
  const lessthan767 = useMediaPredicate("(max-width: 767px)");
  const lessthan993 = useMediaPredicate("(max-width: 993px)");

  const loc = location.pathname ? location.pathname.split("/") : "";

  const type = !_.isEmpty(loc) ? (loc?.length >= 2 ? loc[1] : "") : "";
  const includeRoute = [
    `/${type}/proposal-page`,
    `/${type}/review`,
    `/${type}/quotes`,
    `/${type}/compare-quote`,
  ];

  const includeRouteStickyNavBar = [`/${type}/quotes`];
  const includeRouteQID = [
    `/${type}/proposal-page`,
    `/${type}/review`,
    `/${type}/quotes`,
  ];
  const includeRouteShare = [
    `/${type}/quotes`,
    `/${type}/compare-quote`,
    `/${type}/proposal-page`,
  ];
  const excludeRoute = [
    `/${type}/fg/payment/success`,
    `/${type}/payment/success`,
    `/${type}/bharti/payment/success`,
    `/${type}/otp-verification-tata`,
    `/${type}/payment/failed`,
    `/${type}/payment-gateway`,
    `/${type}/404`,
  ];

  const excludeRoutePayment = [
    `/${type}/fg/payment/success`,
    `/${type}/payment/success`,
    `/${type}/bharti/payment/success`,
    `/${type}/payment/failed`,
    `/${type}/mailed-policy`,
    `/${type}/`,
    `/${type}/404`,
  ];

  const [modal, setModal] = useState(false);
  const [sendQuotes, setSendQuotes] = useState(false);
  const { temp_data, theme_conf } = useSelector((state) => state.home);
  const { comparePdfData } = useSelector((state) => state.quotes);
  const [navCheck, setNavCheck] = useState(false);
  const dispatch = useDispatch();

  const navbarNavigation = (
    <ul
      className="navbar__nav"
      css={`
        top: 45px;
        left: 400px;
        position: absolute;

        @media (max-width: 1250px) {
          left: 300px;
        }
      `}
    >
      <li>
        <DropdownOnHover label={"PROTECTING"} items={protecting} hasLink />

        <i className="fa fa-chevron-down"></i>
      </li>
      <li>
        <DropdownOnHover label={"INVESTING"} items={investing} hasLink />

        <i className="fa fa-chevron-down"></i>
      </li>
      <li>
        <DropdownOnHover label={"FINANCING"} items={financing} hasLink />

        <i className="fa fa-chevron-down"></i>
      </li>
      <li>
        <DropdownOnHover label={"ADVISING"} items={advertising} hasLink />

        <i className="fa fa-chevron-down"></i>
      </li>

      <li
        css={`
          margin-left: 150px !important;
          @media (max-width: 1350px) {
            margin-left: 50px !important;
          }
        `}
      >
        <span>1800-270-7000</span>
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="14"
          height="13"
          viewBox="0 0 14 13"
          style={{ paddingLeft: "5px", width: "19px" }}
        >
          <path
            fill="none"
            fillRule="evenodd"
            stroke="#FFF"
            strokeLinecap="square"
            d="M10.143 7.429L8.429 9.143 3.857 4.57l1.714-1.714L2.714 0 1 1.714C1 7.394 5.605 12 11.286 12L13 10.286l-2.857-2.857z"
          />
        </svg>
      </li>
      <li>
        <span
          onClick={() => {
            history.push("/");
          }}
        >
          HOME
        </span>
      </li>
    </ul>
  );

  const id = query.get("enquiry_id");

  useEffect(() => {
    if (sendQuotes) {
      dispatch(
        setTempData({
          sendQuote: true,
        })
      );
    } else {
      dispatch(
        setTempData({
          sendQuote: false,
        })
      );
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [sendQuotes]);

  const dropDownRef = useRef(null);
  useOutsideClick(dropDownRef, () => setNavCheck(false));

  const UrlFn = () => {
    switch (import.meta.env?.VITE_BROKER) {
      case "OLA":
        if (
          import.meta.env?.VITE_API_BASE_URL === "https://olaapi.fynity.in/api"
        ) {
          return "https://ola-dashboard.fynity.in/";
        } else {
          return "http://uatoladashboard.fynity.in/";
        }
      case "FYNTUNE":
        return "";
      case "ABIBL":
        if (
          import.meta.env?.VITE_API_BASE_URL ===
          "https://apiabibl-preprod-carbike.fynity.in/api"
        ) {
          return "https://cpuat.adityabirlainsurancebrokers.com/";
        } else if (
          import.meta.env?.VITE_API_BASE_URL ===
          "https://apiabibl-carbike.fynity.in/api"
        ) {
          return import.meta.env.VITE_PROD === "YES"
            ? "https://protect.adityabirlainsurancebrokers.com/"
            : "https://cpuat.adityabirlainsurancebrokers.com/";
        } else {
          return import.meta.env.VITE_PROD === "YES"
            ? "https://protect.adityabirlainsurancebrokers.com/"
            : "https://cpuat.adityabirlainsurancebrokers.com/";
        }
      default:
        break;
    }
  };

  function copyToClipboard(text) {
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

  return (
    <HeadContainer>
      <Navbar className="d-block" padding={loc[2] !== "quotes" && !lessthan767}>
        {/*Upper-Header*/}
        <div
          className="navbarAbibl"
          css={`
            padding: 0 69px !important;
            align-items: center !important;
            @media (max-width: 767px) {
              padding: 0 18px !important;
            }
          `}
        >
          <div className="navbar__logo">
            <a href={UrlFn()}>
              <img
                src={`${
                  import.meta.env.VITE_BASENAME !== "NA"
                    ? `/${import.meta.env.VITE_BASENAME}`
                    : ""
                }/assets/images/adityaBirlaLogo.png`}
                alt=""
                height="49px"
              />
            </a>
          </div>
          {/* {navbarNavigation} */}
        </div>
        {/*Sub-Header*/}
        <div
          className="secondaryNavbar"
          css={`
            height: 24px;
            display: flex;
            justify-content: space-between;
          `}
        >
          <h1
            className="secondaryNavbar__header"
            css={`
              font-size: 20px !important;
              font-family: ${({ theme }) =>
                theme?.fontFamily
                  ? theme?.fontFamily
                  : `pfhandbook_regular`}!important;

              padding-top: 10px;
              letter-spacing: 1px;
              padding-left: 69px !important;
              @media (max-width: 767px) {
                font-size: 16px !important;
                padding-left: 18px !important;
              }
            `}
          >
            Insurance Advisory
          </h1>
          <div>
            {enquiry_id && lessthan767 && (
              <p
                onClick={() => copyToClipboard(id)}
                css={`
                  color: #fff;
                  position: relative;
                  left: -9px;
                  font-weight: 100;
                  font-size: 11px;
                  line-height: 1px;
                  top: 8px;
                  & span {
                    user-select: all;
                  }
                `}
              >
                ID - <span style={{ fontSize: "11px" }}>{enquiry_id}</span>
              </p>
            )}
          </div>
          <ButtonContainer style={{ display: "none" }}>
            {!excludeRoute.includes(location.pathname) && (
              <>
                <CallButton id={"callus2"}>
                  <a
                    href={`tel:${
                      theme_conf?.broker_config?.phone || ContactFn()
                    }`}
                  >
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      viewBox="0 0 24 24"
                      fill="#fff"
                    >
                      <path d="M0 0h24v24H0V0z" fill="none" />
                      <path d="M21 15.46l-5.27-.61-2.52 2.52c-2.83-1.44-5.15-3.75-6.59-6.59l2.53-2.53L8.54 3H3.03C2.45 13.18 10.82 21.55 21 20.97v-5.51z" />
                    </svg>
                  </a>
                </CallButton>
                {includeRoute.includes(location.pathname) && (
                  <CallButton
                    id={lessthan767 ? "shareQuotes1" : "shareQuotes2"}
                    onClick={() => {
                      setSendQuotes(true);
                    }}
                  >
                    <SendQuery className="fa fa-share-alt" />
                  </CallButton>
                )}

                <div>
                  {location.pathname === `/${type}/compare-quote` && true && (
                    <ConfirmButton
                      className="d-flex align-items-center justify-content-center"
                      onClick={() => copyToClipboard(id)}
                      style={{
                        // width: "180px",
                        cursor: "copy",
                        width: "250px",
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
                        Trace Id : {id}
                      </label>
                    </ConfirmButton>
                  )}
                </div>

                <div style={{ display: "none" }}>
                  {location.pathname === `/${type}/compare-quote` &&
                    !lessthan767 && (
                      <ConfirmButton
                        className="d-flex align-items-center justify-content-center"
                        onClick={handlePdfDownload}
                        id={"comparePdfDownload"}
                      >
                        <i
                          className="fa fa-download"
                          aria-hidden="true"
                          style={{
                            fontSize: "14px",
                            cursor: "pointer",
                            margin: "0px 5px",
                          }}
                        ></i>

                        <label
                          className="m-0 p-0"
                          style={{
                            fontSize: "14px",
                            paddingTop: "3px",
                            cursor: "pointer",
                          }}
                        >
                          PDF
                        </label>
                      </ConfirmButton>
                    )}
                </div>
                <div style={{ display: "none" }}>
                  <div>
                    {includeRouteShare.includes(location.pathname) &&
                      !lessthan767 && (
                        <ConfirmButton
                          id={"shareQuotes1"}
                          style={{
                            cursor:
                              import.meta.env?.VITE_API_BASE_URL ===
                              "https://olaapi.fynity.in/api"
                                ? "not-allowed"
                                : "pointer",
                          }}
                          className="d-flex align-items-center justify-content-center"
                          onClick={() =>
                            import.meta.env?.VITE_API_BASE_URL ===
                            "https://olaapi.fynity.in/api"
                              ? {}
                              : setSendQuotes(true)
                          }
                          broker={
                            import.meta.env?.VITE_API_BASE_URL ===
                            "https://olaapi.fynity.in/api"
                          }
                        >
                          <i
                            className="fa mr-2 fa-share-alt"
                            style={{
                              fontSize: "14px",
                              cursor:
                                import.meta.env?.VITE_API_BASE_URL ===
                                "https://olaapi.fynity.in/api"
                                  ? "not-allowed"
                                  : "pointer",
                            }}
                          ></i>

                          <label
                            className="m-0 p-0"
                            style={{
                              fontSize: "14px",
                              paddingTop: "3px",
                              cursor:
                                import.meta.env?.VITE_API_BASE_URL ===
                                "https://olaapi.fynity.in/api"
                                  ? "not-allowed"
                                  : "pointer",
                            }}
                          >
                            Share{" "}
                            {loc[2] === "proposal-page"
                              ? "Proposal"
                              : loc[2] === "payment-confirmation"
                              ? "Payment"
                              : "Quotes"}
                          </label>
                        </ConfirmButton>
                      )}
                  </div>

                  <div style={{ display: "none" }}>
                    <ConfirmButton
                      className="d-flex align-items-center justify-content-center"
                      onClick={() => setModal(true)}
                      id={"callus1"}
                    >
                      <img
                        src={`${
                          import.meta.env.VITE_BASENAME !== "NA"
                            ? `/${import.meta.env.VITE_BASENAME}`
                            : ""
                        }/assets/images/tlphn.png`}
                        alt="phone"
                        className="mr-2 box-decoration"
                        height="16"
                        style={{ cursor: "pointer" }}
                      />
                      <label
                        className="m-0 p-0"
                        style={{
                          fontSize: "14px",
                          paddingTop: "3px",
                          cursor: "pointer",
                        }}
                      >
                        Talk To Us
                      </label>
                    </ConfirmButton>
                  </div>
                </div>
              </>
            )}
          </ButtonContainer>
        </div>
        {/*Bottom-Header*/}
        <div
          className="BottomHeader"
          css={`
            height: 24px;
            background-color: #c7222a;
            @media (max-width: 767px) {
              padding: 0 18px;
            }
          `}
        >
          <h1
            className="BottomHeader__header"
            css={`
              font-size: 24px;
              position: relative;
              top: -1.6px;
              font-family: ${({ theme }) =>
                theme?.fontFamily
                  ? theme?.fontFamily
                  : `pfhandbook_regular`}!important;
              color: #fff !important;
              padding-left: 69px;
              @media (max-width: 767px) {
                position: relative;
                top: 2.3px;
                font-size: 18px;
                padding-left: unset;
              }
            `}
          >
            Aditya Birla Insurance Brokers Limited
          </h1>
          {enquiry_id && (
            <p
              onClick={() => copyToClipboard(id)}
              css={`
                color: #fff;
                position: absolute;
                right: ${loc[2] === "quotes" ? "14px" : "90px"};
                top: 124px;
                font-weight: 100;
                font-size: 15px;
                line-height: 1px;
                & span {
                  user-select: all;
                }
                @media (max-width: 769px) {
                  display: none;
                }
              `}
            >
              Enquiry ID - <span>{enquiry_id}</span>
            </p>
          )}
        </div>
      </Navbar>

      {modal && <CallMe show={modal} onClose={setModal} />}
      {sendQuotes && (
        <SendQuotes
          show={sendQuotes}
          onClose={setSendQuotes}
          sendPdf={loc[2] === "compare-quote" ? true : false}
          comparePdfData={comparePdfData}
          type={type}
        />
      )}
      <GlobalStyle />
    </HeadContainer>
  );
};

const GlobalStyle = createGlobalStyle`

 ${({ theme }) =>
   theme?.fontFamily &&
   `.flaticon-setup {
  &:after {
    font-family: ${theme?.fontFamily} || roboto;
     }
}
.secondaryNavbar {
  &__header {
    font-family: ${theme?.fontFamily} !important;
  }
 }
`};
  
${import.meta.env?.VITE_BROKER === "TATA" && "roboto"}
`;

const HeadContainer = styled.div`
  display: flex;
  justify-content: center;
`;

const Navbar = styled.div`
  width: ${(props) => (props.padding ? "90%" : "100%")};
  @media (max-width: 768px) {
    // padding: 18px 25px 18px 15px;
  }

  @media (max-width: 993px) {
    position: relative !important;

    z-index: 0;
  }
`;
const CallButton = styled.span`
  display: none;
  @media (max-width: 767px) {
    margin-top: -12.3px !important;
    color: white !important;
    display: inline-block;
    padding-top: 5px;
    margin-right: 15px;
    & > a > svg {
      width: 28px;
      height: 35px;
      padding: 4px;
      border-radius: 50%;
    }
  }
`;

const ConfirmButton = styled.button`
  font-family: ${({ theme }) =>
    theme?.fontFamily ? theme?.fontFamily : `Inter-Regular`};
  position: relative;
  top: -4.5px;
  transition: 0.2s ease-in-out;
  background-color: #d68d87;
  border: #d68d87;
  padding: 11px 0;
  border-radius: 4px;
  z-index: 2;
  width: 135px;
  height: 20px;
  font-size: 16px;
  color: #fff;
  margin-right: 0px;
  font-weight: 400;
  outline: none;
  margin-right: 30px;
  cursor: pointer;
  &:focus {
    outline: none;
  }
  @media (max-width: 768px) {
    display: none;
  }
  & svg {
    width: 12px;
    height: 8px;
    margin-right: 6px;
  }
  .box-decoration {
    filter: brightness(0) invert(1);
  }
  &:hover {
    color: #c7222a;
    .box-decoration {
      filter: invert(20%) sepia(61%) saturate(3261%) hue-rotate(341deg)
        brightness(95%) contrast(100%);
    }
  }
`;

const SendQuery = styled.i`
  color: #fff;
  max-height: 38px;
  font-size: 17px;
  border-radius: 50px;
  padding: 8px 8px 7px 7px;
  cursor: pointer;
`;

const ButtonContainer = styled.div`
  display: flex;
  justify-content: flex-end;
  padding-top: 9px;
`;

export default Header;
