/* eslint-disable jsx-a11y/anchor-is-valid */
import React from "react";
import styled from "styled-components";
import { useLocation } from "react-router";
import _ from "lodash";
import { useSelector } from "react-redux";
import { useMediaPredicate } from "react-media-hook";
import { MdOutlineMessage } from "react-icons/md";
import { ContactFn } from "components";
import { FaQuestionCircle } from "react-icons/fa";
import { IoShareSocialSharp } from "react-icons/io5";
import { IoMdCall } from "react-icons/io";
import { useState } from "react";
import FaqModal from "components/modal/FaqModal";
import { QRContainer, StyledDiv, StyledWhatsapp } from "./float-style";

const FloatButton = ({ downloadPdf }) => {
  const { quoteComprehesive, quotetThirdParty, quoteShortTerm } = useSelector(
    (state) => state.quotes
  );
  const { theme_conf } = useSelector((state) => state.home);

  const location = useLocation();
  const lessthan767 = useMediaPredicate("(max-width: 767px)");
  const lessthan993 = useMediaPredicate("(max-width: 993px)");
  const lessthan380 = useMediaPredicate("(max-width: 380px)");
  const loc = location.pathname ? location.pathname.split("/") : "";
  const type = !_.isEmpty(loc) ? (loc?.length >= 2 ? loc[1] : "") : "";
  const includeRouteShare = [
    `/${type}/quotes`,
    `/${type}/compare-quote`,
    `/${type}/proposal-page`,
    `/payment-success`,
  ];

  const [open, setOpen] = useState(false);
  const [showQR, setShowQR] = useState(false);

  const quotes =
    (quoteComprehesive && quoteComprehesive.length >= 1) ||
    (quotetThirdParty && quotetThirdParty.length >= 1) ||
    (quoteShortTerm && quoteShortTerm.length >= 1) ||
    loc[2] === "proposal-page" ||
    loc[2] === "compare-quote";

  const openQR = () => {
    window.open(
      "https://api.whatsapp.com/send/?phone=919667753599&text=Hi&type=phone_number&app_absent=0",
      "_blank"
    );
  };

  return (
    <StyledDiv>
      <div id="">
        {import.meta.env.VITE_BROKER === "BAJAJ" && (
          <>
            <StyledWhatsapp
              loc={loc}
              lessthan993={lessthan993}
              lessthan380={lessthan380}
              quotes={quotes}
              includeRouteShare={includeRouteShare}
              location={location}
              onMouseEnter={() => setShowQR(true)}
              onMouseLeave={() => setShowQR(false)}
              // className="floatBtn"
              role="button"
              title="whatsapp"
              id="reddit"
              onClick={() => openQR()}
            >
              <img
                src={`${window.location.origin}${
                  import.meta.env.VITE_BASENAME !== "NA"
                    ? `/${import.meta.env.VITE_BASENAME}`
                    : ""
                }/assets/images/whatsapp.png`}
                style={{ marginLeft: "4.9%", height: "60px", width: "60px" }}
              />
            </StyledWhatsapp>
            <QRContainer
              className="qr_code_popup"
              showQR={showQR}
              onClick={() => openQR()}
              location={includeRouteShare.includes(location.pathname) && quotes}
            >
              <h6>Scan QR code to chat on WhatsApp</h6>
              <img
                src={`${window.location.origin}${
                  import.meta.env.VITE_BASENAME !== "NA"
                    ? `/${import.meta.env.VITE_BASENAME}`
                    : ""
                }/assets/images/bajaj_qr.jpg`}
                alt="Whatsapp QR code"
              />
              <h6>+91 9667-753-599</h6>
            </QRContainer>
          </>
        )}
        {loc[2] === "compare-quote" && lessthan767 ? (
          <noscript />
        ) : (
          <div
            style={{
              display: "none",
              position: "fixed",
              top:
                includeRouteShare.includes(location.pathname) && quotes
                  ? "67%"
                  : "76%",
              right: "1%",
              zIndex: "9",
            }}
            className="floatBtn"
            role="button"
            title="FAQ"
            id="reddit"
            onClick={() => setOpen(true)}
          >
            <FaQuestionCircle size={lessthan380 ? 38 : 42} className="p-2" />
          </div>
        )}
        {includeRouteShare.includes(location.pathname) && (
          <a
            style={{
              position: "fixed",
              top:
                loc[2] === "quotes" && lessthan380
                  ? "69%"
                  : lessthan380
                  ? "76.5%"
                  : "75%",
              right: "1%",
              zIndex: "9",
              visibility:
                quotes || loc[1] === "payment-success" ? "visible" : "hidden",
            }}
            className="floatBtn"
            role="button"
            onClick={() =>
              document?.getElementById("shareQuotes1") &&
              document?.getElementById("shareQuotes1").click()
            }
            title="share"
            id="reddit"
          >
            <IoShareSocialSharp size={lessthan380 ? 38 : 40} className="p-2" />
          </a>
        )}
        <a
          style={{
            position: "fixed",
            right: "1%",
            zIndex: "9",
            top:
              loc[2] === "quotes" && lessthan380
                ? "79%"
                : lessthan380
                ? "86%"
                : lessthan993
                ? "83%"
                : "85%",
          }}
          className="floatBtn"
          role="button"
          href={
            lessthan767 &&
            `tel:${theme_conf?.broker_config?.phone || ContactFn()}`
          }
          onClick={() =>
            !lessthan767 &&
            document?.getElementById("callus1") &&
            document?.getElementById("callus1").click()
          }
          title="call us"
          id="linkedin"
        >
          {import.meta.env.VITE_BROKER === "UIB" ? (
            <MdOutlineMessage className="outlinemd" />
          ) : (
            <IoMdCall size={lessthan380 ? 38 : 40} className="p-2" />
          )}
        </a>
      </div>
      {open && false && <FaqModal show={open} onHide={() => setOpen(false)} />}
    </StyledDiv>
  );
};

export default FloatButton;
